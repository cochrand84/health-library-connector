<?php
/**
 * Library wrapper. Override by copying to your-theme/health-library/library.php.
 *
 * @var array $args {
 *     @type HLMC_Request $request  Current request (mode, article, search, error).
 *     @type bool         $live     True when showing real Mayo Clinic content.
 *     @type string       $base_url URL of the page hosting the library.
 *     @type string       $sidebar   'none' | 'cta' | 'links'.
 *     @type array        $featured  Featured search results for the landing view.
 *     @type array|null   $match     Care-links match for the article (HLMC_Care_Links::match()).
 *     @type bool         $inspector Show the admin-only category inspector.
 *     @type array        $settings Plugin settings.
 * }
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

$hlmc_request = $args['request'];
$hlmc_has_sidebar = 'none' !== $args['sidebar'];
?>
<div class="hlmc hlmc-mode-<?php echo esc_attr( $hlmc_request->mode ); ?><?php echo $hlmc_has_sidebar ? ' hlmc-has-sidebar' : ''; ?>">

	<?php if ( ! $args['live'] ) : ?>
		<div class="hlmc-demo-notice" role="note">
			<strong><?php esc_html_e( 'Demo mode.', 'health-library-connector' ); ?></strong>
			<?php esc_html_e( 'These sample articles were written to demonstrate the plugin. They are not Mayo Clinic content and are not medical advice.', 'health-library-connector' ); ?>
		</div>
	<?php endif; ?>

	<?php
	hlmc_template(
		'search-form',
		array(
			'action'      => $args['base_url'],
			'query'       => $hlmc_request->query,
			'placeholder' => __( 'Search health topics…', 'health-library-connector' ),
		)
	);
	?>

	<?php if ( $hlmc_request->error ) : ?>
		<div class="hlmc-notice hlmc-notice-error" role="alert"><?php echo esc_html( $hlmc_request->error ); ?></div>
	<?php endif; ?>

	<div class="hlmc-layout">
		<div class="hlmc-main">
			<?php
			if ( 'article' === $hlmc_request->mode ) {
				hlmc_template( 'article', $args );
			} elseif ( 'search' === $hlmc_request->mode ) {
				hlmc_template( 'results', $args );
			} else {
				hlmc_template( 'home', $args );
			}
			?>
		</div>

		<?php if ( $hlmc_has_sidebar ) : ?>
			<aside class="hlmc-sidebar" aria-label="<?php esc_attr_e( 'Find care', 'health-library-connector' ); ?>">
				<?php hlmc_template( 'sidebar', $args ); ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $args['inspector'] ) ) : ?>
		<?php hlmc_template( 'category-inspector', $args ); ?>
	<?php endif; ?>

	<?php hlmc_template( 'attribution', $args ); ?>
</div>
