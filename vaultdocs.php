<?php
/*
Plugin Name: Vault Docs
Plugin URI: http://factage.com/yu-ji/tag/vault-docs
Description: This plugin provides automated backup posts and pages to Google Docs.
Author: yu-ji
Version: 0.9.2
Author URI: http://factage.com/yu-ji/
*/

class VaultDocs {
    private $debug = true;
    private $auth = null;
    private $options = array();
    private $httpResponseHeader = null;
    private $disabled = false;

    private static $defaultOptions = array(
        'email' => '',
        'token' => '',
        'enabled' => '',
        'folder' => 'return get_option("home");',
        'folder_id' => '',
        'posts_folder_id' => '',
        'pages_folder_id' => '',
    );
    private static $gdocsPrivateEndpointUrl = 'http://docs.google.com/feeds/default/private/full/';
    private static $gdocsDownloadEndpointUrl = 'http://docs.google.com/feeds/download/documents/Export?docID=%s&exportFormat=html';
    private static $gdocsUserMetaEndpointUrl = 'http://docs.google.com/feeds/metadata/default';

    private static $googleAuthSubRequestUrl = 'https://www.google.com/accounts/AuthSubRequest';
    private static $googleAuthSubSessionTokenUrl = 'https://www.google.com/accounts/AuthSubSessionToken';
    private static $googleAuthSubTokenInfoUrl = 'https://www.google.com/accounts/AuthSubTokenInfo';
    private static $googleAuthSubRevokeTokenUrl = 'https://www.google.com/accounts/AuthSubRevokeToken';
    private static $googleAuthSubScope = 'http://docs.google.com/feeds/';

    /**
     * Initialize plugin options, add actions.
     */
    public function __construct() {
        // initialize options
        if(function_exists('get_option')) {
            $this->options = get_option(get_class($this));

            $updated = false;
            foreach(self::$defaultOptions as $key => $value) {
                if(!isset($this->options[$key])) {
                    $this->options[$key] = eval($value);
                    $updated = true;
                }
            }

            if($updated) {
                $this->updateOptions();
            }
        }

        // l10n
        if(function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('vaultdocs', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/languages');
        }

        if(function_exists('add_action')) {
            // add hooks
            add_action('save_post', array(&$this, 'actionSavePost'));
            add_action('admin_action_editpost', array(&$this, 'actionEditPost'));

            // add menu pages
            add_action('admin_menu', array(&$this, 'addAdminPages'));

            // add actions
            add_action('admin_action_vaultdocs_backgroundBackup', array(&$this, 'backgroundBackup'));
            add_action('admin_action_vaultdocs_backgroundRestore', array(&$this, 'backgroundRestore'));
            add_action('admin_action_vaultdocs_authSubRequest', array(&$this, 'authSubRequest'));
            add_action('admin_action_vaultdocs_authSubResponse', array(&$this, 'authSubResponse'));
            add_action('admin_action_vaultdocs_authSubRevoke', array(&$this, 'authSubRevoke'));

            // check authorized on GoolgeDocs
            if(!$this->options['enabled']) {
                $message = "echo '<div class=\"error\"><p>".sprintf(__('Vault Docs is not authorized on Google account, please check your <a href="%s">Vault Docs options</a>', 'vaultdocs'), admin_url('options-general.php?page=vaultdocs'))."</p></div>';";
                add_action('admin_notices', create_function('', $message));
            }
        }
    }

    /**
     * Check plugin compatible.
     */
    public static function activation() {
        try{
            // check allow_url_fopen
            if(!ini_get('allow_url_fopen')) {
                throw new Exception(__('allow_url_fopen is not enabled on PHP. Vault Docs could not be activated.', 'vaultdocs'));
            }

            // check file_get_contents to docs.google.com
            $context = array(
                'http' => array(
                    'timeout'  => 3,
                )
            );
            $result = @file_get_contents('http://docs.google.com/', false, stream_context_create($context));
            if($result === false) {
                throw new Exception(__('Cannot connect to docs.google.com. Vault Docs could not be activated.', 'vaultdocs'));
            }

            // check OpenSSL
            if(!function_exists('openssl_open')) {
                throw new Exception(__('Disabled OpenSSL on PHP. Vault Docs could not be activated.', 'vaultdocs'));
            }

            // check allow_url_fopen
            if(!class_exists('SimpleXMLElement')) {
                throw new Exception(__('Disabled SimpleXMLElement on PHP. Vault Docs could not be activated.', 'vaultdocs'));
            }
        }catch(Exception $e) {
            $plugin_basename = dirname(plugin_basename(__FILE__));
            deactivate_plugins($plugin_basename.'/vaultdocs.php', true);
            echo '<p style="color: #f00;font-weight: bold;">'.$e->getMessage().'</p>';
            trigger_error('Activate Vault Docs error.', E_USER_ERROR);
        }
    }

    /**
     * Login to Google Docs.
     */
    private function checkAuthSub() {
        if(!empty($this->options['token'])) {
            $headers = array(
                'Authorization: AuthSub token="'.$this->options['token'].'"',
            );
            $context = array(
                'http' => array(
                    'method'  => 'GET',
                    'header'  => join("\r\n", $headers),
                ),
            );
            $result = @file_get_contents(self::$googleAuthSubTokenInfoUrl, false, stream_context_create($context));
            $authResult = !empty($result);
            $enabled = !empty($this->options['enabled']);
            if($authResult != $enabled) {
                $this->options['enabled'] = $authResult;
                $this->updateOptions();
            }
            return $authResult;
        }
        return false;
    }

    /**
     * Action to Google Docs.
     * @param string $url Url of endpoint
     * @param string $method Method of request
     * @param mixed $query Post queries
     * @param string $contentType Request Content-Type
     * @param array $headers Request headers
     */
    private function actionGoogleDocs($url, $method='GET', $query=null, $contentType='application/x-www-form-urlencoded', $headers=array()) {
        if(!$this->checkAuthSub()) {
            return false;
        }

        $headers = array_merge($headers, array(
            'Authorization: AuthSub token="'.$this->options['token'].'"',
            'GData-Version: 3.0',
        ));
        $context = array(
            'http' => array(
                'method'  => $method,
            ),
        );

        // if has query, add ContentType and ContentLength
        if(!empty($query)) {
            if(is_array($query)) {
                $query = http_build_query($query);
            }
            $context['http']['content'] = $query;
            $headers[] = 'Content-Type: '.$contentType;
            $headers[] = "Content-Length: ".strlen($query);
        }

        $context['http']['header'] = join("\r\n", $headers);
        $result = @file_get_contents($url, false, stream_context_create($context));
        array_shift($http_response_header);
        foreach($http_response_header as $header) {
            list($key, $value) = explode(':', $header, 2);
            $this->httpResponseHeader[trim($key)] = trim($value);
        }
        if(!empty($result)) {
            if(strpos($this->httpResponseHeader['Content-Type'], 'application/atom+xml;') !== false) {
                // fix namespace
                $result = str_replace('gd:', 'gd_', $result);
                return new SimpleXMLElement($result);
            }else{
                return $result;
            }
        }
        return false;
    }

    /**
     * Execute backgroud method.
     * @param string $url Url of endpoint
     */
    private function background($url) {
        $url = parse_url($url);
        if(empty($url['port'])) {
            $url['port'] = 80;
        }

        $fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, 0.5);
        if($fp) {
            socket_set_blocking($fp, false);
            $cookies = '';
            foreach($_COOKIE as $key => $value) {
                $cookies .= $key.'='.$value.'; ';
            }
            $req = array(
                'GET '.$url['path'].'?'.$url['query'].' HTTP/1.0',
                'Host: '.$url['host'],
                'Cookie: '.$cookies,
            );
            fputs($fp, join($req, "\r\n")."\r\n\r\n");
            fclose($fp);
            return true;
        }else{
            $this->log('Could not connect to '.$url['host'].':'.$url['port'].' for background connection.');
        }
        return false;
    }

    /**
     * Called from action of  'admin_action_editpost'.
     * @param int $postId Post id
     */
    public function actionEditPost($postId) {
        if($this->disabled) {
            return;
        }
        // remove update vault_docs_resource_id
        if(!empty($_POST['meta'])) {
            foreach($_POST['meta'] as $meta_id => $kv) {
                if($kv['key'] == 'vault_docs_resource_id' && !empty($kv['value'])) {
                    unset($_POST['meta'][$meta_id]);
                    break;
                }
            }
        }
    }

    /**
     * Called from action of  'save_post'.
     * @param int $postId Post id
     */
    public function actionSavePost($postId) {
        if($this->disabled) {
            return;
        }

        // skip save revision
        if(wp_is_post_revision($postId)) {
            return;
        }

        $post = get_post($postId);

        // backup only pages and posts
        if(!in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        // if trashed, not backup
        if($post->post_status == 'trash' && !get_post_meta($postId, 'vault_docs_resource_id', true)) {
            return;
        }

        $url = admin_url('admin.php?action=vaultdocs_backgroundBackup&id='.urlencode($postId));
        $result = $this->background($url);
        if(!$result) {
            ob_start();
            $this->backgroundBackup($id);
            ob_clean();
        }
    }

    /**
     * Called from action of  'admin_action_vaultdocs_backgroundBackup'.
     * @param int $postId Post id
     */
    public function backgroundBackup($postId=null) {
        if(empty($postId) && !empty($_GET['id'])) {
            $postId = $_GET['id'];
        }
        if(!$postId) {
            echo 'VAULTDOCS.next();';
            return;
        }
        $this->log('called backup, postId is '.$postId);

        // check and create folders
        $this->checkFolders();

        $post = get_post($postId);
        $resourceId = get_post_meta($postId, 'vault_docs_resource_id', true);
        $contentType = 'multipart/related; boundary=END_OF_PART';

        $etag = null;

        // check exists document
        if(!empty($resourceId)) {
            $this->log('has resourceId '.$resourceId);
            $url = self::$gdocsPrivateEndpointUrl.urlencode($resourceId);
            $result = $this->actionGoogleDocs($url);
            if($result) {
                // if document is trashed, create new backup
                if(!$result->gd_deleted) {
                    // get etag for update
                    $attrs = $result->attributes();
                    $etag = htmlspecialchars($attrs['gd_etag']);
                    $this->log('exists document, etag is '.$etag);
                }else{
                    $this->log('but deleted..');
                }
            }
        }

        if(empty($etag)) {
            // new backup
            $this->log('will new backup');
            $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['folder_id']).'/contents';
            if($post->post_type == 'post') {
                $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['posts_folder_id']).'/contents';
            }
            if($post->post_type == 'page') {
                $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['pages_folder_id']).'/contents';
            }
            $method = 'POST';
            $atom = <<<POST
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007">
  <category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/docs/2007#document"/>
  <title>{$post->post_title}</title>
  <docs:writersCanInvite value="false" />
</entry>
POST;
            $multipart = array(
                'application/atom+xml' => $atom,
                'text/html' => $post->post_content,
            );
            $multipart = $this->createMultipart($multipart, 'END_OF_PART');
            $this->log($multipart);
        }else{
            // exists document
            if($post->post_status == 'trash') {
                // the post is trashed, the document move to trash
                $this->log($resourceId.' will delete');
                $url = self::$gdocsPrivateEndpointUrl.urlencode($resourceId);
                $this->actionGoogleDocs($url, 'DELETE', null, null, array('If-Match: *'));
                delete_post_meta($postId, 'vault_docs_backup');
                delete_post_meta($postId, 'vault_docs_resource_id');
                echo 'VAULTDOCS.next();';
                return;
            }else{
                // update exists document
                $this->log($resourceId.' will update backup');
                $url = self::$gdocsPrivateEndpointUrl.urlencode($resourceId);
                $method = 'PUT';
                $atom = <<<PUT
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gd="http://schemas.google.com/g/2005" gd:etag="{$etag}">
  <category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/docs/2007#document"/>
  <title>{$post->post_title}</title>
</entry>
PUT;
                $multipart = array(
                    'application/atom+xml' => $atom,
                    'text/html' => $post->post_content,
                );
                $multipart = $this->createMultipart($multipart, 'END_OF_PART');
            }
        }
        $result = $this->actionGoogleDocs($url, $method, $multipart, 'multipart/related; boundary=END_OF_PART');
        if($result) {
            $resourceId = (String)$result->gd_resourceId;
            if(empty($etag)) {
                update_post_meta($postId, 'vault_docs_backup', 1);
                update_post_meta($postId, 'vault_docs_resource_id', $resourceId);
                $this->log('new backup done '.$resourceId);
            }else{
                $this->log('update backup done '.$resourceId);
            }
        }else{
            $this->log('backup failed');
        }
        // set post
        unset($post->post_content);
        unset($post->post_excerpt);
        $post->vault_docs_resource_id = get_post_meta($postId, 'vault_docs_resource_id', true);
        require(dirname(__FILE__).'/background_backup.php');
    }

    /**
     * Called from action of  'admin_action_vaultdocs_backgroundRestore'.
     */
    public function backgroundRestore() {
        global $post;

        $this->disabled = true;

        $resourceId = !empty($_GET['id']) ? $_GET['id'] : '';
        if(!$resourceId) {
            echo 'VAULTDOCS.next();';
            return;
        }

        $status = !empty($_GET['status']) ? $_GET['status'] : 'publish';

        $this->log('called restore, resourceId is '.$resourceId);

        // retrieve backup document
        $url = self::$gdocsPrivateEndpointUrl.urlencode($resourceId);
        $result = $this->actionGoogleDocs($url);
        if(!$result) {
            echo 'VAULTDOCS.next();';
            return;
        }

        // get title
        $title = (String)$result->title;

        // get folder
        $type = 'post';
        foreach($result->link as $link) {
            $attrs = $link->attributes();
            if($attrs['href'] == self::$gdocsPrivateEndpointUrl.urlencode($this->options['pages_folder_id'])) {
                $type = 'page';
                break;
            }
        }

        // retrieve backup content
        $url = sprintf(self::$gdocsDownloadEndpointUrl, urlencode($resourceId));
        $result = $this->actionGoogleDocs($url);
        $lines = explode("\n", $result);
        foreach($lines as $line) {
            array_shift($lines);
            if(strpos($line, '</head>') !== false) {
                break;
            }
        }
        $content = join("\n", $lines);
        $content = preg_replace('/<\/?(body|html)[^<>]*>/im', '', $content);
        $content = preg_replace('/<!--((?:(?!-->).)+)-->/ms', '', $content, 1);
        $content = trim($content);

        $newPost = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
        );

        // find already backup post
        $query = array(
            'meta_key' => 'vault_docs_resource_id',
            'meta_compare' => '=',
            'meta_value' => $resourceId,
            'post_type' => 'any',
            'post_status' => 'publish,pending,draft,future,private',
        );
        query_posts($query);
        if(have_posts()) {
            the_post();
            $newPost['ID'] = $post->ID;
            unset($newPost['post_status']);
        }
        if(($postId = wp_update_post($newPost))) {
            update_post_meta($postId, 'vault_docs_backup', 1);
            update_post_meta($postId, 'vault_docs_resource_id', $resourceId);
            $post = get_post($postId);
            unset($post->post_content);
            unset($post->post_excerpt);
            $post->vault_docs_resource_id = $resourceId;
        }else{
            $post = null;
        }

        require(dirname(__FILE__).'/background_restore.php');
    }

    /**
     * Called from action of 'admin_menu'.
     */
    public function addAdminPages() {
        if(function_exists('add_menu_page')) {
            $plugin_basename = dirname(plugin_basename(__FILE__));
            add_menu_page('Vault Docs', __('Vault Docs', 'vaultdocs'), 'administrator', 'vaultdocs', array(&$this, 'showOptions'), null);
            add_submenu_page('vaultdocs', __('Vault Docs', 'vaultdocs'), __('Options', 'vaultdocs'), 'administrator', 'vaultdocs', array(&$this, 'showOptions'));
            add_submenu_page('vaultdocs', __('Backup &lsaquo; Vault Docs', 'vaultdocs'), __('Backup', 'vaultdocs'), 'administrator', 'vaultdocs-backup', array(&$this, 'showBackup'));
            add_submenu_page('vaultdocs', __('Restore &lsaquo; Vault Docs', 'vaultdocs'), __('Restore', 'vaultdocs'), 'administrator', 'vaultdocs-restore', array(&$this, 'showRestore'));
        }
    }

    /**
     * Called from administration menu of 'Vault Docs'.
     */
    public function showOptions() {
        if(!empty($_POST)) {
            $defaultKeys = array_keys(self::$defaultOptions);
            foreach($_POST as $key => $value) {
                if(in_array($key, $defaultKeys)) {
                    $this->options[$key] = $value;
                }
            }
            if(empty($_POST['folder'])) {
                $errors[] = __('Folder field is required.', 'vaultdocs');
            }
            if(empty($errors)) {
                $this->updateOptions();
            }
        }
        require(dirname(__FILE__).'/options.php');
    }

    /**
     *
     */
    public function authSubRequest() {
        $url = admin_url('admin.php?action=vaultdocs_authSubResponse');
        $url = preg_replace('/^(https?:\/\/)(\w)/e', "'\\1'.strtoupper('\\2')", $url);
        $params = array(
            'next' => $url,
            'scope' => self::$googleAuthSubScope,
            'session' => 1,
        );
        if(!empty($_GET['domain'])) {
            if(preg_match('/^(https?:\/\/)?(([\da-z][\w\-]*\.)+[a-z]+)$/', $_GET['domain'], $match)) {
                $params['hd'] = $match[2];
            }
        }
        header('Location: '.self::$googleAuthSubRequestUrl.'?'.http_build_query($params));
    }

    /**
     *
     */
    public function authSubResponse() {
        if(!empty($_GET['token'])) {
            // convert to session token
            $headers = array(
                'Authorization: AuthSub token="'.$_GET['token'].'"',
            );
            $context = array(
                'http' => array(
                    'method'  => 'GET',
                    'header'  => join("\r\n", $headers),
                ),
            );
            $result = @file_get_contents(self::$googleAuthSubSessionTokenUrl, false, stream_context_create($context));
            if($result) {
                list($result) = explode("\n", $result);
                list(,$result) = explode("=", $result);
                $this->options['token'] = trim($result);
                $this->options['enabled'] = 1;

                // retrieve user information
                $result = $this->actionGoogleDocs(self::$gdocsUserMetaEndpointUrl);
                if($result && $result->author && $result->author->email) {
                    $this->options['email'] = (String)$result->author->email;
                }
                $this->updateOptions();
                header('Location: '.admin_url('options-general.php?page=vaultdocs&authSub=success'));
                return;
            }
        }
        header('Location: '.admin_url('options-general.php?page=vaultdocs&authSub=failure'));
    }

    /**
     *
     */
    public function authSubRevoke() {
        $headers = array(
            'Authorization: AuthSub token="'.$this->options['token'].'"',
        );
        $context = array(
            'http' => array(
                'method'  => 'GET',
                'header'  => join("\r\n", $headers),
            ),
        );
        @file_get_contents(self::$googleAuthSubRevokeTokenUrl, false, stream_context_create($context));

        $this->options['email'] = '';
        $this->options['token'] = '';
        $this->options['enabled'] = 0;
        $this->updateOptions();

        header('Location: '.admin_url('options-general.php?page=vaultdocs&authSub=revoke'));
    }

    /**
     * Called from administration menu of 'Backup'.
     */
    public function showBackup() {
        global $post;

        if(!$this->options['enabled']) {
            return;
        }

        $posts = array();
        if(empty($_POST['id'])) {
            // retrieve already backup id
            $alreadyBackupId = array();
            $query = array(
                'nopaging' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_key' => 'vault_docs_backup',
                'meta_value' => '1',
                'post_type' => 'any',
                'post_status' => 'publish,pending,draft,future,private',
            );
            query_posts($query);
            while (have_posts()) {
                the_post();
                $alreadyBackupId[] = $post->ID;
            }

            // retrieve not backup posts and pages
            $query = array(
                'nopaging' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'post_type' => 'any',
                'post_status' => 'publish,pending,draft,future,private',
            );
            if(!empty($alreadyBackupId)) {
                $query['post__not_in'] = $alreadyBackupId;
            }
            query_posts($query);
            while (have_posts()) {
                the_post();
                $posts[$post->ID] = $post->post_title;
            }
        }else{
            if(!is_array($_POST['id'])) {
                $_POST['id'] = array($_POST['id']);
            }
            foreach($_POST['id'] as $id) {
                if(is_numeric($id)) {
                    $posts[] = $id;
                }
            }
        }
        require(dirname(__FILE__).'/backup.php');
    }

    /**
     * Called from administration menu of 'Restore'.
     */
    public function showRestore() {
        if(!$this->options['enabled']) {
            return;
        }

        $status = !empty($_POST['status']) && preg_match('/^[a-z]+$/i', $_POST['status']) ? $_POST['status'] : '';
        $posts = array();
        $pages = array();
        if(empty($_POST['id'])) {
            $this->checkFolders();

            // retrieve all posts resource id
            $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['posts_folder_id']).'/contents';
            $result = $this->actionGoogleDocs($url);
            if($result) {
                foreach($result->entry as $entry) {
                    $resourceId = (String)$entry->gd_resourceId;
                    $title = (String)$entry->title;
                    $posts[$resourceId] = $title;
                }
            }

            // retrieve all pages resource id
            $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['pages_folder_id']).'/contents';
            $result = $this->actionGoogleDocs($url);
            if($result) {
                foreach($result->entry as $entry) {
                    $resourceId = (String)$entry->gd_resourceId;
                    $title = (String)$entry->title;
                    $pages[$resourceId] = $title;
                }
            }
        }else{
            if(!is_array($_POST['id'])) {
                $_POST['id'] = array($_POST['id']);
            }
            foreach($_POST['id'] as $id) {
                if(preg_match('/^[a-z]+:[a-z0-9]+$/i', $id)) {
                    $posts[] = $id;
                }
            }
        }

        require(dirname(__FILE__).'/restore.php');
    }

    /**
     * Save plugin options to database.
     */
    private function updateOptions() {
        $_options = array();
        $defaultKeys = array_keys(self::$defaultOptions);
        foreach($this->options as $key => $value) {
            if(in_array($key, $defaultKeys)) {
                $_options[$key] = $value;
            }
        }
        $this->options = $_options;
        update_option(get_class($this), $this->options);
    }

    /**
     * Debug logging.
     * @param string $message Debug message
     */
    private function log($message) {
        if(empty($this->debug)) {
            return;
        }
        $message = sprintf("[%s] %s\n", date('Y/m/d H:i:s'), $message);
        file_put_contents(dirname(__FILE__).'/debug.log', $message, FILE_APPEND);
    }

    /**
     * Check exists folders of this plugin.
     */
    private function checkFolders() {
        // retrieve all folders
        $folders = array();
        $results = $this->actionGoogleDocs(self::$gdocsPrivateEndpointUrl.'-/folder');
        if(!empty($results)) {
            foreach($results->entry as $folder) {
                $id = (String)$folder->gd_resourceId;
                $title = (String)$folder->title;
                $folders[$id] = $title;
            }
        }

        $template =
<<<CONTENTS
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns="http://www.w3.org/2005/Atom">
  <category scheme="http://schemas.google.com/g/2005#kind"
      term="http://schemas.google.com/docs/2007#folder"/>
  <title>%s</title>
</entry>
CONTENTS;

        $updated = false;

        // check root folder
        if(empty($this->options['folder_id']) || empty($folders[$this->options['folder_id']]) || $folders[$this->options['folder_id']] != $this->options['folder']) {
            $this->log('not exists root folder');
            $title = $this->options['folder'];
            $result = $this->actionGoogleDocs(self::$gdocsPrivateEndpointUrl, 'POST', sprintf($template, $title), 'application/atom+xml');
            if($result) {
                $resourceId = (String)$result->gd_resourceId;
                $this->options['folder_id'] = $resourceId;
                $this->options['posts_folder_id'] = null;
                $this->options['posts_folder_id'] = null;
                $updated = true;
                $this->log('created root folder '.$resourceId);
            }
        }
        // check posts folder
        if(empty($this->options['posts_folder_id']) || empty($folders[$this->options['posts_folder_id']])) {
            $this->log('not exists posts folder');
            $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['folder_id']).'/contents';
            $result = $this->actionGoogleDocs($url, 'POST', sprintf($template, __('Posts', 'vaultdocs')), 'application/atom+xml');
            if($result) {
                $resourceId = (String)$result->gd_resourceId;
                $this->options['posts_folder_id'] = $resourceId;
                $updated = true;
                $this->log('created posts folder '.$resourceId);
            }
        }

        // check pages folder
        if(empty($this->options['pages_folder_id']) || empty($folders[$this->options['pages_folder_id']])) {
            $this->log('not exists pages folder');
            $url = self::$gdocsPrivateEndpointUrl.urlencode($this->options['folder_id']).'/contents';
            $result = $this->actionGoogleDocs($url, 'POST', sprintf($template, __('Pages', 'vaultdocs')), 'application/atom+xml');
            if($result) {
                $resourceId = (String)$result->gd_resourceId;
                $this->options['pages_folder_id'] = $resourceId;
                $updated = true;
                $this->log('created pages folder '.$resourceId);
            }
        }
        if($updated) {
            $this->updateOptions();
        }
    }

    /**
     * Create multipart contents.
     * @param array $contents Request contents
     * @param string $endOfPart The end of part
     */
    private function createMultipart($contents, $endOfPart) {
        $result = '';
        foreach($contents as $contentType => $content) {
            $result .= '--'.$endOfPart."\r\n";
            $result .= 'Content-Type: '.$contentType . "\r\n\r\n";
            $result .= trim($content);
            $result .= "\r\n";
        }
        $result .= '--'.$endOfPart."--\r\n";
        return $result;
    }
}

$vaultdocs = new VaultDocs();

if(function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, array('VaultDocs', 'activation'));
}
