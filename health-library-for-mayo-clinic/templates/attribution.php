<?php
/**
 * Attribution footer.
 *
 * In live mode this renders the copyright notice and Terms of Use link the
 * Mayo Clinic Syndication Guide requires on pages showing licensed content.
 * Check your license agreement for the exact wording you must display, and
 * override this template if it differs.
 *
 * @var array $args See library.php.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="hlmc-attribution">
	<?php if ( $args['live'] ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: current year */
				esc_html__( '© 1998-%s Mayo Foundation for Medical Education and Research (MFMER). All rights reserved.', 'health-library-for-mayo-clinic' ),
				esc_html( wp_date( 'Y' ) )
			);
			?>
			<a href="https://www.mayoclinic.org/about-this-site/terms-use-licensed-content" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of Use', 'health-library-for-mayo-clinic' ); ?></a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'In live mode, this area shows the Mayo Clinic copyright notice and Terms of Use link required by the content license.', 'health-library-for-mayo-clinic' ); ?></p>
	<?php endif; ?>
</footer>
