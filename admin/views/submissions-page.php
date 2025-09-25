<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// بررسی دسترسی کاربر
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.' );
}

// دریافت فرم‌های موجود
$forms = PCFB_DB::get_forms(true);
$selected_form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// دریافت ارسال‌ها با فیلتر
$submissions = [];
$all_submissions = [];

if ($selected_form_id > 0) {
    // ارسال‌های یک فرم خاص
    $all_submissions = PCFB_DB::get_submissions($selected_form_id);
} else {
    // همه ارسال‌ها از تمام فرم‌ها
    foreach ($forms as $form) {
        $form_submissions = PCFB_DB::get_submissions($form->id);
        $all_submissions = array_merge($all_submissions, $form_submissions);
    }
}

// اعمال جستجو
if (!empty($search_term)) {
    foreach ($all_submissions as $submission) {
        $form_data = json_decode($submission->form_data, true);
        $found = false;
        
        if (is_array($form_data)) {
            foreach ($form_data as $value) {
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }
                if (stripos($value, $search_term) !== false) {
                    $found = true;
                    break;
                }
            }
        }
        
        // جستجو در IP و تاریخ
        if (stripos($submission->ip_address, $search_term) !== false || 
            stripos($submission->created_at, $search_term) !== false) {
            $found = true;
        }
        
        if ($found) {
            $submissions[] = $submission;
        }
    }
} else {
    $submissions = $all_submissions;
}

// مرتب سازی بر اساس تاریخ (جدیدترین اول)
usort($submissions, function($a, $b) {
    return strtotime($b->created_at) - strtotime($a->created_at);
});

// صفحه‌بندی
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_submissions = count($submissions);
$total_pages = ceil($total_submissions / $per_page);
$offset = ($current_page - 1) * $per_page;
$submissions = array_slice($submissions, $offset, $per_page);

// بررسی actionهای مدیریت ارسال‌ها
if (isset($_GET['action']) && isset($_GET['submission_id'])) {
    $action = sanitize_text_field($_GET['action']);
    $submission_id = absint($_GET['submission_id']);
    $nonce = $_GET['_wpnonce'] ?? '';
    
    if (wp_verify_nonce($nonce, 'pcfb_submission_action')) {
        switch ($action) {
            case 'delete':
                global $wpdb;
                $table_name = $wpdb->prefix . 'pcfb_submissions';
                $result = $wpdb->delete(
                    $table_name,
                    ['id' => $submission_id],
                    ['%d']
                );
                
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>ارسال با موفقیت حذف شد.</p></div>';
                    // رفرش صفحه برای به‌روزرسانی لیست
                    echo '<script>setTimeout(function(){ window.location.href = window.location.href.split("?")[0] + "?page=pcfb-settings&tab=submissions"; }, 1000);</script>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف ارسال.</p></div>';
                }
                break;
                
            case 'delete_all':
                if ($selected_form_id > 0) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'pcfb_submissions';
                    $result = $wpdb->delete(
                        $table_name,
                        ['form_id' => $selected_form_id],
                        ['%d']
                    );
                    
                    if ($result !== false) {
                        echo '<div class="notice notice-success is-dismissible"><p>تمام ارسال‌های این فرم حذف شدند.</p></div>';
                        echo '<script>setTimeout(function(){ window.location.href = window.location.href.split("?")[0] + "?page=pcfb-settings&tab=submissions"; }, 1000);</script>';
                    }
                }
                break;
        }
    }
}
?>

<div class="wrap">
    <h1>نتایج و ارسال‌های فرم‌ها</h1>
    
    <!-- فیلتر و جستجو -->
    <div class="pcfb-submissions-filter" style="margin: 20px 0; padding: 20px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px;">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="pcfb-settings">
            <input type="hidden" name="tab" value="submissions">
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="pcfb-form-select" style="font-weight: bold;">انتخاب فرم:</label>
                <select name="form_id" id="pcfb-form-select" style="min-width: 200px;">
                    <option value="0">همه فرم‌ها</option>
                    <?php foreach ($forms as $form) : ?>
                        <option value="<?php echo esc_attr($form->id); ?>" 
                            <?php selected($selected_form_id, $form->id); ?>>
                            <?php echo esc_html($form->form_name); ?> (<?php echo $form->id; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="pcfb-search" style="font-weight: bold;">جستجو:</label>
                <input type="text" name="search" id="pcfb-search" value="<?php echo esc_attr($search_term); ?>" 
                       placeholder="جستجو در داده‌ها، IP، تاریخ..." style="min-width: 250px;">
            </div>
            
            <button type="submit" class="button button-primary">اعمال فیلتر</button>
            
            <?php if (!empty($search_term) || $selected_form_id > 0) : ?>
                <a href="?page=pcfb-settings&tab=submissions" class="button">حذف فیلتر</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- آمار کلی -->
    <div class="pcfb-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 25px 0;">
        <div class="stat-box" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 10px;">📊</div>
            <h3 style="margin: 0 0 10px 0; color: white;">تعداد کل ارسال‌ها</h3>
            <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($total_submissions); ?></p>
        </div>
        
        <div class="stat-box" style="padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 10px;">📋</div>
            <h3 style="margin: 0 0 10px 0; color: white;">فرم‌های فعال</h3>
            <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format(count($forms)); ?></p>
        </div>
        
        <div class="stat-box" style="padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 10px;">🕒</div>
            <h3 style="margin: 0 0 10px 0; color: white;">آخرین ارسال</h3>
            <p style="font-size: 14px; margin: 0;">
                <?php 
                if ($total_submissions > 0) {
                    $last_submission = $all_submissions[0] ?? null;
                    if ($last_submission) {
                        echo human_time_diff(strtotime($last_submission->created_at), current_time('timestamp')) . ' پیش';
                        echo '<br><small>' . date_i18n('Y/m/d H:i', strtotime($last_submission->created_at)) . '</small>';
                    }
                } else {
                    echo 'هنوز ارسالی وجود ندارد';
                }
                ?>
            </p>
        </div>
        
        <div class="stat-box" style="padding: 20px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 10px;">📈</div>
            <h3 style="margin: 0 0 10px 0; color: white;">میانگین روزانه</h3>
            <p style="font-size: 28px; font-weight: bold; margin: 0;">
                <?php
                if ($total_submissions > 0) {
                    $first_submission = end($all_submissions);
                    $days = max(1, round((time() - strtotime($first_submission->created_at)) / (24 * 60 * 60)));
                    echo round($total_submissions / $days, 1);
                } else {
                    echo '0';
                }
                ?>
            </p>
        </div>
    </div>

    <!-- ابزارهای سریع -->
    <?php if ($total_submissions > 0) : ?>
    <div class="pcfb-quick-actions" style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="button button-secondary" onclick="pcfbExportCSV()">
            📥 خروجی CSV (<?php echo $total_submissions; ?> مورد)
        </button>
        
        <?php if ($selected_form_id > 0) : ?>
            <a href="<?php echo wp_nonce_url(
                '?page=pcfb-settings&tab=submissions&form_id=' . $selected_form_id . '&action=delete_all',
                'pcfb_submission_action'
            ); ?>" class="button button-link-delete" 
               onclick="return confirm('⚠️ آیا از حذف تمام ارسال‌های این فرم مطمئن هستید؟ این عمل غیرقابل بازگشت است!')">
                🗑️ حذف همه ارسال‌های این فرم
            </a>
        <?php endif; ?>
        
        <span style="margin-right: auto; color: #666; font-size: 13px;">
            نمایش <?php echo number_format(count($submissions)); ?> از <?php echo number_format($total_submissions); ?> ارسال
        </span>
    </div>
    <?php endif; ?>

    <!-- جدول نتایج -->
    <div class="pcfb-submissions-table">
        <?php if ($total_submissions === 0) : ?>
            <div class="notice notice-info" style="margin: 20px 0;">
                <p>هیچ ارسالی برای نمایش وجود ندارد.</p>
                <?php if ($selected_form_id > 0) : ?>
                    <p>فرم انتخاب شده هنوز هیچ ارسالی ندارد. پس از ارسال فرم، نتایج اینجا نمایش داده می‌شوند.</p>
                <?php else : ?>
                    <p>هنوز هیچ فرمی ارسال نشده است. می‌توانید از طریق شورتکد فرم‌ها را در صفحات قرار دهید.</p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th width="4%">ID</th>
                        <th width="12%">فرم</th>
                        <th width="12%">تاریخ ارسال</th>
                        <th width="10%">IP</th>
                        <th width="45%">داده‌های ارسالی</th>
                        <th width="17%">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission) : 
                        $form_data = json_decode($submission->form_data, true);
                        $form_name = 'فرم حذف شده';
                        $form_id = $submission->form_id;
                        
                        // دریافت نام فرم
                        $form = PCFB_DB::get_form($form_id);
                        if ($form) {
                            $form_name = $form->form_name;
                        }
                        
                        // آماده‌سازی داده‌ها برای نمایش
                        $preview_text = '';
                        if (is_array($form_data) && !empty($form_data)) {
                            $preview_items = [];
                            foreach (array_slice($form_data, 0, 2) as $key => $value) {
                                if (is_array($value)) {
                                    $value = implode(', ', $value);
                                }
                                $preview_items[] = '<strong>' . esc_html($key) . ':</strong> ' . esc_html(substr($value, 0, 30));
                            }
                            $preview_text = implode(' | ', $preview_items);
                            if (count($form_data) > 2) {
                                $preview_text .= ' ...';
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong>#<?php echo esc_html($submission->id); ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo esc_html($form_name); ?></strong>
                                <br>
                                <small style="color: #666;">ID: <?php echo esc_html($form_id); ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <?php echo esc_html(date_i18n('y/m/d', strtotime($submission->created_at))); ?>
                                <br>
                                <small style="color: #666;"><?php echo esc_html(date_i18n('H:i', strtotime($submission->created_at))); ?></small>
                                <br>
                                <small style="color: #999;"><?php echo human_time_diff(strtotime($submission->created_at), current_time('timestamp')); ?> پیش</small>
                            </div>
                        </td>
                        <td>
                            <code style="background: #f1f1f1; padding: 2px 5px; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($submission->ip_address); ?>
                            </code>
                        </td>
                        <td>
                            <div class="pcfb-data-preview" style="max-height: 45px; overflow: hidden; line-height: 1.4;">
                                <?php if (!empty($preview_text)) : ?>
                                    <?php echo $preview_text; ?>
                                <?php else : ?>
                                    <em style="color: #999;">داده‌ای برای نمایش وجود ندارد</em>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <button type="button" class="button button-small" 
                                        onclick="pcfbViewSubmission(<?php echo $submission->id; ?>)"
                                        title="مشاهده جزئیات">
                                    👁️ مشاهده
                                </button>
                                
                                <a href="<?php echo wp_nonce_url(
                                    '?page=pcfb-settings&tab=submissions&action=delete&submission_id=' . $submission->id,
                                    'pcfb_submission_action'
                                ); ?>" class="button button-small button-link-delete"
                                   onclick="return confirm('آیا از حذف این ارسال مطمئن هستید؟')"
                                   title="حذف ارسال">
                                    🗑️ حذف
                                </a>
                                
                                <button type="button" class="button button-small" 
                                        onclick="pcfbCopySubmissionData(<?php echo $submission->id; ?>)"
                                        title="کپی داده‌ها">
                                    📋 کپی
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 20px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_submissions); ?> مورد</span>
                    
                    <span class="pagination-links">
                        <?php
                        $base_url = admin_url('admin.php?page=pcfb-settings&tab=submissions');
                        if ($selected_form_id > 0) $base_url .= '&form_id=' . $selected_form_id;
                        if (!empty($search_term)) $base_url .= '&search=' . urlencode($search_term);
                        
                        if ($current_page > 1) {
                            echo '<a class="prev-page" href="' . $base_url . '&paged=' . ($current_page - 1) . '">‹ قبلی</a>';
                        }
                        
                        echo '<span class="paging-input">';
                        echo '<span class="current-page">' . $current_page . '</span>';
                        echo ' از ';
                        echo '<span class="total-pages">' . $total_pages . '</span>';
                        echo '</span>';
                        
                        if ($current_page < $total_pages) {
                            echo '<a class="next-page" href="' . $base_url . '&paged=' . ($current_page + 1) . '">بعدی ›</a>';
                        }
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- مدال مشاهده جزئیات -->
<div id="pcfb-submission-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; backdrop-filter: blur(2px);">
    <div class="pcfb-modal-content" style="background: white; margin: 2% auto; padding: 0; width: 95%; max-width: 900px; max-height: 90vh; overflow: hidden; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div class="pcfb-modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e0e0e0; background: #fafafa;">
            <h3 style="margin: 0; color: #1d2327;">جزئیات کامل ارسال</h3>
            <button type="button" class="pcfb-close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 5px; border-radius: 3px;" 
                    onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='none'">
                ✕
            </button>
        </div>
        <div class="pcfb-modal-body" id="pcfb-modal-body" style="padding: 20px; max-height: calc(90vh - 100px); overflow-y: auto;">
            <p style="text-align: center; padding: 40px; color: #666;">
                <span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
                در حال بارگذاری جزئیات...
            </p>
        </div>
    </div>
</div>

<script type="text/javascript">
function pcfbViewSubmission(submissionId) {
    jQuery('#pcfb-modal-body').html('<p style="text-align: center; padding: 40px; color: #666;"><span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span> در حال بارگذاری جزئیات...</p>');
    jQuery('#pcfb-submission-modal').fadeIn(200);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'pcfb_get_submission_details',
            submission_id: submissionId,
            nonce: '<?php echo wp_create_nonce("pcfb_submission_nonce"); ?>'
        },
        success: function(response) {
            if (response.success) {
                jQuery('#pcfb-modal-body').html(response.data);
            } else {
                jQuery('#pcfb-modal-body').html(
                    '<div style="text-align: center; padding: 40px; color: #d63638;">' +
                    '<div style="font-size: 48px; margin-bottom: 20px;">❌</div>' +
                    '<h3>خطا در بارگذاری داده‌ها</h3>' +
                    '<p>' + response.data + '</p>' +
                    '</div>'
                );
            }
        },
        error: function(xhr, status, error) {
            jQuery('#pcfb-modal-body').html(
                '<div style="text-align: center; padding: 40px; color: #d63638;">' +
                '<div style="font-size: 48px; margin-bottom: 20px;">🌐</div>' +
                '<h3>خطای ارتباط با سرور</h3>' +
                '<p>لطفاً اتصال اینترنت خود را بررسی کنید و مجدداً تلاش نمایید.</p>' +
                '</div>'
            );
        }
    });
}

function pcfbExportCSV() {
    const formId = jQuery('#pcfb-form-select').val();
    const searchTerm = jQuery('#pcfb-search').val();
    
    let url = '<?php echo admin_url("admin-post.php"); ?>?action=pcfb_export_csv';
    if (formId > 0) url += '&form_id=' + formId;
    if (searchTerm) url += '&search=' + encodeURIComponent(searchTerm);
    
    window.open(url, '_blank');
}

function pcfbCopySubmissionData(submissionId) {
    // در اینجا می‌توانید قابلیت کپی داده‌ها را اضافه کنید
    alert('این قابلیت به زودی اضافه خواهد شد. شناسه ارسال: ' + submissionId);
}

// بستن مدال
jQuery(document).ready(function($) {
    $('.pcfb-close-modal').click(function() {
        $('#pcfb-submission-modal').fadeOut(200);
    });
    
    // بستن مدال با کلیک خارج از آن
    $('#pcfb-submission-modal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
    
    // بستن مدال با کلید ESC
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            $('#pcfb-submission-modal').fadeOut(200);
        }
    });
    
    // قابلیت جستجو با Enter
    $('#pcfb-search').keypress(function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });
});
</script>

<style type="text/css">
.pcfb-submissions-table th {
    font-weight: 600;
    background: #f8f9fa;
}
.pcfb-data-preview {
    font-size: 13px;
    line-height: 1.4;
}
.pcfb-data-preview strong {
    color: #1d2327;
}
.stat-box {
    transition: transform 0.2s ease;
}
.stat-box:hover {
    transform: translateY(-2px);
}
.pcfb-modal-content {
    animation: modalSlideIn 0.3s ease-out;
}
@keyframes modalSlideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 1200px) {
    .pcfb-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 782px) {
    .pcfb-stats {
        grid-template-columns: 1fr;
    }
    .pcfb-submissions-filter form {
        flex-direction: column;
        align-items: stretch;
    }
    .pcfb-submissions-filter form > div {
        margin-bottom: 10px;
    }
}
</style>