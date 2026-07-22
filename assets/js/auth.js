// Authentication Form Validation
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // Login Form Validation
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            let isValid = true;
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            // Validate email
            if (!email.value.trim()) {
                showError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            } else {
                showSuccess(email);
            }
            
            // Validate password
            if (!password.value) {
                showError(password, 'Password is required');
                isValid = false;
            } else {
                showSuccess(password);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Register Form Validation
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            let isValid = true;
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Validate first name
            if (!firstName.value.trim()) {
                showError(firstName, 'First name is required');
                isValid = false;
            } else {
                showSuccess(firstName);
            }
            
            // Validate last name
            if (!lastName.value.trim()) {
                showError(lastName, 'Last name is required');
                isValid = false;
            } else {
                showSuccess(lastName);
            }
            
            // Validate email
            if (!email.value.trim()) {
                showError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            } else {
                showSuccess(email);
            }
            
            // Validate password
            if (!password.value) {
                showError(password, 'Password is required');
                isValid = false;
            } else if (password.value.length < 8) {
                showError(password, 'Password must be at least 8 characters');
                isValid = false;
            } else {
                showSuccess(password);
            }
            
            // Validate confirm password
            if (!confirmPassword.value) {
                showError(confirmPassword, 'Please confirm your password');
                isValid = false;
            } else if (password.value !== confirmPassword.value) {
                showError(confirmPassword, 'Passwords do not match');
                isValid = false;
            } else {
                showSuccess(confirmPassword);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Real-time validation on input
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
    });
});

// Helper Functions
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showError(input, message) {
    const formGroup = input.closest('.form-group');
    const feedback = formGroup.querySelector('.invalid-feedback');
    
    input.classList.add('is-invalid');
    if (feedback) {
        feedback.textContent = message;
    }
}

function showSuccess(input) {
    const formGroup = input.closest('.form-group');
    const feedback = formGroup.querySelector('.invalid-feedback');
    
    input.classList.remove('is-invalid');
    if (feedback) {
        feedback.textContent = '';
    }
}

function validateField(input) {
    const value = input.value.trim();
    
    if (input.hasAttribute('required') && !value) {
        showError(input, 'This field is required');
        return false;
    }
    
    if (input.type === 'email' && value && !isValidEmail(value)) {
        showError(input, 'Please enter a valid email address');
        return false;
    }
    
    if (input.type === 'password' && input.id === 'confirm_password' && value) {
        const password = document.getElementById('password');
        if (password.value !== value) {
            showError(input, 'Passwords do not match');
            return false;
        }
    }
    
    showSuccess(input);
    return true;
}