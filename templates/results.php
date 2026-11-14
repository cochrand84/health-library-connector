<?php
/**
 * Search results with paging.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_request = $args['request'];
$pgcc_results = (array) ( $pgcc_request->search['searchResults'] ?? array() );
$pgcc_total   = (int) ( $pgcc_request->search['count'] ?? count( $pgcc_results ) );
$pgcc_per     = max( 1, (int) $args['settings']['per_page'] );
$pgcc_pages   = (int) ceil( $pgcc_total / $pgcc_per );
?>
<div class="pgcc-search-results">
	<p class="pgcc-count">
		<?php
		printf(
			/* translators: 1: number of results, 2: search terms */
			esc_html( _n( '%1$s result for “%2$s”', '%1$s results for “%2$s”', $pgcc_total, 'pulsegrid-content-connector' ) ),
			esc_html( number_format_i18n( $pgcc_total ) ),
			esc_html( $pgcc_request->query )
		);
		?>
	</p>

	<?php if ( empty( $pgcc_results ) ) : ?>
		<p><?php esc_html_e( 'No results found. Try different search terms.', 'pulsegrid-content-connector' ); ?></p>
	<?php else : ?>
		<ul class="pgcc-results">
			<?php
			foreach ( $pgcc_results as $pgcc_result ) {
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

		<?php if ( $pgcc_pages > 1 ) : ?>
			<nav class="pgcc-pagination" aria-label="<?php esc_attr_e( 'Search results pages', 'pulsegrid-content-connector' ); ?>">
				<?php if ( $pgcc_request->page > 1 ) : ?>
					<a class="pgcc-prev" href="<?php echo esc_url( PGCC_Frontend::search_url( $pgcc_request->query, $pgcc_request->page - 1, $args['base_url'] ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'pulsegrid-content-connector' ); ?></a>
				<?php endif; ?>
				<span class="pgcc-page-of">
					<?php
					/* translators: 1: current page, 2: total pages */
					printf( esc_html__( 'Page %1$d of %2$d', 'pulsegrid-content-connector' ), (int) $pgcc_request->page, (int) $pgcc_pages );
					?>
				</span>
				<?php if ( $pgcc_request->page < $pgcc_pages ) : ?>
					<a class="pgcc-next" href="<?php echo esc_url( PGCC_Frontend::search_url( $pgcc_request->query, $pgcc_request->page + 1, $args['base_url'] ) ); ?>"><?php esc_html_e( 'Next', 'pulsegrid-content-connector' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
