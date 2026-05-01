<?php
/**
 * Mayo Clinic Health Library - Standalone PHP Prototype
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

define('MAYO_API_BASE',    'https://cdapi.mayoclinic.org/api/v2');
define('MAYO_API_KEY',     'YOUR_API_KEY_HERE');   // <-- replace with your key
define('MAYO_CACHE_DIR',   __DIR__ . '/cache');     // local file cache (WP will use transients instead)
define('MAYO_CACHE_TTL',   3600);                   // cache lifetime in seconds (1 hour)
define('SITE_NAME',        'Health Library');
define('ARTICLES_PATH',    '/article/');            // the base path for article pages on your site
                                                    // e.g. /mayo-clinic-health-information-library/article/


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
            'header'  => 'apikey: ' . MAYO_API_KEY . "\r\n",
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
 * Params:   q (required), rows, start, lang
 *
 * @param  string $query  Search term
 * @param  int    $rows   Results per page (default 10)
 * @param  int    $start  Offset for pagination (default 0)
 * @return array|null
 */
function mayo_search(string $query, int $rows = 10, int $start = 0): ?array {
    return mayo_api_get('/search', [
        'q'     => $query,
        'rows'  => $rows,
        'start' => $start,
        'lang'  => 'en',
    ]);
}

/**
 * Fetch a single article by Mayo content ID.
 *
 * Endpoint: GET /content/{id}/en
 *
 * @param  string $mayoId  e.g. 'CON-20372638'
 * @return array|null
 */
function mayo_get_article(string $mayoId): ?array {
    // Sanitize the ID — only allow alphanumerics and hyphens
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
            // NOTE: adjust field name to match actual Mayo JSON response structure
            e($article['title'] ?? 'Article');
            echo ' | ';
        } elseif ($mode === 'search') {
            e($query);
            echo ' | Search | ';
        }
        echo SITE_NAME;
        ?>
    </title>

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
            max-width: 860px;
            margin: 2rem auto;
            padding: 0 1.5rem;
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

    <?php if ($error): ?>
        <div class="notice-error" role="alert">
            <?php e($error); ?>
        </div>
    <?php endif; ?>


    <?php // ---- MODE: ARTICLE ------------------------------------------ ?>
    <?php if ($mode === 'article' && $article): ?>

        <a class="back-link" href="<?php echo ARTICLES_PATH; ?>?q=<?php e($query); ?>">
            &larr; Back to search
        </a>

        <div class="article-content">
            <!--
                IMPORTANT: The field names below (title, content, sections, etc.)
                are PLACEHOLDERS. You'll need to map these to the actual keys
                in the Mayo API JSON response once you have a sample payload.
                Common patterns: article['title'], article['content']['html'],
                article['sections'], article['updatedDate'], etc.
            -->

            <h2><?php e($article['title'] ?? 'Article'); ?></h2>

            <?php if (!empty($article['content'])): ?>
                <!--
                    If Mayo returns pre-formatted HTML in the content field,
                    output it raw (but ONLY Mayo's own HTML — do not do this
                    with user-supplied content).
                -->
                <?php echo $article['content']; ?>

            <?php elseif (!empty($article['sections'])): ?>
                <!--
                    If Mayo returns structured sections, loop through them.
                    Adjust field names to match actual response.
                -->
                <?php foreach ($article['sections'] as $section): ?>
                    <?php if (!empty($section['title'])): ?>
                        <h3><?php e($section['title']); ?></h3>
                    <?php endif; ?>
                    <?php if (!empty($section['content'])): ?>
                        <p><?php echo $section['content']; ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- Fallback: dump raw data for debugging during development -->
                <pre style="font-size:.75rem;overflow:auto;"><?php
                    echo htmlspecialchars(json_encode($article, JSON_PRETTY_PRINT));
                ?></pre>
            <?php endif; ?>

            <div class="article-meta">
                <?php if (!empty($article['updatedDate'])): ?>
                    Last reviewed: <?php e($article['updatedDate']); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mayo license typically requires this attribution -->
        <p class="mayo-attribution">
            &copy; <?php echo date('Y'); ?> Mayo Foundation for Medical Education and Research (MFMER).
            All rights reserved.
        </p>


    <?php // ---- MODE: SEARCH RESULTS ------------------------------------- ?>
    <?php elseif ($mode === 'search' && $searchData): ?>

        <?php
        // NOTE: Adjust these field names to match the actual Mayo search
        // response structure. Common patterns:
        //   $searchData['response']['numFound']  — total result count
        //   $searchData['response']['docs']      — array of result objects
        //   $searchData['results']               — alternative top-level key
        $results  = $searchData['response']['docs']     ?? $searchData['results'] ?? [];
        $total    = $searchData['response']['numFound']  ?? count($results);
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
                    // NOTE: Adjust field names below to actual Mayo response keys.
                    // Common patterns: id, contentId, title, description, snippet,
                    // contentType, type, abstract
                    $id          = $result['id']          ?? $result['contentId'] ?? '';
                    $title       = $result['title']       ?? 'Untitled';
                    $snippet     = $result['description'] ?? $result['abstract']  ?? '';
                    $contentType = $result['contentType'] ?? $result['type']      ?? '';
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
                        <p class="snippet"><?php e($snippet); ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>


    <?php // ---- MODE: HOME (landing / search form only) ----------------- ?>
    <?php else: ?>

        <h2>Search the Health Library</h2>
        <p>
            Search our library of trusted health information from Mayo Clinic.
            Enter a condition, symptom, procedure, or health topic to get started.
        </p>

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
