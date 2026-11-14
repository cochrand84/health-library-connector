<?php
/**
 * Admin-only panel under an article: its Mayo categories and the sidebar
 * row that matched. Never shown to visitors.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_paths  = PGCC_Care_Links::category_paths( $args['request']->article );
$pgcc_match  = $args['match'];
$pgcc_rows   = PGCC_Care_Links::rows();
$pgcc_mapped = array();
foreach ( $pgcc_rows as $pgcc_row ) {
	foreach ( $pgcc_row['ids'] as $pgcc_id ) {
		if ( ! isset( $pgcc_mapped[ $pgcc_id ] ) ) {
			$pgcc_mapped[ $pgcc_id ] = $pgcc_row['topic'] ? $pgcc_row['topic'] : '#';
		}
	}
}
$pgcc_edit = admin_url( 'options-general.php?page=' . PGCC_Admin::PAGE . '&tab=links' );
?>
<details class="pgcc-inspector">
	<summary>
		<?php esc_html_e( 'Mayo categories on this article', 'pulsegrid-content-connector' ); ?>
		<span class="pgcc-inspector-note"><?php esc_html_e( '(only administrators see this)', 'pulsegrid-content-connector' ); ?></span>
	</summary>

	<p class="pgcc-inspector-result">
		<?php if ( $pgcc_match && ! $pgcc_match['fallback'] ) : ?>
			<?php
			printf(
				/* translators: 1: topic, 2: mcvId */
				esc_html__( 'Sidebar: %1$s (matched category %2$s).', 'pulsegrid-content-connector' ),
				'<strong>' . esc_html( $pgcc_match['row']['topic'] ? $pgcc_match['row']['topic'] : '#' . ( $pgcc_match['index'] + 1 ) ) . '</strong>',
				'<code>' . esc_html( $pgcc_match['id'] ) . '</code>'
			);
			?>
		<?php elseif ( $pgcc_match ) : ?>
			<?php
			printf(
				/* translators: %s: topic */
				esc_html__( 'No category is mapped, so the default row is shown: %s.', 'pulsegrid-content-connector' ),
				'<strong>' . esc_html( $pgcc_match['row']['topic'] ) . '</strong>'
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'No category on this article is mapped, and there is no default row.', 'pulsegrid-content-connector' ); ?>
		<?php endif; ?>
		<?php if ( 'links' !== $args['sidebar'] ) : ?>
			<em><?php esc_html_e( '(The sidebar is not set to show care links on this page.)', 'pulsegrid-content-connector' ); ?></em>
		<?php endif; ?>
	</p>

	<?php if ( empty( $pgcc_paths ) ) : ?>
		<p><?php esc_html_e( 'This article has no category data.', 'pulsegrid-content-connector' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Each line runs from broad to specific. The most specific mapped category wins.', 'pulsegrid-content-connector' ); ?></p>
		<ul class="pgcc-inspector-paths">
			<?php foreach ( $pgcc_paths as $pgcc_path ) : ?>
				<li>
					<?php
					$pgcc_parts = array();
					foreach ( $pgcc_path as $pgcc_node ) {
						$pgcc_is_match = $pgcc_match && $pgcc_match['id'] === $pgcc_node['id'];
						$pgcc_label    = esc_html( $pgcc_node['title'] ) . ' <code>' . esc_html( $pgcc_node['id'] ) . '</code>';
						if ( isset( $pgcc_mapped[ $pgcc_node['id'] ] ) ) {
							$pgcc_label .= ' <span class="pgcc-inspector-mapped">→ ' . esc_html( $pgcc_mapped[ $pgcc_node['id'] ] ) . '</span>';
						}
						$pgcc_parts[] = $pgcc_is_match ? '<mark>' . $pgcc_label . '</mark>' : $pgcc_label;
					}
					echo implode( ' › ', $pgcc_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $pgcc_edit ); ?>"><?php esc_html_e( 'Edit sidebar links', 'pulsegrid-content-connector' ); ?> →</a></p>
</details>
