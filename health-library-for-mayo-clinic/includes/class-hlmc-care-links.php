<?php
/**
 * Sidebar "care links": maps Mayo topics to the site's own pages.
 *
 * Each Mayo article carries meta.controlWords.category: a set of paths
 * through the Mayo Controlled Vocabulary, broad to specific, e.g.
 *
 *   Condition › Diagnosable condition (1092) › Pain NOS (1770) › Gas and gas pains (2926)
 *
 * A mapping row lists one or more mcvIds and the links to show for them:
 *
 *   [
 *     'ids'      => ['1452', '2926', '138500'],
 *     'topic'    => 'Gastroenterology',
 *     'provider' => [ ['label' => 'Gastroenterology', 'url' => 'https://…'] ],
 *     'services' => [ ['label' => 'Lab Services', 'url' => 'https://…'], … ],
 *   ]
 *
 * A row whose ids include "*" is the fallback when nothing else matches.
 *
 * Matching prefers the most specific code: generic codes like
 * "Diagnosable condition" sit near the top of almost every path, so a
 * deeper match ("Gas and gas pains") wins over a shallow one. Ties go to
 * the row nearest the top of the table.
 *
 * Rows are edited under Settings → Health Library → Sidebar links, and can
 * be imported/exported as CSV.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Care_Links {

	const OPTION = 'hlmc_care_links';

	/** CSV columns, in template order. More providerN_ / serviceN_ pairs are accepted on import. */
	const CSV_BASE = array( 'mcv_ids', 'topic' );

	/**
	 * Saved rows, or example rows in demo mode when nothing is saved.
	 *
	 * @return array[]
	 */
	public static function rows() {
		$saved = get_option( self::OPTION, null );
		$rows  = is_array( $saved ) ? $saved : ( HLMC_Settings::is_live() ? array() : self::example_rows() );
		/**
		 * Filter the care-link rows (e.g. to load them from a provider directory).
		 *
		 * @param array[] $rows Rows.
		 */
		$rows = apply_filters( 'hlmc_care_links', $rows );
		return array_values( array_filter( array_map( array( __CLASS__, 'clean_row' ), (array) $rows ) ) );
	}

	/**
	 * Whether rows have been saved (vs. showing examples).
	 *
	 * @return bool
	 */
	public static function has_saved_rows() {
		return is_array( get_option( self::OPTION, null ) );
	}

	/**
	 * @param array[] $rows Rows.
	 */
	public static function save( array $rows ) {
		$clean = array_values( array_filter( array_map( array( __CLASS__, 'clean_row' ), $rows ) ) );
		update_option( self::OPTION, $clean, false );
	}

	/** Remove saved rows (back to examples in demo mode, nothing in live mode). */
	public static function reset() {
		delete_option( self::OPTION );
	}

	/**
	 * Normalise one row; returns null if it has no ids or no links.
	 *
	 * @param mixed $row Raw row.
	 * @return array|null
	 */
	public static function clean_row( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$ids = self::parse_ids( $row['ids'] ?? '' );
		$out = array(
			'ids'      => $ids,
			'topic'    => sanitize_text_field( (string) ( $row['topic'] ?? '' ) ),
			'provider' => self::clean_links( $row['provider'] ?? array() ),
			'services' => self::clean_links( $row['services'] ?? array() ),
		);
		if ( empty( $out['ids'] ) || ( empty( $out['provider'] ) && empty( $out['services'] ) ) ) {
			return null;
		}
		return $out;
	}

	/**
	 * "1452; 2926, 138500" → ['1452','2926','138500']. "*" means fallback.
	 *
	 * @param string|array $ids Raw ids.
	 * @return string[]
	 */
	public static function parse_ids( $ids ) {
		if ( is_array( $ids ) ) {
			$ids = implode( ' ', array_map( 'strval', $ids ) );
		}
		preg_match_all( '/\*|\d+/', (string) $ids, $m );
		return array_values( array_unique( $m[0] ) );
	}

	/**
	 * @param mixed $links Raw links.
	 * @return array[] [ ['label' => string, 'url' => string], … ]
	 */
	public static function clean_links( $links ) {
		$out = array();
		foreach ( (array) $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$url   = esc_url_raw( trim( (string) ( $link['url'] ?? '' ) ), array( 'http', 'https' ) );
			$label = sanitize_text_field( (string) ( $link['label'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'label' => '' !== $label ? $label : $url,
				'url'   => $url,
			);
		}
		return $out;
	}

	/**
	 * Every category path in an article, broad → specific.
	 *
	 * Mayo nests the vocabulary as { title, mcvId, keyword: { title, mcvId, keyword: … } }.
	 *
	 * @param array $article Article response.
	 * @return array[] Each path is a list of [ 'id' => string, 'title' => string ].
	 */
	public static function category_paths( array $article ) {
		$cats  = $article['meta']['controlWords']['category'] ?? array();
		$cats  = isset( $cats['keyword'] ) || isset( $cats['mcvId'] ) ? array( $cats ) : (array) $cats;
		$paths = array();
		foreach ( $cats as $node ) {
			$path = array();
			while ( is_array( $node ) ) {
				if ( isset( $node['mcvId'] ) && is_scalar( $node['mcvId'] ) ) {
					$path[] = array(
						'id'    => (string) $node['mcvId'],
						'title' => (string) ( $node['title'] ?? $node['value'] ?? '' ),
					);
				}
				$node = $node['keyword'] ?? null;
			}
			if ( $path ) {
				$paths[] = $path;
			}
		}
		return $paths;
	}

	/**
	 * Deepest position (1 = broadest) at which each id appears.
	 *
	 * @param array[] $paths Category paths.
	 * @return array<string,int>
	 */
	public static function id_depths( array $paths ) {
		$depth = array();
		foreach ( $paths as $path ) {
			foreach ( $path as $i => $node ) {
				$depth[ $node['id'] ] = max( $depth[ $node['id'] ] ?? 0, $i + 1 );
			}
		}
		return $depth;
	}

	/**
	 * Pick the row for an article.
	 *
	 * @param array        $article Article response.
	 * @param array[]|null $rows    Rows (defaults to saved rows).
	 * @return array|null { row: array, index: int, id: string, fallback: bool } or null.
	 */
	public static function match( array $article, $rows = null ) {
		$rows   = null === $rows ? self::rows() : $rows;
		$depths = self::id_depths( self::category_paths( $article ) );

		$best = null;
		foreach ( $rows as $index => $row ) {
			foreach ( $row['ids'] as $id ) {
				if ( isset( $depths[ $id ] ) && ( null === $best || $depths[ $id ] > $best['depth'] ) ) {
					$best = array(
						'row'      => $row,
						'index'    => $index,
						'id'       => $id,
						'depth'    => $depths[ $id ],
						'fallback' => false,
					);
				}
			}
		}

		if ( null === $best ) {
			foreach ( $rows as $index => $row ) {
				if ( in_array( '*', $row['ids'], true ) ) {
					$best = array(
						'row'      => $row,
						'index'    => $index,
						'id'       => '*',
						'depth'    => 0,
						'fallback' => true,
					);
					break;
				}
			}
		}

		/**
		 * Filter the care-links match for an article.
		 *
		 * @param array|null $best    Match or null.
		 * @param array      $article Article response.
		 * @param array      $depths  mcvId => depth found in the article.
		 */
		return apply_filters( 'hlmc_care_links_match', $best, $article, $depths );
	}

	/**
	 * Example rows used in demo mode. Links are placeholders.
	 *
	 * @return array[]
	 */
	public static function example_rows() {
		$demo = home_url( '/about-this-demo/' );
		$row  = function ( $ids, $topic, $services ) use ( $demo ) {
			$links = array();
			foreach ( $services as $s ) {
				$links[] = array(
					'label' => $s,
					'url'   => $demo,
				);
			}
			return array(
				'ids'      => $ids,
				'topic'    => $topic,
				'provider' => array(
					array(
						/* translators: %s: specialty */
						'label' => sprintf( __( 'Find a %s provider', 'health-library-for-mayo-clinic' ), $topic ),
						'url'   => $demo,
					),
				),
				'services' => $links,
			);
		};
		return array(
			$row( array( '1452', '2926', '138500', '1137' ), 'Gastroenterology', array( 'Lab Services', 'Diagnostic Imaging' ) ),
			$row( array( '1104', '1898', '23493' ), 'OB/GYN', array( 'Women & Children', 'NICU' ) ),
			$row( array( '1770' ), 'Pain Management', array( 'Rehab Services', 'Diagnostic Imaging' ) ),
			$row( array( '1090', '1100', '127048' ), 'Orthopedics', array( 'Rehab Services', 'Diagnostic Imaging' ) ),
			$row( array( '1094', '1006' ), 'Primary Care', array( 'Nutrition Counseling', 'Lab Services' ) ),
			$row( array( '*' ), 'Primary Care', array( 'Lab Services', 'Diagnostic Imaging' ) ),
		);
	}

	/* ------------------------------------------------------------------
	 * CSV
	 * ------------------------------------------------------------------ */

	/**
	 * Header row for export/template, wide enough for the given rows.
	 *
	 * @param array[] $rows Rows.
	 * @return string[]
	 */
	public static function csv_header( array $rows = array() ) {
		$np = 1;
		$ns = 3;
		foreach ( $rows as $r ) {
			$np = max( $np, count( $r['provider'] ) );
			$ns = max( $ns, count( $r['services'] ) );
		}
		$h = self::CSV_BASE;
		for ( $i = 1; $i <= $np; $i++ ) {
			$p   = 1 === $i ? 'provider' : 'provider' . $i;
			$h[] = $p . '_label';
			$h[] = $p . '_url';
		}
		for ( $i = 1; $i <= $ns; $i++ ) {
			$h[] = 'service' . $i . '_label';
			$h[] = 'service' . $i . '_url';
		}
		return $h;
	}

	/**
	 * Rows → CSV text (UTF-8 with BOM so Excel opens it correctly).
	 *
	 * @param array[] $rows Rows.
	 * @return string
	 */
	public static function to_csv( array $rows ) {
		$header = self::csv_header( $rows );
		// In-memory stream (php://temp), not the filesystem: fputcsv() handles CSV quoting.
		$fh     = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $fh, $header, ',', '"', '' );
		foreach ( $rows as $r ) {
			$line = array( implode( '; ', $r['ids'] ), $r['topic'] );
			foreach ( $header as $col ) {
				if ( preg_match( '/^(provider|service)(\d*)_(label|url)$/', $col, $m ) ) {
					$list   = 'provider' === $m[1] ? $r['provider'] : $r['services'];
					$n      = '' === $m[2] ? 1 : (int) $m[2];
					$line[] = $list[ $n - 1 ][ $m[3] ] ?? '';
				}
			}
			fputcsv( $fh, $line, ',', '"', '' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * CSV text → rows, with per-line problems reported.
	 *
	 * Accepts comma- or semicolon-delimited files (Excel uses ; in some
	 * locales), a UTF-8 BOM, and any number of providerN_/serviceN_ pairs.
	 *
	 * @param string $csv CSV text.
	 * @return array { rows: array[], errors: string[] }
	 */
	public static function from_csv( $csv ) {
		$csv    = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$lines  = preg_split( '/\r\n|\r|\n/', $csv );
		$first  = (string) ( $lines[0] ?? '' );
		$delim  = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
		// In-memory stream (php://temp), not the filesystem: fgetcsv() handles quoted multi-line cells.
		$fh     = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $fh, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $fh );

		$rows   = array();
		$errors = array();
		$header = fgetcsv( $fh, 0, $delim, '"', '' );
		$header = array_map(
			function ( $h ) {
				return strtolower( trim( preg_replace( '/\s+/', '_', (string) $h ) ) );
			},
			(array) $header
		);

		if ( ! in_array( 'mcv_ids', $header, true ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array(
				'rows'   => array(),
				'errors' => array( __( 'The first row must be the header row from the template (it needs an mcv_ids column).', 'health-library-for-mayo-clinic' ) ),
			);
		}

		$line_no = 1;
		while ( false !== ( $cells = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			$line_no++;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			$raw = array(
				'ids'      => '',
				'topic'    => '',
				'provider' => array(),
				'services' => array(),
			);
			foreach ( $header as $i => $col ) {
				$val = trim( (string) ( $cells[ $i ] ?? '' ) );
				if ( 'mcv_ids' === $col ) {
					$raw['ids'] = $val;
				} elseif ( 'topic' === $col ) {
					$raw['topic'] = $val;
				} elseif ( preg_match( '/^(provider|service)(\d*)_(label|url)$/', $col, $m ) ) {
					$list = 'provider' === $m[1] ? 'provider' : 'services';
					$n    = '' === $m[2] ? 1 : (int) $m[2];
					$raw[ $list ][ $n ][ $m[3] ] = $val;
				}
			}
			ksort( $raw['provider'] );
			ksort( $raw['services'] );

			foreach ( array( 'provider', 'services' ) as $list ) {
				foreach ( $raw[ $list ] as $link ) {
					$url = $link['url'] ?? '';
					if ( '' !== $url && '' === esc_url_raw( $url, array( 'http', 'https' ) ) ) {
						/* translators: 1: line number, 2: URL */
						$errors[] = sprintf( __( 'Line %1$d: “%2$s” is not a valid http(s) URL and was skipped.', 'health-library-for-mayo-clinic' ), $line_no, $url );
					}
				}
			}

			$row = self::clean_row( $raw );
			if ( null === $row ) {
				/* translators: %d: line number */
				$errors[] = sprintf( __( 'Line %d skipped: it needs at least one Mayo category ID and one link with a URL.', 'health-library-for-mayo-clinic' ), $line_no );
				continue;
			}
			$rows[] = $row;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'rows'   => $rows,
			'errors' => $errors,
		);
	}

	/**
	 * Merge imported rows into existing ones: a row with exactly the same
	 * set of ids replaces the existing row in place; anything else is added.
	 *
	 * @param array[] $existing Existing rows.
	 * @param array[] $incoming Imported rows.
	 * @return array[]
	 */
	public static function merge( array $existing, array $incoming ) {
		$key = function ( $row ) {
			$ids = $row['ids'];
			sort( $ids );
			return implode( ',', $ids );
		};
		$index = array();
		foreach ( $existing as $i => $row ) {
			$index[ $key( $row ) ] = $i;
		}
		foreach ( $incoming as $row ) {
			$k = $key( $row );
			if ( isset( $index[ $k ] ) ) {
				$existing[ $index[ $k ] ] = $row;
			} else {
				$existing[]    = $row;
				$index[ $k ] = count( $existing ) - 1;
			}
		}
		return array_values( $existing );
	}
}
