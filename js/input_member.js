const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const passwordInput2 = document.getElementById('password2');
const nameInput = document.getElementById('name');
const phoneInput = document.getElementById('phone');

const loginButton = document.querySelector('.btn.primary');

function checkInputs() {
    const email = emailInput ? emailInput.value.trim() : null;
    const password = passwordInput ? passwordInput.value.trim() : null;
    const password2 = passwordInput2 ? passwordInput2.value.trim() : null;
    const name = nameInput ? nameInput.value.trim() : null;
    const phone = phoneInput ? phoneInput.value.trim() : null;

    let isValid = true;

    //   
    if (emailInput && email === '') isValid = false;
    if (passwordInput && password === '') isValid = false;
    if (passwordInput2 && password2 === '') isValid = false;
    if (nameInput && name === '') isValid = false;
    if (phoneInput && phone === '') isValid = false;

    if (isValid) {
        loginButton?.classList.remove('inactive');
        loginButton?.classList.add('active');
    } else {
        loginButton?.classList.remove('active');
        loginButton?.classList.add('inactive');
    }
}

// input     
if (emailInput) emailInput.addEventListener('input', checkInputs);
if (passwordInput) passwordInput.addEventListener('input', checkInputs);
if (passwordInput2) passwordInput2.addEventListener('input', checkInputs);
if (nameInput) nameInput.addEventListener('input', checkInputs);
if (phoneInput) phoneInput.addEventListener('input', checkInputs);

//     
window.addEventListener('DOMContentLoaded', checkInputs);
