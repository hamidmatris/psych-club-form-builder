/**
 * Psych Club Form Builder - Admin JavaScript
 * مدیریت فرم‌ساز در بخش مدیریت وردپرس
 */

jQuery(document).ready(function($) {
    'use strict';

    // آبجکت اصلی برای مدیریت افزونه
    const PCFB_Admin = {
        
        // متغیرهای global
        vars: {
            currentFormId: 0,
            fieldCount: 0,
            isDragging: false,
            isAddingField: false
        },

        // مقداردهی اولیه
        init: function() {
            this.initDragDrop();
            this.initEventListeners();
            this.initFormBuilder();
            this.toggleBuildButton();
        },

        // مدیریت کشیدن و رها کردن
        initDragDrop: function() {
            const self = this;
            
            // رویدادهای drag برای ابزارها
            $('.pcfb-tool').on('dragstart', function(e) {
                e.originalEvent.dataTransfer.setData('type', $(this).data('type'));
                $(this).addClass('dragging');
                self.vars.isDragging = true;
            });

            $('.pcfb-tool').on('dragend', function() {
                $(this).removeClass('dragging');
                self.vars.isDragging = false;
            });

            // رویدادهای drop برای پیش‌نمایش
            $('#pcfb-preview')
                .off('dragover drop dragleave')
                .on('dragover', function(e) {
                    e.preventDefault();
                    e.originalEvent.dataTransfer.dropEffect = 'copy';
                    $(this).addClass('drag-over');
                })
                .on('dragleave', function() {
                    $(this).removeClass('drag-over');
                })
                .on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag-over');
                    
                    const type = e.originalEvent.dataTransfer.getData('type');
                    if (type && !self.vars.isAddingField) {
                        self.addFieldToPreview(type);
                    }
                });
        },

        // اضافه کردن فیلد جدید به پیش‌نمایش
        addFieldToPreview: function(fieldType) {
            if (this.vars.isAddingField) return;
            this.vars.isAddingField = true;
            
            this.vars.fieldCount++;
            const fieldId = 'field_' + this.vars.fieldCount;
            
            const fieldHTML = this.generateFieldHTML(fieldType, fieldId);
            $('#pcfb-preview').append(fieldHTML);
            
            this.initFieldEvents(fieldId);
            this.updateJSONPreview();
            this.toggleBuildButton();
            this.initFieldSorting();
            
            setTimeout(() => {
                this.vars.isAddingField = false;
            }, 100);
        },

        // تولید HTML برای انواع فیلدها
        generateFieldHTML: function(fieldType, fieldId) {
            const baseHTML = `
                <div class="pcfb-field" data-type="${fieldType}" data-field-id="${fieldId}">
                    <div class="pcfb-field-actions">
                        <button type="button" class="button button-small pcfb-remove-field" title="حذف فیلد">❌</button>
                        <button type="button" class="button button-small pcfb-edit-field" title="ویرایش فیلد">⚙️</button>
                    </div>
                    <div class="pcfb-field-content">
                        ${this.getFieldContentHTML(fieldType, fieldId)}
                    </div>
                    <div class="pcfb-field-settings" style="display: none;">
                        ${this.getFieldSettingsHTML(fieldType, fieldId)}
                    </div>
                </div>
            `;
            
            return baseHTML;
        },

        // محتوای اصلی فیلد بر اساس نوع
        getFieldContentHTML: function(fieldType, fieldId) {
            const fieldName = this.getPersianName(fieldType);
            
            switch(fieldType) {
                case 'text':
                case 'email':
                case 'number':
                case 'date':
                    return `
                        <label for="${fieldId}">
                            ${fieldName}:
                            <input type="${fieldType}" id="${fieldId}" class="pcfb-field-input">
                        </label>
                    `;
                
                case 'textarea':
                    return `
                        <label for="${fieldId}">
                            ${fieldName}:
                            <textarea id="${fieldId}" class="pcfb-field-input" rows="3"></textarea>
                        </label>
                    `;
                
                case 'select':
                    return `
                        <label for="${fieldId}">
                            ${fieldName}:
                            <select id="${fieldId}" class="pcfb-field-input">
                                <option value="">انتخاب کنید</option>
                                <option value="option1">گزینه ۱</option>
                                <option value="option2">گزینه ۲</option>
                            </select>
                        </label>
                    `;
                
                case 'checkbox':
                    return `
                        <div class="pcfb-field-label">${fieldName}:</div>
                        <label class="pcfb-checkbox-option">
                            <input type="checkbox" value="option1"> گزینه ۱
                        </label>
                        <label class="pcfb-checkbox-option">
                            <input type="checkbox" value="option2"> گزینه ۲
                        </label>
                    `;
                
                case 'radio':
                    return `
                        <div class="pcfb-field-label">${fieldName}:</div>
                        <label class="pcfb-radio-option">
                            <input type="radio" name="${fieldId}" value="option1"> گزینه ۱
                        </label>
                        <label class="pcfb-radio-option">
                            <input type="radio" name="${fieldId}" value="option2"> گزینه ۲
                        </label>
                    `;
                
                default:
                    return `<div>نوع فیلد نامعتبر: ${fieldType}</div>`;
            }
        },

        // تنظیمات فیلدها
        getFieldSettingsHTML: function(fieldType, fieldId) {
            let settingsHTML = `
                <div class="pcfb-setting-group">
                    <label for="${fieldId}_label">عنوان فیلد:</label>
                    <input type="text" id="${fieldId}_label" class="field-label" value="${this.getPersianName(fieldType)}">
                </div>
                
                <div class="pcfb-setting-group">
                    <label for="${fieldId}_name">نام فیلد (انگلیسی):</label>
                    <input type="text" id="${fieldId}_name" class="field-name" value="${fieldType}_${this.vars.fieldCount}">
                </div>
                
                <div class="pcfb-setting-group">
                    <label>
                        <input type="checkbox" id="${fieldId}_required" class="field-required"> فیلد اجباری
                    </label>
                </div>
            `;
            
            if (fieldType === 'select' || fieldType === 'checkbox' || fieldType === 'radio') {
                settingsHTML += `
                    <div class="pcfb-setting-group">
                        <label>گزینه‌ها:</label>
                        <div class="pcfb-options-container" id="${fieldId}_options">
                            <div class="pcfb-option-item">
                                <input type="text" value="گزینه ۱" class="pcfb-option-input">
                                <button type="button" class="button button-small pcfb-remove-option">❌</button>
                            </div>
                            <div class="pcfb-option-item">
                                <input type="text" value="گزینه ۲" class="pcfb-option-input">
                                <button type="button" class="button button-small pcfb-remove-option">❌</button>
                            </div>
                        </div>
                        <button type="button" class="button button-small pcfb-add-option">+ افزودن گزینه</button>
                    </div>
                `;
            } else {
                settingsHTML += `
                    <div class="pcfb-setting-group">
                        <label for="${fieldId}_placeholder">متن راهنما (Placeholder):</label>
                        <input type="text" id="${fieldId}_placeholder" class="field-placeholder" placeholder="متن راهنما...">
                    </div>
                `;
            }
            
            return settingsHTML;
        },

        // مدیریت رویدادهای فیلدها
        initFieldEvents: function(fieldId) {
            const self = this;
            const $field = $('[data-field-id="' + fieldId + '"]');
            
            // حذف فیلد
            $field.find('.pcfb-remove-field').on('click', function() {
                if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                    $field.remove();
                    self.vars.fieldCount--;
                    self.updateJSONPreview();
                    self.toggleBuildButton();
                }
            });
            
            // نمایش/مخفی کردن تنظیمات
            $field.find('.pcfb-edit-field').on('click', function() {
                $field.find('.pcfb-field-settings').slideToggle();
            });
            
            // به‌روزرسانی real-time تنظیمات
            $field.find('.field-label, .field-name, .field-required, .field-placeholder, .pcfb-option-input').on('input change', function() {
                self.updateFieldPreview(fieldId);
                self.updateJSONPreview();
            });
            
            // مدیریت گزینه‌ها
            $field.on('click', '.pcfb-add-option', function() {
                self.addOptionToField(fieldId);
            });
            
            $field.on('click', '.pcfb-remove-option', function() {
                $(this).closest('.pcfb-option-item').remove();
                self.updateFieldPreview(fieldId);
                self.updateJSONPreview();
            });
        },

        // اضافه کردن گزینه جدید به فیلد
        addOptionToField: function(fieldId) {
            const $optionsContainer = $('#' + fieldId + '_options');
            const optionCount = $optionsContainer.children().length + 1;
            
            const optionHTML = `
                <div class="pcfb-option-item">
                    <input type="text" value="گزینه ${optionCount}" class="pcfb-option-input">
                    <button type="button" class="button button-small pcfb-remove-option">❌</button>
                </div>
            `;
            
            $optionsContainer.append(optionHTML);
            
            $optionsContainer.find('.pcfb-remove-option').last().on('click', function() {
                $(this).closest('.pcfb-option-item').remove();
                this.updateFieldPreview(fieldId);
                this.updateJSONPreview();
            });
            
            $optionsContainer.find('.pcfb-option-input').last().on('input', function() {
                this.updateFieldPreview(fieldId);
                this.updateJSONPreview();
            });
        },

        // به‌روزرسانی پیش‌نمایش فیلد
        updateFieldPreview: function(fieldId) {
            const $field = $('[data-field-id="' + fieldId + '"]');
            const fieldType = $field.data('type');
            const newLabel = $field.find('#' + fieldId + '_label').val();
            
            if (fieldType === 'checkbox' || fieldType === 'radio') {
                $field.find('.pcfb-field-label').text(newLabel + ':');
            } else {
                $field.find('label').contents().first().replaceWith(newLabel + ':');
            }
            
            const placeholder = $field.find('#' + fieldId + '_placeholder').val();
            if (placeholder) {
                $field.find('.pcfb-field-input').attr('placeholder', placeholder);
            }
        },

        // فعال‌سازی قابلیت مرتب‌سازی فیلدها
        initFieldSorting: function() {
            const $preview = $('#pcfb-preview');
            
            $preview.sortable({
                items: '.pcfb-field',
                cursor: 'move',
                opacity: 0.7,
                placeholder: 'pcfb-field-placeholder',
                stop: function() {
                    this.updateJSONPreview();
                }.bind(this)
            });
        },

        // به‌روزرسانی پیش‌نمایش JSON
        updateJSONPreview: function() {
            const formData = this.collectFormData();
            $('#pcfb-json').text(JSON.stringify(formData, null, 2));
        },

        // جمع‌آوری داده‌های فرم
        collectFormData: function() {
            const formData = {
                form_name: 'فرم جدید',
                fields: []
            };
            
            $('.pcfb-field').each(function() {
                const $field = $(this);
                const fieldType = $field.data('type');
                const fieldId = $field.data('field-id');
                
                const fieldData = {
                    type: fieldType,
                    name: $field.find('.field-name').val() || fieldType + '_' + fieldId,
                    label: $field.find('.field-label').val() || this.getPersianName(fieldType),
                    required: $field.find('.field-required').is(':checked') || false,
                    placeholder: $field.find('.field-placeholder').val() || ''
                };
                
                if (fieldType === 'select' || fieldType === 'checkbox' || fieldType === 'radio') {
                    fieldData.options = [];
                    $field.find('.pcfb-option-input').each(function() {
                        const value = $(this).val().trim();
                        if (value) {
                            fieldData.options.push(value);
                        }
                    });
                }
                
                formData.fields.push(fieldData);
            });
            
            return formData;
        },

        // نمایش/مخفی کردن دکمه ساخت فرم
        toggleBuildButton: function() {
            const fieldCount = $('.pcfb-field').length;
            if (fieldCount > 0) {
                $('#pcfb-build-form').show();
            } else {
                $('#pcfb-build-form').hide();
            }
        },

        // رویدادهای عمومی
        initEventListeners: function() {
            const self = this;
            
            $('#pcfb-clear').on('click', function() {
                if (confirm('آیا از پاک کردن تمام فیلدها مطمئن هستید؟')) {
                    $('.pcfb-field').remove();
                    self.vars.fieldCount = 0;
                    self.updateJSONPreview();
                    self.toggleBuildButton();
                }
            });
            
            $('#pcfb-build-form').on('click', function() {
                self.saveForm();
            });
        },

        // ذخیره فرم در دیتابیس
        saveForm: function() {
            const formData = this.collectFormData();
            const formName = prompt('نامی برای فرم خود وارد کنید:', formData.form_name);
            
            if (!formName || formName.trim() === '') {
                alert('لطفاً یک نام برای فرم وارد کنید.');
                return;
            }

            const saveData = {
                action: 'pcfb_save_form',
                form_name: formName.trim(),
                form_json: JSON.stringify(formData),
                nonce: pcfb_admin.nonce
            };

            const $submitBtn = $('#pcfb-build-form');
            const originalText = $submitBtn.text();
            
            $.ajax({
                url: pcfb_admin.ajax_url,
                type: 'POST',
                data: saveData,
                dataType: 'json',
                beforeSend: function() {
                    $submitBtn.prop('disabled', true).text('در حال ذخیره...');
                },
                success: function(response) {
                    if (response.success) {
                        alert('فرم با موفقیت ذخیره شد!');
                        window.location.href = '?page=pcfb-settings&tab=forms';
                    } else {
                        alert('خطا در ذخیره فرم: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('خطای ارتباط با سرور: ' + error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('ساخت فرم');
                }
            });
        },

        // تبدیل نام انگلیسی به فارسی
        getPersianName: function(fieldType) {
            const names = {
                'text': 'متن',
                'email': 'ایمیل',
                'number': 'عدد',
                'date': 'تاریخ',
                'textarea': 'متن بلند',
                'select': 'انتخابی',
                'checkbox': 'چند انتخابی',
                'radio': 'تک انتخابی'
            };
            
            return names[fieldType] || fieldType;
        },

        // مقداردهی اولیه فرم‌ساز
        initFormBuilder: function() {
            // هیچ کاری لازم نیست - برای سازگاری آینده
        }
    };

    // راه‌اندازی افزونه
    PCFB_Admin.init();

    // در دسترس قرار دادن global
    window.PCFB_Admin = PCFB_Admin;
});