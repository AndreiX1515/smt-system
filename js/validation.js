//     

//   
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return {
        isValid: emailRegex.test(email),
        message: emailRegex.test(email) ? '' : '   .'
    };
}

//   
function validatePassword(password) {
    const minLength = 6;
    const hasNumber = /\d/.test(password);
    const hasLetter = /[a-zA-Z]/.test(password);
    
    if (password.length < minLength) {
        return {
            isValid: false,
            message: `  ${minLength}  .`
        };
    }
    
    if (!hasNumber || !hasLetter) {
        return {
            isValid: false,
            message: '    .'
        };
    }
    
    return {
        isValid: true,
        message: ''
    };
}

//   
function validatePhone(phone) {
    const phoneRegex = /^[0-9\-\+\s\(\)]+$/;
    const minLength = 8;
    
    if (phone.length < minLength) {
        return {
            isValid: false,
            message: '  .'
        };
    }
    
    if (!phoneRegex.test(phone)) {
        return {
            isValid: false,
            message: '   .'
        };
    }
    
    return {
        isValid: true,
        message: ''
    };
}

//   
function validateName(name) {
    const minLength = 1;
    const maxLength = 50;
    
    if (name.length < minLength) {
        return {
            isValid: false,
            message: ' .'
        };
    }
    
    if (name.length > maxLength) {
        return {
            isValid: false,
            message: ` ${maxLength}  .`
        };
    }
    
    return {
        isValid: true,
        message: ''
    };
}

//   
function validateRequired(value, fieldName = '') {
    const isValid = value && value.toString().trim().length > 0;
    return {
        isValid: isValid,
        message: isValid ? '' : `${fieldName} .`
    };
}

//   
function validatePasswordConfirm(password, confirmPassword) {
    const isValid = password === confirmPassword;
    return {
        isValid: isValid,
        message: isValid ? '' : '  .'
    };
}

//     
function setupRealTimeValidation(formSelector, validators = {}) {
    const form = document.querySelector(formSelector);
    if (!form) return;
    
    //      
    Object.keys(validators).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"], #${fieldName}`);
        if (!field) return;
        
        const validator = validators[fieldName];
        
        //    
        field.addEventListener('blur', () => validateField(field, validator));
        field.addEventListener('input', () => {
            //    (  )
            clearFieldError(field);
        });
    });
}

//    
function validateField(field, validator) {
    const value = field.value.trim();
    const result = validator(value);
    
    if (result.isValid) {
        setFieldSuccess(field);
    } else {
        setFieldError(field, result.message);
    }
    
    return result.isValid;
}

//   
function setFieldError(field, message) {
    field.classList.add('error');
    field.classList.remove('success');
    
    //   
    let errorElement = field.parentNode.querySelector('.error-message');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        field.parentNode.appendChild(errorElement);
    }
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

//   
function setFieldSuccess(field) {
    field.classList.add('success');
    field.classList.remove('error');
    
    //   
    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.style.display = 'none';
    }
}

//    
function clearFieldError(field) {
    field.classList.remove('error', 'success');
    
    //   
    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.style.display = 'none';
    }
}

//    
function validateForm(formSelector, validators = {}) {
    const form = document.querySelector(formSelector);
    if (!form) return false;
    
    let isFormValid = true;
    const errors = {};
    
    //   
    Object.keys(validators).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"], #${fieldName}`);
        if (!field) return;
        
        const validator = validators[fieldName];
        const isValid = validateField(field, validator);
        
        if (!isValid) {
            isFormValid = false;
            errors[fieldName] = field.parentNode.querySelector('.error-message')?.textContent || '  .';
        }
    });
    
    return {
        isValid: isFormValid,
        errors: errors
    };
}

//     
function validateBeforeSubmit(formSelector, validators = {}) {
    const result = validateForm(formSelector, validators);
    
    if (!result.isValid) {
        //     
        const firstErrorField = document.querySelector(`${formSelector} .error`);
        if (firstErrorField) {
            firstErrorField.focus();
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        //   
        const errorMessages = result.errors ? Object.values(result.errors).join('\n') : '   .';
        alert(errorMessages);
    }
    
    return result.isValid;
}

//     
const commonValidators = {
    email: validateEmail,
    password: validatePassword,
    phone: validatePhone,
    name: validateName,
    required: validateRequired
};

//     
function setupLoginValidation() {
    const validators = {
        email: (value) => {
            if (!value) return validateRequired(value, '');
            return validateEmail(value);
        },
        password: (value) => validateRequired(value, '')
    };
    
    setupRealTimeValidation('form, .login-form', validators);
    
    //    login.js   
    // const submitBtn = document.querySelector('#loginBtn, .login-btn');
    // if (submitBtn) {
    //     submitBtn.addEventListener('click', (e) => {
    //         if (!validateBeforeSubmit('form, .login-form', validators)) {
    //             e.preventDefault();
    //         }
    //     });
    // }
}

//     
function setupRegistrationValidation() {
    const validators = {
        name: (value) => {
            if (!value) return validateRequired(value, '');
            return validateName(value);
        },
        email: (value) => {
            if (!value) return validateRequired(value, '');
            return validateEmail(value);
        },
        phone: (value) => {
            if (!value) return validateRequired(value, '');
            return validatePhone(value);
        },
        password1: (value) => {
            if (!value) return validateRequired(value, '');
            return validatePassword(value);
        },
        password2: (value) => {
            const password1 = document.querySelector('#password1, [name="password1"]')?.value;
            if (!value) return validateRequired(value, ' ');
            return validatePasswordConfirm(password1, value);
        }
    };
    
    setupRealTimeValidation('form, .register-form', validators);
    
    //    
    const submitBtn = document.querySelector('#joinBtn, .register-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            if (!validateBeforeSubmit('form, .register-form', validators)) {
                e.preventDefault();
            }
        });
    }
}

//      
function setupProfileValidation() {
    const validators = {
        fname: (value) => {
            if (!value) return validateRequired(value, '');
            return validateName(value);
        },
        lname: (value) => validateName(value), // 
        contact: (value) => {
            if (!value) return validateRequired(value, '');
            return validatePhone(value);
        }
    };
    
    setupRealTimeValidation('form, .profile-form', validators);
    
    //    
    const submitBtn = document.querySelector('#updateBtn, .update-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            if (!validateBeforeSubmit('form, .profile-form', validators)) {
                e.preventDefault();
            }
        });
    }
}

//     
document.addEventListener('DOMContentLoaded', function() {
    const pathname = window.location.pathname;
    
    if (pathname.includes('login.html')) {
        setupLoginValidation();
    } else if (pathname.includes('join.html')) {
        // join.js    
        // setupRegistrationValidation();
    } else if (pathname.includes('edit-profile.html')) {
        setupProfileValidation();
    }
});

// CSS   
(function addValidationStyles() {
    if (document.querySelector('#validation-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'validation-styles';
    style.textContent = `
        .error {
            border-color: #ff4757 !important;
        }
        
        .success {
            border-color: #2ed573 !important;
        }
        
        .error-message {
            color: #ff4757;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .input-type1.error {
            border: 1px solid #ff4757;
        }
        
        .input-type1.success {
            border: 1px solid #2ed573;
        }
    `;
    document.head.appendChild(style);
})();