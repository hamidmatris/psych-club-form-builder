/**
 * Psych Club Form Builder - Frontend JavaScript
 * مدیریت فرم‌ها در بخش عمومی سایت - نسخه بهبود یافته
 */

(function($) {
    'use strict';

    class PCFBFrontend {
        constructor() {
            this.isSubmitting = false;
            this.submissionQueue = new Map();
            this.init();
        }

        init() {
            this.bindEvents();
            this.initFormValidation();
            this.initFileUploads();
            this.initCharacterCounters();
            console.log('PCFB Frontend initialized');
        }

        bindEvents() {
            // ارسال فرم
            $(document).on('submit', '.pcfb-public-form', (e) => this.handleFormSubmit(e));
            
            // اعتبارسنجی real-time
            $(document).on('blur change input', '.pcfb-public-form [data-validate]', (e) => 
                this.validateField($(e.target)));
            
            // مدیریت فایل‌ها
            $(document).on('change', '.pcfb-public-form input[type="file"]', (e) => 
                this.handleFileSelection(e));
            
            // بستن پیام‌ها
            $(document).on('click', '.pcfb-close-message', (e) => 
                this.closeMessage($(e.target).closest('.pcfb-form-message')));
        }

        /**
         * مدیریت ارسال فرم
         */
        async handleFormSubmit(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formId = $form.data('form-id') || $form.find('input[name="form_id"]').val();
            
            if (this.isSubmitting) {
                this.showToast('لطفاً منتظر بمانید...', 'warning');
                return false;
            }

            // بررسی وجود فرم در صف
            if (this.submissionQueue.has(formId)) {
                this.showToast('این فرم در حال ارسال است...', 'warning');
                return false;
            }

            // اعتبارسنجی پیش از ارسال
            const validation = await this.validateForm($form);
            if (!validation.isValid) {
                this.showFormErrors($form, validation.errors);
                this.scrollToFirstError($form);
                return false;
            }

            return this.submitForm($form, formId);
        }

        /**
         * اعتبارسنجی کامل فرم
         */
        async validateForm($form) {
            const errors = [];
            const $fields = $form.find('[name]:not([type="hidden"]):not([disabled])');
            
            for (let field of $fields) {
                const $field = $(field);
                const fieldErrors = await this.validateField($field, true);
                
                if (fieldErrors.length > 0) {
                    errors.push({
                        field: $field,
                        errors: fieldErrors
                    });
                }
            }

            // اعتبارسنجی custom (مثلاً مقایسه پسوردها)
            const customErrors = this.validateCustomRules($form);
            errors.push(...customErrors);

            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        /**
         * اعتبارسنجی یک فیلد
         */
        async validateField($field, silent = false) {
            const errors = [];
            const value = $field.val().trim();
            const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
            const isRequired = $field.prop('required') || $field.attr('data-required') === 'true';
            
            // بررسی فیلدهای اجباری
            if (isRequired && !value) {
                errors.push('این فیلد اجباری است');
            }
            
            // اگر فیلد خالی است و اجباری نیست، بررسی بیشتر نیاز نیست
            if (!value && !isRequired) {
                if (!silent) this.clearFieldError($field);
                return errors;
            }
            
            // اعتبارسنجی بر اساس نوع فیلد
            switch (fieldType) {
                case 'email':
                    if (!this.isValidEmail(value)) {
                        errors.push('لطفاً یک ایمیل معتبر وارد کنید');
                    }
                    break;
                    
                case 'number':
                    const numberValidation = this.validateNumberField($field, value);
                    if (!numberValidation.isValid) {
                        errors.push(numberValidation.error);
                    }
                    break;
                    
                case 'tel':
                    if (!this.isValidPhoneNumber(value)) {
                        errors.push('لطفاً شماره تلفن معتبر وارد کنید');
                    }
                    break;
                    
                case 'url':
                    if (!this.isValidUrl(value)) {
                        errors.push('لطفاً آدرس اینترنتی معتبر وارد کنید');
                    }
                    break;
                    
                case 'date':
                    if (!this.isValidDate(value)) {
                        errors.push('لطفاً تاریخ معتبر وارد کنید');
                    }
                    break;
                    
                case 'file':
                    const fileValidation = await this.validateFileField($field);
                    if (!fileValidation.isValid) {
                        errors.push(...fileValidation.errors);
                    }
                    break;
            }
            
            // اعتبارسنجی طول متن
            const lengthValidation = this.validateFieldLength($field, value);
            if (!lengthValidation.isValid) {
                errors.push(lengthValidation.error);
            }
            
            // اعتبارسنجی pattern
            const patternValidation = this.validateFieldPattern($field, value);
            if (!patternValidation.isValid) {
                errors.push(patternValidation.error);
            }
            
            // نمایش یا پاک کردن خطاها
            if (!silent) {
                if (errors.length > 0) {
                    this.showFieldError($field, errors[0]);
                } else {
                    this.clearFieldError($field);
                }
            }
            
            return errors;
        }

        /**
         * ارسال فرم به سرور
         */
        async submitForm($form, formId) {
            this.isSubmitting = true;
            this.submissionQueue.set(formId, true);
            
            const $submitBtn = $form.find('.pcfb-submit-btn');
            const $messagesContainer = $form.find('.pcfb-form-messages');
            
            try {
                this.setButtonState($submitBtn, 'loading');
                this.clearMessages($messagesContainer);
                this.clearAllFieldErrors($form);
                
                // جمع‌آوری داده‌های فرم (شامل فایل‌ها)
                const formData = await this.prepareFormData($form);
                
                // ارسال درخواست
                const response = await this.sendFormData(formData);
                
                if (response.success) {
                    await this.handleSuccessResponse($form, response.data);
                } else {
                    throw new Error(response.data || 'خطای نامشخص از سرور');
                }
                
            } catch (error) {
                this.handleSubmissionError($messagesContainer, error.message);
                console.error('Form submission error:', error);
                
            } finally {
                this.isSubmitting = false;
                this.submissionQueue.delete(formId);
                this.setButtonState($submitBtn, 'normal');
            }
        }

        /**
         * آماده‌سازی داده‌های فرم برای ارسال
         */
        async prepareFormData($form) {
            const formData = new FormData();
            const fieldsData = {};
            
            // افزودن فیلدهای استاندارد
            $form.serializeArray().forEach(item => {
                if (item.name !== 'form_data') {
                    formData.append(item.name, item.value);
                    fieldsData[item.name] = item.value;
                }
            });
            
            // افزودن فایل‌ها
            const fileFields = $form.find('input[type="file"]');
            for (let field of fileFields) {
                const $field = $(field);
                const files = $field[0].files;
                
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        formData.append(`${$field.attr('name')}[]`, files[i]);
                    }
                }
            }
            
            // افزودن داده‌های JSON
            formData.append('form_data', JSON.stringify(fieldsData));
            formData.append('action', 'pcfb_submit_form');
            formData.append('nonce', pcfb_public.nonce);
            
            return formData;
        }

        /**
         * ارسال داده‌ها به سرور
         */
        async sendFormData(formData) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);
            
            try {
                const response = await fetch(pcfb_public.ajax_url, {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
                
            } catch (error) {
                if (error.name === 'AbortError') {
                    throw new Error('زمان ارسال به پایان رسید. لطفاً مجدداً تلاش کنید.');
                }
                throw error;
            }
        }

        /**
         * مدیریت پاسخ موفقیت‌آمیز
         */
        async handleSuccessResponse($form, responseData) {
            const $messagesContainer = $form.find('.pcfb-form-messages');
            
            // نمایش پیام موفقیت
            this.showMessage($messagesContainer, responseData.message, 'success');
            
            // پاک کردن فرم
            this.clearForm($form);
            
            // redirect اگر مشخص شده
            if (responseData.redirect) {
                setTimeout(() => {
                    window.location.href = responseData.redirect;
                }, 2000);
            }
            
            // اسکرول به پیام
            this.scrollToElement($messagesContainer);
            
            // رویداد custom برای integrations
            $(document).trigger('pcfb:formSubmitted', [responseData]);
        }

        /**
         * مدیریت خطاهای ارسال
         */
        handleSubmissionError($container, errorMessage) {
            this.showMessage($container, errorMessage, 'error');
            this.scrollToElement($container);
        }

        /**
         * نمایش پیام به کاربر
         */
        showMessage($container, message, type) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            const messageHTML = `
                <div class="pcfb-form-message pcfb-message-${type}">
                    <span class="pcfb-message-icon">${icons[type]}</span>
                    <span class="pcfb-message-text">${message}</span>
                    <button type="button" class="pcfb-close-message" aria-label="بستن">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            `;
            
            $container.html(messageHTML).slideDown(300);
            
            // بستن خودکار پیام‌های موفقیت
            if (type === 'success') {
                setTimeout(() => {
                    $container.slideUp(300, () => $container.empty());
                }, 5000);
            }
        }

        /**
         * ==================== توابع اعتبارسنجی پیشرفته ====================
         */

        validateNumberField($field, value) {
            const numValue = parseFloat(value);
            const min = $field.attr('min');
            const max = $field.attr('max');
            const step = $field.attr('step');
            
            if (isNaN(numValue)) {
                return { isValid: false, error: 'لطفاً یک عدد معتبر وارد کنید' };
            }
            
            if (min && numValue < parseFloat(min)) {
                return { isValid: false, error: `عدد باید بزرگتر یا برابر ${min} باشد` };
            }
            
            if (max && numValue > parseFloat(max)) {
                return { isValid: false, error: `عدد باید کوچکتر یا برابر ${max} باشد` };
            }
            
            if (step && step !== 'any') {
                const stepValue = parseFloat(step);
                if ((numValue / stepValue) % 1 !== 0) {
                    return { isValid: false, error: `عدد باید مضرب ${step} باشد` };
                }
            }
            
            return { isValid: true };
        }

        async validateFileField($field) {
            const errors = [];
            const files = $field[0].files;
            const maxSize = $field.data('max-size') || pcfb_public.settings.max_file_size;
            const allowedTypes = $field.data('allowed-types') || pcfb_public.settings.allowed_file_types;
            
            if (files.length === 0 && $field.prop('required')) {
                errors.push('انتخاب فایل اجباری است');
                return { isValid: false, errors };
            }
            
            for (let file of files) {
                // بررسی حجم فایل
                if (file.size > maxSize) {
                    const maxSizeMB = (maxSize / 1024 / 1024).toFixed(1);
                    errors.push(`حجم فایل "${file.name}" بسیار بزرگ است (حداکثر: ${maxSizeMB}MB)`);
                }
                
                // بررسی نوع فایل
                if (allowedTypes && !allowedTypes.includes(file.type)) {
                    errors.push(`نوع فایل "${file.name}" مجاز نیست`);
                }
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        validateFieldLength($field, value) {
            const minLength = $field.attr('minlength');
            const maxLength = $field.attr('maxlength');
            
            if (minLength && value.length < parseInt(minLength)) {
                return {
                    isValid: false,
                    error: `متن باید حداقل ${minLength} کاراکتر باشد`
                };
            }
            
            if (maxLength && value.length > parseInt(maxLength)) {
                return {
                    isValid: false,
                    error: `متن نباید بیشتر از ${maxLength} کاراکتر باشد`
                };
            }
            
            return { isValid: true };
        }

        validateFieldPattern($field, value) {
            const pattern = $field.attr('pattern');
            if (!pattern) return { isValid: true };
            
            const regex = new RegExp(pattern);
            if (!regex.test(value)) {
                return {
                    isValid: false,
                    error: 'قالب وارد شده معتبر نیست'
                };
            }
            
            return { isValid: true };
        }

        /**
         * ==================== توابع کمکی ====================
         */

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        isValidPhoneNumber(phone) {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
            return phoneRegex.test(phone.replace(/\s/g, ''));
        }

        isValidUrl(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }

        isValidDate(dateString) {
            return !isNaN(Date.parse(dateString));
        }

        scrollToFirstError($form) {
            const $firstError = $form.find('.pcfb-field-error').first();
            if ($firstError.length) {
                this.scrollToElement($firstError);
            }
        }

        scrollToElement($element) {
            $('html, body').animate({
                scrollTop: $element.offset().top - 100
            }, 500);
        }

        setButtonState($button, state) {
            const states = {
                loading: { disabled: true, text: 'در حال ارسال...', showLoading: true },
                normal: { disabled: false, text: 'ارسال فرم', showLoading: false }
            };
            
            const config = states[state];
            $button.prop('disabled', config.disabled);
            $button.find('.btn-text').text(config.text);
            $button.find('.btn-loading').toggle(config.showLoading);
        }

        // سایر توابع مدیریت UI...
    }

    // راه‌اندازی هنگام بارگذاری صفحه
    $(document).ready(() => {
        window.PCFBFrontend = new PCFBFrontend();
    });

})(jQuery);