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

// include related PHP files (formats.php, etc.)
require_once( LWC__PLUGIN_DIR . "formats.php" );
require_once( LWC__PLUGIN_DIR . "groups.php" ); 
require_once( LWC__PLUGIN_DIR . "parse.php" );

/**
 * Connect to WP_Filesystem for work with files
 */
function lawrcustemail_connect_wp() 
{
	// url for credential form to be displayed
	$url = wp_nonce_url( 'admin.php?page=lawrcustemail' );
	
	// either find credentials somewhere on the server or display a form
	if ( false === ($creds = request_filesystem_credentials( $url, '', false, false, null )) ) {
		return false;
	}
	
	// if these credentials are wrong...
	if ( !WP_Filesystem( $creds ) ) {
		// making the 3rd parameter 'true' instead of 'false' keeps prompting for the credentials
		request_filesystem_credentials( $url, '', true, false, null );
		return false;
	}
	
	return true;
}

/** 
 * Add all stylesheets/scripts to the plugin.
 */
function lawrcustemail_setup_scripts() 
{
	wp_enqueue_style( 'lawrcustemail_stylesheet', plugins_url('lawrence-custom-emailer.css', __FILE__) );
}
add_action( 'admin_enqueue_scripts', 'lawrcustemail_setup_scripts' );

/**
 * Allow wp_mail() to send HTML emails (instead of plaintext emails) so formatting can be done.
 */
function lawrcustemail_allow_html_mail() 
{
	return "text/html";
}
add_filter( 'wp_mail_content_type', 'lawrcustemail_allow_html_mail' );

/** 
 * Add an admin dashboard menu to access the email customizer and emailer.
 */ 
function lawrcustemail_add_admin_menu() 
{
    add_menu_page( 'Lawrence Custom Email', 'Custom Emailer', 'manage_options', 'lawrcustemail', 'lawrcustemail_write_admin_menu' );
}
add_action( 'admin_menu', 'lawrcustemail_add_admin_menu' );

/**
 * Handle AJAX requests when the "Send Email" button is clicked (actually email everything)
 */
function lawrcustemail_ajax_send()
{
	global $wp_filesystem;
	global $wpdb;
	
	// connect to WP_Filesystem for all that good file accessin'
	if ( !lawrcustemail_connect_wp() ) {
	    echo "Could not connect to the server. Please reload the page.";
	    wp_die();
	}
	
	$post = $_POST[ 'selected_post' ];
	$format = $_POST[ 'format_file' ];
	$group = $_POST[ 'group_file' ];
	$subject = $_POST[ 'subject_text' ];
	
	// get the parsed format file
	$format_contents = $wp_filesystem->get_contents( LWC__FORMAT_DIR . $format );
	$format_contents = lawrcustemail_parse( $format_contents, $post );
	
	// get an array of emails to send the format file to from the group file (there should be one address per line)
	$group_contents = $wp_filesystem->get_contents( LWC__GROUP_DIR . $group );
	$addresses = explode( "\n", $group_contents );
	unset( $addresses[0] );	// (the first line is the name of the group file, so skip it)
	
	// send the email to each address
	wp_mail( $addresses, $subject, $format_contents, "", "" );
	
	wp_die();
}
add_action( 'wp_ajax_send', 'lawrcustemail_ajax_send' );

/**
 * Display posts to select in a drop-down menu.
 */
function lawrcustemail_create_post_select() 
{
	echo '<select id="post-select" autocomplete="off">';
     
    // get an array of all WP_Post objects and sort them (most recent articles at the top of the menu, etc.)
 	$get_posts_args = array(
		'posts_per_page' => -1,
 		'orderby' => 'date',
 		'order' => 'DESC',
		'post_type' => 'post',
		'post_status' => 'publish'
 	);

	$all_posts = get_posts( $get_posts_args );
     
	// show the post titles via a select tag; if no posts could be found, then something is really wrong
    if ( $all_posts ) {
    	foreach ( $all_posts as $post ) {
    		if ( $post == $all_posts[0] ) {
    			echo '<option selected value=' . $post->ID . '>' . $post->post_title . '</option>' . "\n";
    		} else {
    			echo '<option value=' . $post->ID . '>' . $post->post_title . '</option>' . "\n" ;
    		}
		}
    } else {
     	die( 'No posts could be found!' );
	}
     
	echo '</select>' . "\n";
}

/** 
 * Write the basic html of the plugin's dashboard menu option.
 */ 
function lawrcustemail_write_admin_menu() 
{
	echo '<div id="lwc-plugin">' . "\n";

    // print out the drop-down menu from which the post to be emailed is selected
    echo "<h1> Select the post to send: </h1>\n";
	lawrcustemail_create_post_select();
    
    // create the editors to select and edit email and formatting files 
    ?>
    <div id="lwc-editors">
    <?
   	lawrcustemail_create_format_editor();
   	lawrcustemail_create_group_editor();
   	?>
   	</div><!-- lwc-editors -->
   	
   	<?php /* create textarea to write the subject of the sent email */ ?>
   	<div id="email-subject">
   		<h1> Write the subject of the email: </h1>
   		<textarea rows="2" id="subject-textarea">Write the subject of the email here!</textarea>
   	</div><!-- email-subject -->
   	
   	<?php /* write the submit button to send emails */ ?>
		<script>
			function lawrcustemail_send_button_clicked() {
				jQuery(document).ready(function($) {
					var data = {
						'action': "send",
						'selected_post': $( '#post-select' ).val(),
						'format_file': $( '#format-select' ).val(),
						'group_file': $( '#group-select' ).val(),
						'subject_text': $( '#subject-textarea' ).val()
					};
					
					jQuery.post(ajaxurl, data, function(response) {
						alert("Email sent.");
					});
				});
			}
		</script>
	<?php
   	
   	echo '<div id="send-button">' . "\n";
   	echo "<h1> Send the email! </h1>\n";
   	
   	$submit_args = array(
   		'onclick' => "lawrcustemail_send_button_clicked()"
	);
	submit_button( 'Send Email', 'primary', 'send_email_button', true, $submit_args );
	echo "</div><!-- send-button -->\n";
	
	echo '</div><!-- lwc-plugin -->' . "\n";
}
 
?>
