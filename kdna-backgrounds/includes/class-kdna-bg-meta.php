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
                        <option value="bands" <?php selected( $shape_style, 'bands' ); ?>><?php esc_html_e( 'Bands (fanned, sweeping bands)', 'kdna-backgrounds' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Wash is the standard all-over blend. Concentric keeps colour 1 as the dark background and sends the other colours out as smooth rings that radiate and loop, on one or more drifting shapes. Bands paints the colours as soft bands that fan out from below the bottom edge (bunched at the bottom, splayed at the top) and slowly sweep, with the tops moving further than the bottoms; use Flow Angle to lean the fan and Radiate Speed for the sweep.', 'kdna-backgrounds' ); ?></p>
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
            <tr class="kdna-bg-shape-row" data-shape-group="concentric">
                <th><label for="kdna_bg_ring_count"><?php esc_html_e( 'Colour Repeats', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_ring_count" name="kdna_bg_ring_count" min="1" max="6" step="1" value="<?php echo esc_attr( $ring_count ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-ring-count-val"><?php echo esc_html( $ring_count ); ?></span>
                    <p class="description"><?php esc_html_e( 'How many times the colour set repeats within one shape as it radiates out. 1 = one ring per colour, higher = more, tighter rings. (Concentric style only; Bands uses Min/Max Width instead.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_min"><?php esc_html_e( 'Min Width', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_min" name="kdna_bg_band_min" min="1" max="100" step="1" value="<?php echo esc_attr( $band_min ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-min-val"><?php echo esc_html( $band_min ); ?></span>
                    <p class="description"><?php esc_html_e( 'The narrowest a band can be. Each band is given a random width between Min and Max, so the bands are irregular (some thin, some fat). (Bands style only.)', 'kdna-backgrounds' ); ?></p>
                </td>
            </tr>
            <tr class="kdna-bg-shape-row" data-shape-group="bands">
                <th><label for="kdna_bg_band_max"><?php esc_html_e( 'Max Width', 'kdna-backgrounds' ); ?></label></th>
                <td>
                    <input type="range" id="kdna_bg_band_max" name="kdna_bg_band_max" min="1" max="100" step="1" value="<?php echo esc_attr( $band_max ); ?>" />
                    <span class="kdna-bg-range-value" id="kdna-bg-band-max-val"><?php echo esc_html( $band_max ); ?></span>
                    <p class="description"><?php esc_html_e( 'The widest a band can be. Set Min and Max close together for even bands, or far apart for strongly varied widths. (If Max is below Min it is treated as equal to Min.) (Bands style only.)', 'kdna-backgrounds' ); ?></p>
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

        /* Colour shapes */
        $shape_style = isset( $_POST['kdna_bg_shape_style'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_bg_shape_style'] ) ) : 'wash';
        if ( ! in_array( $shape_style, array( 'wash', 'concentric' ), true ) ) {
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
        update_post_meta( $post_id, '_kdna_bg_sheen', isset( $_POST['kdna_bg_sheen'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_sheen'] ) ) ) : 0 );
        update_post_meta( $post_id, '_kdna_bg_grain', isset( $_POST['kdna_bg_grain'] ) ? max( 0, min( 100, floatval( $_POST['kdna_bg_grain'] ) ) ) : 0 );
    }
}

new KDNA_BG_Meta();
