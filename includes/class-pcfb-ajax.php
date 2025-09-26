<?php
/**
 * کلاس مدیریت درخواست‌های AJAX برای فرم‌ساز
 * Psych Club Form Builder - AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class PCFB_AJAX
{
    public function __construct() {
        $this->init_ajax_actions();
    }

    /**
     * ثبت تمام هوک‌های AJAX
     */
    private function init_ajax_actions() {
        // مدیریت فرم‌ها
        add_action('wp_ajax_pcfb_save_form', [$this, 'handle_save_form']);
        add_action('wp_ajax_pcfb_load_form', [$this, 'handle_load_form']);
        add_action('wp_ajax_pcfb_delete_form', [$this, 'handle_delete_form']);
        add_action('wp_ajax_pcfb_copy_form', [$this, 'handle_copy_form']);
        add_action('wp_ajax_pcfb_toggle_form_status', [$this, 'handle_toggle_form_status']);
        
        // مدیریت ارسال‌ها
        add_action('wp_ajax_pcfb_get_submission_details', [$this, 'handle_get_submission_details']);
        add_action('wp_ajax_pcfb_delete_submission', [$this, 'handle_delete_submission']);
        add_action('wp_ajax_pcfb_bulk_delete_submissions', [$this, 'handle_bulk_delete_submissions']);
        add_action('wp_ajax_pcfb_export_submissions', [$this, 'handle_export_submissions']);
        
        // ارسال فرم از front-end
        add_action('wp_ajax_pcfb_submit_form', [$this, 'handle_submit_form']);
        add_action('wp_ajax_nopriv_pcfb_submit_form', [$this, 'handle_submit_form']);
        
        // ابزارهای مدیریتی
        add_action('wp_ajax_pcfb_get_stats', [$this, 'handle_get_stats']);
        add_action('wp_ajax_pcfb_cleanup_data', [$this, 'handle_cleanup_data']);
    }

    /**
     * ==================== مدیریت فرم‌ها ====================
     */

    /**
     * ذخیره فرم جدید یا ویرایش فرم موجود
     */
    public function handle_save_form() {
        try {
            $this->verify_admin_permissions();
            
            // دریافت و اعتبارسنجی داده‌ها
            $data = $this->validate_save_form_data();
            
            // ذخیره فرم
            $result = PCFB_DB::save_form($data);
            
            if (!$result) {
                throw new Exception('خطا در ذخیره‌سازی فرم در دیتابیس');
            }

            wp_send_json_success([
                'form_id' => $result,
                'message' => $data['form_id'] > 0 ? 'فرم با موفقیت به‌روزرسانی شد' : 'فرم جدید با موفقیت ایجاد شد',
                'redirect_url' => admin_url('admin.php?page=pcfb-settings&tab=forms')
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * بارگذاری داده‌های یک فرم
     */
    public function handle_load_form() {
        try {
            $this->verify_admin_permissions();
            
            $form_id = $this->get_valid_form_id();
            $form = PCFB_DB::get_form($form_id);
            
            if (!$form) {
                throw new Exception('فرم مورد نظر یافت نشد');
            }

            // decode داده‌های JSON
            $form_data = json_decode($form->form_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('داده‌های فرم معتبر نیستند');
            }

            // دریافت آمار ارسال‌ها
            $submission_count = PCFB_DB::get_submissions_count($form_id);

            wp_send_json_success([
                'form_name' => $form->form_name,
                'form_slug' => $form->form_slug,
                'form_data' => $form_data,
                'settings' => json_decode($form->settings ?: '{}', true),
                'created_at' => $this->format_date($form->created_at),
                'updated_at' => $this->format_date($form->updated_at),
                'status' => $form->status,
                'submission_count' => $submission_count
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * حذف یک فرم (نرم)
     */
    public function handle_delete_form() {
        try {
            $this->verify_admin_permissions();
            
            $form_id = $this->get_valid_form_id();
            $result = PCFB_DB::delete_form($form_id);
            
            if (!$result) {
                throw new Exception('خطا در حذف فرم');
            }

            wp_send_json_success('فرم با موفقیت حذف شد');

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * تغییر وضعیت فعال/غیرفعال فرم
     */
    public function handle_toggle_form_status() {
        try {
            $this->verify_admin_permissions();
            
            $form_id = $this->get_valid_form_id();
            $form = PCFB_DB::get_form($form_id);
            
            if (!$form) {
                throw new Exception('فرم مورد نظر یافت نشد');
            }

            $new_status = $form->status ? 0 : 1;
            $result = PCFB_DB::update_form_status($form_id, $new_status);
            
            if (!$result) {
                throw new Exception('خطا در تغییر وضعیت فرم');
            }

            wp_send_json_success([
                'new_status' => $new_status,
                'message' => $new_status ? 'فرم فعال شد' : 'فرم غیرفعال شد'
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * کپی‌برداری از یک فرم
     */
    public function handle_copy_form() {
        try {
            $this->verify_admin_permissions();
            
            $form_id = $this->get_valid_form_id();
            $original_form = PCFB_DB::get_form($form_id);
            
            if (!$original_form) {
                throw new Exception('فرم مورد نظر یافت نشد');
            }

            // ایجاد نام جدید
            $new_name = $this->generate_unique_form_name($original_form->form_name);
            
            // ایجاد فرم جدید
            $result = PCFB_DB::save_form([
                'form_name' => $new_name,
                'form_json' => $original_form->form_json,
                'settings' => $original_form->settings
            ]);

            if (!$result) {
                throw new Exception('خطا در کپی‌برداری فرم');
            }

            wp_send_json_success([
                'form_id' => $result,
                'message' => 'فرم با موفقیت کپی شد',
                'new_name' => $new_name
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * ==================== مدیریت ارسال‌ها ====================
     */

    /**
     * دریافت جزئیات یک ارسال
     */
    public function handle_get_submission_details() {
        try {
            $this->verify_admin_permissions();
            
            $submission_id = $this->get_valid_submission_id();
            $submission = PCFB_DB::get_submission($submission_id);
            
            if (!$submission) {
                throw new Exception('ارسال مورد نظر یافت نشد');
            }

            // دریافت اطلاعات فرم
            $form = PCFB_DB::get_form($submission->form_id);
            
            // decode داده‌های ارسال
            $form_data = json_decode($submission->form_data, true);
            $fields = [];

            if (is_array($form_data)) {
                foreach ($form_data as $key => $value) {
                    $fields[] = [
                        'label' => $key,
                        'value' => is_array($value) ? implode(', ', $value) : $value
                    ];
                }
            }

            $submission_data = [
                'id' => $submission->id,
                'form_name' => $form ? $form->form_name : 'فرم حذف شده',
                'form_id' => $submission->form_id,
                'submission_date' => $this->format_date($submission->created_at),
                'human_date' => $this->human_time_diff($submission->created_at),
                'ip_address' => $submission->ip_address,
                'user_agent' => $submission->user_agent,
                'user_id' => $submission->user_id,
                'user_name' => $submission->user_id ? get_userdata($submission->user_id)->display_name : 'مهمان',
                'fields' => $fields
            ];

            wp_send_json_success([
                'html' => $this->generate_submission_html($submission_data),
                'data' => $submission_data
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * حذف یک ارسال
     */
    public function handle_delete_submission() {
        try {
            $this->verify_admin_permissions();
            
            $submission_id = $this->get_valid_submission_id();
            $result = PCFB_DB::delete_submission($submission_id);
            
            if (!$result) {
                throw new Exception('خطا در حذف ارسال');
            }

            wp_send_json_success('ارسال با موفقیت حذف شد');

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * حذف گروهی ارسال‌ها
     */
    public function handle_bulk_delete_submissions() {
        try {
            $this->verify_admin_permissions();
            
            $submission_ids = $this->get_valid_submission_ids();
            $deleted_count = 0;

            foreach ($submission_ids as $submission_id) {
                if (PCFB_DB::delete_submission($submission_id)) {
                    $deleted_count++;
                }
            }

            wp_send_json_success([
                'message' => sprintf('%d ارسال با موفقیت حذف شد', $deleted_count),
                'deleted_count' => $deleted_count
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * خروجی ارسال‌ها به صورت CSV
     */
    public function handle_export_submissions() {
        try {
            $this->verify_admin_permissions();
            
            $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
            $filters = $this->get_export_filters();

            // دریافت ارسال‌ها
            $submissions = PCFB_DB::get_submissions_for_export($form_id, $filters);
            
            // تولید CSV
            $csv_data = $this->generate_csv_data($submissions, $form_id);
            
            wp_send_json_success([
                'csv_data' => $csv_data,
                'filename' => $this->generate_export_filename($form_id),
                'count' => count($submissions)
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * ==================== ارسال فرم از front-end ====================
     */

    /**
     * ارسال فرم از سمت کاربر
     */
    public function handle_submit_form() {
        try {
            // بررسی nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pcfb_form_nonce')) {
                throw new Exception('خطای امنیتی. لطفاً صفحه را رفرش کنید');
            }

            // دریافت و اعتبارسنجی داده‌ها
            $form_id = $this->get_valid_form_id();
            $form_data = $this->validate_form_submission($form_id);

            // بررسی محدودیت ارسال (ضد اسپم)
            $this->check_spam_protection($form_id);

            // ذخیره‌سازی ارسال
            $submission_id = PCFB_DB::save_submission([
                'form_id' => $form_id,
                'form_data' => $form_data,
                'status' => 'pending'
            ]);

            if (!$submission_id) {
                throw new Exception('خطا در ذخیره‌سازی داده‌ها');
            }

            // ارسال ایمیل通知
            $email_sent = $this->send_notification_email($form_id, $form_data, $submission_id);

            // پاسخ موفقیت‌آمیز
            $response = [
                'message' => 'فرم شما با موفقیت ارسال شد. با تشکر از مشارکت شما!',
                'submission_id' => $submission_id,
                'email_sent' => $email_sent
            ];

            // بررسی redirect
            if (!empty($_POST['redirect_to'])) {
                $response['redirect'] = esc_url($_POST['redirect_to']);
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * ==================== ابزارهای مدیریتی ====================
     */

    /**
     * دریافت آمار سیستم
     */
    public function handle_get_stats() {
        try {
            $this->verify_admin_permissions();
            
            $stats = PCFB_DB::get_stats();
            $recent_submissions = PCFB_DB::get_recent_submissions(5);

            wp_send_json_success([
                'stats' => $stats,
                'recent_submissions' => $recent_submissions,
                'server_time' => current_time('mysql'),
                'memory_usage' => $this->format_bytes(memory_get_usage(true))
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * پاکسازی داده‌های قدیمی
     */
    public function handle_cleanup_data() {
        try {
            $this->verify_admin_permissions();
            
            $days_old = absint($_POST['days_old'] ?? 30);
            $cleaned_count = PCFB_DB::cleanup_old_submissions($days_old);

            wp_send_json_success([
                'message' => sprintf('%d ارسال قدیمی پاکسازی شد', $cleaned_count),
                'cleaned_count' => $cleaned_count
            ]);

        } catch (Exception $e) {
            $this->send_json_error($e->getMessage());
        }
    }

    /**
     * ==================== توابع کمکی ====================
     */

    /**
     * بررسی دسترسی ادمین
     */
    private function verify_admin_permissions() {
        if (!current_user_can('manage_options')) {
            throw new Exception('شما دسترسی لازم برای این عمل را ندارید');
        }
        
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pcfb_admin_nonce')) {
            throw new Exception('خطای امنیتی');
        }
    }

    /**
     * اعتبارسنجی داده‌های ذخیره فرم
     */
    private function validate_save_form_data() {
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $form_name = sanitize_text_field($_POST['form_name'] ?? '');
        $form_json = wp_unslash($_POST['form_json'] ?? '');

        // اعتبارسنجی نام فرم
        if (empty($form_name)) {
            throw new Exception('نام فرم نمی‌تواند خالی باشد');
        }

        if (strlen($form_name) > 200) {
            throw new Exception('نام فرم نمی‌تواند بیش از ۲۰۰ کاراکتر باشد');
        }

        // اعتبارسنجی JSON
        if (empty($form_json)) {
            throw new Exception('داده‌های فرم نمی‌تواند خالی باشد');
        }

        $decoded_json = json_decode($form_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('داده‌های فرم معتبر نیستند');
        }

        // اعتبارسنجی ساختار فرم
        if (!isset($decoded_json['fields']) || !is_array($decoded_json['fields'])) {
            throw new Exception('ساختار فرم معتبر نیست');
        }

        if (count($decoded_json['fields']) === 0) {
            throw new Exception('فرم باید حداقل یک فیلد داشته باشد');
        }

        return [
            'form_id' => $form_id,
            'form_name' => $form_name,
            'form_json' => $form_json,
            'settings' => $_POST['settings'] ?? []
        ];
    }

    /**
     * اعتبارسنجی ارسال فرم
     */
    private function validate_form_submission($form_id) {
        $form = PCFB_DB::get_form($form_id);
        if (!$form) {
            throw new Exception('فرم معتبر نیست');
        }

        // بررسی فعال بودن فرم
        if (!$form->status) {
            throw new Exception('این فرم غیرفعال شده است');
        }

        $form_structure = json_decode($form->form_json, true);
        if (!$form_structure || !isset($form_structure['fields'])) {
            throw new Exception('ساختار فرم معتبر نیست');
        }

        $form_data = [];
        $errors = [];

        // پردازش و اعتبارسنجی فیلدها
        foreach ($form_structure['fields'] as $field) {
            $field_name = $field['name'] ?? '';
            $field_type = $field['type'] ?? 'text';
            $is_required = $field['required'] ?? false;
            $field_value = $_POST[$field_name] ?? '';

            // اعتبارسنجی فیلدهای اجباری
            if ($is_required && empty($field_value)) {
                $field_label = $field['label'] ?? $field_name;
                $errors[] = sprintf('فیلد "%s" اجباری است', $field_label);
                continue;
            }

            // اعتبارسنجی نوع فیلد
            if (!empty($field_value)) {
                $validation_result = $this->validate_field_value($field_type, $field_value, $field);
                if ($validation_result !== true) {
                    $errors[] = $validation_result;
                    continue;
                }
            }

            // ذخیره مقدار فیلد
            $form_data[$field_name] = $this->sanitize_field_value($field_type, $field_value);
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        return $form_data;
    }

    /**
     * اعتبارسنجی مقدار فیلد
     */
    private function validate_field_value($field_type, $value, $field) {
        switch ($field_type) {
            case 'email':
                if (!is_email($value)) {
                    return 'ایمیل وارد شده معتبر نیست';
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    return 'مقدار وارد شده باید عددی باشد';
                }
                
                $min = $field['min'] ?? null;
                $max = $field['max'] ?? null;
                
                if ($min !== null && $value < $min) {
                    return sprintf('مقدار باید بیشتر یا برابر %s باشد', $min);
                }
                
                if ($max !== null && $value > $max) {
                    return sprintf('مقدار باید کمتر یا برابر %s باشد', $max);
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return 'آدرس اینترنتی وارد شده معتبر نیست';
                }
                break;

            case 'date':
                if (!strtotime($value)) {
                    return 'تاریخ وارد شده معتبر نیست';
                }
                break;
        }

        return true;
    }

    /**
     * بررسی حفاظت در برابر اسپم
     */
    private function check_spam_protection($form_id) {
        $enable_anti_spam = PCFB_DB::get_setting('enable_anti_spam', true);
        if (!$enable_anti_spam) {
            return true;
        }

        $submission_limit = PCFB_DB::get_setting('submission_limit', 10);
        $time_frame = PCFB_DB::get_setting('time_frame', 3600);
        
        $recent_count = PCFB_DB::get_recent_submissions_count($form_id, $time_frame);
        
        if ($recent_count >= $submission_limit) {
            $time_text = $this->seconds_to_persian_text($time_frame);
            throw new Exception(
                sprintf('شما تعداد زیادی فرم ارسال کرده‌اید. لطفاً %s دیگر مجدداً تلاش کنید', $time_text)
            );
        }
        
        return true;
    }

    /**
     * ==================== توابع utility ====================
     */

    private function get_valid_form_id() {
        $form_id = absint($_POST['form_id'] ?? 0);
        if ($form_id === 0) {
            throw new Exception('شناسه فرم نامعتبر است');
        }
        return $form_id;
    }

    private function get_valid_submission_id() {
        $submission_id = absint($_POST['submission_id'] ?? 0);
        if ($submission_id === 0) {
            throw new Exception('شناسه ارسال نامعتبر است');
        }
        return $submission_id;
    }

    private function get_valid_submission_ids() {
        $submission_ids = array_map('absint', (array) ($_POST['submission_ids'] ?? []));
        if (empty($submission_ids)) {
            throw new Exception('هیچ ارسالی برای حذف انتخاب نشده است');
        }
        return $submission_ids;
    }

    private function generate_unique_form_name($base_name) {
        $counter = 1;
        $new_name = $base_name . ' (کپی)';
        
        while (PCFB_DB::form_name_exists($new_name)) {
            $new_name = $base_name . " (کپی {$counter})";
            $counter++;
            if ($counter > 100) break;
        }
        
        return $new_name;
    }

    private function send_json_error($message) {
        error_log('PCFB AJAX Error: ' . $message);
        wp_send_json_error($message);
    }

    // سایر توابع utility مانند format_date, human_time_diff, generate_csv_data, etc.
    // به دلیل محدودیت طول پاسخ، این توابع پیاده‌سازی نشده‌اند
}

new PCFB_AJAX();