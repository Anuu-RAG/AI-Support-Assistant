<?php
/**
 * Plugin Name: AI Support Assistant
 * Plugin URI: https://example.com
 * Description: AI-powered customer support assistant for WordPress and WooCommerce.
 * Version: 0.1.0
 * Author: Anurag Paul
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'opcache_reset' ) ) {
    @opcache_reset();
}

add_action( 'init', function() {
    if ( function_exists( 'opcache_reset' ) ) {
        @opcache_reset();
    }
} );

define( 'AI_SUPPORT_ASSISTANT_VERSION', '0.1.0' );
define( 'AI_SUPPORT_ASSISTANT_FILE', __FILE__ );
define( 'AI_SUPPORT_ASSISTANT_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_SUPPORT_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );

require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-database.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-settings.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-ai.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-ai-classifier.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-ai-reply.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-tickets.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-agents.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'includes/class-frontend.php';
require_once AI_SUPPORT_ASSISTANT_PATH . 'admin/class-admin.php';

register_activation_hook(
    AI_SUPPORT_ASSISTANT_FILE,
    array( 'AI_Support_Assistant_Database', 'create_tables' )
);

add_action( 'plugins_loaded', array( 'AI_Support_Assistant_Database', 'check_db_update' ) );

AI_Support_Assistant_Settings::init();
AI_Support_Assistant_Frontend::init();
new AI_Support_Assistant_Admin();