# Health Library for Mayo Clinic — WordPress plugin

Display the **Mayo Clinic Health Information Library** on any WordPress site,
inside your own theme, using your organization's licensed Mayo Clinic
Realtime (Content Delivery) API credentials. Visitors search and read articles
without leaving your site.

> **You need your own content license and API key from Mayo Clinic.**
> This repository contains no Mayo Clinic content. It ships with a demo mode
> using original placeholder articles so you can try it first.
>
> This is an independent project. It is not affiliated with, endorsed by, or
> sponsored by Mayo Clinic. "Mayo Clinic" is a trademark of Mayo Foundation
> for Medical Education and Research.

**Live demo:** https://mayoplugin.davidcochran.us (demo mode)

## Features

- `[health_library]` — landing page with featured topics, search with paging, and article pages, all from one shortcode on any page
- `[health_library_search]` — a search box for your header, sidebar or home page
- Server-side requests only; the API key never reaches the browser and can live in `wp-config.php`
- Article images proxied and cached server-side (the image endpoint needs authentication)
- License-friendly by default: cache capped at **48 hours**, canonical tag to Mayo's `originalUrl` (also passed to Yoast / Rank Math), "Last reviewed" date, © MFMER notice and Terms of Use link
- Optional sidebar: a call to action, or **specialists matched to the article's topic** using Mayo's `mcvId` category codes
- Theme-independent: inherits your theme's typography; every view can be overridden from your theme
- Demo mode, WP-CLI commands, clean uninstall

## Install

1. Download `health-library-for-mayo-clinic.zip` from [Releases](../../releases), or copy the `health-library-for-mayo-clinic/` folder into `wp-content/plugins/`.
2. Activate it, then go to **Settings → Health Library** and click **Create library page**.
3. You'll see demo content. To go live, choose **Live**, add your credentials, save, and click **Test API connection**.

Prefer keeping secrets out of the database:

```php
// wp-config.php
define( 'HLMC_API_KEY', 'your-api-key' );
define( 'HLMC_TRCKR',   'your-trckr-value' );
```

## URLs

| URL | View |
|-----|------|
| `/health-library/` | Landing page with featured topics |
| `/health-library/?mayo-q=diabetes` | Search results (`&mayo-page=2` for paging) |
| `/health-library/?mayo-id=CON-20372695` | Article |

## Shortcode options

```
[health_library sidebar="none|cta|providers" featured="12"]
[health_library_search placeholder="Search health topics…"]
```

## Specialist sidebar

Mayo article responses include a `meta.controlWords` tree of category nodes,
each with an `mcvId`. Map the IDs you care about to a specialty, and each
specialty to providers, in **Settings → Health Library → Specialty mapping**:

```json
{
  "fallback": "Internal Medicine",
  "specialties": { "1452": "Gastroenterology", "1104": "OB/GYN" },
  "providers": {
    "Gastroenterology": [
      { "name": "Dr. Jane Doe", "title": "Gastroenterologist",
        "phone": "(555) 555-0100", "url": "https://example.org/doctors/jane-doe" }
    ]
  }
}
```

Or supply it from code with the `hlmc_provider_map` filter (for example, from
your provider directory).

## Customizing

Copy any file from `health-library-for-mayo-clinic/templates/` to
`your-theme/health-library/` and edit it. Colors use CSS custom properties
(`--hlmc-accent`, `--hlmc-border`, …) scoped to `.hlmc`.

Filters: `hlmc_source`, `hlmc_template`, `hlmc_allowed_html`,
`hlmc_provider_map`, `hlmc_provider_match`, `hlmc_is_library_page`.
Action: `hlmc_cache_cleared`.

## WP-CLI

```bash
wp health-library status
wp health-library test
wp health-library cache clear
```

## Your license obligations

The plugin builds in the requirements from Mayo's Developer Reference and
Syndication Guide that we know of, but **your license agreement is what
counts**. Check the required attribution wording and override
`templates/attribution.php` if yours differs.

## Repository layout

| Path | What it is |
|------|------------|
| `health-library-for-mayo-clinic/` | The plugin |
| `prototype/` | The original standalone PHP prototype the plugin grew out of |

The early commits are the prototype's development history, reconstructed
from the working sessions it was built in (timestamps are approximate).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
