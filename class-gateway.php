<?php


if (!class_exists('WC_Payment_Gateway')) {
	return;
}
class Paytwit_WC_gateway extends WC_Payment_Gateway {

	private $login_account;
	private $thanks_text;
    private $debug_mode="no";
    private $error_message;
    private $logo;

	public function __construct()
	{
		$this->id = 'paytwit_pay_gateway';
        $this->logo           = $this->get_option( 'paytwit_logo' ) ? $this->get_option( 'paytwit_logo' )  : 'pay';
        $this->icon           = sprintf( '%sassets/images/%s.png ', paytwit_URL, $this->logo );
		$this->has_fields = true;
		$this->method_title = __('Paytwit', 'paytwit');
		$this->method_description = __('Official paytwit.com electronic payment gateway for Woocommerce', 'paytwit');

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title = $this->get_option('paytwit_title');
		$this->description = $this->get_option('paytwit_description');
		$this->login_account = $this->debug_mode == 'yes' ? 'test' : $this->get_option('login_account');
		$this->thanks_text = $this->get_option('paytwit_thanks_text');
		$this->error_message = $this->get_option('paytwit_error_message');


		// Actions
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_receipt_'.$this->id, array($this, 'send_to_bank'));
		add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'return_from_bank'));
		add_filter( 'woocommerce_get_order_item_totals', array($this, 'show_transaction_in_order'), 10, 2 );
		add_filter( 'woocommerce_thankyou_order_received_text' , array($this, 'custom_thankyou_text'),10);


	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'paytwit'),
				'type' => 'checkbox',
				'label' => __('Enable pay gateway', 'paytwit'),
				'default' => 'yes',
			),
			'paytwit_title' => array(
				'title' => __('Title', 'paytwit'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'paytwit'),
				'default' => __('PAY gateway for Woocommerce', 'paytwit'),
				'desc_tip' => true,
			),
            'paytwit_logo' => array(
                'title'   => __('Logo', 'paytwit'),
                'type'    => 'select',
                'default' => 'pay',
                'options' => array(
                    'paytwit'   => __('Paytwit', 'paytwit'),
                    'saman' => __('Saman', 'paytwit'),
                ),
            ),
			'paytwit_description' => array(
				'title' => __('Description', 'paytwit'),
				'type' => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'paytwit'),
				'default' => __('Paytwit electronic payment gateway', 'paytwit'),
				'desc_tip' => true,
			),
			'login_account' => array(
				'title' => __('api key', 'paytwit'),
				'type' => 'text',
				'description' => __('Insert merchant id that received from pay', 'paytwit'),
			),
			'paytwit_thanks_text' => array(
				'title' => __('successful payment message', 'paytwit'),
				'type' => 'textarea',
				'description' => __('thanks text for successful payment', 'paytwit'),
			),
			'paytwit_error_message' => array(
				'title' => __('Failed payment message', 'paytwit'),
				'type' => 'textarea',
				'description' => __('Text for failed payment', 'paytwit'),
			)
            
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url($order),
		);
	}

	/**
	 * Make ready for send to bank.
	 */
	public function send_to_bank($order_id)
	{
		_e('Thank you for your payment. redirecting to bank...', 'paytwit');
		$this->post_form($order_id);
	}

	public function post_form($order_id)
	{
		$order = wc_get_order($order_id);
		$currency = $order->get_currency();
		$amount = intval($order->get_total());
		if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN')
		    || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN')
		    || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN')
		    || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN')
		    || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
		) {
			$amount = $amount * 10;
		} elseif (strtolower($currency) == strtolower('IRHT')) {
			$amount = $amount * 1000 * 10;
		} elseif (strtolower($currency) == strtolower('IRHR')) {
			$amount = $amount * 1000;
		}
		$login_account = $this->login_account;
		$unique_order_id = time();
		$callback_url = add_query_arg('wc_order', $order_id, WC()->api_request_url('Paytwit_WC_gateway'));
		WC()->session->set( 'pay_order_id' , $order_id );
		$url='https://www.paytwit.com/api/v1/sendgateway';
		$id = '1000033747' ;
		$response = wp_remote_post( $url, array(
				'body' => array(
					'api' => $login_account,
					'amount' => $amount,
					'redirect' => $callback_url,
					'factorNumber' => $unique_order_id,
					'resellerId' => $id
				)
			)
		);


		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			$body=wp_remote_retrieve_body($response);
			$result = json_decode($body);
			
			$status= $result->status;
			if($status == 1){
				// Send post method
				$trans_id=$result->transId;
				$send_url='https://paytwit.com/gateway/'.$trans_id;
				wp_redirect($send_url, 301);
			} else {
				$error_message = $result->errorMessage;
				wc_add_notice( __('Payment error:', 'paytwit') . $error_message, 'error' );
				wp_redirect(wc_get_cart_url(), 301);
				exit();
			}
		}
	}

	public function return_from_bank() {
		$login_account = $this->login_account;
		if ( isset($_GET['wc_order']) ) {
			$order_id = absint( $_GET['wc_order'] );
		} else {
			$order_id = absint( WC()->session->get( 'pay_order_id' ) );
		}
		if ( isset($order_id) && !empty($order_id) ) {
			$order = wc_get_order($order_id);
			if ($order->get_status() !== 'completed') {

				// Get data from bank
				$status= $_GET['status'];
				$transId=$_GET['transId'];
				//$factorNumber=$_POST['factorNumber'];
				//$cardNumber=$_POST['cardNumber'];
				//$message=$_POST['message'];

				if($status == 1 ) {
					// BOOM! Payment completed!
					$url='https://www.paytwit.com/api/v1/verifygateway';
					$response = wp_remote_post( $url, array(
							'body' => array(
								'api' => $login_account,
								'transId' => $transId
							)
						)
					);

					

					
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						echo "Something went wrong: $error_message";
					} else {
						$body=wp_remote_retrieve_body($response);
						$result = json_decode($body);


						$status= $result->status;
						if($status == 1){
							// transaction is complete
							wc_reduce_stock_levels($order_id);
							WC()->cart->empty_cart();
							$message = sprintf(
								__('Payment was successful %s Tracking Code: %s', 'paytwit'),
								'<br />',
								$transId
							);
							update_post_meta($order_id , 'pay_trace_number' , $transId);
							$order->add_order_note($message, 1);
							$order->payment_complete();
							$successful_page = add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) );
							wp_redirect( $successful_page );
							exit();
						} else {
							$error_message = $result->errorMessage;
							wc_add_notice( __('Payment error: ', 'paytwit') . $error_message, 'error' );
							wp_redirect(wc_get_cart_url(), 301);
							exit();
						}
					}

				} else {
                    if( $this->error_message ) {
                        $message = $this->error_message ;
                    }
					wc_add_notice( __('Payment error: ', 'paytwit') . $message, 'error' );
					wp_redirect( wc_get_cart_url() );
					exit();
				}
			}
		}
	}

	public function show_transaction_in_order($total_rows, $order) {
		$gateway = $order->get_payment_method();
		if ($gateway === $this->id) {
			$trace_number = get_post_meta( $order->id, 'pay_trace_number', true );
			$total_rows['trace_number'] = array(
				'label' => __( 'Tracking Code:', 'paytwit' ),
				'value' => $trace_number
			);
		}
		return $total_rows;
	}
	public function custom_thankyou_text($thank_message){
		if (!empty($this->thanks_text)){
			return $this->thanks_text;
		}
		return $thank_message;
	}


}