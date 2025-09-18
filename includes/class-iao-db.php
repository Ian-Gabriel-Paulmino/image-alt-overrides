<?php

class IAO_DB {
    private $table;


    public function __construct() {
        /** @global wpdb $wpdb */
        global $wpdb;
        $this->table = $wpdb->prefix . 'image_alt_overrides';

    }

    public function get_alt_text( $image_id, $post_id ): mixed {
        /** @global wpdb $wpdb */
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT alt_text FROM $this->table WHERE image_id = %d AND post_id = %d",
            $image_id, $post_id
        ));

        return $result ? $result : null;
    }
}