<?php
/**
 * Plugin Name: Psych Club Form Builder
 * Description: افزونه ساخت فرم‌های انجمن روانشناسی
 * Version: 1.8
 * Author: شما
 * Text Domain: pcfb
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی نسخه PHP
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', 'pcfb_php_version_notice');
    return;
}

// بررسی نسخه وردپرس
if (version_compare(get_bloginfo('version'), '5.6', '<')) {
    add_action('admin_notices', 'pcfb_wp_version_notice');
    return;
}

function pcfb_php_version_notice() {
    echo '<div class="notice notice-error"><p>';
    echo 'افزونه Psych Club Form Builder نیاز به PHP 7.4 یا بالاتر دارد. نسخه فعلی: ' . PHP_VERSION;
    echo '</p></div>';
}

function pcfb_wp_version_notice() {
    echo '<div class="notice notice-error"><p>';
    echo 'افزونه Psych Club Form Builder نیاز به وردپرس 5.6 یا بالاتر دارد.';
    echo '</p></div>';
}

// تعریف ثابت‌ها
define('PCFB_VERSION', '1.8');
define('PCFB_PLUGIN_FILE', __FILE__);
define('PCFB_PATH', plugin_dir_path(__FILE__));
define('PCFB_URL', plugin_dir_url(__FILE__));
define('PCFB_BASENAME', plugin_basename(__FILE__));

/**
 * کلاس اصلی افزونه
 */
final class Psych_Club_Form_Builder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->define_constants();
        $this->check_requirements();
        $this->includes();
        $this->init_hooks();
    }
    
    private function define_constants() {
        // ثابت‌ها قبلاً تعریف شده‌اند
    }
    
    private function check_requirements() {
        // بررسی‌های اضافی برای اطمینان از سازگاری
        if (!function_exists('register_activation_hook')) {
            wp_die('توابع لازم وردپرس در دسترس نیستند.');
        }
    }
    
    private function includes() {
        try {
            // بارگذاری فایل‌های مورد نیاز با بررسی وجود آن‌ها
            $required_files = [
                'includes/class-pcfb-db.php',
                'includes/class-pcfb-ajax.php',
                'admin/class-pcfb-admin.php',
                'public/class-pcfb-form.php'
            ];
            
            foreach ($required_files as $file) {
                $file_path = PCFB_PATH . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    throw new Exception('فایل ضروری یافت نشد: ' . $file);
                }
            }
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo 'خطا در بارگذاری افزونه: ' . esc_html($e->getMessage());
                echo '</p></div>';
            });
            return;
        }
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, ['Psych_Club_Form_Builder', 'uninstall']);
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('init', [$this, 'load_textdomain']);
    }
    
    public function activate() {
        // ایجاد جدول دیتابیس
        if (class_exists('PCFB_DB')) {
            PCFB_DB::create_tables();
        }
        
        // افزودن گزینه‌های پیش‌فرض
        $default_options = [
            'pcfb_version' => PCFB_VERSION,
            'pcfb_enable_anti_spam' => true,
            'pcfb_submission_limit' => 10,
            'pcfb_time_frame' => 3600,
            'pcfb_enable_email_notifications' => false,
            'pcfb_admin_email' => get_option('admin_email')
        ];
        
        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        // پاکسازی cache
        wp_cache_flush();
    }
    
    public function deactivate() {
        // پاکسازی cron jobs
        wp_clear_scheduled_hook('pcfb_daily_cleanup');
    }
    
    public static function uninstall() {
        // حذف گزینه‌ها
        $options = [
            'pcfb_version',
            'pcfb_enable_anti_spam',
            'pcfb_submission_limit',
            'pcfb_time_frame',
            'pcfb_enable_email_notifications',
            'pcfb_admin_email'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // حذف جداول دیتابیس (اختیاری)
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'pcfb_forms',
            $wpdb->prefix . 'pcfb_submissions'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
    
    public function init_plugin() {
        // بررسی وجود کلاس‌های ضروری
        if (!class_exists('PCFB_Admin') || !class_exists('PCFB_Form')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo 'کلاس‌های اصلی افزونه بارگذاری نشدند.';
                echo '</p></div>';
            });
            return;
        }
        
        // راه‌اندازی کامپوننت‌ها
        new PCFB_Admin();
        new PCFB_Form();
        
        // راه‌اندازی AJAX
        if (class_exists('PCFB_AJAX')) {
            new PCFB_AJAX();
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'pcfb',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

// راه‌اندازی افزونه
function pcfb_init() {
    // بررسی مجدد قبل از راه‌اندازی
    if (version_compare(PHP_VERSION, '7.4', '<') || 
        version_compare(get_bloginfo('version'), '5.6', '<')) {
        return;
    }
    
    Psych_Club_Form_Builder::get_instance();
}

add_action('plugins_loaded', 'pcfb_init', 0);