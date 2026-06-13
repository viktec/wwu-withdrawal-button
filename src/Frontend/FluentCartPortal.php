<?php
/**
 * FluentCart customer-portal integration.
 *
 * Injects the withdrawal button into the order-details view via the documented
 * `fluent_cart/customer/order_details_section_parts` filter (end_of_order slot).
 * Because the portal is a Vue SPA whose handling of server-injected HTML can vary,
 * the reliable fallback is the standalone `[wwu_wb_form]` page (shortcode) keyed
 * by order + email — merchants are guided to publish it.
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

namespace WWU\WithdrawalButton\Frontend;

use WWU\WithdrawalButton\Core\Services;
use WWU\WithdrawalButton\Platform\NormalizedOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCart portal integration.
 */
final class FluentCartPortal {

	/**
	 * Wire the injection filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'fluent_cart/customer/order_details_section_parts', array( $this, 'inject' ), 10, 2 );
	}

	/**
	 * Inject the withdrawal button into the order-details sections.
	 *
	 * @param array $sections Named HTML slots.
	 * @param mixed $context  Context (expected to expose the order id).
	 * @return array
	 */
	public function inject( $sections, $context = null ): array {
		$sections = (array) $sections;

		$order_ref = $this->order_ref_from_context( $context );
		if ( '' === $order_ref ) {
			return $sections;
		}

		$adapter = Services::instance()->platforms->get( 'fluentcart' );
		if ( ! $adapter ) {
			return $sections;
		}
		$order = $adapter->get_order( $order_ref );
		if ( ! $order ) {
			return $sections;
		}

		$settings = (array) get_option( 'wwu_wb_settings', array() );
		if ( empty( $settings['enabled'] ) || ! Services::instance()->applicability->decide( $order )->show ) {
			return $sections;
		}

		$html = $this->button_html( $order );
		$sections['end_of_order'] = ( $sections['end_of_order'] ?? '' ) . $html;
		return $sections;
	}

	/**
	 * Build the button HTML for the portal.
	 *
	 * @param NormalizedOrder $order Order.
	 * @return string
	 */
	private function button_html( NormalizedOrder $order ): string {
		$services = Services::instance();
		$locale   = '' !== $order->locale ? $order->locale : determine_locale();

		$settings = (array) get_option( 'wwu_wb_settings', array() );
		$page_id  = (int) ( $settings['public_form_page_id'] ?? 0 );
		$url      = $page_id > 0
			? add_query_arg( 'wwu_wb_order', rawurlencode( $order->order_ref ), get_permalink( $page_id ) )
			: '#';

		return Template::render(
			'button/withdrawal-button.php',
			array(
				'url'            => $url,
				'label'          => $services->labels->withdraw_label( $order->country, $locale ),
				'days_remaining' => $services->window->days_remaining( $order ),
			)
		);
	}

	/**
	 * Extract an order reference from the FluentCart context (defensive).
	 *
	 * @param mixed $context Context.
	 * @return string
	 */
	private function order_ref_from_context( $context ): string {
		if ( is_array( $context ) ) {
			foreach ( array( 'order_id', 'id', 'order' ) as $k ) {
				if ( isset( $context[ $k ] ) ) {
					$v = $context[ $k ];
					if ( is_object( $v ) && isset( $v->id ) ) {
						return (string) $v->id;
					}
					if ( is_scalar( $v ) ) {
						return (string) $v;
					}
				}
			}
		}
		if ( is_object( $context ) && isset( $context->id ) ) {
			return (string) $context->id;
		}
		return '';
	}
}
