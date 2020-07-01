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

			/**
			 * Add a "Paystack" section to the Extensions settings area of Charitable.
			 */
			add_filter( 'charitable_settings_tab_fields_extensions', [ $this, 'add_paystack_settings' ], 6 );

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

		/**
		 * Add settings to the Extensions settings tab.
		 *
		 * @since  1.0.0
		 *
		 * @param  array[] $fields Settings to display in tab.
		 * @return array[]
		 */
		public function add_paystack_settings( $fields = [] ) {
			if ( ! charitable_is_settings_view( 'extensions' ) ) {
				return $fields;
			}

			$custom_fields = [
				'section_paystack'          => [
					'title'    => __( 'Paystack', 'charitable-paystack' ),
					'type'     => 'heading',
					'priority' => 50,
				],
				'paystack_setting_text'     => [
					'title'    => __( 'Text Field Setting', 'charitable-paystack' ),
					'type'     => 'text',
					'priority' => 50.2,
					'default'  => __( '', 'charitable-paystack' ),
				],
				'paystack_setting_checkbox' => [
					'title'    => __( 'Checkbox Setting', 'charitable-paystack' ),
					'type'     => 'checkbox',
					'priority' => 50.6,
					'default'  => false,
					'help'     => __( '', 'charitable-paystack' ),
				],
			];

			$fields = array_merge( $fields, $custom_fields );

			return $fields;
		}
	}

endif;
