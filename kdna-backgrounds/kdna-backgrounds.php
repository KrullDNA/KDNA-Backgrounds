<?php
/**
 * Plugin Name: KDNA Backgrounds
 * Description: Animated mesh gradient backgrounds using WebGL with Canvas 2D fallback. Create reusable gradient presets and apply them to any Elementor container via the Advanced tab.
 * Version: 1.0.2
 * Author: KDNA
 * Text Domain: kdna-backgrounds
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KDNA_BG_VERSION', '1.0.2' );
define( 'KDNA_BG_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_BG_URL', plugin_dir_url( __FILE__ ) );

require_once KDNA_BG_PATH . 'includes/class-kdna-bg-cpt.php';
require_once KDNA_BG_PATH . 'includes/class-kdna-bg-meta.php';
require_once KDNA_BG_PATH . 'includes/class-kdna-bg-render.php';

add_action( 'init', array( 'KDNA_BG_CPT', 'register' ) );

/* ── Admin assets ── */
add_action( 'admin_enqueue_scripts', 'kdna_bg_admin_assets' );

function kdna_bg_admin_assets( $hook ) {
    $screen = get_current_screen();
    if ( ! $screen || 'kdna_background' !== $screen->post_type ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_style( 'kdna-bg-admin', KDNA_BG_URL . 'admin/css/admin-style.css', array( 'wp-color-picker' ), KDNA_BG_VERSION );
    wp_enqueue_script( 'kdna-gradient-engine', KDNA_BG_URL . 'assets/js/gradient-engine.js', array(), KDNA_BG_VERSION, true );
    wp_enqueue_script( 'kdna-bg-admin', KDNA_BG_URL . 'admin/js/admin-script.js', array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'kdna-gradient-engine' ), KDNA_BG_VERSION, true );
}

/* ── Front-end assets ── */
add_action( 'wp_enqueue_scripts', 'kdna_bg_frontend_assets' );
add_action( 'elementor/preview/enqueue_scripts', 'kdna_bg_frontend_assets' );

function kdna_bg_frontend_assets() {
    wp_enqueue_style( 'kdna-bg-front', KDNA_BG_URL . 'assets/css/front.css', array(), KDNA_BG_VERSION );
    wp_enqueue_script( 'kdna-gradient-engine', KDNA_BG_URL . 'assets/js/gradient-engine.js', array(), KDNA_BG_VERSION, true );
    wp_enqueue_script( 'kdna-bg-front', KDNA_BG_URL . 'assets/js/front.js', array( 'kdna-gradient-engine' ), KDNA_BG_VERSION, true );
}

/* ── Elementor: Container controls ── */
add_action( 'elementor/element/container/section_effects/after_section_end', 'kdna_bg_add_container_controls', 10, 2 );
add_action( 'elementor/element/container/section_layout/after_section_end', 'kdna_bg_add_container_controls_fb', 10, 2 );

function kdna_bg_add_container_controls( $element, $args ) {
    if ( isset( $element->get_controls()['kdna_bg_id'] ) ) return;
    kdna_bg_inject_controls( $element );
}
function kdna_bg_add_container_controls_fb( $element, $args ) {
    if ( isset( $element->get_controls()['kdna_bg_id'] ) ) return;
    kdna_bg_inject_controls( $element );
}

function kdna_bg_inject_controls( $element ) {
    $options = array( '' => __( '-- None --', 'kdna-backgrounds' ) );
    $bgs = get_posts( array(
        'post_type' => 'kdna_background', 'posts_per_page' => -1,
        'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC',
    ) );
    foreach ( $bgs as $bg ) $options[ $bg->ID ] = $bg->post_title;

    $element->start_controls_section( 'kdna_bg_section', array(
        'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
        'label' => __( 'KDNA Background', 'kdna-backgrounds' ),
    ) );

    $element->add_control( 'kdna_bg_id', array(
        'label' => __( 'Mesh Gradient', 'kdna-backgrounds' ),
        'type' => \Elementor\Controls_Manager::SELECT,
        'options' => $options, 'default' => '',
        'description' => __( 'Select a gradient preset from Tools > KDNA Backgrounds.', 'kdna-backgrounds' ),
        'render_type' => 'template',
    ) );

    $element->add_control( 'kdna_bg_overlay_toggle', array(
        'label' => __( 'Colour Overlay', 'kdna-backgrounds' ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => __( 'Yes', 'kdna-backgrounds' ), 'label_off' => __( 'No', 'kdna-backgrounds' ),
        'return_value' => 'yes', 'default' => '',
        'condition' => array( 'kdna_bg_id!' => '' ), 'render_type' => 'template',
    ) );

    $element->add_control( 'kdna_bg_overlay_colour', array(
        'label' => __( 'Overlay Colour', 'kdna-backgrounds' ),
        'type' => \Elementor\Controls_Manager::COLOR,
        'default' => 'rgba(0,0,0,0.3)', 'render_type' => 'template',
        'condition' => array( 'kdna_bg_id!' => '', 'kdna_bg_overlay_toggle' => 'yes' ),
    ) );

    $element->add_control( 'kdna_bg_speed_override', array(
        'label' => __( 'Speed Override', 'kdna-backgrounds' ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => array( 'px' => array( 'min' => 0, 'max' => 20, 'step' => 1 ) ),
        'default' => array( 'size' => 0, 'unit' => 'px' ),
        'description' => __( 'Leave at 0 to use the saved preset speed.', 'kdna-backgrounds' ),
        'render_type' => 'template',
        'condition' => array( 'kdna_bg_id!' => '' ),
    ) );

    $element->end_controls_section();
}

/* ── Elementor: add data attributes to container ── */
add_action( 'elementor/frontend/container/before_render', 'kdna_bg_container_before' );

function kdna_bg_container_before( $element ) {
    $settings = $element->get_settings_for_display();
    $bg_id = isset( $settings['kdna_bg_id'] ) ? absint( $settings['kdna_bg_id'] ) : 0;
    if ( ! $bg_id ) return;

    $post = get_post( $bg_id );
    if ( ! $post || 'publish' !== $post->post_status ) return;

    $element->add_render_attribute( '_wrapper', array(
        'data-kdna-bg-id' => $bg_id,
        'class' => 'kdna-bg-active',
    ) );

    $overlay_on = ! empty( $settings['kdna_bg_overlay_toggle'] ) && 'yes' === $settings['kdna_bg_overlay_toggle'];
    if ( $overlay_on && ! empty( $settings['kdna_bg_overlay_colour'] ) ) {
        $element->add_render_attribute( '_wrapper', 'data-kdna-bg-overlay', esc_attr( $settings['kdna_bg_overlay_colour'] ) );
    }

    $speed = isset( $settings['kdna_bg_speed_override']['size'] ) ? absint( $settings['kdna_bg_speed_override']['size'] ) : 0;
    if ( $speed > 0 ) {
        $element->add_render_attribute( '_wrapper', 'data-kdna-bg-speed', $speed );
    }

    KDNA_BG_Render::enqueue_gradient_data( $bg_id );
}

/* ── Output gradient config data ── */

/* Put the data object in wp_head so it exists before any scripts run */
add_action( 'wp_head', 'kdna_bg_head_placeholder', 99 );
function kdna_bg_head_placeholder() {
    echo "<script>window.kdnaBgData=window.kdnaBgData||{};</script>\n";
}

/* Output the actual gradient data at wp_footer priority 1,
   which runs BEFORE enqueued footer scripts (priority 20) */
add_action( 'wp_footer', 'kdna_bg_output_data', 1 );

function kdna_bg_output_data() {
    $data = KDNA_BG_Render::get_all_data();
    if ( empty( $data ) ) return;
    echo "<script>\n";
    foreach ( $data as $id => $config ) {
        printf( "window.kdnaBgData[%d]=%s;\n", intval( $id ), wp_json_encode( $config ) );
    }
    echo "</script>\n";
}
