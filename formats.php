<?php

define( "LWC__FORMAT_DIR_NAME", "html_formats/" );
define( "LWC__FORMAT_DIR", plugin_dir_path(__FILE__) . LWC__FORMAT_DIR_NAME );

define( "LWC__NEW_FILE_NAME", "New_File" );
define( "LWC__NEW_FILE", LWC__FORMAT_DIR . LWC__NEW_FILE_NAME );

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
 * Create the "New File" option if one doesn't exist (this is dense)
 */
function lawrcustemail_create_new_format_file()
{
	global $wp_filesystem;
	
	// if we can't connect to filesystem, show an error
	if ( !lawrcustemail_connect_wp_format() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to filesystem." );
	}

	$new_template = "[NAME]\n\t[NEW_FILE]\n[FORMAT]\n";
	
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
 * Handle AJAX requests when the user <select>s a new file; send the contents of the new file to populate the textarea
 */
function lawrcustemail_select_format_file()
{
	global $wp_filesystem;
	global $wpdb;
	
	// if we fail to connect to WP_Filesystem, output an error notice 
	if ( !lawrcustemail_connect_wp_format() ) {
		echo "Could not connect to the database. Please reload the page.\n";
		wp_die();
	}	
	
	$filename = $_POST[ 'format_filename' ];
	
	$contents = "";
	
	if ( $wp_filesystem->exists($filename) || $filename == LWC__NEW_FILE ) {
		$contents = $wp_filesystem->get_contents( "./" . $filename );
	} else {
		$contents = "Selected file does not exist.";
	}
	
	if ( empty($contents) ) {
		echo "File is empty.";
	} else {
		echo $contents;
	}
	 
	wp_die();
}
add_action( 'wp_ajax_select_format_file', 'lawrcustemail_select_format_file' );

/**
 * Display email files to select in a drop-down menu.
 */
function lawrcustemail_create_format_select() {
	global $wp_filesystem;
	
	// if we can't connect to filesystem, show an error
	if ( !lawrcustemail_connect_wp_format() ) {
		return new WP_Error( "filesystem_error", "Cannot connect to filesystem." );
	}
	
	// use AJAX to notify the server if the user selected a new option in the <select> element
	?>
		<script>
			function format_select_change(value)
			{
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
			
	echo '<select id="format_select" onchange="format_select_change(this.value)">';
	
	// get all files (hidden or not) from the relevant directory (recursively search through directories, too)
	$files = $wp_filesystem->dirlist( LWC__FORMAT_DIR, $include_hidden = true, $recursive = true );
	
	// skip directories in the <select> tag
	foreach ( $files as $filename ) {
		if ( !$wp_filesystem->is_dir($filename) ) {
			// force the "New File" to be the default option
			if ( $filename == LWC__NEW_FILE ) {
				echo '<option selected value="' . $filename['name'] . '">' . $filename['name'] . '</option>' . "\n";
			}
			
			echo '<option value="' . $filename['name'] . '">' . $filename['name'] . '</option>' . "\n";
		}
	}
	
	echo "</select>\n";
}

/**
 * Create the document formatting editor. 
 */
function lawrcustemail_create_format_editor() {
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
	submit_button( "Submit" );
	
	echo '</div>' . "\n";
}

?>
