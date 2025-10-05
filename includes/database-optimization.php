<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * تحسين أداء قاعدة البيانات وإضافة فهارس محسنة
 * Database performance optimization and enhanced indexes
 */

/**
 * تحسين الجداول الموجودة بإضافة فهارس إضافية
 */
function my_bible_optimize_existing_tables() {
    global $wpdb;
    
    $table_verses = $wpdb->prefix . 'bible_verses';
    $table_dictionary = $wpdb->prefix . 'my_bible_dictionary';
    
    // التحقق من وجود الجداول
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_verses)) !== $table_verses) {
        return;
    }
    
    // إضافة فهارس محسنة لجدول الآيات إذا لم تكن موجودة
    $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$table_verses}");
    $index_names = array();
    foreach ($existing_indexes as $index) {
        $index_names[] = $index->Key_name;
    }
    
    // إضافة فهارس جديدة إذا لم تكن موجودة
    if (!in_array('idx_testament', $index_names)) {
        $wpdb->query("ALTER TABLE {$table_verses} ADD INDEX idx_testament (testament(20))");
    }
    
    if (!in_array('idx_book_chapter', $index_names)) {
        $wpdb->query("ALTER TABLE {$table_verses} ADD INDEX idx_book_chapter (book(50), chapter)");
    }
    
    if (!in_array('idx_translation', $index_names)) {
        $wpdb->query("ALTER TABLE {$table_verses} ADD INDEX idx_translation (translation_code)");
    }
    
    if (!in_array('fulltext_search', $index_names)) {
        $wpdb->query("ALTER TABLE {$table_verses} ADD FULLTEXT INDEX fulltext_search (text)");
    }
    
    // تحسين جدول القاموس
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_dictionary)) === $table_dictionary) {
        $dict_indexes = $wpdb->get_results("SHOW INDEX FROM {$table_dictionary}");
        $dict_index_names = array();
        foreach ($dict_indexes as $index) {
            $dict_index_names[] = $index->Key_name;
        }
        
        // إضافة عمود التصنيف إذا لم يكن موجوداً
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_dictionary}");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        if (!in_array('category', $column_names)) {
            $wpdb->query("ALTER TABLE {$table_dictionary} ADD COLUMN category varchar(100) DEFAULT '' NOT NULL AFTER definition");
        }
        
        if (!in_array('created_at', $column_names)) {
            $wpdb->query("ALTER TABLE {$table_dictionary} ADD COLUMN created_at timestamp DEFAULT CURRENT_TIMESTAMP AFTER category");
        }
        
        if (!in_array('updated_at', $column_names)) {
            $wpdb->query("ALTER TABLE {$table_dictionary} ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
        
        // إضافة فهارس للقاموس
        if (!in_array('idx_category', $dict_index_names)) {
            $wpdb->query("ALTER TABLE {$table_dictionary} ADD INDEX idx_category (category(50))");
        }
        
        if (!in_array('fulltext_term_definition', $dict_index_names)) {
            $wpdb->query("ALTER TABLE {$table_dictionary} ADD FULLTEXT INDEX fulltext_term_definition (term, definition)");
        }
    }
    
    // تحسين أداء الجداول
    $wpdb->query("OPTIMIZE TABLE {$table_verses}");
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_dictionary)) === $table_dictionary) {
        $wpdb->query("OPTIMIZE TABLE {$table_dictionary}");
    }
}

/**
 * تنفيذ استعلامات محسنة للبحث
 */
function my_bible_optimized_search($search_term, $testament = 'all', $limit = 50, $offset = 0) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bible_verses';
    $search_term = sanitize_text_field($search_term);
    
    if (empty($search_term)) {
        return array();
    }
    
    // استخدام البحث النصي المحسن (FULLTEXT) إذا كان متاحاً
    $where_conditions = array();
    $prepare_args = array();
    
    // شرط البحث النصي
    $where_conditions[] = "MATCH(text) AGAINST(%s IN NATURAL LANGUAGE MODE)";
    $prepare_args[] = $search_term;
    
    // شرط العهد
    if ($testament !== 'all' && !empty($testament)) {
        $where_conditions[] = "testament = %s";
        $prepare_args[] = $testament;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "SELECT book, chapter, verse, text, testament,
            MATCH(text) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance_score
            FROM {$table_name} 
            WHERE {$where_clause}
            ORDER BY relevance_score DESC, book, chapter, verse
            LIMIT %d OFFSET %d";
    
    array_unshift($prepare_args, $search_term); // للـ relevance score
    $prepare_args[] = intval($limit);
    $prepare_args[] = intval($offset);
    
    return $wpdb->get_results($wpdb->prepare($sql, ...$prepare_args));
}

/**
 * استعلام محسن للحصول على آيات الأصحاح
 */
function my_bible_get_chapter_verses_optimized($book_name, $chapter, $translation = 'AVD') {
    global $wpdb;
    
    $cache_key = 'bible_chapter_' . md5($book_name . '_' . $chapter . '_' . $translation);
    $cached_result = wp_cache_get($cache_key, 'bible_verses');
    
    if ($cached_result !== false) {
        return $cached_result;
    }
    
    $table_name = $wpdb->prefix . 'bible_verses';
    
    $sql = "SELECT id, book, chapter, verse, text, testament 
            FROM {$table_name} 
            WHERE book = %s AND chapter = %d AND translation_code = %s 
            ORDER BY verse ASC";
    
    $verses = $wpdb->get_results($wpdb->prepare($sql, $book_name, intval($chapter), $translation));
    
    // تخزين في الذاكرة المؤقتة لمدة ساعة
    wp_cache_set($cache_key, $verses, 'bible_verses', 3600);
    
    return $verses;
}

/**
 * استعلام محسن للحصول على قائمة الأسفار
 */
function my_bible_get_books_optimized($testament = 'all') {
    global $wpdb;
    
    $cache_key = 'bible_books_' . md5($testament);
    $cached_result = wp_cache_get($cache_key, 'bible_books');
    
    if ($cached_result !== false) {
        return $cached_result;
    }
    
    $table_name = $wpdb->prefix . 'bible_verses';
    
    $where_clause = '';
    $prepare_args = array();
    
    if ($testament !== 'all' && !empty($testament)) {
        $where_clause = 'WHERE testament = %s';
        $prepare_args[] = $testament;
    }
    
    $sql = "SELECT DISTINCT book, testament, MIN(book_id) as book_order
            FROM {$table_name} 
            {$where_clause}
            GROUP BY book, testament 
            ORDER BY book_order ASC, book ASC";
    
    if (!empty($prepare_args)) {
        $books = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_args));
    } else {
        $books = $wpdb->get_results($sql);
    }
    
    // تخزين في الذاكرة المؤقتة لمدة يوم
    wp_cache_set($cache_key, $books, 'bible_books', 86400);
    
    return $books;
}

/**
 * استعلام محسن لإحصائيات قاعدة البيانات
 */
function my_bible_get_database_stats() {
    global $wpdb;
    
    $cache_key = 'bible_db_stats';
    $cached_stats = wp_cache_get($cache_key, 'bible_stats');
    
    if ($cached_stats !== false) {
        return $cached_stats;
    }
    
    $table_verses = $wpdb->prefix . 'bible_verses';
    $table_dictionary = $wpdb->prefix . 'my_bible_dictionary';
    
    $stats = array();
    
    // إحصائيات الآيات
    $stats['total_verses'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_verses}");
    $stats['total_books'] = $wpdb->get_var("SELECT COUNT(DISTINCT book) FROM {$table_verses}");
    $stats['total_chapters'] = $wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(book, '-', chapter)) FROM {$table_verses}");
    
    // إحصائيات العهود
    $testaments = $wpdb->get_results("
        SELECT testament, COUNT(*) as verse_count, COUNT(DISTINCT book) as book_count 
        FROM {$table_verses} 
        WHERE testament != '' 
        GROUP BY testament
    ");
    
    $stats['testaments'] = array();
    foreach ($testaments as $testament) {
        $stats['testaments'][$testament->testament] = array(
            'verses' => intval($testament->verse_count),
            'books' => intval($testament->book_count)
        );
    }
    
    // إحصائيات القاموس
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_dictionary)) === $table_dictionary) {
        $stats['dictionary_terms'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_dictionary}");
    } else {
        $stats['dictionary_terms'] = 0;
    }
    
    // تخزين لمدة 6 ساعات
    wp_cache_set($cache_key, $stats, 'bible_stats', 21600);
    
    return $stats;
}

/**
 * تنظيف الذاكرة المؤقتة عند تحديث البيانات
 */
function my_bible_clear_cache($type = 'all') {
    if ($type === 'all' || $type === 'verses') {
        wp_cache_flush_group('bible_verses');
        wp_cache_flush_group('bible_books');
    }
    
    if ($type === 'all' || $type === 'stats') {
        wp_cache_flush_group('bible_stats');
    }
    
    if ($type === 'all' || $type === 'dictionary') {
        wp_cache_flush_group('bible_dictionary');
    }
}

/**
 * تحسين استعلام البحث في القاموس
 */
function my_bible_search_dictionary_optimized($search_term, $limit = 20) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'my_bible_dictionary';
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        return array();
    }
    
    $search_term = sanitize_text_field($search_term);
    if (empty($search_term)) {
        return array();
    }
    
    $cache_key = 'dict_search_' . md5($search_term . '_' . $limit);
    $cached_result = wp_cache_get($cache_key, 'bible_dictionary');
    
    if ($cached_result !== false) {
        return $cached_result;
    }
    
    // استخدام البحث النصي المحسن
    $sql = "SELECT term, definition, category,
            MATCH(term, definition) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance_score
            FROM {$table_name} 
            WHERE MATCH(term, definition) AGAINST(%s IN NATURAL LANGUAGE MODE)
            ORDER BY relevance_score DESC, term ASC
            LIMIT %d";
    
    $results = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term, intval($limit)));
    
    // تخزين لمدة 30 دقيقة
    wp_cache_set($cache_key, $results, 'bible_dictionary', 1800);
    
    return $results;
}

/**
 * تشغيل تحسين قاعدة البيانات عند تفعيل الإضافة
 */
add_action('my_bible_plugin_activated', 'my_bible_optimize_existing_tables');

/**
 * تشغيل تحسين دوري لقاعدة البيانات
 */
function my_bible_schedule_db_optimization() {
    if (!wp_next_scheduled('my_bible_daily_optimization')) {
        wp_schedule_event(time(), 'daily', 'my_bible_daily_optimization');
    }
}
add_action('wp', 'my_bible_schedule_db_optimization');

/**
 * تنفيذ التحسين اليومي
 */
function my_bible_daily_optimization_task() {
    global $wpdb;
    
    $table_verses = $wpdb->prefix . 'bible_verses';
    $table_dictionary = $wpdb->prefix . 'my_bible_dictionary';
    
    // تحسين الجداول
    $wpdb->query("OPTIMIZE TABLE {$table_verses}");
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_dictionary)) === $table_dictionary) {
        $wpdb->query("OPTIMIZE TABLE {$table_dictionary}");
    }
    
    // تنظيف الذاكرة المؤقتة القديمة
    wp_cache_flush();
    
    // تسجيل في السجل
    if (function_exists('my_bible_log_error')) {
        my_bible_log_error('Database optimization completed successfully', 'DB_OPTIMIZATION');
    }
}
add_action('my_bible_daily_optimization', 'my_bible_daily_optimization_task');

/**
 * إلغاء المهام المجدولة عند إلغاء تفعيل الإضافة
 */
function my_bible_clear_scheduled_events() {
    wp_clear_scheduled_hook('my_bible_daily_optimization');
}
register_deactivation_hook(__FILE__, 'my_bible_clear_scheduled_events');
?>