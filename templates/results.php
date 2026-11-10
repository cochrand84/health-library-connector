<?php
/**
 * Search results with paging.
 *
 * @var array $args See library.php.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

$hlmc_request = $args['request'];
$hlmc_results = (array) ( $hlmc_request->search['searchResults'] ?? array() );
$hlmc_total   = (int) ( $hlmc_request->search['count'] ?? count( $hlmc_results ) );
$hlmc_per     = max( 1, (int) $args['settings']['per_page'] );
$hlmc_pages   = (int) ceil( $hlmc_total / $hlmc_per );
?>
<div class="hlmc-search-results">
	<p class="hlmc-count">
		<?php
		printf(
			/* translators: 1: number of results, 2: search terms */
			esc_html( _n( '%1$s result for “%2$s”', '%1$s results for “%2$s”', $hlmc_total, 'health-library-connector' ) ),
			esc_html( number_format_i18n( $hlmc_total ) ),
			esc_html( $hlmc_request->query )
		);
		?>
	</p>

	<?php if ( empty( $hlmc_results ) ) : ?>
		<p><?php esc_html_e( 'No results found. Try different search terms.', 'health-library-connector' ); ?></p>
	<?php else : ?>
		<ul class="hlmc-results">
			<?php
			foreach ( $hlmc_results as $hlmc_result ) {
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

		<?php if ( $hlmc_pages > 1 ) : ?>
			<nav class="hlmc-pagination" aria-label="<?php esc_attr_e( 'Search results pages', 'health-library-connector' ); ?>">
				<?php if ( $hlmc_request->page > 1 ) : ?>
					<a class="hlmc-prev" href="<?php echo esc_url( HLMC_Frontend::search_url( $hlmc_request->query, $hlmc_request->page - 1, $args['base_url'] ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'health-library-connector' ); ?></a>
				<?php endif; ?>
				<span class="hlmc-page-of">
					<?php
					/* translators: 1: current page, 2: total pages */
					printf( esc_html__( 'Page %1$d of %2$d', 'health-library-connector' ), (int) $hlmc_request->page, (int) $hlmc_pages );
					?>
				</span>
				<?php if ( $hlmc_request->page < $hlmc_pages ) : ?>
					<a class="hlmc-next" href="<?php echo esc_url( HLMC_Frontend::search_url( $hlmc_request->query, $hlmc_request->page + 1, $args['base_url'] ) ); ?>"><?php esc_html_e( 'Next', 'health-library-connector' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
