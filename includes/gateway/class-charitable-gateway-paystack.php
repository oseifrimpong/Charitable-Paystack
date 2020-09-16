<?php
/**
 * Paystack Gateway class.
 *
 * @package   Charitable Paystack/Classes/Charitable_Gateway_Paystack
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Gateway_Paystack' ) ) :

	/**
	 * Paystack Gateway.
	 *
	 * @since 1.0.0
	 */
	class Charitable_Gateway_Paystack extends Charitable_Gateway {

		/** The gateway ID. */
		const ID = 'paystack';

		/**
		 * API object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Paystack_API
		 */
		private $api;

		/**
		 * Instantiate the gateway class, defining its key values.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			/**
			 * Change the Paystack gateway name as its shown in the gateway settings page.
			 *
			 * @since 1.0.0
			 *
			 * @param string $name The gateway name.
			 */
			$this->name = apply_filters( 'charitable_gateway_paystack_name', __( 'Paystack', 'charitable-paystack' ) );

			$this->defaults = [
				'label' => __( 'Paystack', 'charitable-paystack' ),
			];

			$this->supports = [
				'1.3.0',
			];

			/**
			 * Needed for backwards compatibility with Charitable < 1.3
			 */
			$this->credit_card_form = false;
		}

		/**
		 * Returns the current gateway's ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public static function get_gateway_id() {
			return self::ID;
		}

		/**
		 * Register gateway settings.
		 *
		 * @since  1.0.0
		 *
		 * @param  array[] $settings Default array of settings for the gateway.
		 * @return array[]
		 */
		public function gateway_settings( $settings ) {
			$settings['test_secret_key'] = [
				'type'     => 'text',
				'title'    => __( 'Test Secret Key', 'charitable-paystack' ),
				'priority' => 6,
				'class'    => 'wide',
			];

			$settings['test_public_key'] = [
				'type'     => 'text',
				'title'    => __( 'Test Publishable Key', 'charitable-paystack' ),
				'priority' => 8,
				'class'    => 'wide',
			];

			return $settings;
		}

		/**
		 * Register the payment gateway class.
		 *
		 * @since  1.0.0
		 *
		 * @param  string[] $gateways The list of registered gateways.
		 * @return string[]
		 */
		public static function register_gateway( $gateways ) {
			$gateways['paystack'] = 'Charitable_Gateway_Paystack';
			return $gateways;
		}

		/**
		 * Return the keys to use.
		 *
		 * This will return the test keys if test mode is enabled. Otherwise, returns
		 * the production keys.
		 *
		 * @since  1.0.0
		 *
		 * @return string[]
		 */
		public function get_keys() {
			$keys = [];

			if ( charitable_get_option( 'test_mode' ) ) {
				$keys['secret_key'] = trim( $this->get_value( 'test_secret_key' ) );
				$keys['public_key'] = trim( $this->get_value( 'test_public_key' ) );
			} else {
				$keys['secret_key'] = trim( $this->get_value( 'live_secret_key' ) );
				$keys['public_key'] = trim( $this->get_value( 'live_public_key' ) );
			}

			return $keys;
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $key    The key of the field to get.
		 * @param  mixed[] $values Set of values to find the values in.
		 * @return string|false
		 */
		public function get_gateway_value( $key, $values ) {
			return isset( $values['gateways']['paystack'][ $key ] ) ? $values['gateways']['paystack'][ $key ] : false;
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string                        $key       The key of the field to get.
		 * @param  Charitable_Donation_Processor $processor Donation processor object.
		 * @return string|false
		 */
		public function get_gateway_value_from_processor( $key, Charitable_Donation_Processor $processor ) {
			return $this->get_gateway_value( $key, $processor->get_donation_data() );
		}

		/**
		 * Get the Paystack API object.
		 *
		 * @since  1.0.0
		 *
		 * @return Charitable_Paystack_API|false
		 */
		public function api() {
			if ( ! isset( $this->api ) ) {
				$keys = $this->get_keys();

				if ( empty( $keys['secret_key'] ) ) {
					return false;
				}

				$this->api = new Charitable_Paystack_API( $keys['secret_key'] );
			}

			return $this->api;
		}

		/**
		 * Validate the submitted credit card details.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean $valid   Whether the donation is valid.
		 * @param  string  $gateway The gateway for the donation.
		 * @param  mixed[] $values  Submitted donation values.
		 * @return boolean
		 */
		public static function validate_donation( $valid, $gateway, $values ) {
			if ( 'paystack' != $gateway ) {
				return $valid;
			}

			if ( ! isset( $values['gateways']['paystack'] ) ) {
				return false;
			}

			/**
			 * Check that the donation is valid.
			 *
			 * @todo
			 */

			return $valid;
		}

		/**
		 * Process the donation with the gateway.
		 *
		 * @since  1.0.0
		 *
		 * @param  mixed                         $return      Response to be returned.
		 * @param  int                           $donation_id The donation ID.
		 * @param  Charitable_Donation_Processor $processor   Donation processor object.
		 * @return boolean|array
		 */
		public function process_donation( $return, $donation_id, $processor ) {
			//error_log(__METHOD__);
			$gateway     = new Charitable_Gateway_Paystack();

			$donation    = charitable_get_donation( $donation_id );
			$donor       = $donation->get_donor();
			$values      = $processor->get_donation_data();

			// API keys
			$keys        = $gateway->get_keys();

			// Donation fields
			// $donation_key = $donation->get_donation_key();
			// $item_name    = sprintf( __( 'Donation %d', 'charitable-payu-money' ), $donation->ID );;
			// $description  = $donation->get_campaigns_donated_to();
			$amount 	  = $donation->get_total_donation_amount( true );

			// Donor fields
			// $first_name   = $donor->get_donor_meta( 'first_name' );
			// $last_name    = $donor->get_donor_meta( 'last_name' );
			// $address      = $donor->get_donor_meta( 'address' );
			// $address_2    = $donor->get_donor_meta( 'address_2' );
			$email 		  = $donor->get_donor_meta( 'email' );
			// $city         = $donor->get_donor_meta( 'city' );
			// $state        = $donor->get_donor_meta( 'state' );
			// $country      = $donor->get_donor_meta( 'country' );
			// $postcode     = $donor->get_donor_meta( 'postcode' );
			// $phone        = $donor->get_donor_meta( 'phone' );

			// URL fields
			$return_url = charitable_get_permalink( 'donation_receipt_page', [ 'donation_id' => $donation->ID ] );
			$cancel_url = charitable_get_permalink( 'donation_cancel_page', [ 'donation_id' => $donation->ID ] );
			// $notify_url = function_exists( 'charitable_get_ipn_url' )
			// 	? charitable_get_ipn_url( Charitable_Gateway_Paystack::ID )
			// 	: Charitable_Donation_Processor::get_instance()->get_ipn_url( Charitable_Gateway_Paystack::ID );

			/**
			 * Create donation charge through gateway.
			 *
			 * @todo
			 *
			 * You should return one of three values.
			 *
			 * 1. If the donation fails to be processed and the user should be
			 *    returned to the donation page, return false.
			 * 2. If the donation succeeds and the user should be directed to
			 *    the donation receipt, return true.
			 * 3. If the user should be redirected elsewhere (for example,
			 *    a gateway-hosted payment page), you should return an array
			 *    like this:

				[
					'redirect'	=> $redirect_url,
					'safe' 		=> false // Set to false if you are redirecting away from the site.
				];
			 *
			 */

			$currency 	= charitable_get_currency();

			$response 	= $gateway->api()->post(
				$method = 'transaction/initialize',
				$args 	= [
					'email'  		=> $email,
					'amount' 		=> $amount*100,
					'callback_url' 	=> $return_url,
					//'cancel_url' 	=> $cancel_url, 
					'currency' 		=> $currency,
					
				],
				$timeout = 10
			);

			//Catch failed requests
			$error = $gateway->api->get_last_result();
			
			if ( is_wp_error($error) ) {				
				return false;
			}

			//For when the post request works but the transaction fails
			if ( !$response->status) {
				charitable_get_notices()->add_error(
					sprintf(
						__( 'Donation failed in gateway with error: "%s"', 'charitable-paystack') ,
						$response->message)
					);
				return false;
			}

			//Package the redirect
			$redirect_array = array(
				'redirect' 	=> $response->data->authorization_url,
				'safe'		=> false,
			);

			//Collect the donation reference to refer to later
			$reference = $response->data->reference;
			$donation->set_gateway_transaction_id($reference);

			return $redirect_array;
		
		}

		/**
		 * Process an IPN request.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public static function process_ipn() {
			/**
			 * Process the IPN.
			 *
			 * @todo
			 */
		}

		public static function process_response() {

			if ( ! isset( $_GET['reference'] )) {
				return;
			}

			$gateway = new Charitable_Gateway_Paystack();

			//Collect the payment reference to find by donation ID
			$reference = $_GET['reference']; 
			if (is_null($reference)) {
				return;
			}

			$donation_id = charitable_get_donation_by_transaction_id( $reference );
			if ( is_null( $donation_id ) ) {
				return;
			}

			/* We've processed this donation already. */
			if ( get_post_meta( $donation_id, '_charitable_processed_paystack_response', true ) ) {
				return;
			}

			/* Update our donation */
			$donation = charitable_get_donation( $donation_id );
			$donation->update_status( 'charitable-completed' );
			$keys     = $gateway->get_keys();

			//Check whether the payment has gone through
			$getResponse = $gateway->api()->get(
				$method 	= 'transaction/verify/'. $reference,
				$args 		= [],
				$timeout 	= 10
			);

			//error_log(var_export($getResponse, true));

			$status = $getResponse->data->status;

			$donation = new Charitable_Donation( $donation_id );

			if ($status === "success") {
				$donation->update_donation_log( __( 'Payment completed.', 'charitable-paystack' ) );
				$donation->update_status( 'charitable-completed' );
			} else {
				$donation->update_donation_log( $getResponse->message );
				$donation->update_status( 'charitable-failed' );
			}

			/* Avoid processing this response again. */
			add_post_meta( $donation_id, '_charitable_processed_paystack_response', true );

		}
		
	/**
		 * Check whether a particular donation can be refunded automatically in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Donation $donation The donation object.
		 * @return boolean
		 */
		public function is_donation_refundable( Charitable_Donation $donation ) {

			$gateway     	= new Charitable_Gateway_Paystack();

			$keys 			= $gateway->get_keys();

			$private_key 	= $keys['secret_key'];

			if ( ! $private_key ) {
				return false;
			}		

			return false != $donation->get_gateway_transaction_id();
		}

	/**
		 * Process a refund initiated in the WordPress dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @param  int $donation_id The donation ID.
		 * @return boolean
		 */
		
		
		public static function refund_donation_from_dashboard( $donation_id ) {
			
			$donation = charitable_get_donation( $donation_id );
			if ( ! $donation ) {
				return false;
			}

			$transaction = $donation->get_gateway_transaction_id();
			if ( ! $transaction ) {
				return false;
			}

			$gateway = new Charitable_Gateway_Paystack();

			try {

				//Request refund from Paystack
				$result = $gateway->api()->post(
					$method = 'refund',
					$args 	= [
						'transaction'  		=> $transaction,
						'merchant_note'		=> 'Refunded from Charitable dashboard',
					],
					$timeout 	= 10
				);

				//Ensure the refund has been approved before updating as refunded
				if ($result->status) {
					update_post_meta( $donation_id, '_paystack_refunded', true );
					$donation->log()->add('Refunded automatically from dashboard');
				
				} else {
				//Show the error message for failed refunds in the log
					$donation->log()->add(
						sprintf(
							__( 'Paystack refund failed with message: %s', 'charitable-paystack' ),
							$result->message
						)
					);
				}

				//error_log(var_export($result, true));

				return true;

			} catch ( Exception $e ) {
				$donation->log()->add(
					sprintf(
						/* translators: %s: error message. */
						__( 'paystack refund failed: %s', 'charitable-paystack' ),
						$e->message
					)
				);

				return false;
			}
		}

	}
		
endif;
