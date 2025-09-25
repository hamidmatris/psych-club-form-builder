/**
 * Psych Club Form Builder - Frontend JavaScript
 * مدیریت فرم‌ها در بخش عمومی سایت
 */

jQuery(document).ready(function($) {
    'use strict';

    // آبجکت اصلی برای مدیریت فرم‌های frontend
    const PCFB_Frontend = {
        
        // متغیرهای global
        vars: {
            isSubmitting: false
        },

        // مقداردهی اولیه
        init: function() {
            this.initFormSubmission();
            this.initRealTimeValidation();
            this.initFormMessages();
            console.log('PCFB Frontend initialized');
        },

        // مدیریت ارسال فرم‌ها
        initFormSubmission: function() {
            const self = this;
            
            // رویداد submit برای تمام فرم‌های pcfb
            $(document).on('submit', '.pcfb-public-form', function(e) {
                e.preventDefault();
                
                if (self.vars.isSubmitting) {
                    return false;
                }

                const $form = $(this);
                const formId = $form.find('input[name="form_id"]').val();
                const $submitBtn = $form.find('.pcfb-submit-btn');
                const $messagesContainer = $form.find('.pcfb-form-messages');
                
                console.log('Form submission started for ID:', formId);

                // اعتبارسنجی اولیه
                const validation = self.validateForm($form);
                if (!validation.isValid) {
                    self.showMessage($messagesContainer, validation.message, 'error');
                    self.highlightInvalidFields(validation.invalidFields);
                    return false;
                }

                // شروع ارسال
                self.vars.isSubmitting = true;
                
                self.setButtonState($submitBtn, 'loading');
                self.clearMessages($messagesContainer);
                self.removeFieldErrors($form);

                // جمع‌آوری داده‌های فرم
                const formData = self.collectFormData($form);
                console.log('Form data collected:', formData);

                // ارسال AJAX
                $.ajax({
                    url: pcfb_public.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pcfb_submit_form',
                        form_id: formId,
                        form_data: formData,
                        nonce: pcfb_public.nonce
                    },
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        console.log('Server response:', response);
                        
                        if (response.success) {
                            self.handleSubmissionSuccess($form, response.data);
                        } else {
                            self.handleSubmissionError($messagesContainer, response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        
                        let errorMessage = 'خطای ارتباط با سرور. لطفاً مجدداً تلاش کنید.';
                        if (status === 'timeout') {
                            errorMessage = 'زمان ارسال به پایان رسید. لطفاً مجدداً تلاش کنید.';
                        }
                        
                        self.handleSubmissionError($messagesContainer, errorMessage);
                    },
                    complete: function() {
                        self.vars.isSubmitting = false;
                        self.setButtonState($submitBtn, 'normal');
                    }
                });
            });
        },

        // اعتبارسنجی real-time فیلدها
        initRealTimeValidation: function() {
            const self = this;
            
            $(document).on('blur', '.pcfb-public-form input, .pcfb-public-form select, .pcfb-public-form textarea', function() {
                const $field = $(this);
                self.validateField($field);
            });
            
            $(document).on('input', '.pcfb-public-form input[required], .pcfb-public-form textarea[required]', function() {
                const $field = $(this);
                if ($field.val().trim() !== '') {
                    self.removeFieldError($field);
                }
            });
        },

        // مدیریت پیام‌های فرم
        initFormMessages: function() {
            const self = this;
            
            $(document).on('click', '.pcfb-form-messages .pcfb-close-message', function() {
                $(this).closest('.pcfb-form-message').fadeOut();
            });
        },

        // اعتبارسنجی کامل فرم
        validateForm: function($form) {
            const invalidFields = [];
            let isValid = true;
            let errorMessage = '';
            
            // بررسی فیلدهای اجباری
            $form.find('[required]').each(function() {
                const $field = $(this);
                const fieldValidation = self.validateFieldType($field);
                
                if (!fieldValidation.isValid) {
                    isValid = false;
                    invalidFields.push($field);
                    
                    if (!errorMessage) {
                        errorMessage = fieldValidation.message;
                    }
                }
            });
            
            return {
                isValid: isValid,
                message: errorMessage || 'لطفاً خطاهای فرم را برطرف کنید.',
                invalidFields: invalidFields
            };
        },

        // اعتبارسنجی یک فیلد خاص
        validateField: function($field) {
            const validation = this.validateFieldType($field);
            
            if (!validation.isValid) {
                this.showFieldError($field, validation.message);
                return false;
            } else {
                this.removeFieldError($field);
                return true;
            }
        },

        // اعتبارسنجی بر اساس نوع فیلد
        validateFieldType: function($field) {
            const value = $field.val().trim();
            const isRequired = $field.prop('required');
            const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
            
            // بررسی فیلدهای اجباری
            if (isRequired && value === '') {
                return {
                    isValid: false,
                    message: 'این فیلد اجباری است.'
                };
            }
            
            // اگر فیلد خالی است و اجباری نیست، معتبر است
            if (value === '' && !isRequired) {
                return { isValid: true };
            }
            
            // اعتبارسنجی بر اساس نوع فیلد
            switch (fieldType) {
                case 'email':
                    if (!this.isValidEmail(value)) {
                        return {
                            isValid: false,
                            message: 'لطفاً یک ایمیل معتبر وارد کنید.'
                        };
                    }
                    break;
                    
                case 'number':
                    if (!this.isValidNumber(value)) {
                        return {
                            isValid: false,
                            message: 'لطفاً یک عدد معتبر وارد کنید.'
                        };
                    }
                    break;
            }
            
            return { isValid: true };
        },

        // جمع‌آوری داده‌های فرم
        collectFormData: function($form) {
            const formData = {};
            
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                const fieldType = $field.attr('type');
                
                if (!fieldName || $field.attr('disabled') || fieldName === 'form_id') {
                    return;
                }
                
                if (fieldType === 'checkbox') {
                    if (!formData[fieldName]) {
                        formData[fieldName] = [];
                    }
                    if ($field.is(':checked')) {
                        formData[fieldName].push($field.val());
                    }
                } else if (fieldType === 'radio') {
                    if ($field.is(':checked')) {
                        formData[fieldName] = $field.val();
                    }
                } else {
                    formData[fieldName] = $field.val();
                }
            });
            
            return formData;
        },

        // مدیریت موفقیت‌آمیز ارسال
        handleSubmissionSuccess: function($form, responseData) {
            const $messagesContainer = $form.find('.pcfb-form-messages');
            
            this.showMessage($messagesContainer, responseData.message, 'success');
            this.clearForm($form);
            
            // اسکرول به پیام موفقیت
            $('html, body').animate({
                scrollTop: $messagesContainer.offset().top - 100
            }, 500);
        },

        // مدیریت خطای ارسال
        handleSubmissionError: function($messagesContainer, errorMessage) {
            this.showMessage($messagesContainer, errorMessage, 'error');
        },

        // نمایش پیام
        showMessage: function($container, message, type) {
            const messageClass = type === 'success' ? 'pcfb-message-success' : 'pcfb-message-error';
            const icon = type === 'success' ? '✅' : '❌';
            
            const messageHTML = `
                <div class="pcfb-form-message ${messageClass}">
                    <span class="pcfb-message-icon">${icon}</span>
                    <span class="pcfb-message-text">${message}</span>
                    <button type="button" class="pcfb-close-message">×</button>
                </div>
            `;
            
            $container.html(messageHTML).slideDown();
            
            // بستن خودکار پیام‌های موفقیت بعد از 5 ثانیه
            if (type === 'success') {
                setTimeout(() => {
                    $container.slideUp();
                }, 5000);
            }
        },

        // پاک کردن پیام‌ها
        clearMessages: function($container) {
            $container.slideUp().empty();
        },

        // نمایش خطای فیلد
        showFieldError: function($field, message) {
            const $fieldWrapper = $field.closest('.pcfb-field-frontend');
            $fieldWrapper.addClass('pcfb-field-error');
            
            let $errorMessage = $fieldWrapper.find('.pcfb-field-error-message');
            if ($errorMessage.length === 0) {
                $errorMessage = $(`<span class="pcfb-field-error-message">${message}</span>`);
                $fieldWrapper.append($errorMessage);
            } else {
                $errorMessage.text(message);
            }
        },

        // حذف خطای فیلد
        removeFieldError: function($field) {
            const $fieldWrapper = $field.closest('.pcfb-field-frontend');
            $fieldWrapper.removeClass('pcfb-field-error');
            $fieldWrapper.find('.pcfb-field-error-message').remove();
        },

        // هایلایت فیلدهای نامعتبر
        highlightInvalidFields: function(invalidFields) {
            invalidFields.forEach($field => {
                this.showFieldError($field, 'این فیلد نیاز به توجه دارد.');
            });
        },

        // حذف تمام خطاهای فیلدها
        removeFieldErrors: function($form) {
            $form.find('.pcfb-field-frontend').removeClass('pcfb-field-error');
            $form.find('.pcfb-field-error-message').remove();
        },

        // پاک کردن فرم پس از ارسال موفق
        clearForm: function($form) {
            $form[0].reset();
            this.removeFieldErrors($form);
        },

        // تغییر وضعیت دکمه ارسال
        setButtonState: function($button, state) {
            const $btnText = $button.find('.btn-text');
            const $btnLoading = $button.find('.btn-loading');
            
            if (state === 'loading') {
                $button.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
            } else {
                $button.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        },

        // توابع کمکی برای اعتبارسنجی
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        isValidNumber: function(number) {
            return !isNaN(parseFloat(number)) && isFinite(number);
        }
    };

    // راه‌اندازی سیستم frontend
    PCFB_Frontend.init();

    // در دسترس قرار دادن global
    window.PCFB_Frontend = PCFB_Frontend;
});