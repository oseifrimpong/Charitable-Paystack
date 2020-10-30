<?php
/**
 * Interpret incoming Paystack webhooks.
 *
 * @package   Charitable Paystack/Classes/\Charitable\Pro\Paystack\Gateway\Webhook\Interpreter
 * @author    Eric Daams
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Webhook\Interpreter' ) ) :

	/**
	 * \Charitable\Pro\Paystack\Gateway\Webhook\Interpreter
	 *
	 * @since 1.0.0
	 */
	class Interpreter implements \Charitable_Webhook_Interpreter_Interface, \Charitable_Webhook_Interpreter_Donations_Interface {

		/**
		 * Valid webhook.
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
		 * Check whether there is a processor to use for the webhook source.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function has_processor() {
			return true;
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
		 * core is a donation.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_event_subject() {

			return 'donation';
		}

		/**
		 * Return the interpreter object to use for donation webhooks.
		 *
		 * @since  1.0.0
		 *
		 * @return \Charitable\Pro\Paystack\Gateway\Webhook\Interpreter|false
		 */
		public function get_donations_interpreter() {
			return $this;
		}

		/**
		 * Get the donation object.
		 *
		 * @since  1.7.0
		 *
		 * @return Charitable_Donation|false Returns the donation if one matches the webhook.
		 */
		public function get_donation() {
			if ( ! isset( $this->donation ) ) {
				/* The donation ID needs to match a donation post type. */
				if ( \Charitable::DONATION_POST_TYPE !== get_post_type( $this->donation_id ) ) {
					return false;
				}

				$this->donation = charitable_get_donation( $this->donation_id );

				if ( 'Paystack' !== $this->donation->get_gateway() ) {
					$this->set_invalid_request( __( 'Incorrect gateway', 'charitable-Paystack' ) );
					$this->donation = false;
				}
			}

			return $this->donation;
		}

		/**
		 * Get the type of event described by the webhook.
		 * Payment success is currently the only webhook they send that we are listening for
		 * @since  1.7.0
		 *
		 * @return string
		 */
		public function get_event_type() {
			/* Payment has been fully refunded. */
			if ( $this->get_refund_amount() ) {
				return 'refund';
			}

			switch ( $this->data->status ) {
				
				case 'failed':
					return 'failed_payment';

				case 'success':
					return 'completed_payment';
			}
		}

		/**
		 * Get the refunded amount.
		 * Refunds do not currently throw webhooks
		 * @since  1.7.0
		 *
		 * @return float|false The amount to be refunded, or false if this is not a refund.
		 */
		public function get_refund_amount() {
			return false;
		}

		/**
		 * Get a log message to include when adding the refund.
		 * No refund webhooks currently
		 * @since  1.7.0
		 *
		 * @return string
		 */
		public function get_refund_log_message() {
			return null;
		}

		/**
		 * Get all the refunds for this payment.
		 * No refund webhooks currently
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_refunds() {
			return null;
		}

		/**
		 * Return the gateway transaction ID.
		 *
		 * @since  1.7.0
		 *
		 * @return string|false The gateway transaction ID if available, otherwise false.
		 */
		public function get_gateway_transaction_id() {
			if ( ! isset($this->data->reference) ) {
				return false;
			}
			return $this->data->reference;
		}

		/**
		 * Return the gateway transaction URL.
		 *
		 * @since  1.7.0
		 *
		 * @return string|false The URL if available, otherwise false.
		 */
		public function get_gateway_transaction_url() {
			return false;
		}

		/**
		 * Return the donation status based on the webhook event.
		 * Success is currently the only webhook that will be sent
		 * 
		 * @since  1.7.0
		 *
		 * @return string
		 */
		public function get_donation_status() {
			switch ( $this->data->status ) {

				case 'failed':
					return 'charitable-failed';

				case 'success':
					return 'charitable-completed';
			}
		}

		/**
		 * Return an array of log messages to update the donation.
		 *
		 * @since  1.7.0
		 *
		 * @return array
		 */
		public function get_logs() {
			return array();
		}

		/**
		 * Return an array of meta data to add/update for the donation.
		 *
		 * @since  1.7.0
		 *
		 * @return array
		 */
		public function get_meta() {
			return array();
		}

/**
		 * Get the response message.
		 *
		 * @since  1.7.0
		 *
		 * @return string
		 */
		public function get_response_message() {
			if ( $this->data->message !== null ) {
				return $this->data->message;
			}
		}
		/**
		 * Get the response HTTP status.
		 *
		 * @since  1.7.0
		 *
		 * @return int
		 */
		public function get_response_status() {

		}

		/**
		 * Validate the webhook request.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function parse_request() {

			if ( ! $this->is_valid_request() ) {
				$this->set_invalid_request( __( 'Invalid request', 'charitable-Paystack' ) );
				return;
			}

			$payload = file_get_contents( 'php://input' );

			if ( empty( $payload ) ) {
				$this->set_invalid_request( __( 'Empty data', 'charitable-Paystack' ) );
				return;
			}

            $gateway = $this->donation->get_gateway();
            $keys = $gateway->get_keys();

			//Check the signature is correct, i.e. signed with our key
            if($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $payload, $keys['secret_key'])) {
                $this->set_invalid_request( __( 'Invalid keys', 'charitable-Paystack' ) );
				return;
            }

			/*
			//Check the IP sending the webhook is one of their provided IPs found on their webhooks page
			if ($_SERVER['REMOTE_ADDR'] !== "52.31.139.75" && $_SERVER['REMOTE_ADDR'] !== "52.49.173.169" && $_SERVER['REMOTE_ADDR'] !== "52.214.14.220") {
				$this->set_invalid_request( __( 'Webhook coming from invalid IP address', 'charitable-Paystack' ) );
				return;
			}
			*/
			
            http_response_code(200);

			//parse_str( $payload, $this->data );
            $this->data = json_decode($payload);

			if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
				error_log( var_export( $this->data, true ) );
			}

			//Currently only transaction webhooks are supported
			if ( empty( $this->data ) || ! array_key_exists( 'data', $this->data) || ! array_key_exists( 'reference', $this->data['data']) ) {
				$this->set_invalid_request( __( 'Invalid data, or wrong event', 'charitable-Paystack' ) );
				return;
			}

			/* See if we have a donation stored with this transaction ID. */
			$this->donation_id = charitable_get_donation_by_transaction_id( $this->data['reference'] );

			/* Check that the donation is valid. */
			if ( is_null( $this->donation_id ) || ! $this->get_donation() ) {
				$this->set_invalid_request( __( 'No such donation here.', 'charitable-Paystack' ) );
				return;
			}

			/* We're still here. Webhook is valid. */
			$this->valid = true;
		}

		/**
		 * Returns whether the webhook request is valid.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		private function is_valid_request() {
			return ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' === $_SERVER['REQUEST_METHOD'];
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