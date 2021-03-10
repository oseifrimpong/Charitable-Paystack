<?php
/**
 * Interpret incoming Paystack webhooks.
 *
 * @package   Charitable Paystack/Classes/\Charitable\Pro\Paystack\Gateway\Webhook\Interpreter
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Webhook;

use Charitable\Pro\Paystack\Gateway;
use Charitable\Pro\Paystack\Gateway\Api;
use Charitable\Webhooks\Interpreters\DonationInterpreterInterface;
use Charitable\Webhooks\Interpreters\SubscriptionInterpreterInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Webhook\Interpreter' ) ) :

	/**
	 * Webhook interpreter.
	 *
	 * @since 1.0.0
	 */
	class Interpreter implements DonationInterpreterInterface, SubscriptionInterpreterInterface {

		/**
		 * Whether the request is valid and can be used.
		 *
		 * @since 1.0.0
		 *
		 * @var   boolean
		 */
		private $valid;

		/**
		 * The response message to send.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $response;

		/**
		 * The status code to send in response.
		 *
		 * @since 1.0.0
		 *
		 * @var   int
		 */
		private $status;

		/**
		 * The donation ID.
		 *
		 * @since 1.0.0
		 *
		 * @var   int
		 */
		private $donation_id;

		/**
		 * The donation object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Donation
		 */
		private $donation;

		/**
		 * The recurring donation object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Recurring_Donation|false
		 */
		private $recurring_donation;

		/**
		 * The parsed payload data.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $payload;

		/**
		 * The transaction object.
		 *
		 * @since 1.0.0
		 *
		 * @var   object|false
		 */
		private $transaction;

		/**
		 * Set up interpreter object.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->parse_request();
		}

		/**
		 * Get class properties.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $prop The property to retrieve.
		 * @return mixed
		 */
		public function __get( $prop ) {
			return $this->$prop ?? ( $this->payload->$prop ?? null );
		}

		/**
		 * Checks whether a given property is set.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $prop The property to check.
		 * @return boolean
		 */
		public function __isset( $prop ) {
			return isset( $this->$prop ) || isset( $this->payload->$prop );
		}

		/**
		 * Check whether this is a valid webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function is_valid_webhook() {
			return $this->valid;
		}

		/**
		 * Get the processor to use for the webhook source.
		 *
		 * @since  1.0.0
		 *
		 * @return false|Charitable_Webhook_Processor
		 */
		public function get_processor() {
			return new Processor( $this );
		}

		/**
		 * Get the subject of this webhook event. The only
		 * webhook event subject currently handled by Charitable
		 * core is a donations.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_event_subject() {
			switch ( $this->event ) {
				case 'charge.create':
				case 'charge.success':
					return 'donation';

				case 'subscription.create':
				case 'invoice.create':
				case 'subscription.disable':
					return 'subscription';
			}
		}

		/**
		 * Get the donation object.
		 *
		 * @since  1.0.0
		 *
		 * @return Charitable_Donation|false Returns the donation if one matches the webhook.
		 */
		public function get_donation() {
			if ( ! isset( $this->donation ) ) {
				if ( is_null( $this->donation_id ) ) {
					if ( $this->get_recurring_donation() ) {
						$this->donation_id = $this->get_recurring_donation()->get_first_donation_id();
					} else {
						return false;
					}
				}

				/* The donation ID needs to match a donation post type. */
				if ( \Charitable::DONATION_POST_TYPE !== get_post_type( $this->donation_id ) ) {
					return false;
				}

				$this->donation = charitable_get_donation( $this->donation_id );

				if ( 'paystack' !== $this->donation->get_gateway() ) {
					$this->set_invalid_request( __( 'Incorrect gateway', 'charitable-paystack' ) );
					$this->donation = false;
				}
			}

			return $this->donation;
		}

		/**
		 * Get the type of event described by the webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_event_type() {
			switch ( $this->event ) {
				case 'subscription.disable':
					return 'cancellation';

				case 'subscription.create':
					return 'first_payment';

				case 'invoice.create':
					return 'renewal';
			}
		}

		/**
		 * Checks whether the payment is a renewal of a subscription.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function is_renewal() {
			return 'invoice.create' === $this->event;
		}

		/**
		 * Get the refunded amount.
		 *
		 * @since  1.0.0
		 *
		 * @return float|false The amount to be refunded, or false if this is not a refund.
		 */
		public function get_refund_amount() {
			return false;
		}

		/**
		 * Get a log message to include when adding the refund.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_refund_log_message() {
			return '';
		}

		/**
		 * Get all the refunds for this payment.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_refunds() {
			return array();
		}

		/**
		 * Return the gateway transaction ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false The gateway transaction ID if available, otherwise false.
		 */
		public function get_gateway_transaction_id() {
			return $this->transaction->data->reference ?? $this->data->reference;
		}

		/**
		 * Return the gateway transaction URL.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false The URL if available, otherwise false.
		 */
		public function get_gateway_transaction_url() {
			return sprintf(
				'https://dashboard.paystack.com/#/transactions/%s',
				$this->transaction->data->id ?? $this->data->id
			);
		}

		/**
		 * Return the donation status based on the webhook event.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_donation_status() {
			switch ( $this->get_payment()->status ) {
				case 'open':
				case 'pending':
					return 'charitable-pending';

				case 'canceled':
				case 'expired':
					return 'charitable-cancelled';

				case 'failed':
					return 'charitable-failed';

				case 'paid':
					return 'charitable-completed';
			}
		}

		/**
		 * Return an array of log messages to update the donation.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_logs() {
			$logs = array();

			return $logs;
		}

		/**
		 * Get the Recurring Donation object.
		 *
		 * @since  1.0.0
		 *
		 * @return Charitable_Recurring_Donation|false Returns the Recurring Donation if one matches the webhook.
		 *                                             If not, returns false.
		 */
		public function get_recurring_donation() {
			if ( ! isset( $this->recurring_donation ) ) {
				if ( $this->is_renewal() ) {
					$this->recurring_donation = charitable_recurring_get_subscription_by_gateway_id( $this->get_gateway_subscription_id(), 'paystack' );
				} else {
					$this->recurring_donation = $this->get_recurring_donation_by_authorization_code();
				}
			}

			return $this->recurring_donation;
		}

		/**
		 * Get a recurring donation using the authorization code.
		 *
		 * @since  1.0.0
		 *
		 * @global WPDB $wpdb
		 * @return Charitable_Recurring_Donation|false
		 */
		public function get_recurring_donation_by_authorization_code() {
			if ( ! isset( $this->data->authorization->authorization_code ) ) {
				return false;
			}

			global $wpdb;

			$recurring_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_charitable_paystack_authorization_code' AND meta_value = %s",
					$this->data->authorization->authorization_code
				)
			);

			return $recurring_id ? charitable_get_donation( $recurring_id ) : false;
		}

		/**
		 * Verify the invoice transaction.
		 *
		 * @since  1.0.0
		 *
		 * @return false|object
		 */
		public function verify_invoice_transaction() {
			if ( ! isset( $this->data->transaction->reference ) ) {
				return false;
			}

			/* Don't re-process an invoice that has already been processed. */
			if ( charitable_get_donation_by_transaction_id( $this->data->transaction->reference ) ) {
				return false;
			}

			$test_mode = 'test' === $this->data->domain;

			return ( new Api( $test_mode ) )->get(
				'transaction/verify/' . $this->data->transaction->reference,
				array()
			);
		}

		/**
		 * Get the subscription ID used in the payment gateway.
		 *
		 * @since  1.0.0
		 *
		 * @return mixed|false
		 */
		public function get_gateway_subscription_id() {
			switch ( $this->event ) {
				case 'invoice.create':
					return $this->data->subscription->subscription_code ?? false;

				case 'subscription.create':
				case 'subscription.disable':
					return $this->data->subscription_code ?? false;
			}
		}

		/**
		 * Get the URL to access the subscription in the gateway's dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @return mixed|false
		 */
		public function get_gateway_subscription_url() {
			if ( ! isset( $this->data->customer->customer_code ) ) {
				return false;
			}

			return sprintf( 'https://dashboard.paystack.com/#/customers/%s/subscriptions', $this->data->customer->customer_code );
		}

		/**
		 * Return the Subscription status based on the webhook event.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_subscription_status() {

		}

		/**
		 * Return an array of meta data to add/update for the donation.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_meta() {
			$meta = array();

			if ( $this->is_renewal() ) {
				$meta['_gateway_transaction_id']  = $this->get_gateway_transaction_id();
				$meta['_gateway_transaction_url'] = $this->get_gateway_transaction_url();
			}

			return $meta;
		}

		/**
		 * Get the response message.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_response_message() {
			return $this->response;
		}

		/**
		 * Get the response HTTP status.
		 *
		 * @since  1.0.0
		 *
		 * @return int
		 */
		public function get_response_status() {
			return $this->status;
		}

		/**
		 * Validate the webhook request.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function parse_request() {
			$payload = file_get_contents( 'php://input' );

			if ( empty( $payload ) ) {
				$this->set_invalid_request( __( 'Empty data', 'charitable-paystack' ) );
				return;
			}

			$this->payload = json_decode( $payload );

			if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
				error_log( __METHOD__ );
				error_log( var_export( $this->payload, true ) );
			}

			if ( empty( $this->payload ) || ! isset( $this->event ) ) {
				$this->set_invalid_request( __( 'Invalid data', 'charitable-paystack' ) );
				return;
			}

			/* If this is an invoice.create event, verify the transaction. */
			if ( 'invoice.create' === $this->event ) {
				$this->transaction = $this->verify_invoice_transaction();

				if ( ! $this->transaction ) {
					$this->set_invalid_request( __( 'Unable to verify transaction, or transaction has already been processed', 'charitable-paystack' ) );
					return;
				}
			}

			/* We're still here. Webhook is valid. */
			$this->valid = true;
		}

		/**
		 * Set this as an invalid request.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $response The response to send.
		 * @param  int    $status   The status code to send in response.
		 * @return void
		 */
		private function set_invalid_request( $response = '', $status = 500 ) {
			$this->valid    = false;
			$this->response = $response;
			$this->status   = $status;
		}
	}

endif;
