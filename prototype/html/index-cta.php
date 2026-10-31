<?php
/**
 * Mayo Clinic Health Library - CTA Sidebar Variant
 * Same as index.php but with a Find a Doctor sidebar on search/article pages.
 *
 * This file is a self-contained proof-of-concept for the Mayo Clinic
 * GBS Realtime API integration. Once logic is validated here, it will
 * be transplanted into a WordPress child theme as custom page templates.
 *
 * WordPress equivalents are noted in comments throughout.
 *
 * Modes (driven by query string):
 *   ?q=diabetes           -> Search results page
 *   ?mayo-id=CON-20372638 -> Single article display page
 *   (no params)           -> Search form landing page
 */

// ============================================================
// CONFIGURATION
// In WordPress, these move to wp-config.php or a config file,
// and are accessed via get_option() or defined constants.
// ============================================================

// Credentials are in config.php — edit that file, not this one
require_once __DIR__ . '/config.php';

define('MAYO_API_BASE',  'https://cdapi.mayoclinic.org/api/v2');
define('MAYO_CACHE_DIR', __DIR__ . '/cache');  // local file cache (WP will use transients instead)
define('MAYO_CACHE_TTL', 172800);              // 48 hours MAX per Mayo license requirement
define('SITE_NAME',      'Mayo Clinic Health Library');
define('ARTICLES_PATH',  '/index-cta.php');   // CTA variant — links back to itself

// ============================================================
// IMAGE PROXY — must run before functions are defined and before
// any HTML is output. ?mayo-image=GUID streams the binary from
// Mayo's API server-side so the API key never hits the browser.
// In WordPress this becomes a REST endpoint or rewrite rule.
// ============================================================

if (!empty($_GET['mayo-image'])) {
    // Only image GUIDs — otherwise this would proxy any licensed content ID
    $imageId = is_string($_GET['mayo-image']) ? trim($_GET['mayo-image']) : '';
    if (!preg_match('/^GUID-[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', $imageId)) {
        http_response_code(400);
        exit('Invalid image ID');
    }

    $cacheFile = MAYO_CACHE_DIR . '/' . md5('img_' . $imageId);
    $cacheMeta = $cacheFile . '.meta';

    // Serve from cache if still fresh
    if (file_exists($cacheFile) && file_exists($cacheMeta)
        && (time() - filemtime($cacheFile)) < MAYO_CACHE_TTL) {
        $meta = json_decode(file_get_contents($cacheMeta), true);
        header('Content-Type: '   . ($meta['type'] ?? 'image/jpeg'));
        header('Content-Length: ' . filesize($cacheFile));
        header('Cache-Control: max-age=86400');
        readfile($cacheFile);
        exit;
    }

    // Fetch from Mayo — no Accept header so Mayo returns the raw binary
    $url     = MAYO_API_BASE . '/content/' . $imageId . '/en';
    $context = stream_context_create([
        'http' => [
            'method'           => 'GET',
            'header'           => 'apikey: ' . MAYO_API_KEY . "\r\n" .
                                  'trckr: '  . MAYO_TRCKR   . "\r\n",
            'timeout'          => 15,
            'ignore_errors'    => true,   // don't suppress — we want $http_response_header
        ],
    ]);

    $imageData = file_get_contents($url, false, $context);

    // Pull HTTP status from response headers
    $httpStatus = 200;
    $contentType = 'image/jpeg';
    if (!empty($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {
                $httpStatus = (int) $m[1];
            }
            if (stripos($h, 'Content-Type:') === 0) {
                $contentType = trim(substr($h, 13));
            }
        }
    }

    if ($imageData === false || $httpStatus >= 400 || stripos($contentType, 'image/') !== 0) {
        http_response_code(404);
        exit('Image not available (Mayo API returned ' . $httpStatus . ')');
    }

    // Cache it
    if (!is_dir(MAYO_CACHE_DIR)) mkdir(MAYO_CACHE_DIR, 0755, true);
    file_put_contents($cacheFile, $imageData);
    file_put_contents($cacheMeta, json_encode(['type' => $contentType]));

    header('Content-Type: '   . $contentType);
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: max-age=86400');
    echo $imageData;
    exit;
}


// ============================================================
// CACHE HELPERS
// In WordPress these become:
//   get_transient("mayo_$cacheKey")
//   set_transient("mayo_$cacheKey", $data, MAYO_CACHE_TTL)
//   delete_transient("mayo_$cacheKey")
// ============================================================

function cache_get(string $key): mixed {
    $file = MAYO_CACHE_DIR . '/' . md5($key) . '.json';
    if (!file_exists($file)) return null;
    if ((time() - filemtime($file)) > MAYO_CACHE_TTL) {
        unlink($file);
        return null;
    }
    $raw = file_get_contents($file);
    return $raw !== false ? json_decode($raw, true) : null;
}

function cache_set(string $key, mixed $data): void {
    if (!is_dir(MAYO_CACHE_DIR)) {
        mkdir(MAYO_CACHE_DIR, 0755, true);
    }
    file_put_contents(
        MAYO_CACHE_DIR . '/' . md5($key) . '.json',
        json_encode($data)
    );
}


// ============================================================
// API HELPERS
// In WordPress, file_get_contents + stream_context_create
// becomes wp_remote_get() and wp_remote_retrieve_body().
// ============================================================

/**
 * Make a GET request to the Mayo Realtime API.
 *
 * WordPress equivalent:
 *   $response = wp_remote_get($url, ['headers' => ['apikey' => MAYO_API_KEY]]);
 *   $body     = wp_remote_retrieve_body($response);
 *   $data     = json_decode($body, true);
 *
 * @param  string $endpoint  Path after the base URL, e.g. '/content/CON-xxx/en'
 * @param  array  $params    Query string parameters
 * @return array|null        Decoded JSON response, or null on failure
 */
function mayo_api_get(string $endpoint, array $params = []): ?array {
    $url = MAYO_API_BASE . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Check cache first
    $cacheKey = $url;
    $cached = cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Build the HTTP request
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  =>
                'apikey: ' . MAYO_API_KEY . "\r\n" .
                'trckr: '  . MAYO_TRCKR   . "\r\n" .  // Recommended by Mayo for tracking
                'Accept: application/json' . "\r\n",
            'timeout' => 10,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);

    if ($data !== null) {
        cache_set($cacheKey, $data);
    }

    return $data;
}

/**
 * Search Mayo content by keyword.
 *
 * Endpoint: GET /search
 * Params:   q (required), top, lang
 *
 * @param  string $query  Search term
 * @param  int    $top    Max results to return (default 10)
 * @return array|null
 */
function mayo_search(string $query, int $top = 10, int $skip = 0): ?array {
    // Search endpoint is GET {Base URL}content (not /search)
    // 'skip' enables pagination: skip=1000&top=1000 returns records 1001-2000
    return mayo_api_get('/content', [
        'q'    => $query,
        'top'  => $top,
        'skip' => $skip,
        'lang' => 'en',
    ]);
}

/**
 * Fetch a single article by Mayo content ID.
 *
 * Endpoint: GET {Base URL}content/{id}/{lang}
 *
 * Response structure (as observed):
 *   {
 *     "meta": {
 *       "contentType": "Disease"|"Article"|"Answer"|etc,
 *       "docId": "CON-20372638",
 *       "originalUrl": "https://www.mayoclinic.org/...",  // use for canonical tag
 *       "updateDate": "2025-09-20T00:00:00",              // MUST be displayed per license
 *       "publicationDate": "...",
 *       "attribution": { "url": "...", "value": "..." },
 *       "termsOfUse": { "docId": "ART-20045381" },        // link required per license
 *       "metaKeywords": { "metaKeyword": [...] },
 *       "controlWords": { ... }
 *     },
 *     "body": {
 *       "section": [
 *         { "sectionHead": "Section title" },
 *         { "sectionHead": "...", "html": ["<p>...</p>"] }
 *       ]
 *     },
 *     "title": "Article title",
 *     "abstract": "Brief description",
 *     "encoding": "UTF-8",
 *     "language": "en"
 *   }
 *
 * Note: Structure varies by contentType (Disease, Article, Answer, Recipe, etc.)
 * Note: Images referenced in body HTML have GUID IDs — fetch separately if needed:
 *       GET {Base URL}content/{GUID-id}/en
 *
 * @param  string $mayoId  e.g. 'CON-20372638'
 * @return array|null
 */
function mayo_get_article(string $mayoId): ?array {
    // Sanitize — Mayo IDs contain alphanumerics, hyphens, and for GUIDs, also hyphens only
    $mayoId = preg_replace('/[^A-Za-z0-9\-]/', '', $mayoId);
    if (empty($mayoId)) return null;

    return mayo_api_get('/content/' . $mayoId . '/en');
}


// ============================================================
// ROUTING
// In WordPress this logic lives in your page template file.
// WordPress handles URL routing via the permalink/query vars
// system; this block replaces that for the standalone prototype.
// ============================================================

$mode    = 'home';      // 'home' | 'search' | 'article'
$mayoId  = '';
$query   = '';
$error   = '';

if (!empty($_GET['mayo-id'])) {
    $mode   = 'article';
    $mayoId = trim($_GET['mayo-id']);
} elseif (!empty($_GET['q'])) {
    $mode  = 'search';
    $query = trim($_GET['q']);
}


// ============================================================
// DATA FETCHING
// In WordPress this also lives in the page template, above
// the call to get_header().
// ============================================================

$article     = null;
$searchData  = null;

if ($mode === 'article') {
    $article = mayo_get_article($mayoId);
    if ($article === null) {
        $error = 'The requested article could not be loaded. Please try again or use the search below.';
        $mode  = 'home';
    }
}

if ($mode === 'search') {
    $searchData = mayo_search($query);
    if ($searchData === null) {
        $error = 'Search is temporarily unavailable. Please try again shortly.';
        $mode  = 'home';
    }
}

// Show sidebar on article and search pages only
$showSidebar = in_array($mode, ['article', 'search']);

// Specialty mapping and doctor data loaded from doctors.php
require_once __DIR__ . '/doctors.php';

// Extract matched specialty from article controlWords
$matchedSpecialty = null;
$matchedDoctors   = [];

if ($mode === 'article' && $article) {
    $categories = $article['meta']['controlWords']['category'] ?? [];

    // Walk the category tree to find mcvId values at any depth
    function extractMcvIds(array $node): array {
        $ids = [];
        if (isset($node['mcvId'])) $ids[] = (string) $node['mcvId'];
        foreach ($node as $val) {
            if (is_array($val)) {
                $ids = array_merge($ids, extractMcvIds($val));
            }
        }
        return $ids;
    }

    $allIds = [];
    foreach ($categories as $cat) {
        $allIds = array_merge($allIds, extractMcvIds($cat));
    }
    $allIds = array_unique($allIds);

    global $specialtyMap, $doctorsBySpecialty;
    foreach ($allIds as $id) {
        if (isset($specialtyMap[$id])) {
            $specialty = $specialtyMap[$id];
            if (isset($doctorsBySpecialty[$specialty])) {
                $matchedSpecialty = $specialty;
                $matchedDoctors   = $doctorsBySpecialty[$specialty];
                break;
            }
        }
    }

    // Fallback to Internal Medicine if no match
    if (!$matchedSpecialty) {
        $matchedSpecialty = 'Internal Medicine';
        $matchedDoctors   = $doctorsBySpecialty['Internal Medicine'];
    }
}


// ============================================================
// OUTPUT HELPERS
// ============================================================

/**
 * Build a safe internal URL to an article page.
 * In WordPress: get_permalink($page_id) . '?mayo-id=' . $id
 */
function article_url(string $mayoId): string {
    return ARTICLES_PATH . '?mayo-id=' . urlencode($mayoId);
}

/** Safely echo HTML-escaped output */
function e(string $value): void {
    echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        if ($mode === 'article' && $article) {
            e($article['title'] ?? 'Article');
            echo ' | ';
        } elseif ($mode === 'search') {
            e($query);
            echo ' | Search | ';
        }
        echo SITE_NAME;
        ?>
    </title>

    <?php
    // REQUIRED by Mayo's syndication terms:
    // Canonical tag must be included on all public-facing pages with Mayo content.
    // The originalUrl comes from the article's meta.originalUrl field.
    // In WordPress this goes in wp_head() via add_action('wp_head', ...) in functions.php.
    if ($mode === 'article' && !empty($article['meta']['originalUrl'])):
    ?>
    <link rel="canonical" href="<?php e($article['meta']['originalUrl']); ?>">
    <?php endif; ?>

    <!--
        In WordPress, styles go in your child theme's style.css
        or are enqueued via wp_enqueue_style() in functions.php.
        This inline block is just for prototype clarity.
    -->
    <style>
        /* === Base === */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #222;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        /* === Site Header (becomes your theme's header.php) === */
        .site-header {
            background: #003865; /* replace with your brand color */
            color: #fff;
            padding: 1rem 2rem;
        }
        .site-header a { color: #fff; text-decoration: none; }
        .site-header h1 { margin: 0; font-size: 1.4rem; font-weight: 600; }

        /* === Search Bar === */
        .search-bar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 1rem 2rem;
        }
        .search-form {
            display: flex;
            gap: .5rem;
            max-width: 640px;
        }
        .search-form input[type="search"] {
            flex: 1;
            padding: .5rem .75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        .search-form button {
            padding: .5rem 1.25rem;
            background: #003865;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .search-form button:hover { background: #004f8b; }

        /* === Main Content === */
        .site-main {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* === Two-column layout (article + search pages) === */
        .content-with-sidebar {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            align-items: start;
        }
        .content-col { min-width: 0; }

        /* === CTA Sidebar === */
        .cta-sidebar {
            position: sticky;
            top: 1.5rem;
        }
        .cta-card {
            background: #003865;
            color: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .cta-card h3 {
            margin: 0 0 .5rem;
            font-size: 1.1rem;
            color: #fff;
        }
        .cta-card p {
            font-size: .9rem;
            margin: 0 0 1rem;
            opacity: .9;
            line-height: 1.5;
        }
        .cta-btn {
            display: block;
            text-align: center;
            background: #fff;
            color: #003865;
            font-weight: 600;
            font-size: .95rem;
            padding: .65rem 1rem;
            border-radius: 4px;
            text-decoration: none;
        }
        .cta-btn:hover { background: #e8f0fe; }
        .cta-card-secondary {
            background: #fff;
            border: 2px solid #003865;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .cta-card-secondary h3 {
            margin: 0 0 .4rem;
            font-size: 1rem;
            color: #003865;
        }
        .cta-card-secondary p {
            font-size: .85rem;
            color: #555;
            margin: 0 0 .75rem;
            line-height: 1.4;
        }
        .cta-btn-secondary {
            display: block;
            text-align: center;
            background: #003865;
            color: #fff;
            font-weight: 600;
            font-size: .9rem;
            padding: .55rem 1rem;
            border-radius: 4px;
            text-decoration: none;
        }
        .cta-btn-secondary:hover { background: #004f8b; }
        .cta-disclaimer {
            font-size: .75rem;
            color: #888;
            text-align: center;
            line-height: 1.4;
            margin-top: .5rem;
        }
        .cta-card-secondary h3 {
            font-size: .95rem;
        }
        .cta-card-secondary p {
            margin-bottom: .35rem;
        }

        @media (max-width: 700px) {
            .content-with-sidebar {
                grid-template-columns: 1fr;
            }
            .cta-sidebar {
                position: static;
            }
        }

        /* === Error Notice === */
        .notice-error {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: .75rem 1rem;
            margin-bottom: 1.5rem;
            color: #664d03;
        }

        /* === Search Results === */
        .search-results-count {
            font-size: .9rem;
            color: #555;
            margin-bottom: 1rem;
        }
        .search-results { list-style: none; padding: 0; margin: 0; }
        .search-result-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: .75rem;
        }
        .search-result-item h3 { margin: 0 0 .25rem; font-size: 1.05rem; }
        .search-result-item h3 a { color: #003865; text-decoration: none; }
        .search-result-item h3 a:hover { text-decoration: underline; }
        .search-result-item .snippet { font-size: .9rem; color: #555; margin: 0; }
        .content-type-badge {
            display: inline-block;
            font-size: .75rem;
            background: #e8f0fe;
            color: #1a56db;
            border-radius: 3px;
            padding: .1rem .4rem;
            margin-bottom: .3rem;
        }

        /* === Article === */
        .article-content {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 2rem;
        }
        .article-content h2 { font-size: 1.6rem; margin-top: 0; color: #003865; }
        .article-content h3 { font-size: 1.15rem; color: #003865; }
        .article-content p  { margin-bottom: 1rem; }
        .article-meta {
            font-size: .85rem;
            color: #777;
            border-top: 1px solid #eee;
            margin-top: 1.5rem;
            padding-top: .75rem;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            font-size: .9rem;
            color: #003865;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }

        .article-abstract {
            font-size: 1.05rem;
            color: #444;
            font-style: italic;
            border-left: 3px solid #003865;
            padding-left: .75rem;
            margin-bottom: 1.5rem;
        }

        .mayo-section-content { margin-bottom: 1.25rem; }
        .mayo-section-content p  { margin-bottom: .75rem; }
        .mayo-section-content ul { padding-left: 1.5rem; margin-bottom: .75rem; }
        .mayo-section-content li { margin-bottom: .35rem; }
        .mayo-section-content h3 { font-size: 1rem; color: #003865; margin: 1rem 0 .4rem; }

        figure.mayo-image {
            margin: 1rem 0 1.25rem;
        }
        figure.mayo-image figcaption {
            font-size: .8rem;
            color: #666;
            font-style: italic;
            margin-top: .4rem;
        }

        .result-date {
            font-size: .8rem;
            color: #888;
            margin: .25rem 0 0;
        }

        /* === Mayo Attribution (check your license for exact required text) === */
        .mayo-attribution {
            font-size: .8rem;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 1rem;
            margin-top: 2rem;
        }

        /* === Site Footer (becomes your theme's footer.php) === */
        .site-footer {
            text-align: center;
            padding: 2rem;
            font-size: .85rem;
            color: #888;
            border-top: 1px solid #ddd;
            margin-top: 3rem;
        }
    </style>
</head>
<body>

<!-- ============================================================
     HEADER
     In WordPress: get_header() pulls in header.php from your theme.
     The search form will likely move into header.php or a widget.
     ============================================================ -->
<header class="site-header">
    <h1><a href="/"><?php e(SITE_NAME); ?></a></h1>
</header>

<div class="search-bar">
    <form class="search-form" method="GET" action="<?php echo ARTICLES_PATH; ?>">
        <input
            type="search"
            name="q"
            placeholder="Search health topics&hellip;"
            value="<?php e($query); ?>"
            aria-label="Search health library"
        >
        <button type="submit">Search</button>
    </form>
</div>

<!-- ============================================================
     MAIN CONTENT
     In WordPress: the_content() and custom template logic sit here,
     inside the Loop or in a custom page template.
     ============================================================ -->
<main class="site-main">
<?php if ($showSidebar): ?>
<div class="content-with-sidebar">
<div class="content-col">
<?php endif; ?>

    <?php if ($error): ?>
        <div class="notice-error" role="alert">
            <?php e($error); ?>
        </div>
    <?php endif; ?>


    <?php // ---- MODE: ARTICLE ------------------------------------------ ?>
    <?php if ($mode === 'article' && $article): ?>

        <a class="back-link" href="/index-cta.php?q=<?php e($query); ?>">
            &larr; Back to search
        </a>

        <?php
        // Extract confirmed fields from Mayo API response structure
        // (as observed in article responses)
        $meta        = $article['meta']        ?? [];
        $body        = $article['body']        ?? [];
        $sections    = $body['section']        ?? [];
        $title       = $article['title']       ?? ($meta['docId'] ?? 'Article');
        $abstract    = $article['abstract']    ?? '';   // top-level string (NOT array like search results)
        $originalUrl = $meta['originalUrl']    ?? '';   // required for canonical tag
        $contentType = $meta['contentType']    ?? '';
        $schema      = $article['noNamespaceSchemaLocation'] ?? ''; // e.g. disease.xsd, answer.xsd

        // updateDate MUST be displayed per Mayo's syndication terms
        $updateDate  = !empty($meta['updateDate'])
                       ? date('F j, Y', strtotime($meta['updateDate']))
                       : '';

        // termsOfUse docId — fetch this doc or link to mayoclinic.org terms page
        $touDocId    = $meta['termsOfUse']['docId'] ?? '';
        ?>

        <div class="article-content">
            <?php if ($contentType): ?>
                <span class="content-type-badge"><?php e($contentType); ?></span>
            <?php endif; ?>

            <h2><?php e($title); ?></h2>

            <?php if ($abstract): ?>
                <!-- Article abstract — top-level string field confirmed from API response -->
                <?php
                // Abstract may contain block-level HTML (<p> tags) from Mayo.
                // If it does, render as a div to avoid invalid nested <p> tags.
                $abstractClean = strip_tags($abstract, '<p><b><i><em><strong><a><br><ul><ol><li>');
                if (strpos($abstractClean, '<p') !== false):
                ?>
                    <div class="article-abstract"><?php echo $abstractClean; ?></div>
                <?php else: ?>
                    <p class="article-abstract"><?php echo $abstractClean; ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($sections)): ?>
                <?php foreach ($sections as $section): ?>
                    <?php
                    $sectionHead = $section['sectionHead'] ?? '';
                    $htmlParts   = $section['html']        ?? [];
                    $htmlContent = is_array($htmlParts) ? implode('', $htmlParts) : (string) $htmlParts;

                    // popupMedia sits inside each section — confirmed from ART-20047732 response
                    // Structure: section.popupMedia[].image{ id, alt, caption, title, width, height }
                    $popupMedia  = $section['popupMedia']  ?? [];
                    ?>
                    <?php if ($sectionHead && $sectionHead !== 'Answer section'): ?>
                        <h3><?php e($sectionHead); ?></h3>
                    <?php endif; ?>

                    <?php foreach ($popupMedia as $media):
                        $img = $media['image'] ?? [];
                        $imgId  = preg_replace('/[^A-Za-z0-9\-]/', '', $img['id']      ?? '');
                        $imgAlt = $img['alt']     ?? '';
                        $imgCap = $img['caption'] ?? '';
                        if (!$imgId) continue;
                    ?>
                        <figure class="mayo-image">
                            <img
                                src="/?mayo-image=<?php echo urlencode($imgId); ?>"
                                alt="<?php e($imgAlt); ?>"
                                loading="lazy"
                                style="max-width:100%;height:auto;border-radius:4px;"
                            >
                            <?php if ($imgCap): ?>
                                <figcaption><?php e($imgCap); ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                    <?php if ($htmlContent): ?>
                        <!-- Mayo-provided HTML — safe to output raw, do not sanitize away their markup -->
                        <div class="mayo-section-content"><?php echo $htmlContent; ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- Development fallback: raw dump if section structure doesn't match -->
                <pre style="font-size:.75rem;overflow:auto;background:#f5f5f5;padding:1rem;"><?php
                    echo htmlspecialchars(json_encode($article, JSON_PRETTY_PRINT));
                ?></pre>
            <?php endif; ?>

            <?php if ($updateDate): ?>
                <!-- REQUIRED by Mayo's syndication terms: update date must be displayed -->
                <div class="article-meta">
                    <strong>Last reviewed:</strong> <?php e($updateDate); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- REQUIRED by Mayo's syndication terms: copyright + terms of use -->
        <p class="mayo-attribution">
            &copy;1998-<?php echo date('Y'); ?> Mayo Foundation for Medical Education and Research (MFMER).
            All rights reserved.
            <a href="https://www.mayoclinic.org/about-this-site/terms-use-licensed-content"
               target="_blank" rel="noopener">Terms of Use</a>
        </p>


    <?php // ---- MODE: SEARCH RESULTS ------------------------------------- ?>
    <?php elseif ($mode === 'search' && $searchData): ?>

        <?php
        // Confirmed Mayo API response structure (from Postman, 2026):
        // { "count": 176, "searchResults": [...], "facets": null }
        // Each result: { "contentId", "contentType", "lang", "title",
        //   "abstract": [string,...], "updateDate", "publicationDate",
        //   "alternativeIdentifiers": [] }
        $results = $searchData['searchResults'] ?? [];
        $total   = $searchData['count']         ?? count($results);
        ?>

        <p class="search-results-count">
            <?php echo number_format($total); ?> result<?php echo $total !== 1 ? 's' : ''; ?>
            for &ldquo;<?php e($query); ?>&rdquo;
        </p>

        <?php if (empty($results)): ?>
            <p>No results found. Try different search terms.</p>
        <?php else: ?>
            <ul class="search-results">
                <?php foreach ($results as $result):
                    $id          = $result['contentId']   ?? '';
                    $title       = $result['title']       ?? 'Untitled';
                    $contentType = $result['contentType'] ?? '';
                    // abstract is an array of strings — join them
                    $abstractArr = $result['abstract']    ?? [];
                    $snippet     = is_array($abstractArr)
                                   ? implode(' ', $abstractArr)
                                   : (string) $abstractArr;
                    // Format the update date for display
                    $updateDate  = !empty($result['updateDate'])
                                   ? date('F j, Y', strtotime($result['updateDate']))
                                   : '';
                ?>
                <li class="search-result-item">
                    <?php if ($contentType): ?>
                        <span class="content-type-badge"><?php e($contentType); ?></span>
                    <?php endif; ?>
                    <h3>
                        <a href="<?php echo article_url($id); ?>">
                            <?php e($title); ?>
                        </a>
                    </h3>
                    <?php if ($snippet): ?>
                        <p class="snippet"><?php echo strip_tags($snippet, '<b><i><em><strong>'); ?></p>
                    <?php endif; ?>
                    <?php if ($updateDate): ?>
                        <p class="result-date">Updated: <?php e($updateDate); ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>


    <?php // ---- MODE: HOME (landing with sample articles) -------------- ?>
    <?php else: ?>

        <h2>Search the Health Library</h2>
        <p>
            Search our library of trusted health information from Mayo Clinic.
            Enter a condition, symptom, procedure, or health topic to get started.
        </p>

        <?php
        // Fetch a varied sample of articles to populate the landing page.
        // Uses a broad search with no query (q=*) ordered by update date so
        // results stay reasonably fresh. Cached for 48 hours like everything else.
        $sampleData = mayo_search('*', 12);
        $sampleResults = $sampleData['searchResults'] ?? [];

        // Shuffle so repeat visits feel different even when cached
        shuffle($sampleResults);
        ?>

        <?php if (!empty($sampleResults)): ?>
            <h3 style="margin-top:2rem;color:#003865;">Featured Health Topics</h3>
            <ul class="search-results">
                <?php foreach ($sampleResults as $result):
                    $id          = $result['contentId']   ?? '';
                    $title       = $result['title']       ?? 'Untitled';
                    $contentType = $result['contentType'] ?? '';
                    $abstractArr = $result['abstract']    ?? [];
                    $snippet     = is_array($abstractArr)
                                   ? implode(' ', $abstractArr)
                                   : (string) $abstractArr;
                    $updateDate  = !empty($result['updateDate'])
                                   ? date('F j, Y', strtotime($result['updateDate']))
                                   : '';
                    if (!$id) continue;
                ?>
                <li class="search-result-item">
                    <?php if ($contentType): ?>
                        <span class="content-type-badge"><?php e($contentType); ?></span>
                    <?php endif; ?>
                    <h3>
                        <a href="<?php echo article_url($id); ?>">
                            <?php e($title); ?>
                        </a>
                    </h3>
                    <?php if ($snippet): ?>
                        <p class="snippet"><?php echo strip_tags($snippet, '<b><i><em><strong>'); ?></p>
                    <?php endif; ?>
                    <?php if ($updateDate): ?>
                        <p class="result-date">Updated: <?php e($updateDate); ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    <?php endif; ?>

<?php if ($showSidebar): ?>
</div><!-- /.content-col -->

<aside class="cta-sidebar">

    <?php if ($mode === 'article' && $matchedSpecialty && !empty($matchedDoctors)): ?>

        <div class="cta-card">
            <h3><?php e($matchedSpecialty); ?> Specialists</h3>
            <p>Based on this article, these providers may be able to help.</p>
        </div>

        <?php foreach ($matchedDoctors as $doc): ?>
        <div class="cta-card-secondary">
            <h3><?php e($doc['name']); ?></h3>
            <p><?php e($doc['title']); ?></p>
            <p style="font-size:.8rem;color:#555;margin-bottom:.75rem;">
                <?php e($doc['phone']); ?>
            </p>
            <a class="cta-btn-secondary" href="#">Schedule Appointment</a>
        </div>
        <?php endforeach; ?>

    <?php elseif ($mode === 'search'): ?>

        <div class="cta-card">
            <h3>Find a Doctor</h3>
            <p>Our specialists are here to help with your health concerns.</p>
            <a class="cta-btn" href="#">Search Our Providers</a>
        </div>

    <?php else: ?>

        <div class="cta-card">
            <h3>Find a Doctor</h3>
            <p>Connect with a specialist who can help with your health concerns.</p>
            <a class="cta-btn" href="#">Schedule an Appointment</a>
        </div>

    <?php endif; ?>

    <p class="cta-disclaimer">
        This health information is provided for educational purposes only and is not a substitute for professional medical advice.
    </p>

</aside>

</div><!-- /.content-with-sidebar -->
<?php endif; ?>
</main>

<!-- ============================================================
     FOOTER
     In WordPress: get_footer() pulls in footer.php from your theme.
     ============================================================ -->
<footer class="site-footer">
    <p>Health information provided by Mayo Clinic.</p>
    <p>&copy; <?php echo date('Y'); ?> Mayo Foundation for Medical Education and Research (MFMER). All rights reserved.</p>
</footer>

</body>
</html>
