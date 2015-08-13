<?php

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
	
	if ( $wp_filesystem->exists($filename) ) {
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
			
	echo '<form id="format_form" method="post">';
	echo '<select id="format_select" onchange="format_select_change(this.value)">';
	
	// get all files (hidden or not) from the relevant directory (recursively search through directories, too)
	$files = $wp_filesystem->dirlist( '.', $include_hidden = true, $recursive = true );
	
	// skip directories in the <select> tag
	foreach ( $files as $filename ) {
		if ( !$wp_filesystem->is_dir($filename) ) {
			echo '<option value="' . $filename['name'] . '">' . $filename['name'] . '</option>';
		}
	}
	
	echo '</select>';
	echo '</form>' . "\n";
}

/**
 * Create the document formatting editor. 
 */
function lawrcustemail_create_format_editor() {
	global $wp_filesystem;

	echo '<div id="format_editor_div">';
	
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
	wp_editor( "Please select a formatting file.", 'format_editor', $editor_args );
	submit_button( "Submit" );
	
	echo '</div>' . "\n";
}

?>
