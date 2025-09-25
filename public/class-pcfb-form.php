<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_Form {
    
    public function __construct() {
        add_shortcode( 'pcfb_form', [ $this, 'render_form' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ] );
    }

    /**
     * ثبت اسکریپت‌ها و استایل‌های frontend
     */
    public function enqueue_public_scripts() {
        // فقط زمانی بارگذاری شود که فرم وجود دارد
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'pcfb_form' ) ) {
            
            wp_enqueue_style( 
                'pcfb-public', 
                PCFB_URL . 'public/css/pcfb-styles.css', 
                [], 
                PCFB_VERSION 
            );
            
            wp_enqueue_script( 
                'pcfb-public', 
                PCFB_URL . 'public/js/pcfb-public.js', 
                [ 'jquery' ], 
                PCFB_VERSION, 
                true 
            );
            
            wp_localize_script( 'pcfb-public', 'pcfb_public', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'pcfb_form_nonce' ),
                'texts' => [
                    'required_field' => 'این فیلد اجباری است',
                    'invalid_email' => 'ایمیل معتبر نیست',
                    'submitting' => 'در حال ارسال...',
                    'success_message' => 'فرم با موفقیت ارسال شد!'
                ]
            ] );
        }
    }

    /**
     * نمایش فرم با استفاده از شورتکد
     */
    public function render_form( $atts ) {
        // پارامترهای شورتکد
        $atts = shortcode_atts( [
            'id' => 0,
            'title' => 'yes'
        ], $atts, 'pcfb_form' );

        $form_id = absint( $atts['id'] );
        
        // اگر ID مشخص نشده، اولین فرم فعال را نمایش بده
        if ( $form_id === 0 ) {
            $forms = PCFB_DB::get_forms( true );
            if ( ! empty( $forms ) ) {
                $form_id = $forms[0]->id;
            } else {
                return '<p class="pcfb-error">فرمی برای نمایش وجود ندارد.</p>';
            }
        }

        // دریافت فرم از دیتابیس
        $form = PCFB_DB::get_form( $form_id );
        
        if ( ! $form ) {
            return '<p class="pcfb-error">فرم مورد نظر یافت نشد.</p>';
        }

        // بررسی وضعیت فرم
        if ( isset( $form->status ) && $form->status != 1 ) {
            return '<p class="pcfb-error">این فرم غیرفعال شده است.</p>';
        }

        // decode داده‌های JSON فرم
        $form_data = json_decode( $form->form_json, true );
        
        if ( ! $form_data || ! isset( $form_data['fields'] ) ) {
            return '<p class="pcfb-error">داده‌های فرم معتبر نیستند.</p>';
        }

        ob_start();
        ?>
        <div class="pcfb-form-container" data-form-id="<?php echo esc_attr( $form_id ); ?>">
            <?php if ( $atts['title'] === 'yes' ) : ?>
                <h3 class="pcfb-form-title"><?php echo esc_html( $form->form_name ); ?></h3>
            <?php endif; ?>
            
            <form class="pcfb-public-form" id="pcfb-form-<?php echo esc_attr( $form_id ); ?>" method="post" novalidate>
                <?php wp_nonce_field( 'pcfb_form_submit', 'pcfb_nonce' ); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
                
                <div class="pcfb-form-fields">
                    <?php $this->render_form_fields( $form_data['fields'] ); ?>
                </div>
                
                <div class="pcfb-form-messages"></div>
                
                <button type="submit" class="pcfb-submit-btn">
                    <span class="btn-text">ارسال فرم</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="pcfb-spinner"></span>
                        در حال ارسال...
                    </span>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * رندر فیلدهای فرم
     */
    private function render_form_fields( $fields ) {
        foreach ( $fields as $field ) {
            $field_type = sanitize_text_field( $field['type'] ?? 'text' );
            $field_name = sanitize_text_field( $field['name'] ?? '' );
            $field_label = sanitize_text_field( $field['label'] ?? '' );
            $required = isset( $field['required'] ) && $field['required'] ? 'required' : '';
            $placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
            
            if ( empty( $field_name ) ) continue;
            
            ?>
            <div class="pcfb-field-frontend pcfb-field-<?php echo esc_attr( $field_type ); ?>">
                <label for="pcfb-field-<?php echo esc_attr( $field_name ); ?>">
                    <?php echo esc_html( $field_label ); ?>
                    <?php if ( $required ) : ?>
                        <span class="pcfb-required">*</span>
                    <?php endif; ?>
                </label>
                
                <?php $this->render_field_input( $field, $field_name, $placeholder, $required ); ?>
                
                <?php if ( ! empty( $field['description'] ) ) : ?>
                    <small class="pcfb-field-description">
                        <?php echo esc_html( $field['description'] ); ?>
                    </small>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /**
     * رندر input فیلد بر اساس نوع
     */
    private function render_field_input( $field, $field_name, $placeholder, $required ) {
        $field_type = sanitize_text_field( $field['type'] ?? 'text' );
        $field_value = sanitize_text_field( $field['value'] ?? '' );
        
        switch ( $field_type ) {
            case 'textarea':
                ?>
                <textarea 
                    name="<?php echo esc_attr( $field_name ); ?>" 
                    id="pcfb-field-<?php echo esc_attr( $field_name ); ?>" 
                    placeholder="<?php echo esc_attr( $placeholder ); ?>" 
                    <?php echo $required; ?>
                    rows="4"
                ><?php echo esc_textarea( $field_value ); ?></textarea>
                <?php
                break;
                
            case 'select':
                $options = $field['options'] ?? [];
                ?>
                <select 
                    name="<?php echo esc_attr( $field_name ); ?>" 
                    id="pcfb-field-<?php echo esc_attr( $field_name ); ?>" 
                    <?php echo $required; ?>
                >
                    <option value="">انتخاب کنید</option>
                    <?php foreach ( $options as $option ) : ?>
                        <option value="<?php echo esc_attr( $option ); ?>" 
                            <?php selected( $field_value, $option ); ?>>
                            <?php echo esc_html( $option ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                $options = $field['options'] ?? [];
                foreach ( $options as $index => $option ) :
                    $option_id = $field_name . '_' . $index;
                ?>
                <label class="pcfb-checkbox-option">
                    <input 
                        type="checkbox" 
                        name="<?php echo esc_attr( $field_name ); ?>[]" 
                        value="<?php echo esc_attr( $option ); ?>"
                        id="<?php echo esc_attr( $option_id ); ?>"
                    >
                    <?php echo esc_html( $option ); ?>
                </label>
                <?php
                endforeach;
                break;
                
            case 'radio':
                $options = $field['options'] ?? [];
                foreach ( $options as $index => $option ) :
                    $option_id = $field_name . '_' . $index;
                ?>
                <label class="pcfb-radio-option">
                    <input 
                        type="radio" 
                        name="<?php echo esc_attr( $field_name ); ?>" 
                        value="<?php echo esc_attr( $option ); ?>"
                        id="<?php echo esc_attr( $option_id ); ?>"
                        <?php echo $required; ?>
                        <?php checked( $field_value, $option ); ?>
                    >
                    <?php echo esc_html( $option ); ?>
                </label>
                <?php
                endforeach;
                break;
                
            default: // text, email, number, etc.
                ?>
                <input 
                    type="<?php echo esc_attr( $field_type ); ?>" 
                    name="<?php echo esc_attr( $field_name ); ?>" 
                    id="pcfb-field-<?php echo esc_attr( $field_name ); ?>" 
                    value="<?php echo esc_attr( $field_value ); ?>" 
                    placeholder="<?php echo esc_attr( $placeholder ); ?>" 
                    <?php echo $required; ?>
                >
                <?php
                break;
        }
    }
}