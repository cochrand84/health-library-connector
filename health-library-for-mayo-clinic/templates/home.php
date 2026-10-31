<?php
/**
 * Landing view: intro plus featured articles.
 *
 * @var array $args See library.php.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="hlmc-home">
	<p class="hlmc-intro">
		<?php esc_html_e( 'Search our library of trusted health information. Enter a condition, symptom, procedure, or health topic to get started.', 'health-library-for-mayo-clinic' ); ?>
	</p>

	<?php if ( ! empty( $args['featured'] ) ) : ?>
		<h2 class="hlmc-heading"><?php esc_html_e( 'Featured health topics', 'health-library-for-mayo-clinic' ); ?></h2>
		<ul class="hlmc-results hlmc-results-grid">
			<?php
			foreach ( $args['featured'] as $hlmc_result ) {
				hlmc_template(
					'result-item',
					array(
						'result'   => $hlmc_result,
						'base_url' => $args['base_url'],
					)
				);
			}
			?>
		</ul>
	<?php endif; ?>
</div>
