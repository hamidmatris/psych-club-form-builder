<?php
/**
 * Plugin Name: Psych Club Form Builder
 * Description: افزونه ساخت فرم‌های انجمن روانشناسی
 * Version: 1.8
 * Author: شما
 * Text Domain: pcfb
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// تعریف ثابت‌ها
define( 'PCFB_VERSION', '1.8' );
define( 'PCFB_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCFB_URL', plugin_dir_url( __FILE__ ) );

// بارگذاری فایل‌های مورد نیاز
require_once PCFB_PATH . 'includes/class-pcfb-db.php';
require_once PCFB_PATH . 'includes/class-pcfb-ajax.php';
require_once PCFB_PATH . 'admin/class-pcfb-admin.php';
require_once PCFB_PATH . 'public/class-pcfb-form.php';

// راه‌اندازی افزونه
function pcfb_init() {
    // بررسی اینکه کلاس‌ها وجود دارند
    if ( class_exists( 'PCFB_Admin' ) && class_exists( 'PCFB_Form' ) ) {
        new PCFB_Admin();
        new PCFB_Form();
    }
}
add_action( 'plugins_loaded', 'pcfb_init' );

// ایجاد جدول دیتابیس هنگام فعال‌سازی
register_activation_hook( __FILE__, array( 'PCFB_DB', 'create_table' ) );

// پاکسازی هنگام غیرفعال‌سازی (اختیاری)
function pcfb_deactivate() {
    // پاکسازی موقت می‌تواند اینجا اضافه شود
}
register_deactivation_hook( __FILE__, 'pcfb_deactivate' );

// بارگذاری فایل ترجمه
function pcfb_load_textdomain() {
    load_plugin_textdomain( 'pcfb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'pcfb_load_textdomain' );