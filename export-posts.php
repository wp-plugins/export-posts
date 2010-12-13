<?php
/*
Plugin Name: Export Posts
Plugin URI: http://joeboydston.com/export-posts/
Description: Plugin is for dumping articles to a zip file
Author: Joe Boydston
Version: 1.0.1
Author URI: http://joeboydston.com
*/

//MENU
function dump_menu() {
    global $wpdb;
    include 'export-posts-admin.php';    
}

function dump_admin_actions() {
    add_management_page('Export-Posts', 'Export-Posts', 1, 'Export-Posts', 'dump_menu');
}

function add_admin_scripts() {
	wp_enqueue_script("jquery");
}

add_action('admin_menu', 'dump_admin_actions');
add_action('admin_enqueue_scripts', 'add_admin_scripts');

add_action('admin_menu', 'export_posts_admin_menu');

function export_posts_admin_menu() {
    add_options_page('Export Posts', 'Export Posts', 'administrator',
        'export-posts', 'export_posts_settings_page');
}

function export_posts_settings_page() {
    global $wpdb;
?>
<div>
<h2>Export Post Options</h2>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>

<table width="510">
<tr valign="top">
<th width="120" scope="row">Exported Tag</th>
<td width="406">
<input type="text" name="export_posts_tag" value="<?php echo get_option('export_posts_tag'); ?>"/>
</td>
</tr>
</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="export_posts_tag" />

<p>
<input type="submit" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div>
<?php
}
?>
