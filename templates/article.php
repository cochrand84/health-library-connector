<?php
/**
 * Article view.
 *
 * Renders body.section[] in order: section heading, any popupMedia images,
 * then the section's html[] fragments. Displays meta.updateDate, as Mayo's
 * syndication terms require.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_article  = $args['request']->article;
$pgcc_meta     = (array) ( $pgcc_article['meta'] ?? array() );
$pgcc_sections = (array) ( $pgcc_article['body']['section'] ?? array() );
$pgcc_type     = (string) ( $pgcc_meta['contentType'] ?? '' );
$pgcc_abstract = pgcc_abstract_html( $pgcc_article['abstract'] ?? '' );
$pgcc_updated  = pgcc_format_date( $pgcc_meta['updateDate'] ?? '' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pgcc_back     = wp_get_referer();
?>
<article class="pgcc-article">
	<a class="pgcc-back" href="<?php echo esc_url( $pgcc_back ? $pgcc_back : $args['base_url'] ); ?>">&larr; <?php esc_html_e( 'Back', 'pulsegrid-content-connector' ); ?></a>

	<header class="pgcc-article-header">
		<?php if ( $pgcc_type ) : ?>
			<span class="pgcc-badge"><?php echo esc_html( $pgcc_type ); ?></span>
		<?php endif; ?>
		<h2 class="pgcc-article-title"><?php echo esc_html( wp_strip_all_tags( (string) ( $pgcc_article['title'] ?? '' ) ) ); ?></h2>
		<?php if ( $pgcc_abstract ) : ?>
			<div class="pgcc-abstract"><?php echo $pgcc_abstract; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by wp_kses in pgcc_abstract_html(). ?></div>
		<?php endif; ?>
	</header>

	<?php foreach ( $pgcc_sections as $pgcc_section ) : ?>
		<?php
		$pgcc_head  = (string) ( $pgcc_section['sectionHead'] ?? '' );
		$pgcc_html  = $pgcc_section['html'] ?? array();
		$pgcc_html  = is_array( $pgcc_html ) ? implode( '', $pgcc_html ) : (string) $pgcc_html;
		$pgcc_media = (array) ( $pgcc_section['popupMedia'] ?? array() );
		?>
		<section class="pgcc-section">
			<?php if ( $pgcc_head && 'Answer section' !== $pgcc_head ) : ?>
				<h3><?php echo esc_html( $pgcc_head ); ?></h3>
			<?php endif; ?>

			<?php foreach ( $pgcc_media as $pgcc_item ) : ?>
				<?php
				$pgcc_img  = (array) ( $pgcc_item['image'] ?? array() );
				$pgcc_guid = pgcc_sanitize_guid( $pgcc_img['id'] ?? '' );
				if ( '' === $pgcc_guid ) {
					continue;
				}
				$pgcc_w = absint( $pgcc_img['width'] ?? 0 );
				$pgcc_h = absint( $pgcc_img['height'] ?? 0 );
				?>
				<figure class="pgcc-figure">
					<img
						src="<?php echo esc_url( pgcc_image_url( $pgcc_guid ) ); ?>"
						alt="<?php echo esc_attr( wp_strip_all_tags( (string) ( $pgcc_img['alt'] ?? '' ) ) ); ?>"
						<?php if ( $pgcc_w && $pgcc_h ) : ?>
							width="<?php echo (int) $pgcc_w; ?>" height="<?php echo (int) $pgcc_h; ?>"
						<?php endif; ?>
						loading="lazy"
						decoding="async"
					>
					<?php if ( ! empty( $pgcc_img['caption'] ) ) : ?>
						<figcaption><?php echo wp_kses_post( $pgcc_img['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>

			<?php if ( '' !== trim( $pgcc_html ) ) : ?>
				<div class="pgcc-section-content"><?php echo pgcc_kses( $pgcc_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by wp_kses. ?></div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>

	<?php if ( $pgcc_updated ) : ?>
		<p class="pgcc-updated">
			<strong><?php esc_html_e( 'Last reviewed:', 'pulsegrid-content-connector' ); ?></strong>
			<?php echo esc_html( $pgcc_updated ); ?>
		</p>
	<?php endif; ?>
</article>
