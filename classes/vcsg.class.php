<?php
/**
 * VirtualCoin Services Payment Gateway
 *
 * Use VirtualCoin as Payment Gateway.
 *
 * @class 		woocommerce_gateway_vcs
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		PinoVero
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * plugin_url()
 * add_currency()
 * add_currency_symbol()
 * is_valid_for_use()
 * get_country_code()
 * admin_options()
 * payment_fields()
 * generate_vcs_form()
 * process_payment()
 * receipt_page()
 * prepare_form_fields()
 * check_response_is_valid()
 * check_response()
 * successful_request()
 * log()
 * amounts_equal()
 * add_gateway()
 * ssl_check()
 */
class WC_Gateway_VCSG extends WC_Payment_Gateway {

	public function __construct() {
    global $woocommerce;
    $this->id			= 'vcsg';
    $this->method_title = __( 'VirtualCoin Services Gateway', 'wc_vcsg' );
    $this->icon 		= apply_filters( 'woocommerce_vcsg_icon', $this->plugin_url() . '/assets/images/vcs-logo.png' );
    $this->has_fields 	= true;
    $this->debug_email 	= get_option( 'admin_email' );
    $this->wc_version 	= get_option( 'woocommerce_db_version' );

		// Load form fields
		$this->init_form_fields();

		// Load settings
		$this->init_settings();
		$this->url = 'https://api.virtualcoin.biz/checkout.php';
		$this->title = $this->settings['title'];

		// check for woocommerce version < 2
		if(version_compare($this->wc_version, '2.0', '<')){
			add_action('init',array($this, 'check_response'));
			$this->callback_url = home_url( '/' );
		}else{
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_response') );
			$this->callback_url = $woocommerce->api_request_url( get_class( $this ) );
		}
		add_action( 'valid-vcsg-response', array( $this, 'successful_request' ) );
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_vcsg', array( $this, 'receipt_page' ) );

		if(!$this->is_valid_for_use()){$this->enabled = false;}// Check if enabled
  }

    // Admin Gateway Settings Fields
    function init_form_fields(){
    	$this->form_fields = array(
							'enabled' => array(
											'title' => __( 'Enable/Disable', 'wc_vcsg' ),
											'label' => __( 'Enable VC Gateway', 'wc_vcsg' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'wc_vcsg' ),
											'default' => 'yes'
										),
							'title' => array(
    										'title' => __( 'Title', 'wc_vcsg' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'wc_vcsg' ),
    										'default' => __( 'VirtualCoin Services', 'wc_vcsg' )
    									),
							'description' => array(
											'title' => __( 'Description', 'wc_vcsg' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'wc_vcsg' ),
											'default' => __( 'Pay using the VirtualCoin gateway.', 'wc_vcsg' )
										),
							'terminal_id' => array(
											'title' => __( 'Your VC username', 'wc_vcsg' ),
											'type' => 'text',
											'description' => __( 'The VirtualCoin username you want to use with your store.', 'wc_vcsg' ),
											'default' => ''
										),
							'apikey' => array(
											'title' => __( 'Your generated API Key', 'wc_vcsg' ),
											'type' => 'password',
											'description' => __( 'Place here the API Key generated from your VirtualCoin profile.', 'wc_vcsg' ),
											'default' => ''
										),
							'apipin' => array(
											'title' => __( 'Your PIN code', 'wc_vcsg' ),
											'type' => 'password',
											'description' => __( 'Place here the PIN code you submit in your VirtualCoin profile.', 'wc_vcsg' ),
											'default' => ''
										),										
							'pam' => array(
											'title' => __( 'Thanks Message', 'wc_vcsg' ),
											'type' => 'text',
											'description' => __( 'This is the personal thanks message.', 'wc_vcsg' ),
											'default' => sprintf( __( 'Thank you for shopping at %s', 'wc_vcsg' ), get_bloginfo( 'name' ) )
										),
							);

    }

	// Get the plugin URL
	function plugin_url(){
		if( isset( $this->plugin_url ) ) return $this->plugin_url;
		if(is_ssl()){
			return $this->plugin_url = str_replace('http://', 'https://', WP_PLUGIN_URL) . "/" . plugin_basename( dirname(dirname(__FILE__)));
		}else{return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname(dirname(__FILE__)));}
	}

  // is_valid_for_use()
  // Check if this gateway is enabled
	function is_valid_for_use(){
		global $woocommerce;
		$is_available = false;
    $user_currency = get_option( 'woocommerce_currency' );
    $is_available_currency = true;
		if($is_available_currency && $this->enabled == 'yes' && $this->settings['terminal_id'] != ''){$is_available = true;}
    return $is_available;
	}

	// get_country_code()
  // Get the users country either from their order, or from their customer data
	function get_country_code(){
		global $woocommerce;
		$base_country = $woocommerce->countries->get_base_country();
		return $base_country;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}
    	?>
    	<h3><?php _e( 'VirtualCoin Services', 'wc_vcsg' ); ?></h3>
    	<p><?php printf( __( 'The VirtualCoin Payment Gateway works by directing the visitor to %sVirtualCoin API Checkout%s page to enter their payment information.', 'wc_vcsg' ), '<a href="https://www.virtualcoin.biz/">', '</a>' ); ?></p>

    	<table class="form-table"><?php
		// Generate the HTML For the settings form.
		$this->generate_settings_html();
		?><tr valign="top">
			<td colspan="2">

			</td>
		</tr>
		</table><!--/.form-table-->

    	<?php
    } // End admin_options()


	  // There are no payment fields for vcsg, but we want to show the description if set.
    function payment_fields(){
    	$user_country = $this->get_country_code();
 		  if(empty($user_country)){
			 _e( 'Please complete your billing information before entering payment details.', 'wc_vcsg' );
			 return;
		  }
    	if(isset( $this->settings['description'] ) && ( '' != $this->settings['description'])){echo wpautop( wptexturize( $this->settings['description'] ) );}
    }

  	// Generate the Virtual Card Services button link.
    public function generate_vcsg_form( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		$fields = $this->prepare_form_fields( $order );
		$vcsg_args_array = array();
		foreach ( $fields as $key => $value ){
			$vcsg_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}

		return '<form action="' . $this->url . '" method="post" id="vcsg_payment_form">
		        <input type="hidden" name="action" value="test">
		        <input type="hidden" name="order" value="'.$order.'">
				' . implode( '', $vcsg_args_array ) . '
				<input type="submit" class="button-alt" id="submit_vcsg_payment_form" value="' . __( 'Pay via VirtualCoin Services', 'wc_vcsg' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'wc_vcsg' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to VirtualCoin Services to make payment.', 'wc_vcsg' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
						jQuery( "#submit_vcsg_payment_form" ).click();
					});
				</script>
			</form>';

		$order->add_order_note( __( 'Customer was redirected to VCS.', 'wc_vcsg' ) );
	}

  // Process the payment and return the result.
	function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );
		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);
	}

	/**
	 * Receipt page.
	 *
	 * Display text and a button to direct the user to the payment screen.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with VirtualCoin Services.', 'wc_vcsg' ) . '</p>';
		echo $this->generate_vcsg_form( $order );
	} // End receipt_page()

	/**
     * prepare_form_fields()
     *
     * Prepare the fields to be submitted to Virtual Card Services.
     *
     * @param object $order
     * @return array
     */
    function prepare_form_fields( $order ) {
    	global $woocommerce;

        $amount     = $order->order_total;
        $currency   = get_option( 'woocommerce_currency' );

        $params = array(
            'p1'        =>  $this->settings['terminal_id'],
            'p2'        =>  $order->id,
            'p3'        =>  sprintf( __( '%s purchase, Order # %d', 'wc_vcsg' ), get_bloginfo( 'name' ), $order->id ),
            'p4'        =>  $amount,
            'p5'        =>  $currency,
            'p10' 		=> 	$order->get_cancel_order_url(), // The URL to direct to when the customer clicks "Cancel" on VCS.
            'p11'     =>  $woocommerce->cart->get_checkout_url(), 
            'apikey'  =>  $this->settings['apikey'],
            'apipin'  =>  $this->settings['apipin'],
        );
        //$params['apikey'] = $this->settings['apikey'];
        //$params['apipin'] = $this->settings['apipin'];
        if ( $this->settings['pam'] != '' ) {
            $params['m_1'] = md5( $this->settings['pam'] . '::' . $params['p2'] );
        }

        $params['m_2'] = $order->order_key; // This is returned to us when returning from VCS.

    	return $params;
    } // End prepare_form_fields()

	/**
	 * Check VCS response validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	function check_response_is_valid( $data ){
		global $woocommerce;

		$has_error = false;
		$error_message = '';
		$is_done = false;

		$order_id = (int) $data['p2'];
		$order_key = esc_attr( $data['m_2'] );
		$order = new WC_Order( $order_id );

		$data_string = '';

		$this->log( "\n" . '----------' . "\n" . 'VCS response received' );

		// check transaction password
        if ( $this->settings['pam'] != '' && ! $has_error && ! $is_done ) {
            if ( $this->settings['pam'] != $data['pam'] ) {
                $has_error = true;

                $error_message = 'Transaction password incorrect.';
                $this->log( $error_message );
            }
            if ( $data['m_1'] != md5( $data['pam'] . '::' . $data['p2'] ) && ! $has_error && ! $is_done ) {
            	$has_error = true;

            	$error_message = 'Checksum mismatch.';
            	$this->log( $error_message );
            }
        }

		// check transaction status
		if( ! empty( $data['p3'] ) && substr( $data ['p3'], 6, 8 ) != 'APPROVED' && ! $has_error && ! $is_done ) {
			$has_error = true;

			$error_message = 'Transaction was not successful.';
			$this->log( $error_message );

			$order->update_status( 'failed', sprintf( __( 'Payment failed via VCS. Response: %s', 'wc_vcsg' ), esc_attr( $data['p3'] ) ) );
			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

        // Get data sent by the gateway
        if ( ! $has_error && ! $is_done ) {
        	$this->log( 'Get posted data' );

            $this->log( 'VCS Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $has_error = true;
                $error_message = 'Bad access on page.';
            }
        }

        // Get internal order and verify it hasn't already been processed
        if( ! $has_error && ! $is_done ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $is_done = true;
            }
        }

        // Check data against internal order
        if( ! $has_error && ! $is_done ) {
            $this->log( 'Check data against internal order' );
            $this->log( 'Total from VCS: ' . $data['p6'] );
            $this->log( 'Total stored internally: ' . $order->order_total );

            // Check order amount
            if( ! $this->amounts_equal( $data['p6'], $order->order_total ) ) {
                $has_error = true;
                $error_message = 'Order totals don\'t match.';
            }
            // Check session ID
            elseif( $data['m_2'] != $order->order_key ) {
                $has_error = true;
                $error_message = 'Order key mismatch.';
            }
        }

        // If an error occurred
        if( $has_error ) {
            $this->log( 'Error occurred: ' . $error_message );
            $is_done = false;
        } else {
        	$this->log( 'Transaction completed.' );
        	$is_done = true;

        	// Payment completed
            $order->payment_complete();
			$order->add_order_note( sprintf( __( 'Payment via VCS completed. Response: %s', 'wc_vcsg' ), $posted['p3'] ) );

			// Empty the Cart
			$woocommerce->cart->empty_cart();
        }

        // Close log
        $this->log( '', true );

    	return $is_done;
    } // End check_response_is_valid()


	// Check gateway response.
	function check_response() {
		// Clean
		@ob_clean();

		// Header
		header( 'HTTP/1.1 200 OK' );

		if ( ! empty( $_POST ) && isset( $_POST['p2'] ) && is_numeric( $_POST['p2'] ) ) {
			$_POST = stripslashes_deep( $_POST );
			if ( $this->check_response_is_valid( $_POST ) ) {do_action( 'valid-vcsg-response', (int) $_POST['p2'] );}
		}
	}

	// Successful Payment!
	function successful_request( $order_id = 0 ) {
		global $woocommerce;
		if ( ! $order_id ) return false;
		$order = new WC_Order( $order_id );
		wp_redirect( $this->get_return_url( $order ) );
		exit;
	}

  // Log system processes
	function log ( $message, $close = false ) {
		if(($this->settings['testmode'] != 'yes' && ! is_admin())){return;}
		static $fh = 0;
		if($close){fclose($fh);
        }else{
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = fopen( $dir .'/vcsg.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";
                fwrite( $fh, $line );
            }
        }
	}

	/*
	 Checks to see whether the given amounts are equal using a proper floating
	 point comparison with an Epsilon which ensures that insignificant decimal
	 places are ignored in the comparison.

	 eg. 100.00 is equal to 100.0001

	 @param $amount1 Float 1st amount for comparison
   @param $amount2 Float 2nd amount for comparison
	*/	 
	function amounts_equal($amount1,$amount2){
    if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > 0.01 )
      return( false );
	  else
	    return( true );
	}
} // End Class
?>