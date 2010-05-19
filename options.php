<?php if(!empty($errors)){ ?>
<div id="message" class="error fade"><p><strong><?php echo join('<br/>', $errors) ?></strong></p></div>
<?php }else if(!empty($_POST)){ ?>
<div id="message" class="updated fade"><p><strong><?php _e('Updated options.', 'vaultdocs') ?></strong></p></div>
<?php }else if(!empty($_GET['authSub'])){ ?>
<?php if($_GET['authSub'] == 'success'){ ?>
<div id="message" class="updated fade"><p><strong><?php _e('Authorization complete.', 'vaultdocs') ?></strong></p></div>
<?php } ?>
<?php if($_GET['authSub'] == 'failure'){ ?>
<div id="message" class="error fade"><p><strong><?php _e('Authorization failure.', 'vaultdocs') ?></strong></p></div>
<?php } ?>
<?php if($_GET['authSub'] == 'revoke'){ ?>
<div id="message" class="updated fade"><p><strong><?php _e('Revoke authorization complete.', 'vaultdocs') ?></strong></p></div>
<?php } ?>
<?php } ?>

<div class="wrap">
<h2>Vault Docs</h2>

<form name="vaultDocs" method="post" >

<h3><?php _e('Authorization Google account', 'vaultdocs') ?></h3>

<?php if(empty($this->options['token'])) { ?>
<p style="padding: 0 0 0 10px;">
    <?php _e('You need authorization on Google account.', 'vaultdocs') ?>
</p>
<p style="padding: 0 0 0 10px;">
    <?php _e('Domain(optional):', 'vaultdocs') ?>
    <input type="text" name="domain" id="domain" value="" size="50" /><br />
    <small><?php _e('If you want to use account of Google Apps.', 'vaultdocs'); ?></small>
</p>
<p class="submit" style="padding: 0px;">
    <input type="button" value="<?php _e('Authorization on Google Account &raquo;', 'vaultdocs'); ?>" onclick="location.href='<?php echo admin_url('admin.php?action=vaultdocs_authSubRequest&domain=') ?>'+document.getElementById('domain').value;"/>
</p>
<?php }else{ ?>
<p style="padding: 0 0 0 10px;">
    <?php printf(__('Authorized and backup your entries to %s.', 'vaultdocs'), htmlspecialchars($this->options['email'])) ?>
</p>
<p class="submit" style="padding: 0px;">
    <input type="button" value="<?php _e('Revoke authorization on Google Account &raquo;', 'vaultdocs'); ?>" onclick="location.href='<?php echo admin_url('admin.php?action=vaultdocs_authSubRevoke') ?>';"/>
</p>
<?php } ?>

<h3><?php _e('Google Docs', 'vaultdocs') ?></h3>

<table width="100%" class="form-table">
    <tr>
        <th width="33%" valign="top" scope="row"><?php _e('Backup folder', 'vaultdocs') ?>: </th>
        <td>
            <input type="text" name="folder" value="<?php echo htmlspecialchars($this->options['folder']); ?>" size="50" /><br />
        </td>
    </tr>
</table>

<p class="submit"><input type="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
</form>
</div>
