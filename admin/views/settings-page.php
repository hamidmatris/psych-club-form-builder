<?php
/**
 * صفحه تنظیمات عمومی افزونه
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی کاربر
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
}

// دریافت تنظیمات فعلی با مقادیر پیش‌فرض
$settings = [
    'enable_anti_spam' => get_option('pcfb_enable_anti_spam', true),
    'submission_limit' => get_option('pcfb_submission_limit', 10),
    'time_frame' => get_option('pcfb_time_frame', 3600),
    'enable_email_notifications' => get_option('pcfb_enable_email_notifications', false),
    'admin_email' => get_option('pcfb_admin_email', get_option('admin_email')),
    'save_submissions' => get_option('pcfb_save_submissions', true)
];

// پردازش فرم ارسال شده
if (isset($_POST['pcfb_save_settings']) && check_admin_referer('pcfb_save_settings', 'pcfb_nonce')) {
    
    // اعتبارسنجی و sanitize داده‌ها
    $new_settings = [
        'enable_anti_spam' => isset($_POST['enable_anti_spam']),
        'enable_email_notifications' => isset($_POST['enable_email_notifications']),
        'save_submissions' => isset($_POST['save_submissions']),
        'admin_email' => sanitize_email($_POST['admin_email'] ?? $settings['admin_email'])
    ];

    // اعتبارسنجی ایمیل
    if (!is_email($new_settings['admin_email'])) {
        $new_settings['admin_email'] = get_option('admin_email');
        pcfb_add_admin_notice('ایمیل وارد شده معتبر نیست. از ایمیل پیشفرض مدیر استفاده شد.', 'warning');
    }

    // تنظیمات ضد اسپم
    if ($new_settings['enable_anti_spam']) {
        $new_settings['submission_limit'] = max(1, min(100, intval($_POST['submission_limit'] ?? 10)));
        $new_settings['time_frame'] = max(60, min(86400, intval($_POST['time_frame'] ?? 60) * 60));
    } else {
        $new_settings['submission_limit'] = $settings['submission_limit'];
        $new_settings['time_frame'] = $settings['time_frame'];
    }

    // ذخیره تنظیمات
    foreach ($new_settings as $key => $value) {
        update_option("pcfb_{$key}", $value);
    }

    $settings = array_merge($settings, $new_settings);
    pcfb_add_admin_notice('تنظیمات با موفقیت ذخیره شد.', 'success');
}

// تابع محاسبه آمار سیستم
function pcfb_get_system_stats() {
    global $wpdb;
    
    $forms_table = $wpdb->prefix . 'pcfb_forms';
    $submissions_table = $wpdb->prefix . 'pcfb_submissions';
    
    $stats = [
        'total_forms' => $wpdb->get_var("SELECT COUNT(*) FROM {$forms_table} WHERE status = 1"),
        'total_submissions' => $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table}"),
        'today_submissions' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$submissions_table} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        )
    ];
    
    return $stats;
}

// محاسبه آمار
$stats = pcfb_get_system_stats();

// تابع کمکی برای تبدیل زمان
function pcfb_format_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . ' ساعت' . ($minutes > 0 ? ' و ' . $minutes . ' دقیقه' : '');
    }
    return $minutes . ' دقیقه';
}

// تابع نمایش اعلان
function pcfb_add_admin_notice($message, $type = 'success') {
    add_action('admin_notices', function() use ($message, $type) {
        $class = $type === 'error' ? 'notice-error' : ($type === 'warning' ? 'notice-warning' : 'notice-success');
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    });
}
?>

<div class="wrap pcfb-settings">
    <h1 class="pcfb-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        تنظیمات عمومی فرم‌ساز
    </h1>

    <!-- آمار سریع -->
    <div class="pcfb-stats-grid">
        <div class="pcfb-stat-card pcfb-stat-forms">
            <div class="stat-icon">📋</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($stats['total_forms']); ?></span>
                <span class="stat-label">فرم‌های فعال</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card pcfb-stat-submissions">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($stats['total_submissions']); ?></span>
                <span class="stat-label">کل ارسال‌ها</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card pcfb-stat-today">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($stats['today_submissions']); ?></span>
                <span class="stat-label">ارسال‌های امروز</span>
            </div>
        </div>
        
        <div class="pcfb-stat-card pcfb-stat-status">
            <div class="stat-icon">⚙️</div>
            <div class="stat-content">
                <span class="stat-text"><?php echo $settings['enable_anti_spam'] ? '🟢 فعال' : '🔴 غیرفعال'; ?></span>
                <span class="stat-label">وضعیت سیستم</span>
            </div>
        </div>
    </div>

    <form method="post" class="pcfb-settings-form">
        <?php wp_nonce_field('pcfb_save_settings', 'pcfb_nonce'); ?>
        
        <!-- بخش امنیت -->
        <div class="pcfb-settings-section card">
            <h2 class="pcfb-section-title">
                <span class="dashicons dashicons-shield"></span>
                امنیت و حفاظت
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">سیستم ضد اسپم</th>
                    <td>
                        <label class="pcfb-toggle">
                            <input type="checkbox" name="enable_anti_spam" value="1" 
                                <?php checked($settings['enable_anti_spam'], true); ?>
                                onchange="pcfbToggleAntiSpam(this.checked)" />
                            <span class="slider"></span>
                            <span class="toggle-label">فعال کردن محدودیت ارسال</span>
                        </label>
                        <p class="description">
                            جلوگیری از ارسال تعداد زیاد فرم توسط یک کاربر در بازه زمانی کوتاه
                        </p>
                    </td>
                </tr>
                
                <tr class="anti-spam-settings" style="<?php echo !$settings['enable_anti_spam'] ? 'display: none;' : ''; ?>">
                    <th scope="row">تنظیمات محدودیت</th>
                    <td>
                        <div class="pcfb-limit-settings">
                            <input type="number" name="submission_limit" 
                                value="<?php echo esc_attr($settings['submission_limit']); ?>" 
                                min="1" max="100" class="small-text" />
                            <span>فرم در هر</span>
                            <input type="number" name="time_frame" 
                                value="<?php echo esc_attr($settings['time_frame'] / 60); ?>" 
                                min="1" max="1440" class="small-text" />
                            <span>دقیقه</span>
                        </div>
                        <p class="description">
                            هر کاربر می‌تواند حداکثر 
                            <strong><?php echo $settings['submission_limit']; ?></strong> 
                            فرم در هر 
                            <strong><?php echo pcfb_format_time($settings['time_frame']); ?></strong> 
                            ارسال کند.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- بخش اعلان‌ها -->
        <div class="pcfb-settings-section card">
            <h2 class="pcfb-section-title">
                <span class="dashicons dashicons-email"></span>
                اعلان‌های ایمیلی
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">اعلان ارسال جدید</th>
                    <td>
                        <label class="pcfb-toggle">
                            <input type="checkbox" name="enable_email_notifications" value="1" 
                                <?php checked($settings['enable_email_notifications'], true); ?> />
                            <span class="slider"></span>
                            <span class="toggle-label">فعال کردن اعلان ایمیلی</span>
                        </label>
                        <p class="description">
                            دریافت ایمیل هنگام ارسال هر فرم جدید
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">ایمیل دریافت‌کننده</th>
                    <td>
                        <input type="email" name="admin_email" 
                            value="<?php echo esc_attr($settings['admin_email']); ?>" 
                            class="regular-text" placeholder="admin@example.com" />
                        <p class="description">
                            آدرس ایمیل برای دریافت اعلان‌ها
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- بخش مدیریت داده‌ها -->
        <div class="pcfb-settings-section card">
            <h2 class="pcfb-section-title">
                <span class="dashicons dashicons-database"></span>
                مدیریت داده‌ها
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">ذخیره‌سازی ارسال‌ها</th>
                    <td>
                        <label class="pcfb-toggle">
                            <input type="checkbox" name="save_submissions" value="1" 
                                <?php checked($settings['save_submissions'], true); ?> />
                            <span class="slider"></span>
                            <span class="toggle-label">ذخیره ارسال‌ها در دیتابیس</span>
                        </label>
                        <p class="description">
                            در صورت غیرفعال کردن، داده‌های فرم‌ها ذخیره نخواهند شد
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">آمار ذخیره‌سازی</th>
                    <td>
                        <div class="pcfb-storage-stats">
                            <span>فرم‌ها: <strong><?php echo number_format($stats['total_forms']); ?></strong></span>
                            <span>ارسال‌ها: <strong><?php echo number_format($stats['total_submissions']); ?></strong></span>
                            <span>امروز: <strong><?php echo number_format($stats['today_submissions']); ?></strong></span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pcfb-form-actions">
            <button type="submit" name="pcfb_save_settings" class="button button-primary button-large">
                <span class="dashicons dashicons-yes"></span>
                ذخیره تنظیمات
            </button>
            
            <button type="button" class="button button-large" onclick="pcfbResetSettings()">
                <span class="dashicons dashicons-update"></span>
                بازنشانی به پیش‌فرض
            </button>
        </div>
    </form>

    <!-- ابزارهای پیشرفته -->
    <div class="pcfb-advanced-tools">
        <h2 class="pcfb-tools-title">
            <span class="dashicons dashicons-admin-tools"></span>
            ابزارهای پیشرفته
        </h2>
        
        <div class="pcfb-tools-grid">
            <div class="pcfb-tool-card">
                <h3>پاکسازی داده‌ها</h3>
                <p>حذف ارسال‌های قدیمی برای آزادسازی فضای دیتابیس</p>
                <button type="button" class="button button-secondary" onclick="pcfbCleanupData()">
                    پاکسازی داده‌ها
                </button>
            </div>
            
            <div class="pcfb-tool-card">
                <h3>خروجی داده‌ها</h3>
                <p>دانلود تمام داده‌ها به صورت فایل Excel/CSV</p>
                <button type="button" class="button button-secondary" onclick="pcfbExportData()">
                    خروجی کامل
                </button>
            </div>
            
            <div class="pcfb-tool-card">
                <h3>سیستم گزارش‌ها</h3>
                <p>مشاهده گزارش‌های سیستم و خطاها</p>
                <button type="button" class="button button-secondary" onclick="pcfbViewReports()">
                    مشاهده گزارش‌ها
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.pcfb-settings {
    max-width: 1200px;
}

.pcfb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.pcfb-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.pcfb-stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    font-size: 40px;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    line-height: 1;
}

.stat-text {
    font-size: 20px;
    font-weight: bold;
}

.stat-label {
    opacity: 0.9;
    font-size: 14px;
}

.pcfb-settings-section {
    margin-bottom: 30px;
    padding: 25px;
}

.pcfb-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 0;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.pcfb-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.pcfb-toggle input {
    display: none;
}

.slider {
    width: 50px;
    height: 24px;
    background: #ccc;
    border-radius: 24px;
    position: relative;
    transition: 0.3s;
}

.slider:before {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    top: 2px;
    left: 2px;
    transition: 0.3s;
}

input:checked + .slider {
    background: #0073aa;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.pcfb-limit-settings {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pcfb-storage-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.pcfb-form-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    margin: 30px 0;
}

.pcfb-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.pcfb-tool-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #ccd0d4;
}

.pcfb-tool-card h3 {
    margin-top: 0;
    color: #1d2327;
}

@media (max-width: 768px) {
    .pcfb-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .pcfb-tools-grid {
        grid-template-columns: 1fr;
    }
    
    .pcfb-limit-settings {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pcfb-form-actions {
        flex-direction: column;
    }
}
</style>

<script>
function pcfbToggleAntiSpam(enabled) {
    const settings = document.querySelector('.anti-spam-settings');
    if (settings) {
        settings.style.display = enabled ? '' : 'none';
    }
}

function pcfbResetSettings() {
    if (confirm('آیا از بازنشانی تنظیمات به مقادیر پیش‌فرض مطمئن هستید؟')) {
        window.location.href = '<?php echo admin_url('admin.php?page=pcfb-settings&tab=general&action=reset'); ?>';
    }
}

function pcfbCleanupData() {
    if (confirm('آیا از پاکسازی داده‌های قدیمی مطمئن هستید؟')) {
        alert('این ویژگی به زودی فعال خواهد شد.');
    }
}

function pcfbExportData() {
    alert('در حال آماده‌سازی خروجی...');
}

function pcfbViewReports() {
    alert('سیستم گزارش‌ها به زودی فعال خواهد شد.');
}

// اعتبارسنجی فرم
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.pcfb-settings-form');
    form.addEventListener('submit', function(e) {
        const antiSpamEnabled = form.querySelector('input[name="enable_anti_spam"]').checked;
        
        if (antiSpamEnabled) {
            const limit = parseInt(form.querySelector('input[name="submission_limit"]').value);
            const timeFrame = parseInt(form.querySelector('input[name="time_frame"]').value);
            
            if (limit < 1 || limit > 100) {
                alert('محدودیت ارسال باید بین 1 تا 100 باشد.');
                e.preventDefault();
                return false;
            }
            
            if (timeFrame < 1 || timeFrame > 1440) {
                alert('بازه زمانی باید بین 1 تا 1440 دقیقه باشد.');
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>