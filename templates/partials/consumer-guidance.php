<?php
/**
 * Consumer-facing "how withdrawal works" guidance.
 *
 * A reassuring, plain-language explanation shown wherever a consumer can start a
 * withdrawal (the form, the account chooser, the public/guest page). It both
 * improves UX (reduces hesitation) and strengthens compliance (clear, transparent
 * information about the right and the process). All strings are i18n.
 *
 * @var bool $compact When true, render only the short intro (no details block).
 *
 * @package WWU\WithdrawalButton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$compact = isset( $compact ) ? (bool) $compact : false;
?>
<div class="wwu-wb-guidance">
	<p class="wwu-wb-guidance__intro">
		<?php esc_html_e( 'You can withdraw from this contract within 14 days, without giving any reason. It takes two short steps and we will email you a confirmation straight away.', 'wwu-withdrawal-button' ); ?>
	</p>

	<?php if ( ! $compact ) : ?>
		<details class="wwu-wb-guidance__details">
			<summary><?php esc_html_e( 'How it works & what happens next', 'wwu-withdrawal-button' ); ?></summary>
			<ul class="wwu-wb-guidance__list">
				<li><?php esc_html_e( 'You have 14 days to withdraw — counted from when you (or someone you nominated) received the goods, or from the day the contract was concluded for a service.', 'wwu-withdrawal-button' ); ?></li>
				<li><?php esc_html_e( 'You do not need to explain why. There are no hidden steps and no obligation to call us.', 'wwu-withdrawal-button' ); ?></li>
				<li><?php esc_html_e( 'Fill in your name and email, then confirm. Right after confirming, we email you an acknowledgement of receipt — keep it as your proof.', 'wwu-withdrawal-button' ); ?></li>
				<li><?php esc_html_e( 'We refund all payments you made for the order, including the standard delivery cost, within 14 days, using the same payment method you used.', 'wwu-withdrawal-button' ); ?></li>
				<li><?php esc_html_e( 'If your order is physical goods, please send them back within 14 days of telling us. We may wait until we receive them (or your proof of return) before refunding; return shipping may be at your expense unless we stated otherwise.', 'wwu-withdrawal-button' ); ?></li>
				<li><?php esc_html_e( 'Some items cannot be withdrawn by law (for example, sealed items unsealed after delivery, or digital content you agreed to start immediately). If that applies, we will let you know.', 'wwu-withdrawal-button' ); ?></li>
			</ul>
			<p class="wwu-wb-guidance__help"><?php esc_html_e( 'If anything is unclear, contact us before confirming — we are happy to help.', 'wwu-withdrawal-button' ); ?></p>
		</details>
	<?php endif; ?>
</div>
