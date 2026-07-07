<?php
/**
 * Rastin Shop – theme functions and definitions.
 *
 * @package Rastin
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'RASTIN_VERSION' ) ) {
	define( 'RASTIN_VERSION', '1.1.0' );
}

// Admin menu infrastructure: single top-level theme menu + hook for companion plugins.
require_once get_template_directory() . '/inc/admin-menu.php';

if ( ! function_exists( 'rastin_setup' ) ) {
	/**
	 * Register theme support features.
	 *
	 * @return void
	 */
	function rastin_setup() {
		// Make the theme available for translation.
		load_theme_textdomain( 'rastin', get_template_directory() . '/languages' );

		// Core block theme supports.
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		) );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array(
			'height'      => 60,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		) );

		// WooCommerce support.
		add_theme_support( 'woocommerce', array(
			'thumbnail_image_width' => 400,
			'single_image_width'    => 800,
			'product_grid'          => array(
				'default_columns' => 3,
				'default_rows'    => 3,
			),
		) );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
}
add_action( 'after_setup_theme', 'rastin_setup' );

if ( ! function_exists( 'rastin_enqueue_styles' ) ) {
	/**
	 * Enqueue the front-end stylesheet (theme.json handles most styling).
	 *
	 * @return void
	 */
	function rastin_enqueue_styles() {
		wp_enqueue_style(
			'rastin-style',
			get_stylesheet_uri(),
			array(),
			RASTIN_VERSION
		);

		// Load the RTL stylesheet when the site is right-to-left.
		wp_style_add_data( 'rastin-style', 'rtl', 'replace' );
	}
}
add_action( 'wp_enqueue_scripts', 'rastin_enqueue_styles' );

if ( ! function_exists( 'rastin_editor_assets' ) ) {
	/**
	 * Load front-end styles inside the block editor for visual parity.
	 *
	 * @return void
	 */
	function rastin_editor_assets() {
		add_editor_style( 'style.css' );
	}
}
add_action( 'after_setup_theme', 'rastin_editor_assets' );

if ( ! function_exists( 'rastin_register_pattern_categories' ) ) {
	/**
	 * Register custom block pattern categories used by the theme patterns.
	 *
	 * @return void
	 */
	function rastin_register_pattern_categories() {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category( 'rastin', array(
			'label'       => __( 'رستین', 'rastin' ),
			'description' => __( 'الگوهای اختصاصی قالب رستین.', 'rastin' ),
		) );

		register_block_pattern_category( 'rastin-shop', array(
			'label'       => __( 'رستین – فروشگاه', 'rastin' ),
			'description' => __( 'الگوهای فروشگاهی سازگار با ووکامرس.', 'rastin' ),
		) );
	}
}
add_action( 'init', 'rastin_register_pattern_categories' );

if ( ! function_exists( 'rastin_disable_wc_default_styles' ) ) {
	/**
	 * Keep WooCommerce core layout styles but let theme.json drive typography/colors.
	 *
	 * @param array $enabled Default WooCommerce style handles.
	 * @return array
	 */
	function rastin_disable_wc_default_styles( $enabled ) {
		// Keep general and layout styles; the theme handles the rest.
		return $enabled;
	}
}
add_filter( 'woocommerce_enqueue_styles', 'rastin_disable_wc_default_styles' );

if ( ! function_exists( 'rastin_body_classes' ) ) {
	/**
	 * Add helpful body classes.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	function rastin_body_classes( $classes ) {
		if ( is_rtl() ) {
			$classes[] = 'rastin-rtl';
		}
		return $classes;
	}
}
add_filter( 'body_class', 'rastin_body_classes' );
