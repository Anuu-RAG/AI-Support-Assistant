<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tickets  = AI_Support_Assistant_Tickets::get_all();
$statuses = AI_Support_Assistant_Tickets::get_statuses();

?>

<div class="wrap">

    <h1 class="wp-heading-inline">
        AI Support Tickets
    </h1>

    <a href="<?php echo esc_url(
        admin_url( 'admin.php?page=ai-support&action=new' )
    ); ?>" class="page-title-action">
        Add New Ticket
    </a>

    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">

        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>Subject</th>
                <th>Customer</th>
                <th>Department</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Category</th>
                <th>Assigned Agent</th>
                <th>Created</th>
            </tr>
        </thead>

        <tbody>

        <?php if ( empty( $tickets ) ) : ?>

            <tr>
                <td colspan="9">
                    No tickets found.
                </td>
            </tr>

        <?php else : ?>

            <?php foreach ( $tickets as $ticket ) : ?>

                <tr>

                    <td>
                        #<?php echo esc_html( $ticket->id ); ?>
                    </td>

                    <td>
                        <strong>
                            <a href="<?php echo esc_url(
                                admin_url(
                                    'admin.php?page=ai-support'
                                    . '&action=view'
                                    . '&ticket_id='
                                    . $ticket->id
                                )
                            ); ?>">
                                <?php echo esc_html( $ticket->subject ); ?>
                            </a>
                        </strong>
                    </td>

                    <td>
                        <?php
                        if ( $ticket->customer_name ) {
                            echo esc_html( $ticket->customer_name );
                        } elseif ( $ticket->customer_id ) {
                            echo 'User #' . esc_html( $ticket->customer_id );
                        } else {
                            echo '<em>Guest</em>';
                        }
                        ?>
                    </td>

                    <td>
                        <?php echo $ticket->department_name ? esc_html( $ticket->department_name ) : '<em>None</em>'; ?>
                    </td>

                    <td>
                        <?php
                        $status_label = isset( $statuses[ $ticket->status ] ) ? $statuses[ $ticket->status ] : ucfirst( $ticket->status );
                        $badge_color  = 'open' === $ticket->status ? '#008a20' : ( 'in_progress' === $ticket->status ? '#0073aa' : ( 'pending' === $ticket->status ? '#d63638' : '#646970' ) );
                        ?>
                        <span style="display:inline-block; padding:2px 8px; border-radius:3px; background:<?php echo esc_attr( $badge_color ); ?>; color:#fff; font-size:12px; font-weight:600;">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    </td>

                    <td>
                        <?php echo esc_html( ucfirst( $ticket->priority ) ); ?>
                    </td>

                    <td>
                        <?php echo $ticket->category ? esc_html( ucfirst( $ticket->category ) ) : '—'; ?>
                    </td>

                    <td>
                        <?php echo $ticket->agent_name ? esc_html( $ticket->agent_name ) : '<em style="color:#d63638;">Unassigned</em>'; ?>
                    </td>

                    <td>
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ticket->created_at ) ) ); ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>

    </table>

</div>