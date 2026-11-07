<?php
/**
 * Topic buttons for [health_library_topics].
 *
 * Each topic is a label plus a target. The target is either a Mayo content
 * ID (e.g. CON-20373087), which links straight to that article, or search
 * terms, which link to a search on the library page.
 *
 * Stored in the `hlmc_topics` option as a list of { label, target }. Edited
 * in Settings → Health Library → Topic buttons as one topic per line:
 *
 *   Heart attack | heart attack
 *   Colonoscopy | CON-20373087
 *   Asthma                          (label doubles as the search terms)
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Topics {

	const OPTION = 'hlmc_topics';
	const MAX    = 50;

	/**
	 * Starter list, used until the site saves its own. Plain search terms,
	 * so it works in demo and live mode alike.
	 *
	 * @return array
	 */
	public static function defaults() {
		$out = array();
		foreach ( array(
			__( 'Heart disease', 'health-library-for-mayo-clinic' )       => 'heart disease',
			__( 'Stroke', 'health-library-for-mayo-clinic' )              => 'stroke',
			__( 'Type 2 diabetes', 'health-library-for-mayo-clinic' )     => 'type 2 diabetes',
			__( 'High blood pressure', 'health-library-for-mayo-clinic' ) => 'high blood pressure',
			__( 'Colonoscopy', 'health-library-for-mayo-clinic' )         => 'colonoscopy',
			__( 'Asthma', 'health-library-for-mayo-clinic' )              => 'asthma',
		) as $label => $target ) {
			$out[] = array(
				'label'  => $label,
				'target' => $target,
			);
		}
		return $out;
	}

	/** @return bool Whether the site has saved its own list (even an empty one). */
	public static function has_saved() {
		return is_array( get_option( self::OPTION, null ) );
	}

	/**
	 * Saved topics, or the starter list.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, null );
		return is_array( $saved ) ? self::clean_list( $saved ) : self::defaults();
	}

	/**
	 * Is this target a Mayo content ID rather than search terms?
	 *
	 * IDs look like CON-20373087, ART-20047744, DEMO-10001: letters, a dash,
	 * digits. Search terms almost never do.
	 *
	 * @param string $target Target.
	 * @return bool
	 */
	public static function is_content_id( $target ) {
		return (bool) preg_match( '/^[A-Za-z]{2,6}-\d{3,}$/', trim( (string) $target ) );
	}

	/**
	 * Parse the textarea format (one "Label | target" per line).
	 *
	 * @param string $text Raw text.
	 * @return array List of { label, target }.
	 */
	public static function parse( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
			$label  = $parts[0];
			$target = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $label;
			if ( '' === $label ) {
				$label = $target;
			}
			$out[] = array(
				'label'  => $label,
				'target' => $target,
			);
		}
		return self::clean_list( $out );
	}

	/**
	 * Inverse of parse(), for the textarea.
	 *
	 * @param array $topics List of { label, target }.
	 * @return string
	 */
	public static function to_text( array $topics ) {
		$lines = array();
		foreach ( $topics as $t ) {
			$lines[] = $t['label'] === $t['target'] ? $t['label'] : $t['label'] . ' | ' . $t['target'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * Sanitize a list.
	 *
	 * @param array $list Raw list.
	 * @return array
	 */
	public static function clean_list( $list ) {
		$out = array();
		foreach ( (array) $list as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$label  = mb_substr( sanitize_text_field( (string) ( $t['label'] ?? '' ) ), 0, 80 );
			$target = mb_substr( sanitize_text_field( (string) ( $t['target'] ?? '' ) ), 0, 200 );
			if ( '' === $label || '' === $target ) {
				continue;
			}
			if ( self::is_content_id( $target ) ) {
				$target = strtoupper( hlmc_sanitize_content_id( $target ) );
				if ( '' === $target ) {
					continue;
				}
			}
			$out[] = array(
				'label'  => $label,
				'target' => $target,
			);
			if ( count( $out ) >= self::MAX ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * register_setting() sanitize callback: the form posts the textarea text.
	 *
	 * @param mixed $input Posted value.
	 * @return array
	 */
	public static function sanitize_option( $input ) {
		if ( is_array( $input ) ) {
			return self::clean_list( $input );
		}
		return self::parse( wp_unslash( (string) $input ) );
	}

	/**
	 * Link for one topic.
	 *
	 * @param array  $topic { label, target }.
	 * @param string $base  Library page URL.
	 * @return string
	 */
	public static function url( array $topic, $base ) {
		return self::is_content_id( $topic['target'] )
			? HLMC_Frontend::article_url( $topic['target'], $base )
			: HLMC_Frontend::search_url( $topic['target'], 1, $base );
	}
}
