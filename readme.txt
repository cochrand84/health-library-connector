=== Health Library Connector ===
Contributors: cochrand84
Tags: health, medical, mayo clinic, health library, api
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Show Mayo Clinic Health Information Library content inside any WordPress theme using your licensed Mayo Clinic Realtime API credentials.

== Description ==

Health Library Connector lets organizations that license the Mayo Clinic Health Information Library display that content on their own WordPress site, styled by their own theme, so visitors never leave the site.

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
* Matches your theme: fonts, text and links come from the theme, and the accent color can be linked to one of the theme's own palette colors (or switch to layout-only and style everything yourself)
* Every view can be overridden from your theme
* Demo mode with bundled sample articles, so you can try it without credentials
* WP-CLI: `wp health-library status|test|cache clear`

**You need your own content license and API credentials from Mayo Clinic.** This plugin does not include any Mayo Clinic content.

This plugin is an independent project. It is not affiliated with, endorsed by, or sponsored by Mayo Clinic. "Mayo Clinic" is a trademark of Mayo Foundation for Medical Education and Research.

== Installation ==

1. Upload the `health-library-connector` folder to `/wp-content/plugins/` and activate it.
2. Go to **Settings → Health Library** and click **Create library page** (or add `[health_library]` to any page).
3. The plugin starts in demo mode. To show real content, choose **Live**, enter your API key and trckr value (or define `HLMC_API_KEY` and `HLMC_TRCKR` in `wp-config.php`), save, and click **Test API connection**.

== External services ==

This plugin connects to the **Mayo Clinic Content Delivery API** (`https://cdapi.mayoclinic.org/`), operated by Mayo Clinic, to display Health Information Library content. Using it requires a content license and API credentials from Mayo Clinic.

* **When:** only in Live mode. Demo mode uses the sample content bundled with the plugin and makes no external requests. In Live mode, your server contacts the API when a visitor searches, opens an article, or loads an article image that isn't already cached, and when an administrator clicks **Test API connection**.
* **What is sent:** your API key and trckr value (as request headers), the search terms, the results page number, the requested content or image ID, and the language code. Requests are made by your server; visitors' IP addresses, cookies and other personal data are not sent.
* **Mayo Clinic terms of use:** https://www.mayoclinic.org/about-this-site/terms-conditions-use-policy
* **Mayo Clinic privacy policy:** https://www.mayoclinic.org/about-this-site/privacy-policy

Your use of the content is also governed by your own license agreement with Mayo Clinic.

== Frequently Asked Questions ==

= Where do I get an API key? =

From Mayo Clinic Global Business Solutions as part of a Health Information Library content license.

= Will it match my theme? =

Yes. Fonts, text and link colors always come from your theme. Under Settings → Health Library you can link the accent color (buttons, badges, the sidebar box) to one of your theme's palette colors, so it follows the theme if the theme changes, or set a custom color. Choose "Layout only" to turn off the plugin's cards and buttons entirely and style everything in your theme.

= Can I change the layout? =

Copy any file from the plugin's `templates/` folder into `your-theme/health-library/` and edit it there.

= How do I add topic buttons to my home page? =

Edit the list under Settings → Health Library → Topic buttons, then add `[health_library_topics]` where you want them, usually next to `[health_library_search]`. The Shortcodes & help tab lists every option.

= How do I map topics to my own pages? =

Settings → Health Library → Sidebar links. Download the CSV template, fill it in, and import it, or edit rows directly. While logged in as an administrator, each article shows its Mayo category codes and which row matched.

= Does it work with Yoast SEO or Rank Math? =

Yes. On article views the canonical URL and title are passed to those plugins.

== Changelog ==

= 0.4.1 =
* Packaging: the plugin now sits at the repository root, so GitHub downloads install directly.

= 0.4.0 =
* The accent color can now come from your theme's color palette and follows the theme when it changes. New "Layout only" option leaves all styling to the theme.
* Buttons carry the theme's button class (`wp-element-button`).

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
