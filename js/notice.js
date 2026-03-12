//   

let notices = [];
let currentCategory = 'all';
let currentPage = 1;
let totalPages = 1;

function normalizeLang(raw) {
    const l = String(raw || '').toLowerCase();
    return (l === 'tl') ? 'tl' : 'en'; // policy: only en/tl, default en
}

//    
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('notice.html')) {
        initializeNoticePage();
    }
});

//   
async function initializeNoticePage() {
    // URL   
    const urlParams = new URLSearchParams(window.location.search);
    currentCategory = urlParams.get('category') || 'all';
    
    //   
    await loadNotices();
    
    //   
    setupCategoryFilter();
    
    //   
    renderNoticeList();
}

//   
async function loadNotices() {
    try {
        showLoadingState();
        
        //  API 
        // NOTE: currentCategory  'all', API category   "" .
        //  'all'  DB category='all'   0   .
        const categoryParam = (currentCategory === 'all' || !currentCategory) ? '' : currentCategory;
        const result = await api.getNotices(categoryParam, 20, (currentPage - 1) * 20);
        
        if (result.success) {
            notices = (result.data && Array.isArray(result.data.notices)) ? result.data.notices : [];
            // SMT: ensure newest-first ordering even if backend data is inconsistent
            notices.sort((a, b) => {
                const da = parseNoticeTime(a);
                const db = parseNoticeTime(b);
                return db - da;
            });
            //    
            if (result.data && result.data.categoryCounts) {
                updateCategoryCounts(result.data.categoryCounts);
            }
        } else {
            showEmptyState('No notices found.');
        }
        
    } catch (error) {
        console.error('Failed to load notices:', error);
        showEmptyState('No notices found.');
    } finally {
        hideLoadingState();
    }
}

//   
function setupCategoryFilter() {
    const categoryButtons = document.querySelectorAll('.category-filter button');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            //   
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            
            //   
            button.classList.add('active');
            
            //  
            currentCategory = button.dataset.category || 'all';
            currentPage = 1;
            
            // URL 
            const url = new URL(window.location);
            if (currentCategory === 'all') {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', currentCategory);
            }
            window.history.pushState({}, '', url);
            
            //   
            loadNotices().then(() => {
                renderNoticeList();
            });
        });
    });
}

//   
function renderNoticeList() {
    const container = document.querySelector('.notice-list-container') || createNoticeContainer();
    
    if (notices.length === 0) {
        showEmptyState(getEmptyMessage());
        return;
    }

    //   (list-type8) 
    container.innerHTML = notices.map(notice => createNoticeItem(notice)).join('');
}

function getActiveLanguage() {
    try {
        if (typeof getCurrentLanguage === 'function') return getCurrentLanguage();
    } catch (e) {}
    return normalizeLang(localStorage.getItem('selectedLanguage') || 'en');
}

function pickLocalizedText(value) {
    if (value == null) return '';
    if (typeof value !== 'string') return String(value);
    const s = value.trim();
    if (!s) return '';
    //   {"ko":"...","en":"...","tl":"..."}    
    if (s.startsWith('{') && s.endsWith('}')) {
        try {
            const obj = JSON.parse(s);
            const lang = getActiveLanguage();
            if (obj && typeof obj === 'object') {
                return String(obj[lang] ?? obj.ko ?? obj.en ?? obj.tl ?? value);
            }
        } catch (e) {
            // ignore
        }
    }
    return value;
}

function pickLocalizedAsset(value) {
    // title/content  JSON   imageUrl 
    return pickLocalizedText(value);
}

function parseNoticeTime(notice) {
    const raw = (notice && (notice.publishedAt || notice.createdAt || notice.updatedAt)) ? String(notice.publishedAt || notice.createdAt || notice.updatedAt) : '';
    if (!raw) return 0;
    // Accept both 'YYYY-MM-DD HH:mm:ss' and ISO; normalize for Date.parse
    const norm = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw;
    const ms = Date.parse(norm);
    return Number.isFinite(ms) ? ms : 0;
}

function formatNoticeDate(raw) {
    const s = String(raw || '').trim();
    if (!s) return '';
    // Prefer stable display over Date parsing (Safari-safe): take YYYY-MM-DD
    if (s.length >= 10) return s.slice(0, 10);
    return s;
}

//   HTML 
function createNoticeItem(notice) {
    const created = notice?.publishedAt || notice?.createdAt || notice?.updatedAt || '';
    const publishedDate = formatNoticeDate(created);
    const title = pickLocalizedText(notice?.title || '');
    const imgUrlRaw = pickLocalizedAsset(notice?.imageUrl || '');
    const imgUrl = imgUrlRaw ? (String(imgUrlRaw).startsWith('/') ? String(imgUrlRaw) : `/${String(imgUrlRaw)}`) : '';
    const thumbHtml = imgUrl ? `<div class="thumbnail-wrap"><img src="${escapeHtml(imgUrl)}" alt=""></div>` : '';
    
    return `
        <li>
            <a class="align both" href="notice-detail.html?id=${encodeURIComponent(String(notice.noticeId))}">
                <div>
                    <div class="text fz16 fw600 lh24 black12">${escapeHtml(title)}</div>
                    <div class="text fz13 fw400 lh24 gray96 mt13">${escapeHtml(publishedDate)}</div>
                </div>
                ${thumbHtml}
            </a>
        </li>
    `;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

//   
function getCategoryText(category) {
    const categoryMap = {
        'general': '',
        'booking': '',
        'payment': '',
        'visa': '',
        'system': ''
    };
    return categoryMap[category] || category;
}

//  CSS  
function getPriorityClass(priority) {
    const classMap = {
        'high': 'priority-high',
        'medium': 'priority-medium',
        'low': 'priority-low'
    };
    return classMap[priority] || 'priority-medium';
}

//   
function updateCategoryCounts(categoryCounts) {
    const categoryButtons = document.querySelectorAll('.category-filter button');
    
    categoryButtons.forEach(button => {
        const category = button.dataset.category || 'all';
        const count = category === 'all' ? 
            Object.values(categoryCounts).reduce((sum, count) => sum + count, 0) : 
            categoryCounts[category] || 0;
        
        const countSpan = button.querySelector('.count');
        if (countSpan) {
            countSpan.textContent = `(${count})`;
        }
    });
}

//   
function showEmptyState(message = ' .') {
    const container = document.querySelector('.notice-list-container') || 
                     document.querySelector('.px20') ||
                     document.querySelector('.main');
    
    if (!container) return;
    
    // ul.list-type8   li 
    container.innerHTML = `
        <li style="border-bottom:none;">
            <div class="text fz16 fw500 lh24 gray8" style="text-align:center; padding: 40px 0;">
                ${escapeHtml(message)}
            </div>
        </li>
    `;
}

//    
function getEmptyMessage() {
    const messages = {
        'all': 'No notices found.',
        'general': 'No notices found.',
        'booking': 'No notices found.',
        'payment': 'No notices found.',
        'visa': 'No notices found.',
        'system': 'No notices found.'
    };
    
    return messages[currentCategory] || 'No notices found.';
}

//   
function createNoticeContainer() {
    const container = document.querySelector('.px20') || document.querySelector('.main');
    if (!container) return null;
    
    const noticeContainer = document.createElement('div');
    noticeContainer.className = 'notice-list-container mt24';
    
    container.appendChild(noticeContainer);
    return noticeContainer;
}

//   
function showLoadingState() {
    const container = document.querySelector('.notice-list-container') || 
                     document.querySelector('.px20') ||
                     document.querySelector('.main');
    
    if (!container) return;
    
    const loadingHtml = `
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">  ...</div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', loadingHtml);
}

//   
function hideLoadingState() {
    const loadingState = document.querySelector('.loading-state');
    if (loadingState) {
        loadingState.remove();
    }
}

