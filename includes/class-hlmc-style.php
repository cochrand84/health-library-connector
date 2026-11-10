<?php
/**
 * How the library picks up the site's look.
 *
 * Fonts, text and link colors always come from the theme (the stylesheet
 * never sets them). On top of that, Settings → Health Library offers:
 *
 * Accent color (buttons, badges, the sidebar box)
 *   theme  – one of the active theme's palette colors, by slug. Output as
 *            var(--wp--preset--color--<slug>), so if the theme's color
 *            changes the library follows. Works with block themes and with
 *            classic themes that register an editor color palette.
 *   custom – a fixed hex color.
 *
 * Plugin styling
 *   full   – layout plus the plugin's cards, buttons and chips, painted
 *            with the accent color.
 *   layout – layout only (columns, spacing, sidebar). Buttons carry the
 *            theme's button classes and everything else is left to the
 *            theme or your own CSS.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Style {

	/**
	 * Colors in the active theme's palette (theme.json or editor-color-palette),
	 * plus any the site owner added in the Site Editor.
	 *
	 * @return array List of { slug, name, color }.
	 */
	public static function theme_palette() {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$out     = array();
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $c ) {
				$slug  = sanitize_key( (string) ( $c['slug'] ?? '' ) );
				$color = sanitize_hex_color( (string) ( $c['color'] ?? '' ) );
				if ( '' === $slug || ! $color || isset( $out[ $slug ] ) ) {
					continue;
				}
				$out[ $slug ] = array(
					'slug'  => $slug,
					'name'  => (string) ( $c['name'] ?? $slug ),
					'color' => $color,
				);
			}
		}
		return array_values( $out );
	}

	/**
	 * Default palette pick: a "primary"/"accent"-type color dark enough for
	 * white text, else the darkest color that isn't near-black.
	 *
	 * @param array $palette theme_palette().
	 * @return string Slug or ''.
	 */
	public static function suggest_slug( array $palette ) {
		$usable = array_filter(
			$palette,
			function ( $c ) {
				$l = self::luminance( $c['color'] );
				return $l > 0.02 && $l < 0.3; // Not black, dark enough for white text.
			}
		);
		foreach ( array( 'primary', 'accent', 'brand', 'accent-1', 'secondary', 'accent-2' ) as $want ) {
			foreach ( $usable as $c ) {
				if ( $want === $c['slug'] ) {
					return $c['slug'];
				}
			}
		}
		$usable = array_values( $usable );
		return $usable ? $usable[0]['slug'] : '';
	}

	/**
	 * The accent color in effect.
	 *
	 * @return array { css: string (value for --hlmc-accent), hex: string, slug: string, from_theme: bool }
	 */
	public static function accent() {
		$s      = HLMC_Settings::all();
		$custom = sanitize_hex_color( (string) $s['accent_color'] );
		$custom = $custom ? $custom : '#003865';

		if ( 'custom' !== $s['color_source'] ) {
			$palette = self::theme_palette();
			$slug    = sanitize_key( (string) $s['theme_color'] );
			$slug    = '' !== $slug ? $slug : self::suggest_slug( $palette );
			foreach ( $palette as $c ) {
				if ( $slug === $c['slug'] ) {
					return array(
						'css'        => 'var(--wp--preset--color--' . $c['slug'] . ', ' . $c['color'] . ')',
						'hex'        => $c['color'],
						'slug'       => $c['slug'],
						'from_theme' => true,
					);
				}
			}
		}

		return array(
			'css'        => $custom,
			'hex'        => $custom,
			'slug'       => '',
			'from_theme' => false,
		);
	}

	/**
	 * Inline CSS custom properties for the current settings.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$accent   = self::accent();
		$contrast = self::luminance( $accent['hex'] ) > 0.4 ? '#111' : '#fff';
		return '.hlmc{--hlmc-accent:' . $accent['css'] . ';--hlmc-accent-contrast:' . $contrast . ';}';
	}

	/** @return bool Whether the plugin's own look (cards, buttons) is on. */
	public static function full() {
		return 'layout' !== HLMC_Settings::get( 'style_level' );
	}

	/**
	 * Relative luminance (WCAG) of a #rrggbb color, 0–1.
	 *
	 * @param string $hex Color.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return 0.0;
		}
		$rgb = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$rgb[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	}
}
