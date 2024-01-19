<?php
/**
 * Subscriber Login Block.
 *
 * @since $$next-version$$
 *
 * @package automattic/jetpack
 */

namespace Automattic\Jetpack\Extensions\Subscriber_Login;

use Automattic\Jetpack\Blocks;
use Automattic\Jetpack\Extensions\Premium_Content\Subscription_Service\Abstract_Token_Subscription_Service;
use Automattic\Jetpack\Status\Host;
use Jetpack;
use Jetpack_Gutenberg;
use Jetpack_Memberships;
use Jetpack_Options;

/**
 * Registers the block for use in Gutenberg
 * This is done via an action so that we can disable
 * registration if we need to.
 */
function register_block() {
	if (
		! Jetpack::is_module_active( 'subscriptions' ) ||
		! class_exists( 'Jetpack_Memberships' ) ||
		! class_exists( 'Automattic\Jetpack\Extensions\Premium_Content\Subscription_Service\Abstract_Token_Subscription_Service' )
	) {
		return;
	}

	Blocks::jetpack_register_block(
		__DIR__,
		array( 'render_callback' => __NAMESPACE__ . '\render_block' )
	);

	add_action( 'wp_logout', __NAMESPACE__ . '\subscriber_logout' );
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Logs the subscriber out by clearing out the premium content cookie.
 *
 * @return void
 */
function subscriber_logout() {
	Abstract_Token_Subscription_Service::clear_token_cookie();
}

/**
 * Returns current URL.
 *
 * @return string
 */
function get_current_url() {
	if ( ! isset( $_SERVER['HTTP_HOST'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	return ( is_ssl() ? 'https://' : 'http://' ) . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/**
 * Returns subscriber log in URL.
 *
 * @return string
 */
function get_subscriber_login_url() {
	// Copied from projects/plugins/jetpack/extensions/blocks/subscriptions/subscriptions.php
	if ( ( new Host() )->is_wpcom_simple() ) {
		// On WPCOM we will redirect directly to the current page
		$redirect_url = get_current_url();
	} else {
		// On self-hosted we will save and hide the token
		$redirect_url = get_site_url() . '/wp-json/jetpack/v4/subscribers/auth';
		$redirect_url = add_query_arg( 'redirect_url', get_current_url(), $redirect_url );
	}

	return add_query_arg(
		array(
			'site_id'      => intval( Jetpack_Options::get_option( 'id' ) ),
			'redirect_url' => rawurlencode( $redirect_url ),
		),
		'https://subscribe.wordpress.com/memberships/jwt'
	);
}

/**
 * Determines whether the current visitor is a logged in user or a subscriber.
 *
 * @return bool
 */
function is_subscriber_logged_in() {
	return is_user_logged_in() || Abstract_Token_Subscription_Service::has_token_from_cookie();
}

/**
 * Renders Subscriber Login block.
 *
 * @return string
 */
function render_block() {
	Jetpack_Gutenberg::load_assets_as_required( __DIR__ );

	$block_template = '<div %1$s><a href="%2$s">%3$s</a></div>';

	if ( ! is_subscriber_logged_in() ) {
		return sprintf(
			$block_template,
			get_block_wrapper_attributes(),
			get_subscriber_login_url(),
			__( 'Log in', 'jetpack' )
		);
	}

	if ( Jetpack_Memberships::is_current_user_subscribed() ) {
		return sprintf(
			$block_template,
			get_block_wrapper_attributes(),
			'https://wordpress.com/read/subscriptions',
			__( 'Manage subscriptions', 'jetpack' )
		);
	}

	return sprintf(
		$block_template,
		get_block_wrapper_attributes(),
		wp_logout_url( get_current_url() ),
		__( 'Log out', 'jetpack' )
	);
}
