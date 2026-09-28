<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_AI {

    /**
     * OpenAI Chat Completions API endpoint.
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Maximum character count allowed in total prompt messages as a cost safeguard.
     */
    const MAX_INPUT_CHARS = 40000;

    /**
     * Send a structured request to the OpenAI Chat Completions API.
     *
     * @param array $messages Array of message arrays, e.g. [['role' => 'system', 'content' => '...'], ['role' => 'user', 'content' => '...']]
     * @param array $options Optional override parameters (model, temperature, max_tokens, action_label)
     * @return array Normalized response array: ['success' => bool, 'content' => string, 'usage' => array, 'error' => array|null]
     */
    public static function generate( array $messages, array $options = array() ) {

        // 1. Verify Configuration & Credentials
        $config_check = AI_Support_Assistant_Settings::is_ai_configured();
        if ( ! $config_check['status'] ) {
            return self::format_error( $config_check['code'], $config_check['message'] );
        }

        // 2. Validate Messages
        if ( empty( $messages ) || ! is_array( $messages ) ) {
            return self::format_error( 'invalid_input', 'Request messages array cannot be empty.' );
        }

        $total_chars = 0;
        foreach ( $messages as $msg ) {
            if ( ! isset( $msg['role'], $msg['content'] ) ) {
                return self::format_error( 'invalid_input', 'Each message must contain a role and content.' );
            }
            $total_chars += strlen( (string) $msg['content'] );
        }

        if ( $total_chars > self::MAX_INPUT_CHARS ) {
            return self::format_error( 'input_too_large', sprintf( 'Input payload exceeds maximum allowed limit of %d characters.', self::MAX_INPUT_CHARS ) );
        }

        // 3. Extract & Merge Options with Settings Defaults
        $api_key     = AI_Support_Assistant_Settings::get_api_key();
        $model       = ! empty( $options['model'] ) ? sanitize_text_field( $options['model'] ) : AI_Support_Assistant_Settings::get_model();
        $temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : AI_Support_Assistant_Settings::get_temperature();
        $max_tokens  = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : AI_Support_Assistant_Settings::get_max_tokens();
        $action      = ! empty( $options['action_label'] ) ? sanitize_text_field( $options['action_label'] ) : 'ai_request';

        $payload = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => max( 0.0, min( 2.0, $temperature ) ),
            'max_tokens'  => max( 1, min( 16384, $max_tokens ) ),
        );

        $request_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . trim( $api_key ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        );

        // 4. Perform HTTP Request & Time Execution
        $start_time = microtime( true );
        $response   = wp_remote_post( self::API_ENDPOINT, $request_args );
        $duration   = microtime( true ) - $start_time;

        // 5. Handle Network/Transport Errors
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            self::maybe_log( $action, $model, 0, $duration, array(), array( 'code' => 'network_error', 'message' => $error_message ) );
            return self::format_error( 'network_error', 'Unable to connect to AI API: ' . $error_message );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );

        // 6. Handle HTTP Errors (4xx, 5xx)
        if ( $http_code < 200 || $http_code >= 300 ) {
            $json_err = json_decode( $raw_body, true );
            $api_err  = isset( $json_err['error']['message'] ) ? sanitize_text_field( $json_err['error']['message'] ) : 'HTTP ' . $http_code . ' error returned.';

            self::maybe_log( $action, $model, $http_code, $duration, array(), array( 'code' => 'http_' . $http_code, 'message' => $api_err ) );
            return self::format_error( 'api_error_' . $http_code, 'AI API Error (' . $http_code . '): ' . $api_err );
        }

        // 7. Parse & Normalize Response
        $data = json_decode( $raw_body, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
            self::maybe_log( $action, $model, $http_code, $duration, array(), array( 'code' => 'json_parse_error', 'message' => 'Invalid JSON' ) );
            return self::format_error( 'json_parse_error', 'Failed to parse AI API JSON response.' );
        }

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            self::maybe_log( $action, $model, $http_code, $duration, array(), array( 'code' => 'empty_response', 'message' => 'No content returned' ) );
            return self::format_error( 'empty_response', 'AI API returned an empty completion choice.' );
        }

        $content = trim( (string) $data['choices'][0]['message']['content'] );

        $usage = array(
            'prompt_tokens'     => isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : 0,
            'total_tokens'      => isset( $data['usage']['total_tokens'] ) ? (int) $data['usage']['total_tokens'] : 0,
        );

        // 8. Log Success Metadata if enabled
        self::maybe_log( $action, $model, $http_code, $duration, $usage );

        return array(
            'success' => true,
            'content' => $content,
            'usage'   => $usage,
            'raw'     => null, // Keep raw secret-free
            'error'   => null,
        );
    }

    /**
     * Test API connection with a lightweight 1-token request.
     *
     * @return array Result array ['success' => bool, 'message' => string]
     */
    public static function test_connection() {

        $config_check = AI_Support_Assistant_Settings::is_ai_configured();
        if ( ! $config_check['status'] ) {
            return array(
                'success' => false,
                'message' => $config_check['message'],
            );
        }

        $test_messages = array(
            array(
                'role'    => 'user',
                'content' => 'Respond with the word: OK',
            ),
        );

        $result = self::generate(
            $test_messages,
            array(
                'max_tokens'   => 5,
                'action_label' => 'test_connection',
            )
        );

        if ( ! $result['success'] ) {
            return array(
                'success' => false,
                'message' => $result['error']['message'],
            );
        }

        $model = AI_Support_Assistant_Settings::get_model();

        return array(
            'success' => true,
            'message' => sprintf( 'AI connection successful! Model "%s" responded cleanly. (%d tokens used)', $model, $result['usage']['total_tokens'] ),
        );
    }

    /**
     * Format a standardized error response.
     */
    private static function format_error( $code, $message ) {
        return array(
            'success' => false,
            'content' => '',
            'usage'   => array(
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
            ),
            'raw'     => null,
            'error'   => array(
                'code'    => sanitize_key( $code ),
                'message' => sanitize_text_field( $message ),
            ),
        );
    }

    /**
     * Log request metadata if logging is enabled in settings.
     * Does NOT log API keys, headers, or full prompt contents.
     */
    private static function maybe_log( $action, $model, $http_code, $duration, array $usage = array(), $error = null ) {

        if ( ! AI_Support_Assistant_Settings::is_logging_enabled() ) {
            return;
        }

        $logs = get_option( 'ai_support_logs', array() );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }

        $entry = array(
            'timestamp'   => current_time( 'mysql' ),
            'action'      => sanitize_text_field( $action ),
            'model'       => sanitize_text_field( $model ),
            'http_code'   => (int) $http_code,
            'duration_ms' => (int) round( $duration * 1000 ),
            'usage'       => $usage,
            'success'     => empty( $error ),
            'error_code'  => $error ? sanitize_key( $error['code'] ) : null,
        );

        // Keep last 100 log entries
        array_unshift( $logs, $entry );
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, 0, 100 );
        }

        update_option( 'ai_support_logs', $logs, false );
    }

    /**
     * Get logged AI request entries.
     */
    public static function get_logs() {
        $logs = get_option( 'ai_support_logs', array() );
        return is_array( $logs ) ? $logs : array();
    }
}
