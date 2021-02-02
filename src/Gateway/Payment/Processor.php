<?php
/**
 * The class responsible for creating payment requests for Paystack.
 *
 * @package   Charitable Paystack/Classes
 * @author    Eric Daams
 * @copyright Copyright (c) 2021, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

namespace Charitable\Pro\Paystack\Gateway\Payment;

use Charitable\Helpers\DonationDataMapper;
use Charitable\Gateways\Payment\ProcessorInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Charitable\Pro\Paystack\Gateway\Payment\Processor' ) ) :

	/**
	 * Payment Processor.
	 *
	 * @since 1.0.0
	 */
	class Processor implements ProcessorInterface {

		/**
		 * Get the payment request object.
		 *
		 * @since  1.0.0
		 *
		 * @param  \Charitable_Donation $donation The donation to make a payment request for.
		 * @return \Charitable\Gateways\Payment\RequestInterface
		 */
		public function get_payment_request( \Charitable_Donation $donation ) {
			$data_map = new DonationDataMapper( $donation );
			$data_map->add_map(
				array(
					'email'           => 'email',
					'first_name'      => 'customer.first_name',
					'last_name'       => 'customer.last_name',
					'phone'           => 'customer.phone',
					'address'         => 'customer.metadata.address',
					'address_2'       => 'customer.metadata.address_2',
					'city'            => 'customer.metadata.city',
					'country'         => 'customer.metadata.country',
					'postcode'        => 'customer.metadata.postcode',
					'donation_key'    => 'metadata.metadata.donation_key',
					'donation_id'     => 'metadata.metadata.donation_id',
					'amount_in_cents' => 'amount',
					'currency'        => 'currency',
					'return_url'      => 'callback_url',
				)
			);

			return new Request( $data_map );
		}
	}

endif;
