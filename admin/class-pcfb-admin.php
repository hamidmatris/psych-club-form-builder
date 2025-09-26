<?php
/**
 * کلاس مدیریت افزونه فرم‌ساز
 */

if (!defined('ABSPATH')) {
    exit;
}

class PCFB_Admin
{
    private $tabs = [];
    private $current_tab = 'general';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'init_tabs']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_init', [$this, 'handle_form_actions']);
    }

    public function init_tabs() {
        $this->tabs = [
            'general' => [
                'title' => 'تنظیمات عمومی',
                'capability' => 'manage_options',
                'callback' => [$this, 'display_general_tab'],
                'icon' => 'dashicons-admin-settings'
            ],
            'forms' => [
                'title' => 'مدیریت فرم‌ها',
                'capability' => 'manage_options',
                'callback' => [$this, 'display_forms_tab'],
                'icon' => 'dashicons-format-aside'
            ],
            'submissions' => [
                'title' => 'نتایج فرم‌ها',
                'capability' => 'manage_options',
                'callback' => [$this, 'display_submissions_tab'],
                'icon' => 'dashicons-list-view'
            ]
        ];

        $this->current_tab = $this->get_current_tab();
    }

    public function add_menu() {
        add_menu_page(
            'فرم‌ساز انجمن روانشناسی',
            'فرم‌ساز روانشناسی',
            'manage_options',
            'pcfb-settings',
            [$this, 'settings_page'],
            'dashicons-feedback',
            30
        );
    }

    public function enqueue_admin_scripts($hook) {
        // فقط در صفحات مربوط به افزونه اسکریپت‌ها را بارگذاری کن
        if (strpos($hook, 'pcfb-settings') === false) {
            return;
        }

        // WordPress libraries
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('wp-util');

        // Admin scripts
        wp_enqueue_script(
            'pcfb-admin',
            PCFB_URL . 'admin/js/pcfb-admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-util'],
            PCFB_VERSION,
            true
        );

        // Admin styles
        wp_enqueue_style(
            'pcfb-admin',
            PCFB_URL . 'admin/css/pcfb-admin.css',
            ['wp-components'],
            PCFB_VERSION
        );

        // Localize script data
        wp_localize_script('pcfb-admin', 'pcfb_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pcfb_admin_nonce'),
            'current_tab' => $this->current_tab,
            'texts' => [
                'confirm_delete' => 'آیا از حذف این مورد مطمئن هستید؟',
                'saving' => 'در حال ذخیره...',
                'saved' => 'ذخیره شد!',
                'error' => 'خطا رخ داد!',
                'loading' => 'در حال بارگذاری...'
            ]
        ]);
    }

    public function settings_page() {
        // بررسی دسترسی کاربر
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'pcfb'));
        }

        // نمایش اعلان‌ها
        $this->display_admin_notices();
        ?>
        
        <div class="wrap pcfb-admin">
            <h1 class="pcfb-title">
                <span class="dashicons dashicons-feedback"></span>
                مدیریت فرم‌های انجمن روانشناسی
            </h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_key => $tab_data) : ?>
                    <?php if (current_user_can($tab_data['capability'])) : ?>
                        <a href="<?php echo esc_url($this->get_tab_url($tab_key)); ?>" 
                           class="nav-tab <?php echo $this->current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                            <span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>"></span>
                            <?php echo esc_html($tab_data['title']); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <div class="pcfb-tab-content">
                <?php $this->display_tab_content($this->current_tab); ?>
            </div>
        </div>
        <?php
    }

    private function get_current_tab() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        // بررسی وجود تب و دسترسی
        if (!isset($this->tabs[$tab]) || !current_user_can($this->tabs[$tab]['capability'])) {
            $tab = 'general';
        }

        return $tab;
    }

    private function get_tab_url($tab) {
        return add_query_arg([
            'page' => 'pcfb-settings',
            'tab' => $tab
        ], admin_url('admin.php'));
    }

    private function display_tab_content($tab) {
        if (isset($this->tabs[$tab]['callback']) && is_callable($this->tabs[$tab]['callback'])) {
            call_user_func($this->tabs[$tab]['callback']);
        } else {
            $this->display_error('تابع نمایش این تب یافت نشد.');
        }
    }

    public function display_general_tab() {
        if (!current_user_can('manage_options')) {
            $this->display_error('شما دسترسی لازم ندارید.');
            return;
        }
        
        include_once PCFB_PATH . 'admin/views/settings-page.php';
    }

    public function display_forms_tab() {
        if (!current_user_can('manage_options')) {
            $this->display_error('شما دسترسی لازم ندارید.');
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'create':
            case 'edit':
                include_once PCFB_PATH . 'admin/views/form-editor.php';
                break;
            case 'list':
            default:
                include_once PCFB_PATH . 'admin/views/form-list.php';
                break;
        }
    }

    public function display_submissions_tab() {
        if (!current_user_can('manage_options')) {
            $this->display_error('شما دسترسی لازم ندارید.');
            return;
        }
        
        include_once PCFB_PATH . 'admin/views/submissions-page.php';
    }

    private function display_admin_notices() {
        // نمایش اعلان‌های سیستمی
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'success';
            
            $class = $type === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private function display_error($message) {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    public function handle_form_actions() {
        // مدیریت اقدامات فرم‌ها (در آینده پیاده‌سازی شود)
        if (!isset($_POST['pcfb_action']) || !wp_verify_nonce($_POST['_wpnonce'], 'pcfb_admin_action')) {
            return;
        }
    }
}