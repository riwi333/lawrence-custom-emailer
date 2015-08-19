<?php

/* [NOTE]: if anything is unclear, that's because this is basically a copy of 'formats.php' (except i didn't copy-paste anything)
 * just look at that file and its mirror functions for more info */

define( 'LWC__GROUP_DIR_NAME', "email_groups/" );
define( 'LWC__GROUP_DIR', plugin_dir_path(__FILE__) . LWC__GROUP_DIR_NAME );

define( 'LWC__NEW_GROUP_NAME', "New Group" );
define( 'LWC__NEW_GROUP', LWC__GROUP_DIR . LWC__NEW_GROUP_NAME );

define( 'LWC__GROUP_IMPOSSIBLE_NAME', "\n\n" );

/*
 * Send the file contents if AJAX sends a request in response to a user selecting a new group <select> option
 */
function lawrcustemail_ajax_select_email_group()
{
	global $wp_filesystem;
	global $wpdb;
	
	// connect to WP_Filesystem or send back an error message
	if ( !lawrcustemail_connect_wp() ) {
		echo "Could not connect to the server. Please reload the page.";
		wp_die();
	}
	
	$group = $_POST[ 'email_group' ];
	$contents = "";
	
	if ( $wp_filesystem->exists(LWC__GROUP_DIR . $group) ) {
		$contents = $wp_filesystem->get_contents( LWC__GROUP_DIR . $group );
	} else {
		$contents = "Selected file does not exist.";
	}
	
	if ( empty($contents) ) {
		echo "File is empty.";
	} else {
		echo $contents;
	}
	
	// die() to properly send AJAX the response
	wp_die();
}
add_action( 'wp_ajax_select_email_group', "lawrcustemail_ajax_select_email_group" );

/*
 * Saved the selected email file if AJAX requests to do so (after user clicks on the "Save Edits" button)
 */
function lawrcustemail_ajax_group_save_edits() 
{
	global $wp_filesystem;
	global $wpdb;
	
	// connect to WP_Filesystem()
	if ( !lawrcustemail_connect_wp() ) {
		echo "Could not connect to the server. Please reload the page.";
		wp_die();
	}
	
	// get the name of the file being saved (the name of the group should always be on the first line)
	$contents = $_POST[ 'saved_group_contents' ];
	$lines = explode( "\n", $contents );
	$name = $lines[0];
	
	// to "save edits," overwrite the contents of the selected file
	$wp_filesystem->delete( $_POST[ 'saved_group_name' ] );
	$wp_filesystem->put_contents( LWC__GROUP_DIR . $name, $contents );
	
	$name_to_respond_with = "";
	
	// if we used up a "New Group," make a new one
	if ( !$wp_filesystem->exists(LWC__NEW_GROUP) ) {
		lawrcustemail_create_new_email_group();
		$name_to_respond_with = $name;
	} else {
		$name_to_respond_with = LWC__GROUP_IMPOSSIBLE_NAME;
	}
	
	/* I'm not going to repeat the explanation for the whole $name_to_respond_with thing. Read the code for the 
	 * function named 'lawrcustemail_ajax_format_save_edits()' in 'formats.php' */
	 
	 $ajax_response = array(
	 	'impossible_name' => LWC__GROUP_IMPOSSIBLE_NAME,
	 	'sent_name' => $name_to_respond_with
	 );
	 
	 echo json_encode( $ajax_response );
	 wp_die();
}
add_action( 'wp_ajax_group_save_edits', 'lawrcustemail_ajax_group_save_edits' );

/**
 * Create the "New Group" option if it doesn't exist yet
 */
function lawrcustemail_create_new_email_group()
{
	global $wp_filesystem;
	
	// if we can't connect to the filesystem, etc.
	if ( !lawrcustemail_connect_wp() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to the filesystem." );
	}
	
	$new_group_template = "New Group\n";
	
	// if this "New Group" already exists on the server...
	if ( $wp_filesystem->exists(LWC__NEW_GROUP) ) {
		// if some file has the name of the "New Group" option and is exactly like it, don't do anything
		if ( $wp_filesystem->get_contents(LWC__NEW_GROUP) == $new_group_template ) {
			;
		}
		// otherwise (if the file WAS tampered with), rename it to make space for the actual "New Group"
		else {
			for ($x = 2;; $x++) {
				if ( !$wp_filesystem->exists(LWC__NEW_GROUP . $x) ) {
					$wp_filesystem->copy( LWC__NEW_GROUP, LWC__NEW_GROUP . $x );
					$wp_filesystem->delete( LWC__NEW_GROUP );
					break;
				}
			}
			// and then make a "New Group" file
			$wp_filesystem->put_contents( LWC__NEW_GROUP, $new_group_template );
		}
	}
	// if it doesn't exist yet, just make one!
	else {
		$wp_filesystem->put_contents( LWC__NEW_GROUP, $new_group_template );
	}
	
	return true;
}

/**
 * Display email files to select in a drop-down menu.
 */
function lawrcustemail_create_group_select() 
{
	global $wp_filesystem;
	
	// connect to filesystem, etc.
	if ( !lawrcustemail_connect_wp() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to filesystem." );
	}
	
	// use AJAX (ugh...) to notify the server if the user selects an option from the <select> tag
	?>
		<script>
			function lawrcustemail_group_select_change(value) {
				jQuery(document).ready(function($) {
					
					var data = {
						'action': "select_email_group",
						'email_group': value
				 	};
				 	
				 	jQuery.post(ajaxurl, data, function(response) {
				 		$( '#group_editor' ).html(response);
					});
					
				});
			}
		</script>
	<?php
	
	echo '<select id="group_select" onchange="lawrcustemail_group_select_change(this.value)" autocomplete="off">' . "\n";
	
	// round up all files from the directory (recursively search, too)
	$groups = $wp_filesystem->dirlist( LWC__GROUP_DIR, true, true );
	
	// skip directories and force the "New Group" to be the default option
	foreach ( $groups as $group_name ) {
		if ( !$wp_filesystem->is_dir($group_name['name']) ) {
			if ( $group_name['name'] == LWC__NEW_GROUP_NAME ) {
				echo '<option selected value="' . $group_name['name'] . '">' . $group_name['name'] . '</option>' . "\n";
			} else {
				echo '<option value="' . $group_name['name'] . '">' . $group_name['name'] . '</option>' . "\n";
			}
		}
	}
	
	echo '</select>';
	
	return true;
}

/**
 * Create the email group formatting editor.
 */
function lawrcustemail_create_group_editor() 
{
	global $wp_filesystem;

	echo '<div id="group_editor_box">' . "\n";
	
	// setup the "New Group" option/value
	$result = lawrcustemail_create_new_email_group();
	if ( is_wp_error($result) ) {
	    echo $result->get_error_message();
	}
	
	// show drop-down menu of email group options
	$result = lawrcustemail_create_group_select();
	if ( is_wp_error($result) ) {
		echo $result->get_error_message();
	}
	
	// DISPLAY THE EDITOR!
	$editor_args = array(
		'media_buttons' => false,
		'textarea_rows' => 15
	);
	wp_editor( $wp_filesystem->get_contents(LWC__NEW_GROUP), 'group_editor', $editor_args );
	
	// use AJAX (ugh...) to tell the server that selected file is being saved (when the submit button is clicked)
	?>
		<script>
			function lawrcustemail_email_submit_clicked() {
				jQuery(document).ready(function($) {
					
					var data = {
						'action': 'group_save_edits',
						'saved_group_name': $( '#group_select' ).val(),
						'saved_group_contents': $( '#group_editor' ).val()
					};
					
					/* see lawrcustemail_format_submit_clicked() for details */
					jQuery.post(ajaxurl, data, function(json_response) {
					    var response = $.parseJSON( json_response );
						if ( response.impossible_name == response.sent_name ) {
							$( '#group_select' ).append( $('<option>', {
								value: response.sent_name,
								text: response.sent_name
							}));
						}
						
						alert("File saved.");
					});
				});
			}
		</script>
	<?php
	
	// create the submit button to save changes to the selected group file
	$submit_args = array(
		'onclick' => 'lawrcustemail_email_submit_clicked()'
	);
	submit_button( 'Save Edits', 'primary', 'email_submit_button', true, $submit_args );
	
	echo '</div>' . "\n";
}


?>
