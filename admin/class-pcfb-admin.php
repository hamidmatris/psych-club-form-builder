<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu() {
        add_menu_page(
            'فرم‌های انجمن روانشناسی',
            'فرم‌ساز روانشناسی',
            'manage_options',
            'pcfb-settings',
            array( $this, 'settings_page' ),
            'dashicons-feedback',
            26
        );
    }

    public function settings_page() {
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>مدیریت فرم‌های انجمن روانشناسی</h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=pcfb-settings&tab=general" class="nav-tab <?php echo $tab=='general'?'nav-tab-active':''; ?>">تنظیمات عمومی</a>
                <a href="?page=pcfb-settings&tab=forms" class="nav-tab <?php echo $tab=='forms'?'nav-tab-active':''; ?>">فرم‌ها</a>
                <a href="?page=pcfb-settings&tab=submissions" class="nav-tab <?php echo $tab=='submissions'?'nav-tab-active':''; ?>">نتایج</a>
            </nav>
        <?php
        if ($tab == 'general') {
            include PCFB_PATH . 'admin/views/settings-page.php';
        } elseif ($tab == 'forms') {
            include PCFB_PATH . 'admin/views/forms-page.php';
        } elseif ($tab == 'submissions') {
            include PCFB_PATH . 'admin/views/submissions-page.php';
        }
        echo "</div>";
    }
}
