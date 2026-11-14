<?php
/**
 * Sidebar on search and article views.
 *
 * - sidebar="links": on articles, the care links mapped to the article's
 *   Mayo topic ("Find a Provider" + "Services We Offer"); falls back to the
 *   call to action when nothing is mapped, and on search results.
 * - sidebar="cta": always the call to action.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_s     = $args['settings'];
$pgcc_match = $args['match'];
$pgcc_row   = ( 'links' === $args['sidebar'] && $pgcc_match ) ? $pgcc_match['row'] : null;
?>
<?php if ( $pgcc_row ) : ?>

	<?php if ( ! empty( $pgcc_row['provider'] ) ) : ?>
		<div class="pgcc-cta pgcc-cta-primary pgcc-links-provider">
			<h3><?php echo esc_html( $pgcc_s['links_provider_heading'] ); ?></h3>
			<?php foreach ( $pgcc_row['provider'] as $pgcc_link ) : ?>
				<a class="pgcc-button wp-element-button" href="<?php echo esc_url( $pgcc_link['url'] ); ?>"><?php echo esc_html( $pgcc_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $pgcc_row['services'] ) ) : ?>
		<div class="pgcc-cta pgcc-links-services">
			<h3><?php echo esc_html( $pgcc_s['links_services_heading'] ); ?></h3>
			<ul class="pgcc-link-list">
				<?php foreach ( $pgcc_row['services'] as $pgcc_link ) : ?>
					<li><a href="<?php echo esc_url( $pgcc_link['url'] ); ?>"><?php echo esc_html( $pgcc_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

<?php else : ?>
	<div class="pgcc-cta pgcc-cta-primary">
		<h3><?php echo esc_html( $pgcc_s['cta_title'] ); ?></h3>
		<?php if ( $pgcc_s['cta_text'] ) : ?>
			<p><?php echo esc_html( $pgcc_s['cta_text'] ); ?></p>
		<?php endif; ?>
		<?php if ( $pgcc_s['cta_url'] ) : ?>
			<a class="pgcc-button wp-element-button" href="<?php echo esc_url( $pgcc_s['cta_url'] ); ?>"><?php echo esc_html( $pgcc_s['cta_button'] ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
<p class="pgcc-disclaimer"><?php esc_html_e( 'This health information is for educational purposes only and is not a substitute for professional medical advice.', 'pulsegrid-content-connector' ); ?></p>
