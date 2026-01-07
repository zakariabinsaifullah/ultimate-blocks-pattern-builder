<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UBPB_AI_Builder {

    private $option_key = 'ubpb_gemini_api_key';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ubpb_save_api_key', [ $this, 'ajax_save_api_key' ] );
        add_action( 'wp_ajax_ubpb_generate_pattern', [ $this, 'ajax_generate_pattern' ] );
        add_action( 'wp_ajax_ubpb_add_ai_pattern', [ $this, 'ajax_add_ai_pattern' ] );
    }

    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=upbp_pattern',
            __( 'AI Pattern Builder', 'ultimate-block-patterns-builder' ),
            __( 'AI Pattern Builder', 'ultimate-block-patterns-builder' ),
            'manage_options',
            'ubpb_ai_builder',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'upbp_pattern_page_ubpb_ai_builder' !== $hook ) {
            return;
        }

        // Enqueue Core Block Styles for the preview
        wp_enqueue_style( 'wp-components' );
        wp_enqueue_style( 'wp-block-editor' );
        wp_enqueue_style( 'wp-editor' );
        wp_enqueue_style( 'wp-edit-blocks' );
        wp_enqueue_style( 'wp-block-library' );
        wp_enqueue_style( 'wp-block-library-theme' );

        wp_enqueue_style( 'ubpb-ai-builder', plugin_dir_url( __FILE__ ) . 'css/ubpb-ai-builder.css', [], '1.0.0' );
        wp_enqueue_script( 'ubpb-ai-builder', plugin_dir_url( __FILE__ ) . 'js/ubpb-ai-builder.js', ['jquery'], '1.0.0', true );
        
        wp_localize_script( 'ubpb-ai-builder', 'ubpbAI', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ubpb_ai_nonce' ),
            'apiKey'  => get_option( $this->option_key ),
            'strings' => [
                'saving' => __( 'Saving...', 'ultimate-block-patterns-builder' ),
                'saved' => __( 'Saved!', 'ultimate-block-patterns-builder' ),
                'generating' => __( 'Generating Pattern...', 'ultimate-block-patterns-builder' ),
                'error' => __( 'Error occurred. Please try again.', 'ultimate-block-patterns-builder' ),
                'importing' => __( 'Adding to Patterns...', 'ultimate-block-patterns-builder' ),
                'imported' => __( 'Added successfully!', 'ultimate-block-patterns-builder' ),
                'import' => __( 'Add to Patterns', 'ultimate-block-patterns-builder' ),
            ]
        ]);
    }

    public function render_page() {
        $api_key = get_option( $this->option_key );
        ?>
        <div class="wrap ubpb-ai-wrapper">
            <h1><?php _e( 'AI Pattern Builder', 'ultimate-block-patterns-builder' ); ?></h1>
            
            <?php if ( ! $api_key ) : ?>
                <div class="ubpb-api-setup" id="ubpb-api-setup-screen">
                    <div class="ubpb-setup-card">
                        <h2><?php _e( 'Setup Gemini API', 'ultimate-block-patterns-builder' ); ?></h2>
                        <p><?php _e( 'To use the AI Pattern Builder, you need a free API key from Google Gemini.', 'ultimate-block-patterns-builder' ); ?></p>
                        <p><a href="https://aistudio.google.com/app/apikey" target="_blank"><?php _e( 'Get your API Key here', 'ultimate-block-patterns-builder' ); ?></a></p>
                        
                        <div class="ubpb-form-group">
                            <input type="password" id="ubpb_api_key_input" placeholder="Paste your API Key here" class="large-text">
                            <button class="button button-primary" id="ubpb-save-api-key"><?php _e( 'Save & Continue', 'ultimate-block-patterns-builder' ); ?></button>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                 <div class="ubpb-chat-interface">
                    <div class="ubpb-chat-header">
                        <span class="dashicons dashicons-superhero"></span>
                        <h3><?php _e( 'Pattern Assistant', 'ultimate-block-patterns-builder' ); ?></h3>
                        <div class="ubpb-header-actions">
                             <button id="ubpb-reset-key" class="button button-link"><?php _e( 'Change API Key', 'ultimate-block-patterns-builder' ); ?></button>
                        </div>
                    </div>

                    <div class="ubpb-chat-window" id="ubpb-chat-window">
                        <div class="ubpb-message system">
                            <div class="ubpb-msg-content">
                                <?php _e( 'Hello! Describe the section or pattern you want to build. For example: "A pricing table with 3 columns, blue styling, and buy buttons".', 'ultimate-block-patterns-builder' ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="ubpb-chat-input-area">
                        <textarea id="ubpb-chat-input" placeholder="<?php _e( 'Describe your pattern...', 'ultimate-block-patterns-builder' ); ?>" rows="1"></textarea>
                        <button id="ubpb-send-prompt" class="button button-primary is-large">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                 </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function ajax_save_api_key() {
        check_ajax_referer( 'ubpb_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $key = trim( $key ); 
        
        if ( $key ) {
            update_option( $this->option_key, $key );
            wp_send_json_success();
        } else {
             delete_option( $this->option_key );
             wp_send_json_success();
        }
        wp_send_json_error( 'Invalid key' );
    }

    public function ajax_generate_pattern() {
        check_ajax_referer( 'ubpb_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

        $prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( $_POST['prompt'] ) : '';
        
        // 1. Clean the API key (removes accidental spaces)
        $api_key = get_option( $this->option_key );
        $api_key = preg_replace('/\s+/', '', $api_key); 

        if ( empty( $prompt ) || empty( $api_key ) ) {
            wp_send_json_error( 'Missing prompt or API key.' );
        }

        $prompt = trim( $prompt );

        // Fetch existing patterns for context
        $existing_patterns = $this->get_cached_patterns();
        $patterns_list_str = "[]";
        
        if ( ! empty( $existing_patterns ) ) {
            shuffle( $existing_patterns ); // Randomize to ensure variety
            
            $simplified_list = array_map( function( $p ) {
                return [
                    'id' => isset($p['id']) ? $p['id'] : '',
                    'title' => isset($p['title']) ? $p['title'] : '',
                    'category' => isset($p['category']) ? $p['category'] : '',
                    // We don't send content to save tokens
                ];
            }, $existing_patterns );
            $patterns_list_str = json_encode( $simplified_list );
        }

        // FIX: Using 'gemini-2.0-flash' because it was explicitly found in your diagnostic list.
        $base_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

        // 2. Build the safe URL
        $url = add_query_arg( 'key', $api_key, $base_url );

        $system_prompt = "You are an expert WordPress Gutenberg Block Developer. 
        Your task is to either select a matching existing pattern from a provided list OR generate new valid Gutenberg Block Pattern markup based on the user's description.
        
        Context (Existing Patterns):
        $patterns_list_str
        
        Rules:
        1. FIRST, Analyze the User Request for keywords matching any 'title' or 'category' in the 'Context (Existing Patterns)'.
           - If the request contains keywords (e.g., 'about us', 'pricing', 'hero', 'team', 'contact') that match an existing pattern's CATEGORY or TITLE, you MUST prefer the existing pattern.
           - IGNORE verbs like 'create', 'generate', 'build', 'make'. Focus on the TOPIC.
           - Example: If user says \"Create an about us section\" and you have patterns with category \"About\", pick one RANDOMLY from the matches.
           - If multiple patterns match, you MUST select one RANDOMLY to provide variety. Match the specific intent if possible (e.g. '2 columns'), otherwise Randomize.
           - If a match is found, return ONLY a string in this specific format: USE_EXISTING_PATTERN: <id> | INSTRUCTIONS: <summary of specific user requested changes>
           - Example 1: User says \"Create an about us section\" -> Return: USE_EXISTING_PATTERN: 105
           - Example 2: User says \"About section with title 'About CodeDivo'\" -> Return: USE_EXISTING_PATTERN: 105 | INSTRUCTIONS: Change title text to \"About CodeDivo\"
           - CRITICAL: Do NOT try to generate the pattern code if you find a match. Just return the marker string.
        2. IF NO match found (e.g. user asks for 'Space Rocket Launch' and you only have 'Business' patterns), generate new code following these strict rules:
           a. Use ONLY Core WordPress Blocks (wp:group, wp:heading, wp:paragraph, wp:image, wp:buttons, wp:button, wp:columns, wp:column, wp:spacer, wp:cover).
           b. STRICT RULE: DO NOT use ANY CSS classes for colors (e.g 'has-white-color', 'has-111111-color'). These are FORBIDDEN.
           c. STRICT RULE: ALL styling (colors, typography, spacing, borders) MUST be defined in the block 'style' attribute object. 
              Example: {\"style\":{\"color\":{\"text\":\"#111111\",\"background\":\"#ffffff\"},\"spacing\":{\"padding\":{\"top\":\"20px\",\"bottom\":\"20px\"}}}}
           d. The Outer-Most Parent Block (usually wp:group) MUST be Full Width (\"align\":\"full\") and have a constrained layout width of 1200px (layout:{\"type\":\"constrained\",\"contentSize\":\"1200px\"}).
           e. Return ONLY the raw block markup (HTML string with comments). 
           f. Do NOT use Markdown code blocks (```html). Just output the raw string.
           g. Do NOT include any conversational text.
        ";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt . "\n\nUser Request: " . $prompt]
                    ]
                ]
            ]
        ];

        // 3. Send Request
        $response = wp_remote_post( $url, [
            'body'    => json_encode( $body ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 45
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'API Request Failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( $code !== 200 ) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API Error';
            wp_send_json_error( 'Gemini API Error (' . $code . '): ' . $error_msg );
        }

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $generated_content = $data['candidates'][0]['content']['parts'][0]['text'];
            $generated_content = trim( $generated_content );

            // Check if AI selected an existing pattern
            if ( strpos( $generated_content, 'USE_EXISTING_PATTERN:' ) !== false ) {
                // Parse "USE_EXISTING_PATTERN: 105 | INSTRUCTIONS: Change title..."
                $parts = explode( '|', $generated_content, 2 );
                $pattern_id_str = trim( str_replace( 'USE_EXISTING_PATTERN:', '', $parts[0] ) );
                $instructions = isset($parts[1]) ? trim( str_replace( 'INSTRUCTIONS:', '', $parts[1] ) ) : '';

                // Find content in cache
                $found_content = '';
                foreach ( $existing_patterns as $p ) {
                    if ( (string)$p['id'] === (string)$pattern_id_str ) {
                        $found_content = $p['content'];
                        break;
                    }
                }
                
                if ( $found_content ) {
                    // IF we have specific modification instructions, perform a 2nd pass
                    if ( ! empty( $instructions ) ) {
                        $mod_system_prompt = "You are a WordPress Code Expert. Apply these specific user changes to the provided block pattern markup. Return ONLY the modified valid HTML. Do not output markdown.";
                        $mod_user_prompt = "CODE:\n" . $found_content . "\n\nCHANGES REQUIRED:\n" . $instructions;
                        
                        $mod_body = [
                            'contents' => [
                                ['parts' => [['text' => $mod_system_prompt . "\n\n" . $mod_user_prompt]]]
                            ]
                        ];
                        
                        $mod_response = wp_remote_post( $url, [
                            'body'    => json_encode( $mod_body ),
                            'headers' => [ 'Content-Type' => 'application/json' ],
                            'timeout' => 45
                        ] );
                        
                        if ( ! is_wp_error( $mod_response ) && wp_remote_retrieve_response_code( $mod_response ) === 200 ) {
                             $mod_data = json_decode( wp_remote_retrieve_body( $mod_response ), true );
                             if ( isset( $mod_data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                                 $found_content = $mod_data['candidates'][0]['content']['parts'][0]['text'];
                                 // Clean cleanup
                                 $found_content = preg_replace( '/^```html\s*/i', '', $found_content );
                                 $found_content = preg_replace( '/^```\s*/', '', $found_content );
                                 $found_content = preg_replace( '/\s*```$/', '', $found_content );
                                 $found_content = trim( $found_content );
                             }
                        }
                    }

                    // Render the block content for better preview
                    $preview_html = do_blocks( $found_content );
                    
                     wp_send_json_success( [ 
                        'content' => $found_content, 
                        'preview' => $preview_html,
                        'source' => 'existing'
                    ] );
                     return;
                }
            }
            
            // Clean up Markdown formatting
            $generated_content = preg_replace( '/^```html\s*/i', '', $generated_content );
            $generated_content = preg_replace( '/^```\s*/', '', $generated_content );
            $generated_content = preg_replace( '/\s*```$/', '', $generated_content );
            
            // Also render AI preview
            $preview_html = do_blocks( $generated_content );
            
            wp_send_json_success( [ 
                'content' => $generated_content, 
                'preview' => $preview_html,
                'source' => 'ai' 
            ] );
        } else {
             wp_send_json_error( 'Failed to generate content. The AI returned an empty response.' );
        }
    }

    private function get_cached_patterns() {
        // Reuse the transient from UBPB_Pattern_Library
        return get_transient( 'ubpb_patterns_library_cache' ) ?: [];
    }

    public function ajax_add_ai_pattern() {
        check_ajax_referer( 'ubpb_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

        // Using wp_kses_post would be safer, but patterns often contain complex HTML comments
        // that kses might strip. If you trust the admin user, strict sanitization here is tricky.
        $content = isset( $_POST['content'] ) ? $_POST['content'] : ''; 
        
        $title = 'AI Pattern ' . date('Y-m-d H:i');
        
        if ( preg_match( '/(.*?)/s', $content, $matches ) ) {
             $heading_html = $matches[1];
             $heading_text = strip_tags( $heading_html );
             if ( $heading_text ) {
                 $title = wp_trim_words( $heading_text, 5, '' );
             }
        }

        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'upbp_pattern',
            'post_status'  => 'publish'
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }
        
        wp_send_json_success( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
    }
}

new UBPB_AI_Builder();