/**
 * Psych Club Form Builder - JavaScript Core
 * مدیریت فرم‌ساز بصری
 */

document.addEventListener("DOMContentLoaded", function() {
    class PCFBFormBuilder {
        constructor() {
            this.fields = [];
            this.selectedField = null;
            this.fieldCounter = 0;
            this.isDragging = false;
            
            this.init();
        }

        init() {
            this.createUI();
            this.bindEvents();
            this.loadExistingForm();
            this.updateFieldCount();
        }

        /**
         * ایجاد رابط کاربری فرم‌ساز
         */
        createUI() {
            this.createBuilderContainer();
            this.createToolsPanel();
            this.createPreviewPanel();
            this.createSettingsPanel();
            this.createActionsPanel();
        }

        /**
         * ایجاد کانتینر اصلی
         */
        createBuilderContainer() {
            this.builder = document.getElementById('pcfb-builder');
            if (!this.builder) {
                console.error('عنصر #pcfb-builder یافت نشد');
                return;
            }

            this.builder.innerHTML = `
                <div class="pcfb-builder-container">
                    <div class="pcfb-builder-header">
                        <div class="pcfb-form-info">
                            <input type="text" id="pcfb-form-name" placeholder="نام فرم را وارد کنید..." class="pcfb-form-name-input">
                            <div class="pcfb-form-actions">
                                <button type="button" id="pcfb-save-form" class="button button-primary">ذخیره فرم</button>
                                <button type="button" id="pcfb-preview-form" class="button">پیش‌نمایش</button>
                                <button type="button" id="pcfb-clear-form" class="button button-secondary">پاک کردن همه</button>
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
                            <div class="pcfb-preview-area" id="pcfb-preview-area"></div>
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
                                <button type="button" id="pcfb-toggle-code" class="button button-small">نمایش/مخفی</button>
                                <button type="button" id="pcfb-copy-json" class="button button-small">کپی کد</button>
                            </div>
                        </div>
                        <div class="pcfb-code-container" id="pcfb-code-container" style="display: none;">
                            <pre id="pcfb-json-output"></pre>
                        </div>
                    </div>
                </div>
            `;

            this.previewArea = document.getElementById('pcfb-preview-area');
            this.toolsList = document.getElementById('pcfb-tools-list');
            this.settingsPanel = document.getElementById('pcfb-field-settings');
            this.jsonOutput = document.getElementById('pcfb-json-output');
            this.codeContainer = document.getElementById('pcfb-code-container');
        }

        /**
         * ایجاد پنل ابزارها
         */
        createToolsPanel() {
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
                this.toolsList.appendChild(toolItem);
            });
        }

        /**
         * ایجاد آیتم ابزار
         */
        createToolItem(field) {
            const toolDiv = document.createElement('div');
            toolDiv.className = 'pcfb-tool-item';
            toolDiv.dataset.type = field.type;
            toolDiv.draggable = true;
            toolDiv.innerHTML = `
                <div class="tool-icon">${field.icon}</div>
                <div class="tool-content">
                    <div class="tool-label">${field.label}</div>
                    <div class="tool-description">${field.description}</div>
                </div>
            `;

            return toolDiv;
        }

        /**
         * اتصال رویدادها
         */
        bindEvents() {
            this.bindDragAndDrop();
            this.bindToolEvents();
            this.bindPreviewEvents();
            this.bindSettingsEvents();
            this.bindActionEvents();
        }

        /**
         * اتصال رویدادهای کشیدن و رها کردن
         */
        bindDragAndDrop() {
            // رویدادهای ابزارها
            this.toolsList.addEventListener('dragstart', (e) => {
                if (e.target.classList.contains('pcfb-tool-item')) {
                    e.dataTransfer.setData('text/plain', e.target.dataset.type);
                    e.dataTransfer.effectAllowed = 'copy';
                    e.target.classList.add('dragging');
                }
            });

            this.toolsList.addEventListener('dragend', (e) => {
                if (e.target.classList.contains('pcfb-tool-item')) {
                    e.target.classList.remove('dragging');
                }
            });

            // رویدادهای ناحیه پیش‌نمایش
            this.previewArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                this.previewArea.classList.add('drag-over');
            });

            this.previewArea.addEventListener('dragleave', (e) => {
                if (!this.previewArea.contains(e.relatedTarget)) {
                    this.previewArea.classList.remove('drag-over');
                }
            });

            this.previewArea.addEventListener('drop', (e) => {
                e.preventDefault();
                this.previewArea.classList.remove('drag-over');
                
                const fieldType = e.dataTransfer.getData('text/plain');
                this.addField(fieldType);
            });
        }

        /**
         * اتصال رویدادهای ابزارها
         */
        bindToolEvents() {
            // کلیک روی ابزار برای افزودن سریع
            this.toolsList.addEventListener('click', (e) => {
                const toolItem = e.target.closest('.pcfb-tool-item');
                if (toolItem) {
                    this.addField(toolItem.dataset.type);
                }
            });
        }

        /**
         * اتصال رویدادهای پیش‌نمایش
         */
        bindPreviewEvents() {
            this.previewArea.addEventListener('click', (e) => {
                const fieldElement = e.target.closest('.pcfb-field');
                if (fieldElement) {
                    this.selectField(fieldElement.dataset.fieldId);
                }
            });
        }

        /**
         * اتصال رویدادهای تنظیمات
         */
        bindSettingsEvents() {
            // رویدادهای پنل تنظیمات
            document.getElementById('pcfb-toggle-code').addEventListener('click', () => {
                this.toggleCodePanel();
            });

            document.getElementById('pcfb-copy-json').addEventListener('click', () => {
                this.copyJSONToClipboard();
            });
        }

        /**
         * اتصال رویدادهای اقدامات
         */
        bindActionEvents() {
            document.getElementById('pcfb-save-form').addEventListener('click', () => {
                this.saveForm();
            });

            document.getElementById('pcfb-preview-form').addEventListener('click', () => {
                this.previewForm();
            });

            document.getElementById('pcfb-clear-form').addEventListener('click', () => {
                this.clearForm();
            });

            document.getElementById('pcfb-form-name').addEventListener('input', () => {
                this.updateFormJSON();
            });
        }

        /**
         * افزودن فیلد جدید
         */
        addField(type) {
            const fieldId = this.generateFieldId();
            const defaultLabel = this.getDefaultLabel(type);
            
            const newField = {
                id: fieldId,
                type: type,
                label: defaultLabel,
                name: this.generateFieldName(defaultLabel),
                required: false,
                placeholder: '',
                description: '',
                options: type === 'select' || type === 'radio' || type === 'checkbox' ? ['گزینه ۱', 'گزینه ۲'] : [],
                validation: this.getDefaultValidation(type),
                cssClass: ''
            };

            this.fields.push(newField);
            this.renderField(newField);
            this.updateFormJSON();
            this.updateFieldCount();
            this.selectField(fieldId);
        }

        /**
         * رندر یک فیلد در پیش‌نمایش
         */
        renderField(field) {
            const fieldElement = document.createElement('div');
            fieldElement.className = `pcfb-field pcfb-field-${field.type}`;
            fieldElement.dataset.fieldId = field.id;
            fieldElement.innerHTML = this.generateFieldHTML(field);

            // رویداد حذف فیلد
            fieldElement.querySelector('.pcfb-field-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeField(field.id);
            });

            // رویداد تنظیمات فیلد
            fieldElement.querySelector('.pcfb-field-settings').addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectField(field.id);
            });

            this.previewArea.appendChild(fieldElement);
        }

        /**
         * تولید HTML فیلد
         */
        generateFieldHTML(field) {
            return `
                <div class="pcfb-field-header">
                    <div class="pcfb-field-info">
                        <span class="field-type-icon">${this.getFieldIcon(field.type)}</span>
                        <span class="field-label">${field.label}</span>
                        ${field.required ? '<span class="field-required-badge">اجباری</span>' : ''}
                    </div>
                    <div class="pcfb-field-actions">
                        <button type="button" class="pcfb-field-settings" title="تنظیمات">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </button>
                        <button type="button" class="pcfb-field-remove" title="حذف">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <div class="pcfb-field-preview">
                    ${this.generateFieldPreview(field)}
                </div>
            `;
        }

        /**
         * تولید پیش‌نمایش فیلد
         */
        generateFieldPreview(field) {
            switch (field.type) {
                case 'textarea':
                    return `<textarea placeholder="${field.placeholder}" ${field.required ? 'required' : ''}></textarea>`;
                
                case 'select':
                    return `
                        <select ${field.required ? 'required' : ''}>
                            <option value="">${field.placeholder || 'انتخاب کنید'}</option>
                            ${field.options.map(opt => `<option>${opt}</option>`).join('')}
                        </select>
                    `;
                
                case 'radio':
                    return field.options.map(opt => `
                        <label class="pcfb-radio-option">
                            <input type="radio" name="radio_${field.id}" ${field.required ? 'required' : ''}>
                            ${opt}
                        </label>
                    `).join('');
                
                case 'checkbox':
                    return field.options.map(opt => `
                        <label class="pcfb-checkbox-option">
                            <input type="checkbox" value="${opt}">
                            ${opt}
                        </label>
                    `).join('');
                
                default:
                    return `<input type="${field.type}" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}>`;
            }
        }

        /**
         * انتخاب یک فیلد برای ویرایش
         */
        selectField(fieldId) {
            // حذف انتخاب قبلی
            this.previewArea.querySelectorAll('.pcfb-field').forEach(field => {
                field.classList.remove('selected');
            });

            // انتخاب فیلد جدید
            const fieldElement = this.previewArea.querySelector(`[data-field-id="${fieldId}"]`);
            if (fieldElement) {
                fieldElement.classList.add('selected');
            }

            // نمایش تنظیمات فیلد
            this.showFieldSettings(fieldId);
        }

        /**
         * نمایش تنظیمات فیلد انتخاب شده
         */
        showFieldSettings(fieldId) {
            const field = this.fields.find(f => f.id === fieldId);
            if (!field) {
                this.settingsPanel.innerHTML = '<div class="pcfb-no-selection"><p>فیلد انتخاب شده یافت نشد</p></div>';
                return;
            }

            this.settingsPanel.innerHTML = this.generateFieldSettings(field);
            this.bindFieldSettingsEvents(field);
        }

        /**
         * تولید فرم تنظیمات فیلد
         */
        generateFieldSettings(field) {
            return `
                <div class="pcfb-field-settings-form" data-field-id="${field.id}">
                    <div class="pcfb-setting-group">
                        <label for="field-label-${field.id}">عنوان فیلد</label>
                        <input type="text" id="field-label-${field.id}" value="${field.label}" class="pcfb-setting-input">
                    </div>

                    <div class="pcfb-setting-group">
                        <label for="field-name-${field.id}">نام فیلد (Name)</label>
                        <input type="text" id="field-name-${field.id}" value="${field.name}" class="pcfb-setting-input">
                    </div>

                    <div class="pcfb-setting-group">
                        <label for="field-placeholder-${field.id}">متن راهنما (Placeholder)</label>
                        <input type="text" id="field-placeholder-${field.id}" value="${field.placeholder}" class="pcfb-setting-input">
                    </div>

                    <div class="pcfb-setting-group">
                        <label class="pcfb-setting-checkbox">
                            <input type="checkbox" id="field-required-${field.id}" ${field.required ? 'checked' : ''}>
                            <span class="checkbox-label">فیلد اجباری</span>
                        </label>
                    </div>

                    ${this.generateTypeSpecificSettings(field)}

                    <div class="pcfb-setting-group">
                        <label for="field-description-${field.id}">توضیحات</label>
                        <textarea id="field-description-${field.id}" class="pcfb-setting-textarea">${field.description}</textarea>
                    </div>

                    <div class="pcfb-setting-group">
                        <label for="field-class-${field.id}">کلاس CSS</label>
                        <input type="text" id="field-class-${field.id}" value="${field.cssClass}" class="pcfb-setting-input" placeholder="class1 class2">
                    </div>
                </div>
            `;
        }

        /**
         * تولید تنظیمات خاص هر نوع فیلد
         */
        generateTypeSpecificSettings(field) {
            if (['select', 'radio', 'checkbox'].includes(field.type)) {
                return `
                    <div class="pcfb-setting-group">
                        <label>گزینه‌ها</label>
                        <div class="pcfb-options-container" id="options-container-${field.id}">
                            ${field.options.map((opt, index) => `
                                <div class="pcfb-option-item">
                                    <input type="text" value="${opt}" class="pcfb-option-input" data-index="${index}">
                                    <button type="button" class="pcfb-option-remove" data-index="${index}">×</button>
                                </div>
                            `).join('')}
                        </div>
                        <button type="button" class="pcfb-add-option button button-small">+ افزودن گزینه</button>
                    </div>
                `;
            }

            if (field.type === 'number') {
                return `
                    <div class="pcfb-setting-row">
                        <div class="pcfb-setting-group">
                            <label for="field-min-${field.id}">حداقل مقدار</label>
                            <input type="number" id="field-min-${field.id}" value="${field.validation.min || ''}" class="pcfb-setting-input">
                        </div>
                        <div class="pcfb-setting-group">
                            <label for="field-max-${field.id}">حداکثر مقدار</label>
                            <input type="number" id="field-max-${field.id}" value="${field.validation.max || ''}" class="pcfb-setting-input">
                        </div>
                    </div>
                `;
            }

            return '';
        }

        /**
         * اتصال رویدادهای تنظیمات فیلد
         */
        bindFieldSettingsEvents(field) {
            const form = this.settingsPanel.querySelector('.pcfb-field-settings-form');

            // رویدادهای عمومی
            form.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('input', (e) => {
                    this.updateFieldProperty(field.id, e.target.id.replace(`field-${field.id}-`, ''), e.target.value);
                });
            });

            form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    this.updateFieldProperty(field.id, e.target.id.replace(`field-${field.id}-`, ''), e.target.checked);
                });
            });

            // رویدادهای گزینه‌ها
            if (['select', 'radio', 'checkbox'].includes(field.type)) {
                this.bindOptionsEvents(field);
            }
        }

        /**
         * اتصال رویدادهای مدیریت گزینه‌ها
         */
        bindOptionsEvents(field) {
            const container = document.getElementById(`options-container-${field.id}`);

            // حذف گزینه
            container.querySelectorAll('.pcfb-option-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    this.removeFieldOption(field.id, index);
                });
            });

            // تغییر گزینه
            container.querySelectorAll('.pcfb-option-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    this.updateFieldOption(field.id, index, e.target.value);
                });
            });

            // افزودن گزینه جدید
            this.settingsPanel.querySelector('.pcfb-add-option').addEventListener('click', () => {
                this.addFieldOption(field.id, 'گزینه جدید');
            });
        }

        /**
         * به‌روزرسانی خصوصیت فیلد
         */
        updateFieldProperty(fieldId, property, value) {
            const fieldIndex = this.fields.findIndex(f => f.id === fieldId);
            if (fieldIndex === -1) return;

            this.fields[fieldIndex][property] = value;
            this.updateFieldDisplay(fieldId);
            this.updateFormJSON();
        }

        /**
         * به‌روزرسانی نمایش فیلد
         */
        updateFieldDisplay(fieldId) {
            const field = this.fields.find(f => f.id === fieldId);
            if (!field) return;

            const fieldElement = this.previewArea.querySelector(`[data-field-id="${fieldId}"]`);
            if (fieldElement) {
                fieldElement.outerHTML = this.generateFieldHTML(field);
                // باید رویدادها را دوباره اتصال دهیم
                this.bindFieldEvents(fieldElement);
            }
        }

        /**
         * حذف فیلد
         */
        removeField(fieldId) {
            if (!confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                return;
            }

            this.fields = this.fields.filter(f => f.id !== fieldId);
            
            const fieldElement = this.previewArea.querySelector(`[data-field-id="${fieldId}"]`);
            if (fieldElement) {
                fieldElement.remove();
            }

            this.updateFormJSON();
            this.updateFieldCount();
            this.selectedField = null;
            this.showFieldSettings(null);
        }

        /**
         * به‌روزرسانی کد JSON فرم
         */
        updateFormJSON() {
            const formData = {
                name: document.getElementById('pcfb-form-name').value,
                description: '',
                fields: this.fields,
                settings: {
                    submit_text: 'ارسال فرم',
                    success_message: 'فرم با موفقیت ارسال شد!'
                }
            };

            const jsonString = JSON.stringify(formData, null, 2);
            this.jsonOutput.textContent = jsonString;

            // به‌روزرسانی فیلد hidden برای ذخیره
            const jsonInput = document.getElementById('pcfb-form-json');
            if (jsonInput) {
                jsonInput.value = jsonString;
            }
        }

        /**
         * ذخیره فرم
         */
        saveForm() {
            const formName = document.getElementById('pcfb-form-name').value.trim();
            if (!formName) {
                alert('لطفاً نام فرم را وارد کنید');
                return;
            }

            if (this.fields.length === 0) {
                alert('فرم باید حداقل یک فیلد داشته باشد');
                return;
            }

            // نمایش وضعیت ذخیره‌سازی
            const saveButton = document.getElementById('pcfb-save-form');
            const originalText = saveButton.textContent;
            saveButton.textContent = 'در حال ذخیره...';
            saveButton.disabled = true;

            // ارسال درخواست AJAX
            this.sendSaveRequest(formName)
                .then(response => {
                    alert('فرم با موفقیت ذخیره شد');
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    }
                })
                .catch(error => {
                    alert('خطا در ذخیره فرم: ' + error);
                })
                .finally(() => {
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                });
        }

        /**
         * ارسال درخواست ذخیره فرم
         */
        async sendSaveRequest(formName) {
            const formData = new FormData();
            formData.append('action', 'pcfb_save_form');
            formData.append('nonce', pcfb_admin.nonce);
            formData.append('form_name', formName);
            formData.append('form_json', JSON.stringify({
                fields: this.fields,
                settings: {}
            }));

            const response = await fetch(pcfb_admin.ajax_url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('خطای شبکه');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.data);
            }

            return data.data;
        }

        /**
         * توابع utility
         */
        generateFieldId() {
            return 'field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        generateFieldName(label) {
            return label.replace(/[^a-z0-9_]/gi, '_').toLowerCase() + '_' + this.fieldCounter++;
        }

        getDefaultLabel(type) {
            const labels = {
                text: 'متن تک خطی',
                textarea: 'متن چند خطی',
                email: 'آدرس ایمیل',
                number: 'عدد',
                tel: 'شماره تلفن',
                date: 'تاریخ',
                url: 'آدرس وب سایت',
                checkbox: 'گزینه‌های چندتایی',
                radio: 'انتخاب یک گزینه',
                select: 'لیست انتخابی',
                file: 'آپلود فایل'
            };
            return labels[type] || type;
        }

        getFieldIcon(type) {
            const icons = {
                text: '📝',
                textarea: '📄',
                email: '📧',
                number: '🔢',
                tel: '📱',
                date: '📅',
                url: '🔗',
                checkbox: '✅',
                radio: '🔘',
                select: '⬇️',
                file: '📎'
            };
            return icons[type] || '📋';
        }

        getDefaultValidation(type) {
            const validations = {
                email: { pattern: '[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$' },
                url: { pattern: 'https?://.+' },
                number: { min: '', max: '' }
            };
            return validations[type] || {};
        }

        updateFieldCount() {
            const countElement = document.querySelector('.pcfb-field-count');
            if (countElement) {
                countElement.textContent = `(${this.fields.length} فیلد)`;
            }
        }

        toggleCodePanel() {
            this.codeContainer.style.display = this.codeContainer.style.display === 'none' ? 'block' : 'none';
        }

        copyJSONToClipboard() {
            navigator.clipboard.writeText(this.jsonOutput.textContent)
                .then(() => alert('کد JSON کپی شد!'))
                .catch(() => alert('خطا در کپی کردن کد'));
        }

        previewForm() {
            alert('این ویژگی به زودی فعال خواهد شد');
        }

        clearForm() {
            if (confirm('آیا از پاک کردن تمام فیلدها مطمئن هستید؟ این عمل قابل بازگشت نیست.')) {
                this.fields = [];
                this.previewArea.innerHTML = '<p style="color:#666; text-align:center; padding:40px;">ابزارهای مورد نظر را از پنل سمت چپ به اینجا بکشید و رها کنید.</p>';
                this.updateFormJSON();
                this.updateFieldCount();
                this.selectedField = null;
                this.showFieldSettings(null);
            }
        }

        loadExistingForm() {
            // بارگذاری فرم موجود از دیتابیس (اگر در حالت ویرایش باشیم)
            if (typeof pcfb_existing_form !== 'undefined' && pcfb_existing_form) {
                document.getElementById('pcfb-form-name').value = pcfb_existing_form.form_name;
                this.fields = pcfb_existing_form.fields || [];
                
                this.fields.forEach(field => {
                    this.renderField(field);
                });
                
                this.updateFormJSON();
                this.updateFieldCount();
            }
        }
    }

    // راه‌اندازی فرم‌ساز
    new PCFBFormBuilder();
});