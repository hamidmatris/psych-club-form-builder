<?php
/*
Plugin Name: Psych Club Form Builder
Description: افزونه ساخت فرم‌های انجمن روانشناسی
Version: 1.8
Author: شما
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PCFB_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCFB_URL', plugin_dir_url( __FILE__ ) );

require_once PCFB_PATH . 'admin/class-pcfb-admin.php';
require_once PCFB_PATH . 'public/class-pcfb-form.php';
require_once PCFB_PATH . 'includes/class-pcfb-db.php';

function pcfb_init() {
    new PCFB_Admin();
    new PCFB_Form();
}
add_action( 'plugins_loaded', 'pcfb_init' );
register_activation_hook( __FILE__, ['PCFB_DB', 'create_table'] );

