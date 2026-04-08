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

    /* ── Preview meta box ── */
    public function render_preview_box( $post ) {
        $colours   = get_post_meta( $post->ID, '_kdna_bg_colours', true );
        $speed     = get_post_meta( $post->ID, '_kdna_bg_speed', true );
        $amplitude = get_post_meta( $post->ID, '_kdna_bg_amplitude', true );
        $density   = get_post_meta( $post->ID, '_kdna_bg_density', true );
        $seed      = get_post_meta( $post->ID, '_kdna_bg_seed', true );
        $darken    = get_post_meta( $post->ID, '_kdna_bg_darken_top', true );

        if ( empty( $colours ) || ! is_array( $colours ) ) {
            $colours = array( '#0a2463', '#1e6bff', '#3d8bff' );
        }

        $preview_config = array(
            'colours'   => $colours,
            'speed'     => '' !== $speed ? floatval( $speed ) : 5,
            'amplitude' => '' !== $amplitude ? intval( $amplitude ) : 320,
            'density'   => '' !== $density ? floatval( $density ) : 6,
            'seed'      => '' !== $seed ? intval( $seed ) : 5,
            'darkenTop' => '1' === $darken,
        );
        ?>
        <div id="kdna-bg-preview-container">
            <canvas id="kdna-bg-preview-canvas"></canvas>
        </div>
        <p class="description" style="margin-top:8px;text-align:center;"><?php esc_html_e( 'Save/Update the post to refresh this preview.', 'kdna-backgrounds' ); ?></p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.KDNAGradientEngine === 'undefined') return;
                var cfg = <?php echo wp_json_encode( $preview_config ); ?>;
                var canvas = document.getElementById('kdna-bg-preview-canvas');
                if (!canvas) return;
                var gradient = window.KDNAGradientEngine.create(cfg);
                gradient.init(canvas);
            });
        </script>
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
    }
}

new KDNA_BG_Meta();
