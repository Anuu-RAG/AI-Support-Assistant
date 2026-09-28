<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Support_Assistant_Database {

    public static function seed_departments() {

        global $wpdb;

        $table = $wpdb->prefix . 'ai_support_departments';

        $departments = array(
            'Technical',
            'Billing',
            'Shipping',
            'Returns',
            'General',
        );

        foreach ( $departments as $name ) {

            $slug = sanitize_title( $name );

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                    FROM {$table}
                    WHERE slug = %s",
                    $slug
                )
            );

            if ( ! $exists ) {

                $wpdb->insert(
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
            }
        }
    }

    /**
     * Create plugin database tables.
     */
    public static function create_tables() {

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tickets_table  = $wpdb->prefix . 'ai_support_tickets';
        $messages_table = $wpdb->prefix . 'ai_support_messages';
        $departments_table = $wpdb->prefix . 'ai_support_departments';
        $agents_table      = $wpdb->prefix . 'ai_support_agents';        

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_tickets = "CREATE TABLE $tickets_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            priority VARCHAR(20) NOT NULL DEFAULT 'medium',
            category VARCHAR(50) NULL,
            department_id BIGINT UNSIGNED NULL,
            assigned_agent BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY department_id (department_id),
            KEY assigned_agent (assigned_agent),
            KEY status (status)
        ) $charset_collate;";

        $sql_messages = "CREATE TABLE $messages_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            sender_id BIGINT UNSIGNED NULL,
            sender_type VARCHAR(20) NOT NULL DEFAULT 'customer',
            message LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";

        $sql_departments = "CREATE TABLE $departments_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $sql_agents = "CREATE TABLE $agents_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            department_id BIGINT UNSIGNED NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            active_tickets INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY department_id (department_id),
            KEY is_available (is_available)
        ) $charset_collate;";

        dbDelta( $sql_tickets );
        dbDelta( $sql_messages );
        dbDelta( $sql_departments );
        dbDelta( $sql_agents );

        self::seed_departments();

        update_option( 'ai_support_db_version', AI_SUPPORT_ASSISTANT_VERSION );
    }

    /**
     * Check if database tables need update.
     */
    public static function check_db_update() {
        $installed_ver = get_option( 'ai_support_db_version' );
        if ( $installed_ver !== AI_SUPPORT_ASSISTANT_VERSION ) {
            self::create_tables();
        }
    }
}