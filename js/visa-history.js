//     

let visaApplications = [];
let currentStatus = 'pending';

//    
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('visa-history.html')) {
        setupVisaHistoryBackLink();
        initializeVisaHistoryPage();
    }
});

function setupVisaHistoryBackLink() {
    try {
        const a = document.querySelector('a.btn-mypage');
        if (!a) return;

        // Prefer explicit lang query param, then i18n helper, default to 'en'
        const qp = new URLSearchParams(window.location.search);
        const lang = (qp.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : '') || 'en').toLowerCase();

        // Always navigate by absolute path (avoid history.back which can be a no-op)
        // Keep language parameter so user stays in the same language context.
        a.setAttribute('href', `mypage.html?lang=${encodeURIComponent(lang)}`);
    } catch (_) { /* ignore */ }
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

//     
async function initializeVisaHistoryPage() {
    //  (tools/visa-history.html)   
    //  DB    (#inadequateDocuments ) .
    setupStatusFilter();
    await loadVisaApplications();
}

//
async function loadVisaApplications() {
    try {
        // TEST MODE: Use sample data (set to false to use real API)
        // const USE_TEST_DATA = true;
        const USE_TEST_DATA = false;

        console.log('=== VISA HISTORY DEBUG ===');
        console.log('USE_TEST_DATA:', USE_TEST_DATA);

        if (USE_TEST_DATA) {
            console.log('Using test data mode');

            // Generate sample data
            visaApplications = generateSampleVisaData();
            console.log('Generated applications:', visaApplications.length);

            const statusCounts = {
                pending: visaApplications.filter(v => v.applicationStatus === 'pending').length,
                under_review: visaApplications.filter(v => v.applicationStatus === 'under_review').length,
                approved: visaApplications.filter(v => v.applicationStatus === 'approved').length,
                rejected: visaApplications.filter(v => v.applicationStatus === 'rejected').length
            };
            console.log('Status counts:', statusCounts);

            updateStatusCounts(statusCounts);
            renderAllPanels();
            return;
        }

        showLoadingState();

        //  ID
        const userId = localStorage.getItem('userId') || '1';

        //    API
        const response = await fetch(`../backend/api/visa_applications.php?accountId=${encodeURIComponent(userId)}&status=all`);
        const result = await response.json();

        if (result.success) {
            visaApplications = (result.data && Array.isArray(result.data.visas)) ? result.data.visas : [];
            updateStatusCounts(result.data?.statusCounts || {});
            renderAllPanels();
        } else {
            showEmptyState('Failed to load visa application history.');
        }

    } catch (error) {
        console.error('Failed to load visa applications:', error);
        showEmptyState('Failed to load visa application history.');
    } finally {
        hideLoadingState();
    }
}

// Generate sample visa application data for testing
function generateSampleVisaData() {
    const applicants = [
        'Jose Ramirez', 'Maria Garcia', 'John Smith', 'Lisa Anderson', 'Kim Min-ji',
        'Tanaka Yuki', 'Sarah Johnson', 'Michael Brown', 'Anna Lee', 'David Wilson',
        'Emma Davis', 'James Martinez', 'Sofia Rodriguez', 'Oliver Taylor', 'Isabella Chen'
    ];

    const packages = [
        'Seoul Cherry Blossom Highlights 6-Day, 5-Night Package',
        'Jeju Island Nature Tour 4-Day, 3-Night Package',
        'Busan Coastal Adventure 5-Day, 4-Night Package',
        'Korean Traditional Culture Experience 7-Day Tour',
        'DMZ and Seoul City Tour 3-Day Package',
        'Winter Ski Resort Package 5-Day, 4-Night',
        'Korean Food and Market Tour 4-Day Package',
        'K-Pop and K-Drama Location Tour 6-Day Package',
        'Temple Stay and Mountain Hiking 5-Day Tour',
        'Gyeongju Historical Sites 4-Day Package'
    ];

    const statuses = ['pending', 'under_review', 'approved', 'rejected'];
    const sampleData = [];

    // Generate 12 applications for each status (48 total)
    statuses.forEach((status, statusIndex) => {
        for (let i = 0; i < 12; i++) {
            const appDate = new Date(2025, 3 + statusIndex, 1 + i);
            const depDate = new Date(2025, 4 + statusIndex, 15 + i);

            sampleData.push({
                visaId: `VISA${statusIndex}${String(i + 1).padStart(3, '0')}`,
                applicationId: `APP${statusIndex}${String(i + 1).padStart(3, '0')}`,
                applicantName: applicants[i % applicants.length],
                applicationDate: appDate.toISOString().split('T')[0],
                departureDate: depDate.toISOString().split('T')[0],
                packageName: packages[i % packages.length],
                applicationStatus: status
            });
        }
    });

    return sampleData;
}

//   
function setupStatusFilter() {
    const tabLinks = document.querySelectorAll('.tab-type2 .btn-tab2');
    const panels = {
        pending: document.getElementById('inadequateDocuments'),
        under_review: document.getElementById('duringExamination'),
        approved: document.getElementById('completionIssuance'),
        rejected: document.getElementById('rebellion')
    };
    
    tabLinks.forEach((a) => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = a.getAttribute('data-target');
            const map = {
                inadequateDocuments: 'pending',
                duringExamination: 'under_review',
                completionIssuance: 'approved',
                rebellion: 'rejected'
            };
            currentStatus = map[targetId] || 'pending';

            //  active
            tabLinks.forEach(x => x.classList.remove('active'));
            a.classList.add('active');

            //  active
            Object.values(panels).forEach(p => p && p.classList.remove('active'));
            const panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');

            //       
            renderPanel(currentStatus);
        });
    });
}

function normalizeStatus(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'pending') return 'pending';
    if (s === 'under_review') return 'under_review';
    if (s === 'approved') return 'approved';
    if (s === 'rejected') return 'rejected';
    return 'pending';
}

function pickLatest(items) {
    if (!Array.isArray(items) || items.length === 0) return null;
    const sorted = [...items].sort((a, b) => {
        const ad = new Date(a.applicationDate || 0).getTime();
        const bd = new Date(b.applicationDate || 0).getTime();
        return bd - ad;
    });
    return sorted[0] || null;
}

function panelMeta(statusKey) {
    const map = {
        pending: {
            panelId: 'inadequateDocuments',
            title: 'Incomplete documents',
            btnClass: 'btn primary lg mt12',
            btnText: 'Submit Documents',
            detail: (id) => `visa-detail-inadequate.html?id=${encodeURIComponent(id)}`
        },
        under_review: {
            panelId: 'duringExamination',
            title: 'Under review',
            btnClass: 'btn line active lg mt12',
            btnText: 'Document Verification',
            detail: (id) => `visa-detail-examination.html?id=${encodeURIComponent(id)}`
        },
        approved: {
            panelId: 'completionIssuance',
            title: 'Issuance completed',
            btnClass: 'btn primary lg ico1 mt12',
            btnText: 'Visa file download',
            detail: (id) => `visa-detail-completion.php?id=${encodeURIComponent(id)}`
        },
        rejected: {
            panelId: 'rebellion',
            title: 'Returned',
            btnClass: 'btn primary lg mt12',
            btnText: 'Submit Again',
            detail: (id) => `visa-detail-rebellion.html?id=${encodeURIComponent(id)}`
        }
    };
    return map[statusKey] || map.pending;
}

function renderAllPanels() {
    renderPanel('pending');
    renderPanel('under_review');
    renderPanel('approved');
    renderPanel('rejected');

    //  active   currentStatus (  )
    const activeTab = document.querySelector('.tab-type2 .btn-tab2.active');
    const targetId = activeTab?.getAttribute('data-target') || 'inadequateDocuments';
    const map = {
        inadequateDocuments: 'pending',
        duringExamination: 'under_review',
        completionIssuance: 'approved',
        rebellion: 'rejected'
    };
    currentStatus = map[targetId] || 'pending';
}

function renderPanel(statusKey) {
    const meta = panelMeta(statusKey);
    const panel = document.getElementById(meta.panelId);
    if (!panel) return;

    const items = visaApplications.filter(v => normalizeStatus(v.applicationStatus) === statusKey);
    if (!items.length) {
        panel.innerHTML = `
            <div class="text fz16 fw600 lh24 black12">${meta.title}</div>
            <div class="mt12 text fz14 fw400 lh22 gray6b">No visa applications.</div>
        `;
        return;
    }

    //     (:     )
    const sorted = [...items].sort((a, b) => {
        const ad = new Date(a.applicationDate || a.createdAt || 0).getTime();
        const bd = new Date(b.applicationDate || b.createdAt || 0).getTime();
        return bd - ad;
    });

    panel.innerHTML = sorted.map((v, idx) => {
        const applicationDate = v.applicationDate ? String(v.applicationDate) : '';
        const departureDate = v.departureDate ? String(v.departureDate) : '';
        const applicantName = v.applicantName || '';
        const productName = v.packageName || '';
        const visaId = v.visaId ?? v.applicationId ?? '';
        const visaType = v.visaType || '';
        const visaTypeDisplay = visaType === 'group' ? 'Group Visa' : visaType === 'individual' ? 'Individual Visa' : visaType || 'N/A';
        return `
            <div class="visa-card ${idx > 0 ? 'mt16' : ''}" data-visa-id="${escapeHtml(String(visaId))}" style="cursor:pointer;">
                <div class="text fz16 fw600 lh24 black12">${meta.title}</div>
                <ul class="mt12">
                    <li class="align gap10">
                        <div class="text fz14 fw400 lh22 gray6b">Applicant name</div>
                        <p class="text fz14 fw500 lh22 black12">${escapeHtml(applicantName || 'N/A')}</p>
                    </li>
                    <li class="align gap10">
                        <div class="text fz14 fw400 lh22 gray6b">Visa Type</div>
                        <p class="text fz14 fw500 lh22 black12">${escapeHtml(visaTypeDisplay)}</p>
                    </li>
                    <li class="align gap10">
                        <div class="text fz14 fw400 lh22 gray6b">Application date</div>
                        <p class="text fz14 fw500 lh22 black12">${escapeHtml(applicationDate || 'N/A')}</p>
                    </li>
                    <li class="align gap10">
                        <div class="text fz14 fw400 lh22 gray6b nowrap">Product Name</div>
                        <p class="text fz14 fw500 lh22 black12 ellipsis1">${escapeHtml(productName || 'N/A')}</p>
                    </li>
                    <li class="align gap10">
                        <div class="text fz14 fw400 lh22 gray6b">Departure date</div>
                        <p class="text fz14 fw500 lh22 black12">${escapeHtml(departureDate || 'N/A')}</p>
                    </li>
                </ul>
                <button class="${meta.btnClass}" type="button" data-visa-id="${escapeHtml(String(visaId))}">${meta.btnText}</button>
            </div>
        `;
    }).join('');

    const host = panel;

    host.querySelectorAll('[data-visa-id]').forEach((el) => {
        el.addEventListener('click', (e) => {
            const id = el.getAttribute('data-visa-id');
            if (!id) return;
            // /    
            location.href = meta.detail(id);
        });
    });
}

//   
function updateStatusCounts(statusCounts) {
    const tabLinks = document.querySelectorAll('.tab-type2 .btn-tab2');
    if (tabLinks.length < 4) return;

    const pendingCount = Number(statusCounts.pending || 0);
    const reviewCount = Number(statusCounts.under_review || 0);
    const approvedCount = Number(statusCounts.approved || 0);
    const rejectedCount = Number(statusCounts.rejected || 0);

    const counts = [pendingCount, reviewCount, approvedCount, rejectedCount];
    tabLinks.forEach((a, idx) => {
        const span = a.querySelector('span');
        if (span) span.textContent = String(counts[idx] ?? 0);
    });
}

function showEmptyState(message) {
    //     active   
    renderPanel(currentStatus);
    const meta = panelMeta(currentStatus);
    const panel = document.getElementById(meta.panelId);
    if (!panel) return;
    panel.innerHTML = `
        <div class="text fz16 fw600 lh24 black12">${meta.title}</div>
        <div class="mt12 text fz14 fw400 lh22 gray6b">${escapeHtml(message || 'No visa applications.')}</div>
    `;
}

//   
function showLoadingState() {
    const meta = panelMeta(currentStatus);
    const panel = document.getElementById(meta.panelId);
    if (!panel) return;
    panel.innerHTML = `
        <div class="text fz16 fw600 lh24 black12">${meta.title}</div>
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">Loading...</div>
        </div>
    `;
}

//   
function hideLoadingState() {
    const loadingState = document.querySelector('.loading-state');
    if (loadingState) {
        loadingState.remove();
    }
}