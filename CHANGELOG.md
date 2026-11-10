# Changelog

All notable changes to Health Library Connector. Versions follow
[Semantic Versioning](https://semver.org/); dates are the commit dates.

## 0.4.0 — 2026-11-10

### Added
- **Theme colors.** The accent color (buttons, badges, sidebar box) can be
  linked to one of the active theme's palette colors and follows the theme
  when it changes. A sensible palette color is picked by default; text on the
  accent switches between white and dark for contrast.
- **Layout-only mode.** Turns off the plugin's cards, buttons and chips and
  leaves all styling to the theme or custom CSS.

### Changed
- Stylesheet split into `health-library-layout.css` (always loaded) and
  `health-library.css` (the look, optional).
- Buttons carry the theme's `wp-element-button` class.
- Borders and muted text are derived from the theme's text color, so they
  work on light and dark themes.

## 0.3.0 — 2026-11-10

Submitted to the WordPress.org plugin directory.

### Added
- `[health_library_topics]` shortcode: a row of buttons for common topics,
  each opening a search or a specific article. Edited under
  Settings → Health Library → Topic buttons, or per page with `topics="…"`.
- **Shortcodes & help** tab documenting every shortcode, link parameter,
  customization option and WP-CLI command.
- 22 original demo articles with illustrations for demo mode.
- "External services" section in the readme describing what is sent to the
  Mayo Clinic API and when.

### Changed
- Renamed from "Health Library for Mayo Clinic" to **Health Library
  Connector** (slug and text domain `health-library-connector`). Settings,
  shortcodes and option names are unchanged.
- Settings-screen messages are kept in a short-lived per-user transient
  instead of the URL.
- Translations are loaded by WordPress.org; the plugin no longer calls
  `load_plugin_textdomain()`.
- Passes Plugin Check with no errors or warnings.

### Fixed
- "Topic" content (IDs starting with `ABT-`) returned by search now opens
  instead of showing an error.

## 0.2.0 — 2026-10-31

### Added
- **Sidebar links.** Map Mayo topic categories to your own provider and
  service pages, edited in a table or imported/exported as CSV. The most
  specific matching category wins; a `*` row is the default.
- Admin-only category inspector under each article showing its Mayo
  category codes and which sidebar row matched.
- Rate limiting of uncached API calls per visitor and per site, with images
  in their own budget.

### Changed
- Tested with WordPress 7.1 and PHP 8.4.
- Relicensed under GPL-3.0-or-later.

## 0.1.0 — 2026-10-30

### Added
- First release as a WordPress plugin, converted from the standalone PHP
  prototype (kept in `prototype/`).
- `[health_library]` (landing, search and article views) and
  `[health_library_search]` shortcodes.
- Server-side API client with caching capped at 48 hours and an image proxy,
  so the API key never reaches the browser.
- Canonical URL, update date, copyright and Terms of Use attribution on
  articles.
- Demo mode with bundled sample content, WP-CLI commands and clean
  uninstall.
