<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_Admin {

<<<<<<< HEAD
    private $tabs = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'init_tabs' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function init_tabs() {
        $this->tabs = array(
            'general' => array(
                'title' => 'تنظیمات عمومی',
                'capability' => 'manage_options',
                'callback' => array( $this, 'display_general_tab' )
            ),
            'forms' => array(
                'title' => 'فرم‌ها',
                'capability' => 'manage_options',
                'callback' => array( $this, 'display_forms_tab' )
            ),
            'submissions' => array(
                'title' => 'نتایج',
                'capability' => 'manage_options',
                'callback' => array( $this, 'display_submissions_tab' )
            )
        );
=======
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
>>>>>>> 790f10da24534e457f5891ff27315d2c30e0e07d
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

<<<<<<< HEAD
    public function enqueue_admin_scripts( $hook ) {
        // فقط در صفحات مربوط به افزونه اسکریپت‌ها را بارگذاری کن
        if ( strpos( $hook, 'pcfb-settings' ) === false ) {
            return;
        }
        
        // کتابخانه‌های وردپرس
        wp_enqueue_script( 'jquery-ui-sortable' );
        
        // اسکریپت اختصاصی admin
        wp_enqueue_script(
            'pcfb-admin',
            PCFB_URL . 'admin/js/pcfb-admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            PCFB_VERSION,
            true
        );
        
        // استایل‌های admin
        wp_enqueue_style(
            'pcfb-admin',
            PCFB_URL . 'admin/css/pcfb-admin.css',
            array(),
            PCFB_VERSION
        );
        
        // انتقال داده‌ها به JavaScript
        wp_localize_script( 'pcfb-admin', 'pcfb_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'pcfb_admin_nonce' ),
            'texts' => array(
                'confirm_delete' => 'آیا از حذف مطمئن هستید؟',
                'saving' => 'در حال ذخیره...',
                'loading' => 'در حال بارگذاری...'
            )
        ) );
    }

    public function settings_page() {
        // بررسی دسترسی کاربر
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'pcfb' ) );
        }

        $tab = $this->get_current_tab();
        ?>
        <div class="wrap">
            <h1>مدیریت فرم‌های انجمن روانشناسی</h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ( $this->tabs as $tab_key => $tab_data ) : ?>
                    <?php if ( current_user_can( $tab_data['capability'] ) ) : ?>
                        <a href="<?php echo esc_url( $this->get_tab_url( $tab_key ) ); ?>" 
                           class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html( $tab_data['title'] ); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <div class="pcfb-tab-content">
                <?php $this->display_tab_content( $tab ); ?>
            </div>
        </div>
        <?php
    }

    private function get_current_tab() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        
        // بررسی وجود تب
        if ( ! array_key_exists( $tab, $this->tabs ) ) {
            $tab = 'general';
        }

        // بررسی دسترسی کاربر
        if ( ! current_user_can( $this->tabs[ $tab ]['capability'] ) ) {
            $tab = 'general';
        }

        return $tab;
    }

    private function get_tab_url( $tab ) {
        return add_query_arg( 
            array( 
                'page' => 'pcfb-settings', 
                'tab' => $tab 
            ),
            admin_url( 'admin.php' ) 
        );
    }

    private function display_tab_content( $tab ) {
        if ( isset( $this->tabs[ $tab ]['callback'] ) && is_callable( $this->tabs[ $tab ]['callback'] ) ) {
            call_user_func( $this->tabs[ $tab ]['callback'] );
        } else {
            echo '<div class="notice notice-error"><p>تب مورد نظر یافت نشد.</p></div>';
        }
    }

    public function display_general_tab() {
        include PCFB_PATH . 'admin/views/settings-page.php';
    }

    public function display_forms_tab() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        
        if ( $action === 'create' || $action === 'edit' ) {
            include PCFB_PATH . 'admin/views/forms-page.php';
        } else {
            include PCFB_PATH . 'admin/views/form-list.php';
        }
    }

    public function display_submissions_tab() {
        include PCFB_PATH . 'admin/views/submissions-page.php';
    }
}

// نکته مهم: هیچ کد دیگری بعد از این نباید باشد
=======
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
>>>>>>> 790f10da24534e457f5891ff27315d2c30e0e07d
