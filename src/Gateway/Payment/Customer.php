<?php
/**
 * The class responsible for creating Customers in Paystack.
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

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Payment\Customer' ) ) :

	/**
	 * \Charitable\Pro\Paystack\Gateway\Payment\Customer
	 *
	 * @since 1.0.0
	 */
	class Customer {

		/**
		 * Internal arguments used for defining a particular plan.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		private $args;

		/**
		 * The Customer object from Paystack.
		 *
		 * @since 1.0.0
		 *
		 * @var   object|false
		 */
		private $customer;

		/**
		 * Create customer object.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Mixed set of args.
		 */
		public function __construct( $args ) {
			$this->args = $args;
		}

		/**
		 * Create or update a customer.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $args Mixed set of args.
		 * @return object|false
		 */
		public static function create_or_update( $args ) {
			if ( ! isset( $args['email'] ) ) {
				return false;
			}

			$customer = new Customer( $args );

			if ( $customer->exists() ) {
				return $customer->update();
			}

			return $customer->create();
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
		 * Checks whether the customer exists in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function exists() {
			return false !== $this->get();
		}

		/**
		 * Checks whether the customer exists in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false
		 */
		public function get() {
			if ( ! isset( $this->customer ) ) {
				$this->customer = $this->api()->get( 'customer/' . $this->args['email'] );
			}

			return $this->customer;
		}

		/**
		 * Create a new Customer in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false
		 */
		public function create() {
			$data = array_merge(
				array( 'email' => $this->args['email'] ),
				$this->args['customer']
			);

			/**
			 * Filter the arguments used to add a new Customer in Paystack.
			 *
			 * @see https://paystack.com/docs/api/#customer-create
			 *
			 * @since 1.0.0
			 *
			 * @param array $data The arguments to be passed to create the customer.
			 */
			$data = apply_filters( 'charitable_paystack_customer_args', $data );

			$this->customer = $this->api()->post( 'customer', $data );

			if ( $this->customer && $this->customer->status ) {
				update_metadata( 'donor', $this->get_donor_id(), $this->get_customer_meta_key(), $this->customer->data->customer_code );
			}

			return $this->customer;
		}

		/**
		 * Create a new Customer in Paystack.
		 *
		 * @since  1.0.0
		 *
		 * @return object|false
		 */
		public function update() {
			$donor_id = $this->get_donor_id();

			/* We don't update existing Customer records unless the donor is logged in. */
			if ( ! $donor_id ) {
				return false;
			}

			$customer_code = get_metadata( 'donor', $donor_id, $this->get_customer_meta_key(), true );

			if ( ! $customer_code ) {
				return false;
			}

			$this->customer = $this->api()->put( 'customer/' . $customer_code, $this->args['customer'] );

			return $this->customer;
		}

		/**
		 * Get the current customer's donor ID.
		 *
		 * @since  1.0.0
		 *
		 * @return int|false
		 */
		public function get_donor_id() {
			if ( ! isset( $this->donor_id ) ) {
				if ( ! is_user_logged_in() ) {
					$this->donor_id =  false;
				}

				$this->donor_id = charitable_get_user( get_current_user_id() )->get_donor_id();
			}

			return $this->donor_id;
		}

		/**
		 * Get the meta key used to record the Paystack customer ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_customer_meta_key() {
			$meta_postfix = $this->test_mode ? 'test' : 'live';
			return 'paystack_customer_id_' . $meta_postfix;
		}

	}

endif;
