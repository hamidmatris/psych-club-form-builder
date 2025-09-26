<?php
/**
 * کلاس مدیریت نمایش فرم‌ها در front-end
 */

if (!defined('ABSPATH')) {
    exit;
}

class PCFB_Form
{
    private static $forms_loaded = false;
    
    public function __construct() {
        add_shortcode('pcfb_form', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'add_inline_styles']);
        add_action('wp_ajax_pcfb_submit_form', [$this, 'handle_form_submission']);
        add_action('wp_ajax_nopriv_pcfb_submit_form', [$this, 'handle_form_submission']);
    }

    /**
     * ثبت اسکریپت‌ها و استایل‌های مورد نیاز
     */
    public function enqueue_scripts() {
        global $post;
        
        // بررسی وجود شورتکد در محتوا
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'pcfb_form')) {
            self::$forms_loaded = true;
            
            // استایل‌های اصلی
            wp_enqueue_style(
                'pcfb-public',
                PCFB_URL . 'public/css/pcfb-styles.css',
                [],
                PCFB_VERSION
            );
            
            // اسکریپت‌های اصلی
            wp_enqueue_script(
                'pcfb-public',
                PCFB_URL . 'public/js/pcfb-public.js',
                ['jquery'],
                PCFB_VERSION,
                true
            );
            
            // کتابخانه اعتبارسنجی (اختیاری)
            wp_enqueue_script(
                'pcfb-validator',
                PCFB_URL . 'public/js/pcfb-validator.js',
                ['jquery'],
                PCFB_VERSION,
                true
            );
            
            // انتقال داده‌ها به JavaScript
            wp_localize_script('pcfb-public', 'pcfb_public', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pcfb_form_nonce'),
                'texts' => [
                    'required_field' => 'این فیلد اجباری است',
                    'invalid_email' => 'ایمیل معتبر نیست',
                    'invalid_number' => 'عدد معتبر نیست',
                    'invalid_url' => 'آدرس وب معتبر نیست',
                    'invalid_tel' => 'شماره تلفن معتبر نیست',
                    'file_too_large' => 'حجم فایل بسیار بزرگ است',
                    'invalid_file_type' => 'نوع فایل مجاز نیست',
                    'submitting' => 'در حال ارسال...',
                    'success_message' => 'فرم با موفقیت ارسال شد!',
                    'error_message' => 'خطا در ارسال فرم! لطفاً مجدداً تلاش کنید.'
                ],
                'settings' => [
                    'max_file_size' => wp_max_upload_size(),
                    'allowed_file_types' => $this->get_allowed_file_types()
                ]
            ]);
        }
    }

    /**
     * افزودن استایل‌های inline برای عملکرد بهتر
     */
    public function add_inline_styles() {
        if (!self::$forms_loaded) return;
        ?>
        <style>
            .pcfb-form-container {
                transition: all 0.3s ease;
            }
            
            .pcfb-field-frontend.pcfb-loading {
                opacity: 0.6;
                pointer-events: none;
            }
            
            .pcfb-success-message {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 12px;
                border-radius: 4px;
                margin: 15px 0;
            }
            
            .pcfb-error-message {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 12px;
                border-radius: 4px;
                margin: 15px 0;
            }
            
            .pcfb-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: pcfb-spin 1s linear infinite;
                margin-left: 8px;
            }
            
            @keyframes pcfb-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }

    /**
     * نمایش فرم با استفاده از شورتکد
     */
    public function render_form($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'slug' => '',
            'title' => 'yes',
            'description' => 'no',
            'class' => '',
            'redirect' => ''
        ], $atts, 'pcfb_form');

        // پیدا کردن فرم بر اساس ID یا slug
        $form = $this->get_form_to_display($atts);
        
        if (!$form) {
            return $this->render_error('فرم مورد نظر یافت نشد.');
        }

        // بررسی وضعیت فرم
        if ($form->status != 1) {
            return $this->render_error('این فرم غیرفعال شده است.');
        }

        // decode داده‌های JSON
        $form_data = json_decode($form->form_json, true);
        
        if (!$form_data || !isset($form_data['fields']) || !is_array($form_data['fields'])) {
            return $this->render_error('داده‌های فرم معتبر نیستند.');
        }

        // بررسی محدودیت ارسال (ضد اسپم)
        $submission_check = $this->check_submission_limits($form->id);
        if (is_wp_error($submission_check)) {
            return $this->render_error($submission_check->get_error_message());
        }

        ob_start();
        ?>
        <div class="pcfb-form-container <?php echo esc_attr($atts['class']); ?>" 
             data-form-id="<?php echo esc_attr($form->id); ?>"
             data-form-slug="<?php echo esc_attr($form->form_slug); ?>">
            
            <?php if ($atts['title'] === 'yes') : ?>
                <h3 class="pcfb-form-title"><?php echo esc_html($form->form_name); ?></h3>
            <?php endif; ?>
            
            <?php if ($atts['description'] === 'yes' && !empty($form_data['description'])) : ?>
                <div class="pcfb-form-description">
                    <?php echo wp_kses_post($form_data['description']); ?>
                </div>
            <?php endif; ?>

            <form class="pcfb-public-form" 
                  id="pcfb-form-<?php echo esc_attr($form->id); ?>" 
                  method="post" 
                  enctype="multipart/form-data"
                  novalidate>
                  
                <?php wp_nonce_field('pcfb_form_submit', 'pcfb_nonce'); ?>
                <input type="hidden" name="action" value="pcfb_submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>">
                <?php if (!empty($atts['redirect'])) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                <?php endif; ?>
                
                <div class="pcfb-form-fields">
                    <?php $this->render_form_fields($form_data['fields']); ?>
                </div>
                
                <!-- پیام‌های سیستم -->
                <div class="pcfb-form-messages" style="display: none;"></div>
                
                <!-- دکمه ارسال -->
                <div class="pcfb-form-actions">
                    <button type="submit" class="pcfb-submit-btn" disabled>
                        <span class="btn-text">
                            <?php echo esc_html($form_data['submit_text'] ?? 'ارسال فرم'); ?>
                        </span>
                        <span class="btn-loading" style="display: none;">
                            <span class="pcfb-spinner"></span>
                            <?php echo esc_html($form_data['submitting_text'] ?? 'در حال ارسال...'); ?>
                        </span>
                    </button>
                    
                    <?php if (!empty($form_data['reset_text'])) : ?>
                        <button type="reset" class="pcfb-reset-btn">
                            <?php echo esc_html($form_data['reset_text']); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * پیدا کردن فرم برای نمایش
     */
    private function get_form_to_display($atts) {
        if (!empty($atts['slug'])) {
            return PCFB_DB::get_form_by_slug(sanitize_title($atts['slug']));
        }
        
        $form_id = absint($atts['id']);
        if ($form_id > 0) {
            return PCFB_DB::get_form($form_id);
        }
        
        // اگر هیچکدام مشخص نشده، اولین فرم فعال را برگردان
        $forms = PCFB_DB::get_forms(['status' => 1, 'limit' => 1]);
        return !empty($forms) ? $forms[0] : null;
    }

    /**
     * رندر فیلدهای فرم
     */
    private function render_form_fields($fields) {
        foreach ($fields as $index => $field) {
            if (!isset($field['type']) || !isset($field['name'])) {
                continue;
            }
            
            $field_type = sanitize_text_field($field['type']);
            $field_name = sanitize_text_field($field['name']);
            $field_label = sanitize_text_field($field['label'] ?? '');
            $required = !empty($field['required']) ? 'required' : '';
            $placeholder = sanitize_text_field($field['placeholder'] ?? '');
            $description = sanitize_text_field($field['description'] ?? '');
            $css_class = sanitize_text_field($field['class'] ?? '');
            
            ?>
            <div class="pcfb-field-frontend pcfb-field-<?php echo esc_attr($field_type); ?> <?php echo esc_attr($css_class); ?>"
                 data-field-type="<?php echo esc_attr($field_type); ?>"
                 data-field-name="<?php echo esc_attr($field_name); ?>">
                 
                <?php if (!empty($field_label) && $field_type !== 'checkbox' && $field_type !== 'radio') : ?>
                    <label for="pcfb-field-<?php echo esc_attr($field_name); ?>" class="pcfb-field-label">
                        <?php echo esc_html($field_label); ?>
                        <?php if ($required) : ?>
                            <span class="pcfb-required" title="اجباری">*</span>
                        <?php endif; ?>
                    </label>
                <?php endif; ?>
                
                <div class="pcfb-field-input">
                    <?php $this->render_field_input($field, $field_name, $placeholder, $required); ?>
                </div>
                
                <?php if (!empty($description)) : ?>
                    <small class="pcfb-field-description">
                        <?php echo esc_html($description); ?>
                    </small>
                <?php endif; ?>
                
                <div class="pcfb-field-error" style="display: none;"></div>
            </div>
            <?php
        }
    }

    /**
     * رندر input فیلد بر اساس نوع
     */
    private function render_field_input($field, $field_name, $placeholder, $required) {
        $field_type = $field['type'];
        $field_value = $field['value'] ?? '';
        $options = $field['options'] ?? [];
        $multiple = !empty($field['multiple']) ? 'multiple' : '';
        
        switch ($field_type) {
            case 'textarea':
                ?>
                <textarea 
                    name="<?php echo esc_attr($field_name); ?>" 
                    id="pcfb-field-<?php echo esc_attr($field_name); ?>" 
                    placeholder="<?php echo esc_attr($placeholder); ?>" 
                    <?php echo $required; ?>
                    rows="<?php echo absint($field['rows'] ?? 4); ?>"
                    class="pcfb-textarea"
                ><?php echo esc_textarea($field_value); ?></textarea>
                <?php
                break;
                
            case 'select':
                ?>
                <select 
                    name="<?php echo esc_attr($field_name); ?><?php echo $multiple ? '[]' : ''; ?>" 
                    id="pcfb-field-<?php echo esc_attr($field_name); ?>" 
                    <?php echo $required . ' ' . $multiple; ?>
                    class="pcfb-select"
                >
                    <?php if (empty($multiple)) : ?>
                        <option value=""><?php echo esc_html($field['placeholder'] ?? 'انتخاب کنید'); ?></option>
                    <?php endif; ?>
                    
                    <?php foreach ($options as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" 
                            <?php selected($field_value, $option); ?>>
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                foreach ($options as $index => $option) :
                    $option_id = $field_name . '_' . $index;
                    $checked = is_array($field_value) ? in_array($option, $field_value) : false;
                ?>
                <label class="pcfb-checkbox-option">
                    <input 
                        type="checkbox" 
                        name="<?php echo esc_attr($field_name); ?>[]" 
                        value="<?php echo esc_attr($option); ?>"
                        id="<?php echo esc_attr($option_id); ?>"
                        <?php echo $checked ? 'checked' : ''; ?>
                        class="pcfb-checkbox"
                    >
                    <span class="pcfb-option-label"><?php echo esc_html($option); ?></span>
                </label>
                <?php
                endforeach;
                break;
                
            case 'radio':
                foreach ($options as $index => $option) :
                    $option_id = $field_name . '_' . $index;
                ?>
                <label class="pcfb-radio-option">
                    <input 
                        type="radio" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        value="<?php echo esc_attr($option); ?>"
                        id="<?php echo esc_attr($option_id); ?>"
                        <?php echo $required; ?>
                        <?php checked($field_value, $option); ?>
                        class="pcfb-radio"
                    >
                    <span class="pcfb-option-label"><?php echo esc_html($option); ?></span>
                </label>
                <?php
                endforeach;
                break;
                
            case 'file':
                ?>
                <input 
                    type="file" 
                    name="<?php echo esc_attr($field_name); ?><?php echo $multiple ? '[]' : ''; ?>" 
                    id="pcfb-field-<?php echo esc_attr($field_name); ?>" 
                    <?php echo $required . ' ' . $multiple; ?>
                    accept="<?php echo esc_attr($this->get_file_accept_attribute($field)); ?>"
                    class="pcfb-file"
                >
                <div class="pcfb-file-preview" style="display: none;"></div>
                <?php
                break;
                
            default: // text, email, number, tel, url, etc.
                ?>
                <input 
                    type="<?php echo esc_attr($field_type); ?>" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    id="pcfb-field-<?php echo esc_attr($field_name); ?>" 
                    value="<?php echo esc_attr($field_value); ?>" 
                    placeholder="<?php echo esc_attr($placeholder); ?>" 
                    <?php echo $required; ?>
                    <?php echo $this->get_input_attributes($field); ?>
                    class="pcfb-input pcfb-input-<?php echo esc_attr($field_type); ?>"
                >
                <?php
                break;
        }
    }

    /**
     * بررسی محدودیت ارسال فرم
     */
    private function check_submission_limits($form_id) {
        $enable_anti_spam = PCFB_DB::get_setting('enable_anti_spam', true);
        
        if (!$enable_anti_spam) {
            return true;
        }
        
        $submission_limit = PCFB_DB::get_setting('submission_limit', 10);
        $time_frame = PCFB_DB::get_setting('time_frame', 3600);
        
        $recent_submissions = PCFB_DB::get_submissions([
            'form_id' => $form_id,
            'date_from' => date('Y-m-d H:i:s', time() - $time_frame),
            'ip_address' => PCFB_DB::get_client_ip()
        ]);
        
        if (count($recent_submissions) >= $submission_limit) {
            return new WP_Error(
                'submission_limit_reached',
                sprintf(
                    'شما تعداد زیادی فرم ارسال کرده‌اید. لطفاً %s دیگر مجدداً تلاش کنید.',
                    $this->format_time($time_frame)
                )
            );
        }
        
        return true;
    }

    /**
     * مدیریت ارسال فرم از طریق AJAX
     */
    public function handle_form_submission() {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['pcfb_nonce'] ?? '', 'pcfb_form_nonce')) {
            wp_send_json_error('خطای امنیتی');
        }
        
        // دریافت و اعتبارسنجی داده‌ها
        $form_id = absint($_POST['form_id'] ?? 0);
        $form_data = $this->sanitize_form_data($_POST);
        
        // پردازش و ذخیره داده‌ها
        $result = $this->process_form_submission($form_id, $form_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => 'فرم با موفقیت ارسال شد!',
            'redirect' => $_POST['redirect_to'] ?? ''
        ]);
    }

    /**
     * توابع کمکی
     */
    private function render_error($message) {
        return '<div class="pcfb-error-message">' . esc_html($message) . '</div>';
    }
    
    private function format_time($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return $hours . ' ساعت' . ($minutes > 0 ? ' و ' . $minutes . ' دقیقه' : '');
        }
        return $minutes . ' دقیقه';
    }
    
    private function get_allowed_file_types() {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
    
    private function get_file_accept_attribute($field) {
        $accept_types = $field['accept'] ?? '';
        if (empty($accept_types)) {
            return '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx';
        }
        return $accept_types;
    }
    
    private function get_input_attributes($field) {
        $attrs = '';
        if (isset($field['min'])) $attrs .= ' min="' . esc_attr($field['min']) . '"';
        if (isset($field['max'])) $attrs .= ' max="' . esc_attr($field['max']) . '"';
        if (isset($field['step'])) $attrs .= ' step="' . esc_attr($field['step']) . '"';
        if (isset($field['pattern'])) $attrs .= ' pattern="' . esc_attr($field['pattern']) . '"';
        return $attrs;
    }
}