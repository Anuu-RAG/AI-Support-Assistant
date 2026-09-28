<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_AI_Classifier {

    /**
     * Allowed classification categories.
     */
    public static function get_allowed_categories() {
        return array(
            'billing',
            'shipping',
            'technical',
            'refund',
            'product',
            'account',
            'other',
        );
    }

    /**
     * Allowed priorities.
     */
    public static function get_allowed_priorities() {
        return array(
            'low',
            'medium',
            'high',
            'urgent',
        );
    }

    /**
     * Allowed sentiments.
     */
    public static function get_allowed_sentiments() {
        return array(
            'positive',
            'neutral',
            'negative',
        );
    }

    /**
     * Classify a support ticket using OpenAI API and update ticket attributes.
     *
     * @param int $ticket_id
     * @return array Standardized result array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public static function classify( $ticket_id ) {

        $ticket_id = absint( $ticket_id );
        $ticket    = AI_Support_Assistant_Tickets::get( $ticket_id );

        if ( ! $ticket ) {
            return array(
                'success' => false,
                'message' => 'Ticket not found.',
            );
        }

        $messages_list = AI_Support_Assistant_Tickets::get_messages( $ticket_id );

        if ( empty( $messages_list ) ) {
            return array(
                'success' => false,
                'message' => 'Ticket has no conversation messages to classify.',
            );
        }

        // 1. Build Conversation Prompt History
        $formatted_messages = array();
        foreach ( $messages_list as $msg ) {
            $sender = 'customer' === $msg->sender_type ? 'Customer' : 'Agent';
            $formatted_messages[] = sprintf( '%s: %s', $sender, $msg->message );
        }

        if ( count( $formatted_messages ) > 10 ) {
            $formatted_messages = array_slice( $formatted_messages, -10 );
        }

        $conversation_text = implode( "\n\n", $formatted_messages );

        // 2. Fetch Available Departments for AI Guidance
        $existing_departments = AI_Support_Assistant_Agents::get_departments();
        $dept_names = wp_list_pluck( $existing_departments, 'name' );
        $dept_list_str = implode( ', ', $dept_names );

        // 3. Construct System & User Prompts
        $system_prompt = "You are a customer support ticket classifier for an e-commerce support system.\n"
            . "Analyze the ticket subject and conversation, then return ONLY a JSON object with the following fields:\n"
            . "- category: Exactly one of: billing, shipping, technical, refund, product, account, other\n"
            . "- priority: Exactly one of: low, medium, high, urgent\n"
            . "- department: Exactly one of the available departments: " . $dept_list_str . "\n"
            . "- sentiment: Exactly one of: positive, neutral, negative\n"
            . "- needs_human: Boolean (true or false)\n\n"
            . "CRITICAL RULES:\n"
            . "1. Output MUST be valid JSON only. Do not include markdown codeblocks or extra text.\n"
            . "2. Do NOT write a response to the customer.\n"
            . "3. Do NOT include user IDs, agent IDs, or user assignments in your JSON.";

        $user_prompt = sprintf(
            "Subject: %s\n\nConversation:\n%s",
            $ticket->subject,
            $conversation_text
        );

        $ai_messages = array(
            array(
                'role'    => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role'    => 'user',
                'content' => $user_prompt,
            ),
        );

        // 4. Request AI Completion via AI Service Layer
        $ai_result = AI_Support_Assistant_AI::generate(
            $ai_messages,
            array(
                'action_label' => 'ticket_classification',
                'temperature'  => 0.2,
                'max_tokens'   => 250,
            )
        );

        if ( ! $ai_result['success'] ) {
            return array(
                'success' => false,
                'message' => 'AI Classification failed: ' . $ai_result['error']['message'],
            );
        }

        // 5. Parse & Clean JSON Response
        $raw_content = trim( $ai_result['content'] );

        if ( strpos( $raw_content, '```' ) !== false ) {
            $raw_content = preg_replace( '/^```(?:json)?\s*/i', '', $raw_content );
            $raw_content = preg_replace( '/\s*```$/', '', $raw_content );
            $raw_content = trim( $raw_content );
        }

        $decoded = json_decode( $raw_content, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return array(
                'success' => false,
                'message' => 'AI returned invalid JSON formatting.',
            );
        }

        // 6. Strict Server-Side Validation
        $val_category = isset( $decoded['category'] ) ? strtolower( trim( $decoded['category'] ) ) : 'other';

        if ( ! in_array( $val_category, self::get_allowed_categories(), true ) ) {
            $val_category = 'other';
        }

        $val_priority = isset( $decoded['priority'] ) ? strtolower( trim( $decoded['priority'] ) ) : 'medium';

        if ( ! in_array( $val_priority, self::get_allowed_priorities(), true ) ) {
            $val_priority = 'medium';
        }

        $val_sentiment = isset( $decoded['sentiment'] ) ? strtolower( trim( $decoded['sentiment'] ) ) : 'neutral';

        if ( ! in_array( $val_sentiment, self::get_allowed_sentiments(), true ) ) {
            $val_sentiment = 'neutral';
        }

        $val_needs_human = isset( $decoded['needs_human'] ) ? (bool) $decoded['needs_human'] : true;

        // Department Mapping against database
        $val_dept_name     = isset( $decoded['department'] ) ? trim( $decoded['department'] ) : '';
        $matched_dept_id   = null;
        $matched_dept_name = 'General';

        if ( ! empty( $val_dept_name ) && ! empty( $existing_departments ) ) {
            foreach ( $existing_departments as $dept ) {
                if ( 0 === strcasecmp( $dept->name, $val_dept_name ) || 0 === strcasecmp( $dept->slug, sanitize_title( $val_dept_name ) ) ) {
                    $matched_dept_id   = (int) $dept->id;
                    $matched_dept_name = $dept->name;
                    break;
                }
            }
        }

        if ( null === $matched_dept_id && ! empty( $existing_departments ) ) {
            foreach ( $existing_departments as $dept ) {
                if ( 0 === strcasecmp( $dept->name, 'General' ) || 0 === strcasecmp( $dept->slug, 'general' ) ) {
                    $matched_dept_id   = (int) $dept->id;
                    $matched_dept_name = $dept->name;
                    break;
                }
            }

            if ( null === $matched_dept_id ) {
                $matched_dept_id   = (int) $existing_departments[0]->id;
                $matched_dept_name = $existing_departments[0]->name;
            }
        }

        $classification_data = array(
            'category'        => $val_category,
            'priority'        => $val_priority,
            'department_id'   => $matched_dept_id,
            'department_name' => $matched_dept_name,
            'sentiment'       => $val_sentiment,
            'needs_human'     => $val_needs_human,
            'analyzed_at'     => current_time( 'mysql' ),
        );

        // 7. Save Classification Results
        update_option( 'ai_support_classification_' . $ticket_id, $classification_data, false );

        // 8. Update Ticket Attributes (Category, Priority, Department)
        AI_Support_Assistant_Tickets::update_ticket(
            $ticket_id,
            array(
                'category'      => $val_category,
                'priority'      => $val_priority,
                'department_id' => $matched_dept_id,
            )
        );

        // 9. Deterministic Agent Auto-Assignment
        $assignment_notice = '';

        if ( $matched_dept_id ) {
            $auto_assign_res = AI_Support_Assistant_Tickets::auto_assign( $ticket_id );
            if ( $auto_assign_res['success'] ) {
                $assignment_notice = sprintf( ' Assigned to %s.', $auto_assign_res['agent']->display_name );
            } else {
                $assignment_notice = ' Ticket remains unassigned (no available agent).';
            }
        }

        return array(
            'success' => true,
            'message' => sprintf( 'AI Classification complete! Category: %s, Priority: %s, Department: %s.%s', ucfirst( $val_category ), ucfirst( $val_priority ), $matched_dept_name, $assignment_notice ),
            'data'    => $classification_data,
        );
    }

    /**
     * Get stored classification result for a ticket if available.
     *
     * @param int $ticket_id
     * @return array|null
     */
    public static function get_stored_classification( $ticket_id ) {
        $data = get_option( 'ai_support_classification_' . absint( $ticket_id ) );
        return is_array( $data ) ? $data : null;
    }
}
