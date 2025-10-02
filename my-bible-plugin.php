<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع بحث متقدم، قاموس مصطلحات، فلتر العهد، شواهد، تنقل محسن، دعم الوضع الليلي، قراءة صوتية، إنشاء صور، فهرس للأسفار، وخريطة موقع مخصصة.
Version: 2.4.0
Author: اسمك (تم التحديث بواسطة Gemini)
Text Domain: my-bible-plugin
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

define('MY_BIBLE_PLUGIN_VERSION', '2.4.0');
define('MY_BIBLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_BIBLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- تضمين ملف الدوال المساعدة أولاً ---
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php')) {
    require_once MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>My Bible Plugin Error: The helpers.php file is missing.</p></div>';
    });
}

if (!function_exists('my_bible_log_error')) {
    function my_bible_log_error($message, $context = '') {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[My Bible Plugin] ' . ($context ? '[' . $context . '] ' : '') . $message);
        }
    }
}

function my_bible_enqueue_scripts() {
    try {
        wp_enqueue_script('jquery');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
        wp_enqueue_style('my-bible-styles', MY_BIBLE_PLUGIN_URL . 'assets/css/bible-styles.css', array(), MY_BIBLE_PLUGIN_VERSION);
        wp_enqueue_script('my-bible-frontend', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-frontend.js', array('jquery'), MY_BIBLE_PLUGIN_VERSION, true);

        $options = get_option('my_bible_options');
        $default_dark_mode = isset($options['default_dark_mode']) && $options['default_dark_mode'] === '1';
        $default_testament_view_db = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';

        global $wpdb;
        $testament_values_from_db = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC");
        if ($wpdb->last_error) { my_bible_log_error($wpdb->last_error, 'DB Error loading testaments'); $testament_values_from_db = array(); }

        $testaments_for_js = array('all' => __('الكل', 'my-bible-plugin'));
        if ($testament_values_from_db) {
            foreach ($testament_values_from_db as $test_val) {
                $testaments_for_js[$test_val] = esc_html($test_val);
            }
        }

        $bible_page_for_url = get_page_by_path('bible');
        $base_url_path = 'bible'; 
        if ($bible_page_for_url instanceof WP_Post) {
            $base_url_path = get_page_uri($bible_page_for_url->ID);
        }
        
        $image_fonts_data_php = array(
            'noto_naskh_arabic' => array('label' => __('خط نسخ (افتراضي)', 'my-bible-plugin'), 'family' => '"Noto Naskh Arabic", Arial, Tahoma, sans-serif'),
            'amiri' => array('label' => __('خط أميري', 'my-bible-plugin'), 'family' => 'Amiri, Georgia, serif'),
            'tahoma' => array('label' => __('خط تاهوما', 'my-bible-plugin'), 'family' => 'Tahoma, Geneva, sans-serif'),
            'arial' => array('label' => __('خط آريال', 'my-bible-plugin'), 'family' => 'Arial, Helvetica, sans-serif'),
            'times_new_roman' => array('label' => __('خط تايمز نيو رومان', 'my-bible-plugin'), 'family' => '"Times New Roman", Times, serif')
        );
        $image_backgrounds_data_php = array(
            'gradient_purple_blue' => array('type' => 'gradient', 'colors' => array('#4B0082', '#00008B', '#2F4F4F'), 'label' => __('تدرج بنفسجي-أزرق', 'my-bible-plugin'), 'textColor' => '#FFFFFF'),
            'gradient_blue_green' => array('type' => 'gradient', 'colors' => array('#007bff', '#28a745', '#17a2b8'), 'label' => __('تدرج أزرق-أخضر', 'my-bible-plugin'), 'textColor' => '#FFFFFF' ),
            'solid_dark_grey' => array('type' => 'solid', 'color' => '#343a40', 'label' => __('رمادي داكن ثابت', 'my-bible-plugin'), 'textColor' => '#FFFFFF'),
            'solid_light_beige' => array('type' => 'solid', 'color' => '#f5f5dc', 'label' => __('بيج فاتح ثابت', 'my-bible-plugin'), 'textColor' => '#222222' ),
        );

        $frontend_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bible_ajax_nonce'),
            'base_url' => home_url(trailingslashit($base_url_path)),
            'plugin_url' => MY_BIBLE_PLUGIN_URL,
            'site_name' => get_bloginfo('name'),
            'default_dark_mode' => $default_dark_mode,
            'default_testament_view' => $default_testament_view_db,
            'testaments' => $testaments_for_js,
            'image_fonts_data' => $image_fonts_data_php,
            'image_backgrounds_data' => $image_backgrounds_data_php,
            'image_generator' => array(
                'generating_image' => __('جارٍ إنشاء الصورة...', 'my-bible-plugin'),
                'download_image' => __('تحميل الصورة', 'my-bible-plugin'),
                'website_credit' => get_bloginfo('name'),
            ),
            // *** NEW STRINGS ADDED HERE ***
            'localized_strings' => array(
                'show_tashkeel_label' => __('إظهار التشكيل', 'my-bible-plugin'),
                'hide_tashkeel_label' => __('إلغاء التشكيل', 'my-bible-plugin'),
                 'no_terms_found' => __('لم يتم العثور على كلمات لها معانٍ في هذا الأصحاح.', 'my-bible-plugin'),
            )
        );

        wp_localize_script('my-bible-frontend', 'bibleFrontend', $frontend_data);
    } catch (Exception $e) {
        my_bible_log_error($e->getMessage(), 'Error in my_bible_enqueue_scripts');
    }
}
add_action('wp_enqueue_scripts', 'my_bible_enqueue_scripts');

if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/templates.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/templates.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/sitemap.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/sitemap.php'; }

function my_bible_create_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    $table_name_verses = $wpdb->prefix . 'bible_verses';
    $sql_verses = "CREATE TABLE $table_name_verses ( id mediumint(9) NOT NULL AUTO_INCREMENT, testament varchar(50) NOT NULL, book varchar(100) NOT NULL, chapter smallint(5) NOT NULL, verse smallint(5) NOT NULL, text text NOT NULL, translation_code varchar(10) DEFAULT 'AVD' NOT NULL, book_id smallint(5) DEFAULT 0 NOT NULL, PRIMARY KEY  (id), INDEX book_chapter_verse (book, chapter, verse) ) $charset_collate;";
    dbDelta($sql_verses);

    $table_name_dictionary = $wpdb->prefix . 'my_bible_dictionary';
    $sql_dictionary = "CREATE TABLE $table_name_dictionary ( id mediumint(9) NOT NULL AUTO_INCREMENT, term varchar(255) NOT NULL, definition text NOT NULL, PRIMARY KEY  (id), UNIQUE KEY term (term) ) $charset_collate;";
    dbDelta($sql_dictionary);
}
register_activation_hook(__FILE__, 'my_bible_create_tables');

function my_bible_create_pages() { 
    if (!get_page_by_path('bible')) {
        wp_insert_post([ 'post_title' => __('الكتاب المقدس', 'my-bible-plugin'), 'post_name' => 'bible', 'post_content' => '[bible_content]', 'post_status' => 'publish', 'post_type' => 'page' ]);
    }
}
register_activation_hook(__FILE__, 'my_bible_create_pages');

function my_bible_deactivation() { 
    flush_rewrite_rules(); 
}
register_deactivation_hook(__FILE__, 'my_bible_deactivation');

function my_bible_load_textdomain() { 
    load_plugin_textdomain('my-bible-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages'); 
}
add_action('plugins_loaded', 'my_bible_load_textdomain');

/**
 * Adds the modal HTML to the footer of the site.
 */
function my_bible_add_footer_modals() {
    // Modal for single term definition
    echo '<!-- Bible Dictionary Definition Modal -->';
    echo '<div id="definition-modal" class="bible-modal-overlay">';
    echo '  <div class="bible-modal-content">';
    echo '      <h3 id="modal-term" class="bible-modal-term"></h3>';
    echo '      <div id="modal-definition" class="bible-modal-definition"></div>';
    echo '      <button id="close-modal" class="bible-modal-close-button">' . esc_html__('إغلاق', 'my-bible-plugin') . '</button>';
    echo '  </div>';
    echo '</div>';

    // *** NEW MODAL FOR CHAPTER TERMS LIST ***
    echo '<!-- Chapter Terms List Modal -->';
    echo '<div id="chapter-terms-modal" class="bible-modal-overlay">';
    echo '  <div class="bible-modal-content">';
    echo '      <h3 class="bible-modal-term">' . esc_html__('معاني الكلمات في الأصحاح', 'my-bible-plugin') . '</h3>';
    echo '      <div id="chapter-terms-list" class="bible-modal-definition"></div>';
    echo '      <button id="close-chapter-terms-modal" class="bible-modal-close-button">' . esc_html__('إغلاق', 'my-bible-plugin') . '</button>';
    echo '  </div>';
    echo '</div>';
}
add_action('wp_footer', 'my_bible_add_footer_modals');


// --- Admin Settings ---
function my_bible_settings_menu() { 
    add_options_page( __('إعدادات إضافة الكتاب المقدس', 'my-bible-plugin'), __('الكتاب المقدس', 'my-bible-plugin'), 'manage_options', 'my-bible-settings', 'my_bible_settings_page_content' ); 
}
add_action('admin_menu', 'my_bible_settings_menu');

function my_bible_register_settings() { 
    register_setting('my_bible_options_group', 'my_bible_options', 'my_bible_options_sanitize');
    add_settings_section('my_bible_general_settings_section', __('الإعدادات العامة', 'my-bible-plugin'), 'my_bible_general_settings_section_callback', 'my-bible-settings');
    add_settings_field('bible_random_book', __('تحديد سفر للآيات العشوائية واليومية', 'my-bible-plugin'), 'my_bible_random_book_field_callback', 'my-bible-settings', 'my_bible_general_settings_section');
    add_settings_field('default_dark_mode', __('الوضع الليلي الافتراضي', 'my-bible-plugin'), 'my_bible_default_dark_mode_field_callback', 'my-bible-settings', 'my_bible_general_settings_section');
    add_settings_field('default_testament_view_db', __('عرض العهد الافتراضي', 'my-bible-plugin'), 'my_bible_default_testament_view_db_field_callback', 'my-bible-settings', 'my_bible_general_settings_section');
}
add_action('admin_init', 'my_bible_register_settings');

function my_bible_general_settings_section_callback() { 
    echo '<p>' . esc_html__('اختر الإعدادات العامة لإضافة الكتاب المقدس.', 'my-bible-plugin') . '</p>'; 
}

function my_bible_random_book_field_callback() { 
    global $wpdb; 
    $options = get_option('my_bible_options'); 
    $selected_book = isset($options['bible_random_book']) ? $options['bible_random_book'] : ''; 
    $table_name = $wpdb->prefix . 'bible_verses'; 
    $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name ORDER BY book ASC");
    if ($wpdb->last_error) { my_bible_log_error($wpdb->last_error, 'Settings - loading random books'); echo '<p>' . esc_html__('خطأ في جلب قائمة الأسفار.', 'my-bible-plugin') . '</p>'; return; } 
    if (empty($books)) { echo '<p>' . esc_html__('لم يتم العثور على أسفار في قاعدة البيانات.', 'my-bible-plugin') . '</p>'; return; } 
    echo '<select id="bible_random_book_select" name="my_bible_options[bible_random_book]">'; 
    echo '<option value="">' . esc_html__('كل الأسفار', 'my-bible-plugin') . '</option>'; 
    foreach ($books as $book) { 
        echo '<option value="' . esc_attr($book) . '" ' . selected($selected_book, $book, false) . '>' . esc_html($book) . '</option>'; 
    } 
    echo '</select>'; 
    echo '<p class="description">' . esc_html__('اختر سفراً للآيات العشوائية واليومية.', 'my-bible-plugin') . '</p>'; 
}

function my_bible_default_dark_mode_field_callback() { 
    $options = get_option('my_bible_options');
    $checked = isset($options['default_dark_mode']) && $options['default_dark_mode'] === '1' ? 'checked' : '';
    echo '<input type="checkbox" id="default_dark_mode" name="my_bible_options[default_dark_mode]" value="1" ' . $checked . '>';
    echo '<label for="default_dark_mode">' . esc_html__('تفعيل الوضع الليلي كخيار افتراضي للزوار الجدد.', 'my-bible-plugin') . '</label>';
}

function my_bible_default_testament_view_db_field_callback() { 
    global $wpdb;
    $options = get_option('my_bible_options');
    $selected_testament = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';
    $testaments = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC");
    echo '<select id="default_testament_view_db" name="my_bible_options[default_testament_view_db]">';
    echo '<option value="all" ' . selected('all', $selected_testament, false) . '>' . esc_html__('الكل', 'my-bible-plugin') . '</option>';
    if ($testaments) {
        foreach ($testaments as $testament) {
            echo '<option value="' . esc_attr($testament) . '" ' . selected($testament, $selected_testament, false) . '>' . esc_html($testament) . '</option>';
        }
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('اختر العهد الذي يظهر افتراضيًا عند تحميل صفحة الكتاب المقدس.', 'my-bible-plugin') . '</p>';
}

function my_bible_options_sanitize($input) { 
    $sanitized_input = [];
    if (isset($input['bible_random_book'])) {
        $sanitized_input['bible_random_book'] = sanitize_text_field($input['bible_random_book']);
    }
    if (isset($input['default_dark_mode'])) {
        $sanitized_input['default_dark_mode'] = '1';
    } else {
        $sanitized_input['default_dark_mode'] = '0';
    }
     if (isset($input['default_testament_view_db'])) {
        $sanitized_input['default_testament_view_db'] = sanitize_text_field($input['default_testament_view_db']);
    }
    return $sanitized_input;
}

function my_bible_settings_page_content() { 
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('my_bible_options_group');
            do_settings_sections('my-bible-settings');
            submit_button(__('حفظ التغييرات', 'my-bible-plugin'));
            ?>
        </form>
    </div>
    <?php
}
add_filter('widget_text', 'do_shortcode');
?>
