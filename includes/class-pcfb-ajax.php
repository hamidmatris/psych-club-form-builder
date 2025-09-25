<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * کلاس مدیریت درخواست‌های AJAX برای فرم‌ساز
 * Psych Club Form Builder - AJAX Handler
 */

class PCFB_AJAX {

    public function __construct() {
        // هوک‌های AJAX برای مدیریت فرم‌ها
        add_action( 'wp_ajax_pcfb_save_form', array( $this, 'handle_save_form' ) );
        add_action( 'wp_ajax_pcfb_load_form', array( $this, 'handle_load_form' ) );
        add_action( 'wp_ajax_pcfb_delete_form', array( $this, 'handle_delete_form' ) );
        add_action( 'wp_ajax_pcfb_copy_form', array( $this, 'handle_copy_form' ) );
        
        // هوک‌های AJAX برای مدیریت ارسال‌ها
        add_action( 'wp_ajax_pcfb_get_submission_details', array( $this, 'handle_get_submission_details' ) );
        add_action( 'wp_ajax_pcfb_delete_submission', array( $this, 'handle_delete_submission' ) );
        add_action( 'wp_ajax_pcfb_bulk_delete_submissions', array( $this, 'handle_bulk_delete_submissions' ) );
        
        // هوک‌های AJAX برای front-end
        add_action( 'wp_ajax_pcfb_submit_form', array( $this, 'handle_submit_form' ) );
        add_action( 'wp_ajax_nopriv_pcfb_submit_form', array( $this, 'handle_submit_form' ) );
        
        // هوک‌های admin-post برای خروجی‌ها
        add_action( 'admin_post_pcfb_export_csv', array( $this, 'handle_export_csv' ) );
    }

    /**
     * ذخیره فرم جدید یا ویرایش فرم موجود
     */
    public function handle_save_form() {
        // بررسی وجود پارامترهای لازم
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( 'Nonce ارائه نشده است.' );
        }

        if ( ! isset( $_POST['form_name'] ) ) {
            wp_send_json_error( 'نام فرم ارائه نشده است.' );
        }

        if ( ! isset( $_POST['form_json'] ) ) {
            wp_send_json_error( 'داده‌های فرم ارائه نشده است.' );
        }

        $this->verify_nonce( 'pcfb_admin_nonce' );
        
        // بررسی دسترسی
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'شما دسترسی لازم برای این عمل را ندارید.' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $form_name = sanitize_text_field( $_POST['form_name'] );
        $form_json = wp_unslash( $_POST['form_json'] );

        // اعتبارسنجی داده‌های ورودی
        if ( empty( $form_name ) ) {
            wp_send_json_error( 'نام فرم نمی‌تواند خالی باشد.' );
        }

        if ( strlen( $form_name ) > 200 ) {
            wp_send_json_error( 'نام فرم نمی‌تواند بیش از ۲۰۰ کاراکتر باشد.' );
        }

        if ( empty( $form_json ) ) {
            wp_send_json_error( 'داده‌های فرم نمی‌تواند خالی باشد.' );
        }

        // بررسی صحت JSON
        $decoded_json = json_decode( $form_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'داده‌های فرم معتبر نیستند. خطای JSON: ' . json_last_error_msg() );
        }

        // بررسی ساختار فرم
        if ( ! isset( $decoded_json['fields'] ) || ! is_array( $decoded_json['fields'] ) ) {
            wp_send_json_error( 'ساختار فرم معتبر نیست. فیلدها باید به صورت آرایه باشند.' );
        }

        // بررسی تعداد فیلدها
        if ( count( $decoded_json['fields'] ) === 0 ) {
            wp_send_json_error( 'فرم باید حداقل یک فیلد داشته باشد.' );
        }

        try {
            if ( $form_id > 0 ) {
                // ویرایش فرم موجود
                $result = PCFB_DB::update_form( $form_id, $form_name, $form_json );
                $message = 'فرم با موفقیت به‌روزرسانی شد.';
            } else {
                // ایجاد فرم جدید
                $result = PCFB_DB::insert_form( $form_name, $form_json );
                $message = 'فرم جدید با موفقیت ایجاد شد.';
            }

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result->get_error_message() );
            }

            wp_send_json_success( array(
                'form_id' => $form_id > 0 ? $form_id : $result,
                'message' => $message,
                'redirect_url' => admin_url( 'admin.php?page=pcfb-settings&tab=forms' )
            ) );

        } catch ( Exception $e ) {
            error_log( 'PCFB Save Form Error: ' . $e->getMessage() );
            wp_send_json_error( 'خطای سیستمی: ' . $e->getMessage() );
        }
    }

    /**
     * بارگذاری داده‌های یک فرم
     */
    public function handle_load_form() {
        $this->verify_nonce( 'pcfb_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        if ( $form_id === 0 ) {
            wp_send_json_error( 'شناسه فرم نامعتبر است.' );
        }

        $form = PCFB_DB::get_form( $form_id );
        
        if ( ! $form ) {
            wp_send_json_error( 'فرم مورد نظر یافت نشد.' );
        }

        // decode داده‌های JSON
        $form_data = json_decode( $form->form_json, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'داده‌های فرم معتبر نیستند.' );
        }

        wp_send_json_success( array(
            'form_name' => $form->form_name,
            'form_data' => $form_data,
            'created_at' => $form->created_at,
            'updated_at' => $form->updated_at,
            'submission_count' => count( PCFB_DB::get_submissions( $form_id ) )
        ) );
    }

    /**
     * حذف یک فرم
     */
    public function handle_delete_form() {
        $this->verify_nonce( 'pcfb_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        if ( $form_id === 0 ) {
            wp_send_json_error( 'شناسه فرم نامعتبر است.' );
        }

        $result = PCFB_DB::delete_form( $form_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // حذف ارسال‌های مرتبط
        $this->delete_form_submissions( $form_id );

        wp_send_json_success( 'فرم و تمام ارسال‌های مرتبط با آن حذف شدند.' );
    }

    /**
     * کپی‌برداری از یک فرم
     */
    public function handle_copy_form() {
        $this->verify_nonce( 'pcfb_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        if ( $form_id === 0 ) {
            wp_send_json_error( 'شناسه فرم نامعتبر است.' );
        }

        $original_form = PCFB_DB::get_form( $form_id );
        
        if ( ! $original_form ) {
            wp_send_json_error( 'فرم مورد نظر یافت نشد.' );
        }

        // ایجاد نام جدید برای فرم کپی شده
        $new_name = $original_form->form_name . ' (کپی)';
        $counter = 1;
        
        // بررسی تکراری نبودن نام
        while ( $this->form_name_exists( $new_name ) ) {
            $new_name = $original_form->form_name . " (کپی {$counter})";
            $counter++;
            if ( $counter > 100 ) break;
        }

        $result = PCFB_DB::insert_form( $new_name, $original_form->form_json );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'form_id' => $result,
            'message' => 'فرم با موفقیت کپی شد.',
            'new_name' => $new_name
        ) );
    }

    /**
     * دریافت جزئیات یک ارسال
     */
    public function handle_get_submission_details() {
        // بررسی nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pcfb_submission_nonce' ) ) {
            wp_send_json_error( 'خطای امنیتی' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        
        if ( $submission_id === 0 ) {
            wp_send_json_error( 'شناسه ارسال نامعتبر است.' );
        }

        // دریافت ارسال از دیتابیس
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_submissions';
        $submission = $wpdb->get_row( 
            $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $submission_id ) 
        );

        if ( ! $submission ) {
            wp_send_json_error( 'ارسال مورد نظر یافت نشد.' );
        }

        // دریافت اطلاعات فرم
        $form = PCFB_DB::get_form( $submission->form_id );
        $form_name = $form ? $form->form_name : 'فرم حذف شده (ID: ' . $submission->form_id . ')';

        // decode داده‌های ارسال
        $form_data = json_decode( $submission->form_data, true );
        $fields = array();

        if ( is_array( $form_data ) ) {
            foreach ( $form_data as $key => $value ) {
                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }
                $fields[] = array(
                    'label' => $key,
                    'value' => $value
                );
            }
        }

        $submission_data = array(
            'id' => $submission->id,
            'form_name' => $form_name,
            'form_id' => $submission->form_id,
            'submission_date' => date_i18n( 'Y/m/d H:i:s', strtotime( $submission->created_at ) ),
            'human_date' => human_time_diff( strtotime( $submission->created_at ), current_time( 'timestamp' ) ) . ' پیش',
            'ip_address' => $submission->ip_address,
            'user_agent' => $submission->user_agent,
            'fields' => $fields
        );

        wp_send_json_success( $this->generate_submission_html( $submission_data ) );
    }

    /**
     * حذف یک ارسال
     */
    public function handle_delete_submission() {
        $this->verify_nonce( 'pcfb_submission_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        
        if ( $submission_id === 0 ) {
            wp_send_json_error( 'شناسه ارسال نامعتبر است.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_submissions';
        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $submission_id ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( 'خطا در حذف ارسال از دیتابیس.' );
        }

        wp_send_json_success( 'ارسال با موفقیت حذف شد.' );
    }

    /**
     * حذف گروهی ارسال‌ها
     */
    public function handle_bulk_delete_submissions() {
        $this->verify_nonce( 'pcfb_submission_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $submission_ids = isset( $_POST['submission_ids'] ) ? array_map( 'absint', (array) $_POST['submission_ids'] ) : array();
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        if ( empty( $submission_ids ) && $form_id === 0 ) {
            wp_send_json_error( 'هیچ ارسالی برای حذف انتخاب نشده است.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_submissions';
        $deleted_count = 0;

        if ( ! empty( $submission_ids ) ) {
            // حذف ارسال‌های مشخص شده
            foreach ( $submission_ids as $submission_id ) {
                $result = $wpdb->delete(
                    $table_name,
                    array( 'id' => $submission_id ),
                    array( '%d' )
                );
                if ( $result ) {
                    $deleted_count++;
                }
            }
        } elseif ( $form_id > 0 ) {
            // حذف تمام ارسال‌های یک فرم
            $result = $wpdb->delete(
                $table_name,
                array( 'form_id' => $form_id ),
                array( '%d' )
            );
            $deleted_count = $result;
        }

        wp_send_json_success( array(
            'message' => $deleted_count . ' ارسال با موفقیت حذف شد.',
            'deleted_count' => $deleted_count
        ) );
    }

    /**
     * ارسال فرم از سمت کاربر
     */
    public function handle_submit_form() {
        // بررسی nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pcfb_form_nonce' ) ) {
            wp_send_json_error( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.' );
        }

        // بررسی وجود داده‌های ضروری
        if ( ! isset( $_POST['form_id'] ) || ! isset( $_POST['form_data'] ) ) {
            wp_send_json_error( 'داده‌های ناقص دریافت شده است.' );
        }

        $form_id = absint( $_POST['form_id'] );
        $form_data = wp_unslash( $_POST['form_data'] );

        if ( $form_id === 0 ) {
            wp_send_json_error( 'فرم معتبر نیست.' );
        }

        // بررسی وجود فرم
        $form = PCFB_DB::get_form( $form_id );
        if ( ! $form ) {
            wp_send_json_error( 'فرم مورد نظر یافت نشد.' );
        }

        // بررسی فعال بودن فرم
        if ( isset( $form->status ) && $form->status != 1 ) {
            wp_send_json_error( 'این فرم غیرفعال شده است.' );
        }

        // اعتبارسنجی فرم
        $validation = $this->validate_form_submission( $form_id, $form_data );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( $validation->get_error_message() );
        }

        // بررسی محدودیت ارسال (ضد اسپم)
        $spam_check = $this->check_spam_protection( $form_id );
        if ( is_wp_error( $spam_check ) ) {
            wp_send_json_error( $spam_check->get_error_message() );
        }

        // ذخیره‌سازی در دیتابیس
        $result = PCFB_DB::insert_submission( $form_id, $form_data );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'PCFB Submission Error: ' . $result->get_error_message() );
            wp_send_json_error( 'خطا در ذخیره‌سازی داده‌ها. لطفاً با مدیر سایت تماس بگیرید.' );
        }

        // ارسال ایمیل通知 (اگر فعال باشد)
        $email_sent = $this->send_notification_email( $form_id, $form_data, $result );

        wp_send_json_success( array(
            'message' => 'فرم شما با موفقیت ارسال شد. با تشکر از مشارکت شما!' . ( $email_sent ? ' ایمیل تأیید نیز ارسال شد.' : '' ),
            'submission_id' => $result,
            'email_sent' => $email_sent
        ) );
    }

    /**
     * خروجی CSV از ارسال‌ها
     */
    public function handle_export_csv() {
        // بررسی دسترسی
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی غیرمجاز' );
        }

        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $search_term = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

        // دریافت ارسال‌ها
        if ( $form_id > 0 ) {
            $submissions = PCFB_DB::get_submissions( $form_id );
        } else {
            $submissions = array();
            $forms = PCFB_DB::get_forms( true );
            foreach ( $forms as $form ) {
                $form_submissions = PCFB_DB::get_submissions( $form->id );
                $submissions = array_merge( $submissions, $form_submissions );
            }
        }

        // اعمال جستجو
        if ( ! empty( $search_term ) ) {
            $filtered_submissions = array();
            foreach ( $submissions as $submission ) {
                $form_data = json_decode( $submission->form_data, true );
                $found = false;
                
                if ( is_array( $form_data ) ) {
                    foreach ( $form_data as $value ) {
                        if ( is_array( $value ) ) {
                            $value = implode( ' ', $value );
                        }
                        if ( stripos( $value, $search_term ) !== false ) {
                            $found = true;
                            break;
                        }
                    }
                }
                
                if ( $found ) {
                    $filtered_submissions[] = $submission;
                }
            }
            $submissions = $filtered_submissions;
        }

        // تولید فایل CSV
        $filename = 'pcfb-submissions-' . date( 'Y-m-d-H-i-s' ) . ( $form_id > 0 ? '-form-' . $form_id : '' ) . '.csv';
        
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        
        // هدر CSV
        fputcsv( $output, array( 'ID', 'Form ID', 'Form Name', 'Submission Date', 'IP Address', 'Form Data' ) );

        // داده‌ها
        foreach ( $submissions as $submission ) {
            $form = PCFB_DB::get_form( $submission->form_id );
            $form_name = $form ? $form->form_name : 'فرم حذف شده';
            
            $form_data = json_decode( $submission->form_data, true );
            $form_data_str = '';
            
            if ( is_array( $form_data ) ) {
                $data_parts = array();
                foreach ( $form_data as $key => $value ) {
                    if ( is_array( $value ) ) {
                        $value = implode( ', ', $value );
                    }
                    $data_parts[] = $key . ': ' . $value;
                }
                $form_data_str = implode( ' | ', $data_parts );
            }

            fputcsv( $output, array(
                $submission->id,
                $submission->form_id,
                $form_name,
                $submission->created_at,
                $submission->ip_address,
                $form_data_str
            ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * اعتبارسنجی ارسال فرم
     */
    private function validate_form_submission( $form_id, $form_data ) {
        // دریافت ساختار فرم از دیتابیس
        $form = PCFB_DB::get_form( $form_id );
        
        if ( ! $form ) {
            return new WP_Error( 'invalid_form', 'فرم معتبر نیست.' );
        }

        $form_structure = json_decode( $form->form_json, true );
        
        if ( ! $form_structure || ! isset( $form_structure['fields'] ) ) {
            return new WP_Error( 'invalid_structure', 'ساختار فرم معتبر نیست.' );
        }

        // اعتبارسنجی فیلدهای اجباری
        foreach ( $form_structure['fields'] as $field ) {
            $field_name = isset( $field['name'] ) ? $field['name'] : '';
            $is_required = isset( $field['required'] ) ? $field['required'] : false;
            $field_value = isset( $form_data[ $field_name ] ) ? $form_data[ $field_name ] : '';

            // بررسی فیلدهای اجباری
            if ( $is_required && empty( $field_value ) ) {
                $field_label = isset( $field['label'] ) ? $field['label'] : $field_name;
                return new WP_Error( 'required_field', 
                    sprintf( 'فیلد "%s" اجباری است.', $field_label ) 
                );
            }

            // اعتبارسنجی نوع فیلد
            if ( ! empty( $field_value ) ) {
                $validation_error = $this->validate_field_type( $field, $field_value );
                if ( $validation_error ) {
                    return $validation_error;
                }
            }
        }

        return true;
    }

    /**
     * اعتبارسنجی نوع فیلد
     */
    private function validate_field_type( $field, $value ) {
        $field_type = isset( $field['type'] ) ? $field['type'] : 'text';

        switch ( $field_type ) {
            case 'email':
                if ( ! is_email( $value ) ) {
                    return new WP_Error( 'invalid_email', 'ایمیل وارد شده معتبر نیست.' );
                }
                break;

            case 'number':
                if ( ! is_numeric( $value ) ) {
                    return new WP_Error( 'invalid_number', 'مقدار وارد شده باید عددی باشد.' );
                }
                
                // بررسی محدوده‌های min/max
                $min = isset( $field['min'] ) ? $field['min'] : null;
                $max = isset( $field['max'] ) ? $field['max'] : null;
                
                if ( $min !== null && $value < $min ) {
                    return new WP_Error( 'min_value', sprintf( 'مقدار باید بیشتر یا برابر %s باشد.', $min ) );
                }
                
                if ( $max !== null && $value > $max ) {
                    return new WP_Error( 'max_value', sprintf( 'مقدار باید کمتر یا برابر %s باشد.', $max ) );
                }
                break;

            case 'date':
                if ( ! strtotime( $value ) ) {
                    return new WP_Error( 'invalid_date', 'تاریخ وارد شده معتبر نیست.' );
                }
                break;

            case 'url':
                if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_url', 'آدرس اینترنتی وارد شده معتبر نیست.' );
                }
                break;
        }

        return null;
    }

    /**
     * بررسی حفاظت در برابر اسپم
     */
    private function check_spam_protection( $form_id ) {
        // بررسی فعال بودن سیستم ضد اسپم
        $enable_anti_spam = get_option( 'pcfb_enable_anti_spam', true );
        if ( ! $enable_anti_spam ) {
            return true;
        }

        $ip_address = $this->get_client_ip();
        $current_time = current_time( 'timestamp' );
        
        // دریافت تنظیمات
        $submission_limit = get_option( 'pcfb_submission_limit', 10 );
        $time_frame = get_option( 'pcfb_time_frame', 3600 ); // ثانیه
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_submissions';
        
        $time_ago = date( 'Y-m-d H:i:s', $current_time - $time_frame );
        $recent_submissions = $wpdb->get_var(
            $wpdb->prepare( 
                "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s AND created_at > %s",
                $ip_address,
                $time_ago
            )
        );
        
        if ( $recent_submissions >= $submission_limit ) {
            $time_text = $this->seconds_to_persian_text( $time_frame );
            return new WP_Error( 'rate_limit', 
                sprintf( 'شما تعداد زیادی فرم ارسال کرده‌اید. لطفاً %s دیگر مجدداً تلاش کنید.', $time_text )
            );
        }
        
        return true;
    }

    /**
     * تبدیل ثانیه به متن فارسی
     */
    private function seconds_to_persian_text( $seconds ) {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        
        if ( $hours > 0 ) {
            return $hours . ' ساعت' . ( $minutes > 0 ? ' و ' . $minutes . ' دقیقه' : '' );
        } else {
            return $minutes . ' دقیقه';
        }
    }

    /**
     * ارسال ایمیل通知
     */
    private function send_notification_email( $form_id, $form_data, $submission_id ) {
        // بررسی فعال بودن ارسال ایمیل
        $enable_emails = get_option( 'pcfb_enable_email_notifications', false );
        if ( ! $enable_emails ) {
            return false;
        }
        
        $form = PCFB_DB::get_form( $form_id );
        if ( ! $form ) {
            return false;
        }
        
        $to = get_option( 'admin_email' );
        $subject = 'ارسال جدید فرم: ' . $form->form_name;
        
        // ساخت بدنه ایمیل
        $message = "یک ارسال جدید برای فرم \"{$form->form_name}\" دریافت شد:\n\n";
        $message .= "شناسه ارسال: #{$submission_id}\n";
        $message .= "تاریخ ارسال: " . date_i18n( 'Y/m/d H:i:s' ) . "\n";
        $message .= "آی‌پی: " . $this->get_client_ip() . "\n\n";
        $message .= "داده‌های ارسالی:\n";
        
        foreach ( $form_data as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            $message .= "{$key}: {$value}\n";
        }
        
        $message .= "\n\n---\nاین ایمیل به صورت خودکار از افزونه Psych Club Form Builder ارسال شده است.";
        
        return wp_mail( $to, $subject, $message );
    }

    /**
     * تولید HTML جزئیات ارسال
     */
    private function generate_submission_html( $submission_data ) {
        ob_start();
        ?>
        <div class="pcfb-submission-details">
            <div class="pcfb-submission-meta" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>شناسه ارسال:</strong><br>
                        <span style="font-size: 18px; font-weight: bold; color: #0073aa;">#<?php echo esc_html( $submission_data['id'] ); ?></span>
                    </div>
                    <div>
                        <strong>فرم:</strong><br>
                        <?php echo esc_html( $submission_data['form_name'] ); ?><br>
                        <small style="color: #666;">ID: <?php echo esc_html( $submission_data['form_id'] ); ?></small>
                    </div>
                    <div>
                        <strong>تاریخ ارسال:</strong><br>
                        <?php echo esc_html( $submission_data['submission_date'] ); ?><br>
                        <small style="color: #666;"><?php echo esc_html( $submission_data['human_date'] ); ?></small>
                    </div>
                    <div>
                        <strong>آی‌پی کاربر:</strong><br>
                        <code style="background: #e7f3ff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html( $submission_data['ip_address'] ); ?></code>
                    </div>
                </div>
            </div>

            <div class="pcfb-submission-fields">
                <h4 style="margin-bottom: 15px; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">داده‌های ارسالی</h4>
                
                <?php if ( ! empty( $submission_data['fields'] ) ) : ?>
                    <div style="background: white; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f6f7f7;">
                                    <th style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #ccd0d4; width: 30%;">فیلد</th>
                                    <th style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #ccd0d4; width: 70%;">مقدار</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $submission_data['fields'] as $index => $field ) : ?>
                                <tr style="<?php echo $index % 2 === 0 ? 'background: #fafafa;' : 'background: white;'; ?>">
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: top;">
                                        <strong style="color: #1d2327;"><?php echo esc_html( $field['label'] ); ?></strong>
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: top; word-break: break-word; white-space: pre-wrap;">
                                        <?php echo nl2br( esc_html( $field['value'] ) ); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div style="text-align: center; padding: 40px; color: #666; background: #f9f9f9; border-radius: 4px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📭</div>
                        <h4 style="margin: 0 0 10px 0; color: #666;">هیچ داده‌ای برای نمایش وجود ندارد</h4>
                        <p style="margin: 0;">این ارسال شامل هیچ داده‌ای نمی‌باشد.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $submission_data['user_agent'] ) ) : ?>
            <div class="pcfb-user-agent" style="margin-top: 25px; padding: 15px; background: #f0f6fc; border: 1px solid #c5d9f1; border-radius: 4px;">
                <h5 style="margin: 0 0 10px 0; color: #1d2327;">📱 اطلاعات مرورگر و سیستم کاربر</h5>
                <code style="background: white; padding: 8px 12px; border-radius: 3px; font-size: 12px; display: block; word-break: break-all;">
                    <?php echo esc_html( $submission_data['user_agent'] ); ?>
                </code>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * بررسی وجود نام فرم
     */
    private function form_name_exists( $form_name ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_forms';
        
        $count = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE form_name = %s AND status = 1", $form_name )
        );
        
        return $count > 0;
    }

    /**
     * حذف ارسال‌های یک فرم
     */
    private function delete_form_submissions( $form_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcfb_submissions';
        
        return $wpdb->delete(
            $table_name,
            array( 'form_id' => $form_id ),
            array( '%d' )
        );
    }

    /**
     * دریافت آی‌پی کاربر
     */
    private function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
        }
        
        return sanitize_text_field( $ip );
    }

    /**
     * بررسی nonce برای امنیت
     */
    private function verify_nonce( $action ) {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], $action ) ) {
            wp_send_json_error( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.' );
        }
    }
}

// راه‌اندازی کلاس AJAX
new PCFB_AJAX();