/**
 * Agent Admin - Customer List Page JavaScript
 */

let currentPage = 1;
let currentFilters = {
    search: ''
};

let batchModalState = {
    dialog: null,
    selectedFile: null
};

document.addEventListener('DOMContentLoaded', async function() {
    //  
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated || sessionData.userType !== 'agent') {
            window.location.href = '../index.html';
            return;
        }
        
        initializeCustomerList();
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
});

document.addEventListener('modal:loaded', function(event) {
    const { dialog, action } = event.detail || {};
    if (!dialog || !action) return;
    if (action.includes('customer-batch-upload-modal.html')) {
        initializeBatchUploadModal(dialog);
    }
    if (action.includes('customer-assign-existing-modal.html')) {
        initializeAssignExistingModal(dialog);
    }
});

function initializeCustomerList() {
    //    
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            loadCustomers();
        });
    }
    
    //      (debounce)
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentFilters.search = this.value;
                currentPage = 1;
                loadCustomers();
            }, 500);
        });
    }
    
    //  
    loadCustomers();
}

async function loadCustomers() {
    try {
        showLoading();
        
        const params = new URLSearchParams({
            action: 'getCustomers',
            page: currentPage,
            limit: 10, // SMT : (10) 
            ...currentFilters
        });
        
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, {
            credentials: 'same-origin'
        });
        
        //   
        const responseText = await response.text();
        
        //   
        if (!response.ok) {
            console.error('HTTP Error Response:', responseText);
            // JSON     
            try {
                const errorResult = JSON.parse(responseText);
                throw new Error(`HTTP ${response.status}: ${errorResult.message || 'Server error'}`);
            } catch (e) {
                // JSON     
                throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 500)}`);
            }
        }
        
        if (!responseText || responseText.trim() === '') {
            throw new Error('Empty response from server');
        }
        
        // JSON 
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            // HTML    
            if (responseText.includes('<html') || responseText.includes('Fatal error') || responseText.includes('Parse error')) {
                throw new Error('PHP Error: ' + responseText.substring(0, 500));
            }
            throw new Error('Invalid JSON response from server: ' + responseText.substring(0, 200));
        }
        
        if (result.success) {
            renderCustomers(result.data.customers);
            renderPagination(result.data.pagination);
            updateResultCount(result.data.pagination.total);
        } else {
            console.error('Failed to load customers:', result.message);
            showError('   : ' + (result.message || '   '));
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showError('     : ' + error.message);
    } finally {
        hideLoading();
    }
}

function renderCustomers(customers) {
    const tbody = document.getElementById('customersTableBody') || document.querySelector('.jw-tableA tbody');
    if (!tbody) return;
    
    if (customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="is-center">  .</td></tr>';
        return;
    }
    
    tbody.innerHTML = customers.map(item => {
        const phone = item.phone || item.contactNo || '';
        return `
            <tr onclick="goToCustomerDetail(${item.accountId})">
                <td class="no is-center">${item.rowNum}</td>
                <td class="is-center">${escapeHtml(item.customerName)}</td>
                <td class="is-center">${escapeHtml(item.email)}</td>
                <td class="is-center">${escapeHtml(phone)}</td>
                <td class="is-center">${formatDateTime(item.createdAt)}</td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
    const pagebox = document.querySelector('.jw-pagebox');
    if (!pagebox) return;
    
    const pageContainer = pagebox.querySelector('.page');
    if (!pageContainer) return;
    
    const totalPages = pagination.totalPages;
    const current = pagination.page;
    
    let pageNumbers = [];
    const maxPages = 5;
    let startPage = Math.max(1, current - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pageNumbers.push(i);
    }
    
    pageContainer.innerHTML = pageNumbers.map(page => `
        <button type="button" class="p ${page === current ? 'show' : ''}" 
                role="listitem" ${page === current ? 'aria-current="page"' : ''}
                onclick="goToPage(${page})">${page}</button>
    `).join('');
    
    //  /   
    const firstBtn = pagebox.querySelector('.first');
    const prevBtn = pagebox.querySelector('.prev');
    if (firstBtn && prevBtn) {
        const disabled = current === 1;
        firstBtn.disabled = disabled;
        prevBtn.disabled = disabled;
        firstBtn.setAttribute('aria-disabled', disabled);
        prevBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            firstBtn.onclick = () => goToPage(1);
            prevBtn.onclick = () => goToPage(current - 1);
        }
    }
    
    //  /   
    const nextBtn = pagebox.querySelector('.next');
    const lastBtn = pagebox.querySelector('.last');
    if (nextBtn && lastBtn) {
        const disabled = current === totalPages;
        nextBtn.disabled = disabled;
        lastBtn.disabled = disabled;
        nextBtn.setAttribute('aria-disabled', disabled);
        lastBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            nextBtn.onclick = () => goToPage(current + 1);
            lastBtn.onclick = () => goToPage(totalPages);
        }
    }
}

function goToPage(page) {
    currentPage = page;
    loadCustomers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToCustomerDetail(accountId) {
    window.location.href = `customer-detail.html?id=${accountId}`;
}

function updateResultCount(total) {
    const resultCountNum = document.querySelector('.result-count__num');
    if (resultCountNum) {
        resultCountNum.textContent = total;
    }
}

function showLoading() {
    const tbody = document.getElementById('customersTableBody') || document.querySelector('.jw-tableA tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="5" class="is-center"> ...</td></tr>';
    }
}

function hideLoading() {
    //  renderCustomers 
}

function showError(message) {
    const tbody = document.getElementById('customersTableBody') || document.querySelector('.jw-tableA tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="5" class="is-center" style="color: red;">${escapeHtml(message)}</td></tr>`;
    }
}

function formatDateTime(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initializeBatchUploadModal(dialog) {
    batchModalState.dialog = dialog;
    batchModalState.selectedFile = null;
    dialog.addEventListener('close', resetBatchModalState, { once: true });

    const sampleBtn = dialog.querySelector('#sampleDownloadBtn');
    const fileInput = dialog.querySelector('#batchFileInput');
    const uploadBtn = dialog.querySelector('#batchFileUploadBtn');
    const fileRemoveBtn = dialog.querySelector('#fileRemoveBtn');
    const registerBtn = dialog.querySelector('#batchRegisterBtn');
    const fileInfo = dialog.querySelector('#fileInfo');

    if (fileInfo) {
        fileInfo.style.display = 'none';
    }
    if (registerBtn) {
        registerBtn.disabled = true;
    }

    if (sampleBtn) {
        sampleBtn.addEventListener('click', handleSampleDownload);
    }

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => fileInput.click());
    }

    if (fileInput) {
        fileInput.addEventListener('change', handleBatchFileChange);
    }

    if (fileRemoveBtn) {
        fileRemoveBtn.addEventListener('click', () => clearBatchFile(dialog));
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', handleBatchRegister);
    }

    const closeBtn = dialog.querySelector('#closeDialog');
    if (closeBtn) {
        closeBtn.addEventListener('click', resetBatchModalState, { once: true });
    }
}

function resetBatchModalState() {
    batchModalState = { dialog: null, selectedFile: null };
}

function handleSampleDownload() {
    window.location.href = '../backend/api/agent-api.php?action=downloadCustomerSample';
}

function handleBatchFileChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!isValidBatchFile(file)) {
        alert('Excel  CSV   .');
        event.target.value = '';
        return;
    }

    batchModalState.selectedFile = file;
    displayBatchFileInfo(file);
}

function isValidBatchFile(file) {
    const validTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv'
    ];
    const validExtensions = ['.xlsx', '.xls', '.csv'];
    const fileExt = '.' + (file.name.split('.').pop() || '').toLowerCase();
    return validTypes.includes(file.type) || validExtensions.includes(fileExt);
}

function displayBatchFileInfo(file) {
    if (!batchModalState.dialog) return;
    const fileInfo = batchModalState.dialog.querySelector('#fileInfo');
    const fileName = batchModalState.dialog.querySelector('#fileName');
    const registerBtn = batchModalState.dialog.querySelector('#batchRegisterBtn');

    if (!fileInfo || !fileName || !registerBtn) return;

    const sizeInKB = (file.size / 1024).toFixed(0);
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    fileName.textContent = ` [${ext}, ${sizeInKB}KB]`;
    fileInfo.style.display = 'block';
    registerBtn.disabled = false;
}

function clearBatchFile(dialog = batchModalState.dialog) {
    if (!dialog) return;
    const fileInput = dialog.querySelector('#batchFileInput');
    const fileInfo = dialog.querySelector('#fileInfo');
    const registerBtn = dialog.querySelector('#batchRegisterBtn');

    if (fileInput) fileInput.value = '';
    if (fileInfo) fileInfo.style.display = 'none';
    if (registerBtn) registerBtn.disabled = true;

    batchModalState.selectedFile = null;
}

async function handleBatchRegister() {
    const file = batchModalState.selectedFile;
    if (!file) {
        alert(' .');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('action', 'batchUploadCustomers');

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || '   ');
        }

        const successCount = result.data?.successCount ?? 0;
        const errorCount = result.data?.errorCount ?? 0;
        const errors = Array.isArray(result.data?.errors) ? result.data.errors : [];

        let message = `  . (: ${successCount}, : ${errorCount})`;
        if (errors.length > 0) {
            //       N
            const head = errors.slice(0, 5).join('\n');
            message += `\n\n ( 5):\n${head}`;
            if (errors.length > 5) message += `\n...  ${errors.length - 5}`;
        }
        alert(message);
        resetBatchModalState();
        modal_close();
        loadCustomers();
    } catch (error) {
        console.error('Batch upload error:', error);
        alert('   : ' + error.message);
    }
}

// ========== Assign Existing Customer Modal ==========

let assignModalState = {
    dialog: null,
    selectedAccountId: null
};

function initializeAssignExistingModal(dialog) {
    assignModalState.dialog = dialog;
    assignModalState.selectedAccountId = null;
    dialog.addEventListener('close', resetAssignModalState, { once: true });

    const searchBtn = dialog.querySelector('#assignSearchBtn');
    const emailInput = dialog.querySelector('#assignEmailInput');
    const registerBtn = dialog.querySelector('#assignRegisterBtn');
    const closeBtn = dialog.querySelector('#closeDialog');

    if (registerBtn) registerBtn.disabled = true;

    if (searchBtn) {
        searchBtn.addEventListener('click', () => searchB2CCustomer(dialog));
    }
    if (emailInput) {
        emailInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchB2CCustomer(dialog);
            }
        });
    }
    if (registerBtn) {
        registerBtn.addEventListener('click', () => assignExistingCustomer(dialog));
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', resetAssignModalState, { once: true });
    }
}

function resetAssignModalState() {
    assignModalState = { dialog: null, selectedAccountId: null };
}

async function searchB2CCustomer(dialog) {
    const emailInput = dialog.querySelector('#assignEmailInput');
    const resultArea = dialog.querySelector('#assignSearchResult');
    const registerBtn = dialog.querySelector('#assignRegisterBtn');

    const email = (emailInput?.value || '').trim();
    if (!email) {
        alert('Please enter an email address.');
        emailInput?.focus();
        return;
    }

    assignModalState.selectedAccountId = null;
    if (registerBtn) registerBtn.disabled = true;

    resultArea.style.display = 'block';
    resultArea.innerHTML = '<p style="text-align:center; color:#969696;">Searching...</p>';

    try {
        const params = new URLSearchParams({
            action: 'searchB2CCustomer',
            email: email
        });
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            resultArea.innerHTML = `<p style="color:#E1322E; font-size:14px; padding:12px; background:#FFF5F5; border-radius:10px;">${escapeHtml(result.message)}</p>`;
            return;
        }

        const customer = result.data;
        resultArea.innerHTML = `
            <div style="padding:16px; background:#F7FAFF; border-radius:10px; border:1px solid #D4E7FF;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="../image/person.svg" alt="" style="width:32px; height:32px; opacity:0.6;">
                    <div>
                        <div style="font-size:16px; font-weight:bold; color:#121212;">${escapeHtml(customer.name)}</div>
                        <div style="font-size:14px; color:#969696;">${escapeHtml(customer.email)}</div>
                    </div>
                </div>
            </div>
        `;

        assignModalState.selectedAccountId = customer.accountId;
        if (registerBtn) registerBtn.disabled = false;

    } catch (error) {
        console.error('Search B2C customer error:', error);
        resultArea.innerHTML = '<p style="color:#E1322E; font-size:14px;">Search failed. Please try again.</p>';
    }
}

async function assignExistingCustomer(dialog) {
    if (!assignModalState.selectedAccountId) {
        alert('Please search and select a customer first.');
        return;
    }

    const registerBtn = dialog.querySelector('#assignRegisterBtn');
    if (registerBtn) registerBtn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'assignExistingCustomer');
        formData.append('targetAccountId', assignModalState.selectedAccountId);

        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to assign customer');
        }

        alert('Customer has been successfully assigned.');
        resetAssignModalState();
        modal_close();
        loadCustomers();
    } catch (error) {
        console.error('Assign customer error:', error);
        alert('Failed to assign customer: ' + error.message);
        if (registerBtn) registerBtn.disabled = false;
    }
}