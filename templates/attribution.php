<?php
/**
 * Attribution footer.
 *
 * In live mode this renders the copyright notice and Terms of Use link that
 * Mayo Clinic's syndication terms require on pages showing licensed content.
 * Check your license agreement for the exact wording you must display, and
 * override this template if it differs.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="pgcc-attribution">
	<?php if ( $args['live'] ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: current year */
				esc_html__( '© 1998-%s Mayo Foundation for Medical Education and Research (MFMER). All rights reserved.', 'pulsegrid-content-connector' ),
				esc_html( wp_date( 'Y' ) )
			);
			?>
			<a href="https://www.mayoclinic.org/about-this-site/terms-use-licensed-content" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of Use', 'pulsegrid-content-connector' ); ?></a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'In live mode, this area shows the Mayo Clinic copyright notice and Terms of Use link required by the content license.', 'pulsegrid-content-connector' ); ?></p>
	<?php endif; ?>
</footer>
