<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$departments = AI_Support_Assistant_Agents::get_departments();
$agents      = AI_Support_Assistant_Agents::get_agents();
$priorities  = AI_Support_Assistant_Tickets::get_priorities();

?>

<div class="wrap">

    <h1>Create Support Ticket</h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support' ) ); ?>">

        <?php wp_nonce_field( 'ai_support_create_ticket' ); ?>

        <input
            type="hidden"
            name="ai_support_action"
            value="create_ticket"
        >

        <table class="form-table">

            <tr>
                <th>
                    <label for="subject">Subject <span class="required">*</span></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        class="regular-text"
                        required
                    >
                </td>
            </tr>

            <tr>
                <th>
                    <label for="customer_id">Customer</label>
                </th>
                <td>
                    <select name="customer_id" id="customer_id">
                        <option value="">Guest / Unknown</option>
                        <?php
                        $users = get_users(
                            array(
                                'number'  => 200,
                                'orderby' => 'display_name',
                            )
                        );
                        ?>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>">
                                <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="department_id">Department</label>
                </th>
                <td>
                    <select name="department_id" id="department_id">
                        <option value="">Select Department</option>
                        <?php foreach ( $departments as $dept ) : ?>
                            <option value="<?php echo esc_attr( $dept->id ); ?>">
                                <?php echo esc_html( $dept->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="assigned_agent">Assign Agent</label>
                </th>
                <td>
                    <select name="assigned_agent" id="assigned_agent">
                        <option value="">Unassigned</option>
                        <?php foreach ( $agents as $agent ) : ?>
                            <option value="<?php echo esc_attr( $agent->id ); ?>">
                                <?php echo esc_html( $agent->display_name . ' (' . ( $agent->department_name ? $agent->department_name : 'No Dept' ) . ' - Active: ' . $agent->active_tickets . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label style="margin-left:15px;">
                        <input type="checkbox" name="auto_assign" value="1">
                        Auto-Assign to best available agent in department
                    </label>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="priority">Priority</label>
                </th>
                <td>
                    <select name="priority" id="priority">
                        <?php foreach ( $priorities as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, 'medium' ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="category">Category</label>
                </th>
                <td>
                    <select name="category" id="category">
                        <option value="">Select category</option>
                        <option value="billing">Billing</option>
                        <option value="shipping">Shipping</option>
                        <option value="technical">Technical</option>
                        <option value="refund">Refund</option>
                        <option value="product">Product</option>
                        <option value="account">Account</option>
                        <option value="other">Other</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="message">Customer Message <span class="required">*</span></label>
                </th>
                <td>
                    <textarea
                        name="message"
                        id="message"
                        rows="8"
                        class="large-text"
                        required
                    ></textarea>
                </td>
            </tr>

        </table>

        <?php submit_button( 'Create Ticket' ); ?>

    </form>

</div>