<?php
/*
Plugin Name: Export Posts
Plugin URI: http://joeboydston.com/export-posts/
Description: Plugin is for dumping articles to a zip file
Author: Joe Boydston
Version: 1.0
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

add_action('admin_menu', 'dump_admin_actions');
?>
