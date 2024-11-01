<?php
/*
	Plugin Name: WooCommerce VirtualCoin Services Gateway
	Plugin URI: https://blog.virtualcoin.biz/services/wordpress-plugin-payment-gateway/
	Description: A payment gateway that use VirtualCoin Services to accept your favorite cryptocurrencies like Bitcoin, Litecoin, Dogecoin, ecc.. as payment method
	Version: 1.0.7
	Author: PinoVero
	Author URI: https://www.virtualcoin.biz
	Requires at least: 4.4
	Tested up to: 4.9.6
*/


load_plugin_textdomain( 'wc_vcsg', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_vcsg_init', 0 );

function woocommerce_vcsg_init () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	require_once( plugin_basename( 'classes/vcsg.class.php' ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_vcsg_add_gateway' );
} // End woocommerce_vcsg_init()

function woocommerce_vcsg_add_gateway($methods){
	$methods[] = 'WC_Gateway_VCSG'; 
  return $methods;
}

add_filter( 'woocommerce_currencies', 'add_vcsg_crypto' );
function add_vcsg_crypto( $currencies ) {
     $currencies['LTC'] = __( 'LiteCoin', 'woocommerce' );
     $currencies['DOGE'] = __( 'DogeCoin', 'woocommerce' );
     return $currencies;
}

add_filter('woocommerce_currency_symbol', 'add_vcsg_crypto_symbol', 10, 2);
function add_vcsg_crypto_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
         case 'LTC': $currency_symbol = 'L'; break;
         case 'DOGE': $currency_symbol = '&ETH;'; break;         
     }
     return $currency_symbol;
}
?>