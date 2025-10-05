<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * إضافة meta tags محسنة لمحركات البحث
 * Enhanced SEO meta tags for better search engine optimization
 */

// دالة لإضافة Open Graph و Twitter Card meta tags
function my_bible_add_seo_meta_tags() {
    // فقط في صفحات الكتاب المقدس
    if (!is_page() && !my_bible_is_bible_page()) {
        return;
    }
    
    // الحصول على بيانات الصفحة الحالية
    $page_data = my_bible_get_current_page_seo_data();
    
    if (!$page_data) {
        return;
    }
    
    // إضافة meta tags أساسية
    echo "\n<!-- Bible Plugin SEO Meta Tags -->\n";
    
    // Title و Description
    if (!empty($page_data['title'])) {
        echo '<meta property="og:title" content="' . esc_attr($page_data['title']) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($page_data['title']) . '">' . "\n";
    }
    
    if (!empty($page_data['description'])) {
        echo '<meta name="description" content="' . esc_attr($page_data['description']) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($page_data['description']) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($page_data['description']) . '">' . "\n";
    }
    
    // URL كانونية
    if (!empty($page_data['canonical_url'])) {
        echo '<link rel="canonical" href="' . esc_url($page_data['canonical_url']) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($page_data['canonical_url']) . '">' . "\n";
    }
    
    // نوع المحتوى
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta name="twitter:card" content="summary">' . "\n";
    
    // معلومات الموقع
    $site_name = get_bloginfo('name');
    if ($site_name) {
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        echo '<meta name="twitter:site" content="@' . esc_attr(sanitize_title($site_name)) . '">' . "\n";
    }
    
    // اللغة
    echo '<meta property="og:locale" content="ar_AR">' . "\n";
    
    // Keywords للآيات
    if (!empty($page_data['keywords'])) {
        echo '<meta name="keywords" content="' . esc_attr($page_data['keywords']) . '">' . "\n";
    }
    
    // Schema.org structured data
    $schema_data = my_bible_get_schema_data($page_data);
    if ($schema_data) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }
    
    echo "<!-- End Bible Plugin SEO Meta Tags -->\n\n";
}
add_action('wp_head', 'my_bible_add_seo_meta_tags', 1);

/**
 * دالة للتحقق من أن الصفحة الحالية هي صفحة كتاب مقدس
 */
function my_bible_is_bible_page() {
    global $post;
    
    // تحقق من shortcode
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'bible_content')) {
        return true;
    }
    
    // تحقق من slug الصفحة
    if (is_page('bible')) {
        return true;
    }
    
    // تحقق من URL structure
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($current_url, '/bible/') !== false) {
        return true;
    }
    
    return false;
}

/**
 * جمع بيانات SEO للصفحة الحالية
 */
function my_bible_get_current_page_seo_data() {
    // الحصول على معطيات URL
    $book_slug = get_query_var('book');
    $chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : 0;
    $verse = get_query_var('verse') ? intval(get_query_var('verse')) : 0;
    
    $page_data = array(
        'title' => '',
        'description' => '',
        'canonical_url' => '',
        'keywords' => '',
        'book_name' => '',
        'chapter_number' => 0,
        'verse_number' => 0,
        'verse_text' => ''
    );
    
    $site_name = get_bloginfo('name');
    $base_url = home_url();
    
    if (!empty($book_slug)) {
        // الحصول على اسم السفر من قاعدة البيانات
        if (function_exists('my_bible_get_book_name_from_slug')) {
            $book_name = my_bible_get_book_name_from_slug($book_slug);
        } else {
            $book_name = str_replace('-', ' ', urldecode($book_slug));
        }
        
        if (!$book_name) {
            return null;
        }
        
        $page_data['book_name'] = $book_name;
        
        if ($chapter > 0) {
            $page_data['chapter_number'] = $chapter;
            
            if ($verse > 0) {
                // صفحة آية محددة
                $verse_data = my_bible_get_verse_data($book_name, $chapter, $verse);
                if ($verse_data) {
                    $page_data['verse_number'] = $verse;
                    $page_data['verse_text'] = $verse_data->text;
                    
                    // Title للآية
                    $page_data['title'] = sprintf('%s %d:%d - %s', $book_name, $chapter, $verse, $site_name);
                    
                    // Description للآية
                    $clean_text = wp_trim_words(strip_tags($verse_data->text), 25, '...');
                    $page_data['description'] = sprintf('"%s" - %s %d:%d من %s', $clean_text, $book_name, $chapter, $verse, $site_name);
                    
                    // Canonical URL للآية
                    $page_data['canonical_url'] = home_url("/bible/{$book_slug}/{$chapter}/{$verse}/");
                    
                    // Keywords للآية
                    $page_data['keywords'] = sprintf('الكتاب المقدس, %s, الأصحاح %d, الآية %d, %s', $book_name, $chapter, $verse, $site_name);
                }
            } else {
                // صفحة أصحاح كامل
                $verses_data = my_bible_get_chapter_verses($book_name, $chapter);
                if ($verses_data && !empty($verses_data)) {
                    // Title للأصحاح
                    $page_data['title'] = sprintf('%s الأصحاح %d - %s', $book_name, $chapter, $site_name);
                    
                    // Description للأصحاح
                    $first_verse = wp_trim_words(strip_tags($verses_data[0]->text), 20, '...');
                    $verses_count = count($verses_data);
                    $page_data['description'] = sprintf('%s الأصحاح %d (%d آية) - يبدأ بـ: "%s" - من %s', 
                        $book_name, $chapter, $verses_count, $first_verse, $site_name);
                    
                    // Canonical URL للأصحاح
                    $page_data['canonical_url'] = home_url("/bible/{$book_slug}/{$chapter}/");
                    
                    // Keywords للأصحاح
                    $page_data['keywords'] = sprintf('الكتاب المقدس, %s, الأصحاح %d, %s آية, %s', 
                        $book_name, $chapter, $verses_count, $site_name);
                }
            }
        } else {
            // صفحة السفر (فهرس الأصحاحات)
            $chapters_count = my_bible_get_chapters_count($book_name);
            
            // Title للسفر
            $page_data['title'] = sprintf('%s - %s', $book_name, $site_name);
            
            // Description للسفر
            $page_data['description'] = sprintf('%s من الكتاب المقدس (%d أصحاح) - اقرأ النص الكامل مع الشرح والتفسير في %s', 
                $book_name, $chapters_count, $site_name);
            
            // Canonical URL للسفر
            $page_data['canonical_url'] = home_url("/bible/{$book_slug}/");
            
            // Keywords للسفر
            $page_data['keywords'] = sprintf('الكتاب المقدس, %s, %d أصحاح, %s', $book_name, $chapters_count, $site_name);
        }
    } else {
        // الصفحة الرئيسية للكتاب المقدس
        $page_data['title'] = sprintf('الكتاب المقدس - %s', $site_name);
        $page_data['description'] = sprintf('اقرأ الكتاب المقدس كاملاً باللغة العربية مع البحث المتقدم وقاموس المصطلحات في %s', $site_name);
        $page_data['canonical_url'] = home_url('/bible/');
        $page_data['keywords'] = sprintf('الكتاب المقدس, العهد القديم, العهد الجديد, الإنجيل, التوراة, المزامير, %s', $site_name);
    }
    
    return $page_data;
}

/**
 * الحصول على بيانات آية محددة
 */
function my_bible_get_verse_data($book_name, $chapter, $verse) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    
    $verse_data = $wpdb->get_row($wpdb->prepare(
        "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d LIMIT 1",
        $book_name, $chapter, $verse
    ));
    
    return $verse_data;
}

/**
 * الحصول على آيات أصحاح كامل
 */
function my_bible_get_chapter_verses($book_name, $chapter) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    
    $verses = $wpdb->get_results($wpdb->prepare(
        "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC LIMIT 200",
        $book_name, $chapter
    ));
    
    return $verses;
}

/**
 * حساب عدد أصحاحات السفر
 */
function my_bible_get_chapters_count($book_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT chapter) FROM $table_name WHERE book = %s",
        $book_name
    ));
    
    return intval($count);
}

/**
 * إنشاء بيانات Schema.org المنظمة
 */
function my_bible_get_schema_data($page_data) {
    if (empty($page_data['book_name'])) {
        return null;
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'name' => $page_data['title'],
        'description' => $page_data['description'],
        'url' => $page_data['canonical_url'],
        'inLanguage' => 'ar',
        'isPartOf' => array(
            '@type' => 'Book',
            'name' => 'الكتاب المقدس',
            'author' => array(
                '@type' => 'Organization',
                'name' => 'الكتاب المقدس'
            )
        )
    );
    
    // إضافة معلومات إضافية للآيات
    if (!empty($page_data['verse_text'])) {
        $schema['@type'] = 'CreativeWork';
        $schema['text'] = strip_tags($page_data['verse_text']);
        $schema['isPartOf']['hasPart'] = array(
            '@type' => 'Chapter',
            'name' => $page_data['book_name'] . ' الأصحاح ' . $page_data['chapter_number'],
            'position' => $page_data['chapter_number']
        );
    }
    
    // إضافة معلومات الناشر
    $site_name = get_bloginfo('name');
    if ($site_name) {
        $schema['publisher'] = array(
            '@type' => 'Organization',
            'name' => $site_name,
            'url' => home_url()
        );
    }
    
    // إضافة تاريخ النشر
    $schema['datePublished'] = date('c');
    $schema['dateModified'] = date('c');
    
    return $schema;
}

/**
 * تحسين عناوين الصفحات
 */
function my_bible_filter_page_title($title) {
    if (!my_bible_is_bible_page()) {
        return $title;
    }
    
    $page_data = my_bible_get_current_page_seo_data();
    if ($page_data && !empty($page_data['title'])) {
        return $page_data['title'];
    }
    
    return $title;
}
add_filter('pre_get_document_title', 'my_bible_filter_page_title');
add_filter('wp_title', 'my_bible_filter_page_title');

/**
 * إضافة robots meta tag للأرشفة الصحيحة
 */
function my_bible_add_robots_meta() {
    if (!my_bible_is_bible_page()) {
        return;
    }
    
    // السماح بأرشفة جميع صفحات الكتاب المقدس
    echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";
    
    // إضافة hreflang للدعم متعدد اللغات في المستقبل
    echo '<link rel="alternate" hreflang="ar" href="' . esc_url(get_permalink()) . '">' . "\n";
}
add_action('wp_head', 'my_bible_add_robots_meta', 2);

/**
 * تحسين بنية URL للأرشفة
 */
function my_bible_add_breadcrumb_schema() {
    if (!my_bible_is_bible_page()) {
        return;
    }
    
    $book_slug = get_query_var('book');
    $chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : 0;
    $verse = get_query_var('verse') ? intval(get_query_var('verse')) : 0;
    
    if (empty($book_slug)) {
        return;
    }
    
    $breadcrumb_items = array();
    
    // الصفحة الرئيسية
    $breadcrumb_items[] = array(
        '@type' => 'ListItem',
        'position' => 1,
        'name' => get_bloginfo('name'),
        'item' => home_url('/')
    );
    
    // صفحة الكتاب المقدس
    $breadcrumb_items[] = array(
        '@type' => 'ListItem',
        'position' => 2,
        'name' => 'الكتاب المقدس',
        'item' => home_url('/bible/')
    );
    
    // اسم السفر
    if (function_exists('my_bible_get_book_name_from_slug')) {
        $book_name = my_bible_get_book_name_from_slug($book_slug);
        if ($book_name) {
            $breadcrumb_items[] = array(
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $book_name,
                'item' => home_url("/bible/{$book_slug}/")
            );
            
            // الأصحاح
            if ($chapter > 0) {
                $breadcrumb_items[] = array(
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => "الأصحاح {$chapter}",
                    'item' => home_url("/bible/{$book_slug}/{$chapter}/")
                );
                
                // الآية
                if ($verse > 0) {
                    $breadcrumb_items[] = array(
                        '@type' => 'ListItem',
                        'position' => 5,
                        'name' => "الآية {$verse}",
                        'item' => home_url("/bible/{$book_slug}/{$chapter}/{$verse}/")
                    );
                }
            }
        }
    }
    
    if (count($breadcrumb_items) > 2) {
        $breadcrumb_schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumb_items
        );
        
        echo '<script type="application/ld+json">';
        echo wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo '</script>' . "\n";
    }
}
add_action('wp_head', 'my_bible_add_breadcrumb_schema', 3);
?>