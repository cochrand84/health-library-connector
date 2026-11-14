<?php
/**
 * One search result / featured article card.
 *
 * @var array $args { result: array, base_url: string }
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_r    = $args['result'];
$pgcc_id   = pgcc_sanitize_content_id( $pgcc_r['contentId'] ?? '' );
if ( '' === $pgcc_id ) {
	return;
}
$pgcc_type    = (string) ( $pgcc_r['contentType'] ?? '' );
$pgcc_snippet = pgcc_snippet( $pgcc_r['abstract'] ?? '' );
$pgcc_date    = pgcc_format_date( $pgcc_r['updateDate'] ?? '' );
?>
<li class="pgcc-result">
	<?php if ( $pgcc_type ) : ?>
		<span class="pgcc-badge"><?php echo esc_html( $pgcc_type ); ?></span>
	<?php endif; ?>
	<h3 class="pgcc-result-title">
		<a href="<?php echo esc_url( PGCC_Frontend::article_url( $pgcc_id, $args['base_url'] ) ); ?>"><?php echo esc_html( $pgcc_r['title'] ?? __( 'Untitled', 'pulsegrid-content-connector' ) ); ?></a>
	</h3>
	<?php if ( $pgcc_snippet ) : ?>
		<p class="pgcc-snippet"><?php echo esc_html( $pgcc_snippet ); ?></p>
	<?php endif; ?>
	<?php if ( $pgcc_date ) : ?>
		<p class="pgcc-date">
			<?php
			/* translators: %s: date */
			printf( esc_html__( 'Updated %s', 'pulsegrid-content-connector' ), esc_html( $pgcc_date ) );
			?>
		</p>
	<?php endif; ?>
</li>
