<?php
/**
 * Onboarding dashboard — the friendly "is everything set up, and where does the
 * button appear?" page. Written for non-technical merchants: a setup checklist
 * with status + one-click fixes, a plain explanation of where the button shows,
 * and the reasons it might be hidden on a given order.
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

namespace WWU\WithdrawalButton\Admin;

use WWU\WithdrawalButton\Core\Settings;
use WWU\WithdrawalButton\DurableMedium\PdfBuilder;
use WWU\WithdrawalButton\REST\Authentication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard / onboarding page.
 */
final class DashboardPage {

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Authentication::capability() ) ) {
			return;
		}

		$settings  = Settings::main();
		$app       = (array) get_option( 'wwu_wb_applicability', array() );
		$enabled   = ! empty( $settings['enabled'] );
		$page_id   = (int) ( $settings['public_form_page_id'] ?? 0 );
		$page_ok   = $page_id > 0 && 'publish' === get_post_status( $page_id );
		$pdf_ok    = PdfBuilder::is_available();
		$woo       = class_exists( 'WooCommerce' );
		$fluent    = function_exists( 'fluent_cart_api' );
		$mode      = (string) ( $app['mode'] ?? 'eu_eea_only' );

		echo '<div class="wrap wwu-wb-wrap">';
		echo '<h1>' . esc_html__( 'WWU Withdrawal Button', 'wwu-withdrawal-button' ) . '</h1>';
		echo '<p>' . esc_html__( 'The EU online right-of-withdrawal function (Art. 11a / Art. 54-bis) for WooCommerce & FluentCart.', 'wwu-withdrawal-button' ) . '</p>';

		$this->render_go_live( $settings );

		// --- Setup checklist ---
		echo '<h2>' . esc_html__( 'Setup checklist', 'wwu-withdrawal-button' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:880px;"><tbody>';

		$this->row(
			$enabled,
			__( 'Withdrawal function enabled', 'wwu-withdrawal-button' ),
			$enabled
				? __( 'The button is live for eligible consumers.', 'wwu-withdrawal-button' )
				: __( 'Turn it on in Settings to start showing the button.', 'wwu-withdrawal-button' ),
			$enabled ? '' : array( admin_url( 'admin.php?page=' . AdminController::SETTINGS_SLUG ), __( 'Enable now', 'wwu-withdrawal-button' ) )
		);

		$this->row(
			$woo || $fluent,
			__( 'E-commerce platform detected', 'wwu-withdrawal-button' ),
			( $woo ? 'WooCommerce ' : '' ) . ( $fluent ? 'FluentCart ' : '' ) . ( ( $woo || $fluent ) ? __( 'active.', 'wwu-withdrawal-button' ) : __( 'Activate WooCommerce or FluentCart.', 'wwu-withdrawal-button' ) )
		);

		$this->row(
			$page_ok,
			__( 'Withdrawal form page', 'wwu-withdrawal-button' ),
			$page_ok
				? sprintf( /* translators: %s: page URL. */ __( 'Published: %s', 'wwu-withdrawal-button' ), get_permalink( $page_id ) )
				: __( 'No published form page. Guests need it. Re-activate the plugin to auto-create it.', 'wwu-withdrawal-button' ),
			$page_ok ? array( get_permalink( $page_id ), __( 'View page', 'wwu-withdrawal-button' ) ) : ''
		);

		$this->row(
			$pdf_ok,
			__( 'PDF receipts', 'wwu-withdrawal-button' ),
			$pdf_ok
				? __( 'PDF library available — receipts include a PDF copy.', 'wwu-withdrawal-button' )
				: __( 'PDF library not found. The email receipt still works, but to also attach a PDF, install the plugin from the official ZIP (which bundles the library) instead of a source copy.', 'wwu-withdrawal-button' ),
			null,
			$pdf_ok ? 'ok' : 'warn'
		);

		echo '</tbody></table>';

		// --- Where does the button appear ---
		echo '<h2>' . esc_html__( 'Where the button appears', 'wwu-withdrawal-button' ) . '</h2>';
		echo '<ul style="list-style:disc;margin-left:1.4em;max-width:880px;">';
		echo '<li>' . esc_html__( 'In the customer account, on each eligible order (order list + order detail) and in the "Right of withdrawal" account tab.', 'wwu-withdrawal-button' ) . '</li>';
		echo '<li>' . esc_html__( 'As a link inside the WooCommerce order emails (so guests without an account can reach it).', 'wwu-withdrawal-button' ) . '</li>';
		echo '<li>' . wp_kses_post( sprintf( /* translators: %s: shortcodes. */ __( 'Anywhere you place a shortcode: %s.', 'wwu-withdrawal-button' ), '<code>[wwu_wb_button]</code>, <code>[wwu_wb_form]</code>' ) ) . '</li>';
		echo '</ul>';

		// --- Why it might not appear ---
		echo '<h2>' . esc_html__( "Why the button might not show on an order", 'wwu-withdrawal-button' ) . '</h2>';
		echo '<p>' . esc_html__( 'The button only appears where a real withdrawal right exists. It is hidden when:', 'wwu-withdrawal-button' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:1.4em;max-width:880px;">';
		echo '<li>' . wp_kses_post(
			sprintf(
				/* translators: %s: current mode label. */
				__( 'The consumer is <strong>outside the scope of your applicability mode</strong> (currently: <strong>%s</strong>). For example, a Switzerland order is hidden in "EU/EEA only" mode — that is correct, Swiss law mandates no button.', 'wwu-withdrawal-button' ),
				esc_html( $this->mode_label( $mode ) )
			)
		) . '</li>';
		echo '<li>' . esc_html__( 'The order is not in a contract state (failed, cancelled, refunded, awaiting payment).', 'wwu-withdrawal-button' ) . '</li>';
		echo '<li>' . esc_html__( 'Every item is excluded from the right of withdrawal (e.g. delivered digital content), or it is a business (VAT) order.', 'wwu-withdrawal-button' ) . '</li>';
		echo '<li>' . esc_html__( 'The function is disabled in Settings.', 'wwu-withdrawal-button' ) . '</li>';
		echo '</ul>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: settings URL. */
				__( 'Want it to show on <em>every</em> order regardless of country? Set the mode to "Always show it" in <a href="%s">Settings → Where the button applies</a>.', 'wwu-withdrawal-button' ),
				esc_url( admin_url( 'admin.php?page=' . AdminController::SETTINGS_SLUG ) )
			)
		) . '</p>';

		// --- Quick links ---
		echo '<h2>' . esc_html__( 'Next steps', 'wwu-withdrawal-button' ) . '</h2>';
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . AdminController::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'wwu-withdrawal-button' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . AdminController::COMPLIANCE_SLUG ) ) . '">' . esc_html__( 'Compliance & documents', 'wwu-withdrawal-button' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . AdminController::REQUESTS_SLUG ) ) . '">' . esc_html__( 'Withdrawal requests', 'wwu-withdrawal-button' ) . '</a>';
		echo '</p>';

		echo '<p style="margin-top:2em;color:#666;">' . wp_kses_post(
			sprintf(
				/* translators: %s: WebWakeUp link. */
				__( 'A free open-source compliance tool by %s, with mredodos and Matteo Alfieri (An Idea for Business).', 'wwu-withdrawal-button' ),
				'<a href="https://webwakeup.it" target="_blank" rel="noopener">WebWakeUp</a>'
			)
		) . '</p>';

		echo '</div>';
	}

	/**
	 * Render a checklist row.
	 *
	 * @param bool         $ok     Pass/fail.
	 * @param string       $label  Row label.
	 * @param string       $detail Detail text.
	 * @param array|string|null $action [url, text] action link, '' for none.
	 * @param string       $level  'ok'|'warn'|'err' (overrides the icon when $ok is false).
	 * @return void
	 */
	private function row( bool $ok, string $label, string $detail, $action = '', string $level = '' ): void {
		if ( $ok ) {
			$icon = '<span style="color:#008a20;font-weight:700;">&#10003;</span>';
		} elseif ( 'warn' === $level ) {
			$icon = '<span style="color:#996800;font-weight:700;">&#9888;</span>';
		} else {
			$icon = '<span style="color:#d63638;font-weight:700;">&#10007;</span>';
		}
		echo '<tr>';
		echo '<td style="width:28px;">' . $icon . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		echo '<td style="width:220px;"><strong>' . esc_html( $label ) . '</strong></td>';
		echo '<td>' . esc_html( $detail );
		if ( is_array( $action ) && 2 === count( $action ) ) {
			echo ' &nbsp;<a href="' . esc_url( $action[0] ) . '">' . esc_html( $action[1] ) . '</a>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render the go-live banner.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private function render_go_live( array $settings ): void {
		$go_live = (string) ( $settings['go_live_date'] ?? WWU_WB_GO_LIVE_DATE );
		echo '<p><span class="wwu-wb-badge wwu-wb-badge--warn">' . esc_html(
			sprintf(
				/* translators: %s: date. */
				__( 'Mandatory for EU/EEA consumers on contracts concluded on or after %s.', 'wwu-withdrawal-button' ),
				$go_live
			)
		) . '</span></p>';
	}

	/**
	 * Human label for an applicability mode.
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private function mode_label( string $mode ): string {
		switch ( $mode ) {
			case 'always':
				return __( 'Always show it', 'wwu-withdrawal-button' );
			case 'custom_list':
				return __( 'Custom country list', 'wwu-withdrawal-button' );
			case 'eu_eea_only':
			default:
				return __( 'Only EU/EEA consumers', 'wwu-withdrawal-button' );
		}
	}
}
