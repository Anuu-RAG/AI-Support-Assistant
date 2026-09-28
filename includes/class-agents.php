<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Agents {

    /**
     * Get all departments.
     */
    public static function get_departments() {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_departments';

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY name ASC"
        );
    }

    /**
     * Check if a department exists by ID.
     */
    public static function department_exists( $department_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_departments';

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                absint( $department_id )
            )
        );

        return ! empty( $id );
    }

    /**
     * Create a department.
     */
    public static function create_department( $name ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_departments';

        $name = sanitize_text_field( $name );
        $slug = sanitize_title( $name );

        if ( empty( $name ) ) {
            return false;
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $slug
            )
        );

        if ( $exists ) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'slug' => $slug,
            ),
            array(
                '%s',
                '%s',
            )
        );

        return false !== $result;
    }

    /**
     * Get all agents.
     */
    public static function get_agents() {

        global $wpdb;

        $agents_table      = $wpdb->prefix . 'ai_support_agents';
        $departments_table = $wpdb->prefix . 'ai_support_departments';

        return $wpdb->get_results(
            "SELECT
                agents.*,
                departments.name AS department_name,
                users.display_name,
                users.user_email

            FROM {$agents_table} AS agents

            LEFT JOIN {$departments_table} AS departments
                ON agents.department_id = departments.id

            LEFT JOIN {$wpdb->users} AS users
                ON agents.user_id = users.ID

            ORDER BY users.display_name ASC"
        );
    }

    /**
     * Get a single agent by agent table ID.
     */
    public static function get_agent( $agent_id ) {

        global $wpdb;

        $agents_table      = $wpdb->prefix . 'ai_support_agents';
        $departments_table = $wpdb->prefix . 'ai_support_departments';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    agents.*,
                    departments.name AS department_name,
                    users.display_name,
                    users.user_email

                FROM {$agents_table} AS agents

                LEFT JOIN {$departments_table} AS departments
                    ON agents.department_id = departments.id

                LEFT JOIN {$wpdb->users} AS users
                    ON agents.user_id = users.ID

                WHERE agents.id = %d",
                absint( $agent_id )
            )
        );
    }

    /**
     * Get agent by WordPress user ID.
     */
    public static function get_agent_by_user_id( $user_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d",
                absint( $user_id )
            )
        );
    }

    /**
     * Check if an agent exists by agent table ID.
     */
    public static function agent_exists( $agent_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                absint( $agent_id )
            )
        );

        return ! empty( $id );
    }

    /**
     * Add a WordPress user as a support agent.
     */
    public static function add_agent(
        $user_id,
        $department_id = null
    ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        $user_id = absint( $user_id );

        if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
            return false;
        }

        $existing = self::get_agent_by_user_id( $user_id );

        if ( $existing ) {
            return false;
        }

        $dept_id = null;

        if ( $department_id && self::department_exists( $department_id ) ) {
            $dept_id = absint( $department_id );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'user_id'       => $user_id,
                'department_id' => $dept_id,
                'is_available'  => 1,
                'active_tickets' => 0,
            ),
            array(
                '%d',
                '%d',
                '%d',
                '%d',
            )
        );

        return false !== $result;
    }

    /**
     * Update agent details (availability, department).
     */
    public static function update_agent( $agent_id, $data ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        $agent_id = absint( $agent_id );

        if ( ! self::agent_exists( $agent_id ) ) {
            return false;
        }

        $update_data = array();
        $format      = array();

        if ( isset( $data['is_available'] ) ) {
            $update_data['is_available'] = $data['is_available'] ? 1 : 0;
            $format[] = '%d';
        }

        if ( array_key_exists( 'department_id', $data ) ) {
            $dept_id = ! empty( $data['department_id'] ) && self::department_exists( $data['department_id'] )
                ? absint( $data['department_id'] )
                : null;

            $update_data['department_id'] = $dept_id;
            $format[] = '%d';
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $agent_id ),
            $format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Safely increment active tickets count for an agent.
     */
    public static function increment_active_tickets( $agent_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        $agent_id = absint( $agent_id );

        if ( ! $agent_id ) {
            return false;
        }

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET active_tickets = active_tickets + 1 WHERE id = %d",
                $agent_id
            )
        );
    }

    /**
     * Safely decrement active tickets count for an agent, never allowing negative count.
     */
    public static function decrement_active_tickets( $agent_id ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        $agent_id = absint( $agent_id );

        if ( ! $agent_id ) {
            return false;
        }

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET active_tickets = GREATEST(0, CAST(active_tickets AS SIGNED) - 1) WHERE id = %d",
                $agent_id
            )
        );
    }

    /**
     * Recalculate and sync active tickets for an agent directly from tickets table.
     */
    public static function sync_active_tickets( $agent_id ) {

        global $wpdb;

        $agents_table  = $wpdb->prefix . 'ai_support_agents';
        $tickets_table = $wpdb->prefix . 'ai_support_tickets';

        $agent_id = absint( $agent_id );

        if ( ! $agent_id ) {
            return;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tickets_table} WHERE assigned_agent = %d AND status IN ('open', 'in_progress', 'pending')",
                $agent_id
            )
        );

        $wpdb->update(
            $agents_table,
            array( 'active_tickets' => absint( $count ) ),
            array( 'id' => $agent_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Find an available agent in a department with the lowest workload.
     */
    public static function find_available_agent( $department_id = null ) {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_agents';

        if ( $department_id ) {

            $department_id = absint( $department_id );

            if ( ! self::department_exists( $department_id ) ) {
                return null;
            }

            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                     WHERE department_id = %d
                     AND is_available = 1
                     ORDER BY active_tickets ASC
                     LIMIT 1",
                    $department_id
                )
            );
        }

        return $wpdb->get_row(
            "SELECT * FROM {$table}
             WHERE is_available = 1
             ORDER BY active_tickets ASC
             LIMIT 1"
        );
    }
}