<?php

define( "LWC__FORMAT_DIR_NAME", "html_formats/" );
define( "LWC__FORMAT_DIR", plugin_dir_path(__FILE__) . LWC__FORMAT_DIR_NAME );

define( "LWC__NEW_FILE_NAME", "New_File" );
define( "LWC__NEW_FILE", LWC__FORMAT_DIR . LWC__NEW_FILE_NAME );

define( "LWC__NAME_OPEN_TAG", "[NAME]" );
define( "LWC__NAME_CLOSE_TAG", "[/NAME]" );
define( "LWC__FORMAT_OPEN_TAG", "[FORMAT]" );
define( "LWC__FORMAT_CLOSE_TAG", "[/FORMAT]" );

/**
 * Connect to WP_Filesystem for work with files
 */
function lawrcustemail_connect_wp_format() 
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
 * Handle AJAX requests when the user <select>s a new file; send the contents of the new file to populate the textarea
 */
function lawrcustemail_ajax_select_format_file()
{
	global $wp_filesystem;
	global $wpdb;
	
	// if we fail to connect to WP_Filesystem, output an error notice 
	if ( !lawrcustemail_connect_wp_format() ) {
		echo "Could not connect to the server. Please reload the page.\n";
		wp_die();
	}	
	
	$filename = $_POST[ 'format_filename' ];
	
	$contents = "";
	
	if ( $wp_filesystem->exists(LWC__FORMAT_DIR . $filename) ) {
		$contents = $wp_filesystem->get_contents( LWC__FORMAT_DIR . $filename );
	} else {
		$contents = "Selected file does not exist.";
	}
	
	if ( empty($contents) ) {
		echo "File is empty.";
	} else {
		echo $contents;
	}
	
	// DIE! to properly send a response to AJAX 
	wp_die();
}
add_action( 'wp_ajax_select_format_file', 'lawrcustemail_ajax_select_format_file' );

/*
 * Handle AJAX requests when the user tries to save edits to a format file; just save the changes
 */
function lawrcustemail_ajax_format_save_edits()
{
	global $wp_filesystem;
	global $wpdb;
	
	// if we fail to connect to WP_Filesystem, output an error notice 
	if ( !lawrcustemail_connect_wp_format() ) {
		echo "Could not connect to the database. Please reload the page.\n";
		wp_die();
	}	
	
	// extract the name of the file from the formatting (look for what's between [NAME] tags)
	$contents = $_POST[ 'saved_format_file_contents' ];
	$name_open = stripos( $contents, LWC__NAME_OPEN_TAG );
	$name_close = stripos( $contents, LWC__NAME_CLOSE_TAG );
	$name = substr( $contents, $name_open + strlen(LWC__NAME_OPEN_TAG) + 1, $name_close - ($name_open + strlen(LWC__NAME_OPEN_TAG) + 1));
	
	// remove tabs and newlines (i don't feel like messing with regular expressions, so...)
	$name = str_replace( "\t", '', $name );
	$name = str_replace( "\n", '', $name );
	
	// to "save edits," overwrite the contents of the format editor into a file with the name $filename
	$wp_filesystem->delete( LWC__FORMAT_DIR . $_POST['saved_format_filename'] );
	$wp_filesystem->put_contents( LWC__FORMAT_DIR . $name, $contents );
	
	$name_to_respond_with = "";
	
	// if we used up the "New File" for this, make a new one
	if ( !$wp_filesystem->exists(LWC__NEW_FILE) ) {
		lawrcustemail_create_new_format_file();
		
		$name_to_respond_with = $name;
	} 
	else {
		$name_to_respond_with = LWC__NAME_CLOSE_TAG;
	}
	
	/* the ajax response can work one of two ways: 
	 * 1) the "New File" was used up, in which the new filename is sent in order to be dynamically added to the <select> options for format files
 	 * 2) a pre-existing file was edited and saved, in which no file needs to be added to the <select> options for format files
 	 *
 	 * to differentiate (since we can only send a string through AJAX), the response is a JSON-encoded string that contains the name-closing tag
 	 * and the a name to be sent through AJAX (which is called $name_to_respond_with). In case (1), the sent name is the filename to be added to
 	 * the <select>. In case (2), the sent name is again the name-closing tag. 
 	 *
 	 * Due to the nature of the name-extracting code above, no formatting file can use the name-closing tag for its name (unless someone added
 	 * such a file manually through the server, in which case this would get REALLY screwed up. don't do that, pls. pls.). If the two JSON-encoded
 	 * values in the response are equal (which is only possible for case (2)), then no new <select> option will be added, and vice versa.
 	 */
 	 
 	 $ajax_response = array(
 	 	'name_closing_tag' => LWC__NAME_CLOSE_TAG,
 	 	'sent_name' => $name_to_respond_with
 	 );
	
	// send AJAX the response it wants and die!
	echo json_encode( $ajax_response );
	wp_die();
} 
add_action( 'wp_ajax_format_save_edits', 'lawrcustemail_ajax_format_save_edits' );

/**
 * Create the "New File" option if one doesn't exist (this is dense)
 */
function lawrcustemail_create_new_format_file() 
{
	global $wp_filesystem;
	
	// if we can't connect to filesystem, show an error
	if ( !lawrcustemail_connect_wp_format() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to filesystem." );
	}

	$new_template = LWC__NAME_OPEN_TAG . "\nNew_File\n" . LWC__NAME_CLOSE_TAG . "\n" . LWC__FORMAT_OPEN_TAG . "\n" . LWC__FORMAT_CLOSE_TAG . "\n";

	// do stuf if the "New File" already exists
	if ( $wp_filesystem->exists(LWC__NEW_FILE) ) {
		if ( $wp_filesystem->get_contents(LWC__NEW_FILE) == $new_template ) {
			// if the "New File" exists but is untouched, then there's nothing worry about
			;
		} else {
			/* if the "New File" already exists but isn't the same as the template, rename it so the "New File" can be made (without deleting anything)
	 		 * (this would happen if someone tried to make a new formatting file but didn't name it for some reason (why am i trying so hard?) */
			for ($x = 2;; $x++) {
				// unnamed files will be labelled as "New_File2, New_File3, New_File4, ..."
				if ( !$wp_filesystem->exists(LWC__NEW_FILE . $x) ) {
					$wp_filesystem->copy( LWC__NEW_FILE, LWC__NEW_FILE . $x );
					$wp_filesystem->delete( LWC__NEW_FILE );
					break;
				}
			}
			// and then make a "New File"
			$wp_filesystem->put_contents( LWC__NEW_FILE, $new_template );
		} 
	} else {
		// if it doesn't exist yet, just create the new file with the template format
		$wp_filesystem->put_contents( LWC__NEW_FILE, $new_template );
	}
	
	return true;
}

/**
 * Display email files to select in a drop-down menu.
 */
function lawrcustemail_create_format_select() 
{
	global $wp_filesystem;
	
	// if we can't connect to filesystem, show an error
	if ( !lawrcustemail_connect_wp_format() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to filesystem." );
	}
	
	// use AJAX to notify the server if the user selected a new option in the <select> element
	?>
		<script>
			function lawrcustemail_format_select_change(value) {
				jQuery(document).ready(function($) {
			
					var data = {
						'action': 'select_format_file',
						'format_filename': value
					};
					
					jQuery.post(ajaxurl, data, function(response) {
						$( '#format_editor' ).html(response);
					});
				});
			}
		</script>
	<?php
			
	echo '<select id="format_select" onchange="lawrcustemail_format_select_change(this.value)">';
	
	// get all files (hidden or not) from the relevant directory (recursively search through directories, too)
	$files = $wp_filesystem->dirlist( LWC__FORMAT_DIR, $include_hidden = true, $recursive = true );
	
	// skip directories in the <select> tag
	foreach ( $files as $filename ) {
		if ( !$wp_filesystem->is_dir($filename['name']) ) {
			// force the "New File" to be the default option
			if ( $filename['name'] == LWC__NEW_FILE_NAME ) {
				echo '<option selected value="' . $filename['name'] . '">' . $filename['name'] . '</option>' . "\n";
			} else {
				echo '<option value="' . $filename['name'] . '">' . $filename['name'] . '</option>' . "\n";
			}
		}
	}
	
	echo "</select>\n";
}
 
/**
 * Create the document formatting editor. 
 */
function lawrcustemail_create_format_editor() 
{
	global $wp_filesystem;

	echo '<div id="format_editor_div">';
	
	// setup the "New File" option
	$result = lawrcustemail_create_new_format_file();
	if ( is_wp_error( $result ) ) {
		echo $result->get_error_message();
	}
	
	// show the drop-down menu of format files
	$result = lawrcustemail_create_format_select();
	if ( is_wp_error( $result ) ) {
		echo $result->get_error_message();
	}
	
	// display the editor
	$editor_args = array(
		'media_buttons' => false,
		'textarea_rows' => 15,
		'teeny' => true,
	);
	wp_editor( $wp_filesystem->get_contents(LWC__NEW_FILE), 'format_editor', $editor_args );
	
	// use AJAX to inform PHP that the selected format file is being saved (when the format submit button is clicked)
	?>
		<script>
			function lawrcustemail_format_submit_clicked() {
				jQuery(document).ready(function($) {
					var data = {
						'action': "format_save_edits",
						'saved_format_filename': $( '#format_select' ).val(),
						'saved_format_file_contents': $( "#format_editor" ).val()
					};
					
					/* if the response isnt the name closing tag (which CAN'T be a formatting filename), then add a new
					 * <select> option with the new filename (see the 'lawrcustemail_ajax_format_save_edits()' function
					 * for muchos detalles) */
					jQuery.post(ajaxurl, data, function(json_response) {
						var response = $.parseJSON( json_response );
						if ( response.name_closing_tag != response.sent_name ) {
							$( '#format_select' ).append( $('<option>', {
								value: response.sent_name,
								text: response.sent_name
							}));
							
							/* [BUG]: after saving a file, the <select> tag for formatting becomes stuck */
								
						} 
						
						alert("File saved.");
					});
				});
			}
		</script>
	<?php
	
	$submit_args = 	array(
		'onclick' => "lawrcustemail_format_submit_clicked()"
	);
	
	submit_button( "Save Edits", 'primary', 'format_submit_button', true, $submit_args );
	
	echo '</div>' . "\n";
}

?>
