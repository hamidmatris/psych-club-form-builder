<?php
/**
 * صفحه ویرایشگر فرم - فرم‌ساز بصری
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی کاربر
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
}

// دریافت اطلاعات فرم
$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
$form = $form_id > 0 ? PCFB_DB::get_form($form_id) : null;
$form_data = $form ? json_decode($form->form_json, true) : [];
$page_title = $form ? 'ویرایش فرم: ' . esc_html($form->form_name) : 'ساخت فرم جدید';

// تنظیم داده‌های پیش‌فرض
wp_localize_script('pcfb-admin', 'pcfb_existing_form', [
    'form_id' => $form_id,
    'form_name' => $form ? $form->form_name : '',
    'fields' => $form_data['fields'] ?? []
]);
?>

<div class="wrap pcfb-form-editor">
    <div class="pcfb-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-edit"></span>
            <?php echo $page_title; ?>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms'); ?>" 
           class="page-title-action">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            بازگشت به لیست فرم‌ها
        </a>
        
        <?php if ($form_id > 0) : ?>
        <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms&action=create'); ?>" 
           class="page-title-action">
            <span class="dashicons dashicons-plus"></span>
            فرم جدید
        </a>
        <?php endif; ?>
    </div>

    <hr class="wp-header-end">

    <!-- پیام‌های سیستم -->
    <div id="pcfb-editor-messages"></div>

    <!-- ویرایشگر فرم -->
    <div id="pcfb-builder">
        <!-- این بخش توسط JavaScript پر خواهد شد -->
        <div class="pcfb-loading-editor">
            <div class="loading-content">
                <span class="spinner is-active"></span>
                <p>در حال بارگذاری ویرایشگر فرم...</p>
            </div>
        </div>
    </div>

    <!-- فرم ذخیره سازی (مخفی) -->
    <form id="pcfb-save-form-data" method="post" style="display: none;">
        <?php wp_nonce_field('pcfb_save_form', 'pcfb_nonce'); ?>
        <input type="hidden" name="action" value="pcfb_save_form">
        <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
        <input type="hidden" name="form_name" id="pcfb-save-form-name">
        <input type="hidden" name="form_json" id="pcfb-save-form-json">
        <input type="hidden" name="form_settings" id="pcfb-save-form-settings">
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // راه‌اندازی ویرایشگر هنگام بارگذاری کامل صفحه
    $(window).on('load', function() {
        if (typeof PCFBFormBuilder !== 'undefined') {
            new PCFBFormBuilder();
        } else {
            console.error('PCFBFormBuilder is not defined');
            $('#pcfb-builder').html(
                '<div class="pcfb-error-state">' +
                '<div class="error-icon">❌</div>' +
                '<h3>خطا در بارگذاری ویرایشگر</h3>' +
                '<p>مشکلی در بارگذاری ویرایشگر فرم رخ داده است. لطفاً صفحه را رفرش کنید.</p>' +
                '</div>'
            );
        }
    });

    // مدیریت ذخیره فرم
    $(document).on('click', '#pcfb-save-form-btn', function() {
        const formData = window.pcfbFormBuilder?.getFormData();
        
        if (!formData || !formData.name || formData.fields.length === 0) {
            alert('لطفاً نام فرم را وارد کنید و حداقل یک فیلد اضافه نمایید.');
            return;
        }

        // پر کردن فرم مخفی
        $('#pcfb-save-form-name').val(formData.name);
        $('#pcfb-save-form-json').val(JSON.stringify(formData));
        $('#pcfb-save-form-settings').val(JSON.stringify(formData.settings || {}));

        // ارسال فرم
        $('#pcfb-save-form-data').submit();
    });

    // پیش‌نمایش فرم
    $(document).on('click', '#pcfb-preview-form-btn', function() {
        const formData = window.pcfbFormBuilder?.getFormData();
        if (formData) {
            // باز کردن پیش‌نمایش در پنجره جدید
            const previewWindow = window.open('', '_blank');
            previewWindow.document.write(
                '<!DOCTYPE html><html><head><title>پیش‌نمایش فرم</title></head><body>' +
                '<style>body { font-family: sans-serif; padding: 20px; }</style>' +
                '<h2>پیش‌نمایش فرم: ' + formData.name + '</h2>' +
                '<div id="pcfb-preview-container"></div>' +
                '</body></html>'
            );
            
            // نمایش فرم در پیش‌نمایش
            // این بخش نیاز به پیاده‌سازی رندر فرم دارد
        }
    });
});
</script>

<style>
.pcfb-form-editor {
    max-width: none;
}

.pcfb-loading-editor {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 500px;
    background: #f9f9f9;
    border-radius: 8px;
}

.loading-content {
    text-align: center;
}

.pcfb-error-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.error-icon {
    font-size: 48px;
    margin-bottom: 20px;
}
</style>