<?php
/**
 * Plugin Name: Product Bundles - Variation Bundles
 * Plugin URI: https://docs.woocommerce.com/document/bundles/bundles-extensions/
 * Description: Free mini-extension for WooCommerce Product Bundles that allows you to map variations to Product Bundles.
 * Version: 1.0.4
 * Author: SomewhereWarm
 * Author URI: https://somewherewarm.com/
 *
 * Text Domain: woocommerce-product-bundles-variation-bundles
 * Domain Path: /languages/
 *
 * Requires at least: 4.4
 * Tested up to: 5.6
 * Requires PHP: 5.6
 *
 * WC requires at least: 3.1
 * WC tested up to: 5.0
 *
 * Copyright: © 2017-2020 SomewhereWarm SMPC.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_PB_Variable_Bundles {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '1.0.3';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_pb_version = '5.5';

	/**
	 * PB URL.
	 *
	 * @var string
	 */
	private static $pb_url = 'https://woocommerce.com/products/product-bundles/';

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ) );
	}

	/**
	 * Hooks.
	 */
	public static function load_plugin() {

		if ( ! function_exists( 'WC_PB' ) || version_compare( WC_PB()->version, self::$req_pb_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'pb_admin_notice' ) );
			return false;
		}

		// Add Variation Bundle field to each variation.
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'product_variations_options' ), 10, 3 );

		// Ajax search for Variation Bundles. Only static Bundles are allowed for now.
		add_action( 'wp_ajax_woocommerce_json_search_variable_bundles', array( __CLASS__, 'ajax_search_variable_bundles' ) );

		// Save extra meta info for variations.
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'process_variable_bundles' ), 30, 2 );

		// Add Product Bundle to the cart instead of variation.
		add_filter( 'woocommerce_add_to_cart_product_id', array( __CLASS__, 'add_bundle_to_cart' ) );

		// Change Bundle's permalink to redirect back to the Variable Product page.
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'bundle_cart_item_permalink' ), 90, 3 );

		// Store variation ID in cart item data.
		add_action( 'woocommerce_add_cart_item_data', array(  __CLASS__, 'store_variation_id' ), 10, 3 );

		// Localization.
		add_action( 'init', array( __CLASS__, 'localize_plugin' ) );

		if ( is_admin() ) {
			// Enqueue admin scripts.
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		}

		/*
		* Compatibility and Integrations.
		*
		*/

		// WooCommerce Importer.

		// Map imported columns.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'map_columns' ), 11 );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'add_columns_to_mapping_screen' ), 11 );

		// Parse Variation Bundles.
		add_filter( 'woocommerce_product_importer_parsed_data', array( __CLASS__, 'parse_variation_bundles' ), 10, 2 );

		// WooCommerce Exporter.

		// Add CSV columns for exporting Variation Bundles.
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'add_columns' ), 11 );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'add_columns' ), 11 );

		// "Variation Bundles" column data.
		add_filter( 'woocommerce_product_export_product_column_wc_pb_variation_bundles', array( __CLASS__, 'export_variation_bundles' ), 10, 2 );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public static function enqueue_admin_scripts() {

		// Get admin screen ID.
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		/*
		 * Enqueue scripts.
		 */
		if ( 'product' === $screen_id ) {

			wc_enqueue_js( "
				jQuery( function( $ ) {

					var wrapper = jQuery( '#woocommerce-product-data' );

					wrapper.on( 'woocommerce_variations_loaded woocommerce_variations_added', function() {
						jQuery( '.woocommerce_variations', wrapper ).sw_select2();
					} );
				} );
			" );
		}
	}
	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public static function localize_plugin() {
		load_plugin_textdomain( 'woocommerce-product-bundles-variation-bundles', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Add Variation Bundle field to each variation.
	 *
	 * @param string  $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 *
	 */
	public static function product_variations_options( $loop, $variation_data, $variation ) {

		?><div>
			<p class="form-field form-row form-row-full">
				<label for="variable_bundles_id"><?php _e( 'Variation Bundle', 'woocommerce-product-bundles-variation-bundles' ); ?></label>
				<?php echo wc_help_tip( __( 'Choose a static Product Bundle to add to the cart instead of this variation.', 'woocommerce-product-bundles-variation-bundles' ) ); ?>
				<select class="sw-select2-search--products" style="width: 100%" id="variable_bundles_id[<?php echo $loop; ?>]" name="variable_bundles_id[<?php echo $loop; ?>]" data-allow_clear="yes" data-placeholder="<?php esc_attr_e( 'Search for a Product Bundle&hellip;', 'woocommerce-product-bundles-variation-bundles' ); ?>" data-action="woocommerce_json_search_variable_bundles" data-exclude="<?php echo intval( $variation->ID ); ?>" data-limit="100" data-sortable="true">
					<?php

						$variation_object = wc_get_product( $variation->ID );
						$product_id       = $variation_object->get_meta( '_wc_pb_variable_bundle' );

						if ( ! empty( $product_id ) ) {

							$product = wc_get_product( $product_id );

							if ( is_object( $product ) ) {
								echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
							}
						}
					?>
				</select>
			</p>
		</div><?php
	}

	/**
	 * PB version check notice.
	 */
	public static function pb_admin_notice() {
	    echo '<div class="error"><p>' . sprintf( __( '<strong>Product Bundles &ndash; Variation Bundles</strong> requires <a href="%1$s" target="_blank">WooCommerce Product Bundles</a> version <strong>%2$s</strong> or higher.', 'woocommerce-product-bundles-variation-bundles' ), self::$pb_url, self::$req_pb_version ) . '</p></div>';
	}

	/**
	 * Ajax search for bundled variations.
	 */
	public static function ajax_search_variable_bundles() {

		add_filter( 'woocommerce_json_search_found_products', array( __CLASS__, 'filter_ajax_search_results' ) );
		WC_AJAX::json_search_products( '', false );
		remove_filter( 'woocommerce_json_search_found_products', array( __CLASS__, 'filter_ajax_search_results' ) );
	}

	/**
	 * Include only static Product Bundles in Variation Bundle results.
	 *
	 * @param  array  $search_results
	 * @return array
	 */
	public static function filter_ajax_search_results( $search_results ) {

		if ( ! empty( $search_results ) ) {

			$search_results_filtered = array();

			foreach ( $search_results as $product_id => $product_title ) {

				$product = wc_get_product( $product_id );

				if ( is_object( $product ) && $product->is_type( 'bundle' ) && ! $product->has_options() ) {

					$search_results_filtered[ $product_id ] = $product_title;
				}
			}

			$search_results = $search_results_filtered;
		}

		return $search_results;
	}

	/**
	 * Save extra meta info for variations.
	 *
	 * @param int $variation_id
	 * @param int $index
	 */
	public static function process_variable_bundles( $variation_id, $index ) {

		$variation           = wc_get_product( $variation_id );
		$variable_bundles_id = ! empty( $_POST[ 'variable_bundles_id' ][ $index ] ) ? absint( $_POST[ 'variable_bundles_id' ][ $index ] ) : false;

		if ( $variable_bundles_id ) {
			$variation->update_meta_data( '_wc_pb_variable_bundle', $variable_bundles_id );
		} else {
			$variation->delete_meta_data( '_wc_pb_variable_bundle' );
		}

		$variation->save();
	}

	/**
	 * Add Product Bundle to the cart instead of variation.
	 *
	 * @param int $add_to_cart_id
	 * @return int
	 */
	public static function add_bundle_to_cart( $add_to_cart_id ) {

		if ( ! isset( $_REQUEST[ 'variation_id' ] ) || empty( $_REQUEST[ 'variation_id' ] ) ) {
			return $add_to_cart_id;
		}

		$product_type = WC_Data_Store::load( 'product' )->get_product_type( $add_to_cart_id );

		if ( 'variable' === $product_type ) {

			$variation = wc_get_product( absint( $_REQUEST[ 'variation_id' ] ) );
			if ( is_a( $variation, 'WC_Product') && ! empty( $variation->get_meta( '_wc_pb_variable_bundle' ) ) ) {
				$add_to_cart_id  = $variation->get_meta( '_wc_pb_variable_bundle' );
			}
		}

		return $add_to_cart_id;
	}

	/**
	 * Store variation ID in cart item data.
	 *
	 * @param  array $cart_item_data
	 * @param  int   $product_id
	 * @param  int   $variation_id
	 * @return array
	 */
	public static function store_variation_id( $cart_item_data, $product_id, $variation_id ) {

		if ( ! isset( $_REQUEST[ 'variation_id' ] ) || empty( $_REQUEST[ 'variation_id' ] ) ) {
			return $cart_item_data;
		}

		$product_type = WC_Data_Store::load( 'product' )->get_product_type( $product_id );

		if ( 'bundle' === $product_type ) {
			$cart_item_data[ '_bundle_variation_id' ] = $_REQUEST[ 'variation_id' ];
		}

		return $cart_item_data;
	}

	/**
	 * Change Bundle's permalink to redirect back to the Variable Product page.
	 *
	 * @param  string  $html
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return string
	 */
	public static function bundle_cart_item_permalink( $html, $cart_item, $cart_item_key ) {

		if ( ! isset( $cart_item[ '_bundle_variation_id' ] ) ) {
			return $html;
		}

		$parent_product = wc_get_product( wp_get_post_parent_id( $cart_item[ '_bundle_variation_id' ] ) );

		if ( ! ( $parent_product instanceof WC_Product_Variable ) || ! $parent_product->is_visible() ) {
			return '';
		}

		return get_permalink( $cart_item[ '_bundle_variation_id' ] );
	}

	/*
	* Compatibility and Integrations.
	*
	*/

	/*
	* WooCommerce Importer.
	*
	*/

	/**
	 * Register the 'Variable Bundles' column in the importer.
	 *
	 * @param  array  $options
	 * @return array  $options
	 */
	public static function map_columns( $options ) {
		$options[ 'wc_pb_variation_bundles' ] = __( 'Variation Bundles', 'woocommerce-product-bundles' );
		return $options;
	}

	/**
	 * Add automatic mapping support for custom columns.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function add_columns_to_mapping_screen( $columns ) {

		$columns[ __( 'Variation Bundles', 'woocommerce-product-bundles' ) ] = 'wc_pb_variation_bundles';

		// Always add English mappings.
		$columns[ 'Variation Bundles' ] = 'wc_pb_variation_bundles';

		return $columns;
	}

	/**
	 * Parse Variation Bundles data.
	 *
	 *
	 * @param  array                    $parsed_data
	 * @param  WC_Product_CSV_Importer  $importer
	 * @return array
	 */
	public static function parse_variation_bundles( $parsed_data, $importer ) {

		if ( ! empty( $parsed_data[ 'wc_pb_variation_bundles' ] ) ) {

			$product_id   = $parsed_data[ 'wc_pb_variation_bundles' ];
			$product_type = WC_Data_Store::load( 'product' )->get_product_type( $product_id );

			if ( 'bundle' !== $product_type ) {
				return $parsed_data;
			}

			$product = wc_get_product( $product_id );

			if ( ! is_object( $product ) || $product->has_options() ) {
				return $parsed_data;
			}

			$parsed_data[ 'meta_data' ][] = array(
				'key'   => '_wc_pb_variable_bundle',
				'value' => $product_id
			);
		}

		return $parsed_data;
	}

	/*
	* WooCommerce Exporter.
	*
	*/

	/**
	 * Add CSV columns for exporting Variation Bundles.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function add_columns( $columns ) {

		$columns[ 'wc_pb_variation_bundles' ] = __( 'Variation Bundles', 'woocommerce-product-bundles' );

		return $columns;
	}

	/**
	 * "Variation Bundles" field content.
	 *
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_variation_bundles( $value, $product ) {

		if ( $product->is_type( 'variation' ) ) {
		 	$value = $product->get_meta( '_wc_pb_variable_bundle', true );
		}

		return $value;
	}
}

WC_PB_Variable_Bundles::init();
