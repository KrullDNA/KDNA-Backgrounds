<?php
/**
 * Meta boxes for the KDNA Backgrounds edit screen.
 * Draggable colour rows, animation settings, and live admin preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KDNA_BG_Meta {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_kdna_background', array( $this, 'save' ), 10, 2 );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'kdna_bg_colours',
            __( 'Gradient Colours (drag to reorder)', 'kdna-backgrounds' ),
            array( $this, 'render_colours_box' ),
            'kdna_background',
            'normal',
            'high'
        );

        add_meta_box(
            'kdna_bg_settings',
            __( 'Animation Settings', 'kdna-backgrounds' ),
            array( $this, 'render_settings_box' ),
            'kdna_background',
            'normal',
            'default'
        );

        add_meta_box(
            'kdna_bg_glass',
            __( 'Glass Refraction', 'kdna-backgrounds' ),
            array( $this, 'render_glass_box' ),
            'kdna_background',
            'normal',
            'default'
        );

        add_meta_box(
            'kdna_bg_preview',
            __( 'Live Preview', 'kdna-backgrounds' ),
            array( $this, 'render_preview_box' ),
            'kdna_background',
            'side',
            'high'
        );
    }

    /* ── Colours meta box ── */
    public function render_colours_box( $post ) {
        wp_nonce_field( 'kdna_bg_save', 'kdna_bg_nonce' );

        $colours = get_post_meta( $post->ID, '_kdna_bg_colours', true );
        if ( empty( $colours ) || ! is_array( $colours ) ) {
            $colours = array( '#0a2463', '#1e6bff', '#3d8bff' );
        }
        ?>
        <div id="kdna-bg-colours-wrapper">
            <ul id="kdna-bg-colour-list">
                <?php foreach ( $colours as $i => $hex ) : ?>
                    <li class="kdna-bg-colour-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <span class="kdna-bg-drag-handle dashicons dashicons-menu"></span>
                        <span class="kdna-bg-colour-number"><?php echo esc_html( $i + 1 ); ?></span>
                        <input type="text" class="kdna-bg-colour-picker" name="kdna_bg_colours[]" value="<?php echo esc_attr( $hex ); ?>" />
                        <button type="button" class="button kdna-bg-remove-colour" title="<?php esc_attr_e( 'Remove', 'kdna-backgrounds' ); ?>">&times;</button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="button" class="button button-secondary" id="kdna-bg-add-colour">
                    <?php esc_html_e( '+ Add Colour', 'kdna-backgrounds' ); ?>
                </button>
                <span class="kdna-bg-colour-count">
                    <?php printf( esc_html__( '%d / 10 colours', 'kdna-backgrounds' ), count( $colours ) ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    /* ── Settings meta box ── */
    public function render_settings_box( $post ) {
        $speed     = get_post_meta( $post->ID, '_kdna_bg_speed', true );
        $amplitude = get_post_meta( $post->ID, '_kdna_bg_amplitude', true );
        $seed      = get_post_meta( $post->ID, '_kdna_bg_seed', true );
        $darken    = get_post_meta( $post->ID, '_kdna_bg_darken_top', true );
        $density   = get_post_meta( $post->ID, '_kdna_bg_density', true );

        $speed     = '' !== $speed ? floatval( $speed ) : 5;
        $amplitude = '' !== $amplitude ? intval( $amplitude ) : 320;
        $seed      = '' !== $seed ? intval( $seed ) : 5;
        $darken    = '' !== $darken ? $darken : '0';
        $density   = '' !== $density ? floatval( $density ) : 6;
        ?>
        <table class="form-table kdna-bg-settings-table">
            <tr>
                <th><label for="kdna_bg_speed"><?php esc_html_e( 'Speed', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_speed" name="kdna_bg_speed" min="1" max="20" step="1" value="<?php echo esc_attr( $speed ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-speed-val"><?php echo esc_html( $speed ); ?></span>
                    <p class="description"><?php esc_html_e( 'Controls how fast the gradient animates. 1 = very slow, 20 = very fast.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_amplitude"><?php esc_html_e( 'Wave Amplitude', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_amplitude" name="kdna_bg_amplitude" min="10" max="500" step="10" value="<?php echo esc_attr( $amplitude ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-amp-val"><?php echo esc_html( $amplitude ); ?></span>
                    <p class="description"><?php esc_html_e( 'Controls how much the colour waves move. Low = subtle blending, high = dramatic waves.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_density"><?php esc_html_e( 'Mesh Density', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_density" name="kdna_bg_density" min="2" max="12" step="1" value="<?php echo esc_attr( $density ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-density-val"><?php echo esc_html( $density ); ?></span>
                    <p class="description"><?php esc_html_e( 'Higher = smoother gradients, lower = more angular.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_seed"><?php esc_html_e( 'Randomness Seed', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="number" id="kdna_bg_seed" name="kdna_bg_seed" min="1" max="100" value="<?php echo esc_attr( $seed ); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Different seeds create different wave patterns.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_darken_top"><?php esc_html_e( 'Darken Top Edge', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="kdna_bg_darken_top" name="kdna_bg_darken_top" value="1" <?php checked( $darken, '1' ); ?> />
                        <?php esc_html_e( 'Adds a subtle shadow at the top of the canvas', 'kdna-backgrounds' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /* ── Glass refraction meta box ── */
    public function render_glass_box( $post ) {
        $glass_type = get_post_meta( $post->ID, '_kdna_bg_glass_type', true );
        $strength   = get_post_meta( $post->ID, '_kdna_bg_refract_strength', true );
        $scale      = get_post_meta( $post->ID, '_kdna_bg_refract_scale', true );
        $speed      = get_post_meta( $post->ID, '_kdna_bg_refract_speed', true );
        $rib_count  = get_post_meta( $post->ID, '_kdna_bg_rib_count', true );
        $rib_angle  = get_post_meta( $post->ID, '_kdna_bg_rib_angle', true );

        $glass_type = '' !== $glass_type ? $glass_type : 'none';
        $strength   = '' !== $strength ? floatval( $strength ) : 0;
        $scale      = '' !== $scale ? floatval( $scale ) : 12;
        $speed      = '' !== $speed ? floatval( $speed ) : 5;
        $rib_count  = '' !== $rib_count ? intval( $rib_count ) : 40;
        $rib_angle  = '' !== $rib_angle ? floatval( $rib_angle ) : 90;
        ?>
        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e( 'Optionally warp the gradient so it looks like it is viewed through textured glass. Choose a glass type, then set the refraction strength. Leave strength at 0 (or glass type None) to turn the effect off entirely.', 'kdna-backgrounds' ); ?>
        </p>
        <table class="form-table kdna-bg-settings-table">
            <tr>
                <th><label for="kdna_bg_glass_type"><?php esc_html_e( 'Glass Type', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <select id="kdna_bg_glass_type" name="kdna_bg_glass_type">
                        <option value="none" <?php selected( $glass_type, 'none' ); ?>><?php esc_html_e( 'None', 'kdna-backgrounds' ); ?></option>
                        <option value="liquid" <?php selected( $glass_type, 'liquid' ); ?>><?php esc_html_e( 'Liquid (organic ripples)', 'kdna-backgrounds' ); ?></option>
                        <option value="fluted" <?php selected( $glass_type, 'fluted' ); ?>><?php esc_html_e( 'Fluted (regular ribs)', 'kdna-backgrounds' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Liquid is random water-like rippling. Fluted is evenly spaced reeded-glass ribs.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>

            <!-- Shared: refraction strength (shown for Liquid and Fluted) -->
            <tr class="kdna-bg-glass-row" data-glass-group="liquid fluted">
                <th><label for="kdna_bg_refract_strength"><?php esc_html_e( 'Refraction Strength', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_refract_strength" name="kdna_bg_refract_strength" min="0" max="100" step="1" value="<?php echo esc_attr( $strength ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-refract-strength-val"><?php echo esc_html( $strength ); ?></span>
                    <p class="description"><?php esc_html_e( 'How far the colours are bent. 0 turns the effect off (no performance cost).', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>

            <!-- Liquid only -->
            <tr class="kdna-bg-glass-row" data-glass-group="liquid">
                <th><label for="kdna_bg_refract_scale"><?php esc_html_e( 'Ripple Scale', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_refract_scale" name="kdna_bg_refract_scale" min="1" max="50" step="1" value="<?php echo esc_attr( $scale ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-refract-scale-val"><?php echo esc_html( $scale ); ?></span>
                    <p class="description"><?php esc_html_e( 'Size of the ripples. Low = fine texture, high = broad waves.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="liquid">
                <th><label for="kdna_bg_refract_speed"><?php esc_html_e( 'Ripple Speed', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_refract_speed" name="kdna_bg_refract_speed" min="0" max="20" step="1" value="<?php echo esc_attr( $speed ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-refract-speed-val"><?php echo esc_html( $speed ); ?></span>
                    <p class="description"><?php esc_html_e( 'How fast the ripples move. The ripples drift independently of the colour animation.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>

            <!-- Fluted only -->
            <tr class="kdna-bg-glass-row" data-glass-group="fluted">
                <th><label for="kdna_bg_rib_count"><?php esc_html_e( 'Rib Count', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_count" name="kdna_bg_rib_count" min="10" max="120" step="1" value="<?php echo esc_attr( $rib_count ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-count-val"><?php echo esc_html( $rib_count ); ?></span>
                    <p class="description"><?php esc_html_e( 'How many ribs run across the container.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted">
                <th><label for="kdna_bg_rib_angle"><?php esc_html_e( 'Rib Angle', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_angle" name="kdna_bg_rib_angle" min="0" max="180" step="1" value="<?php echo esc_attr( $rib_angle ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-angle-val"><?php echo esc_html( $rib_angle ); ?></span>
                    <p class="description"><?php esc_html_e( 'Rib orientation in degrees. 0 = horizontal ribs, 90 = vertical ribs, any angle in between works. Fluted ribs are static, the gradient still animates behind them.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /* ── Preview meta box ── */
    public function render_preview_box( $post ) {
        ?>
        <div id="kdna-bg-preview-container">
            <canvas id="kdna-bg-preview-canvas"></canvas>
        </div>
        <p class="description" style="margin-top:8px;text-align:center;"><?php esc_html_e( 'Preview updates live as you change settings.', 'kdna-backgrounds' ); ?></p>
        <?php
    }

    /* ── Save ── */
    public function save( $post_id, $post ) {

        if ( ! isset( $_POST['kdna_bg_nonce'] ) || ! wp_verify_nonce( $_POST['kdna_bg_nonce'], 'kdna_bg_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        /* Colours */
        $colours = array();
        if ( isset( $_POST['kdna_bg_colours'] ) && is_array( $_POST['kdna_bg_colours'] ) ) {
            foreach ( array_slice( $_POST['kdna_bg_colours'], 0, 10 ) as $hex ) {
                $sanitised = sanitize_hex_color( $hex );
                if ( $sanitised ) {
                    $colours[] = $sanitised;
                }
            }
        }
        if ( empty( $colours ) ) {
            $colours = array( '#0a2463', '#1e6bff', '#3d8bff' );
        }
        update_post_meta( $post_id, '_kdna_bg_colours', $colours );

        /* Speed */
        $speed = isset( $_POST['kdna_bg_speed'] ) ? floatval( $_POST['kdna_bg_speed'] ) : 5;
        update_post_meta( $post_id, '_kdna_bg_speed', max( 1, min( 20, $speed ) ) );

        /* Amplitude */
        $amp = isset( $_POST['kdna_bg_amplitude'] ) ? intval( $_POST['kdna_bg_amplitude'] ) : 100;
        update_post_meta( $post_id, '_kdna_bg_amplitude', max( 10, min( 500, $amp ) ) );

        /* Density */
        $density = isset( $_POST['kdna_bg_density'] ) ? floatval( $_POST['kdna_bg_density'] ) : 6;
        update_post_meta( $post_id, '_kdna_bg_density', max( 2, min( 12, $density ) ) );

        /* Seed */
        $seed = isset( $_POST['kdna_bg_seed'] ) ? intval( $_POST['kdna_bg_seed'] ) : 5;
        update_post_meta( $post_id, '_kdna_bg_seed', max( 1, min( 100, $seed ) ) );

        /* Darken top */
        update_post_meta( $post_id, '_kdna_bg_darken_top', isset( $_POST['kdna_bg_darken_top'] ) ? '1' : '0' );

        /* Glass refraction */
        $glass_type = isset( $_POST['kdna_bg_glass_type'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_bg_glass_type'] ) ) : 'none';
        if ( ! in_array( $glass_type, array( 'none', 'liquid', 'fluted' ), true ) ) {
            $glass_type = 'none';
        }
        update_post_meta( $post_id, '_kdna_bg_glass_type', $glass_type );

        $refract_strength = isset( $_POST['kdna_bg_refract_strength'] ) ? floatval( $_POST['kdna_bg_refract_strength'] ) : 0;
        update_post_meta( $post_id, '_kdna_bg_refract_strength', max( 0, min( 100, $refract_strength ) ) );

        $refract_scale = isset( $_POST['kdna_bg_refract_scale'] ) ? floatval( $_POST['kdna_bg_refract_scale'] ) : 12;
        update_post_meta( $post_id, '_kdna_bg_refract_scale', max( 1, min( 50, $refract_scale ) ) );

        $refract_speed = isset( $_POST['kdna_bg_refract_speed'] ) ? floatval( $_POST['kdna_bg_refract_speed'] ) : 5;
        update_post_meta( $post_id, '_kdna_bg_refract_speed', max( 0, min( 20, $refract_speed ) ) );

        $rib_count = isset( $_POST['kdna_bg_rib_count'] ) ? intval( $_POST['kdna_bg_rib_count'] ) : 40;
        update_post_meta( $post_id, '_kdna_bg_rib_count', max( 10, min( 120, $rib_count ) ) );

        $rib_angle = isset( $_POST['kdna_bg_rib_angle'] ) ? floatval( $_POST['kdna_bg_rib_angle'] ) : 90;
        update_post_meta( $post_id, '_kdna_bg_rib_angle', max( 0, min( 180, $rib_angle ) ) );
    }
}

new KDNA_BG_Meta();
