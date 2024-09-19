=== Product Bundles - Variation Bundles ===

Contributors: automattic, woocommerce, SomewhereWarm
Tags: woocommerce, product, bundles, map, variation
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.1
WC requires at least: 8.2
WC tested up to: 9.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Free mini-extension for WooCommerce Product Bundles that allows you to map Bundles to variations. Once a Product Bundle has been mapped to a variation, customers can choose variation attributes and then click the add-to-cart button to add the mapped Bundle to their cart.


== Description ==

Free mini-extension for [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) that allows you to map variations to static Product Bundles.

Useful if you, for example:

* Want to bundle together Hoodies and T-Shirts, and need a way to let customers choose their **Size** once, and then add the right Bundle to their cart.
* Are selling wine in bottles, boxes or pallets, and have created the box and pallet using Product Bundles. Now, you need a way to let customers choose a single bottle, a box, or a pallet.

**Important**: This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.

**Note**: This experimental plugin has been created to validate and refine a feature that may be rolled into WooCommerce Product Bundles -- or dropped! -- in the future.

**Important**: The code in this plugin is provided "as is". Support via the WordPress.org forum is provided on a **voluntary** basis only. If you have an active subscription for WooCommerce Product Bundles, please be aware that WooCommerce Support may not be able to assist you with this experimental plugin.

== Installation ==

This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.


== Screenshots ==

1. A Product Bundle mapped to a variation.


== Changelog ==

= 2.0.0 =
* Tweak - Updated author links.

= 2.0.0 =
* Important - New: PHP 7.4+ is now required.
* Important - New: WooCommerce 8.2+ is now required.
* Important - New: WordPress 6.2+ is now required.
* Important - New: Product Bundles 8.0+ is now required.

= 1.1.4 =
* Feature - Declared compatibility with the new High-Performance order tables.

= 1.1.3 =
* Fix - Make sure Variation Bundle prices respect tax display settings.

= 1.1.2 =
* Fix - Fixed fatal error that showed up when bundling a Variable Product with Variation Bundles.

= 1.1.1 =
* Fix - Make sure variation fields are saved correctly when removing a saved Variation Bundle.

= 1.1.0 =
* Feature - Inherit variation props from the specified Variation Bundle.
* Feature - Add compatibility with the WooCommerce Importer/Exporter.
* Tweak - Hide core variation fields when specifying a Variation Bundle.
* Tweak - Declared compatibility with latest WordPress and WooCommerce versions.

= 1.0.3 =
* Tweak - Declared compatibility with latest WordPress and WooCommerce versions.

= 1.0.2 =
* Fix - Fixed an error when variation IDs are not posted correctly while adding Variable products to the cart.

= 1.0.1 =
* Tweak - Redirect users back to the Variable Product page when clicking cart item.

= 1.0.0 =
* Initial Release.


== Upgrade Notice ==

= 1.1.2 =
Fixed fatal error affecting bundled Variable Products with Variation Bundles.
