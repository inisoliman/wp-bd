<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * تحسين معالجة الأخطاء وإضافة رسائل واضحة
 * Enhanced error handling and clear error messages
 */

/**
 * دالة محسنة لتسجيل الأخطاء مع مزيد من التفاصيل
 */
function my_bible_enhanced_log_error($message, $context = '', $severity = 'error', $additional_data = array()) {
    // التحقق من تفعيل السجلات
    if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }
    
    // تحضير البيانات الأساسية
    $log_entry = array(
        'timestamp' => current_time('Y-m-d H:i:s'),
        'severity' => $severity,
        'context' => $context,
        'message' => $message,
        'user_id' => get_current_user_id(),
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    );
    
    // إضافة البيانات الإضافية
    if (!empty($additional_data)) {
        $log_entry['additional_data'] = $additional_data;
    }
    
    // تنسيق الرسالة
    $formatted_message = sprintf(
        '[%s] [%s] [%s] %s - User: %d, URL: %s',
        $log_entry['timestamp'],
        strtoupper($severity),
        $context ? $context : 'GENERAL',
        $message,
        $log_entry['user_id'],
        $log_entry['url']
    );
    
    // إضافة البيانات الإضافية إذا كانت موجودة
    if (!empty($additional_data)) {
        $formatted_message .= ' - Data: ' . wp_json_encode($additional_data);
    }
    
    // تسجيل الخطأ
    error_log('[My Bible Plugin] ' . $formatted_message);
    
    // حفظ في قاعدة البيانات للأخطاء المهمة
    if (in_array($severity, array('critical', 'error'))) {
        my_bible_store_error_in_db($log_entry);
    }
}

/**
 * حفظ الأخطاء المهمة في قاعدة البيانات
 */
function my_bible_store_error_in_db($error_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'my_bible_error_log';
    
    // إنشاء الجدول إذا لم يكن موجوداً
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        severity varchar(20) NOT NULL,
        context varchar(100) NOT NULL,
        message text NOT NULL,
        user_id bigint(20) DEFAULT 0,
        url text,
        additional_data longtext,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_severity (severity),
        INDEX idx_context (context)
    ) {$wpdb->get_charset_collate()}");
    
    // إدراج السجل
    $wpdb->insert(
        $table_name,
        array(
            'timestamp' => $error_data['timestamp'],
            'severity' => $error_data['severity'],
            'context' => $error_data['context'],
            'message' => $error_data['message'],
            'user_id' => $error_data['user_id'],
            'url' => $error_data['url'],
            'additional_data' => wp_json_encode($error_data)
        ),
        array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );
}

/**
 * دالة محسنة لمعالجة أخطاء AJAX مع رسائل واضحة
 */
function my_bible_enhanced_ajax_error_response($message, $status_code = 400, $error_code_str = 'ajax_error', $additional_data = array()) {
    // تسجيل الخطأ
    my_bible_enhanced_log_error($message, 'AJAX_ERROR', 'error', array(
        'error_code' => $error_code_str,
        'status_code' => $status_code,
        'additional_data' => $additional_data,
        'post_data' => $_POST
    ));
    
    // إرجاع رسالة خطأ محسنة
    $error_response = array(
        'message' => $message,
        'code' => $error_code_str,
        'timestamp' => current_time('timestamp'),
        'debug_info' => array()
    );
    
    // إضافة معلومات للمطورين في حالة التطوير
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $error_response['debug_info'] = array(
            'file' => __FILE__,
            'function' => __FUNCTION__,
            'additional_data' => $additional_data
        );
    }
    
    wp_send_json_error($error_response, $status_code);
}

/**
 * معالج شامل لأخطاء قاعدة البيانات
 */
function my_bible_handle_db_error($wpdb_error, $query = '', $context = '') {
    if (empty($wpdb_error)) {
        return false;
    }
    
    // تحليل نوع الخطأ
    $error_type = 'unknown';
    $user_message = __('حدث خطأ في قاعدة البيانات.', 'my-bible-plugin');
    
    if (strpos($wpdb_error, 'Table') !== false && strpos($wpdb_error, "doesn't exist") !== false) {
        $error_type = 'table_missing';
        $user_message = __('جدول قاعدة البيانات المطلوب غير موجود.', 'my-bible-plugin');
    } elseif (strpos($wpdb_error, 'Duplicate entry') !== false) {
        $error_type = 'duplicate_entry';
        $user_message = __('البيانات موجودة مسبقاً.', 'my-bible-plugin');
    } elseif (strpos($wpdb_error, 'Unknown column') !== false) {
        $error_type = 'column_missing';
        $user_message = __('عمود قاعدة البيانات المطلوب غير موجود.', 'my-bible-plugin');
    } elseif (strpos($wpdb_error, 'Connection') !== false) {
        $error_type = 'connection_error';
        $user_message = __('خطأ في الاتصال بقاعدة البيانات.', 'my-bible-plugin');
    }
    
    // تسجيل الخطأ مع التفاصيل
    my_bible_enhanced_log_error(
        'Database Error: ' . $wpdb_error, 
        'DATABASE', 
        'error', 
        array(
            'error_type' => $error_type,
            'query' => $query,
            'context' => $context
        )
    );
    
    return array(
        'error_type' => $error_type,
        'user_message' => $user_message,
        'technical_message' => $wpdb_error
    );
}

/**
 * معالج أخطاء للملفات المفقودة
 */
function my_bible_handle_missing_file($file_path, $context = '') {
    $user_message = __('ملف مطلوب مفقود.', 'my-bible-plugin');
    
    my_bible_enhanced_log_error(
        "Missing file: {$file_path}",
        'FILE_MISSING',
        'warning',
        array(
            'file_path' => $file_path,
            'context' => $context
        )
    );
    
    return $user_message;
}

/**
 * معالج أخطاء للدوال المفقودة
 */
function my_bible_handle_missing_function($function_name, $context = '') {
    $user_message = __('دالة مطلوبة غير متوفرة.', 'my-bible-plugin');
    
    my_bible_enhanced_log_error(
        "Missing function: {$function_name}",
        'FUNCTION_MISSING',
        'error',
        array(
            'function_name' => $function_name,
            'context' => $context
        )
    );
    
    return $user_message;
}

/**
 * تحسين رسائل الخطأ للمستخدمين
 */
function my_bible_get_user_friendly_error($error_code, $context = '') {
    $messages = array(
        'no_data_found' => __('لم يتم العثور على البيانات المطلوبة.', 'my-bible-plugin'),
        'invalid_input' => __('البيانات المُدخلة غير صحيحة.', 'my-bible-plugin'),
        'permission_denied' => __('ليس لديك صلاحية للوصول إلى هذه البيانات.', 'my-bible-plugin'),
        'server_error' => __('حدث خطأ في الخادم. يرجى المحاولة مرة أخرى.', 'my-bible-plugin'),
        'network_error' => __('خطأ في الشبكة. تحقق من اتصال الإنترنت.', 'my-bible-plugin'),
        'timeout_error' => __('انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.', 'my-bible-plugin'),
        'maintenance_mode' => __('الموقع في وضع الصيانة. يرجى المحاولة لاحقاً.', 'my-bible-plugin')
    );
    
    return isset($messages[$error_code]) ? $messages[$error_code] : __('حدث خطأ غير متوقع.', 'my-bible-plugin');
}

/**
 * إضافة تنبيهات إدارية للأخطاء المهمة
 */
function my_bible_show_admin_error_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_bible_error_log';
    
    // التحقق من وجود أخطاء حديثة
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        $recent_errors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE severity IN ('error', 'critical') AND timestamp > %s",
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        
        if ($recent_errors > 0) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('تحذير: إضافة الكتاب المقدس', 'my-bible-plugin') . '</strong></p>';
            echo '<p>' . sprintf(
                esc_html__('تم تسجيل %d خطأ في الساعة الماضية. يرجى مراجعة سجلات الأخطاء.', 'my-bible-plugin'),
                $recent_errors
            ) . '</p>';
            echo '</div>';
        }
    }
}
add_action('admin_notices', 'my_bible_show_admin_error_notices');

/**
 * إنشاء صفحة إدارية لعرض الأخطاء
 */
function my_bible_add_error_log_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    add_submenu_page(
        'options-general.php',
        __('سجل أخطاء الكتاب المقدس', 'my-bible-plugin'),
        __('سجل الأخطاء', 'my-bible-plugin'),
        'manage_options',
        'my-bible-error-log',
        'my_bible_error_log_page_content'
    );
}
add_action('admin_menu', 'my_bible_add_error_log_admin_page');

/**
 * محتوى صفحة سجل الأخطاء
 */
function my_bible_error_log_page_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_bible_error_log';
    
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('سجل أخطاء إضافة الكتاب المقدس', 'my-bible-plugin') . '</h1>';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        echo '<p>' . esc_html__('لا توجد أخطاء مسجلة حالياً.', 'my-bible-plugin') . '</p>';
        echo '</div>';
        return;
    }
    
    // جلب الأخطاء الحديثة
    $errors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 100"
    ));
    
    if (empty($errors)) {
        echo '<p>' . esc_html__('لا توجد أخطاء مسجلة.', 'my-bible-plugin') . '</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('التاريخ', 'my-bible-plugin') . '</th>';
        echo '<th>' . esc_html__('النوع', 'my-bible-plugin') . '</th>';
        echo '<th>' . esc_html__('السياق', 'my-bible-plugin') . '</th>';
        echo '<th>' . esc_html__('الرسالة', 'my-bible-plugin') . '</th>';
        echo '<th>' . esc_html__('المستخدم', 'my-bible-plugin') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($errors as $error) {
            $severity_class = '';
            switch ($error->severity) {
                case 'critical':
                    $severity_class = 'error';
                    break;
                case 'error':
                    $severity_class = 'warning';
                    break;
                default:
                    $severity_class = 'info';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($error->timestamp) . '</td>';
            echo '<td><span class="' . esc_attr($severity_class) . '">' . esc_html(ucfirst($error->severity)) . '</span></td>';
            echo '<td>' . esc_html($error->context) . '</td>';
            echo '<td>' . esc_html(wp_trim_words($error->message, 15)) . '</td>';
            echo '<td>' . esc_html($error->user_id) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // إضافة زر لمسح السجل
        echo '<p>';
        echo '<a href="' . wp_nonce_url(admin_url('options-general.php?page=my-bible-error-log&action=clear'), 'clear_error_log') . '" class="button button-secondary" onclick="return confirm(\'' . esc_js__('هل أنت متأكد من مسح جميع السجلات؟', 'my-bible-plugin') . '\')">' . esc_html__('مسح السجل', 'my-bible-plugin') . '</a>';
        echo '</p>';
    }
    
    echo '</div>';
}

/**
 * مسح سجل الأخطاء
 */
function my_bible_handle_clear_error_log() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'clear' && wp_verify_nonce($_GET['_wpnonce'], 'clear_error_log')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'my_bible_error_log';
        $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        wp_redirect(admin_url('options-general.php?page=my-bible-error-log&cleared=1'));
        exit;
    }
}
add_action('admin_init', 'my_bible_handle_clear_error_log');

/**
 * تنظيف سجل الأخطاء تلقائياً (الاحتفاظ بآخر 30 يوم)
 */
function my_bible_cleanup_error_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_bible_error_log';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
    }
}

// تشغيل التنظيف أسبوعياً
if (!wp_next_scheduled('my_bible_cleanup_error_log')) {
    wp_schedule_event(time(), 'weekly', 'my_bible_cleanup_error_log');
}
add_action('my_bible_cleanup_error_log', 'my_bible_cleanup_error_log');

/**
 * تحديث دالة تسجيل الأخطاء القديمة لتستخدم النظام المحسن
 */
if (!function_exists('my_bible_log_error')) {
    function my_bible_log_error($message, $context = '') {
        my_bible_enhanced_log_error($message, $context, 'error');
    }
}
?>