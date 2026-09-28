<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Settings {

    const OPTION_NAME  = 'ai_support_settings';
    const OPTION_GROUP = 'ai_support_settings_group';
    const MASK_STRING  = '••••••••••••••••';

    /**
     * Default settings array.
     */
    private static function get_defaults() {
        return array(
            'api_key'                => '',
            'model'                  => 'gpt-4o-mini',
            'temperature'            => 0.7,
            'max_tokens'             => 1000,
            'ai_enabled'             => 0,
            'classification_enabled' => 1,
            'reply_enabled'          => 1,
            'logging_enabled'        => 0,
            'enable_frontend_portal' => 1,
            'enable_floating_widget' => 1,
            'widget_position'        => 'bottom-right',
        );
    }

    /**
     * Initialize settings hooks.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Get all settings merged with defaults.
     */
    public static function get_all() {
        $saved    = get_option( self::OPTION_NAME, array() );
        $defaults = self::get_defaults();

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Get a specific setting value.
     */
    public static function get( $key, $default = null ) {
        $all = self::get_all();

        if ( isset( $all[ $key ] ) ) {
            return $all[ $key ];
        }

        return $default;
    }

    /*
     * Reusable accessor methods for AI and Frontend classes.
     */

    public static function get_api_key() {
        return (string) self::get( 'api_key', '' );
    }

    public static function get_model() {
        return (string) self::get( 'model', 'gpt-4o-mini' );
    }

    public static function get_temperature() {
        return (float) self::get( 'temperature', 0.7 );
    }

    public static function get_max_tokens() {
        return (int) self::get( 'max_tokens', 1000 );
    }

    public static function is_ai_enabled() {
        return (bool) self::get( 'ai_enabled', 0 );
    }

    public static function is_classification_enabled() {
        return (bool) self::get( 'classification_enabled', 1 );
    }

    public static function is_reply_enabled() {
        return (bool) self::get( 'reply_enabled', 1 );
    }

    public static function is_logging_enabled() {
        return (bool) self::get( 'logging_enabled', 0 );
    }

    public static function is_frontend_portal_enabled() {
        return (bool) self::get( 'enable_frontend_portal', 1 );
    }

    public static function is_floating_widget_enabled() {
        return (bool) self::get( 'enable_floating_widget', 1 );
    }

    public static function get_widget_position() {
        $pos = (string) self::get( 'widget_position', 'bottom-right' );
        return in_array( $pos, array( 'bottom-right', 'bottom-left' ), true ) ? $pos : 'bottom-right';
    }

    /**
     * Helper to check whether AI is enabled and fully configured.
     *
     * @return array Status array containing 'status' (bool), 'code' (string), and 'message' (string).
     */
    public static function is_ai_configured() {

        if ( ! self::is_ai_enabled() ) {
            return array(
                'status'  => false,
                'code'    => 'ai_disabled',
                'message' => 'AI features are currently disabled in settings.',
            );
        }

        $api_key = self::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'status'  => false,
                'code'    => 'key_missing',
                'message' => 'AI is enabled but the OpenAI API key has not been configured.',
            );
        }

        return array(
            'status'  => true,
            'code'    => 'configured',
            'message' => 'AI is enabled and configured.',
        );
    }

    /**
     * Register settings, sections, and fields using WordPress Settings API.
     */
    public static function register_settings() {

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => self::get_defaults(),
            )
        );

        // Section 1: API Configuration
        add_settings_section(
            'ai_support_section_api',
            'AI API Configuration',
            array( __CLASS__, 'render_api_section_info' ),
            'ai-support-settings'
        );

        add_settings_field(
            'api_key',
            'OpenAI API Key',
            array( __CLASS__, 'render_api_key_field' ),
            'ai-support-settings',
            'ai_support_section_api'
        );

        add_settings_field(
            'model',
            'AI Model',
            array( __CLASS__, 'render_model_field' ),
            'ai-support-settings',
            'ai_support_section_api'
        );

        add_settings_field(
            'temperature',
            'Temperature',
            array( __CLASS__, 'render_temperature_field' ),
            'ai-support-settings',
            'ai_support_section_api'
        );

        add_settings_field(
            'max_tokens',
            'Maximum Output Tokens',
            array( __CLASS__, 'render_max_tokens_field' ),
            'ai-support-settings',
            'ai_support_section_api'
        );

        // Section 2: Features & Toggles
        add_settings_section(
            'ai_support_section_features',
            'AI Feature Toggles',
            array( __CLASS__, 'render_features_section_info' ),
            'ai-support-settings'
        );

        add_settings_field(
            'ai_enabled',
            'Enable AI System',
            array( __CLASS__, 'render_ai_enabled_field' ),
            'ai-support-settings',
            'ai_support_section_features'
        );

        add_settings_field(
            'classification_enabled',
            'Enable AI Classification',
            array( __CLASS__, 'render_classification_field' ),
            'ai-support-settings',
            'ai_support_section_features'
        );

        add_settings_field(
            'reply_enabled',
            'Enable AI Reply Drafts',
            array( __CLASS__, 'render_reply_field' ),
            'ai-support-settings',
            'ai_support_section_features'
        );

        add_settings_field(
            'logging_enabled',
            'Enable AI Logging',
            array( __CLASS__, 'render_logging_field' ),
            'ai-support-settings',
            'ai_support_section_features'
        );

        // Section 3: Customer Frontend Options
        add_settings_section(
            'ai_support_section_frontend',
            'Customer Experience & Frontend',
            array( __CLASS__, 'render_frontend_section_info' ),
            'ai-support-settings'
        );

        add_settings_field(
            'enable_frontend_portal',
            'Enable Customer Portal Shortcode',
            array( __CLASS__, 'render_portal_field' ),
            'ai-support-settings',
            'ai_support_section_frontend'
        );

        add_settings_field(
            'enable_floating_widget',
            'Enable Floating Support Desk Widget',
            array( __CLASS__, 'render_widget_field' ),
            'ai-support-settings',
            'ai_support_section_frontend'
        );

        add_settings_field(
            'widget_position',
            'Floating Widget Position',
            array( __CLASS__, 'render_widget_pos_field' ),
            'ai-support-settings',
            'ai_support_section_frontend'
        );
    }

    /**
     * Sanitize and validate submitted settings.
     */
    public static function sanitize_settings( $input ) {

        $existing = get_option( self::OPTION_NAME, self::get_defaults() );

        if ( ! is_array( $existing ) ) {
            $existing = self::get_defaults();
        }

        $sanitized = array();

        // 1. API Key handling
        $submitted_key = isset( $input['api_key'] ) ? trim( $input['api_key'] ) : '';

        if ( empty( $submitted_key ) || self::MASK_STRING === $submitted_key || '****************' === $submitted_key ) {
            $sanitized['api_key'] = isset( $existing['api_key'] ) ? $existing['api_key'] : '';
        } else {
            $sanitized['api_key'] = sanitize_text_field( $submitted_key );
        }

        // 2. Model
        $sanitized['model'] = ! empty( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini';

        // 3. Temperature (0.0 to 2.0)
        $temp = isset( $input['temperature'] ) ? (float) $input['temperature'] : 0.7;
        $sanitized['temperature'] = max( 0.0, min( 2.0, $temp ) );

        // 4. Max Tokens (1 to 16384)
        $tokens = isset( $input['max_tokens'] ) ? (int) $input['max_tokens'] : 1000;
        $sanitized['max_tokens'] = max( 1, min( 16384, $tokens ) );

        // 5. Checkboxes (0 or 1)
        $sanitized['ai_enabled']             = ! empty( $input['ai_enabled'] ) ? 1 : 0;
        $sanitized['classification_enabled'] = ! empty( $input['classification_enabled'] ) ? 1 : 0;
        $sanitized['reply_enabled']          = ! empty( $input['reply_enabled'] ) ? 1 : 0;
        $sanitized['logging_enabled']        = ! empty( $input['logging_enabled'] ) ? 1 : 0;
        $sanitized['enable_frontend_portal'] = ! empty( $input['enable_frontend_portal'] ) ? 1 : 0;
        $sanitized['enable_floating_widget'] = ! empty( $input['enable_floating_widget'] ) ? 1 : 0;

        // 6. Widget position
        $pos = isset( $input['widget_position'] ) ? sanitize_key( $input['widget_position'] ) : 'bottom-right';
        $sanitized['widget_position'] = in_array( $pos, array( 'bottom-right', 'bottom-left' ), true ) ? $pos : 'bottom-right';

        return $sanitized;
    }

    /*
     * Section callbacks
     */
    public static function render_api_section_info() {
        echo '<p>Configure your OpenAI API parameters and authentication details. The API key is stored securely in WordPress options and used strictly for backend PHP API calls.</p>';
    }

    public static function render_features_section_info() {
        echo '<p>Enable or disable specific AI capabilities across the support desk.</p>';
    }

    public static function render_frontend_section_info() {
        echo '<p>Control how customers interact with your support desk on the website frontend.</p>';
    }

    /*
     * Field Render Callbacks
     */
    public static function render_api_key_field() {

        $key     = self::get_api_key();
        $has_key = ! empty( $key );
        $val     = $has_key ? self::MASK_STRING : '';

        printf(
            '<input type="password" name="%s[api_key]" value="%s" class="regular-text" placeholder="%s" autocomplete="off" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $val ),
            $has_key ? 'Enter new API key to update...' : 'sk-...'
        );

        if ( $has_key ) {
            echo '<p class="description" style="color:#008a20; font-weight:500;">✓ An OpenAI API key is currently saved. Leave this field unchanged (dots) to keep the existing key, or type a new key to update.</p>';
        } else {
            echo '<p class="description">Enter your OpenAI secret API key (starts with <code>sk-</code>). This key is used strictly server-side by PHP and is never exposed in frontend code.</p>';
        }
    }

    public static function render_model_field() {

        $current = self::get_model();

        $models = array(
            'gpt-4o-mini'   => 'gpt-4o-mini (Recommended - Fast & Cost Efficient)',
            'gpt-4o'        => 'gpt-4o (High Intelligence & Multimodal)',
            'gpt-4-turbo'   => 'gpt-4-turbo (Legacy High Intelligence)',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo (Legacy Model)',
        );

        echo sprintf( '<select name="%s[model]" id="ai_model_select" class="regular-text">', esc_attr( self::OPTION_NAME ) );

        foreach ( $models as $val => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $val ),
                selected( $current, $val, false ),
                esc_html( $label )
            );
        }

        if ( ! isset( $models[ $current ] ) && ! empty( $current ) ) {
            printf(
                '<option value="%s" selected>%s (Custom)</option>',
                esc_attr( $current ),
                esc_html( $current )
            );
        }

        echo '</select>';
        echo '<p class="description">Select the OpenAI model to use for classification and response generation.</p>';
    }

    public static function render_temperature_field() {

        $temp = self::get_temperature();

        printf(
            '<input type="number" name="%s[temperature]" value="%s" step="0.01" min="0.0" max="2.0" class="small-text" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( number_format( $temp, 2, '.', '' ) )
        );

        echo '<p class="description">Controls randomness. Lower values (e.g. 0.2) are more deterministic; higher values (e.g. 0.8) are more creative. Range: 0.0 - 2.0. Default: 0.7.</p>';
    }

    public static function render_max_tokens_field() {

        $tokens = self::get_max_tokens();

        printf(
            '<input type="number" name="%s[max_tokens]" value="%s" step="1" min="1" max="16384" class="small-text" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $tokens )
        );

        echo '<p class="description">Maximum number of tokens to generate in AI responses. Default: 1000.</p>';
    }

    public static function render_ai_enabled_field() {

        $enabled = self::is_ai_enabled();

        printf(
            '<label><input type="checkbox" name="%s[ai_enabled]" value="1" %s /> Enable AI Support Assistant system</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Master toggle for AI features. If disabled, normal ticketing functions continue working without AI.</p>';
    }

    public static function render_classification_field() {

        $enabled = self::is_classification_enabled();

        printf(
            '<label><input type="checkbox" name="%s[classification_enabled]" value="1" %s /> Enable AI Ticket Classification</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Allows AI to analyze ticket sentiment, category, priority, and department recommendations.</p>';
    }

    public static function render_reply_field() {

        $enabled = self::is_reply_enabled();

        printf(
            '<label><input type="checkbox" name="%s[reply_enabled]" value="1" %s /> Enable AI Reply Draft Generation</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Allows AI to generate draft replies for support agents to review and send.</p>';
    }

    public static function render_logging_field() {

        $enabled = self::is_logging_enabled();

        printf(
            '<label><input type="checkbox" name="%s[logging_enabled]" value="1" %s /> Enable AI Request Logging</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Logs metadata for AI API requests (timestamps, model, duration, token usage) for diagnostic purposes.</p>';
    }

    public static function render_portal_field() {

        $enabled = self::is_frontend_portal_enabled();

        printf(
            '<label><input type="checkbox" name="%s[enable_frontend_portal]" value="1" %s /> Enable Customer Support Portal shortcode <code>[ai_support_tickets]</code></label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Allows embedding customer ticket management onto any page or post.</p>';
    }

    public static function render_widget_field() {

        $enabled = self::is_floating_widget_enabled();

        printf(
            '<label><input type="checkbox" name="%s[enable_floating_widget]" value="1" %s /> Enable Floating Support Desk Widget on website footer</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $enabled, true, false )
        );

        echo '<p class="description">Displays a interactive floating support button on the bottom corner of your site.</p>';
    }

    public static function render_widget_pos_field() {

        $current = self::get_widget_position();

        printf(
            '<select name="%s[widget_position]"><option value="bottom-right" %s>Bottom Right</option><option value="bottom-left" %s>Bottom Left</option></select>',
            esc_attr( self::OPTION_NAME ),
            selected( $current, 'bottom-right', false ),
            selected( $current, 'bottom-left', false )
        );

        echo '<p class="description">Choose where the floating support widget button should appear.</p>';
    }
}
