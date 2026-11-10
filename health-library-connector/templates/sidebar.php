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
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

$hlmc_s     = $args['settings'];
$hlmc_match = $args['match'];
$hlmc_row   = ( 'links' === $args['sidebar'] && $hlmc_match ) ? $hlmc_match['row'] : null;
?>
<?php if ( $hlmc_row ) : ?>

	<?php if ( ! empty( $hlmc_row['provider'] ) ) : ?>
		<div class="hlmc-cta hlmc-cta-primary hlmc-links-provider">
			<h3><?php echo esc_html( $hlmc_s['links_provider_heading'] ); ?></h3>
			<?php foreach ( $hlmc_row['provider'] as $hlmc_link ) : ?>
				<a class="hlmc-button" href="<?php echo esc_url( $hlmc_link['url'] ); ?>"><?php echo esc_html( $hlmc_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hlmc_row['services'] ) ) : ?>
		<div class="hlmc-cta hlmc-links-services">
			<h3><?php echo esc_html( $hlmc_s['links_services_heading'] ); ?></h3>
			<ul class="hlmc-link-list">
				<?php foreach ( $hlmc_row['services'] as $hlmc_link ) : ?>
					<li><a href="<?php echo esc_url( $hlmc_link['url'] ); ?>"><?php echo esc_html( $hlmc_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

<?php else : ?>
	<div class="hlmc-cta hlmc-cta-primary">
		<h3><?php echo esc_html( $hlmc_s['cta_title'] ); ?></h3>
		<?php if ( $hlmc_s['cta_text'] ) : ?>
			<p><?php echo esc_html( $hlmc_s['cta_text'] ); ?></p>
		<?php endif; ?>
		<?php if ( $hlmc_s['cta_url'] ) : ?>
			<a class="hlmc-button" href="<?php echo esc_url( $hlmc_s['cta_url'] ); ?>"><?php echo esc_html( $hlmc_s['cta_button'] ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
<p class="hlmc-disclaimer"><?php esc_html_e( 'This health information is for educational purposes only and is not a substitute for professional medical advice.', 'health-library-connector' ); ?></p>
