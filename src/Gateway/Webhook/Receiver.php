<?php
/**
 * Receiver for incoming Paystack webhooks.
 *
 * The responsibility of this class is to ensure the webhook is valid,
 * and then return a webhook processor with an appropriate interpreter.
 *
 * @package   Charitable Paystack/Classes
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Webhook;

use Charitable\Pro\Paystack\Gateway\Api;
use Charitable\Webhooks\Receivers\ReceiverInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Webhook\Receiver' ) ) :

	/**
	 * \Charitable\Pro\Paystack\Gateway\Webhook\Receiver
	 *
	 * @since 1.0.0
	 */
	class Receiver implements ReceiverInterface {

		/**
		 * Get the Processor to use for the webhook event.
		 *
		 * @since  1.0.0
		 *
		 * @return Processor
		 */
		public function get_processor() {
			$interpreter = $this->get_interpreter();

			if ( 'subscription' === $interpreter->get_event_subject() ) {
				return new SubscriptionProcessor( $interpreter );
			}

			return new DonationProcessor( $interpreter );
		}

		/**
		 * Return the Intepreter object to use for donation webhooks.
		 *
		 * @since  1.0.0
		 *
		 * @return Interpreter
		 */
		public function get_interpreter() {
			return new Interpreter();
		}

		/**
		 * Return the HTTP status to send for an invalid event.
		 *
		 * @since  1.0.0
		 *
		 * @return int
		 */
		public function get_invalid_response_status() {
			return 500;
		}

		/**
		 * Response text to send for an invalid event.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_invalid_response_message() {
			return __( 'Invalid request', 'charitable-paystack' );
		}

		/**
		 * Check whether this is a valid webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function is_valid_webhook() {
			return ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' === $_SERVER['REQUEST_METHOD'];
		}
	}

endif;
