<?php
/**
 * The main Charitable Paystack class.
 *
 * The responsibility of this class is to load all the plugin's functionality.
 *
 * @package   Charitable Paystack
 * @copyright Copyright (c) 2021, Eric Daams
 * @license   http://opensource.org/licenses/gpl-1.0.0.php GNU Public License
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Charitable\Pro\Paystack;

use Charitable\Pro\Paystack\Admin\Admin as Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Paystack' ) ) :

	/**
	 * Charitable_Paystack
	 *
	 * @since 1.0.0
	 */
	class Paystack {

		/** Plugin version. */
		const VERSION = '1.0.0-beta.2';

		/** The extension name. */
		const NAME = 'Charitable Paystack';

		/** The extension author. */
		const AUTHOR = 'Studio 164a';

		/**
		 * Single static instance of this class.
		 *
		 * @since 1.0.0
		 *
		 * @var   \Charitable\Pro\Paystack\Paystack
		 */
		private static $instance = null;

		/**
		 * The root file of the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $plugin_file;

		/**
		 * The root directory of the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $directory_path;

		/**
		 * The root directory of the plugin as a URL.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		private $directory_url;

		/**
		 * Create class instance.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_file Absolute path to the main plugin file.
		 */
		public function __construct( $plugin_file ) {
			$this->plugin_file    = $plugin_file;
			$this->directory_path = plugin_dir_path( $plugin_file );
			$this->directory_url  = plugin_dir_url( $plugin_file );

			add_action( 'charitable_start', array( $this, 'start' ), 6 );
		}

		/**
		 * Returns the original instance of this class.
		 *
		 * @since  1.0.0
		 *
		 * @return \Charitable\Pro\Paystack\Paystack
		 */
		public static function get_instance() {
			return self::$instance;
		}

		/**
		 * Run the startup sequence on the charitable_start hook.
		 *
		 * This is only ever executed once.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function start() {
			if ( $this->started() ) {
				return;
			}

			self::$instance = $this;

			$this->load_dependencies();
			$this->maybe_start_admin();
			$this->setup_licensing();
			$this->setup_i18n();
			$this->gateway();

			/**
			 * Do something when the plugin is first started.
			 *
			 * @since 1.0.0
			 *
			 * @param \Charitable\Pro\Paystack\Paystack $plugin This class instance.
			 */
			do_action( 'charitable_paystack_start', $this );
		}

		/**
		 * Include necessary files.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function load_dependencies() {
			$functions = $this->get_path( 'functions' );

			require_once( $functions . 'core-functions.php' );
		}

		/**
		 * Return the gateway class instance.
		 *
		 * @since  1.0.0
		 *
		 * @return \Charitable\Pro\Paystack\Gateway\Gateway
		 */
		public function gateway() {
			if ( ! isset( $this->gateway ) ) {
				$this->gateway = new \Charitable\Pro\Paystack\Gateway\Gateway();
			}

			return $this->gateway;
		}

		/**
		 * Load the admin-only functionality.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function maybe_start_admin() {
			if ( ! is_admin() ) {
				return;
			}

			new Admin();
		}

		/**
		 * Set up licensing for the extension.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function setup_licensing() {
			charitable_get_helper( 'licenses' )->register_licensed_product(
				self::NAME,
				self::AUTHOR,
				self::VERSION,
				$this->plugin_file
			);
		}

		/**
		 * Set up the internationalisation for the plugin.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function setup_i18n() {
			if ( class_exists( 'Charitable_i18n' ) ) {
				I18n::get_instance();
			}
		}

		/**
		 * Returns whether the plugin has already started.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function started() {
			return did_action( 'charitable_paystack_start' ) || current_filter() === 'charitable_paystack_start';
		}

		/**
		 * Returns the plugin's version number.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_version() {
			return self::VERSION;
		}

		/**
		 * Returns plugin paths.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $type          If empty, returns the path to the plugin.
		 * @param  boolean $absolute_path If true, returns the file system path. If false, returns it as a URL.
		 * @return string
		 */
		public function get_path( $type = '', $absolute_path = true ) {
			$base = $absolute_path ? $this->directory_path : $this->directory_url;

			switch ( $type ) {
				case 'includes':
				case 'src':
					$path = $base . 'src/';
					break;

				case 'functions':
					$path = $base . 'functions/';
					break;

				case 'admin-views':
					$path = $base . 'admin-views/';
					break;

				case 'templates':
					$path = $base . 'templates/';
					break;

				case 'directory':
					$path = $base;
					break;

				default:
					$path = $this->plugin_file;
			}

			return $path;
		}

		/**
		 * Throw error on object clone.
		 *
		 * This class is specifically designed to be instantiated once. You can retrieve the instance using charitable()
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function __clone() {
			charitable_paystack_deprecated()->doing_it_wrong(
				__FUNCTION__,
				__( 'Cloning this object is forbidden.', 'charitable-paystack' ),
				'1.0.0'
			);
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function __wakeup() {
			charitable_paystack_deprecated()->doing_it_wrong(
				__FUNCTION__,
				__( 'Unserializing instances of this class is forbidden.', 'charitable-paystack' ),
				'1.0.0'
			);
		}
	}

endif;
