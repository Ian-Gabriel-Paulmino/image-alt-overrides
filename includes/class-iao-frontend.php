<?php

class IAO_Fronted {
    private IAO_DB $db;

    public function __construct( IAO_DB $db ) {
        $this->db = $db;
    }

    public function init() {
        add_filter('the_content', [$this, 'override_alts']);
    }

    public function override_alts( $content ) {
        global $post;

        if (! $post) return $content;

        return preg_replace_callback(
            '/<img[^>]+wp-image-(\d+)[^>]*>/i',
            function( $matches ) use ( $post ) {
            $image_id = intval( $matches[1] );
            $alt = $this->db->get_alt_text( $image_id, $post->ID );
            if ( !$alt ) {
                $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

            }
                return preg_replace( '/alt="[^"]*"/i', 'alt="' . esc_attr( $alt ) . '"', $matches[0] );
            },
            $content
        );


        
    }

}