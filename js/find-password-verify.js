//   2 -  

let userEmail = '';
let resendTimer = null;
let resendCountdown = 0;

document.addEventListener("DOMContentLoaded", function () {
    const verificationCodeInput = document.getElementById("verificationCode");
    const verifyBtn = document.querySelector(".btn.primary.lg");
    const resendLink = document.getElementById("resendCode");
    const emailDisplay = document.getElementById("userEmail");
    
    // URL  
    const urlParams = new URLSearchParams(window.location.search);
    userEmail = urlParams.get('email') || '';
    
    if (emailDisplay && userEmail) {
        emailDisplay.textContent = userEmail;
    }
    
    //       
    if (verificationCodeInput) {
        verificationCodeInput.addEventListener("input", checkFormValidity);
        verificationCodeInput.addEventListener("input", formatVerificationCode);
    }
    
    //   
    if (verifyBtn) {
        verifyBtn.addEventListener("click", handleVerifyCode);
    }
    
    //    
    if (resendLink) {
        resendLink.addEventListener("click", handleResendCode);
    }
    
    //    
    checkFormValidity();
    
    //   
    startResendTimer();
});

//   
function checkFormValidity() {
    const verificationCodeInput = document.getElementById("verificationCode");
    const verifyBtn = document.querySelector(".btn.primary.lg");
    
    if (!verificationCodeInput || !verifyBtn) return;
    
    const code = verificationCodeInput.value.trim();
    const isValid = code.length === 6 && /^[0-9]+$/.test(code);
    
    if (isValid) {
        verifyBtn.classList.remove("inactive");
        verifyBtn.disabled = false;
    } else {
        verifyBtn.classList.add("inactive");
        verifyBtn.disabled = true;
    }
}

//   ( )
function formatVerificationCode() {
    const input = document.getElementById("verificationCode");
    if (!input) return;
    
    let value = input.value.replace(/[^0-9]/g, '');
    if (value.length > 6) {
        value = value.substring(0, 6);
    }
    input.value = value;
}

//   
async function handleVerifyCode() {
    const verificationCode = document.getElementById("verificationCode")?.value.trim();
    
    if (!verificationCode) {
        alert(" .");
        return;
    }
    
    if (verificationCode.length !== 6) {
        alert("6  .");
        return;
    }
    
    if (!/^[0-9]+$/.test(verificationCode)) {
        alert("  .");
        return;
    }
    
    try {
        //  
        const verifyBtn = document.querySelector(".btn.primary.lg");
        if (verifyBtn) {
            verifyBtn.disabled = true;
            verifyBtn.textContent = " ...";
        }
        
        // API 
        const result = await api.findPassword('verify_code', userEmail, verificationCode);
        
        if (result.success) {
            alert(" .");
            
            // 3  
            const params = new URLSearchParams({ 
                email: userEmail,
                code: verificationCode
            });
            location.href = `find-password-reset.html?${params.toString()}`;
        } else {
            alert(result.message || "  .");
        }
        
    } catch (error) {
        console.error('Verify code error:', error);
        alert("  .  .");
    } finally {
        //  
        const verifyBtn = document.querySelector(".btn.primary.lg");
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.textContent = " ";
        }
    }
}

//   
async function handleResendCode(event) {
    event.preventDefault();
    
    if (resendCountdown > 0) {
        alert(` ${resendCountdown}  .`);
        return;
    }
    
    if (!userEmail) {
        alert("    .");
        return;
    }
    
    try {
        // API 
        const result = await api.findPassword('send_code', userEmail);
        
        if (result.success) {
            alert(result.message);
            startResendTimer(); //  
        } else {
            alert(result.message || "  .");
        }
        
    } catch (error) {
        console.error('Resend code error:', error);
        alert("  .  .");
    }
}

//   
function startResendTimer() {
    resendCountdown = 60; // 60 
    
    const resendLink = document.getElementById("resendCode");
    if (!resendLink) return;
    
    resendLink.textContent = ` (${resendCountdown})`;
    resendLink.style.color = '#999';
    resendLink.style.pointerEvents = 'none';
    
    resendTimer = setInterval(() => {
        resendCountdown--;
        
        if (resendCountdown > 0) {
            resendLink.textContent = ` (${resendCountdown})`;
        } else {
            resendLink.textContent = '';
            resendLink.style.color = '';
            resendLink.style.pointerEvents = '';
            clearInterval(resendTimer);
        }
    }, 1000);
}
