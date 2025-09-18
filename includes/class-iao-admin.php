<?php

class IAO_Admin {
    private IAO_DB $db;

    public function __construct( IAO_DB $db ) {
        $this->db = $db;
    }

    public function init(): void {
        add_action( 'add_meta_boxes', [$this, 'add_meta_box']);
        // add_action( 'save_post', [$this, 'save_alt_overrides']);
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
        // Filters content
        $content = apply_filters('the_content', $post->post_content);
        // Filters all images in that specific page/post
        preg_match_all('/wp-image-(\d+)/', $content, $matches);
        
        // If there are no matches; display not found text and return
        if ( empty($matches[1]) ) {
            echo '<p>No images found in this post!</p>';
            return;
        }

        // Loop through all the images and display alt text
        foreach( array_unique($matches[1]) as $image_id) {

            // Check if there are existing alts in the database
            $existing = $this->db->get_alt_text($image_id, $post->ID);

            // Retrieve default alt
            $default_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            // Set value to existing value alt if it exists in the db, default otherwise
            $value = $existing ? $existing : $default_alt;
            echo '<p><strong>Image Id: ' . $image_id . '</strong></p>';
            echo '<input type="text" name="iao_alt[' . $image_id . ']" value="' . esc_attr( $value ) . '" class="widefat"></p>';
        }
    }




}