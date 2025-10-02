<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * إضافة قواعد إعادة الكتابة لملفات خريطة الموقع المخصصة.
 */
function my_bible_custom_sitemap_rewrite_rules() {
    add_rewrite_tag('%my_bible_sitemap%', '([^&]+)'); 
    
    // --- بداية التعديل: إضافة /bible/ إلى بداية كل قاعدة ---
    // هذا يجعل القاعدة أكثر تحديداً ويحل التعارض مع قواعد الأسفار
    add_rewrite_rule('^bible/bible-sitemap\.xml?$', 'index.php?my_bible_sitemap=index', 'top');
    add_rewrite_rule('^bible/bible-sitemap-books\.xml?$', 'index.php?my_bible_sitemap=books', 'top');
    add_rewrite_rule('^bible/bible-sitemap-chapters\.xml?$', 'index.php?my_bible_sitemap=chapters', 'top');
    add_rewrite_rule('^bible/bible-sitemap-verses\.xml?$', 'index.php?my_bible_sitemap=verses', 'top');
    // --- نهاية التعديل ---
}
add_action('init', 'my_bible_custom_sitemap_rewrite_rules');

/**
 * إضافة متغير الاستعلام المخصص إلى قائمة المتغيرات المعروفة.
 */
function my_bible_custom_sitemap_query_vars($vars) {
    $vars[] = 'my_bible_sitemap';
    return $vars;
}
add_filter('query_vars', 'my_bible_custom_sitemap_query_vars');

/**
 * معالجة طلبات خريطة الموقع المخصصة.
 */
function my_bible_handle_custom_sitemap_request() {
    $sitemap_type = get_query_var('my_bible_sitemap');

    if (empty($sitemap_type)) {
        return; // ليس طلب خريطة موقع خاص بنا
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $home_url = home_url('/');
    $lastmod_date_source_file = defined('MY_BIBLE_PLUGIN_DIR') ? MY_BIBLE_PLUGIN_DIR . 'my-bible-plugin.php' : __DIR__ . '/../my-bible-plugin.php';
    $lastmod_date = gmdate('Y-m-d\TH:i:s\Z', file_exists($lastmod_date_source_file) ? filemtime($lastmod_date_source_file) : time());

    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT'); 
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow', true); 

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

    if ($sitemap_type === 'index') {
        error_log('[My Bible Plugin DEBUG] Generating sitemap index (bible-sitemap.xml)');
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // --- بداية التعديل: تعديل الروابط الداخلية لخريطة الموقع ---
        $count_books = (int) $wpdb->get_var("SELECT COUNT(DISTINCT book) FROM {$table_name}");
        if ($count_books > 0) {
            echo "  <sitemap>\n";
            echo "    <loc>" . esc_url(trailingslashit($home_url . 'bible/bible-sitemap-books.xml')) . "</loc>\n";
            echo "    <lastmod>" . esc_html($lastmod_date) . "</lastmod>\n";
            echo "  </sitemap>\n";
        }

        $count_chapters = (int) $wpdb->get_var("SELECT COUNT(DISTINCT book, chapter) FROM {$table_name}");
        if ($count_chapters > 0) {
            echo "  <sitemap>\n";
            echo "    <loc>" . esc_url(trailingslashit($home_url . 'bible/bible-sitemap-chapters.xml')) . "</loc>\n";
            echo "    <lastmod>" . esc_html($lastmod_date) . "</lastmod>\n";
            echo "  </sitemap>\n";
        }

        $count_verses = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        if ($count_verses > 0) {
            echo "  <sitemap>\n";
            echo "    <loc>" . esc_url(trailingslashit($home_url . 'bible/bible-sitemap-verses.xml')) . "</loc>\n";
            echo "    <lastmod>" . esc_html($lastmod_date) . "</lastmod>\n";
            echo "  </sitemap>\n";
        }
        // --- نهاية التعديل ---
        echo '</sitemapindex>';

    } elseif (in_array($sitemap_type, array('books', 'chapters', 'verses'))) {
        error_log('[My Bible Plugin DEBUG] Generating sub-sitemap: ' . $sitemap_type);
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        $entries = array();

        if ($sitemap_type === 'books') {
            $ordered_books = function_exists('my_bible_get_book_order_from_db') ? my_bible_get_book_order_from_db('all') : array();
            $db_books = array();
            if (!empty($ordered_books)) {
                 $db_books = $wpdb->get_col("SELECT DISTINCT book FROM {$table_name} WHERE book IN (" . implode(',', array_map(function($b){ return "'".esc_sql($b)."'"; }, $ordered_books)) . ") ORDER BY FIELD(book, " . implode(',', array_map(function($b){ return "'".esc_sql($b)."'"; }, $ordered_books)) . ")");
            } else {
                $db_books = $wpdb->get_col("SELECT DISTINCT book FROM {$table_name} ORDER BY book ASC");
            }
            if ($db_books) {
                foreach ($db_books as $book_name) {
                    if (function_exists('my_bible_create_book_slug')) {
                        $book_slug = my_bible_create_book_slug($book_name);
                        $url = home_url('/bible/' . $book_slug . '/'); 
                        $entries[] = array('loc' => $url, 'lastmod' => $lastmod_date, 'changefreq' => 'monthly', 'priority' => 0.8);
                    }
                }
            }
        } elseif ($sitemap_type === 'chapters') {
            $results = $wpdb->get_results("SELECT DISTINCT book, chapter FROM {$table_name} ORDER BY book, chapter ASC");
            if ($results) {
                foreach ($results as $item) {
                    if (function_exists('my_bible_create_book_slug')) {
                        $book_slug = my_bible_create_book_slug($item->book);
                        $url = home_url('/bible/' . $book_slug . '/' . $item->chapter . '/');
                        $entries[] = array('loc' => $url, 'lastmod' => $lastmod_date, 'changefreq' => 'yearly', 'priority' => 0.7);
                    }
                }
            }
        } elseif ($sitemap_type === 'verses') {
            $results = $wpdb->get_results("SELECT book, chapter, verse FROM {$table_name} ORDER BY book, chapter ASC, verse ASC");
            if ($results) {
                foreach ($results as $item) {
                     if (function_exists('my_bible_create_book_slug')) {
                        $book_slug = my_bible_create_book_slug($item->book);
                        $url = home_url('/bible/' . $book_slug . '/' . $item->chapter . '/' . $item->verse . '/');
                        $entries[] = array('loc' => $url, 'lastmod' => $lastmod_date, 'changefreq' => 'never', 'priority' => 0.5);
                    }
                }
            }
        }

        foreach ($entries as $entry) {
            echo "  <url>\n";
            echo "    <loc>" . esc_url($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                echo "    <lastmod>" . esc_html($entry['lastmod']) . "</lastmod>\n";
            }
            if (!empty($entry['changefreq'])) {
                echo "    <changefreq>" . esc_html($entry['changefreq']) . "</changefreq>\n";
            }
            if (!empty($entry['priority'])) {
                echo "    <priority>" . esc_html(number_format((float)$entry['priority'], 1)) . "</priority>\n";
            }
            echo "  </url>\n";
        }
        echo '</urlset>';
    } else {
        error_log('[My Bible Plugin DEBUG] Unknown sitemap type requested: \'' . print_r($sitemap_type, true) . '\'. Outputting empty or minimal XML.');
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'; 
    }
    exit; 
}
add_action('template_redirect', 'my_bible_handle_custom_sitemap_request', 5);
?>
