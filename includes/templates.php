<?php
// الملف: includes/templates.php

// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

function my_bible_custom_template_redirect($template) {
    global $wp_query, $wpdb;

    $queried_page = get_queried_object();
    // إذا كانت الصفحة الحالية هي 'bible_read'، استخدم القالب الافتراضي لها
    if (is_page('bible_read')) { 
        return $template; 
    }

    // استهداف صفحة 'bible' فقط للروابط الجميلة
    if (!(is_page('bible') && $queried_page && isset($queried_page->post_name) && $queried_page->post_name === 'bible')) {
        return $template; // ليس طلبًا لصفحة الكتاب المقدس الديناميكية
    }

    $book_slug_from_query = get_query_var('book');
    $chapter_num_from_query = get_query_var('chapter') ? intval(get_query_var('chapter')) : null;
    $verse_num_from_query = get_query_var('verse') ? intval(get_query_var('verse')) : null;
    $view_type = get_query_var('my_bible_view');
    
    $canonical_url = '';
    $base_bible_page_url = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');

    // --- 1. معالجة عرض قائمة الأصحاحات لسفر معين ---
    if (!empty($book_slug_from_query) && $view_type === 'chapters' && empty($chapter_num_from_query)) {
        if (!function_exists('my_bible_get_book_name_from_slug')) {
            my_bible_log_error("Function my_bible_get_book_name_from_slug does not exist in template_redirect for chapters view.");
            return $template; 
        }
        $book_name_for_db = my_bible_get_book_name_from_slug($book_slug_from_query);
        if (!$book_name_for_db) {
            add_filter('the_content', function($content) use ($book_slug_from_query) {
                return '<div class="bible-content-area bible-error-message"><p>' . sprintf(esc_html__('السفر "%s" غير موجود أو الرابط غير صحيح.', 'my-bible-plugin'), esc_html(rawurldecode($book_slug_from_query))) . '</p></div>';
            });
            return get_page_template();
        }
        $table_name = $wpdb->prefix . 'bible_verses';
        $chapters = $wpdb->get_col($wpdb->prepare( "SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $book_name_for_db ));
        if ($wpdb->last_error) { my_bible_log_error("DB error fetching chapters for book '{$book_name_for_db}': " . $wpdb->last_error); }
        if (empty($chapters)) {
            add_filter('the_content', function($content) use ($book_name_for_db) {
                return '<div class="bible-content-area bible-error-message"><p>' . sprintf(esc_html__('لم يتم العثور على أصحاحات للسفر "%s".', 'my-bible-plugin'), esc_html($book_name_for_db)) . '</p></div>';
            });
            return get_page_template();
        }
        
        $page_title_for_tab = sprintf(esc_html__('أصحاحات سفر %s', 'my-bible-plugin'), esc_html($book_name_for_db));
        $meta_description_for_chapters = sprintf(esc_attr__('تصفح جميع أصحاحات سفر %s في الكتاب المقدس. اختر الأصحاح الذي تود قراءته.', 'my-bible-plugin'), esc_html($book_name_for_db));
        
        $canonical_url = esc_url(home_url($base_bible_page_url . $book_slug_from_query . '/'));

        add_filter('pre_get_document_title', function() use ($page_title_for_tab) { return $page_title_for_tab . ' - ' . get_bloginfo('name'); }, 999);
        add_action('wp_head', function() use ($meta_description_for_chapters, $canonical_url) { 
            echo '<meta name="description" content="' . $meta_description_for_chapters . '">' . "\n"; 
            if (!empty($canonical_url)) { echo '<link rel="canonical" href="' . $canonical_url . '" />' . "\n"; }
        }, 1);

        add_filter('the_content', function($content) use ($chapters, $book_name_for_db, $book_slug_from_query, $page_title_for_tab, $base_bible_page_url) {
            $page_content = '<div class="bible-chapters-index bible-content-area">';
            $page_content .= '<h1 id="bible-main-page-title">' . $page_title_for_tab . '</h1>';
            $page_content .= '<ul class="bible-chapters-list">';
            foreach ($chapters as $chapter_num_item) {
                $chapter_url = esc_url(home_url($base_bible_page_url . $book_slug_from_query . '/' . $chapter_num_item));
                $page_content .= '<li><a href="' . $chapter_url . '">' . sprintf(esc_html__('الأصحاح %s', 'my-bible-plugin'), esc_html($chapter_num_item)) . '</a></li>';
            }
            $page_content .= '</ul>';
            $index_page = get_page_by_path('bible-index');
            if ($index_page) {
                 $index_page_url = get_permalink($index_page->ID);
                 $page_content .= '<div class="bible-back-to-index" style="margin-top:20px; text-align:center;"><a href="' . esc_url($index_page_url) . '" class="bible-control-button"><i class="fas fa-list-ul"></i> ' . esc_html__('العودة إلى فهرس الكتاب المقدس', 'my-bible-plugin') . '</a></div>';
            }
            $page_content .= '</div>'; 
            return $page_content;
        });
        return get_page_template();
    }
    // --- 2. عرض الأصحاح الكامل أو الآية المنفردة ---
    elseif (!empty($book_slug_from_query) && !empty($chapter_num_from_query)) {
        if (!function_exists('my_bible_get_book_name_from_slug') || !function_exists('my_bible_create_book_slug') || !function_exists('my_bible_get_controls_html')) { 
            my_bible_log_error("Required helper functions missing in template_redirect for chapter/verse view."); 
            return $template; 
        }
        $book_name_for_db = my_bible_get_book_name_from_slug($book_slug_from_query);
        if (!$book_name_for_db) { 
            add_filter('the_content', function($content) use ($book_slug_from_query) {
                return '<div class="bible-content-area bible-error-message"><p>' . sprintf(esc_html__('السفر "%s" غير موجود.', 'my-bible-plugin'), esc_html(rawurldecode($book_slug_from_query))) . '</p></div>';
            });
            return get_page_template();
        }
        
        $page_main_title_h1 = esc_html($book_name_for_db . ' ' . $chapter_num_from_query);
        $page_title_for_tab = $page_main_title_h1; 
        $meta_description = '';
        $verse_object_for_display = null; 

        if (!empty($verse_num_from_query)) { // آية منفردة
            $table_name = $wpdb->prefix . 'bible_verses';
            $verse_object = $wpdb->get_row($wpdb->prepare( "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d", $book_name_for_db, $chapter_num_from_query, $verse_num_from_query ));
            if ($verse_object) {
                $verse_object_for_display = $verse_object;
                $page_main_title_h1 = esc_html($verse_object->book . ' ' . $verse_object->chapter . ':' . $verse_object->verse);
                $verse_text_snippet = wp_trim_words(strip_tags($verse_object->text), 10, '...');
                $page_title_for_tab = $page_main_title_h1 . ' - ' . $verse_text_snippet;
                $meta_description = esc_attr(strip_tags($verse_object->text));
                $canonical_url = esc_url(home_url($base_bible_page_url . $book_slug_from_query . '/' . $chapter_num_from_query . '/' . $verse_num_from_query . '/'));
            } else { 
                add_filter('the_content', function($content) use ($book_name_for_db, $chapter_num_from_query, $verse_num_from_query) {
                    return '<div class="bible-content-area bible-error-message"><p>' . sprintf(esc_html__('الآية %s %s:%s غير موجودة.', 'my-bible-plugin'), esc_html($book_name_for_db), esc_html($chapter_num_from_query), esc_html($verse_num_from_query)) . '</p></div>';
                });
                return get_page_template();
            }
        } else { // أصحاح كامل
            $table_name_local = $wpdb->prefix . 'bible_verses';
            $first_verse_text = $wpdb->get_var($wpdb->prepare( "SELECT text FROM $table_name_local WHERE book = %s AND chapter = %d ORDER BY verse ASC LIMIT 1", $book_name_for_db, $chapter_num_from_query ));
            if ($wpdb->last_error) { my_bible_log_error("DB error fetching first verse for meta: " . $wpdb->last_error); }
            
            $page_title_for_tab = $page_main_title_h1;
            if ($first_verse_text) {
                $page_title_for_tab .= ' - ' . wp_trim_words(strip_tags($first_verse_text), 7, '...');
            }
            $meta_description = $first_verse_text ? esc_attr(wp_trim_words(strip_tags($first_verse_text), 30, '...')) : sprintf(esc_html__('اقرأ %s الأصحاح %s كاملاً من الكتاب المقدس.', 'my-bible-plugin'), esc_html($book_name_for_db), esc_html($chapter_num_from_query));
            $canonical_url = esc_url(home_url($base_bible_page_url . $book_slug_from_query . '/' . $chapter_num_from_query . '/'));
        }

        add_filter('pre_get_document_title', function() use ($page_title_for_tab) { return $page_title_for_tab . ' - ' . get_bloginfo('name'); }, 999);
        add_action('wp_head', function() use ($meta_description, $canonical_url) { 
            if (!empty($meta_description)) {
                echo '<meta name="description" content="' . $meta_description . '">' . "\n"; 
            }
            if (!empty($canonical_url)) { 
                echo '<link rel="canonical" href="' . $canonical_url . '" />' . "\n"; 
            }
        }, 1);

        add_filter('the_content', function($content) use ($book_name_for_db, $chapter_num_from_query, $verse_num_from_query, $page_main_title_h1, $verse_object_for_display, $base_bible_page_url) {
            global $wpdb; 
            if (!empty($verse_num_from_query) && $verse_object_for_display) { 
                $book_slug_for_url = my_bible_create_book_slug($verse_object_for_display->book);
                $chapter_url = esc_url(home_url($base_bible_page_url . $book_slug_for_url . '/' . $verse_object_for_display->chapter));
                $verse_url = esc_url(home_url($base_bible_page_url . $book_slug_for_url . '/' . $verse_object_for_display->chapter . '/' . $verse_object_for_display->verse));
                
                $page_content = '<div class="bible-single-verse-container bible-content-area">';
                $page_content .= '<h1 id="bible-main-page-title">' . $page_main_title_h1 . '</h1>';
                $page_content .= my_bible_get_controls_html('single_verse', $verse_object_for_display, $page_main_title_h1);
                $page_content .= '<div class="verse-text-container">';
                $page_content .= "<p class='verse-text' data-original-text='" . esc_attr($verse_object_for_display->text) . "' data-verse-url='" . esc_url($verse_url) . "'>";
                // *** MODIFIED HERE ***
                $page_content .= "<span class='text-content'>" . link_bible_terms($verse_object_for_display->text) . "</span></p>";
                $page_content .= '</div>';
                $page_content .= '<div id="verse-image-container" style="margin-top: 20px;"></div>';
                $page_content .= '<div class="chapter-navigation single-verse-nav">';
                $page_content .= '<a href="' . esc_url($chapter_url) . '" class="ajax-nav-link" data-book="'.esc_attr($verse_object_for_display->book).'" data-chapter="'.esc_attr($verse_object_for_display->chapter).'"><i class="fas fa-book-open"></i> ' . sprintf(esc_html__('العودة إلى الأصحاح الكامل (%s %s)', 'my-bible-plugin'), esc_html($verse_object_for_display->book), esc_html($verse_object_for_display->chapter)) . '</a>';
                $page_content .= '</div></div>';
                return $page_content;
            } else { 
                $shortcode_output = '<div class="bible-chapter-container bible-content-area">';
                $shortcode_output .= '<h1 id="bible-main-page-title">' . $page_main_title_h1 . '</h1>';
                $current_testament_for_shortcode = $wpdb->get_var($wpdb->prepare( "SELECT testament FROM " . $wpdb->prefix . "bible_verses WHERE book = %s LIMIT 1", $book_name_for_db ));
                if ($wpdb->last_error) { my_bible_log_error("DB error fetching testament for shortcode attr: " . $wpdb->last_error); }
                $testament_attr = '';
                if ($current_testament_for_shortcode) {
                    $testament_attr = ' testament="' . esc_attr($current_testament_for_shortcode) . '"';
                }
                $shortcode_output .= do_shortcode('[bible_content book="' . esc_attr($book_name_for_db) . '" chapter="' . esc_attr($chapter_num_from_query) . '"' . $testament_attr . ']');
                $shortcode_output .= '</div>';
                return $shortcode_output;
            }
        });
        return get_page_template();
    }
    elseif (is_page('bible') && empty($book_slug_from_query) && empty($chapter_num_from_query) && empty($verse_num_from_query)) {
        add_filter('the_content', function($content){
            $default_content = '<div class="bible-default-view bible-content-area">';
            $default_content .= do_shortcode('[bible_content]'); 
            $default_content .= '</div>';
            return $default_content;
        });
        return get_page_template();
    }

    return $template;
}
add_filter('template_include', 'my_bible_custom_template_redirect', 99);

function my_bible_read_page_meta_setup() {
    if (is_page('bible_read')) { 
        $book_slug = get_query_var('book');
        $chapter_num = get_query_var('chapter');
        $page_title_for_tab = esc_html__('قراءة الكتاب المقدس', 'my-bible-plugin');
        $meta_description = esc_attr__('اقرأ الكتاب المقدس، اختر السفر والأصحاح، أو ابحث عن كلمة معينة.', 'my-bible-plugin');

        if (!empty($book_slug) && !empty($chapter_num) && function_exists('my_bible_get_book_name_from_slug')) {
            $book_name = my_bible_get_book_name_from_slug($book_slug);
            if ($book_name) {
                global $wpdb;
                $first_verse = $wpdb->get_var($wpdb->prepare("SELECT text FROM {$wpdb->prefix}bible_verses WHERE book = %s AND chapter = %d ORDER BY verse ASC LIMIT 1", $book_name, $chapter_num));
                
                $page_title_for_tab = esc_html($book_name . ' ' . $chapter_num);
                if ($first_verse) {
                    $page_title_for_tab .= ' - ' . wp_trim_words(strip_tags($first_verse), 7, '...');
                }
                $meta_description = $first_verse ? esc_attr(wp_trim_words(strip_tags($first_verse), 30, '...')) : sprintf(esc_html__('اقرأ الكتاب المقدس، %s الأصحاح %d.', 'my-bible-plugin'), esc_html($book_name), esc_html($chapter_num));
            }
        }
        
        $GLOBALS['my_bible_dynamic_title'] = $page_title_for_tab;
        $GLOBALS['my_bible_dynamic_description'] = $meta_description;

        add_filter('pre_get_document_title', function($title) {
            if (!empty($GLOBALS['my_bible_dynamic_title'])) {
                return $GLOBALS['my_bible_dynamic_title'] . ' - ' . get_bloginfo('name');
            }
            return $title;
        }, 999);

        add_action('wp_head', function() {
            if (!empty($GLOBALS['my_bible_dynamic_description'])) {
                echo '<meta name="description" content="' . $GLOBALS['my_bible_dynamic_description'] . '">' . "\n";
            }
        }, 1);
    }
}
add_action('wp', 'my_bible_read_page_meta_setup');
?>
