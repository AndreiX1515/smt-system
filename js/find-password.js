//   1 -  

document.addEventListener("DOMContentLoaded", function () {
    const emailInput = document.getElementById("email");
    const sendCodeBtn = document.querySelector(".btn.primary.lg");
    
    //       
    if (emailInput) {
        emailInput.addEventListener("input", checkFormValidity);
    }
    
    //   
    if (sendCodeBtn) {
        sendCodeBtn.addEventListener("click", handleSendCode);
    }
    
    //    
    checkFormValidity();
});

//   
function checkFormValidity() {
    const emailInput = document.getElementById("email");
    const sendCodeBtn = document.querySelector(".btn.primary.lg");
    
    if (!emailInput || !sendCodeBtn) return;
    
    const email = emailInput.value.trim();
    const isValid = isValidEmail(email);
    
    if (isValid) {
        sendCodeBtn.classList.remove("inactive");
        sendCodeBtn.disabled = false;
    } else {
        sendCodeBtn.classList.add("inactive");
        sendCodeBtn.disabled = true;
    }
}

//   
function isValidEmail(email) {
    if (!email || email.trim() === '') {
        return false;
    }
    
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return emailRegex.test(email.trim());
}

//   
async function handleSendCode() {
    const email = document.getElementById("email")?.value.trim();
    
    if (!email) {
        alert(" .");
        return;
    }
    
    if (!isValidEmail(email)) {
        alert("   .");
        return;
    }
    
    try {
        //  
        const sendCodeBtn = document.querySelector(".btn.primary.lg");
        if (sendCodeBtn) {
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = " ...";
        }
        
        // API 
        const result = await api.findPassword('send_code', email);
        
        if (result.success) {
            alert(result.message);
            
            // 2  
            const params = new URLSearchParams({ email: email });
            location.href = `find-password-verify.html?${params.toString()}`;
        } else {
            alert(result.message || "  .");
        }
        
    } catch (error) {
        console.error('Send code error:', error);
        alert("  .  .");
    } finally {
        //  
        const sendCodeBtn = document.querySelector(".btn.primary.lg");
        if (sendCodeBtn) {
            sendCodeBtn.disabled = false;
            sendCodeBtn.textContent = " ";
        }
    }
}
