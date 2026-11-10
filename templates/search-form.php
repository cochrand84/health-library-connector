<?php
/**
 * Search box.
 *
 * @var array $args { action: string, query: string, placeholder: string }
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

// Keep any query args already in the action URL (e.g. ?page_id=12 on sites
// using plain permalinks), since a GET form replaces the query string.
$hlmc_action = (string) $args['action'];
$hlmc_hidden = array();
$hlmc_qs     = wp_parse_url( $hlmc_action, PHP_URL_QUERY );
if ( $hlmc_qs ) {
	wp_parse_str( $hlmc_qs, $hlmc_hidden );
	$hlmc_action = strtok( $hlmc_action, '?' );
}
unset( $hlmc_hidden[ HLMC_Request::PARAM_ID ], $hlmc_hidden[ HLMC_Request::PARAM_QUERY ], $hlmc_hidden[ HLMC_Request::PARAM_PAGE ] );
$hlmc_field_id = wp_unique_id( 'hlmc-q-' );
?>
<form class="hlmc-search" role="search" method="get" action="<?php echo esc_url( $hlmc_action ); ?>">
	<?php foreach ( $hlmc_hidden as $hlmc_name => $hlmc_value ) : ?>
		<?php if ( is_scalar( $hlmc_value ) ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $hlmc_name ); ?>" value="<?php echo esc_attr( $hlmc_value ); ?>">
		<?php endif; ?>
	<?php endforeach; ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $hlmc_field_id ); ?>"><?php esc_html_e( 'Search the health library', 'health-library-connector' ); ?></label>
	<input
		type="search"
		id="<?php echo esc_attr( $hlmc_field_id ); ?>"
		name="<?php echo esc_attr( HLMC_Request::PARAM_QUERY ); ?>"
		value="<?php echo esc_attr( $args['query'] ); ?>"
		placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		required
	>
	<button type="submit" class="hlmc-search-submit wp-element-button"><?php esc_html_e( 'Search', 'health-library-connector' ); ?></button>
</form>
