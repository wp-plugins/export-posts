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

function add_admin_scripts() {
	wp_enqueue_script("jquery");
}

add_action('admin_menu', 'dump_admin_actions');
add_action('admin_enqueue_scripts', 'add_admin_scripts');

?>
