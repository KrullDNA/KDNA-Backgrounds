<?php
/**
 * Registers the KDNA Backgrounds custom post type under Tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KDNA_BG_CPT {

    public static function register() {

        $labels = array(
            'name'               => __( 'KDNA Backgrounds', 'kdna-backgrounds' ),
            'singular_name'      => __( 'Background', 'kdna-backgrounds' ),
            'add_new'            => __( 'Add New Background', 'kdna-backgrounds' ),
            'add_new_item'       => __( 'Add New Background', 'kdna-backgrounds' ),
            'edit_item'          => __( 'Edit Background', 'kdna-backgrounds' ),
            'new_item'           => __( 'New Background', 'kdna-backgrounds' ),
            'view_item'          => __( 'View Background', 'kdna-backgrounds' ),
            'search_items'       => __( 'Search Backgrounds', 'kdna-backgrounds' ),
            'not_found'          => __( 'No backgrounds found', 'kdna-backgrounds' ),
            'not_found_in_trash' => __( 'No backgrounds found in Trash', 'kdna-backgrounds' ),
            'all_items'          => __( 'KDNA Backgrounds', 'kdna-backgrounds' ),
            'menu_name'          => __( 'KDNA Backgrounds', 'kdna-backgrounds' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'tools.php',
            'supports'            => array( 'title' ),
            'menu_icon'           => 'dashicons-art',
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'rewrite'             => false,
        );

        register_post_type( 'kdna_background', $args );
    }
}
