<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UBPB_Pattern_Manager {

    public function __construct() {
        add_filter( 'manage_upbp_pattern_posts_columns', [ $this, 'add_export_column' ] );
        add_action( 'manage_upbp_pattern_posts_custom_column', [ $this, 'render_export_column' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ubpb_import_pattern', [ $this, 'ajax_import_pattern' ] );
        add_action( 'admin_post_ubpb_export_pattern', [ $this, 'handle_export_pattern' ] );
    }

    public function enqueue_assets( $hook ) {
        global $post_type;
        if ( 'upbp_pattern' !== $post_type ) {
            return;
        }

        wp_enqueue_style( 'ubpb-admin-ui', plugin_dir_url( __FILE__ ) . 'css/ubpb-admin-ui.css', [], UBPB_VERSION );
        wp_enqueue_script( 'ubpb-admin-js', plugin_dir_url( __FILE__ ) . 'js/ubpb-admin.js', ['jquery'], UBPB_VERSION, true );
        
        wp_localize_script( 'ubpb-admin-js', 'ubpbConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ubpb_import_nonce' ),
            'strings' => [
                'importTitle' => __( 'Import Pattern from JSON', 'ultimate-block-patterns-builder' ),
                'importBtn' => __( 'Import from JSON', 'ultimate-block-patterns-builder' ),
                'importPlaceholder' => __( 'Drag and drop your JSON file here', 'ultimate-block-patterns-builder' ),
                'selectFile' => __( 'Select File', 'ultimate-block-patterns-builder' ),
                'invalidFile' => __( 'Invalid file type. Please upload a JSON file.', 'ultimate-block-patterns-builder' ),
                'cancel' => __( 'Cancel', 'ultimate-block-patterns-builder' ),
                'import' => __( 'Import', 'ultimate-block-patterns-builder' ),
                'importing' => __( 'Importing...', 'ultimate-block-patterns-builder' ),
                'success' => __( 'Pattern imported successfully! reloading...', 'ultimate-block-patterns-builder' ),
                'error' => __( 'Error importing pattern.', 'ultimate-block-patterns-builder' )
            ]
        ]);
    }

    public function add_export_column( $columns ) {
        $columns['json_export'] = __( 'Export', 'ultimate-block-patterns-builder' );
        return $columns;
    }

    public function render_export_column( $column, $post_id ) {
        if ( 'json_export' === $column ) {
            $export_url = admin_url( 'admin-post.php?action=ubpb_export_pattern&post_id=' . $post_id . '&_wpnonce=' . wp_create_nonce( 'ubpb_export_' . $post_id ) );
            echo '<a href="' . esc_url( $export_url ) . '" class="button button-secondary ubpb-export-btn" title="Export to JSON"><span class="dashicons dashicons-download" style="line-height:1.3; font-size: 14px; margin-top: 3px;"></span> ' . __( 'JSON', 'ultimate-block-patterns-builder' ) . '</a>';
        }
    }

    public function handle_export_pattern() {
        if ( ! current_user_can( 'edit_posts' ) ) return;
        
        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        check_admin_referer( 'ubpb_export_' . $post_id );

        $post = get_post( $post_id );
        if ( ! $post || 'upbp_pattern' !== $post->post_type ) {
            wp_die( 'Invalid pattern.' );
        }

        // Standard WP Pattern / Reusable Block Format
        $data = [
            '__file'  => 'wp_block',
            'title'   => $post->post_title,
            'content' => $post->post_content,
        ];

        $json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        // Sanitize filename
        $filename = sanitize_file_name( $post->post_name . '.json' );

        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Length: ' . strlen( $json ) );
        
        echo $json;
        exit;
    }

    public function ajax_import_pattern() {
        check_ajax_referer( 'ubpb_import_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'File upload failed.' );
        }

        $file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
        $data = json_decode( $file_content, true );

        if ( ! $data ) {
            wp_send_json_error( 'Invalid JSON format' );
        }
        
        $content = '';
        $title = '';

        // Handle both standard WP format and simple format
        if ( isset( $data['__file'] ) && 'wp_block' === $data['__file'] ) {
             // It's a WP standard block json
             $content = isset( $data['content'] ) ? $data['content'] : '';
             $title   = isset( $data['title'] ) ? $data['title'] : '';
        } elseif ( isset( $data['content'] ) ) {
             // Fallback to our simple format or other
             $content = $data['content'];
             $title   = isset( $data['title'] ) ? $data['title'] : '';
        } else {
             wp_send_json_error( 'JSON must contain "content" field or be a valid WP block JSON.' );
        }

        if ( empty( $title ) ) {
            // Try to use filename
            $filename = pathinfo( $_FILES['import_file']['name'], PATHINFO_FILENAME );
            $title = sanitize_text_field( $filename );
        }

        if ( empty( $title ) ) {
            $title = 'Imported Pattern - ' . date( 'Y-m-d H:i' );
        }
        


        // Process images
        $content = $this->process_media_sideload( $content );

        // Create post
        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $title ),
            'post_content' => $content,
            'post_type'    => 'upbp_pattern',
            'post_status'  => 'publish'
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }
        
        wp_send_json_success( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
    }

    private function process_media_sideload( $content ) {
        if ( ! preg_match_all( '/(https?:\/\/[^\s"\'\(\)]+\.(?:png|jpg|jpeg|gif|webp))/i', $content, $matches ) ) {
            return $content;
        }

        $urls = array_unique( $matches[0] );
        $site_url = home_url();

        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        foreach ( $urls as $url ) {
            if ( strpos( $url, $site_url ) !== false ) {
                continue;
            }

            $new_url = media_sideload_image( $url, 0, null, 'src' );

            if ( ! is_wp_error( $new_url ) ) {
                $content = str_replace( $url, $new_url, $content );
            }
        }

        return $content;
    }
}

new UBPB_Pattern_Manager();
