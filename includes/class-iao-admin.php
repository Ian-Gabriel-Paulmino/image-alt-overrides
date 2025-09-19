<?php

class IAO_Admin {
    private IAO_DB $db;

    public function __construct( IAO_DB $db ) {
        $this->db = $db;
    }

    public function init(): void {
        add_action( 'add_meta_boxes', [$this, 'add_meta_box']);
        add_action( 'save_post', [$this, 'save_alt_overrides']);
    }

    public function add_meta_box(): void {
        add_meta_box(
            'iao_metabox',
            __( 'Image Alt Overrides', 'image-alt-overrides' ),
            [$this, 'render_metabox'],
            null,
            'normal',
            'high'
        );
    }



    public function render_metabox( WP_Post $post ): void {
        $image_ids = [];

        // 1. Collect from post content
        $content = apply_filters('the_content', $post->post_content);
        if ( preg_match_all('/wp-image-(\d+)/', $content, $matches) ) {
            $image_ids = array_merge($image_ids, $matches[1]);
        }

        // 2. Collect from ACF
        if ( function_exists('get_fields') ) {
            $fields = get_fields($post->ID);
            if ( $fields ) {
                $acf_images = $this->extract_acf_images($fields);
                $image_ids  = array_merge($image_ids, $acf_images);
            }
        }

        // 3. Deduplicate
        $image_ids = array_unique(array_filter($image_ids));

        // 4. Render inputs
        echo '<div class="iao-metabox">';
        foreach ( $image_ids as $image_id ) {
            $existing    = $this->db->get_alt_text($image_id, $post->ID);
            $default_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            $value       = $existing ? $existing : $default_alt;

            $image_tag = wp_get_attachment_image($image_id, 'thumbnail');

            echo '<p>';
            echo $image_tag . '<br>';
            echo '<input type="text" name="iao_alt[' . esc_attr($image_id) . ']" value="' . esc_attr($value) . '" class="widefat">';
            echo '</p>';
        }
        echo '</div>';
    }

    /**
     * Recursively extract image IDs from ACF fields
     */
    private function extract_acf_images( $fields ): array {
        $image_ids = [];

        foreach ( $fields as $field ) {
            if ( is_array($field) ) {
                // Array return format
                if ( isset($field['ID']) || isset($field['id']) ) {
                    $image_ids[] = $field['ID'] ?? $field['id'];
                } else {
                    // Recurse for nested ACF structures
                    $image_ids = array_merge($image_ids, $this->extract_acf_images($field));
                }
            } elseif ( is_numeric($field) ) {
                // ID return format
                $image_ids[] = intval($field);
            }
        }

        return $image_ids;
    }



    public function save_alt_overrides( $post_id ): void {
        // Saves overrides to the database
        if ( isset($_POST['iao_alt']) && is_array($_POST['iao_alt']) ) {
            foreach ( $_POST['iao_alt'] as $image_id => $alt_text ) {
                $this->db->save_alt_text( intval($image_id), $post_id, sanitize_text_field($alt_text));
            }
        }
    }




}