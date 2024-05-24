<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Automattic\Jetpack\Publicize;

use Automattic\Jetpack\Connection\Rest_Authentication as Connection_Rest_Authentication;
use PHPUnit\Framework\TestCase;
use WorDBless\Options as WorDBless_Options;
use WorDBless\Posts as WorDBless_Posts;
use WorDBless\Users as WorDBless_Users;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Unit tests for the REST_Controller class.
 *
 * @package automattic/jetpack-publicize
 */
class Test_REST_Controller extends TestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * REST Server object.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Setting up the test.
	 *
	 * @before
	 */
	public function set_up() {
		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		$this->admin_id = wp_insert_user(
			array(
				'user_login' => 'dummy_user',
				'user_pass'  => 'dummy_pass',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( 0 );
		$this->setup_jetpack_connections();

		// Register REST routes.
		add_action( 'rest_api_init', array( new REST_Controller(), 'register_rest_routes' ) );

		do_action( 'rest_api_init' );
	}

	/**
	 * Returning the environment into its initial state.
	 *
	 * @after
	 */
	public function tear_down() {
		wp_set_current_user( 0 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		unset(
			$_GET['_for'],
			$_GET['token'],
			$_GET['timestamp'],
			$_GET['nonce'],
			$_GET['body-hash'],
			$_GET['signature'],
			$_SERVER['REQUEST_METHOD']
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		WorDBless_Options::init()->clear_options();
		WorDBless_Posts::init()->clear_all_posts();
		WorDBless_Users::init()->clear_all_users();
	}

	/**
	 * Testing the `POST /jetpack/v4/publicize/connections` endpoint without proper permissions.
	 */
	public function test_get_publicize_connections_without_proper_permission() {
		$request  = new WP_REST_Request( 'GET', '/jetpack/v4/publicize/connections' );
		$response = $this->dispatch_request_signed_with_blog_token( $request );
		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'Sorry, you are not allowed to do that.', $response->get_data()['message'] );
	}

	/**
	 * Testing the `POST /jetpack/v4/publicize/connections` endpoint with proper permissions.
	 */
	public function test_get_publicize_connections_with_proper_permission() {
		$request = new WP_REST_Request( 'GET', '/jetpack/v4/publicize/connections' );
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch_request_signed_with_blog_token( $request );
		$this->assertCount( 2, $response->data );
	}

	/**
	 * Signs a request with a blog token before dispatching it.
	 *
	 * Ensures that these tests pass through Connection_Rest_Authentication::wp_rest_authenticate,
	 * because WP_REST_Server::dispatch doesn't call any auth logic (in a real
	 * request, this would all happen earlier).
	 *
	 * @param WP_REST_Request $request The request to sign before dispatching.
	 * @return WP_REST_Response
	 */
	private function dispatch_request_signed_with_blog_token( $request ) {
		add_filter( 'jetpack_options', array( $this, 'mock_jetpack_site_connection_options' ), 10, 2 );

		$token     = 'new:1:0';
		$timestamp = (string) time();
		$nonce     = 'testing123';
		$body_hash = '';

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$_GET['_for']      = 'jetpack';
		$_GET['token']     = $token;
		$_GET['timestamp'] = $timestamp;
		$_GET['nonce']     = $nonce;
		$_GET['body-hash'] = $body_hash;
		// This is intentionally using base64_encode().
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$_GET['signature'] = base64_encode(
			hash_hmac(
				'sha1',
				implode(
					"\n",
					array(
						$token,
						$timestamp,
						$nonce,
						$body_hash,
						'POST',
						'anything.example',
						'80',
						'',
					)
				) . "\n",
				'blogtoken',
				true
			)
		);

		$jp_connection_auth = Connection_Rest_Authentication::init();
		$jp_connection_auth->wp_rest_authenticate( false );

		$response = $this->server->dispatch( $request );

		$jp_connection_auth->reset_saved_auth_state();

		remove_filter( 'jetpack_options', array( $this, 'mock_jetpack_site_connection_options' ) );

		return $response;
	}

	/**
	 * Intercept the `Jetpack_Options` call and mock the values.
	 * Site-level connection set-up.
	 *
	 * @param mixed  $value The current option value.
	 * @param string $name Option name.
	 *
	 * @return mixed
	 */
	public function mock_jetpack_site_connection_options( $value, $name ) {
		switch ( $name ) {
			case 'blog_token':
				return 'new.blogtoken';
			case 'user_tokens':
				return array( $this->admin_id => 'token.secret.' . $this->admin_id );
			case 'id':
				return get_current_blog_id();
		}

		return $value;
	}

	/**
	 * Dummy function to initialize publicize connections.
	 */
	public function get_connections() {
		return array(
			// Normally connected facebook.
			'facebook' => array(
				'id_number' => array(
					'connection_data' => array(
						'user_id'       => $this->admin_id,
						'id'            => '456',
						'connection_id' => '4560',
						'token_id'      => 'test-unique-id456',
						'meta'          => array(
							'display_name' => 'test-display-name456',
						),
					),
				),
			),
			// Globally connected tumblr.
			'tumblr'   => array(
				'id_number' => array(
					'connection_data' => array(
						'user_id'       => 0,
						'id'            => '123',
						'connection_id' => '1230',
						'token_id'      => 'test-unique-id123',
						'meta'          => array(
							'display_name' => 'test-display-name123',
						),
					),
				),
			),
			// Globally connected nextdoor.
			'nextdoor' => array(
				'id_number' => array(
					'connection_data' => array(
						'user_id'       => 0,
						'id'            => '456',
						'connection_id' => '1236',
						'token_id'      => 'test-unique-id1234',
						'meta'          => array(
							'display_name' => 'test-display-name1234',
						),
					),
				),
			),
		);
	}

	/**
	 * Dummy function to initialize publicize connections.
	 */
	public function setup_jetpack_connections() {
		set_transient(
			'jetpack_social_connections',
			$this->get_connections(),
			3600
		);
	}
}
