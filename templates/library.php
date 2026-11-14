<?php
/**
 * Library wrapper. Override by copying to your-theme/pulsegrid/library.php.
 *
 * @var array $args {
 *     @type PGCC_Request $request  Current request (mode, article, search, error).
 *     @type bool         $live     True when showing real Mayo Clinic content.
 *     @type string       $base_url URL of the page hosting the library.
 *     @type string       $sidebar   'none' | 'cta' | 'links'.
 *     @type array        $featured  Featured search results for the landing view.
 *     @type array|null   $match     Care-links match for the article (PGCC_Care_Links::match()).
 *     @type bool         $inspector Show the admin-only category inspector.
 *     @type array        $settings Plugin settings.
 * }
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_request = $args['request'];
$pgcc_has_sidebar = 'none' !== $args['sidebar'];
?>
<div class="pgcc pgcc-mode-<?php echo esc_attr( $pgcc_request->mode ); ?><?php echo $pgcc_has_sidebar ? ' pgcc-has-sidebar' : ''; ?>">

	<?php if ( ! $args['live'] ) : ?>
		<div class="pgcc-demo-notice" role="note">
			<strong><?php esc_html_e( 'Demo mode.', 'pulsegrid-content-connector' ); ?></strong>
			<?php esc_html_e( 'These sample articles were written to demonstrate the plugin. They are not Mayo Clinic content and are not medical advice.', 'pulsegrid-content-connector' ); ?>
		</div>
	<?php endif; ?>

	<?php
	pgcc_template(
		'search-form',
		array(
			'action'      => $args['base_url'],
			'query'       => $pgcc_request->query,
			'placeholder' => __( 'Search health topics…', 'pulsegrid-content-connector' ),
		)
	);
	?>

	<?php if ( $pgcc_request->error ) : ?>
		<div class="pgcc-notice pgcc-notice-error" role="alert"><?php echo esc_html( $pgcc_request->error ); ?></div>
	<?php endif; ?>

	<div class="pgcc-layout">
		<div class="pgcc-main">
			<?php
			if ( 'article' === $pgcc_request->mode ) {
				pgcc_template( 'article', $args );
			} elseif ( 'search' === $pgcc_request->mode ) {
				pgcc_template( 'results', $args );
			} else {
				pgcc_template( 'home', $args );
			}
			?>
		</div>

		<?php if ( $pgcc_has_sidebar ) : ?>
			<aside class="pgcc-sidebar" aria-label="<?php esc_attr_e( 'Find care', 'pulsegrid-content-connector' ); ?>">
				<?php pgcc_template( 'sidebar', $args ); ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $args['inspector'] ) ) : ?>
		<?php pgcc_template( 'category-inspector', $args ); ?>
	<?php endif; ?>

	<?php pgcc_template( 'attribution', $args ); ?>
</div>
