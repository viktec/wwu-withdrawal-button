<?php
/**
 * Builds the list of a logged-in customer's orders that are relevant to the
 * withdrawal function: those eligible to start a withdrawal now, plus those that
 * already have a withdrawal request (shown with their status).
 *
 * Shared by the My Account "Right of withdrawal" tab (WooMyAccount) and the
 * public [wwu_wb_form] page when it is opened without a specific order, so both
 * surfaces present the customer with something actionable instead of an empty
 * page. WooCommerce-scoped (FluentCart has its own portal); returns '' when no
 * WooCommerce order source is available.
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

namespace WWU\WithdrawalButton\Frontend;

use WWU\WithdrawalButton\Core\Services;
use WWU\WithdrawalButton\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eligible-orders list builder.
 */
final class EligibleOrders {

	/**
	 * How many recent orders to scan for the list.
	 *
	 * @var int
	 */
	private const SCAN_LIMIT = 20;

	/**
	 * Render the customer's withdrawal-relevant orders (or guidance when none).
	 *
	 * @param int $user_id Current user id.
	 * @return string
	 */
	public static function render_for_user( int $user_id ): string {
		$rows = self::collect( $user_id );

		return Template::render(
			'myaccount/withdrawal-list.php',
			array(
				'rows'       => $rows,
				'logged_in'  => $user_id > 0,
				'orders_url' => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '',
			)
		);
	}

	/**
	 * Collect the relevant order rows for a user.
	 *
	 * @param int $user_id Current user id.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect( int $user_id ): array {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$adapter = Services::instance()->platforms->get( 'woocommerce' );
		if ( ! $adapter ) {
			return array();
		}

		$wc_orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => self::SCAN_LIMIT,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'type'        => 'shop_order',
			)
		);

		$services = Services::instance();
		$enabled  = Settings::enabled();
		$rows     = array();

		foreach ( (array) $wc_orders as $wc_order ) {
			if ( ! $wc_order instanceof \WC_Order ) {
				continue;
			}
			$order = $adapter->get_order( (string) $wc_order->get_id() );
			if ( ! $order ) {
				continue;
			}

			$status   = (string) $adapter->get_meta( $order->order_ref, 'status' );
			$eligible = $enabled && $services->applicability->decide( $order )->show;

			// Only surface orders the customer can act on or has already acted on;
			// hide orders that are simply out of scope to avoid noise.
			if ( '' === $status && ! $eligible ) {
				continue;
			}

			$created = $wc_order->get_date_created();
			$locale  = '' !== $order->locale ? $order->locale : determine_locale();

			$rows[] = array(
				'number'   => (string) $order->number,
				'date'     => $created ? wc_format_datetime( $created ) : '',
				'status'   => $status,
				'eligible' => $eligible,
				'url'      => self::form_url( $order->order_ref ),
				'label'    => $services->labels->withdraw_label( $order->country, $locale ),
			);
		}

		return $rows;
	}

	/**
	 * URL of the withdrawal form for an order (account endpoint + order ref).
	 *
	 * @param string $order_ref Order reference.
	 * @return string
	 */
	private static function form_url( string $order_ref ): string {
		$settings = (array) get_option( 'wwu_wb_settings', array() );
		$slug     = sanitize_title( (string) ( $settings['endpoint_slug'] ?? 'wwu-withdrawal' ) );
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return add_query_arg( 'wwu_wb_order', rawurlencode( $order_ref ), wc_get_account_endpoint_url( $slug ) );
		}
		return add_query_arg( 'wwu_wb_order', rawurlencode( $order_ref ) );
	}
}
