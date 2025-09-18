<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

/** @global wpdb $wpdb */
global $wpdb;
$table_name = $wpdb->prefix . 'image_alt_overrides';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );