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
            'kdna_bg_shapes',
            __( 'Colour Shapes', 'kdna-backgrounds' ),
            array( $this, 'render_shapes_box' ),
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

    /* ── Colour shapes meta box ── */
    public function render_shapes_box( $post ) {
        $shape_style = get_post_meta( $post->ID, '_kdna_bg_shape_style', true );
        $dominant_bg = get_post_meta( $post->ID, '_kdna_bg_dominant_bg', true );
        $stretch     = get_post_meta( $post->ID, '_kdna_bg_stretch', true );
        $radiate     = get_post_meta( $post->ID, '_kdna_bg_radiate', true );
        $ring_count  = get_post_meta( $post->ID, '_kdna_bg_ring_count', true );
        $shape_count = get_post_meta( $post->ID, '_kdna_bg_shape_count', true );
        $color_blend = get_post_meta( $post->ID, '_kdna_bg_color_blend', true );
        $drift       = get_post_meta( $post->ID, '_kdna_bg_drift', true );
        $band_min    = get_post_meta( $post->ID, '_kdna_bg_band_min', true );
        $band_max    = get_post_meta( $post->ID, '_kdna_bg_band_max', true );
        $band_vary   = get_post_meta( $post->ID, '_kdna_bg_band_vary', true );
        $band_move   = get_post_meta( $post->ID, '_kdna_bg_band_move', true );
        $band_fade   = get_post_meta( $post->ID, '_kdna_bg_band_fade', true );
        $band_fade_v = get_post_meta( $post->ID, '_kdna_bg_band_fade_var', true );
        $band_bg     = get_post_meta( $post->ID, '_kdna_bg_band_bg_colour', true );
        $grain       = get_post_meta( $post->ID, '_kdna_bg_grain', true );
        $sheen       = get_post_meta( $post->ID, '_kdna_bg_sheen', true );
        $flow_amount = get_post_meta( $post->ID, '_kdna_bg_flow_amount', true );
        $flow_angle  = get_post_meta( $post->ID, '_kdna_bg_flow_angle', true );
        $definition  = get_post_meta( $post->ID, '_kdna_bg_definition', true );
        $spread      = get_post_meta( $post->ID, '_kdna_bg_spread', true );

        $shape_style = '' !== $shape_style ? $shape_style : 'wash';
        $dominant_bg = '' !== $dominant_bg ? $dominant_bg : '0';
        $stretch     = '' !== $stretch ? floatval( $stretch ) : 0;
        $radiate     = '' !== $radiate ? floatval( $radiate ) : 45;
        $ring_count  = '' !== $ring_count ? floatval( $ring_count ) : 1;
        $shape_count = '' !== $shape_count ? floatval( $shape_count ) : 1;
        $color_blend = '' !== $color_blend ? floatval( $color_blend ) : 70;
        $drift       = '' !== $drift ? floatval( $drift ) : 40;
        $band_min    = '' !== $band_min ? floatval( $band_min ) : 25;
        $band_max    = '' !== $band_max ? floatval( $band_max ) : 60;
        $band_vary   = '' !== $band_vary ? floatval( $band_vary ) : 50;
        $band_move   = '' !== $band_move ? floatval( $band_move ) : 40;
        $band_fade   = '' !== $band_fade ? floatval( $band_fade ) : 0;
        $band_fade_v = '' !== $band_fade_v ? floatval( $band_fade_v ) : 50;
        $band_bg     = '' !== $band_bg ? $band_bg : '#0a0a14';
        $grain       = '' !== $grain ? floatval( $grain ) : 0;
        $sheen       = '' !== $sheen ? floatval( $sheen ) : 0;
        $flow_amount = '' !== $flow_amount ? floatval( $flow_amount ) : 0;
        $flow_angle  = '' !== $flow_angle ? floatval( $flow_angle ) : 0;
        $definition  = '' !== $definition ? floatval( $definition ) : 40;
        $spread      = '' !== $spread ? floatval( $spread ) : 50;
        ?>
        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e( 'Control how the colours are distributed. Colour 1 is always the background. In Wash style the other colours blend all over; in Concentric style colour 1 stays as the dark background and the other colours blend through smooth concentric rings that radiate outward and loop, on one or more shapes that drift slowly around the canvas (set colour 1 to near-black for the moody billboard look). Flow Amount adds a gentle swirl, Flow Angle aims the elongation, Colour Spread sets the size, and Shape Definition sets the outer fade.', 'kdna-backgrounds' ); ?>
        </p>
        <table class="form-table kdna-bg-settings-table">
            <tr>
                <th><label for="kdna_bg_shape_style"><?php esc_html_e( 'Shape Style', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <select id="kdna_bg_shape_style" name="kdna_bg_shape_style">
                        <option value="wash" <?php selected( $shape_style, 'wash' ); ?>><?php esc_html_e( 'Wash (even all-over blend)', 'kdna-backgrounds' ); ?></option>
                        <option value="concentric" <?php selected( $shape_style, 'concentric' ); ?>><?php esc_html_e( 'Concentric (animated radiating rings)', 'kdna-backgrounds' ); ?></option>
                        <option value="bands" <?php selected( $shape_style, 'bands' ); ?>><?php esc_html_e( 'Bands (wavy perspective fan)', 'kdna-backgrounds' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Wash is the standard all-over blend. Concentric keeps colour 1 as the dark background and sends the other colours out as smooth rings that radiate and loop, on one or more drifting shapes. Bands paints the colours as a wavy fan of bands seen in perspective (tighter on one side, splayed on the other), separated by the dark Background Colour. Within each band the colours radiate out from its centre-line to its edges and animate, and each band glows softly into the gap beside it. Use Flow Angle to rotate the fan, Perspective for the fan strength, Waviness for the undulation, Band Thickness for how far apart the bands sit, Colour Repeats for the colour steps, Colour Blend for softness, and Radiate Speed for the radiate.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_dominant_bg"><?php esc_html_e( 'Dominant Background', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="kdna_bg_dominant_bg" name="kdna_bg_dominant_bg" value="1" <?php checked( $dominant_bg, '1' ); ?> />
                        <?php esc_html_e( 'Make colour 1 the dominant background', 'kdna-backgrounds' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'When on, colour 1 fills most of the canvas and the other colours are concentrated into shapes, for a moody look with lots of dark negative space. Set colour 1 to near-black for the billboard effect.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric">
                <th><label for="kdna_bg_stretch"><?php esc_html_e( 'Shape Stretch', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_stretch" name="kdna_bg_stretch" min="0" max="100" step="1" value="<?php echo esc_attr( $stretch ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-stretch-val"><?php echo esc_html( $stretch ); ?></span>
                    <p class="description"><?php esc_html_e( 'How much the rings are stretched into ovals along the Flow Angle. 0 = round, high = strongly elongated. When animating, the rings breathe in and out up to this amount. (Concentric style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric">
                <th><label for="kdna_bg_shape_count"><?php esc_html_e( 'Shape Count', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_shape_count" name="kdna_bg_shape_count" min="1" max="4" step="1" value="<?php echo esc_attr( $shape_count ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-shape-count-val"><?php echo esc_html( $shape_count ); ?></span>
                    <p class="description"><?php esc_html_e( 'How many separate concentric ring-shapes appear on the canvas. 1 is a single shape; higher places extra shapes in different parts of the screen, each smaller, with its own random angle and slow drift. They stay apart most of the time and only touch (and blend) now and then as they move. (Concentric style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric bands">
                <th><label for="kdna_bg_ring_count"><?php esc_html_e( 'Colour Repeats', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_ring_count" name="kdna_bg_ring_count" min="1" max="6" step="1" value="<?php echo esc_attr( $ring_count ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-ring-count-val"><?php echo esc_html( $ring_count ); ?></span>
                    <p class="description"><?php esc_html_e( 'In Concentric, how many times the colour set repeats within one shape as it radiates out. In Bands, how many colour steps radiate from the centre-line of each band to its edge (1 = one colour emerging at the centre and one on the outside).', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_move"><?php esc_html_e( 'Band Movement', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_move" name="kdna_bg_band_move" min="0" max="100" step="1" value="<?php echo esc_attr( $band_move ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-move-val"><?php echo esc_html( $band_move ); ?></span>
                    <p class="description"><?php esc_html_e( 'How fast the whole fan of bands travels across the canvas. 0 = the bands hold still; higher = they sweep across faster. (This is separate from Radiate Speed, which moves the colours within the bands.) (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_fade"><?php esc_html_e( 'Transparency', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_fade" name="kdna_bg_band_fade" min="0" max="100" step="1" value="<?php echo esc_attr( $band_fade ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-fade-val"><?php echo esc_html( $band_fade ); ?></span>
                    <p class="description"><?php esc_html_e( 'Softly fades parts of the bands away to the Background Colour, blended gently over many pixels like the Wash style. 0 = solid bands; higher = more of the colour fades out. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_fade_var"><?php esc_html_e( 'Transparency Patchiness', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_fade_var" name="kdna_bg_band_fade_var" min="0" max="100" step="1" value="<?php echo esc_attr( $band_fade_v ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-fade-var-val"><?php echo esc_html( $band_fade_v ); ?></span>
                    <p class="description"><?php esc_html_e( 'How the fading is spread. Low = large, even, uniform fades; high = smaller, more random patches of transparency. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_bg_colour"><?php esc_html_e( 'Background Colour', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="text" id="kdna_bg_band_bg_colour" name="kdna_bg_band_bg_colour" class="kdna-bg-single-colour" value="<?php echo esc_attr( $band_bg ); ?>" data-default-color="#0a0a14" />
                    <p class="description"><?php esc_html_e( 'The colour that fills the whole canvas behind the bands. The band colours glow softly into it. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_min"><?php esc_html_e( 'Band Thickness', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_min" name="kdna_bg_band_min" min="1" max="100" step="1" value="<?php echo esc_attr( $band_min ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-min-val"><?php echo esc_html( $band_min ); ?></span>
                    <p class="description"><?php esc_html_e( 'How wide the bands are versus the dark gaps between them. Low = thin bands with lots of background showing through; high = fat bands that nearly touch. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_max"><?php esc_html_e( 'Waviness', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_max" name="kdna_bg_band_max" min="1" max="100" step="1" value="<?php echo esc_attr( $band_max ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-max-val"><?php echo esc_html( $band_max ); ?></span>
                    <p class="description"><?php esc_html_e( 'How much the bands wave and undulate as they fan out. 0 = clean straight fan; higher = wavy, flowing bands. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_vary"><?php esc_html_e( 'Perspective', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_vary" name="kdna_bg_band_vary" min="0" max="100" step="1" value="<?php echo esc_attr( $band_vary ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-vary-val"><?php echo esc_html( $band_vary ); ?></span>
                    <p class="description"><?php esc_html_e( 'How much the parallel bands converge toward one side, like perspective. 0 = perfectly parallel; higher = the bands fan and bunch toward one side. (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric bands">
                <th><label for="kdna_bg_color_blend"><?php esc_html_e( 'Colour Blend', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_color_blend" name="kdna_bg_color_blend" min="0" max="100" step="1" value="<?php echo esc_attr( $color_blend ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-color-blend-val"><?php echo esc_html( $color_blend ); ?></span>
                    <p class="description"><?php esc_html_e( 'How softly the colours melt into each other. Low = more defined rings with crisper colour edges; high = the colours blur and blend gently from one into the next. (Concentric style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric bands">
                <th><label for="kdna_bg_radiate"><?php esc_html_e( 'Radiate Speed', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_radiate" name="kdna_bg_radiate" min="0" max="100" step="1" value="<?php echo esc_attr( $radiate ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-radiate-val"><?php echo esc_html( $radiate ); ?></span>
                    <p class="description"><?php esc_html_e( 'Movement speed. In Concentric it is how fast the colours travel outward in a loop; in Bands it is how fast the bands migrate across the screen. Tuned to a slow, smooth range so it is never frantic. 0 = still. (Concentric and Bands styles.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="concentric">
                <th><label for="kdna_bg_drift"><?php esc_html_e( 'Movement', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_drift" name="kdna_bg_drift" min="0" max="100" step="1" value="<?php echo esc_attr( $drift ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-drift-val"><?php echo esc_html( $drift ); ?></span>
                    <p class="description"><?php esc_html_e( 'How far the shapes slowly drift around the canvas, moving off one edge and back on like the original gradient. 0 = held in place; higher = wanders further off-screen and back. (Concentric style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_flow_amount"><?php esc_html_e( 'Flow Amount', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_flow_amount" name="kdna_bg_flow_amount" min="0" max="100" step="1" value="<?php echo esc_attr( $flow_amount ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-flow-amount-val"><?php echo esc_html( $flow_amount ); ?></span>
                    <p class="description"><?php esc_html_e( 'How much the colours swirl and flow. 0 = round even blobs (the original look), high = strong marbled, flowing forms.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_flow_angle"><?php esc_html_e( 'Flow Angle', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_flow_angle" name="kdna_bg_flow_angle" min="0" max="360" step="1" value="<?php echo esc_attr( $flow_angle ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-flow-angle-val"><?php echo esc_html( $flow_angle ); ?></span>
                    <p class="description"><?php esc_html_e( 'Direction in degrees (0 to 360), so the look can run on a diagonal. In Bands it sets the angle of the bands; in Concentric it aims the elongation; in Wash it aims the flow (only when Flow Amount is above 0).', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_definition"><?php esc_html_e( 'Shape Definition', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_definition" name="kdna_bg_definition" min="0" max="100" step="1" value="<?php echo esc_attr( $definition ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-definition-val"><?php echo esc_html( $definition ); ?></span>
                    <p class="description"><?php esc_html_e( 'Edge softness of each colour. Low = soft all-over wash, high = sharp, defined ribbons of colour against the base.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_spread"><?php esc_html_e( 'Colour Spread', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_spread" name="kdna_bg_spread" min="0" max="100" step="1" value="<?php echo esc_attr( $spread ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-spread-val"><?php echo esc_html( $spread ); ?></span>
                    <p class="description"><?php esc_html_e( 'How much of the canvas the colours cover. Low = small concentrated shapes with lots of dark negative space, high = colours fill most of the canvas.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_sheen"><?php esc_html_e( 'Sheen', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_sheen" name="kdna_bg_sheen" min="0" max="100" step="1" value="<?php echo esc_attr( $sheen ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-sheen-val"><?php echo esc_html( $sheen ); ?></span>
                    <p class="description"><?php esc_html_e( 'Adds a slow, flowing brightness variation so the colours catch the light like satin or fabric. 0 = flat, smooth colour; higher = stronger light and shade. (Works in all styles.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="kdna_bg_grain"><?php esc_html_e( 'Grain', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_grain" name="kdna_bg_grain" min="0" max="100" step="1" value="<?php echo esc_attr( $grain ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-grain-val"><?php echo esc_html( $grain ); ?></span>
                    <p class="description"><?php esc_html_e( 'Adds a fine film-grain speckle over the whole background for a textured, printed feel. 0 = clean; higher = grainier. (Works in all styles.)', 'kdna-backgrounds' ); ?></p>
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
        $diamond_a  = get_post_meta( $post->ID, '_kdna_bg_diamond_angle', true );
        $light_move = get_post_meta( $post->ID, '_kdna_bg_light_move', true );
        $rib_sharp  = get_post_meta( $post->ID, '_kdna_bg_rib_sharp', true );
        $bevel_w    = get_post_meta( $post->ID, '_kdna_bg_bevel_width', true );
        $rib_hi_w   = get_post_meta( $post->ID, '_kdna_bg_rib_hi_width', true );
        $rib_hi_s   = get_post_meta( $post->ID, '_kdna_bg_rib_hi_strength', true );
        $rib_sh_w   = get_post_meta( $post->ID, '_kdna_bg_rib_sh_width', true );
        $rib_sh_s   = get_post_meta( $post->ID, '_kdna_bg_rib_sh_strength', true );

        $glass_type = '' !== $glass_type ? $glass_type : 'none';
        $strength   = '' !== $strength ? floatval( $strength ) : 0;
        $scale      = '' !== $scale ? floatval( $scale ) : 12;
        $speed      = '' !== $speed ? floatval( $speed ) : 5;
        $rib_count  = '' !== $rib_count ? intval( $rib_count ) : 40;
        $rib_angle  = '' !== $rib_angle ? floatval( $rib_angle ) : 90;
        $diamond_a  = '' !== $diamond_a ? floatval( $diamond_a ) : 45;
        $light_move = '' !== $light_move ? floatval( $light_move ) : 30;
        $rib_sharp  = '' !== $rib_sharp ? floatval( $rib_sharp ) : 0;
        $bevel_w    = '' !== $bevel_w ? floatval( $bevel_w ) : 50;
        $rib_hi_w   = '' !== $rib_hi_w ? floatval( $rib_hi_w ) : 25;
        $rib_hi_s   = '' !== $rib_hi_s ? floatval( $rib_hi_s ) : 40;
        $rib_sh_w   = '' !== $rib_sh_w ? floatval( $rib_sh_w ) : 50;
        $rib_sh_s   = '' !== $rib_sh_s ? floatval( $rib_sh_s ) : 60;
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
                        <option value="diamond" <?php selected( $glass_type, 'diamond' ); ?>><?php esc_html_e( 'Diamond (reflective glass tiles)', 'kdna-backgrounds' ); ?></option>
                        <option value="hexagon" <?php selected( $glass_type, 'hexagon' ); ?>><?php esc_html_e( 'Hexagon (reflective honeycomb)', 'kdna-backgrounds' ); ?></option>
                        <option value="organic" <?php selected( $glass_type, 'organic' ); ?>><?php esc_html_e( 'Organic / Leather (random scales)', 'kdna-backgrounds' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Liquid is random water-like rippling. Fluted is evenly spaced reeded-glass ribs. Diamond, Hexagon and Organic are beveled glass tiles (square, honeycomb, or irregular leather-like scales) that reflect the gradient through each cell.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>

            <!-- Shared: refraction strength (shown for Liquid and Fluted) -->
            <tr class="kdna-bg-glass-row" data-glass-group="liquid fluted diamond hexagon organic">
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
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond hexagon organic">
                <th><label for="kdna_bg_rib_count"><?php esc_html_e( 'Rib Count', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_count" name="kdna_bg_rib_count" min="10" max="120" step="1" value="<?php echo esc_attr( $rib_count ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-count-val"><?php echo esc_html( $rib_count ); ?></span>
                    <p class="description"><?php esc_html_e( 'How many ribs run across the container.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond">
                <th><label for="kdna_bg_rib_angle"><?php esc_html_e( 'Rib Angle', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_angle" name="kdna_bg_rib_angle" min="0" max="180" step="1" value="<?php echo esc_attr( $rib_angle ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-angle-val"><?php echo esc_html( $rib_angle ); ?></span>
                    <p class="description"><?php esc_html_e( 'Rib orientation in degrees. 0 = horizontal ribs, 90 = vertical ribs, any angle in between works. Fluted ribs are static, the gradient still animates behind them.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="diamond">
                <th><label for="kdna_bg_diamond_angle"><?php esc_html_e( 'Side Angle', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_diamond_angle" name="kdna_bg_diamond_angle" min="20" max="70" step="1" value="<?php echo esc_attr( $diamond_a ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-diamond-angle-val"><?php echo esc_html( $diamond_a ); ?></span>
                    <p class="description"><?php esc_html_e( 'The angle of the diamond sides, in degrees. 45 = square diamonds; higher (50-60) makes them taller than wide; lower makes them wider than tall. (Diamond style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="diamond hexagon organic">
                <th><label for="kdna_bg_light_move"><?php esc_html_e( 'Light Movement', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_light_move" name="kdna_bg_light_move" min="0" max="100" step="1" value="<?php echo esc_attr( $light_move ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-light-move-val"><?php echo esc_html( $light_move ); ?></span>
                    <p class="description"><?php esc_html_e( 'Animates the light source so the highlights sweep across the diamonds as if a light is moving over the surface. 0 = a fixed light; higher = faster movement. (Diamond style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond">
                <th><label for="kdna_bg_rib_sharp"><?php esc_html_e( 'Rib Sharpness', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_sharp" name="kdna_bg_rib_sharp" min="0" max="100" step="1" value="<?php echo esc_attr( $rib_sharp ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-sharp-val"><?php echo esc_html( $rib_sharp ); ?></span>
                    <p class="description"><?php esc_html_e( 'How the flute is shaped. 0 = soft, rounded flutes; higher flattens them toward flat facets with a near-linear lens ramp. (The brightness of the ribs is set by the Highlight and Shadow sliders below.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="hexagon organic">
                <th><label for="kdna_bg_bevel_width"><?php esc_html_e( 'Bevel Width', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_bevel_width" name="kdna_bg_bevel_width" min="0" max="100" step="1" value="<?php echo esc_attr( $bevel_w ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-bevel-width-val"><?php echo esc_html( $bevel_w ); ?></span>
                    <p class="description"><?php esc_html_e( 'How wide the sloped bevel ring is, from each cell edge in toward its centre. Low = a thin bevel around a large flat top (flat honeycomb with a bright rim); high = a wide bevel that fills the cell into a full rounded dome. Pair with Refraction Strength (how much it raises) and Highlight Strength (rim brightness) to dial in the look.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond">
                <th><label for="kdna_bg_rib_hi_width"><?php esc_html_e( 'Highlight Width', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_hi_width" name="kdna_bg_rib_hi_width" min="0" max="100" step="1" value="<?php echo esc_attr( $rib_hi_w ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-hi-width-val"><?php echo esc_html( $rib_hi_w ); ?></span>
                    <p class="description"><?php esc_html_e( 'How far the highlight on the right side of each flute reaches in from the right edge. Low = a thin glint at the edge, 100% = a gradient across the whole flute.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond hexagon organic">
                <th><label for="kdna_bg_rib_hi_strength"><?php esc_html_e( 'Highlight Strength', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_hi_strength" name="kdna_bg_rib_hi_strength" min="0" max="100" step="1" value="<?php echo esc_attr( $rib_hi_s ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-hi-strength-val"><?php echo esc_html( $rib_hi_s ); ?></span>
                    <p class="description"><?php esc_html_e( 'How bright the right-side highlight gets. 0 = none. The highlight scales with the colour behind the glass, so it stays invisible over black.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond">
                <th><label for="kdna_bg_rib_sh_width"><?php esc_html_e( 'Shadow Width', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_sh_width" name="kdna_bg_rib_sh_width" min="0" max="100" step="1" value="<?php echo esc_attr( $rib_sh_w ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-sh-width-val"><?php echo esc_html( $rib_sh_w ); ?></span>
                    <p class="description"><?php esc_html_e( 'How far the shadow on the left side of each flute reaches in from the left edge. Low = a crisp thin line at the edge, 100% = the shadow fades all the way from the left edge across to the right.', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-glass-row" data-glass-group="fluted diamond hexagon organic">
                <th><label for="kdna_bg_rib_sh_strength"><?php esc_html_e( 'Shadow Strength', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_rib_sh_strength" name="kdna_bg_rib_sh_strength" min="0" max="100" step="1" value="<?php echo esc_attr( $rib_sh_s ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-rib-sh-strength-val"><?php echo esc_html( $rib_sh_s ); ?></span>
                    <p class="description"><?php esc_html_e( 'How dark the left-side shadow gets. 0 = none. Like the highlight, it scales with the colour behind the glass, so it disappears over black.', 'kdna-backgrounds' ); ?></p>
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
        if ( ! in_array( $glass_type, array( 'none', 'liquid', 'fluted', 'diamond', 'hexagon', 'organic' ), true ) ) {
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

        $diamond_a = isset( $_POST['kdna_bg_diamond_angle'] ) ? floatval( $_POST['kdna_bg_diamond_angle'] ) : 45;
        update_post_meta( $post_id, '_kdna_bg_diamond_angle', max( 20, min( 70, $diamond_a ) ) );

        $light_move = isset( $_POST['kdna_bg_light_move'] ) ? floatval( $_POST['kdna_bg_light_move'] ) : 30;
        update_post_meta( $post_id, '_kdna_bg_light_move', max( 0, min( 100, $light_move ) ) );

        $rib_sharp = isset( $_POST['kdna_bg_rib_sharp'] ) ? floatval( $_POST['kdna_bg_rib_sharp'] ) : 0;
        update_post_meta( $post_id, '_kdna_bg_rib_sharp', max( 0, min( 100, $rib_sharp ) ) );

        $bevel_width = isset( $_POST['kdna_bg_bevel_width'] ) ? floatval( $_POST['kdna_bg_bevel_width'] ) : 50;
        update_post_meta( $post_id, '_kdna_bg_bevel_width', max( 0, min( 100, $bevel_width ) ) );

        update_post_meta( $post_id, '_kdna_bg_rib_hi_width', isset( $_POST['kdna_bg_rib_hi_width'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_rib_hi_width'] ) ) ) : 25 );
        update_post_meta( $post_id, '_kdna_bg_rib_hi_strength', isset( $_POST['kdna_bg_rib_hi_strength'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_rib_hi_strength'] ) ) ) : 40 );
        update_post_meta( $post_id, '_kdna_bg_rib_sh_width', isset( $_POST['kdna_bg_rib_sh_width'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_rib_sh_width'] ) ) ) : 50 );
        update_post_meta( $post_id, '_kdna_bg_rib_sh_strength', isset( $_POST['kdna_bg_rib_sh_strength'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_rib_sh_strength'] ) ) ) : 60 );

        /* Colour shapes */
        $shape_style = isset( $_POST['kdna_bg_shape_style'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_bg_shape_style'] ) ) : 'wash';
        if ( ! in_array( $shape_style, array( 'wash', 'concentric', 'bands' ), true ) ) {
            $shape_style = 'wash';
        }
        update_post_meta( $post_id, '_kdna_bg_shape_style', $shape_style );

        update_post_meta( $post_id, '_kdna_bg_dominant_bg', isset( $_POST['kdna_bg_dominant_bg'] ) ? '1' : '0' );

        update_post_meta( $post_id, '_kdna_bg_radiate', isset( $_POST['kdna_bg_radiate'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_radiate'] ) ) ) : 45 );
        update_post_meta( $post_id, '_kdna_bg_ring_count', isset( $_POST['kdna_bg_ring_count'] ) ? max( 1, min( 6, floatval( $_POST['kdna_bg_ring_count'] ) ) ) : 1 );
        update_post_meta( $post_id, '_kdna_bg_shape_count', isset( $_POST['kdna_bg_shape_count'] ) ? max( 1, min( 4, floatval( $_POST['kdna_bg_shape_count'] ) ) ) : 1 );
        update_post_meta( $post_id, '_kdna_bg_color_blend', isset( $_POST['kdna_bg_color_blend'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_color_blend'] ) ) ) : 70 );
        update_post_meta( $post_id, '_kdna_bg_drift', isset( $_POST['kdna_bg_drift'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_drift'] ) ) ) : 40 );

        $stretch = isset( $_POST['kdna_bg_stretch'] ) ? floatval( $_POST['kdna_bg_stretch'] ) : 0;
        update_post_meta( $post_id, '_kdna_bg_stretch', max( 0, min( 100, $stretch ) ) );

        $flow_amount = isset( $_POST['kdna_bg_flow_amount'] ) ? floatval( $_POST['kdna_bg_flow_amount'] ) : 0;
        update_post_meta( $post_id, '_kdna_bg_flow_amount', max( 0, min( 100, $flow_amount ) ) );

        $flow_angle = isset( $_POST['kdna_bg_flow_angle'] ) ? floatval( $_POST['kdna_bg_flow_angle'] ) : 0;
        update_post_meta( $post_id, '_kdna_bg_flow_angle', max( 0, min( 360, $flow_angle ) ) );

        $definition = isset( $_POST['kdna_bg_definition'] ) ? floatval( $_POST['kdna_bg_definition'] ) : 40;
        update_post_meta( $post_id, '_kdna_bg_definition', max( 0, min( 100, $definition ) ) );

        $spread = isset( $_POST['kdna_bg_spread'] ) ? floatval( $_POST['kdna_bg_spread'] ) : 50;
        update_post_meta( $post_id, '_kdna_bg_spread', max( 0, min( 100, $spread ) ) );

        update_post_meta( $post_id, '_kdna_bg_band_min', isset( $_POST['kdna_bg_band_min'] ) ? max( 1, min( 100, floatval( $_POST['kdna_bg_band_min'] ) ) ) : 25 );
        update_post_meta( $post_id, '_kdna_bg_band_max', isset( $_POST['kdna_bg_band_max'] ) ? max( 1, min( 100, floatval( $_POST['kdna_bg_band_max'] ) ) ) : 60 );
        update_post_meta( $post_id, '_kdna_bg_band_vary', isset( $_POST['kdna_bg_band_vary'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_band_vary'] ) ) ) : 50 );
        update_post_meta( $post_id, '_kdna_bg_band_move', isset( $_POST['kdna_bg_band_move'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_band_move'] ) ) ) : 40 );
        update_post_meta( $post_id, '_kdna_bg_band_fade', isset( $_POST['kdna_bg_band_fade'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_band_fade'] ) ) ) : 0 );
        update_post_meta( $post_id, '_kdna_bg_band_fade_var', isset( $_POST['kdna_bg_band_fade_var'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_band_fade_var'] ) ) ) : 50 );

        $band_bg = isset( $_POST['kdna_bg_band_bg_colour'] ) ? sanitize_hex_color( $_POST['kdna_bg_band_bg_colour'] ) : '';
        update_post_meta( $post_id, '_kdna_bg_band_bg_colour', $band_bg ? $band_bg : '#0a0a14' );
        update_post_meta( $post_id, '_kdna_bg_sheen', isset( $_POST['kdna_bg_sheen'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_sheen'] ) ) ) : 0 );
        update_post_meta( $post_id, '_kdna_bg_grain', isset( $_POST['kdna_bg_grain'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_grain'] ) ) ) : 0 );
    }
}

new KDNA_BG_Meta();
