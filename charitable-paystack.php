<?php
/**
 * Plugin Name:       Charitable - Paystack
 * Plugin URI:        https://www.wpcharitable.com/extensions/charitable-paystack
 * Description:       Accept donations with Paystack, a leading payment processor in Africa.
 * Version:           1.0.0
 * Author:            WP Charitable
 * Author URI:        https://www.wpcharitable.com
 * Requires at least: 5.5
 * Tested up to:      5.6.1
 *
 * Text Domain: charitable-paystack
 * Domain Path: /languages/
 *
 * @package  Charitable Paystack
 * @category Core
 * @author   WP Charitable
 */

namespace Charitable\Pro\Paystack;

use Charitable\Extensions\Activation\Activation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load plugin class, but only if Charitable is found and activated.
 *
 * @return false|Charitable_Paystack Whether the class was loaded.
 */
add_action(
	'plugins_loaded',
	function() {
		/* Load Activation script. */
		require_once( 'vendor/wpcharitable/charitable-extension-activation/src/Activation.php' );

		$activation = new Activation( '1.6.45' );

		if ( $activation->ok() ) {
			spl_autoload_register( '\Charitable\Pro\Paystack\autoloader' );

			require_once( 'includes/class-charitable-paystack.php' );

			return new Paystack( __FILE__ );
		}

		/* translators: %s: link to activate Charitable */
		$activation->activation_notice = __( 'Charitable Paystack requires Charitable! Please <a href="%s">activate it</a> to continue.', 'charitable-paystack' );

		/* translators: %s: link to install Charitable */
		$activation->installation_notice = __( 'Charitable Paystack requires Charitable! Please <a href="%s">install it</a> to continue.', 'charitable-paystack' );

		/* translators: %s: link to update Charitable */
		$activation->update_notice = __( 'Charitable Paystack requires Charitable 1.6.45+! Please <a href="%s">update Charitable</a> to continue.', 'charitable-paystack' );

		$activation->run();

		return false;
	}
);

/**
 * Set up the plugin autoloader.
 *
 * After registering this autoload function with SPL, the following line
 * would cause the function to attempt to load the \Charitable\Pro\Paystack\Foo class
 * from src/Foo.php:
 *
 *      new \Charitable\Pro\Paystack\Foo;
 *
 * @since  1.0.0
 *
 * @param  string $class The fully-qualified class name.
 * @return void
 */
function autoloader( $class ) {
	/* Plugin namespace prefix. */
	$prefix = 'Charitable\\Pro\\Paystack\\';

	/* Base directory for the namespace prefix. */
	$base_dir = __DIR__ . '/src/';

	/* Check if the class name uses the namespace prefix. */
	$len = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class, $len ) ) {
		return;
	}

	/* Get the relative class name. */
	$relative_class = substr( $class, $len );

	/* Get the file path. */
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	/* Bail out if the file doesn't exist. */
	if ( ! file_exists( $file ) ) {
		return;
	}

	/* Finally, require the file. */
	require $file;
}
