/**
 * Psych Club Form Builder - Validator
 * سیستم اعتبارسنجی پیشرفته برای فرم‌ها
 */

class PCFBValidator {
    constructor() {
        this.rules = {
            required: this.validateRequired,
            email: this.validateEmail,
            number: this.validateNumber,
            tel: this.validateTel,
            url: this.validateUrl,
            date: this.validateDate,
            min: this.validateMin,
            max: this.validateMax,
            minLength: this.validateMinLength,
            maxLength: this.validateMaxLength,
            pattern: this.validatePattern
        };
    }

    validate(field, value, rules) {
        const errors = [];
        
        for (const [ruleName, ruleValue] of Object.entries(rules)) {
            if (this.rules[ruleName]) {
                const result = this.rules[ruleName](value, ruleValue, field);
                if (result !== true) {
                    errors.push(result);
                }
            }
        }
        
        return errors;
    }

    validateRequired(value, ruleValue, field) {
        if (ruleValue && (!value || value.toString().trim() === '')) {
            return 'این فیلد اجباری است';
        }
        return true;
    }

    validateEmail(value, ruleValue, field) {
        if (!value) return true;
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            return 'لطفاً یک ایمیل معتبر وارد کنید';
        }
        return true;
    }

    validateNumber(value, ruleValue, field) {
        if (!value) return true;
        
        if (isNaN(parseFloat(value)) || !isFinite(value)) {
            return 'لطفاً یک عدد معتبر وارد کنید';
        }
        return true;
    }

    // سایر متدهای اعتبارسنجی...
}

window.PCFBValidator = PCFBValidator;