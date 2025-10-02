<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// دالة مساعدة لإرجاع أخطاء AJAX بشكل موحد
function my_bible_ajax_error_response($message, $status_code = 400, $error_code_str = 'ajax_error') {
    wp_send_json_error(['message' => $message, 'code' => $error_code_str], $status_code);
}

// جلب الأصحاحات لسفر معين
function my_bible_get_chapters_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
    if (!isset($_POST['book']) || empty(trim($_POST['book']))) {
        my_bible_ajax_error_response(__('اسم السفر مطلوب.', 'my-bible-plugin'), 400);
        return;
    }
    $book_name_input = sanitize_text_field(trim($_POST['book']));

    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $chapters = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC",
        $book_name_input
    ));
    if ($wpdb->last_error) {
        if (function_exists('my_bible_log_error')) {
            my_bible_log_error("AJAX Get Chapters DB Error for book '{$book_name_input}': " . $wpdb->last_error, 'AJAX');
        }
        my_bible_ajax_error_response(__('حدث خطأ أثناء جلب الأصحاحات.', 'my-bible-plugin'), 500, 'db_error_chapters');
        return;
    }
    wp_send_json_success(is_array($chapters) ? $chapters : array()); 
}
add_action('wp_ajax_bible_get_chapters', 'my_bible_get_chapters_ajax');
add_action('wp_ajax_nopriv_bible_get_chapters', 'my_bible_get_chapters_ajax');


// دالة AJAX لجلب الأسفار بناءً على فلتر العهد
function my_bible_get_books_by_testament_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
    
    $testament_value_from_js = isset($_POST['testament']) ? sanitize_text_field($_POST['testament']) : 'all';

    if (!function_exists('my_bible_get_book_order_from_db')) {
        my_bible_ajax_error_response(__('الدالة المساعدة للأسفار غير موجودة (AJAX).', 'my-bible-plugin'), 500, 'helper_function_missing');
        return;
    }
    $books_for_testament = my_bible_get_book_order_from_db($testament_value_from_js);

    if (is_wp_error($books_for_testament)) { 
        my_bible_ajax_error_response($books_for_testament->get_error_message(), 500, 'get_book_order_error');
        return;
    }
    
    wp_send_json_success(is_array($books_for_testament) ? $books_for_testament : array());
}
add_action('wp_ajax_bible_get_books_by_testament', 'my_bible_get_books_by_testament_ajax');
add_action('wp_ajax_nopriv_bible_get_books_by_testament', 'my_bible_get_books_by_testament_ajax');


// جلب الآيات لأصحاح معين
function my_bible_get_verses_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
    if (!isset($_POST['book']) || empty(trim($_POST['book'])) || !isset($_POST['chapter']) || empty(trim($_POST['chapter']))) {
        my_bible_ajax_error_response(__('اسم السفر ورقم الأصحاح مطلوبان.', 'my-bible-plugin'), 400, 'missing_book_chapter');
        return;
    }
    $book_name_input = sanitize_text_field(trim($_POST['book']));
    $chapter_number = intval(trim($_POST['chapter'])); 

    if ($chapter_number <= 0) {
        my_bible_ajax_error_response(__('رقم الأصحاح غير صالح.', 'my-bible-plugin'), 400, 'invalid_chapter_number');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $verses = $wpdb->get_results($wpdb->prepare(
        "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC",
        $book_name_input, $chapter_number
    ));
    if ($wpdb->last_error) {
        my_bible_ajax_error_response(__('حدث خطأ أثناء جلب الآيات.', 'my-bible-plugin'), 500, 'db_error_verses');
        return;
    }

    if (empty($verses)) {
        my_bible_ajax_error_response(sprintf(__('لم يتم العثور على آيات للسفر "%1$s" الأصحاح "%2$d".', 'my-bible-plugin'), esc_html($book_name_input), $chapter_number), 404, 'no_verses_found');
        return;
    }

    if (!function_exists('my_bible_get_controls_html') || !function_exists('my_bible_create_book_slug') || !function_exists('link_bible_terms')) {
         my_bible_ajax_error_response(__('الدوال المساعدة للعرض غير موجودة (AJAX).', 'my-bible-plugin'), 500, 'helper_function_missing_display');
        return;
    }

    $controls_html = my_bible_get_controls_html('content'); 
    $output = $controls_html; 

    $output .= '<div id="verses-content" class="verses-text-container">';
    $first_verse_text_for_meta = ''; 
    $base_bible_page_uri = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');

    foreach ($verses as $key => $verse_obj) {
        if ($key === 0) { 
            $first_verse_text_for_meta = strip_tags($verse_obj->text); 
        }
        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
        $book_slug_for_url = my_bible_create_book_slug($verse_obj->book);
        $verse_url = esc_url(home_url($base_bible_page_uri . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
        
        $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
        // *** MODIFIED HERE ***
        $output .= "<span class='text-content'>" . link_bible_terms($verse_obj->text) . "</span> ";
        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a>";
        $output .= "</p>";
    }
    $output .= '</div>'; 

    $all_chapters_for_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $book_name_input));
    $current_chapter_index = array_search((int)$chapter_number, array_map('intval', $all_chapters_for_book), true); 
    $book_slug_for_nav = my_bible_create_book_slug($book_name_input);

    $output .= '<div class="chapter-navigation">';
    if ($current_chapter_index !== false && $current_chapter_index > 0) {
        $prev_chapter_num = $all_chapters_for_book[$current_chapter_index - 1];
        $prev_url = home_url($base_bible_page_uri . $book_slug_for_nav . '/' . $prev_chapter_num . '/');
        $output .= '<a href="' . esc_url($prev_url) . '" class="prev-chapter-link ajax-nav-link" data-book="' . esc_attr($book_name_input) . '" data-chapter="' . esc_attr($prev_chapter_num) . '"><i class="fas fa-arrow-right"></i> ' . sprintf(esc_html__('الأصحاح السابق (%s)', 'my-bible-plugin'), $prev_chapter_num) . '</a>';
    }
    if ($current_chapter_index !== false && $current_chapter_index < (count($all_chapters_for_book) - 1)) {
        $next_chapter_num = $all_chapters_for_book[$current_chapter_index + 1];
        $next_url = home_url($base_bible_page_uri . $book_slug_for_nav . '/' . $next_chapter_num . '/');
        $output .= '<a href="' . esc_url($next_url) . '" class="next-chapter-link ajax-nav-link" data-book="' . esc_attr($book_name_input) . '" data-chapter="' . esc_attr($next_chapter_num) . '"><i class="fas fa-arrow-left"></i> ' . sprintf(esc_html__('الأصحاح التالي (%s)', 'my-bible-plugin'), $next_chapter_num) . '</a>';
    }
    $output .= '</div>'; 

    $page_title_for_ajax = esc_html($book_name_input . ' ' . $chapter_number);
    if (!empty($first_verse_text_for_meta)) {
        $page_title_for_ajax .= ' - ' . wp_trim_words($first_verse_text_for_meta, 7, '...');
    }
    $meta_description_for_ajax = !empty($first_verse_text_for_meta) ? esc_attr(wp_trim_words($first_verse_text_for_meta, 30, '...')) : sprintf(esc_html__('اقرأ الكتاب المقدس، %s الأصحاح %d.', 'my-bible-plugin'), esc_html($book_name_input), $chapter_number);

    $response_data = array(
        'html' => $output,
        'title' => $page_title_for_ajax, 
        'description' => $meta_description_for_ajax,
        'book' => $book_name_input,
        'chapter' => $chapter_number 
    );
    wp_send_json_success($response_data);
}
add_action('wp_ajax_bible_get_verses', 'my_bible_get_verses_ajax');
add_action('wp_ajax_nopriv_bible_get_verses', 'my_bible_get_verses_ajax');
?>
