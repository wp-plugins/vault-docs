<div class="wrap">

<h2><?php _e('Vault Docs &raquo; Backup', 'vaultdocs') ?></h2>

<?php if(empty($_POST)) { ?>

<?php if(!empty($posts)) { ?>

<h3><?php _e('Entry list not backed up', 'vaultdocs') ?></h3>

<form name="vaultDocs" method="post">

<table id="backups" class="widefat">
    <thead>
        <tr>
            <th class="manage-column check-column"><input type="checkbox" name="checkAll" id="checkAll" value="1" /></th>
            <th class="manage-column" style="text-align: right;width: 20px;"><?php _e('ID', 'vaultdocs') ?></th>
            <th class="manage-column"><?php _e('Title', 'vaultdocs') ?></th>
        </tr>
    </thead>
<?php foreach($posts as $id => $title) { ?>
    <tr>
        <td><input type="checkbox" name="id[]" id="check_<?php echo $id ?>" value="<?php echo $id ?>" /></td>
        <td style="text-align: right;"><?php echo $id ?></td>
        <td><?php echo !empty($title) ? htmlspecialchars($title) : __('(No title)', 'vaultdocs') ?></td>
    </tr>
<?php } ?>
</table>

<p class="submit"><input type="submit" value="<?php _e('Start backup &raquo;', 'vaultdocs'); ?>" /></p>
</form>

<script type="text/javascript">
jQuery('input#checkAll').click(function() {
    if(jQuery(this).attr('checked')) {
        jQuery('table#backups tr td input[type=checkbox]').attr('checked', true);
    }else{
        jQuery('table#backups tr td input[type=checkbox]').attr('checked', false);
    }
});
</script>

<?php } else { ?>

<h3><?php _e('The backup of all the entries has already been completed.', 'vaultdocs') ?></h3>

<?php } ?>

<?php } else { ?>

<p id="loading" style="background: url(images/loading.gif) top left no-repeat; padding-left: 20px;"><?php _e('The backup began. Please wait for a while without shutting this window...', 'vaultdocs') ?></p>

<div id="doneBackup">
</div>

<script type="text/javascript">
if(!window.VAULTDOCS) {
     window.VAULTDOCS = {};
}

VAULTDOCS.posts = <?php echo json_encode($posts) ?>;

VAULTDOCS.start = function() {
    if(VAULTDOCS.posts.length > 0) {
        var id = VAULTDOCS.posts.shift();
        jQuery.getScript('<?php echo admin_url('admin.php?action=vaultdocs_backgroundBackup&id=')?>' + id);
    }else{
        jQuery('p#loading').css('background', null).css('padding-left', null);
        jQuery('div#doneBackup').append('<h3><?php _e('All backups of the selected entry were completed!', 'vaultdocs')?></h3>')
    }
};

VAULTDOCS.next = function(post) {
    if(post) {
        jQuery('div#doneBackup').append('<p id="result_' + post.ID + '"></p>');
        jQuery('p#result_' + post.ID).text('<?php _e('Backup %s is done.', 'vaultdocs')?>'.replace(/%s/, 'ID: ' + post.ID + ' ' + post.post_title));
    }
    VAULTDOCS.start();
};

jQuery(document).ready(VAULTDOCS.start);

jQuery('input#checkAll').click(function() {
    alert('');
    if(jQuery(this).attr('checked')) {
        jQuery('table#backups tr td input[type=checkbox]').attr('checked', true);
    }else{
        jQuery('table#backups tr td input[type=checkbox]').attr('checked', false);
    }
});
</script>

<?php } ?>

</div>
