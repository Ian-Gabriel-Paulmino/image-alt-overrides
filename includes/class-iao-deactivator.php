<?php

class IAO_Deactivator {
    
    public static function deactivate() {

        /** @global mixed $wpdb */
        global $wpdb;

        /** @var string $table_name */
        $table_name =  "$wpdb->prefix image_alt_overrides";

        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    }
}