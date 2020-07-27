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
			// $cancel_url = charitable_get_permalink( 'donation_cancel_page', [ 'donation_id' => $donation->ID ] );
			// $notify_url = function_exists( 'charitable_get_ipn_url' )
			// 	? charitable_get_ipn_url( Charitable_Gateway_Sparrow::ID )
			// 	: Charitable_Donation_Processor::get_instance()->get_ipn_url( Charitable_Gateway_Sparrow::ID );

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
					'redirect' => $redirect_url,
					'safe' => false // Set to false if you are redirecting away from the site.
				];
			 *
			 */

			$response = $gateway->api()->post(
				'transaction/initialize',
				[
					'email'  => $email,
					'amount' => $amount,
				]
			);

			$donation->set_gateway_transaction_id( $response['reference'] );

			// $header = "Authorization: Bearer " . $keys['secret_key'];

			// $url = "https://api.paystack.co/transaction/initialize";
  			// $fields = [
			// 	'email' => $email,
			// 	'amount' => $amount,	//May require conversion of currency - multiply by 100
			// 	//'callback_url' => , WHAT TO MAKE THIS?
			// ];

			// $fields_string = http_build_query($fields);
			// //open connection
			// $ch = curl_init();

			// //set the url, number of POST vars, POST data
			// curl_setopt($ch,CURLOPT_URL, $url);
			// curl_setopt($ch,CURLOPT_POST, true);
			// curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
			// curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			// 	$header,
			// 	"Cache-Control: no-cache",
			// ));

			// //So that curl_exec returns the contents of the cURL; rather than echoing it
			// curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

			// //execute post
			// $result = curl_exec($ch);

			$redirect_array = array(
				'redirect' 	=> $result['data']['authorization_url'],
				'safe'		=> false,
			);

			$reference = $result['data']['reference'];

			$donation->set_gateway_transaction_id($reference);

			curl_close($ch);

			return $redirect_array['redirect'];
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

		//COMPLETE - checking whether the get key matches to verify the payment
		public static function process_response() {



			// if ($_GET['Authorization'] != ) {
			// 	return;
			// }

			$gateway     = new Charitable_Gateway_Paystack();

			$transaction_id = $_GET['reference'];

			$donation_id = charitable_get_donation_by_transaction_id( $transaction_id );

			if ( is_null( $donation_id ) ) {
				return;
			}

			/* Verify transaction with Paystack. */

			// @todo


			/* Update our donation */
			$donation = charitable_get_donation( $donation_id );
			$donation->log()->add( 'My log message' );
			$donation->update_status( 'charitable-completed' );

			// $reference = ; // Reference needs to be retrieved from server and stored here

			$keys = $gateway->get_keys();

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.paystack.co/transaction/verify/:reference",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer " . $keys['secret_key'],
				"Cache-Control: no-cache",
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);

			$success = $response['data']['status'];

			//GUESSING vvv

			$donation_id = $reference;

			/* We've processed this donation already. */
			if ( get_post_meta( $donation_id, '_charitable_processed_paystack_response', true ) ) {
				return;
			}

			$donation = new Charitable_Donation( $donation_id );

			if ($status === "success") {
				$donation->update_donation_log( __( 'Payment completed.', 'charitable-paystack' ) );
				$donation->update_status( 'charitable-completed' );
			} else {
				$donation->update_donation_log( $response['data']['message'] );
				$donation->update_status( 'charitable-failed' );
			}

			/* Avoid processing this response again. */
			add_post_meta( $donation_id, '_charitable_processed_paystack_response', true );

		}

	}

endif;
