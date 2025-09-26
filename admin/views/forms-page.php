<?php
/**
 * صفحه ساخت و ویرایش فرم‌ها
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی کاربر
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
}

// دریافت اطلاعات فرم
$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
$form = $form_id > 0 ? PCFB_DB::get_form($form_id) : null;
$form_data = $form ? json_decode($form->form_json, true) : [];
$page_title = $form ? 'ویرایش فرم: ' . esc_html($form->form_name) : 'ساخت فرم جدید';

// پردازش ذخیره فرم
if (isset($_POST['pcfb_save_form']) && check_admin_referer('pcfb_save_form', 'pcfb_form_nonce')) {
    $form_name = sanitize_text_field($_POST['form_name'] ?? '');
    $form_json = wp_unslash($_POST['form_json'] ?? '');
    
    if (empty($form_name)) {
        pcfb_admin_notice('نام فرم نمی‌تواند خالی باشد.', 'error');
    } else {
        $result = PCFB_DB::save_form([
            'form_id' => $form_id,
            'form_name' => $form_name,
            'form_json' => $form_json,
            'status' => 'active'
        ]);
        
        if ($result) {
            $message = $form_id ? 'فرم با موفقیت به‌روزرسانی شد.' : 'فرم جدید با موفقیت ایجاد شد.';
            pcfb_admin_notice($message, 'success');
            $form_id = $form_id ?: $result;
            wp_redirect(admin_url('admin.php?page=pcfb-settings&tab=forms&action=edit&form_id=' . $form_id));
            exit;
        } else {
            pcfb_admin_notice('خطا در ذخیره فرم.', 'error');
        }
    }
}

// تابع نمایش اعلان
function pcfb_admin_notice($message, $type = 'success') {
    add_action('admin_notices', function() use ($message, $type) {
        $class = $type === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    });
}
?>

<div class="wrap pcfb-form-builder">
    <h1 class="pcfb-page-title">
        <span class="dashicons dashicons-edit"></span>
        <?php echo esc_html($page_title); ?>
    </h1>

    <div class="pcfb-builder-container">
        <!-- نوار ابزار -->
        <div class="pcfb-builder-header">
            <form method="post" class="pcfb-form-info">
                <?php wp_nonce_field('pcfb_save_form', 'pcfb_form_nonce'); ?>
                <input type="hidden" name="form_json" id="pcfb-form-json" value="<?php echo esc_attr(json_encode($form_data)); ?>">
                
                <div class="pcfb-form-name">
                    <label for="form_name">نام فرم:</label>
                    <input type="text" id="form_name" name="form_name" 
                           value="<?php echo esc_attr($form ? $form->form_name : ''); ?>" 
                           placeholder="مثال: فرم تماس با ما" required>
                </div>
                
                <div class="pcfb-form-actions">
                    <button type="submit" name="pcfb_save_form" class="button button-primary button-large">
                        <span class="dashicons dashicons-yes"></span>
                        ذخیره فرم
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms'); ?>" 
                       class="button button-large">
                        <span class="dashicons dashicons-no"></span>
                        انصراف
                    </a>
                    
                    <?php if ($form_id): ?>
                    <a href="<?php echo admin_url('admin.php?page=pcfb-settings&tab=forms&action=create'); ?>" 
                       class="button button-large">
                        <span class="dashicons dashicons-plus"></span>
                        فرم جدید
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- رابط فرم‌ساز -->
        <div class="pcfb-builder-interface">
            <!-- پنل ابزارها -->
            <div class="pcfb-tools-panel">
                <h3 class="pcfb-panel-title">
                    <span class="dashicons dashicons-admin-tools"></span>
                    فیلدهای فرم
                </h3>
                
                <div class="pcfb-tools-list">
                    <?php
                    $field_types = [
                        'text' => ['icon' => '📝', 'label' => 'متن تک خطی'],
                        'textarea' => ['icon' => '📄', 'label' => 'متن چند خطی'],
                        'email' => ['icon' => '📧', 'label' => 'ایمیل'],
                        'number' => ['icon' => '🔢', 'label' => 'عدد'],
                        'tel' => ['icon' => '📱', 'label' => 'تلفن'],
                        'date' => ['icon' => '📅', 'label' => 'تاریخ'],
                        'url' => ['icon' => '🔗', 'label' => 'آدرس وب'],
                        'checkbox' => ['icon' => '✅', 'label' => 'چک‌باکس'],
                        'radio' => ['icon' => '🔘', 'label' => 'دکمه رادیویی'],
                        'select' => ['icon' => '⬇️', 'label' => 'لیست انتخابی'],
                        'file' => ['icon' => '📎', 'label' => 'آپلود فایل']
                    ];
                    
                    foreach ($field_types as $type => $data): ?>
                        <div class="pcfb-tool-item" draggable="true" data-type="<?php echo esc_attr($type); ?>">
                            <span class="tool-icon"><?php echo $data['icon']; ?></span>
                            <span class="tool-label"><?php echo esc_html($data['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ناحیه پیش‌نمایش -->
            <div class="pcfb-preview-panel">
                <h3 class="pcfb-panel-title">
                    <span class="dashicons dashicons-visibility"></span>
                    پیش‌نمایش فرم
                    <span class="pcfb-field-count">(0 فیلد)</span>
                </h3>
                
                <div id="pcfb-preview-area" class="pcfb-preview-area">
                    <?php if (empty($form_data)): ?>
                        <div class="pcfb-empty-state">
                            <div class="empty-icon">📋</div>
                            <p>فیلدهای مورد نظر را از پنل سمت چپ به اینجا بکشید و رها کنید.</p>
                            <small>می‌توانید فیلدها را با کشیدن جابه‌جا کنید و با کلیک روی آیکن تنظیمات، خصوصیات آن‌ها را تغییر دهید.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($form_data as $index => $field): ?>
                            <div class="pcfb-field" data-index="<?php echo $index; ?>" data-type="<?php echo esc_attr($field['type']); ?>">
                                <!-- فیلدهای موجود از دیتابیس -->
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- پنل تنظیمات -->
            <div class="pcfb-settings-panel">
                <h3 class="pcfb-panel-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    تنظیمات فیلد
                </h3>
                
                <div id="pcfb-field-settings" class="pcfb-field-settings">
                    <div class="pcfb-no-selection">
                        <p>لطفاً یک فیلد را انتخاب کنید</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- پنل کد فرم -->
        <div class="pcfb-code-panel">
            <h3 class="pcfb-panel-title">
                <span class="dashicons dashicons-editor-code"></span>
                کد فرم (JSON)
                <button type="button" id="pcfb-toggle-code" class="button button-small">نمایش/مخفی</button>
            </h3>
            
            <div id="pcfb-code-container" class="pcfb-code-container" style="display: none;">
                <pre id="pcfb-json-output"><?php echo esc_html(json_encode($form_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                <button type="button" id="pcfb-copy-json" class="button button-small">کپی کد</button>
            </div>
        </div>
    </div>
</div>

<!-- تمپلیت‌های فیلدها -->
<script type="text/template" id="pcfb-field-template">
    <div class="pcfb-field-header">
        <span class="field-type-icon"></span>
        <span class="field-label"></span>
        <div class="field-actions">
            <button type="button" class="pcfb-field-settings" title="تنظیمات">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
            <button type="button" class="pcfb-field-remove" title="حذف">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
    <div class="pcfb-field-content"></div>
</script>

<style>
.pcfb-form-builder {
    max-width: 1400px;
}

.pcfb-builder-header {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
}

.pcfb-form-info {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.pcfb-form-name {
    flex: 1;
    min-width: 300px;
}

.pcfb-form-name label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.pcfb-form-name input {
    width: 100%;
    padding: 8px 12px;
}

.pcfb-form-actions {
    display: flex;
    gap: 10px;
}

.pcfb-builder-interface {
    display: grid;
    grid-template-columns: 250px 1fr 300px;
    gap: 20px;
    margin-bottom: 20px;
}

.pcfb-tools-panel,
.pcfb-preview-panel,
.pcfb-settings-panel {
    background: white;
    border-radius: 8px;
    border: 1px solid #ccd0d4;
    overflow: hidden;
}

.pcfb-panel-title {
    background: #f8f9fa;
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pcfb-tools-list {
    padding: 15px;
    display: grid;
    gap: 8px;
}

.pcfb-tool-item {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: grab;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.pcfb-tool-item:hover {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.pcfb-tool-item:active {
    cursor: grabbing;
}

.pcfb-preview-area {
    min-height: 500px;
    padding: 20px;
}

.pcfb-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.pcfb-field {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    padding: 15px;
    position: relative;
    cursor: move;
}

.pcfb-field:hover {
    border-color: #0073aa;
}

.pcfb-field-header {
    display: flex;
    align-items: center;
    justify-content: between;
    margin-bottom: 10px;
}

.field-type-icon {
    margin-right: 10px;
}

.field-label {
    flex: 1;
    font-weight: 600;
}

.field-actions {
    display: flex;
    gap: 5px;
}

.pcfb-field-settings,
.pcfb-field-remove {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    border-radius: 3px;
}

.pcfb-field-settings:hover {
    background: #0073aa;
    color: white;
}

.pcfb-field-remove:hover {
    background: #dc3232;
    color: white;
}

.pcfb-field-settings.active {
    background: #0073aa;
    color: white;
}

.pcfb-field-content {
    margin-top: 10px;
}

.pcfb-field-settings {
    padding: 15px;
}

.pcfb-no-selection {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.pcfb-code-panel {
    background: white;
    border-radius: 8px;
    border: 1px solid #ccd0d4;
    margin-bottom: 20px;
}

.pcfb-code-container {
    padding: 15px;
    background: #1d2327;
    color: #f0f0f1;
}

#pcfb-json-output {
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    max-height: 200px;
    overflow: auto;
}

@media (max-width: 1200px) {
    .pcfb-builder-interface {
        grid-template-columns: 1fr;
    }
    
    .pcfb-tools-panel {
        order: 1;
    }
    
    .pcfb-preview-panel {
        order: 2;
    }
    
    .pcfb-settings-panel {
        order: 3;
    }
}

@media (max-width: 768px) {
    .pcfb-form-info {
        flex-direction: column;
        align-items: stretch;
    }
    
    .pcfb-form-name {
        min-width: auto;
    }
    
    .pcfb-form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pcfbFormBuilder = {
        fields: <?php echo json_encode($form_data); ?>,
        selectedField: null,
        
        init() {
            this.initDragAndDrop();
            this.initEventListeners();
            this.updateFieldCount();
            this.renderExistingFields();
        },
        
        initDragAndDrop() {
            // کشیدن ابزارها
            const toolItems = document.querySelectorAll('.pcfb-tool-item');
            toolItems.forEach(tool => {
                tool.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('text/plain', tool.dataset.type);
                    e.dataTransfer.effectAllowed = 'copy';
                });
            });
            
            // رها کردن در ناحیه پیش‌نمایش
            const previewArea = document.getElementById('pcfb-preview-area');
            previewArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                previewArea.classList.add('drag-over');
            });
            
            previewArea.addEventListener('dragleave', (e) => {
                if (!previewArea.contains(e.relatedTarget)) {
                    previewArea.classList.remove('drag-over');
                }
            });
            
            previewArea.addEventListener('drop', (e) => {
                e.preventDefault();
                previewArea.classList.remove('drag-over');
                
                const fieldType = e.dataTransfer.getData('text/plain');
                this.addField(fieldType);
            });
        },
        
        initEventListeners() {
            // نمایش/مخفی کردن کد JSON
            document.getElementById('pcfb-toggle-code').addEventListener('click', () => {
                const container = document.getElementById('pcfb-code-container');
                container.style.display = container.style.display === 'none' ? 'block' : 'none';
            });
            
            // کپی کردن کد JSON
            document.getElementById('pcfb-copy-json').addEventListener('click', () => {
                const jsonOutput = document.getElementById('pcfb-json-output');
                navigator.clipboard.writeText(jsonOutput.textContent).then(() => {
                    alert('کد JSON کپی شد!');
                });
            });
            
            // به‌روزرسانی خودکار JSON هنگام تغییرات
            document.getElementById('form_name').addEventListener('input', () => {
                this.updateFormJSON();
            });
        },
        
        addField(type) {
            const fieldId = Date.now();
            const newField = {
                id: fieldId,
                type: type,
                label: this.getDefaultLabel(type),
                required: false,
                placeholder: '',
                options: type === 'select' || type === 'radio' || type === 'checkbox' ? ['گزینه ۱', 'گزینه ۲'] : []
            };
            
            this.fields.push(newField);
            this.renderField(newField);
            this.updateFormJSON();
            this.updateFieldCount();
        },
        
        renderExistingFields() {
            this.fields.forEach(field => {
                this.renderField(field);
            });
        },
        
        renderField(field) {
            const previewArea = document.getElementById('pcfb-preview-area');
            const fieldElement = document.createElement('div');
            fieldElement.className = 'pcfb-field';
            fieldElement.dataset.fieldId = field.id;
            fieldElement.innerHTML = this.generateFieldHTML(field);
            
            // حذف فیلد
            fieldElement.querySelector('.pcfb-field-remove').addEventListener('click', () => {
                this.removeField(field.id);
            });
            
            // تنظیمات فیلد
            fieldElement.querySelector('.pcfb-field-settings').addEventListener('click', () => {
                this.showFieldSettings(field);
            });
            
            previewArea.appendChild(fieldElement);
        },
        
        generateFieldHTML(field) {
            return `
                <div class="pcfb-field-header">
                    <span class="field-type-icon">${this.getFieldIcon(field.type)}</span>
                    <span class="field-label">${field.label}</span>
                    <div class="field-actions">
                        <button type="button" class="pcfb-field-settings" title="تنظیمات">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </button>
                        <button type="button" class="pcfb-field-remove" title="حذف">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <div class="pcfb-field-content">
                    ${this.generateFieldInput(field)}
                </div>
            `;
        },
        
        generateFieldInput(field) {
            switch (field.type) {
                case 'textarea':
                    return `<textarea placeholder="${field.placeholder}" ${field.required ? 'required' : ''}></textarea>`;
                case 'select':
                    return `<select ${field.required ? 'required' : ''}>
                        ${field.options.map(opt => `<option>${opt}</option>`).join('')}
                    </select>`;
                case 'radio':
                    return field.options.map(opt => `
                        <label style="display: block; margin: 5px 0;">
                            <input type="radio" name="radio_${field.id}" ${field.required ? 'required' : ''}>
                            ${opt}
                        </label>
                    `).join('');
                case 'checkbox':
                    return field.options.map(opt => `
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" value="${opt}">
                            ${opt}
                        </label>
                    `).join('');
                default:
                    return `<input type="${field.type}" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}>`;
            }
        },
        
        removeField(fieldId) {
            if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                this.fields = this.fields.filter(f => f.id !== fieldId);
                document.querySelector(`[data-field-id="${fieldId}"]`).remove();
                this.updateFormJSON();
                this.updateFieldCount();
            }
        },
        
        showFieldSettings(field) {
            // پیاده‌سازی پنل تنظیمات فیلد
            console.log('تنظیمات فیلد:', field);
            // این بخش نیاز به پیاده‌سازی کامل دارد
        },
        
        updateFormJSON() {
            const formData = {
                name: document.getElementById('form_name').value,
                fields: this.fields
            };
            
            document.getElementById('pcfb-form-json').value = JSON.stringify(formData.fields);
            document.getElementById('pcfb-json-output').textContent = JSON.stringify(formData, null, 2);
        },
        
        updateFieldCount() {
            const countElement = document.querySelector('.pcfb-field-count');
            countElement.textContent = `(${this.fields.length} فیلد)`;
        },
        
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
        },
        
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
    };
    
    // راه‌اندازی فرم‌ساز
    pcfbFormBuilder.init();
});
</script>