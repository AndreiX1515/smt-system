//   3 -   

let userEmail = '';
let verificationCode = '';

document.addEventListener("DOMContentLoaded", function () {
    const newPasswordInput = document.getElementById("newPassword");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const resetBtn = document.querySelector(".btn.primary.lg");
    
    // URL   
    const urlParams = new URLSearchParams(window.location.search);
    userEmail = urlParams.get('email') || '';
    verificationCode = urlParams.get('code') || '';
    
    if (!userEmail || !verificationCode) {
        alert(" .    .");
        location.href = "find-password.html";
        return;
    }
    
    //       
    [newPasswordInput, confirmPasswordInput].forEach(input => {
        if (input) {
            input.addEventListener("input", checkFormValidity);
        }
    });
    
    //   
    if (resetBtn) {
        resetBtn.addEventListener("click", handleResetPassword);
    }
    
    //    
    checkFormValidity();
});

//   
function checkFormValidity() {
    const newPasswordInput = document.getElementById("newPassword");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const resetBtn = document.querySelector(".btn.primary.lg");
    
    if (!newPasswordInput || !confirmPasswordInput || !resetBtn) return;
    
    const newPassword = newPasswordInput.value.trim();
    const confirmPassword = confirmPasswordInput.value.trim();
    
    const isValid = isValidPassword(newPassword) && 
                   newPassword === confirmPassword && 
                   newPassword.length > 0;
    
    if (isValid) {
        resetBtn.classList.remove("inactive");
        resetBtn.disabled = false;
    } else {
        resetBtn.classList.add("inactive");
        resetBtn.disabled = true;
    }
}

//   
function isValidPassword(password) {
    if (!password || password.length < 6) {
        return false;
    }
    
    // , ,    2 
    const hasLetter = /[a-zA-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    const typeCount = [hasLetter, hasNumber, hasSpecial].filter(Boolean).length;
    
    return typeCount >= 2;
}

//   
async function handleResetPassword() {
    const newPassword = document.getElementById("newPassword")?.value.trim();
    const confirmPassword = document.getElementById("confirmPassword")?.value.trim();
    
    if (!newPassword || !confirmPassword) {
        alert("  .");
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert("  .");
        return;
    }
    
    if (!isValidPassword(newPassword)) {
        alert(" , ,    2  6  .");
        return;
    }
    
    try {
        //  
        const resetBtn = document.querySelector(".btn.primary.lg");
        if (resetBtn) {
            resetBtn.disabled = true;
            resetBtn.textContent = " ...";
        }
        
        // API 
        const result = await api.findPassword('reset_password', userEmail, verificationCode, newPassword);
        
        if (result.success) {
            alert("  . .");
            
            //   
            location.href = "login.html";
        } else {
            alert(result.message || "  .");
        }
        
    } catch (error) {
        console.error('Reset password error:', error);
        alert("  .  .");
    } finally {
        //  
        const resetBtn = document.querySelector(".btn.primary.lg");
        if (resetBtn) {
            resetBtn.disabled = false;
            resetBtn.textContent = " ";
        }
    }
}

//  / 
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const img = button.querySelector('img');
    
    if (!input || !button || !img) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        img.src = '../images/ico_eye_on.svg';
    } else {
        input.type = 'password';
        img.src = '../images/ico_eye_off.svg';
    }
}
