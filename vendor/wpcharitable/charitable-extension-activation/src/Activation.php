<?php
/**
 * Activation handler for Charitable extensions.
 *
 * @package Charitable/Extensions/Activation/Activation
 * @since   1.0.0
 * @version 1.1.0
 */

namespace Charitable\Extensions\Activation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * \Charitable\Extensions\Activation\Activation
 *
 * @since 1.0.0
 */
class Activation {

	public $activation_notice;
	public $installation_notice;
	public $update_notice;

	private $has_charitable;
	private $charitable_base;
	private $has_min_version;

	/**
	 * Setup the activation class
	 *
	 * @since  1.0.0
	 *
	 * @param  string $min_version Minimum version required.
	 * @return void
	 */
	public function __construct( $min_version = '1.0.0' ) {
		/* If ABSPATH isn't defined, we can't use this. */
		if ( ! defined( '\ABSPATH' ) ) {
			return;
		}

		/* Charitable is active. */
		if ( class_exists( '\Charitable' ) ) {
			$this->has_min_version = version_compare( \Charitable::VERSION, $min_version, '>=' );
			return;
		}

		// We need plugin.php!
		require_once( \ABSPATH . 'wp-admin/includes/plugin.php' );

		/* Is Charitable installed? */
		foreach ( \get_plugins() as $plugin_path => $plugin ) {
			if ( 'Charitable' === $plugin['Name'] ) {
				$this->has_charitable  = true;
				$this->charitable_base = $plugin_path;
				break;
			}
		}
	}

	/**
	 * Check whether it's safe to activate the plugin.
	 *
	 * @since  1.1.0
	 *
	 * @return boolean
	 */
	public function ok() {
		return $this->has_min_version;
	}

	/**
	 * Process plugin deactivation
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_notices', array( $this, 'missing_charitable_notice' ) );
	}

	/**
	 * Display notice if Charitable isn't installed
	 *
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public function missing_charitable_notice() {
		if ( ! $this->has_min_version ) {
			if ( ! isset( $this->update_notice ) ) {
				return;
			}

			$notice = $this->update_notice;
			$url    = esc_url(
				wp_nonce_url(
					admin_url( 'update.php?action=upgrade-plugin&plugin=' . $this->charitable_base ),
					'upgrade-plugin_' . $this->charitable_base
				)
			);
		} elseif ( $this->has_charitable ) {
			if ( ! isset( $this->activation_notice ) ) {
				return;
			}

			$notice = $this->activation_notice;
			$url    = esc_url(
				wp_nonce_url(
					admin_url( 'plugins.php?action=activate&plugin=' . $this->charitable_base ),
					'activate-plugin_' . $this->charitable_base
				)
			);
		} else {
			if ( ! isset( $this->installation_notice ) ) {
				return;
			}

			$notice = $this->installation_notice;
			$url    = esc_url(
				wp_nonce_url(
					self_admin_url( 'update.php?action=install-plugin&plugin=charitable' ),
					'install-plugin_charitable'
				)
			);
		}

		echo '<div class="error"><p>' . sprintf( $notice, $url ) . '</p></div>';
	}
}
