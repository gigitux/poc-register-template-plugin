<?php
/**
 * Plugin Name:       poc-register-template-plugin
 * Description:       Example static block scaffolded with Create Block tool.
 * Requires at least: 5.9
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       poc-register-template-plugin
 *
 * @package           create-block
 */

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function create_block_poc_register_template_plugin_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'create_block_poc_register_template_plugin_block_init' );

const THEME = 'create-block';

function create_template_object( $template_file ) {
	$template_slug = substr(
		$template_file,
		// Starting position of slug.
		strpos( $template_file, 'templates' . DIRECTORY_SEPARATOR ) + 1 + strlen( 'templates' ),
		// Subtract ending '.html'.
		-5
	);

	return array(
		'slug'  => $template_slug,
		'path'  => $template_file,
		'theme' => THEME,
		'type'  => 'wp_template',
	);
}


function add_block_templates( $query_result, $query, $template_type ) {

	if ( 'wp_template' !== $template_type ) {
		return $query_result;
	}
	$template_folder = plugin_dir_path( __FILE__ ) . 'templates';
	$template_files  = get_block_templates_paths( $template_folder );

	foreach ( $template_files as $template_file ) {
		$new_template_item = create_template_object( $template_file );

		$query_result[] = build_block_template_result_from_file( $new_template_item, $new_template_item['type'] );
	}

	return $query_result;

}


// Copy-pasted
function build_block_template_result_from_file( $template_file, $template_type ) {
	$content                  = file_get_contents( $template_file['path'] );
	$template                 = new WP_Block_Template();
	$template->id             = THEME . '//' . $template_file['slug'];
	$template->slug           = $template_file['slug'];
	$template->content        = $content;
	$template->source         = 'plugin';
	$template->type           = $template_type;
	$template->title          = $template_file['slug'];
	$template->status         = 'publish';
	$template->has_theme_file = true;
	$template->is_custom      = false;

	if ( 'wp_template' === $template_type && isset( $default_template_types[ $template_file['slug'] ] ) ) {
		$template->description = $default_template_types[ $template_file['slug'] ]['description'];
		$template->title       = $default_template_types[ $template_file['slug'] ]['title'];
		$template->is_custom   = false;
	}

	if ( 'wp_template' === $template_type && isset( $template_file['postTypes'] ) ) {
		$template->post_types = $template_file['postTypes'];
	}

	if ( 'wp_template_part' === $template_type && isset( $template_file['area'] ) ) {
		$template->area = $template_file['area'];
	}

	return $template;
}

// Copy-pasted
function build_block_template_result_from_post( $post ) {
	$default_template_types = get_default_block_template_types();
	$terms                  = get_the_terms( $post, 'wp_theme' );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	if ( ! $terms ) {
		return new WP_Error( 'template_missing_theme', __( 'No theme is defined for this template.' ) );
	}

	$theme          = $terms[0]->name;
	$template_file  = build_block_template_result_from_file( $post->post_type, $post->post_name );
	$has_theme_file = get_stylesheet() === $theme && null !== $template_file;

	$origin           = get_post_meta( $post->ID, 'origin', true );
	$is_wp_suggestion = get_post_meta( $post->ID, 'is_wp_suggestion', true );

	$template                 = new WP_Block_Template();
	$template->wp_id          = $post->ID;
	$template->id             = $theme . '//' . $post->post_name;
	$template->theme          = $theme;
	$template->content        = $post->post_content;
	$template->slug           = $post->post_name;
	$template->source         = 'custom';
	$template->origin         = ! empty( $origin ) ? $origin : null;
	$template->type           = $post->post_type;
	$template->description    = $post->post_excerpt;
	$template->title          = $post->post_title;
	$template->status         = $post->post_status;
	$template->has_theme_file = $has_theme_file;
	$template->is_custom      = empty( $is_wp_suggestion );
	$template->author         = $post->post_author;

	if ( 'wp_template' === $post->post_type && $has_theme_file && isset( $template_file['postTypes'] ) ) {
		$template->post_types = $template_file['postTypes'];
	}

	if ( 'wp_template' === $post->post_type && isset( $default_template_types[ $template->slug ] ) ) {
		$template->is_custom = false;
	}

	if ( 'wp_template_part' === $post->post_type ) {
		$type_terms = get_the_terms( $post, 'wp_template_part_area' );
		if ( ! is_wp_error( $type_terms ) && false !== $type_terms ) {
			$template->area = $type_terms[0]->name;
		}
	}

	return $template;
}


// Copy-pasted
function get_block_templates_paths( $base_directory ) {
	$path_list = array();
	if ( file_exists( $base_directory ) ) {
		$nested_files      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_directory ) );
		$nested_html_files = new RegexIterator( $nested_files, '/^.+\.html$/i', RecursiveRegexIterator::GET_MATCH );
		foreach ( $nested_html_files as $path => $file ) {
			$path_list[] = $path;
		}
	}
	return $path_list;
}

function pre_get_block_template( $template, $id, $template_type ) {
	$template_name_parts  = explode( '//', $id );
	list( $theme, $slug ) = $template_name_parts;

	$wp_query_args  = array(
		'post_type'     => $template_type,
		'post_status'   => array( 'auto-draft', 'draft', 'publish', 'trash' ),
		'no_found_rows' => true,
		'tax_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => $theme,
			),
		),
	);
	$template_query = new \WP_Query( $wp_query_args );

	$posts = $template_query->posts;

	// If we have more than one result from the query, it means that the current template is present in the db (has
	// been customized by the user) and we should not return the `archive-product` template.
	if ( count( $posts ) > 1 ) {
		return null;
	}

	if ( count( $posts ) ) {
		$template = build_block_template_result_from_post( $posts[0] );

		if ( ! is_wp_error( $template ) ) {
			$template->id    = $theme . '//' . $slug;
			$template->slug  = $slug;
			$template->title = $slug;
			// $template->description = BlockTemplateUtils::get_block_template_description( $slug );
			unset( $template->source );

			return $template;
		}
	}

	return $template;
}

function get_block_file( $template, $id, $template_type ) {
	$template_name_parts = explode( '//', $id );

	if ( count( $template_name_parts ) < 2 ) {
		return $template;
	}

	list( $template_id, $template_slug ) = $template_name_parts;

	$directory          = plugin_dir_path( __FILE__ ) . 'templates';
	$template_files     = get_block_templates_paths( $directory );
	$template_file_path = $directory . '/' . $template_slug . '.html';
	$template_object    = create_template_object( $template_file_path );
	$template_built     = build_block_template_result_from_file( $template_object, $template_type );

	if ( null !== $template_built ) {
		return $template_built;
	}

	// Hand back over to Gutenberg if we can't find a template.
	return $template;
}

add_filter( 'pre_get_block_template', 'pre_get_block_template', 10, 3 );
add_filter( 'pre_get_block_file_template', 'get_block_file', 10, 3 );
add_filter( 'get_block_templates', 'add_block_templates', 10, 3 );
