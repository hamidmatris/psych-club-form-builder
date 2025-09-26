<?php
/**
 * کلاس مدیریت دیتابیس - نسخه بهینه‌سازی شده
 */

if (!defined('ABSPATH')) {
    exit;
}

class PCFB_DB {
    private static $table_forms = 'pcfb_forms';
    private static $table_submissions = 'pcfb_submissions';

    /**
     * ایجاد جداول دیتابیس با مدیریت خطا
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // بررسی وجود تابع dbDelta
        if (!function_exists('dbDelta')) {
            error_log('PCFB Error: dbDelta function not available');
            return false;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        try {
            // جدول فرم‌ها
            $forms_table = $wpdb->prefix . self::$table_forms;
            $forms_sql = "CREATE TABLE $forms_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_name varchar(200) NOT NULL,
                form_slug varchar(100) NOT NULL,
                form_json longtext NOT NULL,
                settings longtext DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                status tinyint(1) DEFAULT 1 NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY form_slug (form_slug),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";

            // جدول ارسال‌ها
            $submissions_table = $wpdb->prefix . self::$table_submissions;
            $submissions_sql = "CREATE TABLE $submissions_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_id mediumint(9) NOT NULL,
                form_data longtext NOT NULL,
                ip_address varchar(45) DEFAULT '',
                user_agent text DEFAULT '',
                user_id bigint(20) DEFAULT 0,
                status varchar(20) DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY status (status),
                KEY created_at (created_at),
                KEY user_id (user_id)
            ) $charset_collate;";

            // اجرای کوئری‌ها
            dbDelta($forms_sql);
            dbDelta($submissions_sql);
            
            // بررسی خطاهای احتمالی
            if (!empty($wpdb->last_error)) {
                error_log('PCFB Database Error: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('PCFB Database Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ذخیره یا به‌روزرسانی فرم
     */
    public static function save_form($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_forms;
        
        // اعتبارسنجی داده‌ها
        $form_id = isset($data['form_id']) ? absint($data['form_id']) : 0;
        $form_name = sanitize_text_field($data['form_name'] ?? '');
        $form_json = wp_unslash($data['form_json'] ?? '');
        
        if (empty($form_name)) {
            return new WP_Error('invalid_name', 'نام فرم نمی‌تواند خالی باشد.');
        }
        
        if (empty($form_json)) {
            return new WP_Error('invalid_json', 'داده‌های فرم نمی‌تواند خالی باشد.');
        }
        
        // ایجاد slug خودکار
        $form_slug = sanitize_title($form_name);
        if (empty($form_slug)) {
            $form_slug = 'form-' . uniqid();
        }
        
        // آماده‌سازی داده‌ها
        $form_data = [
            'form_name' => $form_name,
            'form_slug' => $form_slug,
            'form_json' => $form_json,
            'settings' => isset($data['settings']) ? wp_json_encode($data['settings']) : '{}',
            'updated_at' => current_time('mysql')
        ];
        
        if ($form_id > 0) {
            // به‌روزرسانی فرم موجود
            $result = $wpdb->update(
                $table_name,
                $form_data,
                ['id' => $form_id],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            return $result !== false ? $form_id : false;
        } else {
            // ایجاد فرم جدید
            $form_data['created_at'] = current_time('mysql');
            $form_data['status'] = 1;
            
            $result = $wpdb->insert(
                $table_name,
                $form_data,
                ['%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * دریافت لیست فرم‌ها
     */
    public static function get_forms($args = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_forms;
        
        $defaults = [
            'status' => 1,
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = [];
        $prepare_args = [];
        
        if ($args['status'] !== null) {
            $where[] = 'status = %d';
            $prepare_args[] = $args['status'];
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_sql = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = "LIMIT %d";
            $prepare_args[] = $args['limit'];
            
            if ($args['offset'] > 0) {
                $limit_sql .= " OFFSET %d";
                $prepare_args[] = $args['offset'];
            }
        }
        
        $query = "SELECT * FROM {$table_name} {$where_sql} {$order_sql} {$limit_sql}";
        
        if (!empty($prepare_args)) {
            $query = $wpdb->prepare($query, $prepare_args);
        }
        
        return $wpdb->get_results($query);
    }

    // بعد از متد get_forms() این متدها را اضافه کنید:

/**
 * دریافت یک فرم خاص
 */
public static function get_form($form_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_forms;
    
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $form_id)
    );
}

/**
 * حذف فرم (نرم)
 */
public static function delete_form($form_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_forms;
    
    return $wpdb->update(
        $table_name,
        ['status' => 0, 'updated_at' => current_time('mysql')],
        ['id' => $form_id],
        ['%d', '%s'],
        ['%d']
    );
}

/**
 * دریافت ارسال‌های یک فرم
 */
public static function get_submissions($form_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_submissions;
    
    if ($form_id) {
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE form_id = %d ORDER BY created_at DESC",
                $form_id
            )
        );
    }
    
    return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
}

/**
 * حذف یک ارسال
 */
public static function delete_submission($submission_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_submissions;
    
    return $wpdb->delete(
        $table_name,
        ['id' => $submission_id],
        ['%d']
    );
}

/**
 * حذف تمام ارسال‌های یک فرم
 */
public static function delete_form_submissions($form_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_submissions;
    
    return $wpdb->delete(
        $table_name,
        ['form_id' => $form_id],
        ['%d']
    );
}
}