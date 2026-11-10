<?php
/**
 * Topic buttons ([health_library_topics]).
 *
 * @var array $args { heading: string, items: array of { label, url } }
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="hlmc hlmc-topics-wrap" aria-label="<?php echo esc_attr( $args['heading'] ? $args['heading'] : __( 'Health topics', 'health-library-connector' ) ); ?>">
	<?php if ( $args['heading'] ) : ?>
		<h3 class="hlmc-topics-heading"><?php echo esc_html( $args['heading'] ); ?></h3>
	<?php endif; ?>
	<ul class="hlmc-topics">
		<?php foreach ( $args['items'] as $hlmc_item ) : ?>
			<li><a href="<?php echo esc_url( $hlmc_item['url'] ); ?>"><?php echo esc_html( $hlmc_item['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
