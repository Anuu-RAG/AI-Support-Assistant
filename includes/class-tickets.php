<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Tickets {

    /**
     * Allowed ticket statuses.
     */
    public static function get_statuses() {
        return array(
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'pending'     => 'Pending',
            'resolved'    => 'Resolved',
            'closed'      => 'Closed',
        );
    }

    /**
     * Allowed ticket priorities.
     */
    public static function get_priorities() {
        return array(
            'low'    => 'Low',
            'medium' => 'Medium',
            'high'   => 'High',
            'urgent' => 'Urgent',
        );
    }

    /**
     * Check if status is considered active (workload active).
     */
    public static function is_active_status( $status ) {
        return in_array( $status, array( 'open', 'in_progress', 'pending' ), true );
    }

    /**
     * Get all tickets with joined department and agent information.
     */
    public static function get_all() {

        global $wpdb;

        $tickets_table     = $wpdb->prefix . 'ai_support_tickets';
        $departments_table = $wpdb->prefix . 'ai_support_departments';
        $agents_table      = $wpdb->prefix . 'ai_support_agents';

        return $wpdb->get_results(
            "SELECT
                tickets.*,
                departments.name AS department_name,
                agent_user.display_name AS agent_name,
                customer_user.display_name AS customer_name,
                customer_user.user_email AS customer_email
            FROM {$tickets_table} AS tickets
            LEFT JOIN {$departments_table} AS departments
                ON tickets.department_id = departments.id
            LEFT JOIN {$agents_table} AS agents
                ON tickets.assigned_agent = agents.id
            LEFT JOIN {$wpdb->users} AS agent_user
                ON agents.user_id = agent_user.ID
            LEFT JOIN {$wpdb->users} AS customer_user
                ON tickets.customer_id = customer_user.ID
            ORDER BY tickets.created_at DESC"
        );
    }

    /**
     * Get a single ticket with detailed information.
     */
    public static function get( $ticket_id ) {

        global $wpdb;

        $tickets_table     = $wpdb->prefix . 'ai_support_tickets';
        $departments_table = $wpdb->prefix . 'ai_support_departments';
        $agents_table      = $wpdb->prefix . 'ai_support_agents';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    tickets.*,
                    departments.name AS department_name,
                    agent_user.display_name AS agent_name,
                    agent_user.user_email AS agent_email,
                    customer_user.display_name AS customer_name,
                    customer_user.user_email AS customer_email
                FROM {$tickets_table} AS tickets
                LEFT JOIN {$departments_table} AS departments
                    ON tickets.department_id = departments.id
                LEFT JOIN {$agents_table} AS agents
                    ON tickets.assigned_agent = agents.id
                LEFT JOIN {$wpdb->users} AS agent_user
                    ON agents.user_id = agent_user.ID
                LEFT JOIN {$wpdb->users} AS customer_user
                    ON tickets.customer_id = customer_user.ID
                WHERE tickets.id = %d",
                absint( $ticket_id )
            )
        );
    }

    /**
     * Create a new ticket.
     */
    public static function create( $data ) {

        global $wpdb;

        $tickets_table  = $wpdb->prefix . 'ai_support_tickets';
        $messages_table = $wpdb->prefix . 'ai_support_messages';

        $subject = sanitize_text_field( $data['subject'] );
        $message = sanitize_textarea_field( $data['message'] );

        if ( empty( $subject ) || empty( $message ) ) {
            return false;
        }

        $customer_id = ! empty( $data['customer_id'] )
            ? absint( $data['customer_id'] )
            : null;

        $priorities = self::get_priorities();
        $priority   = ! empty( $data['priority'] ) && isset( $priorities[ $data['priority'] ] )
            ? sanitize_text_field( $data['priority'] )
            : 'medium';

        $category = ! empty( $data['category'] )
            ? sanitize_text_field( $data['category'] )
            : null;

        $department_id = ! empty( $data['department_id'] ) && AI_Support_Assistant_Agents::department_exists( $data['department_id'] )
            ? absint( $data['department_id'] )
            : null;

        $assigned_agent = null;

        if ( ! empty( $data['assigned_agent'] ) && AI_Support_Assistant_Agents::agent_exists( $data['assigned_agent'] ) ) {
            $assigned_agent = absint( $data['assigned_agent'] );
        } elseif ( ! empty( $data['auto_assign'] ) ) {
            $agent = AI_Support_Assistant_Agents::find_available_agent( $department_id );
            if ( $agent ) {
                $assigned_agent = absint( $agent->id );
            }
        }

        /*
         * Insert ticket.
         */
        $inserted = $wpdb->insert(
            $tickets_table,
            array(
                'customer_id'    => $customer_id,
                'subject'        => $subject,
                'status'         => 'open',
                'priority'       => $priority,
                'category'       => $category,
                'department_id'  => $department_id,
                'assigned_agent' => $assigned_agent,
                'created_at'     => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
            )
        );

        if ( false === $inserted ) {
            return false;
        }

        $ticket_id = $wpdb->insert_id;

        /*
         * Insert initial customer message.
         */
        $message_inserted = $wpdb->insert(
            $messages_table,
            array(
                'ticket_id'   => $ticket_id,
                'sender_id'   => $customer_id,
                'sender_type' => 'customer',
                'message'     => $message,
                'created_at'  => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );

        if ( false === $message_inserted ) {
            $wpdb->delete(
                $tickets_table,
                array( 'id' => $ticket_id ),
                array( '%d' )
            );
            return false;
        }

        // Increment agent workload if assigned upon creation (ticket is 'open')
        if ( $assigned_agent ) {
            AI_Support_Assistant_Agents::increment_active_tickets( $assigned_agent );
        }

        return $ticket_id;
    }

    /**
     * Update ticket status and manage agent workload accordingly.
     */
    public static function update_status( $ticket_id, $new_status ) {

        global $wpdb;

        $ticket = self::get( $ticket_id );
        if ( ! $ticket ) {
            return false;
        }

        $statuses = self::get_statuses();
        if ( ! isset( $statuses[ $new_status ] ) ) {
            return false;
        }

        $old_status = $ticket->status;
        if ( $old_status === $new_status ) {
            return true;
        }

        $table = $wpdb->prefix . 'ai_support_tickets';

        $updated = $wpdb->update(
            $table,
            array(
                'status'     => $new_status,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $ticket_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return false;
        }

        // Handle workload count changes for assigned agent
        if ( ! empty( $ticket->assigned_agent ) ) {
            $was_active = self::is_active_status( $old_status );
            $is_active  = self::is_active_status( $new_status );

            if ( $was_active && ! $is_active ) {
                AI_Support_Assistant_Agents::decrement_active_tickets( $ticket->assigned_agent );
            } elseif ( ! $was_active && $is_active ) {
                AI_Support_Assistant_Agents::increment_active_tickets( $ticket->assigned_agent );
            }
        }

        return true;
    }

    /**
     * Assign or reassign ticket to an agent.
     */
    public static function assign_agent( $ticket_id, $new_agent_id ) {

        global $wpdb;

        $ticket = self::get( $ticket_id );
        if ( ! $ticket ) {
            return false;
        }

        $old_agent_id = ! empty( $ticket->assigned_agent ) ? absint( $ticket->assigned_agent ) : null;
        $new_agent_id = ! empty( $new_agent_id ) ? absint( $new_agent_id ) : null;

        if ( $new_agent_id && ! AI_Support_Assistant_Agents::agent_exists( $new_agent_id ) ) {
            return false;
        }

        if ( $old_agent_id === $new_agent_id ) {
            return true;
        }

        $table   = $wpdb->prefix . 'ai_support_tickets';
        $updated = $wpdb->update(
            $table,
            array(
                'assigned_agent' => $new_agent_id,
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $ticket_id ) ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return false;
        }

        // Workload adjustment only if ticket is active
        if ( self::is_active_status( $ticket->status ) ) {
            if ( $old_agent_id ) {
                AI_Support_Assistant_Agents::decrement_active_tickets( $old_agent_id );
            }
            if ( $new_agent_id ) {
                AI_Support_Assistant_Agents::increment_active_tickets( $new_agent_id );
            }
        }

        return true;
    }

    /**
     * Auto-assign an agent based on ticket department and workload.
     */
    public static function auto_assign( $ticket_id ) {

        $ticket = self::get( $ticket_id );
        if ( ! $ticket ) {
            return array(
                'success' => false,
                'message' => 'Ticket not found.',
            );
        }

        $agent = AI_Support_Assistant_Agents::find_available_agent( $ticket->department_id );

        if ( ! $agent ) {
            return array(
                'success' => false,
                'message' => 'No available agent found in the selected department.',
            );
        }

        $assigned = self::assign_agent( $ticket_id, $agent->id );

        if ( ! $assigned ) {
            return array(
                'success' => false,
                'message' => 'Failed to assign agent.',
            );
        }

        $agent_details = AI_Support_Assistant_Agents::get_agent( $agent->id );
        $agent_name    = $agent_details ? $agent_details->display_name : 'Agent #' . $agent->id;

        return array(
            'success' => true,
            'message' => sprintf( 'Ticket assigned to %s.', $agent_name ),
            'agent'   => $agent_details,
        );
    }

    /**
     * Comprehensive update method for a ticket (status, priority, department, agent).
     */
    public static function update_ticket( $ticket_id, $data ) {

        global $wpdb;

        $ticket = self::get( $ticket_id );
        if ( ! $ticket ) {
            return false;
        }

        $table = $wpdb->prefix . 'ai_support_tickets';

        // 1. Handle Status Change
        if ( isset( $data['status'] ) ) {
            self::update_status( $ticket_id, sanitize_text_field( $data['status'] ) );
        }

        // 2. Handle Agent Change (or Auto Assign)
        if ( isset( $data['assigned_agent'] ) ) {
            if ( 'auto' === $data['assigned_agent'] ) {
                self::auto_assign( $ticket_id );
            } else {
                $agent_id = ! empty( $data['assigned_agent'] ) ? absint( $data['assigned_agent'] ) : null;
                self::assign_agent( $ticket_id, $agent_id );
            }
        }

        // 3. Handle Priority & Department
        $update_fields = array();
        $format        = array();

        if ( isset( $data['priority'] ) ) {
            $priorities = self::get_priorities();
            if ( isset( $priorities[ $data['priority'] ] ) ) {
                $update_fields['priority'] = sanitize_text_field( $data['priority'] );
                $format[]                  = '%s';
            }
        }

        if ( array_key_exists( 'department_id', $data ) ) {
            $dept_id = ! empty( $data['department_id'] ) && AI_Support_Assistant_Agents::department_exists( $data['department_id'] )
                ? absint( $data['department_id'] )
                : null;

            $update_fields['department_id'] = $dept_id;
            $format[]                       = '%d';
        }

        if ( isset( $data['category'] ) ) {
            $update_fields['category'] = sanitize_text_field( $data['category'] );
            $format[]                  = '%s';
        }

        if ( ! empty( $update_fields ) ) {
            $update_fields['updated_at'] = current_time( 'mysql' );
            $format[]                    = '%s';

            $wpdb->update(
                $table,
                $update_fields,
                array( 'id' => absint( $ticket_id ) ),
                $format,
                array( '%d' )
            );
        }

        return true;
    }

    /**
     * Get messages belonging to a ticket.
     */
    public static function get_messages( $ticket_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_messages';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    m.*,
                    u.display_name AS sender_name
                FROM {$table} AS m
                LEFT JOIN {$wpdb->users} AS u
                    ON m.sender_id = u.ID
                WHERE m.ticket_id = %d
                ORDER BY m.created_at ASC",
                absint( $ticket_id )
            )
        );
    }

    /**
     * Add a message to a ticket.
     */
    public static function add_message( $ticket_id, $sender_id, $sender_type, $message ) {

        global $wpdb;

        $messages_table = $wpdb->prefix . 'ai_support_messages';

        $inserted = $wpdb->insert(
            $messages_table,
            array(
                'ticket_id'   => absint( $ticket_id ),
                'sender_id'   => $sender_id ? absint( $sender_id ) : null,
                'sender_type' => sanitize_text_field( $sender_type ),
                'message'     => sanitize_textarea_field( $message ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );

        if ( false === $inserted ) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Update ticket updated_at timestamp
        $tickets_table = $wpdb->prefix . 'ai_support_tickets';
        $wpdb->update(
            $tickets_table,
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $ticket_id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $message_id;
    }
}
