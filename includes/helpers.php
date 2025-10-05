<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// --- الدوال المساعدة للقاموس ---

/**
 * Normalizes a string for dictionary lookup by removing diacritics and converting to lowercase.
 * This is a private helper function to ensure consistent normalization.
 * @param string $string The string to normalize.
 * @return string The normalized string.
 */
function _my_bible_normalize_for_lookup($string) {
    // Return empty string if input is not a string or is empty
    if (!is_string($string) || empty($string)) {
        return '';
    }
    // Remove all Unicode combining marks (this is the most effective way to remove diacritics).
    $normalized = preg_replace('/\p{M}/u', '', $string);
    // Convert to lowercase for case-insensitive matching.
    $normalized = mb_strtolower($normalized, 'UTF-8');
    return $normalized;
}


/**
 * Fetches all terms and definitions from the Bible dictionary.
 * Uses the normalization function to create clean keys for matching.
 * Caches the result for the duration of the page load.
 *
 * @return array An associative array mapping normalized terms to their definitions.
 */
function get_bible_dictionary_data() {
    static $dictionary_data = null;

    if ($dictionary_data === null) {
        global $wpdb;
        $dictionary_data = array();
        $table_name = $wpdb->prefix . 'my_bible_dictionary';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            $results = $wpdb->get_results("SELECT term, definition FROM {$table_name}", ARRAY_A);

            if ($results) {
                foreach ($results as $row) {
                    if (!empty($row['term'])) {
                        // Use the new helper to create a clean, normalized key.
                        $normalized_term = _my_bible_normalize_for_lookup($row['term']);
                        if (!empty($normalized_term)) {
                            // Store the original term casing along with the definition.
                            $dictionary_data[$normalized_term] = [
                                'original'   => $row['term'],
                                'definition' => $row['definition']
                            ];
                        }
                    }
                }
            }
        }
    }
    return $dictionary_data;
}

/**
 * [V4 - High Performance & Corrected Normalization] Finds and links dictionary terms in verse text.
 * Updated: Works for all users (logged in and visitors)
 *
 * @param string $verse_text The raw Bible verse text.
 * @return string The processed text with interactive HTML links for dictionary terms.
 */
function link_bible_terms($verse_text) {
    $dictionary = get_bible_dictionary_data();
    
    if (empty($dictionary) || empty(trim($verse_text))) {
        return esc_html($verse_text);
    }

    static $structured_dictionary = null;
    if ($structured_dictionary === null) {
        $structured_dictionary = [];
        // The keys of $dictionary are already normalized.
        foreach ($dictionary as $term_key => $data) {
            $word_count = count(explode(' ', $term_key));
            if (!isset($structured_dictionary[$word_count])) {
                $structured_dictionary[$word_count] = [];
            }
            $structured_dictionary[$word_count][$term_key] = $data['definition'];
        }
        krsort($structured_dictionary);
    }

    $verse_tokens = preg_split('/(\s+|[.,:;!?\(\)\[\]{}<>«»\-"\'`\r\n\t]+)/u', $verse_text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $final_html = '';
    $token_count = count($verse_tokens);
    $i = 0;

    while ($i < $token_count) {
        $current_token = $verse_tokens[$i];
        
        if (trim($current_token) === '' || preg_match('/^[.,:;!?\(\)\[\]{}<>«»\-"\'`\r\n\t]+$/u', $current_token)) {
            $final_html .= esc_html($current_token);
            $i++;
            continue;
        }
        
        $match_found = false;
        
        foreach ($structured_dictionary as $word_count => $terms) {
            if ($i + ($word_count * 2) - 1 < $token_count) {
                $phrase_tokens = array_slice($verse_tokens, $i, ($word_count * 2) - 1);
                $original_phrase = implode('', $phrase_tokens);
                
                // Normalize the phrase from the verse using the same helper function.
                $normalized_phrase = _my_bible_normalize_for_lookup($original_phrase);

                if (isset($terms[$normalized_phrase])) {
                    $definition = esc_attr($terms[$normalized_phrase]);
                    $final_html .= "<a href='javascript:void(0);' class='bible-term' data-definition='{$definition}'>" . esc_html($original_phrase) . "</a>";
                    $i += ($word_count * 2) - 1;
                    $match_found = true;
                    break; 
                }
            }
        }

        if (!$match_found) {
            $final_html .= esc_html($current_token);
            $i++;
        }
    }

    return $final_html;
}


// --- الدوال المساعدة العامة (موجودة مسبقاً) ---

if (!function_exists('my_bible_get_controls_html')) {
    function my_bible_get_controls_html($context = 'content', $verse_object = null, $verse_reference_text = '') {
        $unique_id_suffix = '-' . uniqid(); 
        if ($context === 'content' || $context === 'search') {
            $unique_id_suffix = ($context === 'search') ? '-search' : '';
        }

        $controls_html = '<div class="bible-controls-wrapper">';
        
        $controls_html .= '<div class="bible-main-controls">';
        $controls_html .= '<button class="bible-control-button" data-action="show-chapter-terms"><i class="fas fa-book-open"></i> <span class="label">' . esc_html__('معاني كلمات الأصحاح', 'my-bible-plugin') . '</span></button>';
        
        $controls_html .= '<button id="toggle-tashkeel' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="toggle-tashkeel"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="increase-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="increase-font"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="decrease-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="decrease-font"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';
        
        $dark_mode_button_id = ($context === 'content' || $context === 'search') ? 'dark-mode-toggle' : 'dark-mode-toggle' . esc_attr($unique_id_suffix);
        $controls_html .= '<button id="' . esc_attr($dark_mode_button_id) . '" class="bible-control-button dark-mode-toggle-button" data-action="dark-mode-toggle"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="read-aloud-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button read-aloud-button" data-action="read-aloud"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '</div>'; 

        $show_image_options_contexts = array('single_verse', 'random_verse', 'daily_verse');
        if (in_array($context, $show_image_options_contexts) && $verse_object && !empty($verse_reference_text)) {
            $controls_html .= '<div class="bible-image-generator-controls">';
            $controls_html .= '<button id="generate-verse-image-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="generate-image" data-verse-text="' . esc_attr($verse_object->text) . '" data-verse-reference="' . esc_attr($verse_reference_text) . '"><i class="fas fa-image"></i> <span class="label">' . esc_html__('إنشاء صورة للمشاركة', 'my-bible-plugin') . '</span></button>';
            $controls_html .= '<div class="bible-image-options-group">';
                $controls_html .= '<div class="bible-image-option">';
                $controls_html .= '<label for="bible-image-font-select' . esc_attr($unique_id_suffix) . '">' . esc_html__('الخط:', 'my-bible-plugin') . '</label>';
                $controls_html .= '<select id="bible-image-font-select' . esc_attr($unique_id_suffix) . '" class="bible-image-select">';
                $controls_html .= '<option value="">' . esc_html__('اختر الخط...', 'my-bible-plugin') . '</option>';
                $controls_html .= '</select></div>';
                $controls_html .= '<div class="bible-image-option">';
                $controls_html .= '<label for="bible-image-bg-select' . esc_attr($unique_id_suffix) . '">' . esc_html__('الخلفية:', 'my-bible-plugin') . '</label>';
                $controls_html .= '<select id="bible-image-bg-select' . esc_attr($unique_id_suffix) . '" class="bible-image-select">';
                $controls_html .= '<option value="">' . esc_html__('اختر الخلفية...', 'my-bible-plugin') . '</option>';
                $controls_html .= '</select></div>';
            $controls_html .= '</div>'; 
            $controls_html .= '</div>'; 
        }
        $controls_html .= '</div>'; 
        return $controls_html;
    }
}

if (!function_exists('my_bible_sanitize_book_name')) {
    function my_bible_sanitize_book_name($book_name) {
        if (empty($book_name)) return '';
        $book_name = (string) $book_name;
        $book_name = trim($book_name);
        $book_name = str_replace('-', ' ', $book_name);
        $book_name = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_name);
        $book_name = str_replace(array('أ', 'إ', 'آ', 'ٱ', 'أُ', 'إِ'), 'ا', $book_name);
        $book_name = str_replace(array('ى'), 'ي', $book_name);
        $book_name = preg_replace('/\s+/', ' ', $book_name);
        return trim($book_name);
    }
}

if (!function_exists('my_bible_create_book_slug')) {
    function my_bible_create_book_slug($book_name) {
        if (empty($book_name)) return '';
        $slug = my_bible_sanitize_book_name($book_name);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/[^\p{Arabic}\p{N}a-zA-Z0-9\-]+/u', '', $slug);
        return rawurlencode($slug);
    }
}

if (!function_exists('my_bible_get_defined_book_order_within_testaments')) {
    function my_bible_get_defined_book_order_within_testaments() {
        return array(
            'العهد القديم' => array( 'سفر التكوين', 'سفر الخروج', 'سفر اللاويين', 'سفر العدد', 'سفر التثنية', 'سفر يشوع', 'سفر القضاة', 'سفر راعوث', 'سفر صموئيل الأول', 'سفر صموئيل الثاني', 'سفر الملوك الأول', 'سفر الملوك الثاني', 'سفر أخبار الأيام الأول', 'سفر أخبار الأيام الثاني', 'سفر عزرا', 'سفر نحميا', 'سفر أستير', 'سفر أيوب', 'سفر المزامير', 'سفر الأمثال', 'سفر الجامعة', 'سفر نشيد الأنشاد', 'سفر إشعياء', 'سفر إرميا', 'سفر مراثي إرميا', 'سفر حزقيال', 'سفر دانيال', 'سفر هوشع', 'سفر يوئيل', 'سفر عاموس', 'سفر عوبديا', 'سفر يونان', 'سفر ميخا', 'سفر ناحوم', 'سفر حبقوق', 'سفر صفنيا', 'سفر حجي', 'سفر زكريا', 'سفر ملاخي' ),
            'العهد الجديد' => array( 'إنجيل متى', 'إنجيل مرقس', 'إنجيل لوقا', 'إنجيل يوحنا', 'سفر أعمال الرسل', 'رسالة بولس الرسول إلى أهل رومية', 'رسالة بولس الرسول الأولى إلى أهل كورنثوس', 'رسالة بولس الرسول الثانية إلى أهل كورنثوس', 'رسالة بولس الرسول إلى أهل غلاطية', 'رسالة بولس الرسول إلى أهل أفسس', 'رسالة بولس الرسول إلى أهل فيلبي', 'رسالة بولس الرسول إلى أهل كولوسي', 'رسالة بولس الرسول الأولى إلى أهل تسالونيكي', 'رسالة بولس الرسول الثانية إلى أهل تسالونيكي', 'رسالة بولس الرسول الأولى إلى تيموثاوس', 'رسالة بولس الرسول الثانية إلى تيموثاوس', 'رسالة بولس الرسول إلى تيطس', 'رسالة بولس الرسول إلى فليمون', 'الرسالة إلى العبرانيين', 'رسالة يعقوب', 'رسالة بطرس الأولى', 'رسالة بطرس الثانية', 'رسالة يوحنا الأولى', 'رسالة يوحنا الثانية', 'رسالة يوحنا الثالثة', 'رسالة يهوذا', 'سفر رؤيا يوحنا اللاهوتي' )
        );
    }
}

if (!function_exists('my_bible_get_book_order_from_db')) {
    function my_bible_get_book_order_from_db($testament_value_in_db = 'all') {
        $cache_key = 'bible_book_order_' . md5(is_string($testament_value_in_db) ? $testament_value_in_db : 'serialized_array');
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) { return $cached_result; }
        global $wpdb; $table_name = $wpdb->prefix . 'bible_verses'; $where_clause = ''; $prepare_args = array();
        if ($testament_value_in_db !== 'all' && !empty($testament_value_in_db)) {
            $valid_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM {$table_name} WHERE testament != ''");
            if (!in_array($testament_value_in_db, $valid_testaments) && $testament_value_in_db !== 'all') {
                set_transient($cache_key, array(), HOUR_IN_SECONDS); return array(); 
            }
            $where_clause = "WHERE testament = %s"; $prepare_args[] = $testament_value_in_db;
        }
        $defined_order_for_current_testament = array(); $order_by_clause_parts = array();
        $all_defined_orders = my_bible_get_defined_book_order_within_testaments();
        if ($testament_value_in_db !== 'all' && isset($all_defined_orders[$testament_value_in_db])) {
            $defined_order_for_current_testament = $all_defined_orders[$testament_value_in_db];
        } elseif ($testament_value_in_db === 'all') {
            $ot_order = isset($all_defined_orders['العهد القديم']) ? $all_defined_orders['العهد القديم'] : array();
            $nt_order = isset($all_defined_orders['العهد الجديد']) ? $all_defined_orders['العهد الجديد'] : array();
            $defined_order_for_current_testament = array_merge($ot_order, $nt_order);
        }
        if (!empty($defined_order_for_current_testament)) {
            $books_in_db_for_testament_query = "SELECT DISTINCT book FROM {$table_name} {$where_clause}";
            if (!empty($prepare_args)) { $books_in_db_for_testament_query = $wpdb->prepare($books_in_db_for_testament_query, $prepare_args); }
            $books_actually_in_db_for_testament = $wpdb->get_col($books_in_db_for_testament_query);
            if ($books_actually_in_db_for_testament) {
                $final_ordered_list_from_defined = array_values(array_intersect($defined_order_for_current_testament, $books_actually_in_db_for_testament));
                $remaining_books_in_db = array_diff($books_actually_in_db_for_testament, $final_ordered_list_from_defined);
                if ($remaining_books_in_db) { sort($remaining_books_in_db); $final_ordered_list_from_defined = array_merge($final_ordered_list_from_defined, $remaining_books_in_db); }
                if (!empty($final_ordered_list_from_defined)) {
                    // إصلاح أمني: استخدام طريقة آمنة لبناء FIELD query
                    $placeholders = implode(', ', array_fill(0, count($final_ordered_list_from_defined), '%s'));
                    $order_by_clause_parts[] = $wpdb->prepare("FIELD(book, $placeholders)", ...$final_ordered_list_from_defined);
                }
            }
        }
        $order_by_clause_parts[] = "book ASC"; $order_by_sql = "ORDER BY " . implode(', ', $order_by_clause_parts);
        $sql = "SELECT DISTINCT book FROM {$table_name} {$where_clause} {$order_by_sql}";
        if(!empty($prepare_args) && !empty($where_clause)){ $sql = $wpdb->prepare($sql, $prepare_args); }
        $books = $wpdb->get_col($sql);
        if ($wpdb->last_error) { my_bible_log_error("DB Error in get_book_order_from_db: " . $wpdb->last_error . " SQL: " . $sql); set_transient($cache_key, array(), HOUR_IN_SECONDS); return array(); }
        set_transient($cache_key, $books ? $books : array(), HOUR_IN_SECONDS);
        return $books ? $books : array();
    }
}
if (!function_exists('my_bible_get_book_name_from_slug')) {
    function my_bible_get_book_name_from_slug($book_slug) {
        global $wpdb; if (empty($book_slug)) return false;
        $decoded_slug = rawurldecode($book_slug); $table_name = $wpdb->prefix . 'bible_verses';
        $book_name_try_direct = str_replace('-', ' ', $decoded_slug);
        $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_try_direct ));
        if ($db_book_name) return $db_book_name;
        $sanitized_slug_as_name = my_bible_sanitize_book_name($book_name_try_direct);
        $db_book_name_alt_query = $wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE( REPLACE(REPLACE(REPLACE(REPLACE(book, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'أُ', 'ا'), 'إِ', 'ا'), 'ى', 'ي'), 'ً', ''), 'ٌ', ''), 'ٍ', ''), 'َ', ''), 'ُ', ''), 'ِ', '') = %s LIMIT 1", $sanitized_slug_as_name );
        $db_book_name_alt = $wpdb->get_var($db_book_name_alt_query);
        if ($db_book_name_alt) return $db_book_name_alt;
        if (!preg_match('/\d/', $decoded_slug)) {
            $possible_books = $wpdb->get_results($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book LIKE %s", '%' . $wpdb->esc_like($book_name_try_direct) . '%' ));
            if (count($possible_books) === 1) { return $possible_books[0]->book; }
        }
        return false;
    }
}
if (!function_exists('my_bible_parse_reference')) {
    function my_bible_parse_reference($reference_string) {
        $reference_string = trim($reference_string);
        $parsed = array('book' => null, 'chapter' => null, 'verse' => null, 'is_reference' => false);
        if (preg_match('/^([0-9]?\s*[^\d\s]+(?:\s+[^\d\s]+)*)\s*([0-9]+)(?:[\s:.]*\s*([0-9]+))?$/u', $reference_string, $matches)) {
            $book_name_input = trim($matches[1]); $chapter_num = intval($matches[2]);
            $verse_num = isset($matches[3]) && !empty($matches[3]) ? intval($matches[3]) : null;
            global $wpdb; $table_name = $wpdb->prefix . 'bible_verses';
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_input));
            if (!$db_book_name) {
                $sanitized_input_book = my_bible_sanitize_book_name($book_name_input);
                 $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE( REPLACE(REPLACE(REPLACE(REPLACE(book, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'أُ', 'ا'), 'إِ', 'ا'), 'ى', 'ي'), 'ً', ''), 'ٌ', ''), 'ٍ', ''), 'َ', ''), 'ُ', ''), 'ِ', '') = %s", $sanitized_input_book));
            }
            if (!$db_book_name && preg_match('/^([0-9]+)\s+(.+)$/u', $book_name_input, $book_parts)) {
                $number_map_to_text = array('1' => 'الأول', '2' => 'الثاني', '3' => 'الثالث');
                $numeric_prefix_num = $book_parts[1]; $book_base_name = trim($book_parts[2]);
                $possible_books_query = $wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book LIKE %s AND book LIKE %s", '%' . $wpdb->esc_like($book_base_name) . '%', isset($number_map_to_text[$numeric_prefix_num]) ? '%' . $wpdb->esc_like($number_map_to_text[$numeric_prefix_num]) . '%' : '% %' );
                $possible_books = $wpdb->get_col($possible_books_query);
                if (count($possible_books) === 1) { $db_book_name = $possible_books[0];
                } elseif (count($possible_books) > 1) {
                    foreach ($possible_books as $possible_book) {
                        if (isset($number_map_to_text[$numeric_prefix_num]) && (strpos($possible_book, $number_map_to_text[$numeric_prefix_num] . ' ' . $book_base_name) !== false || strpos($possible_book, $book_base_name . ' ' . $number_map_to_text[$numeric_prefix_num]) !== false || strpos($possible_book, $book_base_name . $number_map_to_text[$numeric_prefix_num]) !== false )) {
                            $db_book_name = $possible_book; break;
                        }
                    }
                }
            }
            if ($db_book_name && $chapter_num > 0) {
                $parsed['book'] = $db_book_name; $parsed['chapter'] = $chapter_num;
                $parsed['verse'] = ($verse_num !== null && $verse_num > 0) ? $verse_num : null;
                $parsed['is_reference'] = true;
            }
        }
        return $parsed;
    }
}
?>
