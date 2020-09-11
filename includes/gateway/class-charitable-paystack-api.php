<?php
/**
 * Super-simple, minimum abstraction Paystack API wrapper.
 *
 * @package Charitable Paystack/Classes/Charitable_Paystack_API
 * @author  Eric Daams
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since   1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.0.0
 */
class Charitable_Paystack_API {

	private $api_key;
	private $api_endpoint = 'https://api.paystack.co';
	private $valid_api_key;

	/**
	 * Create a new instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key Your Paystack API key
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Perform a GET request to the Paystack API.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $method  The API endpoint we're calling.
	 * @param  mixed[] $args    Array of arguments.
	 * @param  int     $timeout Duration before request times out.
	 * @return mixed[]
	 */
	public function get( $method, $args = [], $timeout = 10 ) {
		return $this->make_request( 'get', $method, $args, $timeout );
	}

	/**
	 * Perform a POST request to the Paystack API.
	 *
	 * @since   1.0.0
	 *
	 * @param  string  $method  The API endpoint we're calling.
	 * @param  mixed[] $args    Array of arguments.
	 * @param  int     $timeout Duration before request times out.
	 * @return mixed[]
	 */
	public function post( $method, $args = [], $timeout = 10 ) {
		return $this->make_request( 'post', $method, $args, $timeout );
	}

	/**
	 * Perform a PUT request.
	 *
	 * @since  2.0.0
	 *
	 * @param  string  $method  The API endpoint we're calling.
	 * @param  mixed[] $args    Array of arguments.
	 * @param  int     $timeout Duration before request times out.
	 * @return mixed
	 */
	public function put( $method, $args = [], $timeout = 10 ) {
		$args['method'] = 'PUT';
		return $this->make_request( 'put', $method, $args, $timeout );
	}

	/**
	 * Performs the underlying HTTP request. Not very exciting.
	 *
	 * @since   1.0.0
	 *
	 * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete.
	 * @param  string $method    The API method to be called.
	 * @param  mixed[] $args     Assoc array of parameters to be passed as the body of the request.
	 * @return false|mixed[] Assoc array of decoded result. False if there was an error.
	 */
	public function make_request( $http_verb, $method, $args = [], $timeout = 10) {
		if ( ! $this->is_valid_api_key() ) {
			return false;
		}

		$http_verb = strtoupper( $http_verb );
		$url       = $this->api_endpoint . '/' . $method;
		$body      = empty( $args ) ? '' : json_encode( $args );

		$request_args = [
			'method'      => $http_verb,
			'timeout'     => 20,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'user-agent'  => 'Charitable Paystack/' . Charitable_Paystack::VERSION . '; ' . home_url(),
			'body'        => $body,
			'headers'     => [
				'content-type'  => 'application/json',
				'authorization' => 'Bearer ' . $this->api_key,
			],
		];


		switch ( $http_verb ) {
			case 'GET':
				$this->request = wp_remote_get( $url, $request_args );
				break;
			case 'POST':
				$this->request = wp_remote_post( $url, $request_args );
				break;
			default:
				$this->request = wp_remote_request( $url, $request_args );
		}

		if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
			error_log( var_export( $this->request, true ) );
		}

		/**
		 * If this is the first time we've called the API, check whether the API key is valid.
		 *
		 * We assume it is invalid if a WP_Error has been returned, or if a 401 response code
		 * was returned.
		 *
		 * @see https://developers.Paystack.com/docs/response
		 */
		if ( ! isset( $this->valid_api_key ) ) {
			$this->valid_api_key = $this->api_key_validated( $this->request );
		}

		/*
		if ( $this->is_failed_request( $this->request ) ) {
			return false;
		}
		*/

		return json_decode( wp_remote_retrieve_body( $this->request ) );
	}

	public function get_last_result() {
		return $this->request;
	}

	/**
	 * Checks whether the API key is valid.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean
	 */
	public function is_valid_api_key() {
		/* We will assume the API key is valid until we see evidence otherwise. */
		if ( ! isset( $this->valid_api_key ) ) {
			return true;
		}

		return $this->valid_api_key;
	}

	/**
	 * Checks whether the API key is valid, based on API call response.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $request The remote request results.
	 * @return boolean
	 */
	private function api_key_validated( $request ) {
		return '401' != wp_remote_retrieve_response_code( $request );
	}

	/**
	 * Returns whether the request failed.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $request The remote request results.
	 * @return boolean
	 */
	private function is_failed_request( $request ) {
		return is_wp_error( $request ) || 2 != substr( wp_remote_retrieve_response_code( $this->request ), 0, 1 );
	}
}
