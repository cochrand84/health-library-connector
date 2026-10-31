<?php
/**
 * Sidebar: a generic call to action, or specialists matched to the article.
 *
 * @var array $args See library.php.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

$hlmc_s     = $args['settings'];
$hlmc_match = $args['match'];
$hlmc_cta   = $hlmc_s['cta_url'] ? $hlmc_s['cta_url'] : '';
?>
<?php if ( 'providers' === $args['sidebar'] && $hlmc_match ) : ?>
	<div class="hlmc-cta hlmc-cta-primary">
		<h3>
			<?php
			/* translators: %s: specialty name */
			printf( esc_html__( '%s specialists', 'health-library-for-mayo-clinic' ), esc_html( $hlmc_match['specialty'] ) );
			?>
		</h3>
		<p><?php esc_html_e( 'Based on this article, these providers may be able to help.', 'health-library-for-mayo-clinic' ); ?></p>
	</div>
	<?php foreach ( $hlmc_match['providers'] as $hlmc_p ) : ?>
		<div class="hlmc-cta hlmc-provider">
			<h4><?php echo esc_html( $hlmc_p['name'] ?? '' ); ?></h4>
			<?php if ( ! empty( $hlmc_p['title'] ) ) : ?>
				<p class="hlmc-provider-title"><?php echo esc_html( $hlmc_p['title'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $hlmc_p['phone'] ) ) : ?>
				<p class="hlmc-provider-phone"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hlmc_p['phone'] ) ); ?>"><?php echo esc_html( $hlmc_p['phone'] ); ?></a></p>
			<?php endif; ?>
			<?php
			$hlmc_link = ! empty( $hlmc_p['url'] ) ? $hlmc_p['url'] : $hlmc_cta;
			if ( $hlmc_link ) :
				?>
				<a class="hlmc-button hlmc-button-secondary" href="<?php echo esc_url( $hlmc_link ); ?>"><?php esc_html_e( 'Schedule appointment', 'health-library-for-mayo-clinic' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php else : ?>
	<div class="hlmc-cta hlmc-cta-primary">
		<h3><?php echo esc_html( $hlmc_s['cta_title'] ); ?></h3>
		<?php if ( $hlmc_s['cta_text'] ) : ?>
			<p><?php echo esc_html( $hlmc_s['cta_text'] ); ?></p>
		<?php endif; ?>
		<?php if ( $hlmc_cta ) : ?>
			<a class="hlmc-button" href="<?php echo esc_url( $hlmc_cta ); ?>"><?php echo esc_html( $hlmc_s['cta_button'] ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
<p class="hlmc-disclaimer"><?php esc_html_e( 'This health information is for educational purposes only and is not a substitute for professional medical advice.', 'health-library-for-mayo-clinic' ); ?></p>
