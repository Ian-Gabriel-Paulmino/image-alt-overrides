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

    public function save_alt_text( $image_id, $post_id, $alt_text ): void {
        /** @global wpdb $wpdb */
        global $wpdb;

        // Check if the entry exists
        $existing = $this->get_alt_text($image_id, $post_id);

        // If it exists, update entry
        if( $existing ) {
            $wpdb->update(
                $this->table,
                ['alt_text' => $alt_text],
                ['image_id' => $image_id, 'post_id' => $post_id],
                ['%s'], ['%d','%d']
            );
        } else {

            // Add new entry if it doesn't exists
            $wpdb->insert(
                $this->table,
                [
                    'image_id' => $image_id,
                    'post_id' => $post_id,
                    'alt_text' => $alt_text
                ],
                ['%d', '%d', '%s']
            );
        }
    }


    
}