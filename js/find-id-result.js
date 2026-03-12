//    

document.addEventListener("DOMContentLoaded", function () {
    displayFoundEmail();
});

//   
function displayFoundEmail() {
    const urlParams = new URLSearchParams(window.location.search);
    const email = urlParams.get('email');
    const maskedEmail = urlParams.get('maskedEmail');
    const username = urlParams.get('username');
    
    const emailElement = document.getElementById('foundEmail');
    
    if (!emailElement) {
        console.error('Email display element not found.');
        return;
    }
    
    if (email && maskedEmail) {
        //   
        emailElement.textContent = maskedEmail;
        
        //     
        if (username) {
            emailElement.title = `Username: ${username}`;
        }
    } else {
        // URL     
        emailElement.textContent = 'Email information not found.';
        emailElement.style.color = '#ff4444';
        
        // 3    
        setTimeout(() => {
            history.back();
        }, 3000);
    }
}
