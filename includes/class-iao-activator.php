<?php

Class IAO_Activator {

    public static function activate(): void {
        
        /** @global wpdb $wpdb */
        global $wpdb;

        /** @var string $table_name */
        $table_name = $wpdb->prefix . 'image_alt_overrides';

        /** @var string $charset_collate */
        $charset_collate = $wpdb->get_charset_collate();

        /** @var string $sql */
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            image_id BIGINT(20) UNSIGNED NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            alt_text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_At DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY image_post (image_id, post_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

    }
}