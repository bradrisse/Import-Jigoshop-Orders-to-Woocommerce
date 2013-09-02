<?php
/**
 * Plugin Name: Import Jigoshop Orders to Woocommerce
 * Plugin URI:  https://bradly.risse.com
 * Description:  Jigoshop orders import to woocommerce 
 * Author:      bradlyrisse.com
 * Version:     1.1
 * Author URI:  http://bradlyrisse.com/
 * Text Domain: bradlyrisse
 * Domain Path: /languages/
 * Network:     true
 */

 /*
  * This Script imports parts of Jigoshop order values to WooCommerce. 
  * 
  * Please be sure you have switched off Jigoshop and switched on WooCommerce.
  * 
  * We take no warranty for this script! 
  * 
  */
  
   /*
  * Check Woocommerce Active
  * 
  */


/*
  * Required Functions
  * 
  */
include 'classes/jigoshop_order.class.php';
include 'classes/jigoshop_orders.class.php';
include 'classes/jigoshop_tax.class.php';
include 'classes/jigoshop_countries.class.php';
  
// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

/**
 * WordPress Importer class
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class Woo_Jigo_Converter extends WP_Importer {

	var $results;

	function Woo_Jigo_Converter() { /* nothing */ }

	/**
	 * Registered callback function for the WooCommerce - JigoShop Converter
	 *
	 */
	function dispatch() {
		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->analyze();
				break;
			case 1:
				check_admin_referer('woo_jigo_converter');
				$this->woo_import_jigo_orders();
				break;
		}

		$this->footer();
	}

	// Display import page title
	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'JigoShop To WooCommerce Converter', 'woo_jigo' ) . '</h2>';
	}

	// Close div.wrap
	function footer() {
		echo '</div>';
	}

	// Jigoshop Version
	function jigoshop_version() {

		// Get the db version
		$jigoshop_db_version = get_site_option( 'jigoshop_db_version' );

		if ( ! is_numeric($jigoshop_db_version) ) {
			switch ( $jigoshop_db_version ) {
				case '0.9.6':
					$jigoshop_db_version = 1105310;
					break;
				case '0.9.7':
					$jigoshop_db_version = 1105311;
					break;
				case '0.9.7.1':
					$jigoshop_db_version = 1105312;
					break;
				case '0.9.7.2':
					$jigoshop_db_version = 1105313;
					break;
				case '0.9.7.3':
					$jigoshop_db_version = 1106010;
					break;
				case '0.9.7.4':
					$jigoshop_db_version = 1106011;
					break;
				case '0.9.7.5':
					$jigoshop_db_version = 1106130;
					break;
				case '0.9.7.6':
					$jigoshop_db_version = 1106140;
					break;
				case '0.9.7.7':
					$jigoshop_db_version = 1106220;
					break;
				case '0.9.7.8':
					$jigoshop_db_version = 1106221;
					break;
				case '0.9.8':
					$jigoshop_db_version = 1107010;
					break;
				case '0.9.8.1':
					$jigoshop_db_version = 1109080;
					break;
				case '0.9.9':
					$jigoshop_db_version = 1109200;
					break;
				case '0.9.9.1':
					$jigoshop_db_version = 1111090;
					break;
				case '0.9.9.2':
					$jigoshop_db_version = 1111091;
					break;
				case '0.9.9.3':
					$jigoshop_db_version = 1111092;
					break;
			}
		}
		return $jigoshop_db_version;
	}

	// Analyze
	function analyze() {
		global $wpdb;

		$jigoshop_version = $this->jigoshop_version();

		echo '<div class="narrow">';

		// show error message when JigoShop plugin is active
		if ( class_exists( 'jigoshop' ) )
			echo '<div class="error"><p>'.__('Please deactivate your JigoShop plugin.', 'woo_jigo').'</p></div>';

		echo '<p>'.__('Analyzing JigoShop orders&hellip;', 'woo_jigo').'</p>';

		echo '<ol>';

		if ( $jigoshop_version < 1202010 ) {
			$q = "
				SELECT p.ID
				FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
				WHERE
					p.post_type = 'shop_order'
					AND pm.meta_key = 'order_data'
					AND pm.meta_value != ''
					AND pm.post_id = p.ID
				";
			$product_ids = $wpdb->get_col($q);
			$products = count($product_ids);
			printf( '<li>'.__('<b>%d</b> orders were identified', 'woo_jigo').'</li>', $products );
		}
		else {
			$q = "
				SELECT ID
				FROM $wpdb->posts
				WHERE post_type = 'shop_order'
				";
			$product_ids = $wpdb->get_col($q);
			$products = count($product_ids);
			printf( '<li>'.__('<b>%d</b> "possible" orders were identified', 'woo_jigo').'</li>', $products );
		}

		echo '</ol>';

			?>
			<form name="woo_jigo" id="woo_jigo" action="admin.php?import=woo_jigo&amp;step=1" method="post">
			<?php wp_nonce_field('woo_jigo_converter'); ?>
			<p class="submit"><input type="submit" name="submit" class="button" value="<?php _e('Convert Now', 'woo_jigo'); ?>" /></p>
			</form>
			<?php

			echo '<p>'.__('<b>Please backup your database first</b>. We are not responsible for any harm or wrong doing this plugin may cause. Users are fully responsible for their own use. This plugin is to be used WITHOUT warranty.', 'woo_jigo').'</p>';


		echo '</div>';
	}

	// Import Orders
	function woo_import_jigo_orders() {
	global $wpdb;
	
	$sql = 'SELECT * FROM ' . $wpdb->users . '';
	$users = $wpdb->get_results( $sql );
	
	echo '<h3>Transfering Orders</h3>';
	
	//echo '<br /><br /><br /><br />';
	
	echo '<ol>';
	
	foreach ( $users as $user ):
		
		$jigo_orders = new jigoshop_orders();
		
		echo '<li>';
		
		echo '<h4>User: ' . $user->ID .'</h4>';
			
		$jigo_orders->get_customer_orders( $user->ID );
		
		$orders = $jigo_orders->orders;
		
		/*
if( !is_admin() && 11==22):
			echo '<pre>';
			print_r( $orders );
			echo '</pre>';
		endif;
*/
		
		foreach( $orders AS $key => $order ):
			
			$items = $item_id = $jigo_orders->orders[$key]->_data['items'];
			
			$order_id = $jigo_orders->orders[$key]->_data['id'];
			$order_key = $jigo_orders->orders[$key]->_data['order_key'];
			$order_discount = $jigo_orders->orders[$key]->_data['order_discount'];
			
			$user_id = $jigo_orders->orders[$key]->_data['user_id'];
			
			$billing_first_name = $jigo_orders->orders[$key]->_data['billing_first_name'];
			$billing_last_name = $jigo_orders->orders[$key]->_data['billing_last_name'];
			$billing_company = $jigo_orders->orders[$key]->_data['billing_company'];
			$billing_address_1 = $jigo_orders->orders[$key]->_data['billing_address_1'];
			$billing_address_2 = $jigo_orders->orders[$key]->_data['billing_address_2'];
			$billing_city = $jigo_orders->orders[$key]->_data['billing_city'];
			$billing_postcode = $jigo_orders->orders[$key]->_data['billing_postcode'];
			$billing_country = $jigo_orders->orders[$key]->_data['billing_country'];
			$billing_state = $jigo_orders->orders[$key]->_data['billing_state'];
			// $billing_email = $jigo_orders->orders[$key]->_data['billing_email'];
			$billing_phone = $jigo_orders->orders[$key]->_data['billing_phone'];
			$payment_method = $jigo_orders->orders[$key]->_data['payment_method'];
			$payment_method_title = $jigo_orders->orders[$key]->_data['payment_method_title'];
			
			
			echo 'Order ID: ' .  $order_id . '<br /><ul>';

			
			$order_items = array();
			
			$tax_total = 0;
			$order_total = 0;
			
			foreach ( $items as $item_key => $item ):
				$item_id = $item['id'];
				$item_name = $item['name'];
				$item_qty = $item['qty'];
				$item_cost = $item['cost'];
				$item_taxrate = $item['taxrate'];
				$item_tax = $item_cost / 100 * $item_taxrate;
				$item_total = $item_cost * $item_qty;
				
				echo '<li>'. $item_name .'</li>';
				
				$order_items[] = array(
			 		'id' 				=> $item_id,
			 		'name' 				=> $item_name,
			 		'qty' 				=> (int) $item_qty,
			 		'line_subtotal'		=> $item_cost,
			 		'line_subtotal_tax' => $item_tax,
			 		'line_total'		=> $item_total,
			 		'line_tax'			=> $item_tax * $item_qty
			 	);
				
				$tax_total += $item_tax;
				$order_total += $item_total;
				
				//order_item_id 1 || order_item_name Example Product Name || order_item_type line_item || order_id 4138
				
				//add item to woocommerce items with woocommerce_add_order_item
				$newitem_id = woocommerce_add_order_item( $order_id, array(
                    'order_item_name'       => $item_name,
                    'order_item_type'       => 'line_item'
					) );
            
					//add item meta to woocommerce_add_order_item_meta

					if ( $newitem_id ) {

						woocommerce_add_order_item_meta( $newitem_id,'_qty',(int) $item_qty);
						//woocommerce_add_order_item_meta( $item_id,'_tax_class', '');
						woocommerce_add_order_item_meta( $newitem_id,'_product_id', $item_id);
						woocommerce_add_order_item_meta( $newitem_id,'_variation_id', $item_variation_id);
						woocommerce_add_order_item_meta( $newitem_id,'_line_subtotal', $item_cost);
						woocommerce_add_order_item_meta( $newitem_id,'_line_subtotal_tax', $item_tax);
						woocommerce_add_order_item_meta( $newitem_id,'_line_total', $item_total);
						woocommerce_add_order_item_meta( $newitem_id,'_line_tax', $item_tax * $item_qty);

             }
				
			endforeach;
			
			echo '</ul>';
			
			update_post_meta( $order_id, '_order_tax', 				number_format( $tax_total, 2, '.', '' ) );
			update_post_meta( $order_id, '_order_total', 			number_format( $order_total, 2, '.', '' ) );
			update_post_meta( $order_id, '_order_key', 				$order_key );
			update_post_meta( $order_id, '_order_discount', 		$order_discount );
			
			//woocommerce doesn't use order_items
			//update_post_meta( $order_id, '_order_items', 			$order_items );
			update_post_meta( $order_id, '_customer_user', 			(int) $user_id );
			
			update_post_meta( $order_id, '_billing_first_name', 	$billing_first_name );
			update_post_meta( $order_id, '_billing_last_name', 		$billing_last_name );
			update_post_meta( $order_id, '_billing_company', 		$billing_company );
			update_post_meta( $order_id, '_billing_address_1', 		$billing_address_1 );
			update_post_meta( $order_id, '_billing_address_2', 		$billing_address_2 );
			update_post_meta( $order_id, '_billing_city', 			$billing_city );
			update_post_meta( $order_id, '_billing_country', 		$billing_country );
			update_post_meta( $order_id, '_billing_state', 			$billing_state );
			update_post_meta( $order_id, '_billing_postcode', 		$billing_postcode );
			update_post_meta( $order_id, '_billing_phone', 			$billing_phone );
			update_post_meta( $order_id, '_billing_email', 			$user->user_email );
			
			update_post_meta( $order_id, '_payment_method', 		$payment_method );
			update_post_meta( $order_id, '_payment_method_title', 	$payment_method_title );
			
			woocommerce_downloadable_product_permissions( $order_id );
		
		endforeach;
		
		echo '</li>';
	endforeach;
	echo '</ol>';
	
	}//end import

}

} // class_exists( 'WP_Importer' )

function woo_import_jigo_orders_init() {
	$GLOBALS['woo_jigo'] = new Woo_Jigo_Converter();
	register_importer( 'woo_jigo', 'JigoShop To WooCommerce Order Converter', __('Convert order, order items, and user data from JigoShop to WooCommerce.', 'woo_jigo'), array( $GLOBALS['woo_jigo'], 'dispatch' ) );

}
add_action( 'admin_init', 'woo_import_jigo_orders_init' );