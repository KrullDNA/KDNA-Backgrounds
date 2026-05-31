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
     * Build the full front-end config array for a background post.
     *
     * This is the single source of truth for the JSON that becomes
     * window.kdnaBgData[id]. The footer output, the Elementor editor
     * preload, and the collection cache below all call this so the
     * shape can never drift between them.
     */
    public static function build_config( $post_id ) {
        $colours   = get_post_meta( $post_id, '_kdna_bg_colours', true );
        $speed     = get_post_meta( $post_id, '_kdna_bg_speed', true );
        $amplitude = get_post_meta( $post_id, '_kdna_bg_amplitude', true );
        $density   = get_post_meta( $post_id, '_kdna_bg_density', true );
        $seed      = get_post_meta( $post_id, '_kdna_bg_seed', true );
        $darken    = get_post_meta( $post_id, '_kdna_bg_darken_top', true );

        /* Glass refraction (second pass) */
        $glass_type      = get_post_meta( $post_id, '_kdna_bg_glass_type', true );
        $refract_strength = get_post_meta( $post_id, '_kdna_bg_refract_strength', true );
        $refract_scale   = get_post_meta( $post_id, '_kdna_bg_refract_scale', true );
        $refract_speed   = get_post_meta( $post_id, '_kdna_bg_refract_speed', true );
        $rib_count       = get_post_meta( $post_id, '_kdna_bg_rib_count', true );
        $rib_angle       = get_post_meta( $post_id, '_kdna_bg_rib_angle', true );

        if ( empty( $colours ) || ! is_array( $colours ) ) {
            $colours = array( '#0a2463', '#1e6bff', '#3d8bff' );
        }

        return array(
            'id'              => intval( $post_id ),
            'colours'         => $colours,
            'speed'           => '' !== $speed ? floatval( $speed ) : 5,
            'amplitude'       => '' !== $amplitude ? intval( $amplitude ) : 100,
            'density'         => '' !== $density ? floatval( $density ) : 6,
            'seed'            => '' !== $seed ? intval( $seed ) : 5,
            'darkenTop'       => '1' === $darken,
            'glassType'       => '' !== $glass_type ? $glass_type : 'none',
            'refractStrength' => '' !== $refract_strength ? floatval( $refract_strength ) : 0,
            'refractScale'    => '' !== $refract_scale ? floatval( $refract_scale ) : 12,
            'refractSpeed'    => '' !== $refract_speed ? floatval( $refract_speed ) : 5,
            'ribCount'        => '' !== $rib_count ? intval( $rib_count ) : 40,
            'ribAngle'        => '' !== $rib_angle ? floatval( $rib_angle ) : 90,
        );
    }

    /**
     * Collect gradient config for a specific background post.
     */
    public static function enqueue_gradient_data( $post_id ) {

        if ( isset( self::$data[ $post_id ] ) ) {
            return;
        }

        self::$data[ $post_id ] = self::build_config( $post_id );
    }

    /**
     * Return all collected data.
     */
    public static function get_all_data() {
        return self::$data;
    }
}
