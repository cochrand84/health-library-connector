<?php
/**
 * Search box.
 *
 * @var array $args { action: string, query: string, placeholder: string }
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

// Keep any query args already in the action URL (e.g. ?page_id=12 on sites
// using plain permalinks), since a GET form replaces the query string.
$pgcc_action = (string) $args['action'];
$pgcc_hidden = array();
$pgcc_qs     = wp_parse_url( $pgcc_action, PHP_URL_QUERY );
if ( $pgcc_qs ) {
	wp_parse_str( $pgcc_qs, $pgcc_hidden );
	$pgcc_action = strtok( $pgcc_action, '?' );
}
unset( $pgcc_hidden[ PGCC_Request::PARAM_ID ], $pgcc_hidden[ PGCC_Request::PARAM_QUERY ], $pgcc_hidden[ PGCC_Request::PARAM_PAGE ] );
$pgcc_field_id = wp_unique_id( 'pgcc-q-' );
?>
<form class="pgcc-search" role="search" method="get" action="<?php echo esc_url( $pgcc_action ); ?>">
	<?php foreach ( $pgcc_hidden as $pgcc_name => $pgcc_value ) : ?>
		<?php if ( is_scalar( $pgcc_value ) ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $pgcc_name ); ?>" value="<?php echo esc_attr( $pgcc_value ); ?>">
		<?php endif; ?>
	<?php endforeach; ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $pgcc_field_id ); ?>"><?php esc_html_e( 'Search the health library', 'pulsegrid-content-connector' ); ?></label>
	<input
		type="search"
		id="<?php echo esc_attr( $pgcc_field_id ); ?>"
		name="<?php echo esc_attr( PGCC_Request::PARAM_QUERY ); ?>"
		value="<?php echo esc_attr( $args['query'] ); ?>"
		placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		required
	>
	<button type="submit" class="pgcc-search-submit wp-element-button"><?php esc_html_e( 'Search', 'pulsegrid-content-connector' ); ?></button>
</form>
