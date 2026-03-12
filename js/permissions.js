//   

let currentPermissions = null;

document.addEventListener("DOMContentLoaded", function () {
    const updateBtn = document.querySelector(".btn.primary.lg");
    const formInputs = [
        document.getElementById("accountType"),
        document.getElementById("clientType"),
        document.getElementById("clientRole"),
        document.getElementById("seats")
    ];
    
    //       
    formInputs.forEach(input => {
        if (input) {
            input.addEventListener("change", checkFormValidity);
        }
    });
    
    //   
    if (updateBtn) {
        updateBtn.addEventListener("click", handleUpdatePermissions);
    }
    
    //   
    loadPermissions();
    
    //    
    checkFormValidity();
});

//   
async function loadPermissions() {
    const userId = localStorage.getItem('userId');
    
    if (!userId) {
        alert(' .');
        location.href = 'login.html';
        return;
    }
    
    try {
        const result = await api.getUserPermissions(userId);
        
        if (result.success) {
            currentPermissions = result.data;
            displayCurrentPermissions(result.data);
            populateForm(result.data);
        } else {
            alert(result.message || '    .');
        }
        
    } catch (error) {
        console.error('Load permissions error:', error);
        alert('   .');
    }
}

//    
function displayCurrentPermissions(permissions) {
    const container = document.getElementById('currentPermissions');
    
    if (!container) return;
    
    const clientInfo = permissions.clientInfo;
    
    container.innerHTML = `
        <div class="permission-item mb12">
            <div class="text fz12 fw500 lh18 black12"> </div>
            <div class="text fz12 fw400 lh18 gray6">${getAccountTypeLabel(permissions.accountType)}</div>
        </div>
        <div class="permission-item mb12">
            <div class="text fz12 fw500 lh18 black12"> </div>
            <div class="text fz12 fw400 lh18 gray6">${getClientTypeLabel(clientInfo.clientType)}</div>
        </div>
        <div class="permission-item mb12">
            <div class="text fz12 fw500 lh18 black12"> </div>
            <div class="text fz12 fw400 lh18 gray6">${getClientRoleLabel(clientInfo.clientRole)}</div>
        </div>
        <div class="permission-item mb12">
            <div class="text fz12 fw500 lh18 black12"> </div>
            <div class="text fz12 fw400 lh18 gray6">${clientInfo.seats}</div>
        </div>
        <div class="permission-item">
            <div class="text fz12 fw500 lh18 black12"></div>
            <div class="text fz12 fw400 lh18 gray6">${clientInfo.companyName || ''}</div>
        </div>
    `;
}

//    
function populateForm(permissions) {
    const accountTypeSelect = document.getElementById("accountType");
    const clientTypeSelect = document.getElementById("clientType");
    const clientRoleSelect = document.getElementById("clientRole");
    const seatsInput = document.getElementById("seats");
    
    if (accountTypeSelect) {
        accountTypeSelect.value = permissions.accountType;
    }
    
    if (clientTypeSelect) {
        clientTypeSelect.value = permissions.clientInfo.clientType;
    }
    
    if (clientRoleSelect) {
        clientRoleSelect.value = permissions.clientInfo.clientRole;
    }
    
    if (seatsInput) {
        seatsInput.value = permissions.clientInfo.seats;
    }
}

//   
function checkFormValidity() {
    const updateBtn = document.querySelector(".btn.primary.lg");
    
    if (!updateBtn) return;
    
    //    
    const hasChanges = checkForChanges();
    
    if (hasChanges) {
        updateBtn.classList.remove("inactive");
        updateBtn.disabled = false;
    } else {
        updateBtn.classList.add("inactive");
        updateBtn.disabled = true;
    }
}

//  
function checkForChanges() {
    if (!currentPermissions) return false;
    
    const accountType = document.getElementById("accountType")?.value;
    const clientType = document.getElementById("clientType")?.value;
    const clientRole = document.getElementById("clientRole")?.value;
    const seats = document.getElementById("seats")?.value;
    
    const clientInfo = currentPermissions.clientInfo;
    
    return (
        (accountType && accountType !== currentPermissions.accountType) ||
        (clientType && clientType !== clientInfo.clientType) ||
        (clientRole && clientRole !== clientInfo.clientRole) ||
        (seats && parseInt(seats) !== clientInfo.seats)
    );
}

//   
async function handleUpdatePermissions() {
    const userId = localStorage.getItem('userId');
    
    if (!userId) {
        alert(' .');
        location.href = 'login.html';
        return;
    }
    
    const accountType = document.getElementById("accountType")?.value;
    const clientType = document.getElementById("clientType")?.value;
    const clientRole = document.getElementById("clientRole")?.value;
    const seats = document.getElementById("seats")?.value;
    
    //   
    if (!checkForChanges()) {
        alert('  .');
        return;
    }
    
    //  
    if (!confirm(' ?       .')) {
        return;
    }
    
    try {
        //  
        const updateBtn = document.querySelector(".btn.primary.lg");
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.textContent = " ...";
        }
        
        // API 
        const result = await api.updateUserPermissions(userId, {
            accountType: accountType || undefined,
            clientType: clientType || undefined,
            clientRole: clientRole || undefined,
            seats: seats ? parseInt(seats) : undefined
        });
        
        if (result.success) {
            alert('  .');
            
            //    
            await loadPermissions();
        } else {
            alert(result.message || '  .');
        }
        
    } catch (error) {
        console.error('Update permissions error:', error);
        alert('  .  .');
    } finally {
        //  
        const updateBtn = document.querySelector(".btn.primary.lg");
        if (updateBtn) {
            updateBtn.disabled = false;
            updateBtn.textContent = "  ";
        }
    }
}

//   
function getAccountTypeLabel(type) {
    const labels = {
        'guest': ' ',
        'agent': '',
        'employee': '',
        'admin': ''
    };
    return labels[type] || type;
}

function getClientTypeLabel(type) {
    const labels = {
        'Retailer': '',
        'Wholeseller': ''
    };
    return labels[type] || type;
}

function getClientRoleLabel(role) {
    const labels = {
        'Sub-Agent': ' ',
        'Head Agent': ' '
    };
    return labels[role] || role;
}
