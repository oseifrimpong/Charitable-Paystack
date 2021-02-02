<?php
/**
 * Process incoming Paystack webhooks.
 *
 * @package   Charitable Paystack/Classes/\Charitable\Pro\Paystack\Gateway\Webhook\Processor
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Webhook;

use Charitable\Pro\Paystack\Gateway\Api;
use Charitable\Webhooks\Processors\SubscriptionProcessor as BaseSubscriptionProcessor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Webhook\Processor' ) ) :

	/**
	 * Subscription webhook processor.
	 *
	 * @since 1.0.0
	 */
	class SubscriptionProcessor extends BaseSubscriptionProcessor {

		/**
		 * Process first payment.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function process_first_payment() {
			/* Update the initial payment and mark it as complete. */
			$this->process_completed_payment();

			/* Create the subscription using the mandate. */
			$payment = $this->interpreter->get_payment();

			/* Set up subscription arguments to send to Paystack. */
			$subscription_args = array(
				'amount'      => array(
					'currency' => charitable_get_currency(),
					'value'    => $this->get_subscription_amount(),
				),
				'interval'    => $this->get_subscription_interval(),
				'description' => $this->get_subscription_description(),
				'startDate'   => charitable_recurring_calculate_future_date( 1, $this->recurring_donation->get_donation_period(), 'now', 'Y-m-d' ),
				'mandateId'   => $payment->mandateId,
				'webhookUrl'  => charitable_get_ipn_url( 'paystack' ),
				'metadata'    => array(
					'recurring_donation_id' => $this->recurring_donation->ID,
				),
			);

			if ( ! empty( $this->recurring_donation->get_donation_length() ) ) {
				/* Subtract one from the donation length because the first payment has already been made. */
				$subscription_args['times'] = $this->recurring_donation->get_donation_length() - 1;
			}

			/**
			 * Filter the arguments used to add a new subscription.
			 *
			 * @since 1.0.0
			 *
			 * @param array                         $subscription_args  The arguments made to create the subscription.
			 * @param Charitable_Recurring_Donation $recurring_donation The recurring donation object.
			 * @param object                        $payment            The first payment object received from Paystack.
			 */
			$subscription_args = apply_filters( 'charitable_paystack_subscription_args', $subscription_args, $this->recurring_donation, $payment );

			error_log( var_export( $subscription_args, true ) );

			$api                = new Api( $this->donation->get( 'test_mode' ) );
			$this->subscription = $api->post( 'customers/' . $payment->customerId . '/subscriptions', $subscription_args );

			/* Activate the subscription. */
			$this->recurring_donation->renew();

			$this->save_gateway_subscription_data();
			$this->update_meta();
			$this->update_logs();

			$this->set_response( __( 'Subscription Webhook: First payment processed', 'charitable' ) );

			return true;
		}

		/**
		 * Save the gateway subscription ID and URL if available.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function save_gateway_subscription_data() {
			$subscription_id = isset( $this->subscription ) ? $this->subscription->id : $this->interpreter->get_gateway_subscription_id();

			$this->recurring_donation->set_gateway_subscription_id( $subscription_id );
		}

		/**
		 * Get the subscription interval to use.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_subscription_interval() {
			switch ( $this->recurring_donation->get_donation_period() ) {
				case 'day':
					return '1 day';

				case 'week':
					return '7 days';

				case 'month':
					return '1 months';

				case 'quarter':
					return '3 months';

				case 'semiannual':
					return '6 months';

				case 'year':
					return '12 months';
			}
		}

		/**
		 * Get the amount for the recurring donation.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_subscription_amount() {
			/* Handles support for Fee Relief. */
			if ( $this->donation->get( 'cover_fees' ) ) {
				return number_format( $this->donation->get( 'total_donation_with_fees' ), 2 );
			}

			return number_format( $this->donation->get_total_donation_amount( true ), 2 );
		}

		/**
		 * Get a description for a recurring donation.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_subscription_description() {
			return sprintf(
				/* translators: %1$s: name of campaign; %2$d: recurring donation ID */
				__( '%1$s - Recurring Donation #%2$d', 'charitable-paystack' ),
				$this->recurring_donation->get_campaigns_donated_to(),
				$this->recurring_donation->ID
			);
		}
	}

endif;
