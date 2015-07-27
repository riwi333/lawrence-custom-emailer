<?php 
/*
Plugin Name: Lawrence Custom Emailer
Description: Plugin to create a custom email format for Lawrence articles that can sent to the Lawrenceville community via Gmail 
Version: 1.0
Author: Ricky Williams
*/

define( "LWC__PLUGIN_DIR", plugin_dir_path(__FILE__) );
 
// prevent direct access to plugin file
defined( 'ABSPATH' ) or die( 'Illegal access error!' ); 

// include related PHP files (menus.php, etc.)
require_once( LWC__PLUGIN_DIR . "menus.php" );
require_once( LWC__PLUGIN_DIR . "formats.php" );
require_once( LWC__PLUGIN_DIR . "groups.php" );

/** 
 * Add all stylesheets/scripts to the plugin.
 */
function lawrcustemail_setup_scripts() {
	wp_enqueue_style( 'lawrcustemail_stylesheet', plugins_url('lawrence-custom-emailer.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'lawrcustemail_setup_scripts' );

/**
 * Allow wp_mail() to send HTML emails (instead of plaintext emails) so formatting can be done.
 */
function lawrcustemail_allow_html_mail() {
    return "text/html";
}
add_filter( 'wp_mail_content_type', 'lawrcustemail_allow_html_mail' );

/**
 * Create the document formatting editor. 
 */
function lawrcustemail_create_format_editor() {
}

/**
 * Create the email group formatting editor.
 */
function lawrcustemail_create_email_editor() {
    // first retrieve already created email files and display them in a drop-down menu
    echo '<div id="email_select">';
}

/** 
 * Add an admin dashboard menu to access the email customizer and emailer.
 */ 
function lawrcustemail_add_admin_menu() {
    add_menu_page( 'Lawrence Custom Email', 'Custom Emailer', 'manage_options', 'lawrcustemail', 'lawrcustemail_write_admin_menu' );
}
add_action( 'admin_menu', 'lawrcustemail_add_admin_menu' );

/** 
 * Write the basic html of the plugin's dashboard menu option.
 */ 
function lawrcustemail_write_admin_menu() {
    // print out the drop-down menu from which the post to be emailed is selected
    lawrcustemail_create_post_select();
    
    // create the editor to select and edit email formatting files 
    lawrcustemail_create_format_editor();
}
 
?>