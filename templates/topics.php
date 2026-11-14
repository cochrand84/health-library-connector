<?php
/**
 * Topic buttons ([pulsegrid_topics]).
 *
 * @var array $args { heading: string, items: array of { label, url } }
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="pgcc pgcc-topics-wrap" aria-label="<?php echo esc_attr( $args['heading'] ? $args['heading'] : __( 'Health topics', 'pulsegrid-content-connector' ) ); ?>">
	<?php if ( $args['heading'] ) : ?>
		<h3 class="pgcc-topics-heading"><?php echo esc_html( $args['heading'] ); ?></h3>
	<?php endif; ?>
	<ul class="pgcc-topics">
		<?php foreach ( $args['items'] as $pgcc_item ) : ?>
			<li><a href="<?php echo esc_url( $pgcc_item['url'] ); ?>"><?php echo esc_html( $pgcc_item['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
