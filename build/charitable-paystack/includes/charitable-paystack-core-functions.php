<?php
/**
 * Charitable Paystack Core Functions.
 *
 * @package   Charitable Paystack/Functions/Core
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This returns the original Charitable_Paystack object.
 *
 * Use this whenever you want to get an instance of the class. There is no
 * reason to instantiate a new object, though you can do so if you're stubborn :)
 *
 * @since   1.0.0
 *
 * @return Charitable_Paystack
 */
function charitable_paystack() {
	return Charitable_Paystack::get_instance();
}

/**
 * This returns the Charitable_Paystack_Deprecated object.
 *
 * @since  1.0.0
 *
 * @return Charitable_Paystack_Deprecated
 */
function charitable_paystack_deprecated() {
	return Charitable_Paystack_Deprecated::get_instance();
}

/**
 * Displays a template.
 *
 * @since  1.0.0
 *
 * @param  string|array $template_name A single template name or an ordered array of template.
 * @param  array        $args          Optional array of arguments to pass to the view.
 * @return Charitable_Paystack_Template
 */
function charitable_paystack_template( $template_name, array $args = [] ) {
	if ( empty( $args ) ) {
		$template = new Charitable_Paystack_Template( $template_name );
	} else {
		$template = new Charitable_Paystack_Template( $template_name, false );
		$template->set_view_args( $args );
		$template->render();
	}

	return $template;
}
