<?php
/**
 * The class responsible for creating payment requests for Paystack.
 *
 * @package   Charitable Paystack/Classes
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Payment;

use Charitable\Gateways\Payment\RequestInterface;
use Charitable\Gateways\Payment\ResponseInterface;
use Charitable\Helpers\DonationDataMapper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Payment\Request' ) ) :

	/**
	 * \Charitable\Pro\Paystack\Gateway\Payment\Request
	 *
	 * @since 1.0.0
	 */
	class Request implements RequestInterface {

		/**
		 * Data map.
		 *
		 * @since 1.0.0
		 *
		 * @var   DonationDataMapper
		 */
		private $data_map;

		/**
		 * Whether this is a test mode request.
		 *
		 * @since 1.0.0
		 *
		 * @var   boolean
		 */
		private $test_mode;

		/**
		 * The current user's donor ID.
		 *
		 * @since 1.0.0
		 *
		 * @var   int|false
		 */
		private $donor_id;

		/**
		 * Donation.
		 *
		 * @since 1.0.0
		 *
		 * @var   \Charitable_Donation|null
		 */
		private $donation;

		/**
		 * Class instantiation.
		 *
		 * @since 1.0.0
		 *
		 * @param DonationDataMapper $data_map  The data mapper object.
		 * @param boolean|null       $test_mode Whether this is a test mode request.
		 */
		public function __construct( DonationDataMapper $data_map, $test_mode = null ) {
			$this->data_map = $data_map;
			$this->test_mode = is_null( $test_mode ) ? charitable_get_option( 'test_mode' ) : $test_mode;
		}

		/**
		 * Get the API object.
		 *
		 * @since  1.0.0
		 *
		 * @return \Charitable\Pro\Paystack\Gateway\Api
		 */
		public function api() {
			return \charitable_paystack()->gateway()->api();
		}

		/**
		 * Prepare the request.
		 *
		 * @return boolean
		 */
		public function prepare_request() {
			Customer::create_or_update( $this->data_map->get_data( 'email', 'customer' ) );

			$this->request_data = $this->data_map->get_data( array( 'email', 'amount', 'currency', 'callback_url', 'metadata' ) );

			/* If this is a recurring donation, create a plan and add that to the request data. */
			$recurring_donation = $this->data_map->get_donation()->get_donation_plan();

			if ( $recurring_donation ) {
				$plan = Plan::init_with_recurring_donation( $recurring_donation );

				$this->request_data['plan'] = $plan->id;
			}

			return true;
		}

		/**
		 * Make the request.
		 *
		 * @return boolean
		 */
		public function make_request() {
			/**
			 * Filter the arguments used to add a new Payment in Paystack.
			 *
			 * @see https://paystack.com/docs/api/#transaction-initialize
			 *
			 * @since 1.0.0
			 *
			 * @param array $request_data The arguments to be passed to create the payment.
			 * @param array $data         Additional data received for the request.
			 */
			$this->request_data = apply_filters( 'charitable_paystack_payment_args', $this->request_data, $this->data_map );

			/* Make the request. */
			$this->response_data = $this->api()->post( 'transaction/initialize', $this->request_data );

			/* Check for an error. */
			if ( false === $this->response_data ) {
				$response = $this->api()->get_last_response();

				if ( is_wp_error( $response ) ) {
					charitable_get_notices()->add_errors_from_wp_error( $this->api()->get_last_response() );
				}

				return false;
			}

			if ( ! $this->response_data->status) {
				charitable_get_notices()->add_error(
					sprintf(
						/* Translators: %s: error message */
						__( 'Donation failed in gateway with error: "%s"', 'charitable-paystack') ,
						$this->response_data->message
					)
				);
				return false;
			}

			return true;
		}

		/**
		 * Return the response to the request.
		 *
		 * @return \Charitable\Pro\Paystack\Gateway\Payment\Response
		 */
		public function get_response() : ResponseInterface {
			return new Response( $this->response_data );
		}
	}

endif;
