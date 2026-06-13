<?php
/**
 * Withdrawal landing view — lists the customer's withdrawal-relevant orders.
 *
 * Shown in the My Account "Right of withdrawal" tab and on the public form page
 * when no specific order is selected. Each eligible order links to its two-step
 * form; orders that already have a request show their status.
 *
 * @var array  $rows       Order rows {number, date, status, eligible, url, label}.
 * @var bool   $logged_in  Whether a customer is logged in.
 * @var string $orders_url URL of the WooCommerce "Orders" tab (may be '').
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows       = isset( $rows ) && is_array( $rows ) ? $rows : array();
$logged_in  = ! empty( $logged_in );
$orders_url = isset( $orders_url ) ? (string) $orders_url : '';
?>
<div class="wwu-wb-account-intro">
	<h2><?php esc_html_e( 'Right of withdrawal', 'wwu-withdrawal-button' ); ?></h2>

	<?php if ( ! $logged_in ) : ?>

		<p><?php esc_html_e( 'To start a withdrawal, open the link in your order confirmation email, or log in to your account to choose an order.', 'wwu-withdrawal-button' ); ?></p>

	<?php elseif ( empty( $rows ) ) : ?>

		<p><?php esc_html_e( 'You have no orders that can be withdrawn right now. The button appears on eligible orders within the withdrawal period.', 'wwu-withdrawal-button' ); ?></p>
		<?php if ( '' !== $orders_url ) : ?>
			<p><a class="wwu-wb-button" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'Go to my orders', 'wwu-withdrawal-button' ); ?></a></p>
		<?php endif; ?>

	<?php else : ?>

		<p><?php esc_html_e( 'Choose an order to withdraw from:', 'wwu-withdrawal-button' ); ?></p>
		<table class="wwu-wb-orders shop_table shop_table_responsive">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'wwu-withdrawal-button' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wwu-withdrawal-button' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wwu-withdrawal-button' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Order', 'wwu-withdrawal-button' ); ?>"><?php echo esc_html( '#' . (string) $row['number'] ); ?></td>
						<td data-title="<?php esc_attr_e( 'Date', 'wwu-withdrawal-button' ); ?>"><?php echo esc_html( (string) $row['date'] ); ?></td>
						<td data-title="<?php esc_attr_e( 'Action', 'wwu-withdrawal-button' ); ?>">
							<?php if ( '' !== (string) $row['status'] ) : ?>
								<span class="wwu-wb-status-notice"><?php echo esc_html( sprintf( /* translators: %s: status. */ __( 'Request status: %s', 'wwu-withdrawal-button' ), (string) $row['status'] ) ); ?></span>
							<?php else : ?>
								<a class="wwu-wb-button" href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html( (string) $row['label'] ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>
</div>
