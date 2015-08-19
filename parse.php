<?php


// definitons of formatting tags
define( "LWC__NAME_OPEN_TAG", "[NAME]" );
define( "LWC__NAME_CLOSE_TAG", "[/NAME]" );
define( "LWC__FORMAT_OPEN_TAG", "[FORMAT]" );
define( "LWC__FORMAT_CLOSE_TAG", "[/FORMAT]" );
define( "LWC__POST_TAG", "[POST]" );
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
	if ( has_post_thumbnail($post_id) ) {
		$images = wp_get_attachment_image_src( get_post_thumbnail_id($post_id) );
		return $images[0];
	}
	else {
		$media = get_attached_media( 'image', $post_id );
		$media = array_filter( $media );
		if ( empty($media) ) {
			return "";
		} else {
			return wp_get_attachment_img_src( $media[0]->ID );
		}
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
	// remove everything after a [caption] tag
	$caption_open = stripos( $post, "[caption" );
	$post = substr( $post, 0, $caption_open );
	
	return $post;
}

/**
 * Replace and remove formatting tags ([NAME], [/FORMAT], etc.)
 *
 * @param String $format The format file to be analyzed.
 * @param Integer $post_id Wordpress ID of the post to be sent in the email.
 * @return 
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
	
	// replace the [POST] tag with the post content
	$post_content = get_post_field( 'post_content', $post_id );
	$post_content = lawrcustemail_isolate_post_content( $post_content );
	$format = str_ireplace( LWC__POST_TAG, $post_content, $format );
	
	return $format;
}

?>
