<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// دریافت فرم‌های موجود از دیتابیس
$forms = PCFB_DB::get_forms( true ); // فقط فرم‌های فعال
$message = '';

// بررسی actionهای دریافتی
if ( isset( $_GET['action'] ) && isset( $_GET['form_id'] ) ) {
    $action = sanitize_text_field( $_GET['action'] );
    $form_id = absint( $_GET['form_id'] );
    $nonce = $_GET['_wpnonce'] ?? '';
    
    if ( wp_verify_nonce( $nonce, 'pcfb_form_action' ) ) {
        switch ( $action ) {
            case 'delete':
                $result = PCFB_DB::delete_form( $form_id );
                if ( ! is_wp_error( $result ) ) {
                    $message = '<div class="notice notice-success"><p>فرم با موفقیت حذف شد.</p></div>';
                } else {
                    $message = '<div class="notice notice-error"><p>خطا در حذف فرم: ' . $result->get_error_message() . '</p></div>';
                }
                break;
                
            case 'duplicate':
                $original_form = PCFB_DB::get_form( $form_id );
                if ( $original_form ) {
                    $new_name = $original_form->form_name . ' (کپی)';
                    $result = PCFB_DB::insert_form( $new_name, $original_form->form_json );
                    if ( ! is_wp_error( $result ) ) {
                        $message = '<div class="notice notice-success"><p>فرم با موفقیت کپی شد.</p></div>';
                    }
                }
                break;
        }
        
        // رفرش لیست فرم‌ها پس از action
        $forms = PCFB_DB::get_forms( true );
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">مدیریت فرم‌ها</h1>
    <a href="<?php echo admin_url( 'admin.php?page=pcfb-settings&tab=forms&action=create' ); ?>" 
       class="page-title-action">
        📝 ایجاد فرم جدید
    </a>
    
    <hr class="wp-header-end">

    <?php if ( $message ) echo $message; ?>

    <?php if ( empty( $forms ) ) : ?>
        
        <div class="pcfb-empty-state">
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📋</div>
                <h2>هنوز فرمی ایجاد نکرده‌اید</h2>
                <p style="font-size: 16px; color: #666; margin-bottom: 30px;">
                    برای شروع، اولین فرم خود را ایجاد کنید.
                </p>
                <a href="<?php echo admin_url( 'admin.php?page=pcfb-settings&tab=forms&action=create' ); ?>" 
                   class="button button-primary button-hero">
                    ساخت اولین فرم
                </a>
            </div>
        </div>

    <?php else : ?>

        <div class="pcfb-forms-stats" style="background: #f6f7f7; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📊</span>
                    <div>
                        <strong><?php echo number_format( count( $forms ) ); ?></strong>
                        <span>فرم فعال</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📨</span>
                    <div>
                        <strong>
                            <?php
                            $total_submissions = 0;
                            foreach ( $forms as $form ) {
                                $submissions = PCFB_DB::get_submissions( $form->id );
                                $total_submissions += count( $submissions );
                            }
                            echo number_format( $total_submissions );
                            ?>
                        </strong>
                        <span>ارسال کل</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="25%">نام فرم</th>
                    <th width="15%">تعداد فیلدها</th>
                    <th width="15%">تعداد ارسال‌ها</th>
                    <th width="20%">تاریخ ایجاد</th>
                    <th width="20%">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $forms as $form ) : 
                    $form_data = json_decode( $form->form_json, true );
                    $field_count = isset( $form_data['fields'] ) ? count( $form_data['fields'] ) : 0;
                    $submission_count = count( PCFB_DB::get_submissions( $form->id ) );
                    $edit_url = admin_url( 'admin.php?page=pcfb-settings&tab=forms&action=edit&form_id=' . $form->id );
                    $submissions_url = admin_url( 'admin.php?page=pcfb-settings&tab=submissions&form_id=' . $form->id );
                    $delete_url = wp_nonce_url( 
                        admin_url( 'admin.php?page=pcfb-settings&tab=forms&action=delete&form_id=' . $form->id ), 
                        'pcfb_form_action' 
                    );
                    $duplicate_url = wp_nonce_url( 
                        admin_url( 'admin.php?page=pcfb-settings&tab=forms&action=duplicate&form_id=' . $form->id ), 
                        'pcfb_form_action' 
                    );
                ?>
                <tr>
                    <td><?php echo esc_html( $form->id ); ?></td>
                    <td>
                        <strong>
                            <a href="<?php echo $edit_url; ?>" title="ویرایش فرم">
                                <?php echo esc_html( $form->form_name ); ?>
                            </a>
                        </strong>
                        <?php if ( $form->status == 0 ) : ?>
                            <span class="pcfb-status-badge" style="background: #ccc; color: #666; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-right: 5px;">غیرفعال</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="pcfb-field-count"><?php echo number_format( $field_count ); ?> فیلد</span>
                    </td>
                    <td>
                        <a href="<?php echo $submissions_url; ?>" class="pcfb-submission-link">
                            <?php echo number_format( $submission_count ); ?> ارسال
                        </a>
                    </td>
                    <td>
                        <span class="pcfb-date" title="<?php echo esc_attr( $form->created_at ); ?>">
                            <?php echo human_time_diff( strtotime( $form->created_at ), current_time( 'timestamp' ) ) . ' پیش'; ?>
                        </span>
                        <br>
                        <small style="color: #666;"><?php echo date_i18n( 'Y/m/d', strtotime( $form->created_at ) ); ?></small>
                    </td>
                    <td>
                        <div class="pcfb-action-buttons">
                            <a href="<?php echo $edit_url; ?>" class="button button-small" title="ویرایش فرم">
                                ✏️ ویرایش
                            </a>
                            
                            <a href="<?php echo $submissions_url; ?>" class="button button-small" title="مشاهده نتایج">
                                📊 نتایج
                            </a>
                            
                            <a href="<?php echo $duplicate_url; ?>" class="button button-small" title="کپی‌برداری از فرم">
                                📋 کپی
                            </a>
                            
                            <a href="<?php echo $delete_url; ?>" 
                               class="button button-small button-link-delete" 
                               title="حذف فرم"
                               onclick="return confirm('آیا از حذف فرم \"<?php echo esc_js( $form->form_name ); ?>\" مطمئن هستید؟')">
                                🗑️ حذف
                            </a>
                        </div>
                        
                        <div class="pcfb-shortcode-info" style="margin-top: 5px;">
                            <small>
                                <strong>شورتکد:</strong> 
                                <code style="background: #f1f1f1; padding: 2px 4px; border-radius: 3px;">
                                    [pcfb_form id="<?php echo $form->id; ?>"]
                                </code>
                                <button type="button" class="button-link pcfb-copy-shortcode" 
                                        data-shortcode='[pcfb_form id="<?php echo $form->id; ?>"]'
                                        title="کپی شورتکد">
                                    📋
                                </button>
                            </small>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pcfb-bulk-actions" style="margin-top: 20px;">
            <select id="pcfb-bulk-action">
                <option value="">عملیات گروهی</option>
                <option value="export">خروجی CSV</option>
                <option value="duplicate">کپی‌برداری</option>
            </select>
            <button type="button" id="pcfb-bulk-apply" class="button">اعمال</button>
        </div>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // کپی کردن شورتکد
    $('.pcfb-copy-shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        navigator.clipboard.writeText(shortcode).then(function() {
            // نمایش پیام موفقیت
            const originalText = $(this).text();
            $(this).text('✅ کپی شد!');
            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        }.bind(this));
    });

    // عملیات گروهی
    $('#pcfb-bulk-apply').on('click', function() {
        const action = $('#pcfb-bulk-action').val();
        const selectedForms = $('input.pcfb-form-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedForms.length === 0) {
            alert('لطفاً حداقل یک فرم را انتخاب کنید.');
            return;
        }

        switch (action) {
            case 'export':
                pcfbExportForms(selectedForms);
                break;
            case 'duplicate':
                pcfbDuplicateForms(selectedForms);
                break;
            default:
                alert('لطفاً یک عمل را انتخاب کنید.');
        }
    });

    function pcfbExportForms(formIds) {
        // پیاده‌سازی خروجی گروهی
        alert('خروجی CSV برای فرم‌های انتخاب شده');
    }

    function pcfbDuplicateForms(formIds) {
        if (confirm('آیا از کپی‌برداری فرم‌های انتخاب شده مطمئن هستید؟')) {
            // پیاده‌سازی کپی‌برداری گروهی با AJAX
            $.post(ajaxurl, {
                action: 'pcfb_bulk_duplicate',
                form_ids: formIds,
                nonce: '<?php echo wp_create_nonce("pcfb_bulk_action"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا در کپی‌برداری: ' + response.data);
                }
            });
        }
    }

    // انتخاب تمام فرم‌ها
    $('#pcfb-select-all').on('change', function() {
        $('.pcfb-form-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>

<style>
.pcfb-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.pcfb-action-buttons .button {
    margin: 2px;
    font-size: 12px;
    padding: 4px 8px;
}

.pcfb-shortcode-info {
    background: #f8f9fa;
    padding: 5px;
    border-radius: 3px;
    border-right: 3px;
}

.pcfb-copy-shortcode {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    margin-right: 5px;
}

.pcfb-copy-shortcode:hover {
    background: #e0e0e0;
    border-radius: 3px;
}

.pcfb-status-badge {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 8px;
}

.pcfb-submission-link:hover {
    text-decoration: underline;
}

/* حالت موبایل */
@media (max-width: 782px) {
    .pcfb-action-buttons {
        flex-direction: column;
    }
    
    .pcfb-action-buttons .button {
        width: 100%;
        text-align: center;
    }
    
    .pcfb-shortcode-info {
        font-size: 12px;
    }
}
</style>