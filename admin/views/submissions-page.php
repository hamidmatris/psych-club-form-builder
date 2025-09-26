<?php
/**
 * صفحه مدیریت ارسال‌های فرم‌ها
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی کاربر
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
}

// دریافت فرم‌های موجود
$forms = PCFB_DB::get_forms(['status' => 1]);
$selected_form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// تابع دریافت ارسال‌ها
function pcfb_get_filtered_submissions($form_id = 0, $search_term = '') {
    global $wpdb;
    
    $submissions_table = $wpdb->prefix . 'pcfb_submissions';
    $forms_table = $wpdb->prefix . 'pcfb_forms';
    
    $where = [];
    $prepare_args = [];
    
    if ($form_id > 0) {
        $where[] = 's.form_id = %d';
        $prepare_args[] = $form_id;
    }
    
    if (!empty($search_term)) {
        $where[] = '(s.form_data LIKE %s OR s.ip_address LIKE %s OR s.created_at LIKE %s)';
        $prepare_args[] = '%' . $wpdb->esc_like($search_term) . '%';
        $prepare_args[] = '%' . $wpdb->esc_like($search_term) . '%';
        $prepare_args[] = '%' . $wpdb->esc_like($search_term) . '%';
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT s.*, f.form_name 
              FROM {$submissions_table} s 
              LEFT JOIN {$forms_table} f ON s.form_id = f.id 
              {$where_sql} 
              ORDER BY s.created_at DESC";
    
    if (!empty($prepare_args)) {
        $query = $wpdb->prepare($query, $prepare_args);
    }
    
    return $wpdb->get_results($query);
}

// دریافت ارسال‌ها
$all_submissions = pcfb_get_filtered_submissions($selected_form_id, $search_term);
$total_submissions = count($all_submissions);

// صفحه‌بندی
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_pages = ceil($total_submissions / $per_page);
$offset = ($current_page - 1) * $per_page;
$submissions = array_slice($all_submissions, $offset, $per_page);

// تابع محاسبه آمار
function pcfb_get_submissions_stats() {
    global $wpdb;
    
    $submissions_table = $wpdb->prefix . 'pcfb_submissions';
    
    return [
        'total_submissions' => $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table}"),
        'today_submissions' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$submissions_table} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        )
    ];
}

$stats = pcfb_get_submissions_stats();
        ?>
        
        <div class="wrap pcfb-submissions-page">
            <div class="pcfb-page-header">
                <h1 class="wp-heading-inline">
                    <span class="dashicons dashicons-email-alt"></span>
                    مدیریت ارسال‌های فرم
                </h1>
                
                <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms'); ?>" 
                   class="page-title-action">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    بازگشت به فرم‌ها
                </a>
            </div>

            <hr class="wp-header-end">

            <!-- فیلتر و جستجو -->
            <div class="pcfb-filters-card">
                <form method="get" class="pcfb-filter-form">
                    <input type="hidden" name="page" value="pcfb-settings">
                    <input type="hidden" name="tab" value="submissions">
                    
                    <div class="pcfb-filter-row">
                        <div class="pcfb-filter-group">
                            <label for="pcfb-form-select" class="pcfb-filter-label">
                                <span class="dashicons dashicons-edit-page"></span>
                                انتخاب فرم
                            </label>
                            <select name="form_id" id="pcfb-form-select" class="pcfb-filter-select">
                                <option value="0">همه فرم‌ها</option>
                                <?php foreach ($this->forms as $form) : ?>
                                    <option value="<?php echo esc_attr($form->id); ?>" 
                                        <?php selected($this->selected_form_id, $form->id); ?>>
                                        <?php echo esc_html($form->form_name); ?> (ID: <?php echo $form->id; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="pcfb-filter-group">
                            <label for="pcfb-search" class="pcfb-filter-label">
                                <span class="dashicons dashicons-search"></span>
                                جستجو
                            </label>
                            <input type="text" name="search" id="pcfb-search" 
                                   value="<?php echo esc_attr($this->search_term); ?>" 
                                   placeholder="جستجو در داده‌ها، IP، تاریخ..." 
                                   class="pcfb-filter-input">
                        </div>
                        
                        <div class="pcfb-filter-actions">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-filter"></span>
                                اعمال فیلتر
                            </button>
                            
                            <?php if (!empty($this->search_term) || $this->selected_form_id > 0) : ?>
                                <a href="?page=pcfb-settings&tab=submissions" class="button">
                                    <span class="dashicons dashicons-no"></span>
                                    حذف فیلتر
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- کارت‌های آمار -->
            <div class="pcfb-stats-grid">
                <div class="pcfb-stat-card pcfb-stat-primary">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo number_format($total_submissions); ?></span>
                        <span class="stat-label">کل ارسال‌ها</span>
                    </div>
                </div>
                
                <div class="pcfb-stat-card pcfb-stat-success">
                    <div class="stat-icon">📋</div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo number_format(count($this->forms)); ?></span>
                        <span class="stat-label">فرم‌های فعال</span>
                    </div>
                </div>
                
                <div class="pcfb-stat-card pcfb-stat-info">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo number_format($stats['today_submissions']); ?></span>
                        <span class="stat-label">ارسال‌های امروز</span>
                    </div>
                </div>
                
                <div class="pcfb-stat-card pcfb-stat-warning">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-content">
                        <span class="stat-number">
                            <?php echo $this->get_daily_average($total_submissions); ?>
                        </span>
                        <span class="stat-label">میانگین روزانه</span>
                    </div>
                </div>
            </div>

            <!-- ابزارهای سریع -->
            <?php if ($total_submissions > 0) : ?>
            <div class="pcfb-quick-actions">
                <div class="pcfb-actions-left">
                    <button type="button" class="button button-secondary" onclick="pcfbExportCSV()">
                        <span class="dashicons dashicons-download"></span>
                        خروجی CSV (<?php echo $total_submissions; ?> مورد)
                    </button>
                    
                    <?php if ($this->selected_form_id > 0) : ?>
                        <a href="<?php echo wp_nonce_url(
                            '?page=pcfb-settings&tab=submissions&form_id=' . $this->selected_form_id . '&action=delete_all',
                            'pcfb_submission_action'
                        ); ?>" class="button button-danger" 
                           onclick="return confirm('⚠️ آیا از حذف تمام ارسال‌های این فرم مطمئن هستید؟ این عمل غیرقابل بازگشت است!')">
                            <span class="dashicons dashicons-trash"></span>
                            حذف همه ارسال‌های این فرم
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="pcfb-actions-right">
                    <span class="pcfb-results-count">
                        نمایش <?php echo number_format(count($paginated_submissions)); ?> از <?php echo number_format($total_submissions); ?> ارسال
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- جدول نتایج -->
            <div class="pcfb-submissions-table">
                <?php if ($total_submissions === 0) : ?>
                    <div class="pcfb-empty-state">
                        <div class="empty-content">
                            <div class="empty-icon">📭</div>
                            <h2>هیچ ارسالی برای نمایش وجود ندارد</h2>
                            <p>
                                <?php if ($this->selected_form_id > 0) : ?>
                                    فرم انتخاب شده هنوز هیچ ارسالی ندارد. پس از ارسال فرم، نتایج اینجا نمایش داده می‌شوند.
                                <?php else : ?>
                                    هنوز هیچ فرمی ارسال نشده است. می‌توانید از طریق شورتکد فرم‌ها را در صفحات قرار دهید.
                                <?php endif; ?>
                            </p>
                            <div class="empty-actions">
                                <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms'); ?>" 
                                   class="button button-primary">
                                    مدیریت فرم‌ها
                                </a>
                                <a href="https://example.com/docs" target="_blank" class="button">
                                    راهنمای استفاده
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="column-id">ID</th>
                                <th class="column-form">فرم</th>
                                <th class="column-date">تاریخ ارسال</th>
                                <th class="column-ip">IP</th>
                                <th class="column-data">داده‌های ارسالی</th>
                                <th class="column-actions">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_submissions as $submission) : 
                                $this->render_submission_row($submission);
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- صفحه‌بندی -->
                    <?php $this->render_pagination($total_pages, $total_submissions); ?>
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
        
        <?php
        $this->enqueue_scripts();
    }
    
    private function render_submission_row($submission) {
        $form_data = json_decode($submission->form_data, true);
        $form = PCFB_DB::get_form($submission->form_id);
        $form_name = $form ? $form->form_name : 'فرم حذف شده';
        $preview_text = $this->get_data_preview($form_data);
        ?>
        
        <tr>
            <td class="column-id">
                <strong>#<?php echo esc_html($submission->id); ?></strong>
            </td>
            
            <td class="column-form">
                <div class="form-info">
                    <strong class="form-name"><?php echo esc_html($form_name); ?></strong>
                    <br>
                    <small class="form-id">ID: <?php echo esc_html($submission->form_id); ?></small>
                </div>
            </td>
            
            <td class="column-date">
                <div class="date-info">
                    <span class="date-human">
                        <?php echo human_time_diff(strtotime($submission->created_at), current_time('timestamp')) . ' پیش'; ?>
                    </span>
                    <br>
                    <small class="date-exact">
                        <?php echo date_i18n('Y/m/d - H:i', strtotime($submission->created_at)); ?>
                    </small>
                </div>
            </td>
            
            <td class="column-ip">
                <code class="ip-address"><?php echo esc_html($submission->ip_address); ?></code>
            </td>
            
            <td class="column-data">
                <div class="data-preview">
                    <?php if (!empty($preview_text)) : ?>
                        <?php echo $preview_text; ?>
                    <?php else : ?>
                        <em class="no-data">داده‌ای برای نمایش وجود ندارد</em>
                    <?php endif; ?>
                </div>
            </td>
            
            <td class="column-actions">
                <div class="action-buttons">
                    <button type="button" class="button button-small button-primary view-submission" 
                            data-submission-id="<?php echo $submission->id; ?>"
                            title="مشاهده جزئیات">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    
                    <a href="<?php echo wp_nonce_url(
                        '?page=pcfb-settings&tab=submissions&action=delete&submission_id=' . $submission->id,
                        'pcfb_submission_action'
                    ); ?>" class="button button-small button-danger"
                       onclick="return confirm('آیا از حذف این ارسال مطمئن هستید؟')"
                       title="حذف ارسال">
                        <span class="dashicons dashicons-trash"></span>
                    </a>
                    
                    <button type="button" class="button button-small copy-submission" 
                            data-submission-id="<?php echo $submission->id; ?>"
                            title="کپی داده‌ها">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }
    
    private function get_data_preview($form_data) {
        if (!is_array($form_data) || empty($form_data)) {
            return '';
        }
        
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
        
        return $preview_text;
    }
    
    private function render_pagination($total_pages, $total_submissions) {
        if ($total_pages <= 1) return;
        ?>
        
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format($total_submissions); ?> مورد</span>
                
                <span class="pagination-links">
                    <?php
                    $base_url = $this->get_base_url();
                    
                    if ($this->current_page > 1) {
                        echo '<a class="prev-page" href="' . $base_url . '&paged=' . ($this->current_page - 1) . '">‹ قبلی</a>';
                    }
                    
                    echo '<span class="paging-input">';
                    echo '<span class="current-page">' . $this->current_page . '</span>';
                    echo ' از ';
                    echo '<span class="total-pages">' . $total_pages . '</span>';
                    echo '</span>';
                    
                    if ($this->current_page < $total_pages) {
                        echo '<a class="next-page" href="' . $base_url . '&paged=' . ($this->current_page + 1) . '">بعدی ›</a>';
                    }
                    ?>
                </span>
            </div>
        </div>
        <?php
    }
    
    private function get_base_url() {
        $base_url = admin_url('admin.php?page=pcfb-settings&tab=submissions');
        
        if ($this->selected_form_id > 0) {
            $base_url .= '&form_id=' . $this->selected_form_id;
        }
        
        if (!empty($this->search_term)) {
            $base_url .= '&search=' . urlencode($this->search_term);
        }
        
        return $base_url;
    }
    
    private function render_modal() {
        ?>
        <div id="pcfb-submission-modal" class="pcfb-modal">
            <div class="pcfb-modal-overlay"></div>
            <div class="pcfb-modal-content">
                <div class="pcfb-modal-header">
                    <h3>جزئیات کامل ارسال</h3>
                    <button type="button" class="pcfb-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="pcfb-modal-body" id="pcfb-modal-body">
                    <div class="pcfb-loading">
                        <span class="spinner is-active"></span>
                        در حال بارگذاری جزئیات...
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function enqueue_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // مشاهده جزئیات ارسال
            $('.view-submission').on('click', function() {
                const submissionId = $(this).data('submission-id');
                pcfbViewSubmission(submissionId);
            });
            
            // کپی داده‌های ارسال
            $('.copy-submission').on('click', function() {
                const submissionId = $(this).data('submission-id');
                pcfbCopySubmissionData(submissionId);
            });
            
            // مدیریت مدال
            $('.pcfb-modal-close, .pcfb-modal-overlay').on('click', function() {
                $('#pcfb-submission-modal').removeClass('active');
            });
            
            // بستن مدال با ESC
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    $('#pcfb-submission-modal').removeClass('active');
                }
            });
        });
        
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
            const formId = $('#pcfb-form-select').val();
            const searchTerm = $('#pcfb-search').val();
            
            let url = '<?php echo admin_url("admin-post.php"); ?>?action=pcfb_export_csv';
            if (formId > 0) url += '&form_id=' + formId;
            if (searchTerm) url += '&search=' + encodeURIComponent(searchTerm);
            
            window.open(url, '_blank');
        }
        </script>
        <?php
    }
    
    private function get_daily_average($total_submissions) {
        if ($total_submissions === 0) return '0';
        
        $first_submission = PCFB_DB::get_oldest_submission();
        if (!$first_submission) return '0';
        
        $days = max(1, round((time() - strtotime($first_submission->created_at)) / (24 * 60 * 60)));
        return round($total_submissions / $days, 1);
    }
    
    private function show_message($message, $type = 'success') {
        $class = $type === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

// راه‌اندازی صفحه
new PCFB_Submissions_Page();