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
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

$hlmc_article  = $args['request']->article;
$hlmc_meta     = (array) ( $hlmc_article['meta'] ?? array() );
$hlmc_sections = (array) ( $hlmc_article['body']['section'] ?? array() );
$hlmc_type     = (string) ( $hlmc_meta['contentType'] ?? '' );
$hlmc_abstract = hlmc_abstract_html( $hlmc_article['abstract'] ?? '' );
$hlmc_updated  = hlmc_format_date( $hlmc_meta['updateDate'] ?? '' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hlmc_back     = wp_get_referer();
?>
<article class="hlmc-article">
	<a class="hlmc-back" href="<?php echo esc_url( $hlmc_back ? $hlmc_back : $args['base_url'] ); ?>">&larr; <?php esc_html_e( 'Back', 'health-library-connector' ); ?></a>

	<header class="hlmc-article-header">
		<?php if ( $hlmc_type ) : ?>
			<span class="hlmc-badge"><?php echo esc_html( $hlmc_type ); ?></span>
		<?php endif; ?>
		<h2 class="hlmc-article-title"><?php echo esc_html( wp_strip_all_tags( (string) ( $hlmc_article['title'] ?? '' ) ) ); ?></h2>
		<?php if ( $hlmc_abstract ) : ?>
			<div class="hlmc-abstract"><?php echo $hlmc_abstract; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by wp_kses in hlmc_abstract_html(). ?></div>
		<?php endif; ?>
	</header>

	<?php foreach ( $hlmc_sections as $hlmc_section ) : ?>
		<?php
		$hlmc_head  = (string) ( $hlmc_section['sectionHead'] ?? '' );
		$hlmc_html  = $hlmc_section['html'] ?? array();
		$hlmc_html  = is_array( $hlmc_html ) ? implode( '', $hlmc_html ) : (string) $hlmc_html;
		$hlmc_media = (array) ( $hlmc_section['popupMedia'] ?? array() );
		?>
		<section class="hlmc-section">
			<?php if ( $hlmc_head && 'Answer section' !== $hlmc_head ) : ?>
				<h3><?php echo esc_html( $hlmc_head ); ?></h3>
			<?php endif; ?>

			<?php foreach ( $hlmc_media as $hlmc_item ) : ?>
				<?php
				$hlmc_img  = (array) ( $hlmc_item['image'] ?? array() );
				$hlmc_guid = hlmc_sanitize_guid( $hlmc_img['id'] ?? '' );
				if ( '' === $hlmc_guid ) {
					continue;
				}
				$hlmc_w = absint( $hlmc_img['width'] ?? 0 );
				$hlmc_h = absint( $hlmc_img['height'] ?? 0 );
				?>
				<figure class="hlmc-figure">
					<img
						src="<?php echo esc_url( hlmc_image_url( $hlmc_guid ) ); ?>"
						alt="<?php echo esc_attr( wp_strip_all_tags( (string) ( $hlmc_img['alt'] ?? '' ) ) ); ?>"
						<?php if ( $hlmc_w && $hlmc_h ) : ?>
							width="<?php echo (int) $hlmc_w; ?>" height="<?php echo (int) $hlmc_h; ?>"
						<?php endif; ?>
						loading="lazy"
						decoding="async"
					>
					<?php if ( ! empty( $hlmc_img['caption'] ) ) : ?>
						<figcaption><?php echo wp_kses_post( $hlmc_img['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>

			<?php if ( '' !== trim( $hlmc_html ) ) : ?>
				<div class="hlmc-section-content"><?php echo hlmc_kses( $hlmc_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by wp_kses. ?></div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>

	<?php if ( $hlmc_updated ) : ?>
		<p class="hlmc-updated">
			<strong><?php esc_html_e( 'Last reviewed:', 'health-library-connector' ); ?></strong>
			<?php echo esc_html( $hlmc_updated ); ?>
		</p>
	<?php endif; ?>
</article>
