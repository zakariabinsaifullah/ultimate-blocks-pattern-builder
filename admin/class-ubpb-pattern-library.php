<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UBPB_Pattern_Library {

    private $api_url = 'https://gutenlayouts.com/wp-json/gutenlayouts/v1/patterns';
    private $transient_key = 'ubpb_patterns_library_cache';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ubpb_refresh_library', [ $this, 'ajax_refresh_library' ] );
        add_action( 'wp_ajax_ubpb_import_library_pattern', [ $this, 'ajax_import_library_pattern' ] );
    }

    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=upbp_pattern',
            __( 'Patterns Library', 'ultimate-block-patterns-builder' ),
            __( 'Patterns Library', 'ultimate-block-patterns-builder' ),
            'manage_options',
            'ubpb_patterns_library',
            [ $this, 'render_page' ]
        );
    }

    public function get_patterns( $force_refresh = false ) {
        $patterns = get_transient( $this->transient_key );

        if ( false === $patterns || $force_refresh ) {
            $response = wp_remote_get( $this->api_url, [ 'timeout' => 20 ] );

            if ( is_wp_error( $response ) ) {
                return [];
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( ! is_array( $data ) ) {
                return [];
            }

            $patterns = $data;
            set_transient( $this->transient_key, $patterns, 24 * HOUR_IN_SECONDS );
        }

        return $patterns;
    }

    public function render_page() {
        ?>
        <div class="wrap ubpb-library-wrapper">
            <div class="ubpb-library-header">
                <div>
                    <h1 class="wp-heading-inline"><?php _e( 'Patterns Library', 'ultimate-block-patterns-builder' ); ?></h1>
                    <p class="ubpb-header-desc"><?php _e( 'Browse and import ready-to-use block patterns.', 'ultimate-block-patterns-builder' ); ?></p>
                </div>
                <button id="ubpb-library-refresh" class="button">
                    <span class="dashicons dashicons-update"></span> <?php _e( 'Refresh Data', 'ultimate-block-patterns-builder' ); ?>
                </button>
            </div>
            
            <div id="ubpb-library-app">
                <!-- App rendered via JS -->
                <div class="ubpb-loading-state">
                    <span class="spinner is-active"></span> <?php _e( 'Loading library...', 'ultimate-block-patterns-builder' ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( 'upbp_pattern_page_ubpb_patterns_library' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ubpb-library-css', plugin_dir_url( __FILE__ ) . 'css/ubpb-library.css', [], UBPB_VERSION );
        wp_enqueue_script( 'ubpb-library-js', plugin_dir_url( __FILE__ ) . 'js/ubpb-library.js', ['jquery', 'wp-util'], UBPB_VERSION, true );

        $patterns = $this->get_patterns();

        wp_localize_script( 'ubpb-library-js', 'ubpbLibraryConfig', [
            'patterns' => $patterns,
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ubpb_library_nonce' ),
            'strings' => [
                'importing' => __( 'Importing...', 'ultimate-block-patterns-builder' ),
                'imported' => __( 'Imported', 'ultimate-block-patterns-builder' ),
                'import' => __( 'Add Pattern', 'ultimate-block-patterns-builder' ),
                'view' => __( 'Edit Pattern', 'ultimate-block-patterns-builder' ),
                'unlockPro' => __( 'Unlock Pro', 'ultimate-block-patterns-builder' ),
                'error' => __( 'Error', 'ultimate-block-patterns-builder' ),
                'allCategories' => __( 'All Categories', 'ultimate-block-patterns-builder' ),
                'searchPlaceholder' => __( 'Search patterns...', 'ultimate-block-patterns-builder' ),
                'noResults' => __( 'No patterns found.', 'ultimate-block-patterns-builder' ),
            ]
        ] );
    }

    public function ajax_refresh_library() {
        check_ajax_referer( 'ubpb_library_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $patterns = $this->get_patterns( true );
        wp_send_json_success( $patterns );
    }

    public function ajax_import_library_pattern() {
        check_ajax_referer( 'ubpb_library_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : 'Imported Pattern';
        $content = isset( $_POST['content'] ) ? $_POST['content'] : ''; 

        // Process images
        $content = $this->process_media_sideload( $content );

        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'upbp_pattern',
            'post_status'  => 'publish'
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }

        wp_send_json_success( [
            'editLink' => get_edit_post_link( $post_id, 'raw' )
        ] );
    }

    private function process_media_sideload( $content ) {
        // Find all image URLs with common extensions
        // Matches urls ending in extension, inside quotes or parentheses (for css url())
        // Refined regex to match standard image extensions
        if ( ! preg_match_all( '/(https?:\/\/[^\s"\'\(\)]+\.(?:png|jpg|jpeg|gif|webp))/i', $content, $matches ) ) {
            return $content;
        }

        $urls = array_unique( $matches[0] );
        $site_url = home_url();

        // Load required WP admin files for media sideloading
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        foreach ( $urls as $url ) {
            // Skip if already local
            if ( strpos( $url, $site_url ) !== false ) {
                continue;
            }

            // Attempt to download and attach
            // passing 0 as post_id to unattach initially, or could attach to the new post later. 
            // returning 'src' gives just the url
            $new_url = media_sideload_image( $url, 0, null, 'src' );

            if ( ! is_wp_error( $new_url ) ) {
                $content = str_replace( $url, $new_url, $content );
            }
        }

        return $content;
    }
}

new UBPB_Pattern_Library();
