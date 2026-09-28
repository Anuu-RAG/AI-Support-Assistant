<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ticket_id = isset( $_GET['ticket_id'] )
    ? absint( $_GET['ticket_id'] )
    : 0;

$ticket = AI_Support_Assistant_Tickets::get( $ticket_id );

if ( ! $ticket ) {
    echo '<div class="wrap">';
    echo '<h1>Ticket Not Found</h1>';
    echo '<p>The requested ticket does not exist.</p>';
    echo '</div>';
    return;
}

$messages    = AI_Support_Assistant_Tickets::get_messages( $ticket_id );
$statuses    = AI_Support_Assistant_Tickets::get_statuses();
$priorities  = AI_Support_Assistant_Tickets::get_priorities();
$departments = AI_Support_Assistant_Agents::get_departments();
$agents      = AI_Support_Assistant_Agents::get_agents();

?>

<div class="wrap">

    <p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">
            ← Back to Tickets
        </a>
    </p>

    <h1>
        Ticket #<?php echo esc_html( $ticket->id ); ?>
        —
        <?php echo esc_html( $ticket->subject ); ?>
    </h1>

    <div style="display:flex; gap:20px; max-width:1200px; align-items:flex-start;">

        <!-- Main conversation area -->
        <div style="flex:1;">

            <div style="background:#fff; border:1px solid #c3c4c7; padding:24px; margin-bottom:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f0f0f1; padding-bottom:12px;">
                    <h2 style="margin:0; font-size:18px; font-weight:700; color:#1d2327;">💬 Conversation Thread</h2>
                    <span style="font-size:12px; color:#646970; background:#f0f0f1; padding:3px 8px; border-radius:12px; font-weight:600;">
                        <?php echo count( $messages ); ?> Messages
                    </span>
                </div>

                <?php if ( empty( $messages ) ) : ?>
                    <p style="color:#646970; font-style:italic;">No messages in this ticket yet.</p>
                <?php else : ?>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach ( $messages as $message ) : ?>
                            <?php
                            $is_customer = 'customer' === $message->sender_type;
                            $sender_display = $is_customer
                                ? ( $ticket->customer_name ? $ticket->customer_name : 'Customer' )
                                : ( $message->sender_name ? $message->sender_name : 'Support Agent' );
                            ?>
                            <div style="display:flex; flex-direction:column; align-self:<?php echo $is_customer ? 'flex-start' : 'flex-end'; ?>; max-width:85%; width:100%;">
                                <div style="display:flex; justify-content:<?php echo $is_customer ? 'flex-start' : 'flex-end'; ?>; align-items:center; margin-bottom:6px; font-size:12px; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <strong style="color:<?php echo $is_customer ? '#1d2327' : '#4f46e5'; ?>; font-size:13px;">
                                            <?php echo esc_html( $sender_display ); ?>
                                        </strong>
                                        <span style="background:<?php echo $is_customer ? '#e2e8f0' : '#eeeffe'; ?>; color:<?php echo $is_customer ? '#475569' : '#4338ca'; ?>; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px;">
                                            <?php echo esc_html( $is_customer ? '👤 Customer' : '🎧 Support Agent' ); ?>
                                        </span>
                                    </div>
                                    <span style="font-size:12px; color:#646970;">
                                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
                                    </span>
                                </div>
                                <div style="padding:14px 18px; border-radius:12px; font-size:14px; line-height:1.6; <?php echo $is_customer ? 'background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-top-left-radius:2px;' : 'background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; border-top-right-radius:2px; box-shadow:0 4px 12px rgba(79, 70, 229, 0.15);'; ?>">
                                    <?php echo nl2br( esc_html( $message->message ) ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- AI Reply Assistant Panel -->
            <?php
            $stored_draft = AI_Support_Assistant_AI_Reply::get_draft( $ticket->id );
            ?>
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h2 style="margin:0;">🤖 AI Reply Assistant</h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>" style="display:inline;">
                        <?php wp_nonce_field( 'ai_support_generate_reply' ); ?>
                        <input type="hidden" name="ai_support_action" value="generate_ai_reply">
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
                        <button type="submit" class="button button-primary">
                            ⚡ <?php echo $stored_draft ? 'Regenerate AI Draft' : 'Generate AI Reply Draft'; ?>
                        </button>
                    </form>
                </div>

                <?php if ( $stored_draft ) : ?>
                    <div style="background:#f0f6fc; border-left:4px solid #0073aa; padding:12px 15px; margin-bottom:15px; border-radius:2px;">
                        <p style="margin:0 0 6px 0; font-weight:600; color:#0073aa;">
                            ℹ️ AI-generated draft — review and edit before sending.
                        </p>
                        <p style="margin:0 0 10px 0; font-size:12px; color:#50575e;">
                            This draft was compiled based on conversation history and classification context. You can edit it directly in the reply box below before sending to the customer.
                        </p>

                        <div style="display:flex; gap:10px; align-items:center;">
                            <button type="button" class="button button-secondary button-small" onclick="document.getElementById('reply_message').value = document.getElementById('ai_draft_preview').value; document.getElementById('reply_message').focus();">
                                📋 Copy Draft to Reply Box
                            </button>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>" style="display:inline;">
                                <?php wp_nonce_field( 'ai_support_clear_draft' ); ?>
                                <input type="hidden" name="ai_support_action" value="clear_ai_draft">
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
                                <button type="submit" class="button button-link-delete button-small" style="color:#d63638; text-decoration:none;">
                                    Discard Draft
                                </button>
                            </form>
                        </div>
                    </div>

                    <textarea id="ai_draft_preview" class="large-text" rows="5" readonly style="background:#f6f7f7; font-family:monospace; font-size:13px; margin-bottom:10px; color:#2c3338;"><?php echo esc_textarea( $stored_draft ); ?></textarea>
                <?php else : ?>
                    <p style="color:#646970; font-style:italic; margin:0;">
                        Click "Generate AI Reply Draft" to analyze ticket context and generate a response for review.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Reply box -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px;">
                <h2 style="margin-top:0;">Add Reply</h2>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_reply_ticket' ); ?>
                    <input type="hidden" name="ai_support_action" value="reply_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">

                    <p>
                        <textarea
                            name="reply_message"
                            id="reply_message"
                            rows="6"
                            class="large-text"
                            placeholder="Type your support reply here..."
                            required
                        ><?php echo esc_textarea( $stored_draft ); ?></textarea>
                    </p>

                    <div style="display:flex; align-items:center; gap:15px;">
                        <label for="reply_status">Update Status on Reply:</label>
                        <select name="reply_status" id="reply_status">
                            <option value="">Do not change (<?php echo esc_html( $ticket->status ); ?>)</option>
                            <?php foreach ( $statuses as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ticket->status, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php submit_button( 'Send Reply', 'primary', 'submit', false ); ?>
                    </div>
                </form>
            </div>

        </div>

        <!-- Sidebar: Ticket Controls, AI Classification & Metadata -->
        <div style="width:320px; flex-shrink:0;">

            <!-- AI Classification Card -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h2 style="margin-top:0;">🤖 AI Classification</h2>

                <?php
                $ai_data = AI_Support_Assistant_AI_Classifier::get_stored_classification( $ticket->id );
                ?>

                <?php if ( $ai_data ) : ?>
                    <p style="margin-bottom:8px;">
                        <strong>Category:</strong>
                        <span style="background:#f0f0f1; padding:2px 8px; border-radius:3px; font-weight:600;">
                            <?php echo esc_html( ucfirst( $ai_data['category'] ) ); ?>
                        </span>
                    </p>
                    <p style="margin-bottom:8px;">
                        <strong>Priority:</strong>
                        <span style="background:#f0f0f1; padding:2px 8px; border-radius:3px; font-weight:600;">
                            <?php echo esc_html( ucfirst( $ai_data['priority'] ) ); ?>
                        </span>
                    </p>
                    <p style="margin-bottom:8px;">
                        <strong>Department:</strong>
                        <span style="background:#f0f0f1; padding:2px 8px; border-radius:3px; font-weight:600;">
                            <?php echo esc_html( $ai_data['department_name'] ); ?>
                        </span>
                    </p>
                    <p style="margin-bottom:8px;">
                        <strong>Sentiment:</strong>
                        <?php
                        $sent_color = 'positive' === $ai_data['sentiment'] ? '#008a20' : ( 'negative' === $ai_data['sentiment'] ? '#d63638' : '#646970' );
                        ?>
                        <span style="color:<?php echo esc_attr( $sent_color ); ?>; font-weight:600;">
                            ● <?php echo esc_html( ucfirst( $ai_data['sentiment'] ) ); ?>
                        </span>
                    </p>
                    <p style="margin-bottom:12px;">
                        <strong>Needs Human:</strong>
                        <?php echo $ai_data['needs_human'] ? '<span style="color:#d63638; font-weight:600;">Yes</span>' : '<span style="color:#008a20; font-weight:600;">No</span>'; ?>
                    </p>
                    <p style="font-size:11px; color:#646970; margin-bottom:15px;">
                        Last analyzed: <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ai_data['analyzed_at'] ) ) ); ?>
                    </p>
                <?php else : ?>
                    <p style="color:#646970; font-style:italic; margin-bottom:15px;">
                        AI classification has not been run yet.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_classify_ticket' ); ?>
                    <input type="hidden" name="ai_support_action" value="classify_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
                    <button type="submit" class="button button-primary" style="width:100%;">
                        🤖 Analyze with AI
                    </button>
                </form>
            </div>

            <!-- Ticket Attributes Card -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h2 style="margin-top:0;">Ticket Attributes</h2>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_update_ticket' ); ?>
                    <input type="hidden" name="ai_support_action" value="update_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">

                    <p>
                        <label for="status"><strong>Status:</strong></label><br>
                        <select name="status" id="status" style="width:100%;">
                            <?php foreach ( $statuses as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ticket->status, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="priority"><strong>Priority:</strong></label><br>
                        <select name="priority" id="priority" style="width:100%;">
                            <?php foreach ( $priorities as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ticket->priority, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="department_id"><strong>Department:</strong></label><br>
                        <select name="department_id" id="department_id" style="width:100%;">
                            <option value="">Unassigned Department</option>
                            <?php foreach ( $departments as $dept ) : ?>
                                <option value="<?php echo esc_attr( $dept->id ); ?>" <?php selected( $ticket->department_id, $dept->id ); ?>>
                                    <?php echo esc_html( $dept->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="assigned_agent"><strong>Assigned Agent:</strong></label><br>
                        <select name="assigned_agent" id="assigned_agent" style="width:100%;">
                            <option value="">Unassigned</option>
                            <?php foreach ( $agents as $agent ) : ?>
                                <option value="<?php echo esc_attr( $agent->id ); ?>" <?php selected( $ticket->assigned_agent, $agent->id ); ?>>
                                    <?php echo esc_html( $agent->display_name . ( $agent->department_name ? ' (' . $agent->department_name . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p style="margin-top:15px; display:flex; gap:10px;">
                        <?php submit_button( 'Save Changes', 'secondary', 'submit_update', false ); ?>
                    </p>
                </form>

                <hr style="margin:15px 0;">

                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_update_ticket' ); ?>
                    <input type="hidden" name="ai_support_action" value="update_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
                    <input type="hidden" name="auto_assign_trigger" value="1">
                    <button type="submit" class="button button-small" style="width:100%;">
                        ⚡ Auto-Assign Best Available Agent
                    </button>
                </form>
            </div>

            <!-- Customer Card -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px;">
                <h2 style="margin-top:0;">Customer Details</h2>

                <?php if ( $ticket->customer_id && $ticket->customer_name ) : ?>
                    <p>
                        <strong>Name:</strong> <?php echo esc_html( $ticket->customer_name ); ?><br>
                        <strong>Email:</strong> <?php echo esc_html( $ticket->customer_email ); ?><br>
                        <strong>User ID:</strong> #<?php echo esc_html( $ticket->customer_id ); ?>
                    </p>
                <?php else : ?>
                    <p><em>Guest Customer / Unknown</em></p>
                <?php endif; ?>

                <p>
                    <small style="color:#646970;">
                        Created: <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ticket->created_at ) ) ); ?><br>
                        Updated: <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ticket->updated_at ) ) ); ?>
                    </small>
                </p>
            </div>

        </div>

    </div>

</div>