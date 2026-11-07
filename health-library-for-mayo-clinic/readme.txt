=== Health Library for Mayo Clinic ===
Contributors: davidcochran
Tags: health, medical, mayo clinic, health library, api
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Show Mayo Clinic Health Information Library content inside any WordPress theme using your licensed Mayo Clinic Realtime API credentials.

== Description ==

Health Library for Mayo Clinic lets organizations that license the Mayo Clinic Health Information Library display that content on their own WordPress site, styled by their own theme, so visitors never leave the site.

* Search, article and landing views from a single `[health_library]` shortcode
* `[health_library_search]` search box for headers, sidebars or the home page
* `[health_library_topics]` buttons for common topics, each opening a search or a specific article
* Server-side API calls — your API key never reaches the browser
* Article images proxied and cached server-side
* Caching capped at 48 hours, as Mayo Clinic's syndication terms require
* Limits how many uncached API calls a visitor can trigger, protecting your API quota
* Canonical tag pointing at Mayo's original URL, update date, copyright and Terms of Use link
* Optional sidebar: links to your own provider and service pages, matched to each article's topic — edit in a table or import from a spreadsheet (CSV)
* Category inspector for administrators to see each article's Mayo topic codes
* Works with any theme; every view can be overridden from your theme
* Demo mode with bundled sample articles, so you can try it without credentials
* WP-CLI: `wp health-library status|test|cache clear`

**You need your own content license and API credentials from Mayo Clinic.** This plugin does not include any Mayo Clinic content.

This plugin is an independent project. It is not affiliated with, endorsed by, or sponsored by Mayo Clinic. "Mayo Clinic" is a trademark of Mayo Foundation for Medical Education and Research.

== Installation ==

1. Upload the `health-library-for-mayo-clinic` folder to `/wp-content/plugins/` and activate it.
2. Go to **Settings → Health Library** and click **Create library page** (or add `[health_library]` to any page).
3. The plugin starts in demo mode. To show real content, choose **Live**, enter your API key and trckr value (or define `HLMC_API_KEY` and `HLMC_TRCKR` in `wp-config.php`), save, and click **Test API connection**.

== Frequently Asked Questions ==

= Where do I get an API key? =

From Mayo Clinic Global Business Solutions as part of a Health Information Library content license.

= Can I change the layout? =

Copy any file from the plugin's `templates/` folder into `your-theme/health-library/` and edit it there.

= How do I add topic buttons to my home page? =

Edit the list under Settings → Health Library → Topic buttons, then add `[health_library_topics]` where you want them, usually next to `[health_library_search]`. The Shortcodes & help tab lists every option.

= How do I map topics to my own pages? =

Settings → Health Library → Sidebar links. Download the CSV template, fill it in, and import it, or edit rows directly. While logged in as an administrator, each article shows its Mayo category codes and which row matched.

= Does it work with Yoast SEO or Rank Math? =

Yes. On article views the canonical URL and title are passed to those plugins.

== Changelog ==

= 0.3.0 =
* New `[health_library_topics]` shortcode: buttons for common topics, edited under Settings → Health Library → Topic buttons.
* New Shortcodes & help tab documenting every shortcode, link parameter and customization option.

= 0.2.1 =
* Fix: "Topic" content (IDs starting with ABT-) that appears in search results now opens instead of showing an error.

= 0.2.0 =
* Sidebar links: map Mayo topics to your own provider and service pages, with a table editor and CSV import/export.
* Most-specific topic matching, default row, and an admin-only category inspector on articles.

= 0.1.0 =
* First release, converted from the standalone PHP prototype.
