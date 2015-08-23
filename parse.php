<?php

// definitons of formatting tags
define( "LWC__NAME_OPEN_TAG", "[NAME]" );
define( "LWC__NAME_CLOSE_TAG", "[/NAME]" );
define( "LWC__FORMAT_OPEN_TAG", "[FORMAT]" );
define( "LWC__FORMAT_CLOSE_TAG", "[/FORMAT]" );
define( "LWC__POST_TAG", "[POST]" );
define( "LWC__POST_TITLE_TAG", "[TITLE]" );
define( "LWC__POST_AUTHOR_TAG", "[AUTHOR]" );
define( "LWC__POST_IMAGE_TAG", "[POST_IMG]" );
define( "LWC__IMAGE_OPEN_TAG", "[IMAGE]" );
define( "LWC__IMAGE_CLOSE_TAG", "[/IMAGE]" );

/**
 * Get the <img> tag for either the featured post image or the first image displayed in a post.
 *
 * @param Integer $post_id Wordpress ID of the relevant post.
 * @return String The HTML of the <img> tag, or an empty string if no image is in the post.
 */
function lawrcustemail_get_post_img($post_id)
{
	$media = array_filter( get_attached_media('image', $post_id) );
	if ( empty($media) ) {
		return "";
	} else {
		$first_media = array_shift( array_values($media) );
		return wp_get_attachment_image( $first_media->ID );
	}
}
 
/**
 * Isolate the text of a post by removing <img> tags, etc. 
 *
 * @param String $post The raw text/html content of a post.
 * @return String The isolated text of the post.
 */ 
function lawrcustemail_isolate_post_content($post)
{
	// remove everything within [caption] tags
	while ( ($caption_open = stripos($post, "[caption")) !== FALSE ) {
		$caption_close = stripos( $post, "[/caption]" );
		$post = substr( $post, 0, $caption_open ) . substr( $post, $caption_close + strlen("[/caption]") );
	}
	
	// remove all <img> tags
	while ( ($img_open = stripos($post, "<img")) !== FALSE ) {
		$img_close = stripos( $post, "/>", $img_open );
		$post = substr( $post, 0, $img_open ) . substr( $post, $img_close + strlen("/>") );
	}
	
	return $post;
}

/**
 * Replace and remove formatting tags ([NAME], [/FORMAT], etc.)
 *
 * @param String $format The format file to be analyzed.
 * @param Integer $post_id Wordpress ID of the post to be sent in the email.
 * @return String The prepared html code.
 */
function lawrcustemail_parse($format, $post_id)
{
	// remove [NAME] tags and everything in beteween
	$name_close = stripos( $format, LWC__NAME_CLOSE_TAG );
	$format = substr( $format, $name_close + strlen(LWC__NAME_CLOSE_TAG) );
	
	// remove the [FORMAT] and [/FORMAT] tags
	$format_open = stripos( $format, LWC__FORMAT_OPEN_TAG );
	$format = substr( $format, $format_open + strlen(LWC__FORMAT_OPEN_TAG) );
	$format_close = stripos( $format, LWC__FORMAT_CLOSE_TAG );
	$format = substr( $format, 0, $format_close );
	
	// add the featured image of the post in place of the [POST_IMG] tag
	$featured_img_html = lawrcustemail_get_post_img( $post_id );
	$format = str_ireplace( LWC__POST_IMAGE_TAG, $featured_img_html, $format );
	
	// replace the [POST], [POST_TITLE] tags with the relevant content
	$post_content = lawrcustemail_isolate_post_content( get_post_field('post_content', $post_id) );
	$post_title = get_post_field( 'post_title', $post_id );
	$format = str_ireplace( LWC__POST_TAG, $post_content, $format );
	$format = str_ireplace( LWC__POST_TITLE_TAG, $post_title, $format );
	
	/* since someone decided to use the 'Custom Author Byline' plugin (which is really basic), processing the [AUTHOR]
	 * tag takes a bit of extra code (you have to get the post object by its ID and then get the metadata for the the 
	 * post since the custom author byline plugin filters that. I could probs just edit functions.php, but then the 
	 * plugin wouldn't be isolated) */
	$post_obj = get_post( $post_id );
	setup_postdata( $post_obj );
	$post_author = get_post_meta($post_obj->ID, 'author', TRUE);
	if ( !$post_author ) {
		$post_author = "admin";
	}
	$format = str_ireplace( LWC__POST_AUTHOR_TAG, $post_author, $format );
	
	// add in images specified in [IMAGE] tags
	while ( ($img_open = stripos($format, LWC__IMAGE_OPEN_TAG)) !== FALSE ) {
		$img_close = stripos( $format, LWC__IMAGE_CLOSE_TAG );
		$img_substr = substr( $format, $img_open, $img_close + strlen(LWC__IMAGE_CLOSE_TAG) - $img_open );	// the [IMAGE] tags and everything in between
		$img_url = str_ireplace( array(LWC__IMAGE_OPEN_TAG, LWC__IMAGE_CLOSE_TAG), '', $img_substr );
		$img_url = trim( $img_url );
		$img_html = "<img src=\"$img_url\">";
		$format = str_ireplace( $img_substr, $img_html, $format );
	}
	
	return $format;
}

?>
