<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_DB {
    private static $table = 'pcfb_forms';

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_name varchar(200) NOT NULL,
            form_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function insert_form($name, $json) {
<<<<<<< HEAD
    global $wpdb;
    
    // بررسی و sanitize داده‌ها
    $name = sanitize_text_field($name);
    if (empty($name)) {
        return new WP_Error('invalid_name', 'نام فرم نمی‌تواند خالی باشد.');
    }
    
    // بررسی صحت JSON
    if (!self::is_valid_json($json)) {
        return new WP_Error('invalid_json', 'داده‌های فرم معتبر نیستند.');
    }
    
    $table_name = $wpdb->prefix . self::$table;
    
    $result = $wpdb->insert(
        $table_name,
        [
            'form_name' => $name,
            'form_json' => $json
        ],
        ['%s', '%s']
    );
    
    if ($result === false) {
        error_log('PCFB DB Error: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'خطا در ذخیره‌سازی دیتابیس: ' . $wpdb->last_error);
    }
    
    return $wpdb->insert_id;
}

// اضافه کردن متد update_form اگر وجود ندارد
public static function update_form($id, $name, $json) {
    global $wpdb;
    
    $id = absint($id);
    $name = sanitize_text_field($name);
    
    if (empty($name)) {
        return new WP_Error('invalid_name', 'نام فرم نمی‌تواند خالی باشد.');
    }
    
    if (!self::is_valid_json($json)) {
        return new WP_Error('invalid_json', 'داده‌های فرم معتبر نیستند.');
    }
    
    $table_name = $wpdb->prefix . self::$table;
    
    $result = $wpdb->update(
        $table_name,
        [
            'form_name' => $name,
            'form_json' => $json
        ],
        ['id' => $id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($result === false) {
        error_log('PCFB DB Update Error: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'خطا در به‌روزرسانی دیتابیس');
    }
    
    return $result;
}
=======
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        $wpdb->insert($table_name, [
            'form_name' => $name,
            'form_json' => $json
        ]);
        return $wpdb->insert_id;
    }
>>>>>>> 790f10da24534e457f5891ff27315d2c30e0e07d

    public static function get_forms() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
    }

    public static function get_form($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
<<<<<<< HEAD
	
	// اضافه کردن متدهای缺失 به کلاس PCFB_DB
public static function update_form($id, $name, $json) {
    // پیاده‌سازی مشابه insert_form
}

public static function delete_form($id) {
    // پیاده‌سازی soft delete
}
// اضافه کردن به متد settings_page()
if ($tab == 'forms') {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    if ($action == 'create' || $action == 'edit') {
        include PCFB_PATH . 'admin/views/forms-page.php';
    } else {
        include PCFB_PATH . 'admin/views/form-list.php';
    }
}
=======
>>>>>>> 790f10da24534e457f5891ff27315d2c30e0e07d
}
