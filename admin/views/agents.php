<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$agents      = AI_Support_Assistant_Agents::get_agents();
$departments = AI_Support_Assistant_Agents::get_departments();

// Get eligible WP Users to add as agents (users not already agents)
$existing_agent_user_ids = wp_list_pluck( $agents, 'user_id' );
$all_users = get_users(
    array(
        'number'  => 100,
        'orderby' => 'display_name',
    )
);

$eligible_users = array_filter(
    $all_users,
    function( $u ) use ( $existing_agent_user_ids ) {
        return ! in_array( (int) $u->ID, $existing_agent_user_ids, true );
    }
);

?>

<div class="wrap">

    <h1>Support Agents & Departments</h1>

    <div style="display:flex; gap:20px; align-items:flex-start; margin-top:20px;">

        <!-- Left Column: Tables & Updates -->
        <div style="flex:1;">

            <h2>Departments</h2>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $departments ) ) : ?>
                    <tr>
                        <td colspan="3">No departments created yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $departments as $department ) : ?>
                        <tr>
                            <td><?php echo esc_html( $department->id ); ?></td>
                            <td><strong><?php echo esc_html( $department->name ); ?></strong></td>
                            <td><code><?php echo esc_html( $department->slug ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <br><br>

            <h2>Support Agents</h2>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Availability</th>
                        <th>Active Workload</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $agents ) ) : ?>
                    <tr>
                        <td colspan="6">No support agents assigned yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $agents as $agent ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $agent->display_name ); ?></strong></td>
                            <td><?php echo esc_html( $agent->user_email ); ?></td>
                            <td>
                                <form method="post" style="display:inline-flex; gap:5px;" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support-agents' ) ); ?>">
                                    <?php wp_nonce_field( 'ai_support_update_agent' ); ?>
                                    <input type="hidden" name="ai_support_action" value="update_agent">
                                    <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->id ); ?>">
                                    <input type="hidden" name="is_available" value="<?php echo esc_attr( $agent->is_available ); ?>">

                                    <select name="department_id" style="font-size:12px;">
                                        <option value="">No Department</option>
                                        <?php foreach ( $departments as $dept ) : ?>
                                            <option value="<?php echo esc_attr( $dept->id ); ?>" <?php selected( $agent->department_id, $dept->id ); ?>>
                                                <?php echo esc_html( $dept->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-small">Update</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" style="display:inline;" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support-agents' ) ); ?>">
                                    <?php wp_nonce_field( 'ai_support_update_agent' ); ?>
                                    <input type="hidden" name="ai_support_action" value="update_agent">
                                    <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->id ); ?>">
                                    <input type="hidden" name="department_id" value="<?php echo esc_attr( $agent->department_id ); ?>">
                                    <input type="hidden" name="is_available" value="<?php echo $agent->is_available ? '0' : '1'; ?>">

                                    <?php if ( $agent->is_available ) : ?>
                                        <button type="submit" class="button button-small button-primary" style="background:#008a20; border-color:#008a20;">
                                            ● Available
                                        </button>
                                    <?php else : ?>
                                        <button type="submit" class="button button-small" style="color:#d63638;">
                                            ○ Unavailable
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <strong style="font-size:14px;"><?php echo esc_html( $agent->active_tickets ); ?></strong> active tickets
                            </td>
                            <td>
                                <form method="post" style="display:inline;" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support-agents' ) ); ?>">
                                    <?php wp_nonce_field( 'ai_support_update_agent' ); ?>
                                    <input type="hidden" name="ai_support_action" value="update_agent">
                                    <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->id ); ?>">
                                    <input type="hidden" name="sync_workload" value="1">
                                    <button type="submit" class="button button-small" title="Recalculate active tickets directly from database">
                                        🔄 Sync Workload
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

        </div>

        <!-- Right Column: Add Department & Add Agent Forms -->
        <div style="width:320px; flex-shrink:0;">

            <!-- Add Department Form -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h3 style="margin-top:0;">Add Department</h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support-agents' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_add_department' ); ?>
                    <input type="hidden" name="ai_support_action" value="add_department">

                    <p>
                        <label for="department_name">Department Name <span class="required">*</span></label><br>
                        <input type="text" id="department_name" name="department_name" class="regular-text" style="width:100%;" required>
                    </p>

                    <?php submit_button( 'Add Department', 'primary', 'submit', false ); ?>
                </form>
            </div>

            <!-- Add Agent Form -->
            <div style="background:#fff; border:1px solid #c3c4c7; padding:20px; border-radius:4px;">
                <h3 style="margin-top:0;">Add Support Agent</h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ai-support-agents' ) ); ?>">
                    <?php wp_nonce_field( 'ai_support_add_agent' ); ?>
                    <input type="hidden" name="ai_support_action" value="add_agent">

                    <p>
                        <label for="user_id">Select WordPress User <span class="required">*</span></label><br>
                        <select name="user_id" id="user_id" style="width:100%;" required>
                            <option value="">Select User</option>
                            <?php foreach ( $eligible_users as $user ) : ?>
                                <option value="<?php echo esc_attr( $user->ID ); ?>">
                                    <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="department_id">Department</label><br>
                        <select name="department_id" id="department_id" style="width:100%;">
                            <option value="">No Department</option>
                            <?php foreach ( $departments as $dept ) : ?>
                                <option value="<?php echo esc_attr( $dept->id ); ?>">
                                    <?php echo esc_html( $dept->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <?php submit_button( 'Add Agent', 'primary', 'submit', false ); ?>
                </form>
            </div>

        </div>

    </div>

</div>