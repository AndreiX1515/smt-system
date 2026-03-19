/**
 * Agent Admin - Inquiry List Page JavaScript
 */

let currentPage = 1;
let currentFilters = {
    responseStatus: '',
    sort: 'latest'
};

document.addEventListener('DOMContentLoaded', async function() {
    //  
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated) {
            window.location.href = '../index.html';
            return;
        }
        
        initializeInquiryList();
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
});

function initializeInquiryList() {
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            loadInquiries();
        });
    }
    
    //  (Response Status)  
    const responseStatusSelect = document.getElementById('responseStatus');
    if (responseStatusSelect) {
        responseStatusSelect.addEventListener('change', function() {
            currentFilters.responseStatus = this.value;
            currentPage = 1;
            loadInquiries();
        });
    }

    //  (Processing Status)  
    //   
    const sortSelect = document.getElementById('sort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            currentFilters.sort = this.value;
            currentPage = 1;
            loadInquiries();
        });
    }
    
    //  
    loadInquiries();
}

async function loadInquiries() {
    try {
        showLoading();
        
        const params = new URLSearchParams({
            action: 'getInquiries',
            page: currentPage,
            limit: 20
        });
        
        //   (  )
        if (currentFilters.responseStatus) {
            params.append('responseStatus', currentFilters.responseStatus);
        }
        if (currentFilters.sort) {
            params.append('sort', currentFilters.sort);
        }
        
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success) {
            renderInquiries(result.data.inquiries);
            renderPagination(result.data.pagination);
            updateResultCount(result.data.pagination.total);
        } else {
            console.error('Failed to load inquiries:', result.message);
            showError('   .');
        }
    } catch (error) {
        console.error('Error loading inquiries:', error);
        showError('     .');
    } finally {
        hideLoading();
    }
}

function renderInquiries(inquiries) {
    const tbody = document.getElementById('inquiries-tbody');
    if (!tbody) return;
    
    if (inquiries.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="is-center">  .</td></tr>';
        return;
    }
    
    tbody.innerHTML = inquiries.map(item => {
        const statusBadgeClass = getResponseBadgeClass(item.responseStatus);
        
        return `
        <tr>
            <td class="no is-center">${item.rowNum}</td>
            <td class="ellipsis">${escapeHtml(item.inquiryTitle)}</td>
            <td class="is-center">${formatDate(item.createdAt)}</td>
            <td class="is-center">
                <span class="badge ${statusBadgeClass}">${escapeHtml(item.responseLabel || '')}</span>
            </td>
        </tr>
    `;
    }).join('');

    // row click → detail
    tbody.querySelectorAll('tr').forEach((tr, idx) => {
        const inquiry = inquiries[idx];
        if (!inquiry || !inquiry.inquiryId) return;
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', () => goToInquiryDetail(inquiry.inquiryId));
    });
}

function getResponseBadgeClass(status) {
    const map = {
        'not_responded': 'badge-warning',
        'response_complete': 'badge-success'
    };
    return map[status] || 'badge-gray';
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
    loadInquiries();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToInquiryDetail(inquiryId) {
    window.location.href = `inquiry-detail.html?id=${inquiryId}`;
}

function updateResultCount(total) {
    const resultCountNum = document.querySelector('.result-count__num');
    if (resultCountNum) {
        resultCountNum.textContent = total;
    }
}

function showLoading() {
    const tbody = document.getElementById('inquiries-tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="4" class="is-center"> ...</td></tr>';
    }
}

function hideLoading() {
    //  renderInquiries 
}

function showError(message) {
    const tbody = document.getElementById('inquiries-tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="4" class="is-center" style="color: red;">${escapeHtml(message)}</td></tr>`;
    }
}

function formatDate(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
