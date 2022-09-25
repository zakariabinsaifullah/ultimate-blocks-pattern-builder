<?php 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// create admin sub menu page for block patterns
add_action( 'admin_menu', 'ubpb_admin_menu' );
function ubpb_admin_menu() {
    add_submenu_page( 'edit.php?post_type=upbp_pattern', __( 'Help', 'ultimate-block-patterns-builder' ), __( 'Help', 'ultimate-block-patterns-builder' ), 'manage_options', 'upbp_help', 'ubpb_help_page' );
}

// menu page callback

function ubpb_help_page() {
    ?>
    <div class="wrap">
        <div class="wrap_max_width">
            <div class="help__head">
                <h1><?php _e( 'Ultimate Blocks Pattern Builder', 'ultimate-block-patterns-builder' ); ?></h1>
                <p>
                    <?php _e( 'Ultimate Blocks Pattern Builder is a free plugin that allows you to create your own block patterns and use them in your posts and pages.', 'ultimate-block-patterns-builder' ); ?>
                </p>
            </div>
            <div class="help__body">
                <div class="video_tutorial">
                    <h2><?php _e( 'Video Tutorial', 'ultimate-block-patterns-builder' ); ?></h2>
                    <iframe width="560" height="315" src="https://www.youtube.com/embed/JRz2mfG4XZY" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
                <div class="quick__links">
                    <div class="single_link">
                        <h3><?php _e( 'Hire Me', 'ultimate-block-patterns-builder' ); ?></h3>
                        <p>
                            <?php _e( 'I am available for any Freelance Project', 'ultimate-block-patterns-builder' ); ?>
                        </p>
                        <a href="https://makegutenblock.com/contact" target="_blank"><?php _e( 'Contact Me', 'ultimate-block-patterns-builder' ); ?></a>
                    </div>
                    <div class="single_link">
                        <h3><?php _e( 'Support', 'ultimate-block-patterns-builder' ); ?></h3>
                        <p>
                            <?php _e( 'You can get support from WordPress plugin support forum', 'ultimate-block-patterns-builder' ); ?>
                        </p>
                        <a href="https://wordpress.org/support/plugin/ultimate-block-patterns-builder/" target="_blank"><?php _e( 'Support', 'ultimate-block-patterns-builder' ); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}


// enqueue admin page css
add_action( 'admin_enqueue_scripts', 'ubpb_admin_enqueue_scripts' );

function ubpb_admin_enqueue_scripts($screen) {
    if ( 'upbp_pattern_page_upbp_help' === $screen ) {
        wp_enqueue_style( 'ubpb-admin-style', plugins_url( 'adminpage.css', __FILE__ ) );
    }
}