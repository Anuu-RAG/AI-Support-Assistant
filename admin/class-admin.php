<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Admin {

    public function __construct() {

        add_action(
            'admin_menu',
            array( $this, 'register_menu' )
        );

        add_action(
            'admin_init',
            array( $this, 'handle_actions' )
        );

        add_action(
            'admin_notices',
            array( $this, 'render_notices' )
        );
    }

    /**
     * Register admin menu and submenus.
     */
    public function register_menu() {

        add_menu_page(
            'AI Support Assistant',
            'AI Support',
            'manage_options',
            'ai-support',
            array( $this, 'tickets_page' ),
            'dashicons-format-chat',
            25
        );

        add_submenu_page(
            'ai-support',
            'Tickets',
            'Tickets',
            'manage_options',
            'ai-support',
            array( $this, 'tickets_page' )
        );

        add_submenu_page(
            'ai-support',
            'Agents & Departments',
            'Agents',
            'manage_options',
            'ai-support-agents',
            array( $this, 'agents_page' )
        );

        add_submenu_page(
            'ai-support',
            'AI Support Settings',
            'Settings',
            'manage_options',
            'ai-support-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Dispatch and handle POST admin actions.
     */
    public function handle_actions() {

        if ( ! is_admin() || empty( $_POST['ai_support_action'] ) ) {
            return;
        }

        $action = sanitize_key( $_POST['ai_support_action'] );

        switch ( $action ) {

            case 'create_ticket':
                $this->handle_create_ticket();
                break;

            case 'reply_ticket':
                $this->handle_reply_ticket();
                break;

            case 'update_ticket':
                $this->handle_update_ticket();
                break;

            case 'add_department':
                $this->handle_add_department();
                break;

            case 'add_agent':
                $this->handle_add_agent();
                break;

            case 'update_agent':
                $this->handle_update_agent();
                break;

            case 'test_ai_connection':
                $this->handle_test_ai_connection();
                break;

            case 'classify_ticket':
                $this->handle_classify_ticket();
                break;

            case 'generate_ai_reply':
                $this->handle_generate_reply();
                break;

            case 'clear_ai_draft':
                $this->handle_clear_draft();
                break;
        }
    }

    private function handle_create_ticket() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_create_ticket'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to create tickets.' );
        }

        $ticket_id = AI_Support_Assistant_Tickets::create(
            array(
                'customer_id'    => isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : null,
                'subject'        => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
                'priority'       => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'medium',
                'category'       => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
                'department_id'  => isset( $_POST['department_id'] ) ? absint( $_POST['department_id'] ) : null,
                'assigned_agent' => isset( $_POST['assigned_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['assigned_agent'] ) ) : '',
                'auto_assign'    => ! empty( $_POST['auto_assign'] ),
                'message'        => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
            )
        );

        if ( ! $ticket_id ) {
            wp_die( 'Unable to create the ticket.' );
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=ticket_created' )
        );
        exit;
    }

    private function handle_reply_ticket() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_reply_ticket'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to reply to tickets.' );
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
        $reply     = isset( $_POST['reply_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply_message'] ) ) : '';

        if ( ! $ticket_id || empty( $reply ) ) {
            wp_die( 'Invalid reply content.' );
        }

        $success = AI_Support_Assistant_Tickets::add_message(
            $ticket_id,
            get_current_user_id(),
            'agent',
            $reply
        );

        if ( ! $success ) {
            wp_die( 'Unable to save the reply.' );
        }

        // Clear transient AI draft if it existed
        AI_Support_Assistant_AI_Reply::delete_draft( $ticket_id );

        if ( ! empty( $_POST['reply_status'] ) ) {
            AI_Support_Assistant_Tickets::update_status( $ticket_id, sanitize_text_field( $_POST['reply_status'] ) );
        }

        // Send email notification to customer via wp_mail()
        $ticket = AI_Support_Assistant_Tickets::get( $ticket_id );
        if ( $ticket && ! empty( $ticket->customer_email ) ) {
            $email_subject = sprintf( '[Ticket #%d Reply] %s', $ticket->id, $ticket->subject );
            $portal_url    = add_query_arg(
                array(
                    'ticket_action' => 'view',
                    'ticket_id'     => $ticket->id,
                    'access_email'  => $ticket->customer_email,
                ),
                home_url( '/' )
            );

            $email_body = sprintf(
                "Hello %s,\n\nOur support team has posted a reply to your ticket #%d (%s):\n\n----------------------------------------\n%s\n----------------------------------------\n\nYou can view the full conversation thread and respond online here:\n%s\n\nBest regards,\nCustomer Support Team",
                $ticket->customer_name ? $ticket->customer_name : 'Customer',
                $ticket->id,
                $ticket->subject,
                $reply,
                $portal_url
            );

            wp_mail( $ticket->customer_email, $email_subject, $email_body );
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=reply_added' )
        );
        exit;
    }

    private function handle_update_ticket() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_update_ticket'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to update tickets.' );
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

        if ( ! $ticket_id ) {
            wp_die( 'Invalid ticket ID.' );
        }

        $notice = 'ticket_updated';

        if ( ! empty( $_POST['auto_assign_trigger'] ) ) {
            $auto_res = AI_Support_Assistant_Tickets::auto_assign( $ticket_id );
            if ( ! $auto_res['success'] ) {
                $notice = 'no_agent';
            } else {
                $notice = 'agent_assigned';
            }
        } else {
            $data = array();

            if ( isset( $_POST['status'] ) ) {
                $data['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
            }

            if ( isset( $_POST['priority'] ) ) {
                $data['priority'] = sanitize_text_field( wp_unslash( $_POST['priority'] ) );
            }

            if ( isset( $_POST['category'] ) ) {
                $data['category'] = sanitize_text_field( wp_unslash( $_POST['category'] ) );
            }

            if ( array_key_exists( 'department_id', $_POST ) ) {
                $data['department_id'] = absint( $_POST['department_id'] );
            }

            if ( array_key_exists( 'assigned_agent', $_POST ) ) {
                $data['assigned_agent'] = sanitize_text_field( wp_unslash( $_POST['assigned_agent'] ) );
            }

            AI_Support_Assistant_Tickets::update_ticket( $ticket_id, $data );
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=' . $notice )
        );
        exit;
    }

    private function handle_add_department() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_add_department'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to manage departments.' );
        }

        $name = isset( $_POST['department_name'] ) ? sanitize_text_field( wp_unslash( $_POST['department_name'] ) ) : '';

        $success = AI_Support_Assistant_Agents::create_department( $name );
        $notice  = $success ? 'dept_created' : 'dept_error';

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support-agents&ai_notice=' . $notice )
        );
        exit;
    }

    private function handle_add_agent() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_add_agent'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to manage agents.' );
        }

        $user_id       = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $department_id = isset( $_POST['department_id'] ) ? absint( $_POST['department_id'] ) : null;

        $success = AI_Support_Assistant_Agents::add_agent( $user_id, $department_id );
        $notice  = $success ? 'agent_added' : 'agent_error';

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support-agents&ai_notice=' . $notice )
        );
        exit;
    }

    private function handle_update_agent() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_update_agent'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to update agents.' );
        }

        $agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;

        if ( ! $agent_id ) {
            wp_die( 'Invalid agent.' );
        }

        if ( ! empty( $_POST['sync_workload'] ) ) {
            AI_Support_Assistant_Agents::sync_active_tickets( $agent_id );
            wp_safe_redirect(
                admin_url( 'admin.php?page=ai-support-agents&ai_notice=agent_updated' )
            );
            exit;
        }

        $is_available  = isset( $_POST['is_available'] ) ? (int) $_POST['is_available'] : 0;
        $department_id = isset( $_POST['department_id'] ) ? absint( $_POST['department_id'] ) : null;

        AI_Support_Assistant_Agents::update_agent(
            $agent_id,
            array(
                'is_available'  => $is_available,
                'department_id' => $department_id,
            )
        );

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support-agents&ai_notice=agent_updated' )
        );
        exit;
    }

    private function handle_test_ai_connection() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_test_connection'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to test AI connection.' );
        }

        $result = AI_Support_Assistant_AI::test_connection();

        set_transient( 'ai_support_test_result', $result, 60 );

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support-settings&ai_notice=test_result' )
        );
        exit;
    }

    private function handle_classify_ticket() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_classify_ticket'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to classify tickets.' );
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

        if ( ! $ticket_id ) {
            wp_die( 'Invalid ticket ID.' );
        }

        $result = AI_Support_Assistant_AI_Classifier::classify( $ticket_id );

        set_transient( 'ai_support_classify_result_' . $ticket_id, $result, 60 );

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=classify_result' )
        );
        exit;
    }

    private function handle_generate_reply() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_generate_reply'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to generate AI replies.' );
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

        if ( ! $ticket_id ) {
            wp_die( 'Invalid ticket ID.' );
        }

        $result = AI_Support_Assistant_AI_Reply::generate( $ticket_id );

        set_transient( 'ai_support_reply_result_' . $ticket_id, $result, 60 );

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=reply_result' )
        );
        exit;
    }

    private function handle_clear_draft() {

        if (
            ! isset( $_POST['_wpnonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
                'ai_support_clear_draft'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to manage drafts.' );
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

        if ( $ticket_id ) {
            AI_Support_Assistant_AI_Reply::delete_draft( $ticket_id );
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=ai-support&action=view&ticket_id=' . $ticket_id . '&ai_notice=draft_cleared' )
        );
        exit;
    }

    /**
     * Render feedback notices.
     */
    public function render_notices() {

        if ( empty( $_GET['ai_notice'] ) ) {
            return;
        }

        $notice = sanitize_key( $_GET['ai_notice'] );

        switch ( $notice ) {
            case 'reply_result':
                $ticket_id = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;
                $res       = get_transient( 'ai_support_reply_result_' . $ticket_id );
                delete_transient( 'ai_support_reply_result_' . $ticket_id );
                if ( $res && is_array( $res ) ) {
                    if ( ! empty( $res['success'] ) ) {
                        echo '<div class="notice notice-success is-dismissible"><p>✓ AI reply draft generated successfully! Review the draft below before sending.</p></div>';
                    } else {
                        printf(
                            '<div class="notice notice-error is-dismissible"><p>Unable to generate AI reply draft: %s</p></div>',
                            esc_html( $res['error']['message'] )
                        );
                    }
                }
                break;
            case 'draft_cleared':
                echo '<div class="notice notice-info is-dismissible"><p>AI draft cleared.</p></div>';
                break;
            case 'classify_result':
                $ticket_id = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;
                $res       = get_transient( 'ai_support_classify_result_' . $ticket_id );
                delete_transient( 'ai_support_classify_result_' . $ticket_id );
                if ( $res && is_array( $res ) ) {
                    $class = ! empty( $res['success'] ) ? 'notice-success' : 'notice-error';
                    printf(
                        '<div class="notice %s is-dismissible"><p>%s</p></div>',
                        esc_attr( $class ),
                        esc_html( $res['message'] )
                    );
                }
                break;
            case 'test_result':
                $res = get_transient( 'ai_support_test_result' );
                delete_transient( 'ai_support_test_result' );
                if ( $res && is_array( $res ) ) {
                    $class = ! empty( $res['success'] ) ? 'notice-success' : 'notice-error';
                    printf(
                        '<div class="notice %s is-dismissible"><p>%s</p></div>',
                        esc_attr( $class ),
                        esc_html( $res['message'] )
                    );
                }
                break;
            case 'ticket_created':
                echo '<div class="notice notice-success is-dismissible"><p>Ticket created successfully.</p></div>';
                break;
            case 'reply_added':
                echo '<div class="notice notice-success is-dismissible"><p>Reply posted successfully.</p></div>';
                break;
            case 'ticket_updated':
                echo '<div class="notice notice-success is-dismissible"><p>Ticket updated successfully.</p></div>';
                break;
            case 'no_agent':
                echo '<div class="notice notice-warning is-dismissible"><p>No available agent found in the selected department. The ticket remains unassigned.</p></div>';
                break;
            case 'agent_assigned':
                echo '<div class="notice notice-success is-dismissible"><p>Available agent automatically assigned successfully.</p></div>';
                break;
            case 'dept_created':
                echo '<div class="notice notice-success is-dismissible"><p>Department created successfully.</p></div>';
                break;
            case 'dept_error':
                echo '<div class="notice notice-error is-dismissible"><p>Unable to create department (name required or slug already exists).</p></div>';
                break;
            case 'agent_added':
                echo '<div class="notice notice-success is-dismissible"><p>Support agent added successfully.</p></div>';
                break;
            case 'agent_error':
                echo '<div class="notice notice-error is-dismissible"><p>Unable to add agent (user may already be assigned as an agent).</p></div>';
                break;
            case 'agent_updated':
                echo '<div class="notice notice-success is-dismissible"><p>Support agent updated successfully.</p></div>';
                break;
        }
    }

    /**
     * Render tickets page.
     */
    public function tickets_page() {

        $action = isset( $_GET['action'] )
            ? sanitize_key( $_GET['action'] )
            : '';

        if ( 'new' === $action ) {
            require AI_SUPPORT_ASSISTANT_PATH . 'admin/views/ticket-new.php';
            return;
        }

        if ( 'view' === $action ) {
            require AI_SUPPORT_ASSISTANT_PATH . 'admin/views/ticket-single.php';
            return;
        }

        require AI_SUPPORT_ASSISTANT_PATH . 'admin/views/tickets.php';
    }

    /**
     * Render agents page.
     */
    public function agents_page() {
        require AI_SUPPORT_ASSISTANT_PATH . 'admin/views/agents.php';
    }

    /**
     * Render settings page.
     */
    public function settings_page() {
        require AI_SUPPORT_ASSISTANT_PATH . 'admin/views/settings.php';
    }
}