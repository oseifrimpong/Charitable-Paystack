<?php
/**
 * Class responsible for processing webhooks.
 *
 * @package   Charitable Paystack/Classes/Charitable_Paystack_Webhook_Processor
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

if ( ! class_exists( 'Charitable_Paystack_Webhook_Processor' ) ) :

	/**
	 * Charitable_Paystack_Webhook_Processor
	 *
	 * @since 1.0.0
	 */
	class Charitable_Paystack_Webhook_Processor {

		/**
		 * Webhook object.
		 *
		 * @since 1.0.0
		 *
		 * @var   ?
		 */
		protected $webhook;

		/**
		 * Gateway helper.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Gateway_Paystack
		 */
		protected $gateway;

		/**
		 * Create class object.
		 *
		 * @since 1.0.0
		 *
		 * @param ? $webhook The webhook object.
		 */
		public function __construct( $webhook ) {
			$this->webhook = $webhook;
			$this->gateway = new Charitable_Gateway_Paystack();
		}

		/**
		 * Process an incoming Paystack IPN.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public static function process() {
			/* Retrieve and validate the request's body. */
			$webhook = self::get_validated_incoming_event();

			if ( ! $webhook ) {
				status_header( 500 );
				die( __( 'Invalid Paystack event.', 'charitable-paystack' ) );
			}

			$processor = new Charitable_Paystack_Webhook_Processor( $webhook );
			$processor->run();
		}

		/**
		 * Process the webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function run() {
			try {
				status_header( 200 );

				/**
				 * Default webhook event processors.
				 *
				 * @since 1.0.0
				 *
				 * @param array $processors Array of Paystack event types and associated callback functions.
				 */
				$default_processors = apply_filters(
					'charitable_paystack_default_webhook_event_processors',
					[]
				);

				/** @todo Get event type from the webhook. */
				$webhook_event = $this->webhook->event;

				/* Check if this event can be handled by one of our built-in event processors. */
				if ( array_key_exists( $webhook_event, $default_processors ) ) {

					$message = call_user_func( $default_processors[ $webhook_event ], $this->webhook );

					/* Kill processing with a message returned by the event processor. */
					die( $message );
				}

				/**
				 * Fire an action hook to process the event.
				 *
				 * Note that this will only fire for webhooks that have not already been processed by one
				 * of the default webhook handlers above.
				 *
				 * @since 1.0.0
				 *
				 * @param string $event_type Type of event.
				 * @param ?      $webhook    The webhook object.
				 */
				do_action( 'charitable_paystack_webhook_event', $webhook_event, $this->webhook );

			} catch ( Exception $e ) {
				$body = $e->getJsonBody();

				error_log( $body['error']['message'] );

				status_header( 500 );

				die(
					sprintf(
						/* translators: %s: error message */
						__( 'Webhook processing error: %s', 'charitable-paystack' )
					)
				);
			}
		}

		/**
		 * For an IPN request, get the validated incoming event object.
		 *
		 * @since  1.0.0
		 *
		 * @return false|?
		 */
		private static function get_validated_incoming_event() {
			$payload = file_get_contents( 'php://input' );

			if ( empty( $payload ) ) {
				return false;
			}

			return $payload;
		}
	}

endif;
