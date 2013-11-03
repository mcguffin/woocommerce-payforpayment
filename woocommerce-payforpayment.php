<?php
/*
Plugin Name: WooCommerce Pay for Payment
Plugin URI: https://github.com/mcguffin/woocommerce-payforpayment
Description: Add extra fees depending on your payment methods.
Version: 0.0.2
Author: Jörn Lund
Author URI: https://github.com/mcguffin

Text Domain: chargepayment
Domain Path: /lang/
*/


class Pay4Pay {

	function __construct() {
		global $woocommerce;
		load_plugin_textdomain( 'pay4pay' , false, dirname( plugin_basename( __FILE__ )) . '/lang' );
		add_filter( 'before_woocommerce_init' , array($this, 'add_payment_options') );
		add_action( 'woocommerce_before_calculate_totals' , array($this,'add_pay4payment' ) );
		add_action( 'woocommerce_review_order_after_submit' , array($this,'print_autoload_js') );

	}
	
	
	function print_autoload_js(){
		?><script type="text/javascript">
jQuery(document).ready(function($){
	$(document.body).on('change', 'input[name="payment_method"]', function() {
		$('body').trigger('update_checkout');
		$.ajax( $fragment_refresh );
	});
});
 		</script><?php 
	}
	
	function add_pay4payment( /*$cart_object*/ ) {
		global $woocommerce;
		
		$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		$current_gateway = '';
		$default_gateway = get_option( 'woocommerce_default_gateway' );
		if ( ! empty( $available_gateways ) ) {
			
		   // Chosen Method
			if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
				$current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
			} elseif ( isset( $available_gateways[ $default_gateway ] ) ) {
				$current_gateway = $available_gateways[ $default_gateway ];
			} else {
				$current_gateway =	current( $available_gateways );
			}
		
			if ( isset( $current_gateway->settings['pay4pay_charges_fixed']) ) {
				$cost = $current_gateway->settings['pay4pay_charges_fixed'];
				if ( $percent = $current_gateway->settings['pay4pay_charges_percentage'] ) {
					$subtotal = 0;
					foreach ( $woocommerce->cart->cart_contents as $key => $value ) {
						$subtotal += $value['data']->price * $value['quantity'];
					}
					$cost += $subtotal * ($percent / 100 );
				}
				$taxable = $current_gateway->settings['pay4pay_taxes'] !== 0;
				if ( $current_gateway->settings['pay4pay_taxes'] == 'incl' ) {
					$tax = new WC_Tax();
					$taxrates = array_shift($tax->get_shop_base_rate());
					$taxrate = floatval( $taxrates['rate']) / 100;
					$cost = ($cost / (1+$taxrate));
				}
				if ( $cost != 0 ) {
					$woocommerce->cart->add_fee( $current_gateway->title . ' payment method fees' , $cost, $taxable );
				}
			}
		}
	}

	function add_payment_options( $some ) {
		global $woocommerce;
		foreach ( $woocommerce->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
			$gateway->form_fields += array(
				'pay4pay_charges_fixed' => array(
					'title' => __( 'Fixed charge', 'pay4pay' ),
					'type' => 'number',
					'description' => __( 'Extra charge to be added to cart when this payment method is selected.', 'pay4pay' ),
					'default' => 0,
					'desc_tip' => true,
					'custom_attributes' => array(
						'step' => 'any',
					),
				),
				'pay4pay_charges_percentage' => array(
					'title' => __( 'Percent charge', 'pay4pay' ),
					'type' => 'number',
					'description' => __( 'Percentage of cart total to be added to payment.', 'pay4pay' ),
					'default' => 0,
					'desc_tip' => true,
					'custom_attributes' => array(
						'step' => 'any',
					),
				),
				'pay4pay_taxes' => array(
					'title' => __('Includes Taxes','pay4pay'),
					'type' => 'select',
					'description' => __( 'Select an option to handle taxes for the extra charges specified above.', 'pay4pay' ),
					'options' => array(
						0 => __( 'No taxes', 'pay4pay' ),
						'incl' => __( 'Including tax', 'woocommerce' ),
						'excl' => __( 'Excluding tax', 'woocommerce' ),
					),
					'default' => 'incl',
					'desc_tip' => true,
				),
			);
			add_action( 'woocommerce_update_options_payment_gateways_'.$gateway->id , array($this,'update_payment_options') , 20 );
		}
		return $some;
	}
	
	function update_payment_options(  ) {
		global $woocommerce, $woocommerce_settings, $current_section, $current_tab;
		$class = new $current_section();
		$prefix = 'woocommerce_'.$class->id;
		$opt_name = $prefix.'_settings';
		$options = get_option( $opt_name );
		
		// validate!
		$extra = array(
			'pay4pay_charges_fixed' => floatval( $_POST[$prefix.'_pay4pay_charges_fixed'] ),
			'pay4pay_charges_percentage' => floatval( $_POST[$prefix.'_pay4pay_charges_percentage'] ),
			'pay4pay_taxes' => $_POST[$prefix.'_pay4pay_taxes'], // 0, incl, excl
		);
		$options += $extra;
		update_option( $opt_name , $options );
	}
	
}

new Pay4Pay();




?>
