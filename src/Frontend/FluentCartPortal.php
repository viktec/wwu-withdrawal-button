<?php
/**
 * FluentCart customer-portal integration.
 *
 * FluentCart ships a Vue single-page customer portal, but it is fully extensible
 * from PHP through a set of documented server-side hooks (dev.fluentcart.com).
 * This class wires every relevant one so a FluentCart customer gets the same
 * withdrawal experience a WooCommerce customer gets:
 *
 *  - `fluent_cart/customer_portal/custom_endpoints`        → a dedicated
 *    "Right of withdrawal" portal page that renders the order chooser + form.
 *  - `fluent_cart/global_customer_menu_items`              → a sidebar entry
 *    that links to that page.
 *  - `fluent_cart/customer_dashboard_data`                 → a reassuring banner
 *    above the orders table.
 *  - `fluent_cart/customer/order_details_section_parts`    → the withdrawal
 *    button inside a single order's details (after the summary).
 *  - `fluent_cart/email_notification_merge_tags`           → a `{{wwu.recesso_url}}`
 *    merge tag for FluentCart's transactional emails.
 *
 * Every handler is defensive about the exact array shape it receives so a future
 * FluentCart release that renames a key degrades gracefully (the surface simply
 * does not appear) instead of fataling. The standalone `[wwu_wb_form]` page stays
 * available as a universal fallback regardless of portal internals.
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

namespace WWU\WithdrawalButton\Frontend;

use WWU\WithdrawalButton\Core\Services;
use WWU\WithdrawalButton\Core\Settings;
use WWU\WithdrawalButton\Platform\NormalizedOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCart portal integration.
 */
final class FluentCartPortal {

	/**
	 * Portal endpoint key (slug used by FluentCart to route to our page).
	 *
	 * @var string
	 */
	private const ENDPOINT_KEY = 'wwu-withdrawal';

	/**
	 * Wire every FluentCart customer-portal hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'fluent_cart/customer_portal/custom_endpoints', array( $this, 'register_endpoint' ), 10, 1 );
		add_filter( 'fluent_cart/global_customer_menu_items', array( $this, 'add_menu_item' ), 10, 2 );
		add_filter( 'fluent_cart/customer_dashboard_data', array( $this, 'add_dashboard_banner' ), 10, 1 );
		add_filter( 'fluent_cart/customer/order_details_section_parts', array( $this, 'inject' ), 10, 2 );
		add_filter( 'fluent_cart/email_notification_merge_tags', array( $this, 'register_merge_tags' ), 10, 1 );

		// The portal page is a server-rendered Vue SPA the asset gate cannot detect;
		// force our CSS/JS to load on it so the chooser + form are styled and work.
		add_filter( 'wwu_wb_force_enqueue_frontend', array( $this, 'maybe_enqueue_on_portal' ), 10, 1 );
	}

	/**
	 * Register a "Right of withdrawal" endpoint inside the FluentCart portal.
	 *
	 * @param mixed $endpoints Existing endpoint definitions (array expected).
	 * @return array
	 */
	public function register_endpoint( $endpoints ): array {
		$endpoints = is_array( $endpoints ) ? $endpoints : array();

		if ( ! Settings::enabled() ) {
			return $endpoints;
		}

		$definition = array(
			// Alias keys cover shape differences across FluentCart versions; unknown
			// keys are ignored by the consumer, so over-specifying is safe.
			'key'             => self::ENDPOINT_KEY,
			'slug'            => self::ENDPOINT_KEY,
			'label'           => __( 'Right of withdrawal', 'wwu-withdrawal-button' ),
			'title'           => __( 'Right of withdrawal', 'wwu-withdrawal-button' ),
			'render_callback' => array( $this, 'render_endpoint' ),
			'callback'        => array( $this, 'render_endpoint' ),
		);

		$endpoints[] = $definition;
		return $endpoints;
	}

	/**
	 * Render the portal endpoint: the customer's order chooser + form.
	 *
	 * FluentCart injects the returned HTML into its SPA shell. We make sure our
	 * frontend bundle is present (the portal page bypasses the standard gate).
	 *
	 * @param mixed ...$args Optional context passed by FluentCart (unused).
	 * @return string
	 */
	public function render_endpoint( ...$args ): string {
		( new Assets() )->ensure();

		$heading = '<h2 class="wwu-wb-portal__title">' . esc_html__( 'Right of withdrawal', 'wwu-withdrawal-button' ) . '</h2>';
		$chooser = EligibleOrders::render_for_user( get_current_user_id() );

		return '<div class="wwu-wb-portal">' . $heading . $chooser . '</div>';
	}

	/**
	 * Add a sidebar menu item that links to the withdrawal endpoint.
	 *
	 * @param mixed $menu_items Existing menu items (array expected).
	 * @param mixed $context    Portal context (may expose base_url).
	 * @return array
	 */
	public function add_menu_item( $menu_items, $context = null ): array {
		$menu_items = is_array( $menu_items ) ? $menu_items : array();

		if ( ! Settings::enabled() ) {
			return $menu_items;
		}

		$url = $this->endpoint_url( $context );

		$menu_items[] = array(
			'key'      => self::ENDPOINT_KEY,
			'slug'     => self::ENDPOINT_KEY,
			'label'    => __( 'Right of withdrawal', 'wwu-withdrawal-button' ),
			'title'    => __( 'Right of withdrawal', 'wwu-withdrawal-button' ),
			'url'      => $url,
			'route'    => self::ENDPOINT_KEY,
			'icon'     => 'dashicons dashicons-undo',
			'priority' => 50,
		);

		return $menu_items;
	}

	/**
	 * Add a reassuring banner above the customer's orders table.
	 *
	 * @param mixed $data Dashboard data (array expected).
	 * @return array
	 */
	public function add_dashboard_banner( $data ): array {
		$data = is_array( $data ) ? $data : array();

		if ( ! Settings::enabled() ) {
			return $data;
		}

		$url  = $this->endpoint_url( null );
		$text = esc_html__( 'Changed your mind? You can exercise your right of withdrawal on eligible orders.', 'wwu-withdrawal-button' );
		$cta  = esc_html__( 'Open the withdrawal page', 'wwu-withdrawal-button' );

		$banner  = '<div class="wwu-wb-portal-banner">';
		$banner .= '<p>' . $text . ' ';
		$banner .= '<a class="wwu-wb-portal-banner__link" href="' . esc_url( $url ) . '">' . $cta . '</a></p>';
		$banner .= '</div>';

		if ( ! isset( $data['sections_parts'] ) || ! is_array( $data['sections_parts'] ) ) {
			$data['sections_parts'] = array();
		}
		$existing = isset( $data['sections_parts']['before_orders_table'] ) ? (string) $data['sections_parts']['before_orders_table'] : '';
		$data['sections_parts']['before_orders_table'] = $existing . $banner;

		return $data;
	}

	/**
	 * Inject the withdrawal button into a single order's details (after summary).
	 *
	 * @param mixed $sections Named HTML slots (array expected).
	 * @param mixed $context  Context — expected to expose the order model.
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

		if ( ! Settings::enabled() || ! Services::instance()->applicability->decide( $order )->show ) {
			return $sections;
		}

		// `after_summary` is the FluentCart-recommended slot for per-order CTAs.
		$html = $this->button_html( $order );
		$sections['after_summary'] = ( $sections['after_summary'] ?? '' ) . $html;
		return $sections;
	}

	/**
	 * Register a `{{wwu.recesso_url}}` merge tag for FluentCart emails.
	 *
	 * Best-effort: the merge-tag registry shape varies, so we add a group entry
	 * AND a flat entry. Unknown keys are ignored by the consumer.
	 *
	 * @param mixed $tags Existing merge tags (array expected).
	 * @return array
	 */
	public function register_merge_tags( $tags ): array {
		$tags = is_array( $tags ) ? $tags : array();

		$url = $this->standalone_page_url();
		if ( '' === $url ) {
			return $tags;
		}

		// Grouped shape (group => [ merge_code => label ]).
		if ( ! isset( $tags['wwu'] ) || ! is_array( $tags['wwu'] ) ) {
			$tags['wwu'] = array();
		}
		$tags['wwu']['{{wwu.recesso_url}}'] = __( 'Right of withdrawal page URL', 'wwu-withdrawal-button' );

		// Flat shape (merge_code => value) as a fallback for simpler parsers.
		$tags['{{wwu.recesso_url}}'] = $url;

		return $tags;
	}

	/**
	 * Force-load the frontend bundle on the FluentCart portal page.
	 *
	 * Detection is best-effort: FluentCart renders its portal via its own
	 * shortcode/block on a normal WordPress page. When that marker is present we
	 * opt in; otherwise we leave the decision to the standard gate.
	 *
	 * @param mixed $enqueue Current decision.
	 * @return bool
	 */
	public function maybe_enqueue_on_portal( $enqueue ): bool {
		if ( $enqueue ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post ) {
			return false;
		}
		$content = (string) $post->post_content;
		foreach ( array( 'fluent_cart_customer_portal', 'fluentcart_customer_portal', 'fluent-cart/customer-portal', 'fluent_cart/customer-portal' ) as $needle ) {
			if ( false !== strpos( $content, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build the button HTML for a single order in the portal.
	 *
	 * @param NormalizedOrder $order Order.
	 * @return string
	 */
	private function button_html( NormalizedOrder $order ): string {
		$services = Services::instance();
		$locale   = '' !== $order->locale ? $order->locale : determine_locale();

		$url = $this->standalone_page_url();
		$url = '' !== $url
			? add_query_arg( 'wwu_wb_order', rawurlencode( $order->order_ref ), $url )
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
	 * URL of the in-portal withdrawal endpoint.
	 *
	 * Prefers a base URL supplied by FluentCart in the portal context; falls back
	 * to the standalone public form page so the link is never dead.
	 *
	 * @param mixed $context Portal context (may expose base_url).
	 * @return string
	 */
	private function endpoint_url( $context ): string {
		$base = '';
		if ( is_array( $context ) && ! empty( $context['base_url'] ) ) {
			$base = (string) $context['base_url'];
		} elseif ( is_object( $context ) && isset( $context->base_url ) ) {
			$base = (string) $context->base_url;
		}

		if ( '' !== $base ) {
			return trailingslashit( $base ) . self::ENDPOINT_KEY;
		}

		$standalone = $this->standalone_page_url();
		return '' !== $standalone ? $standalone : home_url( '/' );
	}

	/**
	 * URL of the standalone public withdrawal page (settings: public_form_page_id).
	 *
	 * @return string
	 */
	private function standalone_page_url(): string {
		$settings = (array) get_option( 'wwu_wb_settings', array() );
		$page_id  = (int) ( $settings['public_form_page_id'] ?? 0 );
		if ( $page_id <= 0 ) {
			return '';
		}
		$permalink = get_permalink( $page_id );
		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Extract an order reference from the FluentCart context (defensive).
	 *
	 * The documented context for `order_details_section_parts` exposes the order
	 * model under the `order` key; we also tolerate `order_id`/`id` scalars.
	 *
	 * @param mixed $context Context.
	 * @return string
	 */
	private function order_ref_from_context( $context ): string {
		if ( is_array( $context ) ) {
			foreach ( array( 'order', 'order_id', 'id' ) as $k ) {
				if ( ! isset( $context[ $k ] ) ) {
					continue;
				}
				$v = $context[ $k ];
				if ( is_object( $v ) && isset( $v->id ) ) {
					return (string) $v->id;
				}
				if ( is_scalar( $v ) ) {
					return (string) $v;
				}
			}
		}
		if ( is_object( $context ) && isset( $context->id ) ) {
			return (string) $context->id;
		}
		return '';
	}
}
