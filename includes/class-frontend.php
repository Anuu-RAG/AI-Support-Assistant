<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Frontend {

    /**
     * Initialize frontend hooks, shortcodes, and AJAX actions.
     */
    public static function init() {

        add_action( 'init', array( __CLASS__, 'get_or_create_support_desk_page_url' ) );

        add_shortcode( 'ai_support_tickets', array( __CLASS__, 'render_portal_shortcode' ) );

        add_action( 'wp_footer', array( __CLASS__, 'render_floating_widget' ) );
        add_action( 'wp_head', array( __CLASS__, 'output_head_styles' ) );

        // AJAX handlers for Frontend Portal and Floating Widget
        add_action( 'wp_ajax_ai_support_frontend_create_ticket', array( __CLASS__, 'handle_ajax_create_ticket' ) );
        add_action( 'wp_ajax_nopriv_ai_support_frontend_create_ticket', array( __CLASS__, 'handle_ajax_create_ticket' ) );

        add_action( 'wp_ajax_ai_support_frontend_customer_reply', array( __CLASS__, 'handle_ajax_customer_reply' ) );
        add_action( 'wp_ajax_nopriv_ai_support_frontend_customer_reply', array( __CLASS__, 'handle_ajax_customer_reply' ) );

        add_action( 'wp_ajax_ai_support_frontend_poll_messages', array( __CLASS__, 'handle_ajax_poll_messages' ) );
        add_action( 'wp_ajax_nopriv_ai_support_frontend_poll_messages', array( __CLASS__, 'handle_ajax_poll_messages' ) );
    }

    /**
     * Get or automatically create the dedicated Support Desk page containing shortcode [ai_support_tickets]
     */
    public static function get_or_create_support_desk_page_url() {

        $page_id = get_option( 'ai_support_desk_page_id' );
        if ( $page_id && get_post( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
            return get_permalink( $page_id );
        }

        global $wpdb;
        $found_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
              AND post_status = 'publish' 
              AND (post_content LIKE '%[ai_support_tickets]%' OR post_name = 'support-desk' OR post_title = 'Support Desk') 
            ORDER BY ID ASC LIMIT 1"
        );

        if ( $found_id ) {
            update_option( 'ai_support_desk_page_id', $found_id );
            return get_permalink( $found_id );
        }

        // Programmatically create page with shortcode
        $new_page_id = wp_insert_post(
            array(
                'post_title'   => 'Support Desk',
                'post_content' => '[ai_support_tickets]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            )
        );

        if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
            update_option( 'ai_support_desk_page_id', $new_page_id );
            return get_permalink( $new_page_id );
        }

        return home_url();
    }

    /**
     * Output CSS styles in wp_head for early theme override
     */
    public static function output_head_styles() {
        echo self::get_common_styles();
    }

    /**
     * Return custom CSS tokens and utility classes for sleek modern styling
     */
    private static function get_common_styles() {
        return '
        <style id="ai-support-assistant-styles">
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");

            h1.wp-block-post-title,
            .wp-block-post-title,
            .entry-title {
                text-align: center !important;
                margin-left: auto !important;
                margin-right: auto !important;
                display: block !important;
            }

            .ai-support-root {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: #0f172a;
                -webkit-font-smoothing: antialiased;
                box-sizing: border-box;
            }
            .ai-support-root *, .ai-support-root *::before, .ai-support-root *::after {
                box-sizing: border-box;
            }

            .ai-support-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
                overflow: hidden;
            }

            .ai-support-field {
                margin-bottom: 18px;
            }

            .ai-support-label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                margin-bottom: 6px;
            }

            .ai-support-input,
            .ai-support-select,
            .ai-support-textarea {
                width: 100%;
                padding: 10px 14px;
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                font-size: 14px;
                font-family: inherit;
                color: #0f172a;
                transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                outline: none;
            }

            .ai-support-input:focus,
            .ai-support-select:focus,
            .ai-support-textarea:focus {
                border-color: #6366f1;
                background: #ffffff;
                box-shadow: 0 0 0 3.5px rgba(99, 102, 241, 0.15);
            }

            .ai-support-btn-primary {
                background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
                color: #ffffff !important;
                border: none;
                padding: 11px 22px;
                font-size: 14px;
                font-weight: 600;
                border-radius: 10px;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s ease;
                box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
                text-decoration: none !important;
            }
            .ai-support-btn-primary:hover {
                background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
                transform: translateY(-1px);
                box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
            }

            .ai-support-btn-secondary {
                background: #f1f5f9;
                color: #334155 !important;
                border: 1px solid #cbd5e1;
                padding: 9px 18px;
                font-size: 14px;
                font-weight: 600;
                border-radius: 10px;
                cursor: pointer;
                text-decoration: none !important;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ai-support-btn-secondary:hover {
                background: #e2e8f0;
                color: #0f172a !important;
            }

            .ai-support-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }
            .ai-support-badge-open {
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
            }
            .ai-support-badge-pending {
                background: #fffbeb;
                color: #b45309;
                border: 1px solid #fde68a;
            }
            .ai-support-badge-closed {
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
            }

            .ai-support-msg-header {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                margin-bottom: 4px !important;
                font-size: 12px !important;
                color: #64748b !important;
                padding: 0 4px !important;
            }
            .ai-support-msg-header-cust {
                justify-content: flex-end !important;
            }
            .ai-support-msg-header-agent {
                justify-content: flex-start !important;
            }

            @keyframes aiWidgetPopIn {
                from { opacity: 0; transform: translateY(16px) scale(0.96); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
        </style>';
    }

    /**
     * Option A: Render Customer Support Portal Shortcode [ai_support_tickets]
     */
    public static function render_portal_shortcode() {

        if ( ! headers_sent() ) {
            nocache_headers();
        }

        if ( ! AI_Support_Assistant_Settings::is_frontend_portal_enabled() ) {
            return '<div class="ai-support-notice alert" style="padding:15px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; font-family:sans-serif;">Customer Support Portal is currently disabled.</div>';
        }

        ob_start();

        $is_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $action       = isset( $_GET['ticket_action'] ) ? sanitize_key( $_GET['ticket_action'] ) : 'list';
        $ticket_id    = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;
        $departments  = AI_Support_Assistant_Agents::get_departments();

        echo self::get_common_styles();
        ?>
        <div class="ai-support-root ai-support-portal-wrap" style="max-width:940px; margin:30px auto;">

            <!-- Header Banner -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); padding:24px 28px; border-radius:16px; margin-bottom:24px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.15); color:#fff;">
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:24px;">💬</span>
                        <h2 style="margin:0; font-size:22px; font-weight:700; color:#ffffff; letter-spacing:-0.02em;">Customer Support Desk</h2>
                        <span style="background:rgba(99,102,241,0.25); border:1px solid rgba(165,180,252,0.3); color:#c7d2fe; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px;">✨ AI Powered</span>
                    </div>
                    <p style="margin:6px 0 0 34px; font-size:13px; color:#94a3b8;">Create, track, and manage your support inquiries with real-time assistance.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="<?php echo esc_url( remove_query_arg( array( 'ticket_action', 'ticket_id' ) ) ); ?>" class="ai-support-btn-secondary">
                        📋 My Tickets
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'ticket_action', 'new' ) ); ?>" class="ai-support-btn-primary">
                        <span>+</span> Open New Ticket
                    </a>
                </div>
            </div>

            <?php if ( 'new' === $action ) : ?>

                <!-- Form: Open New Ticket -->
                <div class="ai-support-card" style="padding:32px;">
                    <div style="margin-bottom:24px; border-bottom:1px solid #f1f5f9; padding-bottom:16px;">
                        <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Submit a Support Request</h3>
                        <p style="margin:4px 0 0 0; font-size:13px; color:#64748b;">Please provide complete details so our team (and AI assistant) can best help you.</p>
                    </div>

                    <form id="ai-support-portal-create-form">
                        <?php wp_nonce_field( 'ai_support_frontend_nonce', 'nonce' ); ?>

                        <?php if ( ! $is_logged_in ) : ?>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="ai-support-field-group">
                                <div class="ai-support-field">
                                    <label class="ai-support-label">Your Name *</label>
                                    <input type="text" name="guest_name" required class="ai-support-input" placeholder="e.g. Jane Doe">
                                </div>
                                <div class="ai-support-field">
                                    <label class="ai-support-label">Your Email *</label>
                                    <input type="email" name="guest_email" required class="ai-support-input" placeholder="jane@example.com">
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="ai-support-field">
                            <label class="ai-support-label">Subject *</label>
                            <input type="text" name="subject" required class="ai-support-input" placeholder="Brief summary of your issue...">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="ai-support-field-group">
                            <div class="ai-support-field">
                                <label class="ai-support-label">Department</label>
                                <select name="department_id" class="ai-support-select">
                                    <option value="">Select Department</option>
                                    <?php foreach ( $departments as $dept ) : ?>
                                        <option value="<?php echo esc_attr( $dept->id ); ?>"><?php echo esc_html( $dept->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ai-support-field">
                                <label class="ai-support-label">Priority</label>
                                <select name="priority" class="ai-support-select">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="ai-support-field">
                            <label class="ai-support-label">Message *</label>
                            <textarea name="message" rows="6" required class="ai-support-textarea" placeholder="Describe your question or issue in detail..."></textarea>
                        </div>

                        <div style="display:flex; align-items:center; gap:16px; margin-top:24px;">
                            <button type="submit" class="ai-support-btn-primary">
                                Submit Support Ticket
                            </button>
                            <span class="portal-form-status" style="font-size:14px; font-weight:500;"></span>
                        </div>
                    </form>
                </div>

            <?php elseif ( 'view' === $action && $ticket_id ) : ?>

                <!-- View Single Ticket Details -->
                <?php
                $ticket = AI_Support_Assistant_Tickets::get( $ticket_id );
                $authorized = false;

                if ( $ticket ) {
                    if ( $is_logged_in && (int) $ticket->customer_id === (int) $current_user->ID ) {
                        $authorized = true;
                    } elseif ( ! empty( $_GET['access_email'] ) && strcasecmp( $_GET['access_email'], $ticket->customer_email ) === 0 ) {
                        $authorized = true;
                    }
                }
                ?>

                <?php if ( ! $ticket || ! $authorized ) : ?>
                    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:20px; color:#991b1b;">
                        <strong style="display:block; font-size:15px; margin-bottom:4px;">⚠️ Access Restricted</strong>
                        <p style="margin:0; font-size:13px;">Ticket not found or you do not have permission to view this ticket.</p>
                    </div>
                <?php else : ?>
                    <?php
                    $messages = AI_Support_Assistant_Tickets::get_messages( $ticket_id );
                    $statuses = AI_Support_Assistant_Tickets::get_statuses();
                    $badge_class = 'closed' === $ticket->status ? 'ai-support-badge-closed' : ( 'pending' === $ticket->status ? 'ai-support-badge-pending' : 'ai-support-badge-open' );
                    ?>
                    <div class="ai-support-card" style="padding:28px;">
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid #f1f5f9; padding-bottom:20px; margin-bottom:24px;">
                            <div>
                                <div style="font-size:12px; font-weight:600; color:#6366f1; letter-spacing:0.04em; text-transform:uppercase; margin-bottom:4px;">Ticket #<?php echo esc_html( $ticket->id ); ?></div>
                                <h3 style="margin:0; font-size:20px; font-weight:700; color:#0f172a;"><?php echo esc_html( $ticket->subject ); ?></h3>
                            </div>
                            <span class="ai-support-badge <?php echo esc_attr( $badge_class ); ?>">
                                <?php echo esc_html( isset( $statuses[ $ticket->status ] ) ? $statuses[ $ticket->status ] : ucfirst( $ticket->status ) ); ?>
                            </span>
                        </div>

                        <!-- Messages Thread -->
                        <div id="ai-support-messages-container" style="display:flex; flex-direction:column; gap:16px; margin-bottom:28px;">
                            <?php foreach ( $messages as $msg ) : ?>
                                <?php $is_cust = 'customer' === $msg->sender_type; ?>
                                <div style="display:flex; flex-direction:column; align-self:<?php echo $is_cust ? 'flex-end' : 'flex-start'; ?>; max-width:85%;" data-message-id="<?php echo esc_attr( $msg->id ); ?>">
                                    <div class="ai-support-msg-header <?php echo $is_cust ? 'ai-support-msg-header-cust' : 'ai-support-msg-header-agent'; ?>" style="display:flex; justify-content:<?php echo $is_cust ? 'flex-end' : 'flex-start'; ?>; align-items:center; margin-bottom:4px; font-size:12px; color:#64748b; padding:0 4px; gap:12px;">
                                        <strong style="color:<?php echo $is_cust ? '#4f46e5' : '#0f172a'; ?>; margin-right:12px; display:inline-block;"><?php echo esc_html( $is_cust ? 'You' : ( $msg->sender_name ? $msg->sender_name : 'Support Agent' ) ); ?></strong>
                                        <span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?></span>
                                    </div>
                                    <div style="padding:14px 18px; border-radius:14px; font-size:14px; line-height:1.6; <?php echo $is_cust ? 'background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; border-bottom-right-radius:2px;' : 'background:#f8fafc; color:#1e293b; border:1px solid #e2e8f0; border-bottom-left-radius:2px;'; ?>">
                                        <?php echo nl2br( esc_html( $msg->message ) ); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Customer Reply Form -->
                        <div style="border-top:1px solid #f1f5f9; padding-top:24px;">
                            <h4 style="margin:0 0 12px 0; font-size:15px; font-weight:600; color:#0f172a;">Post a Reply</h4>
                            <form id="ai-support-portal-reply-form">
                                <?php wp_nonce_field( 'ai_support_frontend_nonce', 'nonce' ); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">

                                <div class="ai-support-field">
                                    <textarea name="message" rows="4" required class="ai-support-textarea" placeholder="Type your reply to the support team..."></textarea>
                                </div>
                                <div style="display:flex; align-items:center; gap:16px;">
                                    <button type="submit" class="ai-support-btn-primary">
                                        Send Reply
                                    </button>
                                    <span class="portal-reply-status" style="font-size:14px; font-weight:500;"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else : ?>

                <!-- List Customer Tickets -->
                <?php if ( $is_logged_in ) : ?>
                    <?php
                    global $wpdb;
                    $tickets_table = $wpdb->prefix . 'ai_support_tickets';
                    $user_tickets  = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$tickets_table} WHERE customer_id = %d ORDER BY created_at DESC",
                            $current_user->ID
                        )
                    );
                    $statuses = AI_Support_Assistant_Tickets::get_statuses();
                    ?>

                    <?php if ( empty( $user_tickets ) ) : ?>
                        <div class="ai-support-card" style="padding:48px; text-align:center;">
                            <div style="font-size:40px; margin-bottom:12px;">📭</div>
                            <h3 style="margin:0 0 6px 0; font-size:18px; font-weight:700; color:#0f172a;">No Active Tickets</h3>
                            <p style="color:#64748b; font-size:14px; margin-bottom:20px;">You haven't submitted any support requests yet.</p>
                            <a href="<?php echo esc_url( add_query_arg( 'ticket_action', 'new' ) ); ?>" class="ai-support-btn-primary">
                                + Open Your First Ticket
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="ai-support-card">
                            <table style="width:100%; border-collapse:collapse; text-align:left;">
                                <thead>
                                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.03em;">
                                        <th style="padding:14px 20px;">Ticket ID</th>
                                        <th style="padding:14px 20px;">Subject</th>
                                        <th style="padding:14px 20px;">Status</th>
                                        <th style="padding:14px 20px;">Date</th>
                                        <th style="padding:14px 20px; text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $user_tickets as $t ) : ?>
                                        <?php $badge_class = 'closed' === $t->status ? 'ai-support-badge-closed' : ( 'pending' === $t->status ? 'ai-support-badge-pending' : 'ai-support-badge-open' ); ?>
                                        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;">
                                            <td style="padding:16px 20px; font-weight:600; color:#6366f1;">#<?php echo esc_html( $t->id ); ?></td>
                                            <td style="padding:16px 20px; font-weight:600; color:#0f172a;"><?php echo esc_html( $t->subject ); ?></td>
                                            <td style="padding:16px 20px;">
                                                <span class="ai-support-badge <?php echo esc_attr( $badge_class ); ?>">
                                                    <?php echo esc_html( isset( $statuses[ $t->status ] ) ? $statuses[ $t->status ] : ucfirst( $t->status ) ); ?>
                                                </span>
                                            </td>
                                            <td style="padding:16px 20px; font-size:13px; color:#64748b;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $t->created_at ) ) ); ?></td>
                                            <td style="padding:16px 20px; text-align:right;">
                                                <a href="<?php echo esc_url( add_query_arg( array( 'ticket_action' => 'view', 'ticket_id' => $t->id ) ) ); ?>" style="color:#4f46e5; font-weight:600; font-size:13px; text-decoration:none;">View Details →</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php else : ?>

                    <!-- Guest View -->
                    <div class="ai-support-card" style="padding:40px; text-align:center;">
                        <h3 style="margin:0 0 8px 0; font-size:20px; font-weight:700; color:#0f172a;">Welcome to Support</h3>
                        <p style="color:#64748b; font-size:14px; margin-bottom:24px; max-width:480px; margin-left:auto; margin-right:auto;">Submit a support request directly or log in to access your complete ticket history.</p>
                        <div style="display:flex; justify-content:center; gap:12px;">
                            <a href="<?php echo esc_url( add_query_arg( 'ticket_action', 'new' ) ); ?>" class="ai-support-btn-primary">
                                + Submit Support Ticket
                            </a>
                            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="ai-support-btn-secondary">
                                Log In to View Tickets
                            </a>
                        </div>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var createForm = document.getElementById('ai-support-portal-create-form');
            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var status = createForm.querySelector('.portal-form-status');
                    status.style.color = '#4f46e5';
                    status.textContent = 'Submitting ticket & classifying with AI...';

                    var formData = new FormData(createForm);
                    formData.append('action', 'ai_support_frontend_create_ticket');

                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            var card = createForm.closest('.ai-support-card');
                            if (card) {
                                card.innerHTML = 
                                    '<div style="padding:40px 24px; text-align:center;">' +
                                        '<div style="font-size:48px; margin-bottom:14px;">🎉</div>' +
                                        '<h3 style="margin:0 0 10px 0; font-size:22px; font-weight:700; color:#0f172a;">Ticket #' + data.data.ticket_id + ' Created Successfully!</h3>' +
                                        '<p style="font-size:14px; color:#64748b; line-height:1.6; max-width:540px; margin:0 auto 24px auto;">' +
                                            'Your support inquiry has been submitted. If you want to check the progress, visit your <a href="' + data.data.redirect_url + '" style="color:#4f46e5; font-weight:700; text-decoration:underline;">[Support Desk]</a>.' +
                                        '</p>' +
                                        '<div style="display:flex; justify-content:center; gap:12px;">' +
                                            '<a href="' + data.data.redirect_url + '" class="ai-support-btn-primary" style="padding:12px 24px; text-decoration:none;">' +
                                                'View Ticket Progress →' +
                                            '</a>' +
                                        '</div>' +
                                    '</div>';
                            }
                        } else {
                            status.style.color = '#dc2626';
                            status.textContent = '✗ ' + (data.data || 'Failed to submit ticket.');
                        }
                    })
                    .catch(err => {
                        status.style.color = '#dc2626';
                        status.textContent = '✗ Connection error.';
                    });
                });
            }

            var replyForm = document.getElementById('ai-support-portal-reply-form');
            if (replyForm) {
                replyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var status = replyForm.querySelector('.portal-reply-status');
                    var textarea = replyForm.querySelector('textarea[name="message"]');
                    var msgText = textarea.value.trim();

                    if (!msgText) return;

                    status.style.color = '#4f46e5';
                    status.textContent = 'Sending reply...';

                    var formData = new FormData(replyForm);
                    formData.append('action', 'ai_support_frontend_customer_reply');

                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            status.style.color = '#059669';
                            status.textContent = '✓ Reply posted!';
                            
                            // Dynamically append message bubble to chat container
                            var container = document.getElementById('ai-support-messages-container');
                            if (container) {
                                var newBubble = document.createElement('div');
                                if (data.data.message_id) {
                                    newBubble.setAttribute('data-message-id', data.data.message_id);
                                }
                                newBubble.style.display = 'flex';
                                newBubble.style.flexDirection = 'column';
                                newBubble.style.alignSelf = 'flex-end';
                                newBubble.style.maxWidth = '85%';
                                newBubble.innerHTML = '<div class="ai-support-msg-header ai-support-msg-header-cust" style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:4px; font-size:12px; color:#64748b; padding:0 4px; gap:12px;">' +
                                    '<strong style="color:#4f46e5; display: inline-block; margin-right: 12px;">You</strong>' +
                                    '<span>' + (data.data.created_at || 'Just now') + '</span></div>' +
                                    '<div style="padding:14px 18px; border-radius:14px; font-size:14px; line-height:1.6; background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; border-bottom-right-radius:2px;">' +
                                    msgText.replace(/\n/g, '<br>') + '</div>';
                                container.appendChild(newBubble);
                                newBubble.scrollIntoView({ behavior: 'smooth' });
                            }
                            textarea.value = '';
                            setTimeout(function() { status.textContent = ''; }, 2500);
                        } else {
                            status.style.color = '#dc2626';
                            status.textContent = '✗ ' + (data.data || 'Failed to post reply.');
                        }
                    })
                    .catch(err => {
                        status.style.color = '#dc2626';
                        status.textContent = '✗ Connection error.';
                    });
                });
            }

            // Real-time AJAX Polling for single ticket view
            var msgContainer = document.getElementById('ai-support-messages-container');
            if (msgContainer) {
                var currentTicketId = <?php echo isset( $ticket->id ) ? (int) $ticket->id : 0; ?>;
                var accessEmailParam = '<?php echo esc_js( isset( $_GET['access_email'] ) ? sanitize_email( $_GET['access_email'] ) : '' ); ?>';

                function getMaxMsgId() {
                    var els = msgContainer.querySelectorAll('[data-message-id]');
                    var max = 0;
                    els.forEach(function(el) {
                        var id = parseInt(el.getAttribute('data-message-id'), 10);
                        if (id > max) max = id;
                    });
                    return max;
                }

                var lastMsgId = getMaxMsgId();

                function pollForNewReplies() {
                    if (!currentTicketId) return;

                    var params = new URLSearchParams();
                    params.append('action', 'ai_support_frontend_poll_messages');
                    params.append('ticket_id', currentTicketId);
                    params.append('last_message_id', lastMsgId);
                    if (accessEmailParam) {
                        params.append('access_email', accessEmailParam);
                    }

                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: params.toString()
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(resData) {
                        if (resData.success && resData.data && resData.data.messages && resData.data.messages.length > 0) {
                            resData.data.messages.forEach(function(msg) {
                                if (msgContainer.querySelector('[data-message-id="' + msg.id + '"]')) {
                                    return;
                                }

                                var bubble = document.createElement('div');
                                bubble.setAttribute('data-message-id', msg.id);
                                bubble.style.display = 'flex';
                                bubble.style.flexDirection = 'column';
                                bubble.style.alignSelf = msg.is_customer ? 'flex-end' : 'flex-start';
                                bubble.style.maxWidth = '85%';

                                var hdrClass = msg.is_customer ? 'ai-support-msg-header-cust' : 'ai-support-msg-header-agent';
                                var alignJustify = msg.is_customer ? 'flex-end' : 'flex-start';
                                var nameColor = msg.is_customer ? '#4f46e5' : '#0f172a';
                                var styleBg = msg.is_customer 
                                    ? 'background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; border-bottom-right-radius:2px;' 
                                    : 'background:#f8fafc; color:#1e293b; border:1px solid #e2e8f0; border-bottom-left-radius:2px;';

                                bubble.innerHTML = 
                                    '<div class="ai-support-msg-header ' + hdrClass + '" style="display:flex; justify-content:' + alignJustify + '; align-items:center; margin-bottom:4px; font-size:12px; color:#64748b; padding:0 4px; gap:12px;">' +
                                        '<strong style="color:' + nameColor + '; display:inline-block; margin-right:12px;">' + msg.sender_name + '</strong>' +
                                        '<span>' + msg.date + '</span>' +
                                    '</div>' +
                                    '<div style="padding:14px 18px; border-radius:14px; font-size:14px; line-height:1.6; ' + styleBg + '">' +
                                        msg.message +
                                    '</div>';

                                msgContainer.appendChild(bubble);
                                bubble.scrollIntoView({ behavior: 'smooth' });

                                if (msg.id > lastMsgId) {
                                    lastMsgId = msg.id;
                                }
                            });
                        }
                    })
                    .catch(function(err) {});
                }

                setInterval(pollForNewReplies, 3000);
            }
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Option B: Render Floating Support Desk Widget on Site Footer
     */
    public static function render_floating_widget() {

        if ( ! AI_Support_Assistant_Settings::is_floating_widget_enabled() || is_admin() ) {
            return;
        }

        $position    = AI_Support_Assistant_Settings::get_widget_position();
        $is_left     = 'bottom-left' === $position;
        $pos_css     = $is_left ? 'left:28px;' : 'right:28px;';
        $departments = AI_Support_Assistant_Agents::get_departments();

        echo self::get_common_styles();
        ?>
        <div class="ai-support-root">
            <!-- Floating Launcher Button -->
            <div id="ai-support-widget-launcher" style="position:fixed; bottom:28px; <?php echo esc_attr( $pos_css ); ?> z-index:999999;">
                <button type="button" id="ai-support-widget-toggle" class="ai-support-btn-primary" style="padding:14px 22px; border-radius:30px; font-size:15px; box-shadow:0 10px 25px -5px rgba(79, 70, 229, 0.45); animation: aiPulseGlow 3s infinite ease-in-out;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <span>Support</span>
                </button>
            </div>

            <!-- Floating Support Window Modal -->
            <div id="ai-support-widget-modal" style="display:none; position:fixed; bottom:92px; <?php echo esc_attr( $pos_css ); ?> width:380px; max-width:calc(100vw - 32px); background:#ffffff; border-radius:18px; box-shadow:0 25px 50px -12px rgba(15, 23, 42, 0.25); border:1px solid #e2e8f0; z-index:999999; overflow:hidden;">
                
                <!-- Modal Header -->
                <div style="background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color:#ffffff; padding:18px 20px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:18px;">💬</span>
                            <span style="font-weight:700; font-size:16px; color:#ffffff;">Customer Support Desk</span>
                        </div>
                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">AI-assisted support portal</div>
                    </div>
                    <button type="button" id="ai-support-widget-close" style="background:rgba(255,255,255,0.1); border:none; color:#ffffff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background 0.2s ease;">
                        ✕
                    </button>
                </div>

                <!-- Modal Body -->
                <div style="padding:20px; max-height:480px; overflow-y:auto;">
                    <form id="ai-support-widget-form">
                        <?php wp_nonce_field( 'ai_support_frontend_nonce', 'nonce' ); ?>

                        <?php if ( ! is_user_logged_in() ) : ?>
                            <div class="ai-support-field">
                                <label class="ai-support-label">Your Name *</label>
                                <input type="text" name="guest_name" required class="ai-support-input" placeholder="e.g. Jane Doe">
                            </div>
                            <div class="ai-support-field">
                                <label class="ai-support-label">Your Email *</label>
                                <input type="email" name="guest_email" required class="ai-support-input" placeholder="jane@example.com">
                            </div>
                        <?php endif; ?>

                        <div class="ai-support-field">
                            <label class="ai-support-label">Subject *</label>
                            <input type="text" name="subject" required class="ai-support-input" placeholder="How can we help?">
                        </div>

                        <div class="ai-support-field">
                            <label class="ai-support-label">Department</label>
                            <select name="department_id" class="ai-support-select">
                                <option value="">Select Department</option>
                                <?php foreach ( $departments as $dept ) : ?>
                                    <option value="<?php echo esc_attr( $dept->id ); ?>"><?php echo esc_html( $dept->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ai-support-field">
                            <label class="ai-support-label">Message *</label>
                            <textarea name="message" rows="4" required class="ai-support-textarea" placeholder="Type message..."></textarea>
                        </div>

                        <button type="submit" class="ai-support-btn-primary" style="width:100%; margin-top:6px;">
                            Submit Support Ticket
                        </button>
                        <div class="widget-status" style="margin-top:14px; font-size:13px; text-align:center; font-weight:500;"></div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtn = document.getElementById('ai-support-widget-toggle');
            var closeBtn  = document.getElementById('ai-support-widget-close');
            var modal     = document.getElementById('ai-support-widget-modal');
            var widgetForm= document.getElementById('ai-support-widget-form');

            if (toggleBtn && modal) {
                toggleBtn.addEventListener('click', function() {
                    var isHidden = modal.style.display === 'none' || !modal.style.display;
                    if (isHidden) {
                        modal.style.display = 'block';
                        modal.style.animation = 'aiWidgetPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                    } else {
                        modal.style.display = 'none';
                    }
                });
            }

            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }

            if (widgetForm) {
                widgetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var status = widgetForm.querySelector('.widget-status');
                    status.style.color = '#4f46e5';
                    status.textContent = 'Submitting & classifying ticket...';

                    var formData = new FormData(widgetForm);
                    formData.append('action', 'ai_support_frontend_create_ticket');

                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            var modalBody = widgetForm.parentElement;
                            if (modalBody) {
                                modalBody.innerHTML = 
                                    '<div style="padding:28px 20px; text-align:center;">' +
                                        '<div style="font-size:44px; margin-bottom:12px;">🎉</div>' +
                                        '<h4 style="margin:0 0 8px 0; font-size:18px; font-weight:700; color:#0f172a;">Ticket #' + data.data.ticket_id + ' Submitted!</h4>' +
                                        '<p style="font-size:13px; color:#64748b; line-height:1.6; margin:0 0 18px 0;">' +
                                            'Your support request has been submitted. If you want to check the progress, visit your <a href="' + data.data.redirect_url + '" style="color:#4f46e5; font-weight:700; text-decoration:underline;">[Support Desk]</a>.' +
                                        '</p>' +
                                        '<a href="' + data.data.redirect_url + '" class="ai-support-btn-primary" style="width:100%; display:inline-flex; justify-content:center; padding:11px 18px; text-decoration:none;">' +
                                            'Open Support Desk →' +
                                        '</a>' +
                                    '</div>';
                            }
                        } else {
                            status.style.color = '#dc2626';
                            status.textContent = '✗ ' + (data.data || 'Error creating ticket.');
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX Ticket Creation from Frontend Portal / Floating Widget
     */
    public static function handle_ajax_create_ticket() {

        check_ajax_referer( 'ai_support_frontend_nonce', 'nonce' );

        $is_logged_in = is_user_logged_in();
        $user_id      = $is_logged_in ? get_current_user_id() : null;

        $subject       = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $message       = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $department_id = isset( $_POST['department_id'] ) ? absint( $_POST['department_id'] ) : null;
        $priority      = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'medium';

        if ( empty( $subject ) || empty( $message ) ) {
            wp_send_json_error( 'Subject and message are required.' );
        }

        // 1. Create Ticket
        $ticket_id = AI_Support_Assistant_Tickets::create(
            array(
                'customer_id'   => $user_id,
                'subject'       => $subject,
                'message'       => $message,
                'department_id' => $department_id,
                'priority'      => $priority,
                'auto_assign'   => true,
            )
        );

        if ( ! $ticket_id ) {
            wp_send_json_error( 'Unable to create ticket in database.' );
        }

        // 2. Automatically Run AI Classification if Enabled
        if ( AI_Support_Assistant_Settings::is_ai_enabled() && AI_Support_Assistant_Settings::is_classification_enabled() ) {
            AI_Support_Assistant_AI_Classifier::classify( $ticket_id );
        } else {
            AI_Support_Assistant_Tickets::auto_assign( $ticket_id );
        }

        $support_desk_url = self::get_or_create_support_desk_page_url();

        $redirect_url = add_query_arg(
            array(
                'ticket_action' => 'view',
                'ticket_id'     => $ticket_id,
            ),
            $support_desk_url
        );

        if ( ! $is_logged_in && ! empty( $_POST['guest_email'] ) ) {
            $redirect_url = add_query_arg( 'access_email', sanitize_email( $_POST['guest_email'] ), $redirect_url );
        }

        wp_send_json_success(
            array(
                'ticket_id'        => $ticket_id,
                'message'          => sprintf( 'Ticket #%d created successfully!', $ticket_id ),
                'redirect_url'     => $redirect_url,
                'support_desk_url' => $support_desk_url,
            )
        );
    }

    /**
     * Handle Customer Reply AJAX from Frontend Portal
     */
    public static function handle_ajax_customer_reply() {

        check_ajax_referer( 'ai_support_frontend_nonce', 'nonce' );

        $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
        $message   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( ! $ticket_id || empty( $message ) ) {
            wp_send_json_error( 'Message content is required.' );
        }

        $ticket = AI_Support_Assistant_Tickets::get( $ticket_id );
        if ( ! $ticket ) {
            wp_send_json_error( 'Ticket not found.' );
        }

        $sender_id = is_user_logged_in() ? get_current_user_id() : null;

        $added = AI_Support_Assistant_Tickets::add_message(
            $ticket_id,
            $sender_id,
            'customer',
            $message
        );

        if ( ! $added ) {
            wp_send_json_error( 'Failed to save customer reply.' );
        }

        // Reopen ticket if closed
        if ( ! AI_Support_Assistant_Tickets::is_active_status( $ticket->status ) ) {
            AI_Support_Assistant_Tickets::update_status( $ticket_id, 'open' );
        }

        wp_send_json_success(
            array(
                'message'    => 'Reply posted successfully.',
                'created_at' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
                'message_id' => $added,
            )
        );
    }

    /**
     * Handle AJAX Polling for New Messages in Frontend Portal
     */
    public static function handle_ajax_poll_messages() {

        $ticket_id       = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
        $last_message_id = isset( $_POST['last_message_id'] ) ? absint( $_POST['last_message_id'] ) : 0;

        if ( ! $ticket_id ) {
            wp_send_json_error( 'Invalid ticket ID.' );
        }

        $ticket = AI_Support_Assistant_Tickets::get( $ticket_id );
        if ( ! $ticket ) {
            wp_send_json_error( 'Ticket not found.' );
        }

        // Authorization check
        $authorized = false;
        if ( is_user_logged_in() && (int) $ticket->customer_id === (int) get_current_user_id() ) {
            $authorized = true;
        } elseif ( ! empty( $_POST['access_email'] ) && strcasecmp( $_POST['access_email'], $ticket->customer_email ) === 0 ) {
            $authorized = true;
        } elseif ( current_user_can( 'manage_options' ) ) {
            $authorized = true;
        }

        if ( ! $authorized ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ai_support_messages';

        $raw_messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, u.display_name AS sender_name
                FROM {$table} AS m
                LEFT JOIN {$wpdb->users} AS u ON m.sender_id = u.ID
                WHERE m.ticket_id = %d AND m.id > %d
                ORDER BY m.id ASC",
                $ticket_id,
                $last_message_id
            )
        );

        $formatted_messages = array();
        foreach ( $raw_messages as $msg ) {
            $is_cust = 'customer' === $msg->sender_type;
            $formatted_messages[] = array(
                'id'          => (int) $msg->id,
                'sender_type' => $msg->sender_type,
                'sender_name' => $is_cust ? 'You' : ( $msg->sender_name ? $msg->sender_name : 'Support Agent' ),
                'is_customer' => $is_cust,
                'date'        => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $msg->created_at ) ),
                'message'     => nl2br( esc_html( $msg->message ) ),
            );
        }

        $statuses = AI_Support_Assistant_Tickets::get_statuses();

        wp_send_json_success(
            array(
                'messages'     => $formatted_messages,
                'status'       => $ticket->status,
                'status_label' => isset( $statuses[ $ticket->status ] ) ? $statuses[ $ticket->status ] : ucfirst( $ticket->status ),
            )
        );
    }
}

