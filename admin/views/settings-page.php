<<<<<<< HEAD
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// بررسی دسترسی کاربر
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.' );
}

// دریافت تنظیمات فعلی
$enable_anti_spam = get_option( 'pcfb_enable_anti_spam', true );
$submission_limit = get_option( 'pcfb_submission_limit', 10 );
$time_frame = get_option( 'pcfb_time_frame', 3600 ); // 1 ساعت به ثانیه
$enable_email_notifications = get_option( 'pcfb_enable_email_notifications', false );
$admin_email = get_option( 'pcfb_admin_email', get_option( 'admin_email' ) );

// ذخیره تنظیمات اگر فرم ارسال شده
if ( isset( $_POST['pcfb_save_settings'] ) ) {
    // بررسی nonce
    if ( ! isset( $_POST['pcfb_settings_nonce'] ) || ! wp_verify_nonce( $_POST['pcfb_settings_nonce'], 'pcfb_save_settings' ) ) {
        wp_die( 'خطای امنیتی' );
    }

    // ذخیره تنظیمات - استفاده از isset برای جلوگیری از خطا
    $enable_anti_spam = isset( $_POST['pcfb_enable_anti_spam'] ) ? true : false;
    
    // اگر سیستم ضد اسپم فعال است، مقادیر را از فرم بگیرید، در غیر این صورت از مقادیر قبلی استفاده کنید
    if ( $enable_anti_spam ) {
        $submission_limit = isset( $_POST['pcfb_submission_limit'] ) ? absint( $_POST['pcfb_submission_limit'] ) : $submission_limit;
        $time_frame = isset( $_POST['pcfb_time_frame'] ) ? absint( $_POST['pcfb_time_frame'] ) * 60 : $time_frame; // تبدیل به ثانیه
    } else {
        // اگر غیرفعال است، همان مقادیر قبلی را نگه دار
        $submission_limit = get_option( 'pcfb_submission_limit', 10 );
        $time_frame = get_option( 'pcfb_time_frame', 3600 );
    }
    
    $enable_email_notifications = isset( $_POST['pcfb_enable_email_notifications'] ) ? true : false;
    $admin_email = isset( $_POST['pcfb_admin_email'] ) ? sanitize_email( $_POST['pcfb_admin_email'] ) : $admin_email;

    // اعتبارسنجی
    if ( $submission_limit < 1 ) {
        $submission_limit = 1;
    }
    
    if ( $time_frame < 60 ) {
        $time_frame = 60; // حداقل 1 دقیقه
    }

    if ( ! is_email( $admin_email ) ) {
        $admin_email = get_option( 'admin_email' );
        echo '<div class="notice notice-warning is-dismissible"><p>ایمیل وارد شده معتبر نیست. از ایمیل پیشفرض مدیر استفاده شد.</p></div>';
    }

    // ذخیره در دیتابیس
    update_option( 'pcfb_enable_anti_spam', $enable_anti_spam );
    update_option( 'pcfb_submission_limit', $submission_limit );
    update_option( 'pcfb_time_frame', $time_frame );
    update_option( 'pcfb_enable_email_notifications', $enable_email_notifications );
    update_option( 'pcfb_admin_email', $admin_email );

    echo '<div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
}

// محاسبه آمار
$forms = PCFB_DB::get_forms( true );
$total_forms = count( $forms );
$total_submissions = 0;
$today_submissions = 0;

foreach ( $forms as $form ) {
    $submissions = PCFB_DB::get_submissions( $form->id );
    $total_submissions += count( $submissions );
    
    // ارسال‌های امروز
    $today_start = date( 'Y-m-d 00:00:00' );
    foreach ( $submissions as $submission ) {
        if ( strtotime( $submission->created_at ) >= strtotime( $today_start ) ) {
            $today_submissions++;
        }
    }
}

// تبدیل زمان به متن قابل فهم
function pcfb_time_to_text( $seconds ) {
    $hours = floor( $seconds / 3600 );
    $minutes = floor( ( $seconds % 3600 ) / 60 );
    
    if ( $hours > 0 ) {
        return $hours . ' ساعت' . ( $minutes > 0 ? ' و ' . $minutes . ' دقیقه' : '' );
    } else {
        return $minutes . ' دقیقه';
    }
}

// تبدیل ثانیه به دقیقه برای نمایش در فرم
$time_frame_minutes = $time_frame / 60;
?>

<div class="wrap">
    <h1>تنظیمات عمومی فرم‌ساز</h1>
    
    <div class="pcfb-settings-container">
        <!-- آمار سریع -->
        <div class="pcfb-quick-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 10px;">📋</div>
                <h3 style="margin: 0 0 10px 0; color: white;">فرم‌های فعال</h3>
                <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format( $total_forms ); ?></p>
            </div>
            
            <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 10px;">📊</div>
                <h3 style="margin: 0 0 10px 0; color: white;">کل ارسال‌ها</h3>
                <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format( $total_submissions ); ?></p>
            </div>
            
            <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                <h3 style="margin: 0 0 10px 0; color: white;">ارسال‌های امروز</h3>
                <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format( $today_submissions ); ?></p>
            </div>
            
            <div class="stat-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 10px;">⚙️</div>
                <h3 style="margin: 0 0 10px 0; color: white;">وضعیت سیستم</h3>
                <p style="font-size: 16px; font-weight: bold; margin: 0;">
                    <?php echo $enable_anti_spam ? '🟢 فعال' : '🔴 غیرفعال'; ?>
                </p>
            </div>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'pcfb_save_settings', 'pcfb_settings_nonce' ); ?>
            
            <div class="pcfb-settings-sections">
                <!-- بخش حفاظت ضد اسپم -->
                <div class="pcfb-settings-section" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        🛡️ حفاظت ضد اسپم
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 300px;">فعالسازی حفاظت ضد اسپم</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="pcfb_enable_anti_spam" value="1" 
                                        <?php checked( $enable_anti_spam, true ); ?> 
                                        onchange="toggleAntiSpamFields(this.checked)" />
                                    فعال کردن سیستم محدودیت ارسال
                                </label>
                                <p class="description">
                                    این ویژگی از ارسال تعداد زیاد فرم توسط یک کاربر در بازه زمانی کوتاه جلوگیری می‌کند.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">محدودیت ارسال</th>
                            <td>
                                <input type="number" name="pcfb_submission_limit" id="pcfb_submission_limit" 
                                       value="<?php echo esc_attr( $submission_limit ); ?>" 
                                       min="1" max="100" step="1" style="width: 100px;" 
                                       <?php echo ! $enable_anti_spam ? 'disabled' : ''; ?> />
                                <span>فرم در هر</span>
                                
                                <input type="number" name="pcfb_time_frame" id="pcfb_time_frame" 
                                       value="<?php echo esc_attr( $time_frame_minutes ); ?>" 
                                       min="1" max="1440" step="1" style="width: 100px; margin: 0 5px;"
                                       <?php echo ! $enable_anti_spam ? 'disabled' : ''; ?> />
                                <span>دقیقه</span>
                                
                                <p class="description">
                                    هر کاربر می‌تواند حداکثر 
                                    <strong><?php echo $submission_limit; ?></strong> 
                                    فرم در هر 
                                    <strong><?php echo pcfb_time_to_text( $time_frame ); ?></strong> 
                                    ارسال کند.
                                    <?php if ( ! $enable_anti_spam ) : ?>
                                        <br><span style="color: #d63638;">⚠️ سیستم ضد اسپم غیرفعال است</span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">پیام خطای محدودیت</th>
                            <td>
                                <textarea class="large-text code" rows="2" readonly 
                                    style="background: #f6f7f7; color: #666; width: 100%;"
                                >شما تعداد زیادی فرم ارسال کرده‌اید. لطفاً <?php echo pcfb_time_to_text( $time_frame ); ?> دیگر مجدداً تلاش کنید.</textarea>
                                <p class="description">
                                    این پیام هنگام رسیدن به محدودیت ارسال به کاربر نمایش داده می‌شود.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- بخش اعلان‌ها -->
                <div class="pcfb-settings-section" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        📧 اعلان‌های ایمیلی
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 300px;">دریافت ایمیل برای ارسال‌های جدید</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="pcfb_enable_email_notifications" value="1" 
                                        <?php checked( $enable_email_notifications, true ); ?> />
                                    فعال کردن اعلان ایمیلی
                                </label>
                                <p class="description">
                                    با فعال کردن این گزینه، هنگام ارسال هر فرم جدید ایمیل通知 دریافت خواهید کرد.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">ایمیل دریافت‌کننده</th>
                            <td>
                                <input type="email" name="pcfb_admin_email" value="<?php echo esc_attr( $admin_email ); ?>" 
                                       class="regular-text" placeholder="admin@example.com" style="width: 300px;" />
                                <p class="description">
                                    ایمیل‌هایی که باید اعلان دریافت کنند (می‌توانید چند ایمیل را با کاما جدا کنید)
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- بخش مدیریت داده‌ها -->
                <div class="pcfb-settings-section" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        🗃️ مدیریت داده‌ها
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 300px;">ذخیره‌سازی داده‌ها</th>
                            <td>
                                <div style="background: #f6f7f7; padding: 15px; border-radius: 4px;">
                                    <strong>وضعیت فعلی:</strong> 
                                    <span style="color: #00a32a;">✅ فعال</span>
                                    <br>
                                    <small>تمام ارسال‌های فرم در دیتابیس ذخیره می‌شوند.</small>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">آمار ذخیره‌سازی</th>
                            <td>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                    <div style="background: #f0f6ff; padding: 10px; border-radius: 4px; text-align: center;">
                                        <strong>فرم‌ها</strong><br>
                                        <?php echo number_format( $total_forms ); ?>
                                    </div>
                                    <div style="background: #f0f6ff; padding: 10px; border-radius: 4px; text-align: center;">
                                        <strong>ارسال‌ها</strong><br>
                                        <?php echo number_format( $total_submissions ); ?>
                                    </div>
                                    <div style="background: #f0f6ff; padding: 10px; border-radius: 4px; text-align: center;">
                                        <strong>امروز</strong><br>
                                        <?php echo number_format( $today_submissions ); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <p class="submit">
                <button type="submit" name="pcfb_save_settings" class="button button-primary button-large">
                    💾 ذخیره تنظیمات
                </button>
                <button type="button" class="button button-large" onclick="pcfbResetSettings()">
                    🔄 بازنشانی به پیش‌فرض
                </button>
            </p>
        </form>

        <!-- ابزارهای پیشرفته -->
        <div class="pcfb-advanced-tools" style="margin-top: 30px;">
            <h2>🛠️ ابزارهای پیشرفته</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0;">پاکسازی داده‌ها</h3>
                    <p>حذف ارسال‌های قدیمی برای آزادسازی فضای دیتابیس.</p>
                    <button type="button" class="button button-secondary" onclick="pcfbCleanupData()">
                        🗑️ پاکسازی داده‌های قدیمی
                    </button>
                </div>
                
                <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0;">خروجی کلی</h3>
                    <p>دانلود تمام داده‌ها به صورت فایل Excel/CSV.</p>
                    <button type="button" class="button button-secondary" onclick="pcfbExportAllData()">
                        📥 خروجی کامل داده‌ها
                    </button>
                </div>
                
                <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0;">سیستم لاگ</h3>
                    <p>مشاهده گزارش‌های سیستم و خطاها.</p>
                    <button type="button" class="button button-secondary" onclick="pcfbViewLogs()">
                        📋 مشاهده لاگ‌ها
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function toggleAntiSpamFields(isEnabled) {
    document.getElementById('pcfb_submission_limit').disabled = !isEnabled;
    document.getElementById('pcfb_time_frame').disabled = !isEnabled;
}

function pcfbResetSettings() {
    if (confirm('آیا از بازنشانی تنظیمات به مقادیر پیش‌فرض مطمئن هستید؟')) {
        if (confirm('این عمل تمام تنظیمات فعلی را پاک خواهد کرد. ادامه می‌دهید؟')) {
            window.location.href = '<?php echo admin_url('admin.php?page=pcfb-settings&tab=general&reset_settings=1'); ?>';
        }
    }
}

function pcfbCleanupData() {
    if (confirm('آیا از پاکسازی داده‌های قدیمی مطمئن هستید؟ این عمل قابل بازگشت نیست.')) {
        alert('این ویژگی به زودی فعال خواهد شد.');
    }
}

function pcfbExportAllData() {
    alert('در حال آماده‌سازی خروجی کامل...');
}

function pcfbViewLogs() {
    alert('سیستم لاگ به زودی فعال خواهد شد.');
}

// اعتبارسنجی فرم قبل از ارسال
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        var enableAntiSpam = document.querySelector('input[name="pcfb_enable_anti_spam"]').checked;
        
        if (enableAntiSpam) {
            var submissionLimit = document.getElementById('pcfb_submission_limit').value;
            var timeFrame = document.getElementById('pcfb_time_frame').value;
            
            if (submissionLimit < 1 || submissionLimit > 100) {
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

<style type="text/css">
.pcfb-settings-section h2 {
    display: flex;
    align-items: center;
    gap: 10px;
}
.stat-box {
    transition: transform 0.2s ease;
}
.stat-box:hover {
    transform: translateY(-2px);
}
.card {
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media (max-width: 782px) {
    .pcfb-quick-stats {
        grid-template-columns: 1fr;
    }
    .pcfb-advanced-tools > div {
        grid-template-columns: 1fr;
    }
    .pcfb-settings-section {
        padding: 15px;
    }
}
</style>
=======
<h2>تنظیمات عمومی</h2>
<p>اینجا می‌توانید تنظیمات کلی افزونه را انجام دهید.</p>
>>>>>>> 790f10da24534e457f5891ff27315d2c30e0e07d
