<?php
/**
 * Plugin Name: Product Bundles - Variation Bundles
 * Plugin URI: https://docs.woocommerce.com/document/bundles/bundles-extensions/
 * Description: Free mini-extension for WooCommerce Product Bundles that allows you to map variations to Product Bundles.
 * Version: 2.0.1
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 *
 * Text Domain: woocommerce-product-bundles-variation-bundles
 * Domain Path: /languages/
 *
 * Requires at least: 6.2
 * Tested up to: 6.6
 * Requires PHP: 7.4
 *
 * WC requires at least: 8.2
 * WC tested up to: 9.1
 *
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
	public static $version = '2.0.1';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_pb_version = '8.0';

	/**
	 * Min required WC version.
	 *
	 * @var string
	 */
	public static $req_wc_version = '8.2';

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

		if ( ! function_exists( 'WC' ) || version_compare( WC()->version, self::$req_wc_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'wc_admin_notice' ) );
			return false;
		}

		if ( ! function_exists( 'WC_PB' ) || version_compare( WC_PB()->version, self::$req_pb_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'pb_admin_notice' ) );
			return false;
		}

		// Add Variation Bundle field to each variation.
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'product_variations_options' ), 10, 3 );

		// Ajax search for Variation Bundles. Only static Bundles are allowed for now.
		add_action( 'wp_ajax_woocommerce_json_search_variable_bundles', array( __CLASS__, 'ajax_search_variable_bundles' ) );

		// Save extra meta info for variations.
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'process_variable_bundles' ), 10, 2 );

		// Inherit props from mapped bundle.
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'before_product_object_save' ), 10 );

		add_filter( 'woocommerce_product_variation_get_sku', array( __CLASS__, 'variation_get_sku' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_manage_stock', array( __CLASS__, 'variation_get_manage_stock' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_virtual', array( __CLASS__, 'variation_get_virtual' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_stock_status', array( __CLASS__, 'variation_get_stock_status' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_stock_quantity', array( __CLASS__, 'variation_get_stock_quantity' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_width', array( __CLASS__, 'variation_get_width' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_length', array( __CLASS__, 'variation_get_length' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_height', array( __CLASS__, 'variation_get_height' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_shipping_class_id', array( __CLASS__, 'variation_get_shipping_class_id' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_tax_class', array( __CLASS__, 'variation_get_tax_class' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'variation_get_price' ), -1000, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'variation_get_regular_price' ), -1000, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'variation_get_sale_price' ), -1000, 2 );
		add_filter( 'woocommerce_variation_prices', array( __CLASS__, 'variation_prices' ), -1000, 3 );

		// Add Product Bundle to the cart instead of variation.
		add_filter( 'woocommerce_add_to_cart_product_id', array( __CLASS__, 'add_bundle_to_cart' ) );

		// Change Bundle's permalink to redirect back to the Variable Product page.
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'bundle_cart_item_permalink' ), 90, 3 );

		// Store variation ID in cart item data.
		add_action( 'woocommerce_add_cart_item_data', array(  __CLASS__, 'store_variation_id' ), 10, 3 );

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );

		// Localization.
		add_action( 'init', array( __CLASS__, 'localize_plugin' ) );

		if ( is_admin() ) {
			// Enqueue admin scripts.
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
			add_action( 'admin_head', array( __CLASS__, 'enqueue_admin_styles' ) );
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
	 * Enqueue styles.
	 *
	 * @return void
	 */
	public static function enqueue_admin_styles() {

		// Get admin screen ID.
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		/*
		 * Enqueue styles.
		 */
		if ( 'product' === $screen_id ) {
			$styles = '
			<style>
				.woocommerce_variable_attributes.variation_bundle_enabled .form-row.options label.tips,
				.woocommerce_variable_attributes.variation_bundle_enabled .form-row:not(.options, .upload_image, [class*="variable_description"] ),
				.woocommerce_variable_attributes.variation_bundle_enabled .form-field:not([class*="variable_description"]) {
					display: none !important;
				}
				.woocommerce_variable_attributes.variation_bundle_enabled .variation_bundles_row .form-field {
					display: block !important;
				}
			</style>
			';
			echo $styles;
		}
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

			wc_enqueue_js( '
				;( function( $ ) {

					// Cache containers.
					var $wrapper              = $( "#woocommerce-product-data" ),
						$variations_container = $wrapper.find( "#variable_product_options" );
					if ( ! $wrapper.length ) {
						return;
					}

					var toggle_elements = function( $container, hide ) {

						if ( hide === "true" ) {
							$container.addClass( "variation_bundle_enabled" );
						} else if ( hide === "false" ) {
							$container.removeClass( "variation_bundle_enabled" );
						}
					};

					var hide_elements = function( $container ) {
						toggle_elements( $container, "true" );
					};

					var show_elements = function( $container ) {
						toggle_elements( $container, "false" );
					};

					// Init variations data.
					$wrapper.on( "woocommerce_variations_loaded woocommerce_variations_added", function() {

						var $variation_bundles_selects = $variations_container.find( ".variation-bundles-select" );
						$variation_bundles_selects.each( function( index ) {

							var $select    = $( this ),
								$container = $select.parents( ".woocommerce_variable_attributes" );

							$container.sw_select2();
							if ( $select.val() ) {
								hide_elements( $container );
							}

							$select.on( "change", function() {
								var $this = $( this );
								if ( ! $this.val() ) {
									show_elements( $container );
								} else {
									hide_elements( $container );
								}
							} );
						} );
					} );

				} )( jQuery );
			' );
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
	 * Declare HPOS( Custom Order tables) compatibility.
	 *
	 */
	public static function declare_hpos_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );
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

		?><div class="variation_bundles_row">
			<p class="form-field form-row form-row-full">
				<label for="variable_bundles_id"><?php _e( 'Variation Bundle', 'woocommerce-product-bundles-variation-bundles' ); ?></label>
				<?php echo wc_help_tip( __( 'Choose a non-configurable Product Bundle to add to the cart instead of this variation. When this option is populated, all standard variation properties will be inherited from the specified Product Bundle.', 'woocommerce-product-bundles-variation-bundles' ) ); ?>
				<select class="sw-select2-search--products variation-bundles-select" style="width: 100%" id="variable_bundles_id[<?php echo $loop; ?>]" name="variable_bundles_id[<?php echo $loop; ?>]" data-allow_clear="yes" data-placeholder="<?php esc_attr_e( 'Search for a Product Bundle&hellip;', 'woocommerce-product-bundles-variation-bundles' ); ?>" data-action="woocommerce_json_search_variable_bundles" data-exclude="<?php echo intval( $variation->ID ); ?>" data-limit="100" data-sortable="true">
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
	 * WC version check notice.
	 */
	public static function wc_admin_notice() {
		echo '<div class="error"><p>' . sprintf( __( '<strong>Product Bundles &ndash; Variation Bundles</strong> requires <a href="%1$s" target="_blank">WooCommerce</a> version <strong>%2$s</strong> or higher.', 'woocommerce-product-bundles-variation-bundles' ), self::$pb_url, self::$req_wc_version ) . '</p></div>';
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
	 * @param WC_Product_Variation  $variation_id
	 * @param int                   $index
	 */
	public static function process_variable_bundles( $variation, $index ) {

		$variation_bundle_id = ! empty( $_POST[ 'variable_bundles_id' ][ $index ] ) ? absint( $_POST[ 'variable_bundles_id' ][ $index ] ) : false;

		if ( $variation_bundle_id ) {
			$variation->update_meta_data( '_wc_pb_variable_bundle', $variation_bundle_id );
		} else {
			$variation->delete_meta_data( '_wc_pb_variable_bundle' );
		}
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function before_product_object_save( $product ) {

		if ( $product->is_type( 'variable' ) ) {
			wp_cache_delete( 'variation_bundle_parent' . $product->get_id(), 'products' );
		}

		if ( ! $product->is_type( 'variation' ) ) {
			return;
		}

		if ( $variation_bundle = self::maybe_get_variation_bundle( $product, false ) ) {

			$product->set_manage_stock( false );
			$product->set_downloadable( false );
			$product->set_virtual( false );
			$product->set_stock_status( 'instock' );
			$product->set_stock_quantity( '' );
			$product->set_weight( '' );
			$product->set_width( '' );
			$product->set_height( '' );
			$product->set_length( '' );
			$product->set_shipping_class_id( 0 );
			$product->set_tax_class( '' );

			if ( '' === $product->get_regular_price( 'edit' ) ) {
				$product->set_regular_price( '0' );
			}
		}
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  bool        $sku
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_sku( $sku, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			return $variation_bundle->get_sku();
		}

		return $sku;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  bool        $manage_stock
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_manage_stock( $manage_stock, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {

			// 'WC_Product_Variation::get_manage_stock' is called in 'view' context when rendering variation admin fields.
			if ( did_action( 'woocommerce_variation_header' ) !== did_action( 'woocommerce_variation_options' ) ) {
				return false;
			}

			$parent_data = $variation->get_parent_data();
			$parent_data[ 'manage_stock' ] = 'no';
			$variation->set_parent_data( $parent_data );

			return $variation_bundle->get_manage_stock();
		}

		return $manage_stock;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  bool        $is_virtual
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_virtual( $is_virtual, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			return $variation_bundle->get_virtual();
		}

		return $is_virtual;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $stock_quantity
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_stock_quantity( $stock_quantity, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$stock_quantity = $variation_bundle->get_bundle_stock_quantity();
			if ( '' === $stock_quantity ) {
				$stock_quantity = null;
			}
		}

		return $stock_quantity;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $stock_status
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_stock_status( $stock_status, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$stock_status = 'insufficientstock' === $variation_bundle->get_bundle_stock_status() ? 'outofstock' : $variation_bundle->get_stock_status();
		}

		return $stock_status;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $weight
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_weight( $weight, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$weight = $variation_bundle->get_weight();
		}

		return $weight;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $width
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_width( $width, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$width = $variation_bundle->get_width();
		}

		return $width;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $length
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_length( $length, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$length = $variation_bundle->get_length();
		}

		return $length;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $height
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_height( $height, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$height = $variation_bundle->get_height();
		}

		return $height;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $shipping_class_id
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_shipping_class_id( $shipping_class_id, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$shipping_class_id = $variation_bundle->get_shipping_class_id();
		}

		return $shipping_class_id;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $tax_class
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_tax_class( $tax_class, $variation ) {

		$parent_product = self::get_variation_parent( $variation );

		if ( 'none' === $parent_product->get_tax_status() ) {
			return $tax_class;
		}

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$tax_class = $variation_bundle->get_tax_class();
		}

		return $tax_class;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $price
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_price( $price, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$price = $variation_bundle->get_min_raw_price();
		}

		return $price;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $regular_price
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_regular_price( $regular_price, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$regular_price = $variation_bundle->get_min_raw_regular_price();
		}

		return $regular_price;
	}

	/**
	 * Inherit from mapped bundle.
	 *
	 * @since  1.1.0
	 *
	 * @param  string      $sale_price
	 * @param  WC_Product  $variation
	 * @return void
	 */
	public static function variation_get_sale_price( $sale_price, $variation ) {

		if ( $variation_bundle = self::maybe_get_variation_bundle( $variation ) ) {
			$sale_price = $variation_bundle->get_min_raw_price();
		}

		return $sale_price;
	}

	/**
	 * Inherit prices from mapped bundles.
	 *
	 * @since  1.1.0
	 *
	 * @param  array       $prices
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function variation_prices( $prices_array, $product, $for_display ) {

		$prices         = array();
		$regular_prices = array();
		$sale_prices    = array();
		$tax_setting    = wc_tax_enabled() ? get_option( 'woocommerce_tax_display_shop' ) : '';
		// Filter regular prices.
		foreach ( $prices_array[ 'regular_price' ] as $variation_id => $regular_price ) {
			if ( $variation_bundle = self::maybe_get_variation_bundle( $variation_id ) ) {
				if ( $for_display && ! empty( $tax_setting ) && 'none' !== $product->get_tax_status() ) {
					if ( 'incl' === $tax_setting ) {
						$regular_prices[ $variation_id ] = $variation_bundle->get_bundle_regular_price_including_tax( 'min' );
					} elseif ( 'excl' === $tax_setting ) {
						$regular_prices[ $variation_id ] = $variation_bundle->get_bundle_regular_price_excluding_tax( 'min' );
					}
				} else {
					$regular_prices[ $variation_id ] = $variation_bundle->get_min_raw_regular_price( 'sync' );
				}
			} else {
				$regular_prices[ $variation_id ] = $regular_price;
			}
		}

		// Filter prices.
		foreach ( $prices_array[ 'price' ] as $variation_id => $price ) {
			if ( $variation_bundle = self::maybe_get_variation_bundle( $variation_id ) ) {
				if ( $for_display && ! empty( $tax_setting ) && 'none' !== $product->get_tax_status() ) {
					if ( 'incl' === $tax_setting ) {
						$prices[ $variation_id ] = $variation_bundle->get_bundle_price_including_tax( 'min' );
					} elseif ( 'excl' === $tax_setting ) {
						$prices[ $variation_id ] = $variation_bundle->get_bundle_price_excluding_tax( 'min' );
					}
				} else {
					$prices[ $variation_id ] = $variation_bundle->get_min_raw_price( 'sync' );
				}
			} else {
				$prices[ $variation_id ] = $price;
			}
		}

		// Filter sale prices.
		foreach ( $prices_array[ 'sale_price' ] as $variation_id => $sale_price ) {
			if ( $variation_bundle = self::maybe_get_variation_bundle( $variation_id ) ) {
				if ( $for_display && ! empty( $tax_setting ) && 'none' !== $product->get_tax_status() ) {
					if ( 'incl' === $tax_setting ) {
						$sale_prices[ $variation_id ] = $variation_bundle->get_bundle_price_including_tax( 'min' );
					} elseif ( 'excl' === $tax_setting ) {
						$sale_prices[ $variation_id ] = $variation_bundle->get_bundle_price_excluding_tax( 'min' );
					}
				} else {
					$sale_prices[ $variation_id ] = $variation_bundle->get_min_raw_price( 'sync' );
				}
			} else {
				$sale_prices[ $variation_id ] = $sale_price;
			}
		}

		asort( $regular_prices );
		asort( $prices );
		asort( $sale_prices );

		$prices_array = array(
			'price'         => $prices,
			'regular_price' => $regular_prices,
			'sale_price'    => $sale_prices
		);

		return $prices_array;
	}

	/**
	 * Retrieve the bundle mapped to a variation.
	 *
	 * @since  1.1.0
	 *
	 * @param  WC_Product_Variation|int  $variation
	 * @param  bool                      $use_cache
	 * @return WC_Product_Bundle|false
	 */
	public static function maybe_get_variation_bundle( $variation, $use_cache = true ) {

		if ( $variation instanceof WC_Product_Variation ) {
			$variation_id = $variation->get_id();
		} else {
			$variation_id = (int) $variation;
		}

		if ( $use_cache ) {
			$variation_bundle = WC_PB_Helpers::cache_get( $variation_id, 'variation_bundles' );
			if ( null !== $variation_bundle ) {
				return $variation_bundle;
			}
		}

		$variation_bundle    = false;
		$variation_bundle_id = false;

		if ( $variation instanceof WC_Product_Variation ) {
			$variation_bundle_id = $variation->get_meta( '_wc_pb_variable_bundle' );
		} else {
			$variation_bundle_id = (int) get_post_meta( $variation_id, '_wc_pb_variable_bundle', true );
		}

		if ( $variation_bundle_id ) {
			$variation_bundle = wc_get_product( $variation_bundle_id );
		}

		if ( $use_cache ) {
			WC_PB_Helpers::cache_set( $variation_id, $variation_bundle, 'variation_bundles' );
		}

		return $variation_bundle;
	}

	/**
	 * Add Product Bundle to the cart instead of variation.
	 *
	 * @param  int $add_to_cart_id
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
	 * @param  array  $cart_item_data
	 * @param  int    $product_id
	 * @param  int    $variation_id
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
		$options[ 'wc_pb_variation_bundles' ] = __( 'Variation Bundles', 'woocommerce-product-bundles-variation-bundles' );
		return $options;
	}

	/**
	 * Add automatic mapping support for custom columns.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function add_columns_to_mapping_screen( $columns ) {

		$columns[ __( 'Variation Bundles', 'woocommerce-product-bundles-variation-bundles' ) ] = 'wc_pb_variation_bundles';

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

			$product_id   = $importer->parse_relative_field( $parsed_data[ 'wc_pb_variation_bundles' ] );
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

		$columns[ 'wc_pb_variation_bundles' ] = __( 'Variation Bundles', 'woocommerce-product-bundles-variation-bundles' );

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
			$bundle_id   = $product->get_meta( '_wc_pb_variable_bundle', true );

			if ( ! empty( $bundle_id ) ) {
				$bundle      = wc_get_product( $bundle_id );
				$bundle_sku  = $bundle->get_sku();
				$value       = $bundle_sku ? $bundle_sku : 'id:' . $bundle_id;
			}
		}

		return $value;
	}

	/**
	 * Returns the parent product object of the variation using cache
	 *
	 * @since  1.1.3
	 *
	 * @param  WC_Product  $variation
	 * @return WC_Product
	 */
	public static function get_variation_parent( $variation ) {
		$parent_id      = $variation->get_parent_id();
		$cache_key      = 'variation_bundle_parent' . $parent_id;
		$parent_product = wp_cache_get( $cache_key, 'products' );

		if ( ! is_a( $parent_product, 'WC_Product' )  ) {
			$parent_product = wc_get_product( $parent_id );
			wp_cache_set( $cache_key, $parent_product, 'products' );
		}

		return $parent_product;
	}
}

WC_PB_Variable_Bundles::init();
