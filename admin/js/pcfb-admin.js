/**
 * Psych Club Form Builder - Admin JavaScript (نسخه پیشرفته)
 * مدیریت فرم‌ساز در بخش مدیریت وردپرس
 */

class PCFBAdmin {
    constructor() {
        this.state = {
            currentFormId: 0,
            fields: new Map(),
            selectedField: null,
            isDragging: false,
            isProcessing: false,
            formData: {
                name: '',
                description: '',
                settings: {},
                fields: []
            }
        };

        this.selectors = {
            builder: '#pcfb-builder',
            preview: '#pcfb-preview-area',
            tools: '#pcfb-tools-list',
            settings: '#pcfb-field-settings',
            jsonOutput: '#pcfb-json-output',
            formName: '#pcfb-form-name'
        };

        this.init();
    }

    /**
     * مقداردهی اولیه
     */
    init() {
        this.createUI();
        this.bindEvents();
        this.loadExistingForm();
        this.updateFieldCount();
        this.updateJSONPreview();
    }

    /**
     * ایجاد رابط کاربری اگر وجود ندارد
     */
    createUI() {
        if ($(this.selectors.builder).length === 0) return;

        // ایجاد ساختار پایه اگر لازم باشد
        const baseHTML = `
            <div class="pcfb-builder-container">
                <div class="pcfb-builder-header">
                    <div class="pcfb-form-info">
                        <input type="text" id="pcfb-form-name" placeholder="نام فرم را وارد کنید..." 
                               class="pcfb-form-name-input" required>
                        <div class="pcfb-form-actions">
                            <button type="button" id="pcfb-save-form" class="button button-primary">
                                <span class="btn-text">ذخیره فرم</span>
                                <span class="btn-loading" style="display: none;">
                                    <span class="pcfb-spinner"></span>
                                    در حال ذخیره...
                                </span>
                            </button>
                            <button type="button" id="pcfb-preview-form" class="button">پیش‌نمایش</button>
                            <button type="button" id="pcfb-clear-form" class="button button-secondary">
                                پاک کردن همه
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pcfb-builder-interface">
                    <div class="pcfb-tools-panel">
                        <h3 class="pcfb-panel-title">فیلدهای فرم</h3>
                        <div class="pcfb-tools-list" id="pcfb-tools-list"></div>
                    </div>

                    <div class="pcfb-preview-panel">
                        <div class="pcfb-preview-header">
                            <h3 class="pcfb-panel-title">پیش‌نمایش فرم</h3>
                            <span class="pcfb-field-count">(0 فیلد)</span>
                        </div>
                        <div class="pcfb-preview-area" id="pcfb-preview-area">
                            <div class="pcfb-empty-state">
                                <div class="empty-icon">📋</div>
                                <p>فیلدهای مورد نظر را از پنل سمت چپ به اینجا بکشید و رها کنید.</p>
                            </div>
                        </div>
                    </div>

                    <div class="pcfb-settings-panel">
                        <h3 class="pcfb-panel-title">تنظیمات فیلد</h3>
                        <div class="pcfb-field-settings" id="pcfb-field-settings">
                            <div class="pcfb-no-selection">
                                <p>لطفاً یک فیلد را انتخاب کنید</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pcfb-code-panel">
                    <div class="pcfb-code-header">
                        <h3 class="pcfb-panel-title">کد فرم (JSON)</h3>
                        <div class="pcfb-code-actions">
                            <button type="button" id="pcfb-toggle-code" class="button button-small">
                                نمایش/مخفی
                            </button>
                            <button type="button" id="pcfb-copy-json" class="button button-small">
                                کپی کد
                            </button>
                        </div>
                    </div>
                    <div class="pcfb-code-container" id="pcfb-code-container" style="display: none;">
                        <pre id="pcfb-json-output"></pre>
                    </div>
                </div>
            </div>
        `;

        $(this.selectors.builder).html(baseHTML);
        this.createToolItems();
    }

    /**
     * ایجاد آیتم‌های ابزار
     */
    createToolItems() {
        const fieldTypes = [
            { type: 'text', icon: '📝', label: 'متن تک خطی', description: 'فیلد متنی یک خطی' },
            { type: 'textarea', icon: '📄', label: 'متن چند خطی', description: 'فیلد متنی چند خطی' },
            { type: 'email', icon: '📧', label: 'ایمیل', description: 'آدرس ایمیل' },
            { type: 'number', icon: '🔢', label: 'عدد', description: 'مقادیر عددی' },
            { type: 'tel', icon: '📱', label: 'تلفن', description: 'شماره تلفن' },
            { type: 'date', icon: '📅', label: 'تاریخ', description: 'انتخاب تاریخ' },
            { type: 'url', icon: '🔗', label: 'آدرس وب', description: 'لینک وب سایت' },
            { type: 'checkbox', icon: '✅', label: 'چک‌باکس', description: 'انتخاب چندتایی' },
            { type: 'radio', icon: '🔘', label: 'دکمه رادیویی', description: 'انتخاب تکی' },
            { type: 'select', icon: '⬇️', label: 'لیست انتخابی', description: 'لیست dropdown' },
            { type: 'file', icon: '📎', label: 'آپلود فایل', description: 'آپلود فایل' }
        ];

        fieldTypes.forEach(field => {
            const toolItem = this.createToolItem(field);
            $(this.selectors.tools).append(toolItem);
        });
    }

    /**
     * ایجاد یک آیتم ابزار
     */
    createToolItem(field) {
        return `
            <div class="pcfb-tool-item" draggable="true" data-type="${field.type}">
                <div class="tool-icon">${field.icon}</div>
                <div class="tool-content">
                    <div class="tool-label">${field.label}</div>
                    <div class="tool-description">${field.description}</div>
                </div>
            </div>
        `;
    }

    /**
     * اتصال تمام رویدادها
     */
    bindEvents() {
        this.bindDragAndDrop();
        this.bindToolEvents();
        this.bindPreviewEvents();
        this.bindSettingsEvents();
        this.bindActionEvents();
        this.bindKeyboardShortcuts();
    }

    /**
     * رویدادهای کشیدن و رها کردن
     */
    bindDragAndDrop() {
        const self = this;

        // Drag start برای ابزارها
        $(document).on('dragstart', '.pcfb-tool-item', function(e) {
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('type'));
            e.originalEvent.dataTransfer.effectAllowed = 'copy';
            $(this).addClass('dragging');
            self.state.isDragging = true;
        });

        $(document).on('dragend', '.pcfb-tool-item', function() {
            $(this).removeClass('dragging');
            self.state.isDragging = false;
        });

        // Drop zone events
        $(this.selectors.preview)
            .on('dragover', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'copy';
                $(this).addClass('drag-over');
            })
            .on('dragleave', function(e) {
                if (!$(this).is(e.relatedTarget) && !$(this).has(e.relatedTarget).length) {
                    $(this).removeClass('drag-over');
                }
            })
            .on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                const fieldType = e.originalEvent.dataTransfer.getData('text/plain');
                if (fieldType) {
                    self.addField(fieldType);
                }
            });
    }

    /**
     * رویدادهای ابزارها
     */
    bindToolEvents() {
        const self = this;

        // کلیک برای افزودن سریع
        $(document).on('click', '.pcfb-tool-item', function() {
            const fieldType = $(this).data('type');
            self.addField(fieldType);
        });

        // دابل کلیک برای افزودن و انتخاب
        $(document).on('dblclick', '.pcfb-tool-item', function() {
            const fieldType = $(this).data('type');
            const fieldId = self.addField(fieldType);
            self.selectField(fieldId);
        });
    }

    /**
     * رویدادهای ناحیه پیش‌نمایش
     */
    bindPreviewEvents() {
        const self = this;

        // انتخاب فیلد با کلیک
        $(document).on('click', '.pcfb-field', function(e) {
            if ($(e.target).closest('.pcfb-field-actions').length === 0) {
                const fieldId = $(this).data('field-id');
                self.selectField(fieldId);
            }
        });

        // حذف فیلد
        $(document).on('click', '.pcfb-field-remove', function(e) {
            e.stopPropagation();
            const fieldId = $(this).closest('.pcfb-field').data('field-id');
            self.removeField(fieldId);
        });

        // تنظیمات فیلد
        $(document).on('click', '.pcfb-field-settings-btn', function(e) {
            e.stopPropagation();
            const fieldId = $(this).closest('.pcfb-field').data('field-id');
            self.selectField(fieldId);
        });
    }

    /**
     * رویدادهای پنل تنظیمات
     */
    bindSettingsEvents() {
        const self = this;

        // نمایش/مخفی کردن کد JSON
        $('#pcfb-toggle-code').on('click', function() {
            self.toggleCodePanel();
        });

        // کپی کردن JSON
        $('#pcfb-copy-json').on('click', function() {
            self.copyJSONToClipboard();
        });

        // تغییرات real-time در تنظیمات
        $(document).on('input change', '.pcfb-setting-input, .pcfb-setting-textarea, .pcfb-setting-checkbox', function() {
            const fieldId = $(this).closest('.pcfb-field-settings-form').data('field-id');
            if (fieldId) {
                self.updateFieldFromSettings(fieldId);
            }
        });
    }

    /**
     * رویدادهای اقدامات اصلی
     */
    bindActionEvents() {
        const self = this;

        // ذخیره فرم
        $('#pcfb-save-form').on('click', function() {
            self.saveForm();
        });

        // پیش‌نمایش فرم
        $('#pcfb-preview-form').on('click', function() {
            self.previewForm();
        });

        // پاک کردن فرم
        $('#pcfb-clear-form').on('click', function() {
            self.clearForm();
        });

        // تغییر نام فرم
        $('#pcfb-form-name').on('input', function() {
            self.updateJSONPreview();
        });
    }

    /**
     * میانبرهای صفحه‌کلید
     */
    bindKeyboardShortcuts() {
        const self = this;

        $(document).on('keydown', function(e) {
            // جلوگیری از عملکرد پیش‌فرض در حالت ویرایش
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            // Ctrl+S برای ذخیره
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                self.saveForm();
            }

            // Delete برای حذف فیلد انتخاب شده
            if (e.key === 'Delete' && self.state.selectedField) {
                e.preventDefault();
                self.removeField(self.state.selectedField);
            }

            // Escape برای لغو انتخاب
            if (e.key === 'Escape') {
                self.deselectField();
            }
        });
    }

    /**
     * افزودن فیلد جدید
     */
    addField(fieldType) {
        if (this.state.isProcessing) return null;

        this.state.isProcessing = true;
        const fieldId = this.generateFieldId();

        const fieldData = {
            id: fieldId,
            type: fieldType,
            label: this.getDefaultLabel(fieldType),
            name: this.generateFieldName(fieldType),
            required: false,
            placeholder: '',
            description: '',
            options: ['select', 'radio', 'checkbox'].includes(fieldType) ? ['گزینه ۱', 'گزینه ۲'] : [],
            validation: this.getDefaultValidation(fieldType),
            cssClass: '',
            settings: {}
        };

        this.state.fields.set(fieldId, fieldData);
        this.renderField(fieldData);
        this.updateJSONPreview();
        this.updateFieldCount();

        setTimeout(() => {
            this.state.isProcessing = false;
        }, 50);

        return fieldId;
    }

    /**
     * رندر یک فیلد در پیش‌نمایش
     */
    renderField(fieldData) {
        const fieldHTML = this.generateFieldHTML(fieldData);
        $(this.selectors.preview).find('.pcfb-empty-state').remove();
        $(this.selectors.preview).append(fieldHTML);
        
        this.initFieldSorting();
    }

    /**
     * تولید HTML فیلد
     */
    generateFieldHTML(fieldData) {
        return `
            <div class="pcfb-field pcfb-field-${fieldData.type}" data-field-id="${fieldData.id}">
                <div class="pcfb-field-header">
                    <div class="pcfb-field-info">
                        <span class="field-type-icon">${this.getFieldIcon(fieldData.type)}</span>
                        <span class="field-label">${fieldData.label}</span>
                        ${fieldData.required ? '<span class="field-required-badge">اجباری</span>' : ''}
                    </div>
                    <div class="pcfb-field-actions">
                        <button type="button" class="pcfb-field-settings-btn" title="تنظیمات">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </button>
                        <button type="button" class="pcfb-field-remove" title="حذف">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <div class="pcfb-field-preview">
                    ${this.generateFieldPreview(fieldData)}
                </div>
            </div>
        `;
    }

    /**
     * انتخاب یک فیلد
     */
    selectField(fieldId) {
        // حذف انتخاب قبلی
        this.deselectField();

        // انتخاب جدید
        const $field = $(`.pcfb-field[data-field-id="${fieldId}"]`);
        if ($field.length === 0) return;

        $field.addClass('selected');
        this.state.selectedField = fieldId;
        this.showFieldSettings(fieldId);
    }

    /**
     * لغو انتخاب فیلد
     */
    deselectField() {
        $('.pcfb-field').removeClass('selected');
        this.state.selectedField = null;
        this.showFieldSettings(null);
    }

    /**
     * نمایش تنظیمات فیلد انتخاب شده
     */
    showFieldSettings(fieldId) {
        if (!fieldId) {
            $(this.selectors.settings).html(`
                <div class="pcfb-no-selection">
                    <p>لطفاً یک فیلد را انتخاب کنید</p>
                </div>
            `);
            return;
        }

        const fieldData = this.state.fields.get(fieldId);
        if (!fieldData) return;

        const settingsHTML = this.generateFieldSettingsHTML(fieldData);
        $(this.selectors.settings).html(settingsHTML);
        this.bindFieldSettingsEvents(fieldId);
    }

    /**
     * حذف فیلد
     */
    removeField(fieldId) {
        if (!confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
            return;
        }

        this.state.fields.delete(fieldId);
        $(`.pcfb-field[data-field-id="${fieldId}"]`).remove();
        
        if (this.state.selectedField === fieldId) {
            this.deselectField();
        }

        this.updateJSONPreview();
        this.updateFieldCount();

        // اگر فیلدی باقی نمانده، پیام خالی نشان دهید
        if (this.state.fields.size === 0) {
            $(this.selectors.preview).html(`
                <div class="pcfb-empty-state">
                    <div class="empty-icon">📋</div>
                    <p>فیلدهای مورد نظر را از پنل سمت چپ به اینجا بکشید و رها کنید.</p>
                </div>
            `);
        }
    }

    /**
     * فعال‌سازی مرتب‌سازی فیلدها
     */
    initFieldSorting() {
        const self = this;

        $(this.selectors.preview).sortable({
            items: '.pcfb-field',
            cursor: 'move',
            opacity: 0.7,
            placeholder: 'pcfb-field-placeholder',
            tolerance: 'pointer',
            start: function(e, ui) {
                ui.placeholder.height(ui.helper.outerHeight());
            },
            stop: function() {
                self.updateFieldOrder();
            }
        });
    }

    /**
     * به‌روزرسانی ترتیب فیلدها
     */
    updateFieldOrder() {
        const orderedFields = [];
        
        $(this.selectors.preview).find('.pcfb-field').each(function() {
            const fieldId = $(this).data('field-id');
            orderedFields.push(fieldId);
        });

        // به‌روزرسانی ترتیب در state
        const newFields = new Map();
        orderedFields.forEach(fieldId => {
            const fieldData = this.state.fields.get(fieldId);
            if (fieldData) {
                newFields.set(fieldId, fieldData);
            }
        });

        this.state.fields = newFields;
        this.updateJSONPreview();
    }

    /**
     * ذخیره فرم
     */
    async saveForm() {
        const formName = $(this.selectors.formName).val().trim();
        
        if (!formName) {
            this.showMessage('لطفاً نام فرم را وارد کنید', 'error');
            $(this.selectors.formName).focus();
            return;
        }

        if (this.state.fields.size === 0) {
            this.showMessage('فرم باید حداقل یک فیلد داشته باشد', 'error');
            return;
        }

        try {
            await this.performSave(formName);
        } catch (error) {
            this.showMessage('خطا در ذخیره فرم: ' + error.message, 'error');
        }
    }

    /**
     * انجام عملیات ذخیره
     */
    async performSave(formName) {
        const $saveBtn = $('#pcfb-save-form');
        const originalText = $saveBtn.find('.btn-text').text();

        this.setButtonState($saveBtn, 'loading');

        const formData = this.prepareFormData(formName);
        const response = await this.sendSaveRequest(formData);

        if (response.success) {
            this.showMessage('فرم با موفقیت ذخیره شد', 'success');
            
            // redirect اگر لازم باشد
            if (response.data.redirect_url) {
                setTimeout(() => {
                    window.location.href = response.data.redirect_url;
                }, 1500);
            }
        } else {
            throw new Error(response.data);
        }

        this.setButtonState($saveBtn, 'normal');
    }

    /**
     * سایر متدهای utility و helper...
     */

    // [بقیه متدها مانند generateFieldPreview, generateFieldSettingsHTML, 
    // prepareFormData, sendSaveRequest, و توابع helper...]
}

// راه‌اندازی هنگام بارگذاری DOM
jQuery(document).ready(function($) {
    window.PCFBAdmin = new PCFBAdmin();
});