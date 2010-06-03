<div class="wrap">

<h2><?php _e('Vault Docs &raquo; Restore', 'vaultdocs') ?></h2>

<?php if(empty($_POST)) { ?>

<?php if(!empty($posts) || !empty($pages)) { ?>

<form name="vaultDocs" method="post">

<?php if(!empty($posts)) { ?>

<h3><?php _e('Post list backed up', 'vaultdocs') ?></h3>

<table id="posts" class="widefat">
    <thead>
        <tr>
            <th class="manage-column check-column"><input type="checkbox" id="checkAllPosts" value="1" /></th>
            <th class="manage-column" style="width: 30%;"><?php _e('ID', 'vaultdocs') ?></th>
            <th class="manage-column"><?php _e('Title', 'vaultdocs') ?></th>
        </tr>
    </thead>
<?php foreach($posts as $id => $title) { ?>
    <tr>
        <td><input type="checkbox" name="id[]" id="check_<?php echo $id ?>" value="<?php echo $id ?>" /></td>
        <td><?php echo $id ?></td>
        <td><?php echo !empty($title) ? htmlspecialchars($title) : __('(No title)', 'vaultdocs') ?></td>
    </tr>
<?php } ?>
</table>

<?php } ?>

<?php if(!empty($pages)) { ?>

<h3><?php _e('Page list backed up', 'vaultdocs') ?></h3>

<table id="pages" class="widefat">
    <thead>
        <tr>
            <th class="manage-column check-column"><input type="checkbox" id="checkAllPages" value="1" /></th>
            <th class="manage-column" style="width: 30%;"><?php _e('ID', 'vaultdocs') ?></th>
            <th class="manage-column"><?php _e('Title', 'vaultdocs') ?></th>
        </tr>
    </thead>
<?php foreach($pages as $id => $title) { ?>
    <tr>
        <td><input type="checkbox" name="id[]" id="check_<?php echo $id ?>" value="<?php echo $id ?>" /></td>
        <td><label for="check_<?php echo $id ?>"><?php echo $id ?></label></td>
        <td><label for="check_<?php echo $id ?>"><?php echo !empty($title) ? htmlspecialchars($title) : __('(No title)', 'vaultdocs') ?></label></td>
    </tr>
<?php } ?>
</table>

<?php } ?>

<p>
<strong><?php _e('The status set to restored entry:', 'vaultdocs') ?></strong>
<select name="status">
<option value="publish" selected="selected"><?php _e('Publish', 'vaultdocs') ?></option>
<option value="draft"><?php _e('Draft', 'vaultdocs') ?></option>
<option value="pending"><?php _e('Pending', 'vaultdocs') ?></option>
<option value="private"><?php _e('Private', 'vaultdocs') ?></option>
</select>
</p>

<p class="submit">
    <input type="submit" value="<?php _e('Start restore &raquo;', 'vaultdocs'); ?>" />
</p>

</form>

<script type="text/javascript">
jQuery('input#checkAllPages').click(function() {
    if(jQuery(this).attr('checked')) {
        jQuery('table#pages tr td input[type=checkbox]').attr('checked', true);
    }else{
        jQuery('table#pages tr td input[type=checkbox]').attr('checked', false);
    }
});
jQuery('input#checkAllPosts').click(function() {
    if(jQuery(this).attr('checked')) {
        jQuery('table#posts tr td input[type=checkbox]').attr('checked', true);
    }else{
        jQuery('table#posts tr td input[type=checkbox]').attr('checked', false);
    }
});
</script>


<?php } else { ?>

<h3><?php _e('There is no entry backed up.', 'vaultdocs') ?></h3>

<?php } ?>

<?php } else { ?>

<p id="loading" style="background: url(images/loading.gif) top left no-repeat; padding-left: 20px;"><?php _e('The restore began. Please wait for a while without shutting this window...', 'vaultdocs') ?></p>

<div id="doneRestore">
</div>

<script type="text/javascript">
if(!window.VAULTDOCS) {
     window.VAULTDOCS = {};
}

VAULTDOCS.posts = <?php echo json_encode($posts) ?>;

VAULTDOCS.start = function() {
    if(VAULTDOCS.posts.length > 0) {
        var id = VAULTDOCS.posts.shift();
        jQuery.getScript('<?php echo admin_url('admin.php?action=vaultdocs_backgroundRestore&status='.$status.'&id=')?>' + id);
    }else{
        jQuery('p#loading').css('background', null).css('padding-left', null);
        jQuery('div#doneRestore').append('<h3><?php _e('The restoration of all the selected entries was completed.', 'vaultdocs')?></h3>')
    }
};

VAULTDOCS.next = function(post) {
    if(post) {
        jQuery('div#doneRestore').append('<p id="result_' + post.ID + '"></p>');
        jQuery('p#result_' + post.ID).text('<?php _e('Restore %s to %s is done.', 'vaultdocs')?>'.replace(/%s/, post.vault_docs_resource_id).replace(/%s/, 'ID: ' + post.ID + ' ' + post.post_title));
    }
    VAULTDOCS.start();
};

jQuery(document).ready(VAULTDOCS.start);
</script>

<?php } ?>

</div>
