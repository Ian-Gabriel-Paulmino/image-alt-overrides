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
        $content = apply_filters('the_content', $post->post_content);
        preg_match_all('/wp-image-(\d+)/', $content, $matches);

        if ( empty($matches[1]) ) {
            echo '<p>No images found in this post!</p>';
            return;
        }

        $unique_images = array_unique($matches[1]);
        $per_page = 3;
        $total = count($unique_images);

        // Current page (default = 1)
        $page = isset($_GET['iao_page']) ? max(1, intval($_GET['iao_page'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Slice for pagination
        $images = array_slice($unique_images, $offset, $per_page);

        foreach ($images as $image_id) {
            $existing = $this->db->get_alt_text($image_id, $post->ID);
            $default_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            $value = $existing ? $existing : $default_alt;

            $image_tag = wp_get_attachment_image($image_id, 'thumbnail');
            echo '<p>' . $image_tag . '<br>';
            // echo '<p><strong>Image Id: ' . $image_id . '</strong></p>';
            echo '<input type="text" name="iao_alt[' . $image_id . ']" value="' . esc_attr($value) . '" class="widefat"></p>';
        }

        // Pagination controls
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div style="margin-top:10px;">';
            if ($page > 1) {
                echo '<a href="' . esc_url(add_query_arg('iao_page', $page - 1)) . '">&laquo; Prev</a> ';
            }
            if ($page < $total_pages) {
                echo '<a href="' . esc_url(add_query_arg('iao_page', $page + 1)) . '">Next &raquo;</a>';
            }
            echo '</div>';
        }
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