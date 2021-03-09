<?php
/**
 * The class responsible for creating Plans in Paystack.
 *
 * @package   Charitable Paystack/Classes
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Payment;

use Charitable\Pro\Paystack\Gateway\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Payment\Plan' ) ) :

	/**
	 * \Charitable\Pro\Paystack\Gateway\Payment\Plan
	 *
	 * @since 1.0.0
	 */
	class Plan {

		/**
		 * Campaign ID.
		 *
		 * @since 1.0.0
		 *
		 * @var   int
		 */
		private $campaign_id;

		/**
		 * Mode.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $mode;

		/**
		 * The campaign's stored plans.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $plans;

		/**
		 * Internal arguments used for defining a particular plan.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $args;

		/**
		 * Plan args.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $plan_args;

		/**
		 * Plan key.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $plan_key;

		/**
		 * The Plan object from Paystack.
		 *
		 * @since 1.0.0
		 *
		 * @var   object|false
		 */
		private $plan;

		/**
		 * Create class object.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $campaign_id The campaign id.
		 * @param array $args        Mixed set of args.
		 */
		public function __construct( $campaign_id, $args ) {
			$this->campaign_id = $campaign_id;
			$this->mode        = charitable_get_option( 'test_mode' ) ? 'test' : 'live';
			$this->args        = $args;
		}

		/**
		 * Get a Plan object with a recurring donation.
		 *
		 * This will create a Plan in Paystack if necessary, or if one has been created
		 * previously, it will use that.
		 *
		 * @since  1.0.0
		 *
		 * @param  \Charitable_Recurring_Donation $recurring_donation Recurring donation object.
		 * @return Plan
		 */
		public static function init_with_recurring_donation( \Charitable_Recurring_Donation $recurring_donation ) {
			/* We assume there is only one campaign being donated to. */
			$campaign_id = current( $recurring_donation->get_campaign_donations() )->campaign_id;

			$amount = $recurring_donation->get_recurring_donation_amount();

			if ( $recurring_donation->get( 'cover_fees' ) ) {
				$amount = $recurring_donation->get( 'total_donation_with_fees' );
			}

			return new Plan(
				$campaign_id,
				array(
					'period'   => $recurring_donation->get_donation_period(),
					'amount'   => $amount * 100,
					'interval' => 1,
				)
			);
		}

		/**
		 * Return a plan object property.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $property The plan property to get.
		 * @return mixed|null
		 */
		public function __get( $property ) {
			if ( isset( $this->$property ) ) {
				return $this->$property;
			}

			$plan = $this->get();

			return $plan ? $plan->data->$property : null;
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
		 * Get the Plan object from Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false
		 */
		public function get() {
			if ( ! isset( $this->plan ) ) {
				$plan_id = $this->get_plan_id();

				/* Create or return the plan object. */
				if ( ! $plan_id ) {
					$this->plan = $this->create();
				} else {
					$this->plan = $this->api()->get( 'plan/' . $plan_id );
				}
			}

			return $this->plan;
		}

		/**
		 * Create the Plan in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false
		 */
		public function create() {
			if ( ! isset( $this->args['amount'] ) ) {
				return false;
			}

			/**
			 * Filter the plan arguments.
			 *
			 * @see https://paystack.com/docs/api/#plan-create
			 *
			 * @since 1.0.0
			 *
			 * @param array $args The plan arguments.
			 * @param \Charitable\Pro\Paystack\Gateway\Payment\Plan $plan The plan object.
			 */
			$args = apply_filters(
				'charitable_paystack_plan_args',
				array(
					'name'          => $this->get_plan_name(),
					'amount'        => $this->args['amount'],
					'description'   => 'Plan Description',
					'interval'      => $this->get_plan_interval(),
					'currency'      => charitable_get_currency(),
					'send_invoices' => charitable_get_option( array( 'charitable_paystack', 'send_invoices' ), false ),
					'send_sms'      => charitable_get_option( array( 'charitable_paystack', 'send_sms' ), false ),
				)
			);

			return $this->api()->post( 'plan', $args );
		}

		/**
		 * Return the key for a plan.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_plan_key() {
			if ( ! isset( $this->plan_key ) ) {
				$this->plan_key = charitable_recurring_get_plan_key( $this->args );
			}

			return $this->plan_key;
		}

		/**
		 * Return all plans associated with this campaign.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_plans() {
			if ( isset( $this->plans ) ) {
				return $this->plans;
			}

			$all_plans = get_post_meta( $this->campaign_id, 'paystack_donation_plans', true );

			if ( ! is_array( $all_plans ) || ! isset( $all_plans[ $this->mode ] ) ) {
				$this->plans = array();
				return $this->plans;
			}

			$this->plans = $all_plans[ $this->mode ];

			return $this->plans;
		}

		/**
		 * Get the saved plan ID, if it has been saved.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false
		 */
		public function get_plan_id() {
			$plan_key = $this->get_plan_key();
			$plans    = $this->get_plans();

			return $plans[ $plan_key ] ?? false;
		}

		/**
		 * Get the plan name.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_plan_name() {
			return sprintf(
				/* translators: %1$s: recurring donation interval/period; %2$s: campaign name */
				__( '%1$s Donation to %2$s', 'charitable-paystack' ),
				ucfirst( $this->get_plan_interval() ),
				get_the_title( $this->campaign_id )
			);
		}

		/**
		 * Get the interval to use when defining the plan in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_plan_interval() {
			switch ( $this->args['period'] ) {
				case 'hour':
					return 'hourly';

				case 'day':
					return 'daily';

				case 'week':
					return 'weekly';

				case 'month':
					return 'monthly';

				case 'semiannual':
					return 'biannually';

				case 'annual':
					return 'annually';
			}
		}
	}

endif;
