<?php
/**
 * Paystack Gateway class.
 *
 * @package   Charitable Paystack/Classes
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.1
 */

namespace Charitable\Pro\Paystack\Gateway;

use Charitable\Webhooks\Receivers as WebhookReceivers;
use Charitable\Gateways\Payment\Processors as PaymentProcessors;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Gateway' ) ) :

	/**
	 * Paystack Gateway.
	 *
	 * @since 1.0.0
	 */
	class Gateway extends \Charitable_Gateway {

		/** The gateway ID. */
		const ID = 'paystack';

		/**
		 * Boolean flag recording whether the gateway hooks
		 * have been set up.
		 *
		 * @since 1.0.0
		 *
		 * @var   boolean
		 */
		private static $setup = false;

		/**
		 * API object.
		 *
		 * @since 1.0.0
		 *
		 * @var   \Charitable\Pro\Paystack\Gateway\Api
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

			$this->defaults = array(
				'label' => __( 'Paystack', 'charitable-paystack' ),
			);

			$this->supports = array(
				'1.3.0',
				'refunds',
				'recurring',
			);

			$this->setup();
		}

		/**
		 * Set up hooks for the class.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function setup() {
			if ( self::$setup ) {
				return;
			}

			self::$setup = true;

			/* Register our new gateway. */
			add_filter( 'charitable_payment_gateways', array( $this, 'register_gateway' ) );

			/* Refund a donation from the dashboard. */
			add_action( 'charitable_process_refund_paystack', array( $this, 'refund_donation_from_dashboard' ) );

			/* Update the donation after the donor returns from Paystack. */
			add_action( 'wp', array( $this, 'process_return_after_payment' ) );

			if ( version_compare( charitable()->get_version(), '1.7', '<' ) ) {
				/* Register payment processor. */
				$this->load_forward_compatible_packages();
			}

			/* Register the Paystack webhook receiver. */
			WebhookReceivers::register( self::ID, '\Charitable\Pro\Paystack\Gateway\Webhook\Receiver' );

			/* Register the Paystack payment processor */
			PaymentProcessors::register( self::ID, '\Charitable\Pro\Paystack\Gateway\Payment\Processor' );
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
			$paystack_settings = array(
				'live_keys'         => array(
					'title'    => __( 'Live Mode Settings', 'charitable-paystack' ),
					'type'     => 'heading',
					'priority' => 4,
				),
				'live_secret_key' => array(
					'type'     => 'text',
					'title'    => __( 'Live Secret Key', 'charitable-paystack' ),
					'priority' => 6,
					'class'    => 'wide',
				),
				'live_public_key'  => array(
					'type'     => 'text',
					'title'    => __( 'Live Publishable Key', 'charitable-paystack' ),
					'priority' => 8,
					'class'    => 'wide',
				),
				'test_keys'         => array(
					'title'    => __( 'Test Mode Settings', 'charitable-paystack' ),
					'type'     => 'heading',
					'priority' => 12,
				),
				'test_secret_key'   => array(
					'type'     => 'text',
					'title'    => __( 'Test Secret Key', 'charitable-paystack' ),
					'priority' => 14,
					'class'    => 'wide',
				),
				'test_public_key'    => array(
					'type'     => 'text',
					'title'    => __( 'Test Publishable Key', 'charitable-paystack' ),
					'priority' => 16,
					'class'    => 'wide',
				),
			);

			if ( class_exists( 'Charitable_Recurring' ) ) {
				$paystack_settings += array(
					'recurring_donations' => array(
						'title'    => __( 'Recurring Donations', 'charitable-paystack' ),
						'type'     => 'heading',
						'priority' => 22,
					),
					'send_invoices'       => array(
						'type'     => 'checkbox',
						'title'    => __( 'Send Invoices for Renewals', 'charitable-paystack' ),
						'help'     => __( 'Send donors invoices when their recurring donation renewals are processed.', 'charitable-paystack' ),
						'priority' => 24,
						'default'  => 0,
					),
					'send_sms'            => array(
						'type'     => 'checkbox',
						'title'    => __( 'Send SMS for Renewals', 'charitable-paystack' ),
						'help'     => __( 'Send donors an SMS when their recurring donation renewals are processed.', 'charitable-paystack' ),
						'priority' => 26,
						'default'  => 0,
					),
				);
			}

			return $settings + $paystack_settings;
		}

		/**
		 * Register the payment gateway class.
		 *
		 * @since  1.0.0
		 *
		 * @param  string[] $gateways The list of registered gateways.
		 * @return string[]
		 */
		public function register_gateway( $gateways ) {
			$gateways['paystack'] = '\Charitable\Pro\Paystack\Gateway\Gateway';
			return $gateways;
		}

		/**
		 * Get the API object.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to explicitly get the test or live key.
		 *                                 If left as null, this will return the key for the
		 *                                 current mode.
		 * @return \Charitable\Pro\Paystack\Gateway\Api
		 */
		public function api( $test_mode = null ) {
			if ( ! isset( $this->api ) ) {
				$this->api = new \Charitable\Pro\Paystack\Gateway\Api( $test_mode );
			}

			return $this->api;
		}

		/**
		 * Check whether a particular donation can be refunded automatically in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @param  \Charitable_Donation $donation The donation object.
		 * @return boolean
		 */
		public function is_donation_refundable( \Charitable_Donation $donation ) {
			return $this->api()->has_valid_api_key() && $donation->get_gateway_transaction_id();
		}

		/**
		 * Process a refund initiated in the WordPress dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @param  int $donation_id The donation ID.
		 * @return boolean
		 */
		public function refund_donation_from_dashboard( $donation_id ) {
			$donation = charitable_get_donation( $donation_id );

			if ( ! $donation ) {
				return false;
			}

			$api = $this->api();

			if ( ! $api->has_api_key() ) {
				return false;
			}

			$transaction = $donation->get_gateway_transaction_id();

			if ( ! $transaction ) {
				return false;
			}

			/**
			 * @todo Make refund.
			 */
		}

		/**
		 * Update the payment after the donor is returned from Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function process_return_after_payment() {
			if ( ! isset( $_GET['reference'] ) || ! charitable()->endpoints()->is_page( 'donation_receipt' ) ) {
				return;
			}

			$reference = $_GET['reference'];

			if ( is_null( $reference ) ) {
				return;
			}

			$donation_id = get_query_var( 'donation_id' );

			/* We've processed this donation already. */
			if ( get_post_meta( $donation_id, '_charitable_processed_paystack_response', true ) ) {
				return;
			}

			$donation = charitable_get_donation( $donation_id, true );

			/* The reference should match the current donation. */
			if ( $reference !== $donation->get_gateway_transaction_id() ) {
				return;
			}

			/* Verify whether the payment has been completed. */
			$transaction = $this->verify_transaction( $reference, $donation->get_test_mode( false ) );

			/* Add a notice for the verification error. */
			$response = $this->api()->get_last_response();

			if ( is_wp_error( $response ) ) {
				charitable_get_notices()->add_errors_from_wp_error( $response );
			} else {
				charitable_get_notices()->add_error(
					sprintf(
						/* Translators: %s: error message */
						__( 'Donation failed in gateway with error: %s', 'charitable-paystack') ,
						json_decode( wp_remote_retrieve_body( $response ) )->message
					)
				);
			}

			/* Get the recurring donation, if applicable. */
			$recurring_donation = $donation->get_donation_plan();

			/* Mark the donation as complete. */
			if ( 'success' === $transaction->data->status )  {
				$donation->update_status( 'charitable-completed' );

				/** @todo Replace with call to $this->donation->set_gateway_transaction_url() once it's in core */
				\Charitable\Packages\Gateways\set_gateway_transaction_url(
					sprintf( 'https://dashboard.paystack.com/#/transactions/%s', $transaction->data->id ),
					$donation
				);

				if ( $recurring_donation ) {
					/* Activate the subscription. */
					$recurring_donation->renew();

					/* Record the Authorization code. This is how we link a Paystack subscription to our recurring donation. */
					update_post_meta( $recurring_donation->ID, '_charitable_paystack_authorization_code', $transaction->data->authorization->authorization_code );
				}
			} else {
				$donation->update_donation_log( $transaction->message );
				$donation->update_status( 'charitable-failed' );

				if ( $recurring_donation ) {
					$recurring_donation->set_to_failed( __( 'Initial donation failed.', 'charitable-paystack' ) );
				}
			}

			/* Avoid processing this response again. */
			add_post_meta( $donation_id, '_charitable_processed_paystack_response', true );
		}

		/**
		 * Verify a transaction, given the reference.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $reference The transaction reference.
		 * @param  boolean $test_mode Whether the transaction was made in test mode.
		 * @return false|object
		 */
		public function verify_transaction( $reference, $test_mode ) {
			return $this->api( $test_mode )->get( 'transaction/verify/' . $reference, array() );
		}

		/**
		 * Given a transaction verification, try to get the related Paystack subscription id.
		 *
		 * @since  1.0.0
		 *
		 * @param  object $result The verification result.
		 * @return string|false
		 */
		public function get_paystack_subscription_id( $result ) {
			if ( is_null( $result->data->plan ) ) {
				return false;
			}


		}

		/**
		 * Process a Paystack webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function process_webhook() {
			/* Stop infinite recursion. */
			remove_action( 'charitable_process_ipn_' . self::ID, array( $this, 'process_webhook' ) );
			\Charitable\Packages\Webhooks\handle( self::ID );
		}

		/**
		 * Load the gateways & webhooks packages for forward compatibility.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function load_forward_compatible_packages() {
			require_once \charitable_paystack()->get_path( 'directory' ) . 'packages/charitable-gateways/package.php';
			require_once \charitable_paystack()->get_path( 'directory' ) . 'packages/charitable-webhooks/package.php';

			add_filter( 'charitable_process_donation_' . self::ID, '\Charitable\Packages\Gateways\process_donation', 10, 3 );
			add_action( 'charitable_process_ipn_' . self::ID, array( $this, 'process_webhook' ) );
		}
	}


endif;
