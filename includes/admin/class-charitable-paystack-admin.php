<?php
/**
 * The class responsible for adding & saving extra settings in the Charitable admin.
 *
 * @package   Charitable Paystack/Classes/Charitable_Paystack_Admin
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Paystack_Admin' ) ) :

	/**
	 * Charitable_Paystack_Admin
	 *
	 * @since 1.0.0
	 */
	class Charitable_Paystack_Admin {

		/**
		 * The single static class instance.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Paystack_Admin
		 */
		private static $instance = null;

		/**
		 * Create and return the class object.
		 *
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new Charitable_Paystack_Admin();
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
			add_filter( 'plugin_action_links_' . plugin_basename( charitable_paystack()->get_path() ), [ $this, 'add_plugin_action_links' ] );

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
			$links[] = '<a href="' . admin_url( 'admin.php?page=charitable-settings&tab=extensions' ) . '">' . __( 'Settings', 'charitable-newsletter-connect' ) . '</a>';
			return $links;
		}
	}

endif;
