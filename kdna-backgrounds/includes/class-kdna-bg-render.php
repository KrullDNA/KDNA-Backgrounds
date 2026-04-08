<?php
/**
 * Collects gradient configuration data and outputs it in the footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KDNA_BG_Render {

    private static $data = array();

    /**
     * Collect gradient config for a specific background post.
     */
    public static function enqueue_gradient_data( $post_id ) {

        if ( isset( self::$data[ $post_id ] ) ) {
            return;
        }

        $colours   = get_post_meta( $post_id, '_kdna_bg_colours', true );
        $speed     = get_post_meta( $post_id, '_kdna_bg_speed', true );
        $amplitude = get_post_meta( $post_id, '_kdna_bg_amplitude', true );
        $density   = get_post_meta( $post_id, '_kdna_bg_density', true );
        $seed      = get_post_meta( $post_id, '_kdna_bg_seed', true );
        $darken    = get_post_meta( $post_id, '_kdna_bg_darken_top', true );

        if ( empty( $colours ) || ! is_array( $colours ) ) {
            $colours = array( '#0a2463', '#1e6bff', '#3d8bff' );
        }

        self::$data[ $post_id ] = array(
            'id'        => $post_id,
            'colours'   => $colours,
            'speed'     => '' !== $speed ? floatval( $speed ) : 5,
            'amplitude' => '' !== $amplitude ? intval( $amplitude ) : 100,
            'density'   => '' !== $density ? floatval( $density ) : 6,
            'seed'      => '' !== $seed ? intval( $seed ) : 5,
            'darkenTop' => '1' === $darken,
        );
    }

    /**
     * Return all collected data.
     */
    public static function get_all_data() {
        return self::$data;
    }
}
