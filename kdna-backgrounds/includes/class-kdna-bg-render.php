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
        $diamond_angle   = get_post_meta( $post_id, '_kdna_bg_diamond_angle', true );
        $light_move      = get_post_meta( $post_id, '_kdna_bg_light_move', true );
        $rib_sharp       = get_post_meta( $post_id, '_kdna_bg_rib_sharp', true );
        $rib_hi_width    = get_post_meta( $post_id, '_kdna_bg_rib_hi_width', true );
        $rib_hi_strength = get_post_meta( $post_id, '_kdna_bg_rib_hi_strength', true );
        $rib_sh_width    = get_post_meta( $post_id, '_kdna_bg_rib_sh_width', true );
        $rib_sh_strength = get_post_meta( $post_id, '_kdna_bg_rib_sh_strength', true );

        /* Shape controls */
        $shape_style  = get_post_meta( $post_id, '_kdna_bg_shape_style', true );
        $dominant_bg  = get_post_meta( $post_id, '_kdna_bg_dominant_bg', true );
        $stretch      = get_post_meta( $post_id, '_kdna_bg_stretch', true );
        $radiate      = get_post_meta( $post_id, '_kdna_bg_radiate', true );
        $ring_count   = get_post_meta( $post_id, '_kdna_bg_ring_count', true );
        $shape_count  = get_post_meta( $post_id, '_kdna_bg_shape_count', true );
        $color_blend  = get_post_meta( $post_id, '_kdna_bg_color_blend', true );
        $drift        = get_post_meta( $post_id, '_kdna_bg_drift', true );
        $band_min     = get_post_meta( $post_id, '_kdna_bg_band_min', true );
        $band_max     = get_post_meta( $post_id, '_kdna_bg_band_max', true );
        $band_vary    = get_post_meta( $post_id, '_kdna_bg_band_vary', true );
        $band_move    = get_post_meta( $post_id, '_kdna_bg_band_move', true );
        $band_fade    = get_post_meta( $post_id, '_kdna_bg_band_fade', true );
        $band_fade_v  = get_post_meta( $post_id, '_kdna_bg_band_fade_var', true );
        $band_bg      = get_post_meta( $post_id, '_kdna_bg_band_bg_colour', true );
        $grain        = get_post_meta( $post_id, '_kdna_bg_grain', true );
        $sheen        = get_post_meta( $post_id, '_kdna_bg_sheen', true );
        $flow_amount = get_post_meta( $post_id, '_kdna_bg_flow_amount', true );
        $flow_angle  = get_post_meta( $post_id, '_kdna_bg_flow_angle', true );
        $definition  = get_post_meta( $post_id, '_kdna_bg_definition', true );
        $spread      = get_post_meta( $post_id, '_kdna_bg_spread', true );

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
            'diamondAngle'    => '' !== $diamond_angle ? floatval( $diamond_angle ) : 45,
            'lightMove'       => '' !== $light_move ? floatval( $light_move ) : 30,
            'ribSharp'        => '' !== $rib_sharp ? floatval( $rib_sharp ) : 0,
            'ribHiWidth'      => '' !== $rib_hi_width ? floatval( $rib_hi_width ) : 25,
            'ribHiStrength'   => '' !== $rib_hi_strength ? floatval( $rib_hi_strength ) : 40,
            'ribShWidth'      => '' !== $rib_sh_width ? floatval( $rib_sh_width ) : 50,
            'ribShStrength'   => '' !== $rib_sh_strength ? floatval( $rib_sh_strength ) : 60,
            'shapeStyle'      => '' !== $shape_style ? $shape_style : 'wash',
            'dominantBg'      => '1' === $dominant_bg,
            'stretch'         => '' !== $stretch ? floatval( $stretch ) : 0,
            'radiateSpeed'    => '' !== $radiate ? floatval( $radiate ) : 45,
            'ringCount'       => '' !== $ring_count ? floatval( $ring_count ) : 1,
            'shapeCount'      => '' !== $shape_count ? floatval( $shape_count ) : 1,
            'colorBlend'      => '' !== $color_blend ? floatval( $color_blend ) : 70,
            'drift'           => '' !== $drift ? floatval( $drift ) : 40,
            'bandMin'         => '' !== $band_min ? floatval( $band_min ) : 25,
            'bandMax'         => '' !== $band_max ? floatval( $band_max ) : 60,
            'bandVary'        => '' !== $band_vary ? floatval( $band_vary ) : 50,
            'bandMove'        => '' !== $band_move ? floatval( $band_move ) : 40,
            'bandFade'        => '' !== $band_fade ? floatval( $band_fade ) : 0,
            'bandFadeVar'     => '' !== $band_fade_v ? floatval( $band_fade_v ) : 50,
            'bandBgColor'     => '' !== $band_bg ? $band_bg : '',
            'grain'           => '' !== $grain ? floatval( $grain ) : 0,
            'sheen'           => '' !== $sheen ? floatval( $sheen ) : 0,
            'flowAmount'      => '' !== $flow_amount ? floatval( $flow_amount ) : 0,
            'flowAngle'       => '' !== $flow_angle ? floatval( $flow_angle ) : 0,
            'definition'      => '' !== $definition ? floatval( $definition ) : 40,
            'spread'          => '' !== $spread ? floatval( $spread ) : 50,
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
