<?php
/**
 * Plugin Name: Ultimate Block Patterns Builder
 * Plugin URI: https://wordpress.org/plugins/ultimate-block-patterns-builder/
 * Description: Create and manage custom block patterns for Gutenberg Editor
 * Version: 1.0.0
 * Author: Zakaria Binsaifullah
 * Author URI: https://makegutenblock.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultimate-block-patterns-builder
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// include admin page 
require_once plugin_dir_path( __FILE__ ) . 'admin/adminpage.php';

// Define plugin constants
define( 'UBPB_VERSION', '1.0.0' );
define( 'UBPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UBPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UBPB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// load plugin text domain
add_action( 'plugins_loaded', 'ubpb_load_textdomain' );
function ubpb_load_textdomain() {
    load_plugin_textdomain( 'ultimate-block-patterns-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Register hooks that are fired when the plugin is activated or deactivated.
// When the plugin is deleted, the uninstall.php file is loaded.
register_activation_hook( __FILE__, 'ubpb_activate');
register_deactivation_hook( __FILE__, 'ubpb_deactivate');

// activation hook 
function ubpb_activate() {
    // get role 
    $role = get_role( 'administrator' );
    if( ! empty ( $role ) ) {
        // add capability 
        $role->add_cap( 'upbp_create_patterns' );
        $role->add_cap( 'upbp_publish_patterns');
        $role->add_cap( 'upbp_read_private_patterns');
        $role->add_cap( 'upbp_edit_patterns');
        $role->add_cap( 'upbp_edit_others_patterns');
        $role->add_cap( 'upbp_edit_private_patterns');
        $role->add_cap( 'upbp_edit_published_patterns');
        $role->add_cap( 'upbp_delete_patterns');
        $role->add_cap( 'upbp_delete_private_patterns');
        $role->add_cap( 'upbp_delete_published_patterns');
        $role->add_cap( 'upbp_delete_others_patterns');
    }
}

// deactivation hook
function ubpb_deactivate() {
    // get role 
    $role = get_role( 'administrator' );
    if( ! empty ( $role ) ) {
        // remove capability 
        $role->remove_cap( 'upbp_create_patterns' );
        $role->remove_cap( 'upbp_publish_patterns');
        $role->remove_cap( 'upbp_read_private_patterns');
        $role->remove_cap( 'upbp_edit_patterns');
        $role->remove_cap( 'upbp_edit_others_patterns');
        $role->remove_cap( 'upbp_edit_private_patterns');
        $role->remove_cap( 'upbp_edit_published_patterns');
        $role->remove_cap( 'upbp_delete_patterns');
        $role->remove_cap( 'upbp_delete_private_patterns');
        $role->remove_cap( 'upbp_delete_published_patterns');
        $role->remove_cap( 'upbp_delete_others_patterns');
    }
}

// Register Custom Post Type

// REGISTER POST TYPE
add_action( 'init','upbp_register_post_type' );
function upbp_register_post_type() {
	$pattern_args = [
		'public'              => false,
		'publicly_queryable'  => false,
		'show_in_rest'        => true,
		'show_in_nav_menus'   => false,
		'show_in_admin_bar'   => true,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => null,
		'menu_icon'           => 'dashicons-screenoptions',
		'can_export'          => true,
		'delete_with_user'    => false,
		'hierarchical'        => false,
		'has_archive'         => false,
		'capability_type'     => 'upbp_pattern',
		'map_meta_cap'        => true,

		'capabilities'        => [
			// meta caps (don't assign these to roles)
			'edit_post'   => 'upbp_edit_pattern',
			'read_post'   => 'upbp_read_pattern',
			'delete_post' => 'upbp_delete_pattern',

			// primitive/meta caps
			'create_posts' => 'upbp_create_patterns',

			// primitive caps used outside of map_meta_cap()
			'edit_posts'         => 'upbp_edit_patterns',
			'edit_others_posts'  => 'upbp_edit_others_patterns',
			'publish_posts'      => 'upbp_publish_patterns',
			'read_private_posts' => 'upbp_read_private_patterns',

			// primitive caps used inside of map_meta_cap()
			'read'                   => 'read',
			'delete_posts'           => 'upbp_delete_patterns',
			'delete_private_posts'   => 'upbp_delete_private_patterns',
			'delete_published_posts' => 'upbp_delete_published_patterns',
			'delete_others_posts'    => 'upbp_delete_others_patterns',
			'edit_private_posts'     => 'upbp_edit_private_patterns',
			'edit_published_posts'   => 'upbp_edit_published_patterns'
		],

		'labels'              => [
			'name'                  => __( 'Patterns',                   'ultimate-blocks-pattern-builder' ),
			'singular_name'         => __( 'Pattern',                    'ultimate-blocks-pattern-builder' ),
			'menu_name'             => __( 'Block Patterns',             'ultimate-blocks-pattern-builder' ),
			'name_admin_bar'        => __( 'Pattern',                    'ultimate-blocks-pattern-builder' ),
			'add_new'               => __( 'New Pattern',                'ultimate-blocks-pattern-builder' ),
			'add_new_item'          => __( 'Add New Pattern',            'ultimate-blocks-pattern-builder' ),
			'edit_item'             => __( 'Edit Pattern',               'ultimate-blocks-pattern-builder' ),
			'new_item'              => __( 'New Pattern',                'ultimate-blocks-pattern-builder' ),
			'view_item'             => __( 'View Pattern',               'ultimate-blocks-pattern-builder' ),
			'view_items'            => __( 'View Patterns',              'ultimate-blocks-pattern-builder' ),
			'search_items'          => __( 'Search Patterns',            'ultimate-blocks-pattern-builder' ),
			'not_found'             => __( 'No patterns found',          'ultimate-blocks-pattern-builder' ),
			'not_found_in_trash'    => __( 'No patterns found in trash', 'ultimate-blocks-pattern-builder' ),
			'all_items'             => __( 'Patterns',                   'ultimate-blocks-pattern-builder' ),
			'featured_image'        => __( 'Pattern Image',              'ultimate-blocks-pattern-builder' ),
			'set_featured_image'    => __( 'Set pattern image',          'ultimate-blocks-pattern-builder' ),
			'remove_featured_image' => __( 'Remove pattern image',       'ultimate-blocks-pattern-builder' ),
			'use_featured_image'    => __( 'Use as pattern image',       'ultimate-blocks-pattern-builder' ),
			'insert_into_item'      => __( 'Insert into pattern',        'ultimate-blocks-pattern-builder' ),
			'uploaded_to_this_item' => __( 'Uploaded to this pattern',   'ultimate-blocks-pattern-builder' ),
			'filter_items_list'     => __( 'Filter patterns list',       'ultimate-blocks-pattern-builder' ),
			'items_list_navigation' => __( 'Patterns list navigation',   'ultimate-blocks-pattern-builder' ),
			'items_list'            => __( 'Patterns list',              'ultimate-blocks-pattern-builder' ),
		],

		// The rewrite handles the URL structure.
		'rewrite' => false,

		// What features the post type supports.
        // support title + editor + custom taxonomies
        'supports' => [ 'title', 'editor', 'excerpt'],
	];

	// Register the post type.
	register_post_type( 'upbp_pattern', $pattern_args );
}

// post title placeholder
add_filter( 'enter_title_here', 'upbp_title_placeholder' );

function upbp_title_placeholder( $title ) {
    $screen = get_current_screen();
    if ( 'upbp_pattern' == $screen->post_type ) {
        $title = __( 'Enter pattern name', 'ultimate-blocks-pattern-builder' );
    }
    return $title;
}


// register block pattern category
add_action( 'init', 'upbp_register_patterns_category' );

function upbp_register_patterns_category() {
    register_block_pattern_category(
        'ultimate-patterns',
        array( 'label' => __( 'Ultimate Patterns', 'ultimate-blocks-pattern-builder' ) )
    );
}

// register block pattern from all upbp_pattern post types
add_action( 'init', 'upbp_register_block_patterns' );

function upbp_register_block_patterns() {
    $args = array(
        'post_type'      => 'upbp_pattern',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );

    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $pattern_name        = get_the_title();
            $pattern_content     = get_the_content();
            $pattern_category    = 'ultimate-patterns';
            $pattern_title       = $pattern_name;
            $pattern_description = get_the_excerpt();
            $pattern_keywords    = array();
            $pattern_textdomain  = 'ultimate-blocks-pattern-builder';
            $pattern             = array(
                'title'       => $pattern_title,
                'content'     => $pattern_content,
                'categories'  => array( $pattern_category ),
                'description' => $pattern_description,
                'keywords'    => $pattern_keywords,
                'textdomain'  => $pattern_textdomain,
            );
            register_block_pattern( $pattern_name, $pattern );
        }
    }
    wp_reset_postdata();
}

// redirect user to upbp_help page after plugin activation
add_action( 'activated_plugin', 'upbp_redirect_after_activation' );

function upbp_redirect_after_activation( $plugin ) {
    if ( $plugin == plugin_basename( __FILE__ ) ) {
        exit( wp_redirect( admin_url( 'edit.php?post_type=upbp_pattern&page=upbp_help' ) ) );
    }
}