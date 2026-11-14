<?php
/**
 * Landing view: intro plus featured articles.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pgcc-home">
	<p class="pgcc-intro">
		<?php esc_html_e( 'Search our library of trusted health information. Enter a condition, symptom, procedure, or health topic to get started.', 'pulsegrid-content-connector' ); ?>
	</p>

	<?php if ( ! empty( $args['featured'] ) ) : ?>
		<h2 class="pgcc-heading"><?php esc_html_e( 'Featured health topics', 'pulsegrid-content-connector' ); ?></h2>
		<ul class="pgcc-results pgcc-results-grid">
			<?php
			foreach ( $args['featured'] as $pgcc_result ) {
				pgcc_template(
					'result-item',
					array(
						'result'   => $pgcc_result,
						'base_url' => $args['base_url'],
					)
				);
			}
			?>
		</ul>
	<?php endif; ?>
</div>
