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
		 * The payment object from Paystack.
		 *
		 * @since 1.0.0
		 *
		 * @var   object
		 */
		private $payment;

		/**
		 * The parsed data.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $data;

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
			return $this->$prop;
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
			return isset( $this->$prop );
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
			if ( 'oneoff' === $this->payment->sequenceType ) {
				return 'donation';
			}

			return 'subscription';
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
					return false;
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
		 * Get the Paystack payment object.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false Returns the payment object if one exists.
		 */
		public function get_payment() {
			if ( ! isset( $this->payment ) ) {
				$test_mode     = is_null( $this->donation_id ) ? charitable_get_option( 'test_mode' ) : $this->get_donation()->get_test_mode( false );
				$api           = new Api( $test_mode );
				$this->payment = $api->get( 'payments/' . $this->data['id'] . '?embed=refunds' );
			}

			return $this->payment;
		}

		/**
		 * Get the type of event described by the webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_event_type() {
			/* Payment has been fully refunded. */
			if ( $this->get_refund_amount() ) {
				return 'refund';
			}

			switch ( $this->get_payment()->status ) {
				case 'canceled':
				case 'expired':
					return 'cancellation';

				case 'failed':
					return 'failed_payment';

				case 'paid':
					if ( 'subscription' !== $this->get_event_subject() ) {
						return 'completed_payment';
					}

					return $this->is_first_payment() ? 'first_payment' : 'renewal';
			}
		}

		/**
		 * Checks whether the payment has a mandate.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function has_mandate() {
			return isset( $this->get_payment()->mandateId );
		}

		/**
		 * Checks whether the payment is a renewal of a subscription.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function is_renewal() {
			return 'recurring' === $this->get_payment()->sequenceType;
		}

		/**
		 * Checks whether the payment is the first for a subscription.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function is_first_payment() {
			return 'first' === $this->get_payment()->sequenceType;
		}

		/**
		 * Get the refunded amount.
		 *
		 * @since  1.0.0
		 *
		 * @return float|false The amount to be refunded, or false if this is not a refund.
		 */
		public function get_refund_amount() {
			if ( ! isset( $this->get_payment()->amountRefunded ) || '0.00' === $this->get_payment()->amountRefunded->value ) {
				return false;
			}

			return end( $this->get_payment()->_embedded->refunds )->amount->value;
		}

		/**
		 * Get a log message to include when adding the refund.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_refund_log_message() {
			return end( $this->get_payment()->_embedded->refunds )->description;
		}

		/**
		 * Get all the refunds for this payment.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_refunds() {
			return $this->get_payment()->_embedded->refunds;
		}

		/**
		 * Return the gateway transaction ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false The gateway transaction ID if available, otherwise false.
		 */
		public function get_gateway_transaction_id() {
			return $this->get_payment()->id;
		}

		/**
		 * Return the gateway transaction URL.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false The URL if available, otherwise false.
		 */
		public function get_gateway_transaction_url() {
			return $this->get_payment()->_links->dashboard->href;
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

			switch ( $this->get_payment()->status ) {
				case 'expired':
					$logs[] = __( 'Payment expired.', 'charitable-paystack' );
					break;
			}

			/* Log refund notes. */
			if ( $this->get_refund_amount() && $this->get_payment()->description !== $this->get_refund_log_message() ) {
				$logs[] = sprintf(
					/* translators: %s: refund note */
					__( 'Refund note: "%s"', 'charitable-paystack' ),
					$this->get_refund_log_message()
				);
			}

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
			if ( $this->is_renewal() ) {
				$this->recurring_donation = charitable_recurring_get_subscription_by_gateway_id( $this->get_gateway_subscription_id(), 'paystack' );
			} else {
				$this->donation           = $this->get_donation();
				$this->recurring_donation = $this->donation->get_donation_plan();
			}

			return $this->recurring_donation;
		}

		/**
		 * Get the subscription ID used in the payment gateway.
		 *
		 * @since  1.0.0
		 *
		 * @return mixed|false
		 */
		public function get_gateway_subscription_id() {
			return $this->get_payment()->subscriptionId ?? false;
		}

		/**
		 * Get the URL to access the subscription in the gateway's dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @return mixed|false
		 */
		public function get_gateway_subscription_url() {
			return '';
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

			parse_str( $payload, $this->data );

			if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
				error_log( __METHOD__ );
				error_log( var_export( $this->data, true ) );
			}

			if ( empty( $this->data ) || ! array_key_exists( 'id', $this->data ) ) {
				$this->set_invalid_request( __( 'Invalid data', 'charitable-paystack' ) );
				return;
			}

			/* Try to find the donation ID based on the transaction ID passed. */
			$this->donation_id = charitable_get_donation_by_transaction_id( $this->data['id'] );

			/* Get the payment from Paystack. */
			if ( ! $this->get_payment() ) {
				$this->set_invalid_request( __( 'Invalid payment', 'charitable-paystack' ) );
				return;
			}

			if ( ! $this->is_renewal() ) {
				/* Confirmk that the donation is valid. */
				if ( is_null( $this->donation_id ) || ! $this->get_donation() ) {
					$this->set_invalid_request( __( 'No such donation here.', 'charitable-paystack' ) );
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
