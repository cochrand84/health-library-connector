<?php
/**
 * One search result / featured article card.
 *
 * @var array $args { result: array, base_url: string }
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

$hlmc_r    = $args['result'];
$hlmc_id   = hlmc_sanitize_content_id( $hlmc_r['contentId'] ?? '' );
if ( '' === $hlmc_id ) {
	return;
}
$hlmc_type    = (string) ( $hlmc_r['contentType'] ?? '' );
$hlmc_snippet = hlmc_snippet( $hlmc_r['abstract'] ?? '' );
$hlmc_date    = hlmc_format_date( $hlmc_r['updateDate'] ?? '' );
?>
<li class="hlmc-result">
	<?php if ( $hlmc_type ) : ?>
		<span class="hlmc-badge"><?php echo esc_html( $hlmc_type ); ?></span>
	<?php endif; ?>
	<h3 class="hlmc-result-title">
		<a href="<?php echo esc_url( HLMC_Frontend::article_url( $hlmc_id, $args['base_url'] ) ); ?>"><?php echo esc_html( $hlmc_r['title'] ?? __( 'Untitled', 'health-library-for-mayo-clinic' ) ); ?></a>
	</h3>
	<?php if ( $hlmc_snippet ) : ?>
		<p class="hlmc-snippet"><?php echo esc_html( $hlmc_snippet ); ?></p>
	<?php endif; ?>
	<?php if ( $hlmc_date ) : ?>
		<p class="hlmc-date">
			<?php
			/* translators: %s: date */
			printf( esc_html__( 'Updated %s', 'health-library-for-mayo-clinic' ), esc_html( $hlmc_date ) );
			?>
		</p>
	<?php endif; ?>
</li>
