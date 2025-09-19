<?php




class IAO_Frontend {
    private IAO_DB $db;
    private int $current_post_id = 0;

    public function __construct( IAO_DB $db ) {
        /** @global wpdb $wpdb */
        $this->db = $db;
    }

    public function init() {
        // Store current post ID for ACF filters
        add_action('wp', [$this, 'store_current_post_id']);
        add_action('loop_start', [$this, 'store_current_post_id_from_query']);
        
        // Replace <img ... wp-image-123 ...> in post content
        add_filter('the_content', [$this, 'override_alts']);

        // Catch wp_get_attachment_image() and related helpers
        add_filter('wp_get_attachment_image_attributes', [$this, 'override_alts_in_attributes'], 10, 3);

        // ACF: handle image fields (single image) - higher priority
        add_filter('acf/format_value/type=image', [$this, 'override_acf_images'], 15, 3);

        // ACF: handle gallery fields (array of images) - higher priority
        add_filter('acf/format_value/type=gallery', [$this, 'override_acf_gallery'], 15, 3);

        // Generic fallback for ACF formatted values (catches nested / unusual setups)
        add_filter('acf/format_value', [$this, 'override_acf_generic'], 25, 3);
        
        // Additional hook for get_field() calls
        add_filter('acf/load_value', [$this, 'override_acf_load_value'], 15, 3);
    }

    /**
     * Store current post ID from main query
     */
    public function store_current_post_id() {
        global $post;
        if ($post && is_object($post) && isset($post->ID)) {
            $this->current_post_id = intval($post->ID);
        }
    }

    /**
     * Store current post ID when loop starts
     */
    public function store_current_post_id_from_query($query) {
        if ($query->is_main_query() && $query->have_posts()) {
            $posts = $query->get_posts();
            if (!empty($posts) && isset($posts[0]->ID)) {
                $this->current_post_id = intval($posts[0]->ID);
            }
        }
    }

    /**
     * Get the appropriate post ID for context
     */
    private function get_context_post_id($acf_post_id = null): int {
        // Try ACF's post ID first
        if ($acf_post_id && is_numeric($acf_post_id) && $acf_post_id > 0) {
            return intval($acf_post_id);
        }
        
        // Fall back to stored post ID
        if ($this->current_post_id > 0) {
            return $this->current_post_id;
        }
        
        // Last resort: global $post
        global $post;
        if ($post && is_object($post) && isset($post->ID)) {
            return intval($post->ID);
        }
        
        // If we're on a singular page, try get_queried_object_id()
        if (is_singular()) {
            return get_queried_object_id();
        }
        
        return 0;
    }

    /**
     * Replace alt attributes on <img ... wp-image-ID ...> inside post content.
     */
    public function override_alts( $content ) {
        $post_id = $this->get_context_post_id();
        if ( ! $post_id ) {
            return $content;
        }

        return preg_replace_callback(
            '/<img[^>]+wp-image-(\d+)[^>]*>/i',
            function( $matches ) use ( $post_id ) {
                $image_id = intval( $matches[1] );
                $alt = $this->get_custom_alt( $image_id, $post_id );
                
                // If the image tag doesn't have an alt attribute at all, add one:
                if ( stripos( $matches[0], ' alt=' ) === false ) {
                    return preg_replace( '/<img/i', '<img alt="' . esc_attr( $alt ) . '"', $matches[0], 1 );
                }
                return preg_replace( '/alt="[^"]*"/i', 'alt="' . esc_attr( $alt ) . '"', $matches[0] );
            },
            $content
        );
    }

    /**
     * Filter that runs for wp_get_attachment_image() and similar helpers.
     */
    public function override_alts_in_attributes( $attr, $attachment, $size ) {
        $post_id = $this->get_context_post_id();
        if ( ! $post_id ) {
            return $attr;
        }

        // Normalize attachment ID
        $attachment_id = $this->normalize_attachment_id($attachment);

        if ( $attachment_id > 0 ) {
            $custom_alt = $this->get_custom_alt( $attachment_id, $post_id );
            if ( $custom_alt !== '' ) {
                $attr['alt'] = $custom_alt;
            }
        }

        return $attr;
    }

    /**
     * ACF load_value filter - catches get_field() calls
     */
    public function override_acf_load_value( $value, $post_id, $field ) {
        $context_post_id = $this->get_context_post_id($post_id);
        if ( ! $context_post_id ) {
            return $value;
        }

        // Only process image and gallery field types
        if ( isset($field['type']) && in_array($field['type'], ['image', 'gallery']) ) {
            if ( $field['type'] === 'image' ) {
                return $this->process_acf_image_value( $value, $context_post_id );
            } elseif ( $field['type'] === 'gallery' ) {
                return $this->process_acf_gallery_value( $value, $context_post_id );
            }
        }

        return $value;
    }

    /**
     * ACF: called for fields of type 'image' when ACF formats the value.
     */
    public function override_acf_images( $value, $post_id, $field ) {
        $context_post_id = $this->get_context_post_id($post_id);
        if ( ! $context_post_id ) {
            return $value;
        }

        return $this->process_acf_image_value( $value, $context_post_id );
    }

    /**
     * ACF: called for gallery fields.
     */
    public function override_acf_gallery( $value, $post_id, $field ) {
        $context_post_id = $this->get_context_post_id($post_id);
        if ( ! $context_post_id ) {
            return $value;
        }

        return $this->process_acf_gallery_value( $value, $context_post_id );
    }

    /**
     * Generic ACF fallback
     */
    public function override_acf_generic( $value, $post_id, $field ) {
        $context_post_id = $this->get_context_post_id($post_id);
        if ( ! $context_post_id ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            return $this->walk_and_override_acf_array( $value, $context_post_id );
        }
        return $value;
    }

    /**
     * Process a single ACF image value
     */
    private function process_acf_image_value( $value, int $post_id ) {
        // If ACF returned an array with ID and alt etc.
        if ( is_array( $value ) && ( isset( $value['ID'] ) || isset( $value['id'] ) ) ) {
            $image_id = intval( $value['ID'] ?? $value['id'] );
            $custom_alt = $this->get_custom_alt( $image_id, $post_id );
            if ( $custom_alt !== '' ) {
                $value['alt'] = $custom_alt;
            }
            return $value;
        }

        return $value;
    }

    /**
     * Process ACF gallery value
     */
    private function process_acf_gallery_value( $value, int $post_id ) {
        if ( empty( $value ) || ! is_array( $value ) ) {
            return $value;
        }

        foreach ( $value as $k => $item ) {
            if ( is_array( $item ) && ( isset( $item['ID'] ) || isset( $item['id'] ) ) ) {
                $image_id = intval( $item['ID'] ?? $item['id'] );
                $custom_alt = $this->get_custom_alt( $image_id, $post_id );
                if ( $custom_alt !== '' ) {
                    $value[ $k ]['alt'] = $custom_alt;
                }
            }
        }

        return $value;
    }

    /**
     * Normalize attachment ID from various input types
     */
    private function normalize_attachment_id( $attachment ): int {
        if ( is_object( $attachment ) && property_exists( $attachment, 'ID' ) ) {
            return intval( $attachment->ID );
        }
        
        if ( is_array( $attachment ) && isset( $attachment['ID'] ) ) {
            return intval( $attachment['ID'] );
        }
        
        if ( is_numeric( $attachment ) ) {
            return intval( $attachment );
        }
        
        return 0;
    }

    /**
     * Recursive walker that looks for image arrays or IDs and injects alt when possible.
     */
    private function walk_and_override_acf_array( $arr, $post_id ) {
        foreach ( $arr as $key => $val ) {
            if ( is_array( $val ) ) {
                // if looks like an image array
                if ( isset( $val['ID'] ) || isset( $val['id'] ) ) {
                    $image_id = intval( $val['ID'] ?? $val['id'] );
                    $custom_alt = $this->get_custom_alt( $image_id, $post_id );
                    if ( $custom_alt !== '' ) {
                        $arr[ $key ]['alt'] = $custom_alt;
                    }
                } else {
                    // recurse
                    $arr[ $key ] = $this->walk_and_override_acf_array( $val, $post_id );
                }
            }
        }
        return $arr;
    }

    /**
     * Resolve override -> fallback to media library alt -> empty string.
     */
    private function get_custom_alt( int $image_id, int $post_id ): string {
        if ( $image_id <= 0 || $post_id <= 0 ) {
            return '';
        }
        
        $alt = $this->db->get_alt_text( $image_id, $post_id );
        if ( ! $alt ) {
            $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
        }
        return $alt ? $alt : '';
    }
}
