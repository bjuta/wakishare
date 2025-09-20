<?php

namespace YourShare;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function register(string $plugin_file): void
    {
        register_activation_hook($plugin_file, [self::class, 'activate']);
        register_deactivation_hook($plugin_file, [self::class, 'deactivate']);
    }

    public static function activate(): void
    {
        self::create_tables();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }

    private static function create_tables(): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $charset = $wpdb->get_charset_collate();

        $events_table   = $wpdb->prefix . 'yourshare_events';
        $reactions_table = $wpdb->prefix . 'yourshare_reactions';
        $cache_table     = $wpdb->prefix . 'yourshare_counts_cache';

        $events_sql = "CREATE TABLE {$events_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            network varchar(50) NOT NULL DEFAULT '',
            share_url text NOT NULL,
            ip_address varchar(100) NOT NULL DEFAULT '',
            user_agent text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_network (post_id, network)
        ) {$charset};";

        $reactions_sql = "CREATE TABLE {$reactions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reaction varchar(50) NOT NULL DEFAULT '',
            total int(11) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_reaction (post_id, reaction)
        ) {$charset};";

        $cache_sql = "CREATE TABLE {$cache_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            network varchar(50) NOT NULL DEFAULT '',
            share_count bigint(20) unsigned NOT NULL DEFAULT 0,
            retrieved_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_network (post_id, network)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($events_sql);
        dbDelta($reactions_sql);
        dbDelta($cache_sql);
    }
}
