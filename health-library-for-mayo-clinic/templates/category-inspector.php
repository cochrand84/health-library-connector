<?php
/**
 * Admin-only panel under an article: its Mayo categories and the sidebar
 * row that matched. Never shown to visitors.
 *
 * @var array $args See library.php.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

$hlmc_paths  = HLMC_Care_Links::category_paths( $args['request']->article );
$hlmc_match  = $args['match'];
$hlmc_rows   = HLMC_Care_Links::rows();
$hlmc_mapped = array();
foreach ( $hlmc_rows as $hlmc_row ) {
	foreach ( $hlmc_row['ids'] as $hlmc_id ) {
		if ( ! isset( $hlmc_mapped[ $hlmc_id ] ) ) {
			$hlmc_mapped[ $hlmc_id ] = $hlmc_row['topic'] ? $hlmc_row['topic'] : '#';
		}
	}
}
$hlmc_edit = admin_url( 'options-general.php?page=' . HLMC_Admin::PAGE . '&tab=links' );
?>
<details class="hlmc-inspector">
	<summary>
		<?php esc_html_e( 'Mayo categories on this article', 'health-library-for-mayo-clinic' ); ?>
		<span class="hlmc-inspector-note"><?php esc_html_e( '(only administrators see this)', 'health-library-for-mayo-clinic' ); ?></span>
	</summary>

	<p class="hlmc-inspector-result">
		<?php if ( $hlmc_match && ! $hlmc_match['fallback'] ) : ?>
			<?php
			printf(
				/* translators: 1: topic, 2: mcvId */
				esc_html__( 'Sidebar: %1$s (matched category %2$s).', 'health-library-for-mayo-clinic' ),
				'<strong>' . esc_html( $hlmc_match['row']['topic'] ? $hlmc_match['row']['topic'] : '#' . ( $hlmc_match['index'] + 1 ) ) . '</strong>',
				'<code>' . esc_html( $hlmc_match['id'] ) . '</code>'
			);
			?>
		<?php elseif ( $hlmc_match ) : ?>
			<?php
			printf(
				/* translators: %s: topic */
				esc_html__( 'No category is mapped, so the default row is shown: %s.', 'health-library-for-mayo-clinic' ),
				'<strong>' . esc_html( $hlmc_match['row']['topic'] ) . '</strong>'
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'No category on this article is mapped, and there is no default row.', 'health-library-for-mayo-clinic' ); ?>
		<?php endif; ?>
		<?php if ( 'links' !== $args['sidebar'] ) : ?>
			<em><?php esc_html_e( '(The sidebar is not set to show care links on this page.)', 'health-library-for-mayo-clinic' ); ?></em>
		<?php endif; ?>
	</p>

	<?php if ( empty( $hlmc_paths ) ) : ?>
		<p><?php esc_html_e( 'This article has no category data.', 'health-library-for-mayo-clinic' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Each line runs from broad to specific. The most specific mapped category wins.', 'health-library-for-mayo-clinic' ); ?></p>
		<ul class="hlmc-inspector-paths">
			<?php foreach ( $hlmc_paths as $hlmc_path ) : ?>
				<li>
					<?php
					$hlmc_parts = array();
					foreach ( $hlmc_path as $hlmc_node ) {
						$hlmc_is_match = $hlmc_match && $hlmc_match['id'] === $hlmc_node['id'];
						$hlmc_label    = esc_html( $hlmc_node['title'] ) . ' <code>' . esc_html( $hlmc_node['id'] ) . '</code>';
						if ( isset( $hlmc_mapped[ $hlmc_node['id'] ] ) ) {
							$hlmc_label .= ' <span class="hlmc-inspector-mapped">→ ' . esc_html( $hlmc_mapped[ $hlmc_node['id'] ] ) . '</span>';
						}
						$hlmc_parts[] = $hlmc_is_match ? '<mark>' . $hlmc_label . '</mark>' : $hlmc_label;
					}
					echo implode( ' › ', $hlmc_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $hlmc_edit ); ?>"><?php esc_html_e( 'Edit sidebar links', 'health-library-for-mayo-clinic' ); ?> →</a></p>
</details>
