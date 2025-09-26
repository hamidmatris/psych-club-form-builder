<?php
/**
 * صفحه لیست فرم‌ها - مدیریت فرم‌ساز
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی کاربر
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
}

// دریافت فرم‌های موجود
$forms = PCFB_DB::get_forms(['status' => 1, 'orderby' => 'created_at', 'order' => 'DESC']);
$total_forms = count($forms);

// محاسبه آمار کلی
$stats = PCFB_DB::get_stats();

// مدیریت actionها
$message = $this->handle_form_actions();

// تابع مدیریت actionها
function handle_form_actions() {
    if (!isset($_GET['action']) || !isset($_GET['form_id'])) {
        return '';
    }

    $action = sanitize_text_field($_GET['action']);
    $form_id = absint($_GET['form_id']);
    $nonce = $_GET['_wpnonce'] ?? '';

    if (!wp_verify_nonce($nonce, 'pcfb_form_action')) {
        return '<div class="notice notice-error"><p>خطای امنیتی</p></div>';
    }

    switch ($action) {
        case 'delete':
            return $this->handle_delete_form($form_id);
            
        case 'duplicate':
            return $this->handle_duplicate_form($form_id);
            
        case 'toggle_status':
            return $this->handle_toggle_status($form_id);
            
        default:
            return '';
    }
}

function handle_delete_form($form_id) {
    $result = PCFB_DB::delete_form($form_id);
    
    if ($result !== false) {
        return '<div class="notice notice-success is-dismissible"><p>فرم با موفقیت حذف شد.</p></div>';
    } else {
        return '<div class="notice notice-error is-dismissible"><p>خطا در حذف فرم.</p></div>';
    }
}

function handle_duplicate_form($form_id) {
    $original_form = PCFB_DB::get_form($form_id);
    
    if (!$original_form) {
        return '<div class="notice notice-error is-dismissible"><p>فرم مورد نظر یافت نشد.</p></div>';
    }

    // ایجاد نام جدید
    $new_name = $this->generate_unique_form_name($original_form->form_name);
    
    $result = PCFB_DB::save_form([
        'form_name' => $new_name,
        'form_json' => $original_form->form_json,
        'settings' => $original_form->settings
    ]);

    if ($result) {
        return '<div class="notice notice-success is-dismissible"><p>فرم با موفقیت کپی شد.</p></div>';
    } else {
        return '<div class="notice notice-error is-dismissible"><p>خطا در کپی‌برداری فرم.</p></div>';
    }
}
?>

<div class="wrap pcfb-forms-list">
    <div class="pcfb-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-edit-page"></span>
            مدیریت فرم‌ها
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms&action=create'); ?>" 
           class="page-title-action">
            <span class="dashicons dashicons-plus"></span>
            ایجاد فرم جدید
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=submissions'); ?>" 
           class="page-title-action">
            <span class="dashicons dashicons-list-view"></span>
            مشاهده ارسال‌ها
        </a>
    </div>

    <hr class="wp-header-end">

    <?php if ($message) echo $message; ?>

    <!-- کارت‌های آمار -->
    <div class="pcfb-stats-cards">
        <div class="pcfb-stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($total_forms); ?></span>
                <span class="stat-label">فرم‌های فعال</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($stats['total_submissions']); ?></span>
                <span class="stat-label">کل ارسال‌ها</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($stats['today_submissions']); ?></span>
                <span class="stat-label">ارسال‌های امروز</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card">
            <div class="stat-icon">⚡</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo PCFB_VERSION; ?></span>
                <span class="stat-label">ورژن افزونه</span>
            </div>
        </div>
    </div>

    <?php if (empty($forms)) : ?>
        <!-- حالت خالی -->
        <div class="pcfb-empty-state">
            <div class="empty-content">
                <div class="empty-icon">📋</div>
                <h2>هنوز فرمی ایجاد نکرده‌اید</h2>
                <p>برای شروع، اولین فرم خود را ایجاد کنید و آن را در صفحات سایت قرار دهید.</p>
                <div class="empty-actions">
                    <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms&action=create'); ?>" 
                       class="button button-primary button-hero">
                        <span class="dashicons dashicons-plus"></span>
                        ساخت اولین فرم
                    </a>
                    <a href="https://github.com/your-repo/docs" 
                       target="_blank" 
                       class="button button-hero">
                        <span class="dashicons dashicons-sos"></span>
                        راهنمای استفاده
                    </a>
                </div>
            </div>
        </div>
    <?php else : ?>
        <!-- جدول فرم‌ها -->
        <div class="pcfb-forms-table-container">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" class="column-primary">نام فرم</th>
                        <th scope="col" class="column-fields">فیلدها</th>
                        <th scope="col" class="column-submissions">ارسال‌ها</th>
                        <th scope="col" class="column-date">تاریخ ایجاد</th>
                        <th scope="col" class="column-actions">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form) : 
                        $form_data = json_decode($form->form_json, true);
                        $field_count = isset($form_data['fields']) ? count($form_data['fields']) : 0;
                        $submission_count = PCFB_DB::get_submissions_count($form->id);
                        
                        // URLs
                        $edit_url = admin_url('admin.php?page=pcfb-settings&tab=forms&action=edit&form_id=' . $form->id);
                        $submissions_url = admin_url('admin.php?page=pcfb-settings&tab=submissions&form_id=' . $form->id);
                        $preview_url = add_query_arg('pcfb_preview', $form->id, home_url());
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=pcfb-settings&tab=forms&action=delete&form_id=' . $form->id),
                            'pcfb_form_action'
                        );
                        $duplicate_url = wp_nonce_url(
                            admin_url('admin.php?page=pcfb-settings&tab=forms&action=duplicate&form_id=' . $form->id),
                            'pcfb_form_action'
                        );
                        $export_url = wp_nonce_url(
                            admin_url('admin.php?page=pcfb-settings&tab=forms&action=export&form_id=' . $form->id),
                            'pcfb_form_action'
                        );
                    ?>
                    <tr>
                        <td class="column-primary">
                            <strong class="row-title">
                                <a href="<?php echo $edit_url; ?>" class="form-name-link">
                                    <?php echo esc_html($form->form_name); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo $edit_url; ?>">ویرایش</a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo $preview_url; ?>" target="_blank">پیش‌نمایش</a> |
                                </span>
                                <span class="duplicate">
                                    <a href="<?php echo $duplicate_url; ?>">کپی</a> |
                                </span>
                                <span class="delete">
                                    <a href="<?php echo $delete_url; ?>" 
                                       class="submitdelete"
                                       onclick="return confirm('آیا از حذف فرم «<?php echo esc_js($form->form_name); ?>» مطمئن هستید؟')">
                                        حذف
                                    </a>
                                </span>
                            </div>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text">نمایش جزئیات بیشتر</span>
                            </button>
                        </td>
                        
                        <td class="column-fields">
                            <span class="field-badge">
                                <span class="dashicons dashicons-forms"></span>
                                <?php echo number_format($field_count); ?> فیلد
                            </span>
                        </td>
                        
                        <td class="column-submissions">
                            <a href="<?php echo $submissions_url; ?>" class="submission-link">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php echo number_format($submission_count); ?> ارسال
                            </a>
                            <?php if ($submission_count > 0) : ?>
                                <br>
                                <small class="submission-latest">
                                    آخرین: <?php echo $this->get_latest_submission_time($form->id); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        
                        <td class="column-date">
                            <span class="date-human">
                                <?php echo human_time_diff(strtotime($form->created_at), current_time('timestamp')) . ' پیش'; ?>
                            </span>
                            <br>
                            <small class="date-exact">
                                <?php echo date_i18n('Y/m/d - H:i', strtotime($form->created_at)); ?>
                            </small>
                        </td>
                        
                        <td class="column-actions">
                            <div class="action-buttons">
                                <a href="<?php echo $edit_url; ?>" 
                                   class="button button-small button-primary" 
                                   title="ویرایش فرم">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                
                                <a href="<?php echo $submissions_url; ?>" 
                                   class="button button-small" 
                                   title="مشاهده ارسال‌ها">
                                    <span class="dashicons dashicons-list-view"></span>
                                </a>
                                
                                <a href="<?php echo $preview_url; ?>" 
                                   target="_blank" 
                                   class="button button-small" 
                                   title="پیش‌نمایش فرم">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a>
                                
                                <div class="pcfb-dropdown">
                                    <button type="button" class="button button-small pcfb-dropdown-toggle">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    </button>
                                    <div class="pcfb-dropdown-menu">
                                        <a href="<?php echo $duplicate_url; ?>" class="pcfb-dropdown-item">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            کپی فرم
                                        </a>
                                        <a href="<?php echo $export_url; ?>" class="pcfb-dropdown-item">
                                            <span class="dashicons dashicons-download"></span>
                                            خروجی CSV
                                        </a>
                                        <hr class="pcfb-dropdown-divider">
                                        <a href="<?php echo $delete_url; ?>" 
                                           class="pcfb-dropdown-item pcfb-dropdown-item-danger"
                                           onclick="return confirm('آیا از حذف فرم مطمئن هستید؟')">
                                            <span class="dashicons dashicons-trash"></span>
                                            حذف فرم
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="shortcode-section">
                                <label class="shortcode-label">
                                    <strong>شورتکد:</strong>
                                </label>
                                <div class="shortcode-input-group">
                                    <input type="text" 
                                           value='[pcfb_form id="<?php echo $form->id; ?>"]' 
                                           class="shortcode-input" 
                                           readonly>
                                    <button type="button" 
                                            class="button button-small copy-shortcode-btn"
                                            data-clipboard-text='[pcfb_form id="<?php echo $form->id; ?>"]'>
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- عملیات گروهی -->
        <div class="pcfb-bulk-actions">
            <div class="bulk-actions-panel">
                <select name="pcfb_bulk_action" id="pcfb-bulk-action">
                    <option value="">عملیات گروهی...</option>
                    <option value="duplicate">کپی‌برداری انتخاب شده‌ها</option>
                    <option value="export">خروجی CSV انتخاب شده‌ها</option>
                    <option value="delete">حذف انتخاب شده‌ها</option>
                </select>
                <button type="button" id="pcfb-bulk-apply" class="button">اعمال</button>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // کپی کردن شورتکد
    $('.copy-shortcode-btn').on('click', function() {
        const shortcode = $(this).data('clipboard-text');
        const $button = $(this);
        
        navigator.clipboard.writeText(shortcode).then(function() {
            // نمایش feedback
            $button.html('<span class="dashicons dashicons-yes"></span>');
            $button.addClass('copied');
            
            setTimeout(function() {
                $button.html('<span class="dashicons dashicons-admin-page"></span>');
                $button.removeClass('copied');
            }, 2000);
        });
    });

    // مدیریت dropdownها
    $('.pcfb-dropdown-toggle').on('click', function(e) {
        e.stopPropagation();
        $(this).closest('.pcfb-dropdown').toggleClass('active');
    });

    // بستن dropdown با کلیک خارج
    $(document).on('click', function() {
        $('.pcfb-dropdown').removeClass('active');
    });

    // عملیات گروهی
    $('#pcfb-bulk-apply').on('click', function() {
        const action = $('#pcfb-bulk-action').val();
        
        if (!action) {
            alert('لطفاً یک عمل را انتخاب کنید.');
            return;
        }

        // در اینجا می‌توانید عملیات گروهی را پیاده‌سازی کنید
        switch (action) {
            case 'duplicate':
                if (confirm('آیا از کپی‌برداری فرم‌های انتخاب شده مطمئن هستید؟')) {
                    // پیاده‌سازی AJAX برای کپی گروهی
                }
                break;
                
            case 'export':
                // پیاده‌سازی خروجی گروهی
                break;
                
            case 'delete':
                if (confirm('آیا از حذف فرم‌های انتخاب شده مطمئن هستید؟ این عمل غیرقابل بازگشت است.')) {
                    // پیاده‌سازی حذف گروهی
                }
                break;
        }
    });

    // جستجوی فرم‌ها
    $('#pcfb-search-form').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.wp-list-table tbody tr').each(function() {
            const formName = $(this).find('.form-name-link').text().toLowerCase();
            if (formName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>

<style>
.pcfb-forms-list {
    max-width: 1200px;
}

/* استایل‌های کارت‌های آمار، dropdownها، و سایر المان‌ها */
/* به دلیل محدودیت طول پاسخ، استایل‌های کامل در فایل CSS جداگانه قرار می‌گیرند */
</style>