<?php
/**
 * Small helpers shared by the plugin and its templates.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mayo content IDs look like CON-20372695, ART-20047732, SYM-20050922 or
 * GUID-xxxxxxxx-xxxx-...; allow letters, digits and hyphens only.
 *
 * @param string $id Raw ID.
 * @return string Sanitized ID, or '' if nothing usable.
 */
function hlmc_sanitize_content_id( $id ) {
	$id = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $id );
	return ( strlen( $id ) > 0 && strlen( $id ) <= 64 ) ? $id : '';
}

/**
 * Image GUIDs only. The image proxy accepts nothing else so it can't be used
 * as an open proxy to arbitrary API content.
 *
 * @param string $guid Raw GUID.
 * @return string Sanitized GUID or ''.
 */
function hlmc_sanitize_guid( $guid ) {
	$guid = trim( (string) $guid );
	return preg_match( '/^GUID-[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', $guid ) ? $guid : '';
}

/**
 * Mayo returns `abstract` as an array in search results and as a string in
 * articles, sometimes with <p> markup. Normalise to a string.
 *
 * @param mixed $abstract Raw value.
 * @return string
 */
function hlmc_abstract_text( $abstract ) {
	if ( is_array( $abstract ) ) {
		$abstract = implode( ' ', array_map( 'strval', $abstract ) );
	}
	return trim( (string) $abstract );
}

/**
 * Allowed markup for Mayo-provided HTML. Starts from the post allowlist and
 * removes anything interactive; Mayo content is trusted but defence in
 * depth is cheap.
 *
 * @return array
 */
function hlmc_allowed_html() {
	$allowed = wp_kses_allowed_html( 'post' );
	unset( $allowed['form'], $allowed['input'], $allowed['button'], $allowed['select'], $allowed['textarea'] );
	/**
	 * Filter the tags allowed in Mayo article HTML.
	 *
	 * @param array $allowed wp_kses allowlist.
	 */
	return apply_filters( 'hlmc_allowed_html', $allowed );
}

/**
 * Output-safe Mayo HTML.
 *
 * @param string $html Raw HTML.
 * @return string
 */
function hlmc_kses( $html ) {
	return wp_kses( (string) $html, hlmc_allowed_html() );
}

/**
 * Abstract markup: inline formatting plus <p> and lists. Rendered inside a
 * <div> (not a <p>) so nested paragraphs don't break the layout.
 *
 * @param string $abstract Abstract text/HTML.
 * @return string
 */
function hlmc_abstract_html( $abstract ) {
	$allowed = array(
		'p'      => array(),
		'b'      => array(),
		'i'      => array(),
		'em'     => array(),
		'strong' => array(),
		'br'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'a'      => array(
			'href'  => true,
			'title' => true,
		),
	);
	return wp_kses( hlmc_abstract_text( $abstract ), $allowed );
}

/**
 * Plain-text snippet for result lists.
 *
 * @param mixed $abstract Raw abstract.
 * @return string
 */
function hlmc_snippet( $abstract ) {
	return trim( wp_strip_all_tags( hlmc_abstract_text( $abstract ) ) );
}

/**
 * Format an ISO date from the API using the site's date format.
 *
 * @param string $iso ISO 8601 date.
 * @return string
 */
function hlmc_format_date( $iso ) {
	if ( empty( $iso ) ) {
		return '';
	}
	$ts = strtotime( $iso );
	return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '';
}

/**
 * Bring every Mayo content shape into the article layout the templates use.
 *
 * Most content types (Disease, Symptom, Article, Answer...) arrive with
 * `body.section[]`. "Topic" content (IDs like ABT-...) arrives instead with
 * `topicBody.concept[]` ({conceptName, section:{html}}) and a `description`.
 * Convert that into `body.section[]` and use the description as the abstract.
 *
 * @param mixed $article Decoded API response.
 * @return array Article, or an empty array when it has no displayable body.
 */
function hlmc_normalize_article( $article ) {
	if ( ! is_array( $article ) ) {
		return array();
	}
	if ( ! empty( $article['body']['section'] ) ) {
		return $article;
	}

	$concepts = $article['topicBody']['concept'] ?? array();
	if ( ! is_array( $concepts ) || ! $concepts ) {
		return array();
	}
	if ( isset( $concepts['conceptName'] ) || isset( $concepts['section'] ) ) {
		$concepts = array( $concepts ); // A single concept, not a list.
	}

	$description = trim( wp_strip_all_tags( (string) ( $article['description'] ?? '' ) ) );
	$sections    = array();
	foreach ( $concepts as $concept ) {
		if ( ! is_array( $concept ) ) {
			continue;
		}
		$name  = (string) ( $concept['conceptName'] ?? '' );
		$parts = $concept['section'] ?? array();
		if ( isset( $parts['html'] ) || isset( $parts['popupMedia'] ) ) {
			$parts = array( $parts );
		}
		$html  = '';
		$media = array();
		foreach ( (array) $parts as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			$chunk = $part['html'] ?? '';
			$html .= is_array( $chunk ) ? implode( '', array_map( 'strval', $chunk ) ) : (string) $chunk;
			if ( ! empty( $part['popupMedia'] ) && is_array( $part['popupMedia'] ) ) {
				$media = array_merge( $media, $part['popupMedia'] );
			}
		}
		// The "Description" concept repeats the description shown as the abstract.
		if ( 'description' === strtolower( $name ) && '' !== $description && trim( wp_strip_all_tags( $html ) ) === $description ) {
			continue;
		}
		if ( '' === trim( $html ) && ! $media ) {
			continue;
		}
		$sections[] = array(
			'sectionHead' => $name,
			'html'        => $html,
			'popupMedia'  => $media,
		);
	}
	if ( ! $sections ) {
		return array();
	}

	$article['body'] = array( 'section' => $sections );
	if ( empty( $article['abstract'] ) && '' !== $description ) {
		$article['abstract'] = $description;
	}
	return $article;
}

/**
 * URL of the proxied image.
 *
 * @param string $guid Image GUID.
 * @return string
 */
function hlmc_image_url( $guid ) {
	return add_query_arg( HLMC_Image_Proxy::QUERY_VAR, rawurlencode( $guid ), home_url( '/' ) );
}

/**
 * Locate and render a template. Themes can override any template by copying
 * it to `your-theme/health-library/{name}.php`.
 *
 * @param string $name Template name without extension.
 * @param array  $args Variables available to the template as $args.
 */
function hlmc_template( $name, array $args = array() ) {
	$name     = sanitize_file_name( $name );
	$template = locate_template( array( 'health-library/' . $name . '.php' ) );
	if ( ! $template ) {
		$template = HLMC_DIR . 'templates/' . $name . '.php';
	}
	/**
	 * Filter the template file used for a Health Library view.
	 *
	 * @param string $template Absolute path.
	 * @param string $name     Template name.
	 * @param array  $args     Template arguments.
	 */
	$template = apply_filters( 'hlmc_template', $template, $name, $args );
	if ( is_readable( $template ) ) {
		include $template;
	}
}
