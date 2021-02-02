<?php
/**
 * The class responsible for adding & saving extra settings in the Charitable admin.
 *
 * @package   Charitable Paystack\Classes
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Admin\Admin' ) ) :

	/**
	 * Admin class.
	 *
	 * @since 1.0.0
	 */
	class Admin {

		/**
		 * The single static class instance.
		 *
		 * @since 1.0.0
		 *
		 * @var   \Charitable\Pro\Paystack\Admin
		 */
		private static $instance = null;

		/**
		 * Create and return the class object.
		 *
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new Admin();
			}

			return self::$instance;
		}

		/**
		 * Set up the class.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			if ( ! is_null( self::$instance ) ) {
				return;
			}

			self::$instance = $this;

			/**
			 * Add a direct link to the Extensions settings page from the plugin row.
			 */
			add_filter( 'plugin_action_links_' . plugin_basename( \charitable_paystack()->get_path() ), array( $this, 'add_plugin_action_links' ) );

			/* Include a link to the payment page in Paystack when displaying the transaction ID */
			add_filter( 'charitable_donation_admin_meta', array( $this, 'add_link_to_paystack_payment_page' ), 10, 2 );
		}

		/**
		 * Add custom links to the plugin actions.
		 *
		 * @since  1.0.0
		 *
		 * @param  string[] $links Links to be added to plugin actions row.
		 * @return string[]
		 */
		public function add_plugin_action_links( $links ) {
			if ( \Charitable_Gateways::get_instance()->is_active_gateway( 'paystack' ) ) {
				$links[] = '<a href="' . admin_url( 'admin.php?page=charitable-settings&tab=gateways&group=gateways_paystack' ) . '">' . __( 'Settings', 'charitable-paystack' ) . '</a>';
			} else {
				$activate_url = esc_url(
					add_query_arg(
						array(
							'charitable_action' => 'enable_gateway',
							'gateway_id'        => 'paystack',
							'_nonce'            => wp_create_nonce( 'gateway' ),
						),
						admin_url( 'admin.php?page=charitable-settings&tab=gateways' )
					)
				);

				$links[] = '<a href="' . $activate_url . '">' . __( 'Activate Paystack Gateway', 'charitable-paystack' ) . '</a>';
			}

			return $links;
		}

		/**
		 * Add a link to the Paystack payment page for a particular
		 * donation if we have the URL.
		 *
		 * @since  1.0.0
		 *
		 * @param  array                         $meta     The meta values to show in the Donation Details box.
		 * @param  \Charitable_Abstract_Donation $donation The Donation object.
		 * @return array
		 */
		public function add_link_to_paystack_payment_page( $meta, \Charitable_Abstract_Donation $donation ) {
			if ( ! isset( $meta['gateway_transaction_id'] ) ) {
				return $meta;
			}

			if ( ! $donation->_gateway_transaction_url ) {
				return $meta;
			}

			$meta['gateway_transaction_id']['value'] = '<a href="' . $donation->_gateway_transaction_url . '" target="_blank">' . $donation->get_gateway_transaction_id() . '</a>';

			return $meta;
		}
	}

endif;
