// Product Info Page JavaScript

let currentCategory = 'season';
// 서브카테고리는 code 기준으로 관리합니다. ('all'이면 전체)
let currentSubCategory = 'all';
let currentSort = 'popular';
let currentView = 'grid';
let currentPage = 1;
let packagesPerPage = 12;
let allPackages = [];
let filteredPackages = [];
let appliedFilters = {
    priceMin: 0,
    priceMax: 10000000, // 1000만원으로 상한선 증가
    duration: [],
    features: [],
    search: ''
};

let categoryMeta = null; // backend/api/categories.php에서 가져온 메타

// B2B/B2C 사용자 구분
function isB2BUser() {
    try {
        const at = String(localStorage.getItem('accountType') || '').toLowerCase();
        return at === 'agent' || at === 'admin';
    } catch (_) {}
    return false;
}

function __isPlaceholderImage(src) {
    const s = String(src || '').trim();
    if (!s) return false;
    if (s.startsWith('@img_')) return true;
    if (s.includes('/images/@img_')) return true;
    if (s.includes('images/@img_')) return true;
    return false;
}

function __firstImageFromProductImagesField(v) {
    try {
        if (Array.isArray(v)) return v[0] || '';
        const raw = String(v || '').trim();
        if (!raw) return '';
        if (raw.startsWith('[') || raw.startsWith('{')) {
            const decoded = JSON.parse(raw);
            if (Array.isArray(decoded)) return decoded[0] || '';
            if (decoded && typeof decoded === 'object') {
                const lang = getLangFromUrlOrStorage();
                const pick = decoded[lang] ?? decoded.en ?? decoded.ko ?? null;
                if (Array.isArray(pick)) return pick[0] || '';
                if (typeof pick === 'string') return pick;
            }
        }
        return raw;
    } catch (_) {
        return '';
    }
}

function __pickPackageThumbnail(pkg) {
    const candidates = [
        pkg?.imageUrl,
        (Array.isArray(pkg?.images) ? pkg.images[0] : null),
        pkg?.thumbnail_image,
        pkg?.thumbnailImage,
        pkg?.thumbnail,
        __firstImageFromProductImagesField(pkg?.product_images),
        __firstImageFromProductImagesField(pkg?.productImages),
        pkg?.mainImage,
        pkg?.packageImageUrl,
        pkg?.packageImage,
    ].map(v => (v == null ? '' : String(v).trim())).filter(Boolean);

    const nonPlaceholders = candidates.filter(c => !__isPlaceholderImage(c));
    return (nonPlaceholders[0] || candidates[0] || '');
}

// 초기화
document.addEventListener('DOMContentLoaded', function() {
    // 진입 시 저장된 언어(en/tl)를 즉시 적용 (한글 노출 방지)
    try {
        const lang = (typeof getCurrentLanguage === 'function')
            ? getCurrentLanguage()
            : (localStorage.getItem('selectedLanguage') || 'en');
        document.documentElement.lang = (lang === 'tl' ? 'tl' : 'en');
        if (typeof loadServerTexts === 'function') {
            // i18n.js가 defer라 로드된 뒤여야 함
            loadServerTexts(document.documentElement.lang).then(() => {
                if (typeof updatePageLanguage === 'function') updatePageLanguage(document.documentElement.lang);
            }).catch(() => {});
        } else if (typeof updatePageLanguage === 'function') {
            updatePageLanguage(document.documentElement.lang);
        }
    } catch (_) {}
    initializePage();
    setupEventListeners();
});

// 페이지 초기화
async function initializePage() {
    await ensureCategoryMeta();
    renderMainCategoryTabs();
    currentCategory = getCategoryFromUrl();
    // URL category가 관리자 대분류에 없으면 첫 번째 대분류로 보정
    const validCodes = Array.isArray(categoryMeta) ? categoryMeta.map(c => c.code).filter(Boolean) : [];
    if (validCodes.length && !validCodes.includes(currentCategory)) {
        currentCategory = validCodes[0];
        updateUrlParams({ category: currentCategory, subCategory: null });
    }
    activateTab(currentCategory);
    setupTabClickEvents();
    setSubCategories(currentCategory);
    
    // API 객체가 로드될 때까지 잠시 대기
    setTimeout(() => {
        loadPackages();
    }, 100);
}

async function ensureCategoryMeta() {
    try {
        if (categoryMeta) return categoryMeta;
        if (typeof api !== 'undefined' && api.getCategories) {
            const res = await api.getCategories();
            if (res?.success && res?.data?.mainCategories) {
                categoryMeta = res.data.mainCategories;
                return categoryMeta;
            }
        }
        // fallback: 직접 fetch
        const r = await fetch('../backend/api/categories.php', { credentials: 'same-origin' });
        const j = await r.json().catch(() => ({}));
        if (j?.success && j?.data?.mainCategories) {
            categoryMeta = j.data.mainCategories;
            return categoryMeta;
        }
    } catch (e) {
        console.warn('Failed to load category meta:', e);
    }
    categoryMeta = [];
    return categoryMeta;
}

function getLangFromUrlOrStorage() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en'));
}

function updateUrlParams({ category, subCategory } = {}) {
    const url = new URL(window.location.href);
    if (category) url.searchParams.set('category', category);
    const lang = getLangFromUrlOrStorage();
    if (lang) url.searchParams.set('lang', lang);

    // subCategory는 칩 선택 상태와 동일하게 유지(없으면 제거)
    if (subCategory === null || subCategory === undefined || subCategory === 'all') {
        url.searchParams.delete('subCategory');
        url.searchParams.delete('sub_category');
    } else {
        url.searchParams.set('subCategory', subCategory);
    }
    window.history.replaceState({}, '', url.toString());
}

function renderMainCategoryTabs() {
    const tabsHost = document.getElementById('mainCategoryTabs');
    if (!tabsHost) return;

    const lang = getLangFromUrlOrStorage();
    const mains = Array.isArray(categoryMeta) ? categoryMeta : [];

    // 관리자 카테고리가 없으면 최소 fallback (UI 깨짐 방지)
    const source = mains.length ? mains : [
        { code: 'season', name: 'Season' },
        { code: 'region', name: 'Region' },
        { code: 'theme', name: 'Theme' },
        { code: 'private', name: 'Private' },
        { code: 'daytrip', name: 'Day trip' }
    ];

    tabsHost.innerHTML = source
        .map((m) => {
            const code = String(m?.code || '').trim();
            if (!code) return '';
            const name = String(m?.name || code);
            return `<li><a class="btn-tab2" href="?category=${encodeURIComponent(code)}&lang=${encodeURIComponent(lang)}" data-category="${escapeHtml(code)}" role="tab" aria-selected="false">${escapeHtml(name)}</a></li>`;
        })
        .filter(Boolean)
        .join('');

    // 헤더 타이틀도 현재 카테고리명으로 세팅(초기에는 activateTab 이후에 다시 세팅됨)
    updateHeaderTitleFromCategory(getCategoryFromUrl());
}

function updateHeaderTitleFromCategory(categoryCode) {
    const titleEl = document.querySelector('.header-type2 .title');
    if (!titleEl) return;
    // SMT 수정: 타이틀 영역은 대분류에 따라 변경되지 않고 'Package product'로 고정(요구사항)
    titleEl.textContent = 'Package product';
}

// 이벤트 리스너 설정
function setupEventListeners() {
    // 검색 입력 이벤트
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(performSearch, 300));
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }

    // 가격 범위 슬라이더
    const minPriceSlider = document.getElementById('minPrice');
    const maxPriceSlider = document.getElementById('maxPrice');
    
    if (minPriceSlider && maxPriceSlider) {
        minPriceSlider.addEventListener('input', updatePriceLabels);
        maxPriceSlider.addEventListener('input', updatePriceLabels);
    }
}

// URL에서 category 파라미터 가져오기
function getCategoryFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('category') || 'season';
}

// 탭 활성화
function activateTab(category) {
    const tabs = document.querySelectorAll('.btn-tab2');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        tab.setAttribute('aria-selected', 'false');
        if (tab.getAttribute('data-category') === category) {
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
        }
    });
    updateHeaderTitleFromCategory(category);
}

// 탭 클릭 이벤트 설정
function setupTabClickEvents() {
    const tabs = document.querySelectorAll('.btn-tab2');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const category = this.getAttribute('data-category');
            
            if (category !== currentCategory) {
                currentCategory = category;
                currentPage = 1;
                
                // URL 변경 없이 카테고리 변경
                activateTab(category);
                setSubCategories(category);
                updateUrlParams({ category, subCategory: 'all' });
                loadPackages();
            }
        });
    });
}

// 서브 카테고리 설정
function setSubCategories(category) {
    const container = document.getElementById('subCategories');
    if (!container) return;

    // 관리자 등록 카테고리(product_main/sub_categories) 기반으로 동적 구성
    const main = Array.isArray(categoryMeta) ? categoryMeta.find(m => m.code === category) : null;
    const subs = Array.isArray(main?.subCategories) ? main.subCategories : [];
    const categories = [{ label: 'All', code: 'all' }].concat(
        subs.map(s => ({ label: s.name || s.code, code: s.code || '' })).filter(x => x.code)
    );
    
    container.innerHTML = categories.map(cat => `
        <li>
            <button class="chips type2 ${cat.code === 'all' ? 'active' : ''} btn-category"
                    type="button" 
                    role="tab" 
                    aria-selected="${cat.code === 'all'}"
                    onclick="selectSubCategory('${cat.code}', this)">${cat.label}</button>
        </li>
    `).join('');

    currentSubCategory = 'all';
}

// 서브 카테고리 선택
function selectSubCategory(subCategoryCode, el) {
    const buttons = document.querySelectorAll('.btn-category');
    buttons.forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });
    
    const target = el || (typeof event !== 'undefined' ? event.target : null);
    if (target) {
        target.classList.add('active');
        target.setAttribute('aria-selected', 'true');
    }
    
    currentSubCategory = subCategoryCode || 'all';
    currentPage = 1;
    updateUrlParams({ category: currentCategory, subCategory: currentSubCategory });
    
    // 서브카테고리가 변경되면 새로운 데이터를 로드
    loadPackages();
}

// 패키지 로드
async function loadPackages() {
    showLoading(true);
    
    try {
        // API 객체 확인
        if (typeof api === 'undefined') {
            console.error('API object is not defined');
            throw new Error('API object is not available');
        }
        
        // 새로운 API 사용
        const params = {
            category: currentCategory,
            limit: packagesPerPage * 2,
            // 오늘 기준 구매 가능한 상품만 노출(요구사항)
            purchasableOnly: 1
        };
        
        // 서브카테고리가 'All'이 아닌 경우에만 추가
        if (currentSubCategory !== 'all') {
            params.subCategory = currentSubCategory; // code 전달
        }
        
        console.log('Calling API with params:', params);
        console.log('API object:', api);
        const result = await api.getPackages(params);
        console.log('API Response:', result);
        
        let packages = [];
        if (result && result.success && result.data) {
            // home_packages.php는 result.data.packages를 반환
            if (Array.isArray(result.data.packages)) {
                packages = result.data.packages;
            } else if (Array.isArray(result.data.products)) {
                packages = result.data.products;
            } else if (Array.isArray(result.data)) {
                packages = result.data;
            }
            console.log('Packages extracted:', packages, 'Type:', typeof packages, 'IsArray:', Array.isArray(packages));
        }
        
        // 더미(fallback) 데이터 노출 금지: 관리자 등록 상품과 불일치함
        if (!Array.isArray(packages)) packages = [];
        
        // 안전장치: packages가 배열인지 다시 한번 확인
        if (!Array.isArray(packages)) {
            console.error('Packages is still not an array:', packages);
            packages = [];
        }
        
        // 이미지 경로 처리 (요구사항 id 49: 등록된 썸네일 우선)
        // 이중 가격 시스템: B2B/B2C에 따라 다른 가격 표시
        const b2bUser = isB2BUser();

        allPackages = packages.map(pkg => {
            // B2B 가격 또는 B2C 가격 선택
            // package_available_dates.price (nextFlightPrice)는 "해당 날짜의 패키지 총 가격"
            // 합산하지 않고, 날짜 가격이 있으면 그것 사용, 없으면 기본 가격 사용
            let displayPrice = 0;
            const datePrice = Number(pkg.nextFlightPrice || 0);

            if (b2bUser) {
                // B2B 가격 (에이전트): b2bPrice 우선
                const b2bBase = Number(pkg.b2bPrice || pkg.b2b_price || pkg.packagePrice || pkg.price || 0);
                // 날짜 가격이 있으면 B2B 비율 적용, 없으면 b2bBase 사용
                if (datePrice > 0 && pkg.packagePrice > 0 && b2bBase > 0) {
                    const discountRatio = b2bBase / Number(pkg.packagePrice);
                    displayPrice = Math.round(datePrice * discountRatio);
                } else {
                    displayPrice = b2bBase;
                }
            } else {
                // B2C 가격 (일반 사용자): 날짜 가격 우선, 없으면 packagePrice
                displayPrice = datePrice > 0 ? datePrice : Number(pkg.packagePrice || pkg.price || 0);
            }

            return {
                ...pkg,
                imageUrl: processImageUrl(__pickPackageThumbnail(pkg)),
                searchText: `${pkg.productName || pkg.packageName || pkg.name} ${pkg.destination || ''} ${pkg.category || pkg.packageCategory || ''}`.toLowerCase(),
                formattedPrice: `₱${new Intl.NumberFormat('en-US').format(displayPrice)}~`,
                packagePrice: displayPrice,
                packageName: pkg.productName || pkg.packageName || pkg.name,
                packageId: pkg.productId || pkg.packageId || pkg.id,
                hasAvailableSeats: pkg.isConfirmed || pkg.hasAvailableSeats || false,
                subCategory: pkg.subCategory || null
            };
        });
        
        console.log('All packages processed:', allPackages);
        filterAndRenderPackages();
        
    } catch (error) {
        console.error('패키지 로드 오류:', error);
        // 에러 발생 시 빈 목록 처리
        allPackages = [];
        filterAndRenderPackages();
        showError('패키지를 불러오는데 실패했습니다.');
    } finally {
        showLoading(false);
    }
}

// 이미지 URL 처리
function processImageUrl(imageSrc) {
    if (!imageSrc) return '';
    
    // 완전한 URL인 경우 그대로 반환
    if (imageSrc.startsWith('http://') || imageSrc.startsWith('https://')) {
        return imageSrc;
    }
    
    // 상대 경로 처리
    if (imageSrc.startsWith('../')) {
        return imageSrc.replace('../images/', 'https://www.smt-escape.com/images/');
    }
    
    // 절대 경로인 경우: 현재 도메인 기준으로 붙인다(운영/스테이징 혼선 방지)
    if (imageSrc.startsWith('/')) {
        const origin = (typeof window !== 'undefined' && window.location && window.location.origin)
            ? window.location.origin
            : 'https://www.smt-escape.com';
        return `${origin}${imageSrc}`;
    }
    
    // 파일명만 있는 경우: 업로드 이미지(/uploads/products/*)가 대부분
    if (!imageSrc.includes('/') && !imageSrc.startsWith('@')) {
        const origin = (typeof window !== 'undefined' && window.location && window.location.origin)
            ? window.location.origin
            : 'https://www.smt-escape.com';
        return `${origin}/uploads/products/${imageSrc}`;
    }

    // 파일명만 있는 경우(기본 이미지 리소스 등)
    return `https://www.smt-escape.com/images/${imageSrc}`;
}

// 필터링 및 렌더링
function filterAndRenderPackages() {
    console.log('Filtering packages. Total packages:', allPackages.length);
    filteredPackages = allPackages.filter(pkg => {
        console.log('Filtering package:', pkg.packageName, 'subCategory:', pkg.subCategory, 'currentSubCategory:', currentSubCategory);
        
        // 서브 카테고리 필터
        if (currentSubCategory !== 'all') {
            const expectedSubCategory = currentSubCategory; // code
            console.log('Expected subCategory(code):', expectedSubCategory, 'Package subCategory:', pkg.subCategory);

            if (expectedSubCategory && pkg.subCategory !== expectedSubCategory) {
                console.log('Package filtered out by subCategory:', pkg.packageName);
                return false;
            }
        }
        
        // 검색 필터
        if (appliedFilters.search) {
            if (!pkg.searchText.includes(appliedFilters.search.toLowerCase())) {
                console.log('Package filtered out by search:', pkg.packageName);
                return false;
            }
        }
        
        // 가격 필터 (비활성화 - UI가 없으므로)
        // if (pkg.packagePrice < appliedFilters.priceMin || pkg.packagePrice > appliedFilters.priceMax) {
        //     console.log('Package filtered out by price:', pkg.packageName, 'price:', pkg.packagePrice);
        //     return false;
        // }
        
        console.log('Package passed all filters:', pkg.packageName);
        return true;
    });
    
    // 정렬
    sortPackages();
    
    // 렌더링
    console.log('About to render packages. filteredPackages:', filteredPackages);
    renderPackages();
    updatePackageCount();
}

// 패키지 정렬
function sortPackages() {
    filteredPackages.sort((a, b) => {
        switch (currentSort) {
            case 'price-low':
                return a.packagePrice - b.packagePrice;
            case 'price-high':
                return b.packagePrice - a.packagePrice;
            case 'newest':
                return b.packageId - a.packageId;
            case 'popular':
            default:
                return 0; // 기본 순서 유지
        }
    });
}

// 패키지 렌더링
function renderPackages() {
    console.log('Rendering packages. Filtered packages:', filteredPackages.length);
    const container = document.getElementById('packageList');
    const startIndex = 0;
    const endIndex = currentPage * packagesPerPage;
    const packagesToShow = filteredPackages.slice(startIndex, endIndex);
    
    console.log('Packages to show:', packagesToShow.length);
    
    if (packagesToShow.length === 0) {
        console.log('No packages to show, showing empty state');
        showEmptyState(true);
        container.innerHTML = '';
        return;
    }
    
    showEmptyState(false);
    
    const cardsHtml = packagesToShow.map(pkg => createPackageCard(pkg)).join('');
    console.log('Generated HTML length:', cardsHtml.length);
    container.innerHTML = cardsHtml;
    
    // Load More 버튼 표시/숨김
    updateLoadMoreButton();
}

// 패키지 카드 생성 - Figma 디자인 스타일
function createPackageCard(pkg) {
    console.log('Creating package card for:', pkg.packageName, pkg);

    // 가격 표시 처리 - formattedPrice 우선 사용 (이중 가격 시스템에서 B2B/B2C에 맞게 설정됨)
    let priceDisplay = pkg.formattedPrice || `₱${new Intl.NumberFormat('en-US').format(pkg.packagePrice || 0)}~`;
    
    // "+" 기호가 포함된 경우 두 가격을 합산
    if (priceDisplay.includes('+')) {
        try {
            // "₱5,000 + ₱5,000" 형식에서 숫자 추출
            const priceParts = priceDisplay.split('+');
            let totalPrice = 0;
            
            priceParts.forEach(part => {
                // 각 부분에서 숫자만 추출 (콤마 제거)
                const numbers = part.replace(/[^\d.]/g, '');
                if (numbers) {
                    totalPrice += parseFloat(numbers.replace(/,/g, ''));
                }
            });
            
            // 합산된 가격을 포맷팅
            if (totalPrice > 0) {
                priceDisplay = `₱${new Intl.NumberFormat('en-US').format(totalPrice)}~`;
            }
        } catch (error) {
            console.error('Error calculating total price:', error);
            // 오류 발생 시 원본 가격 사용
        }
    }
    
    const hasAvailableSeats = pkg.hasAvailableSeats || pkg.isConfirmed || (pkg.flights && pkg.flights.some(f => f.availSeats > 0));
    const confirmedText = typeof getText === 'function' ? getText('confirmed') : '출발 확정';
    
    const pid = Number(pkg.packageId || pkg.id);
    const lang = (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'ko'));
    const onClick = (Number.isFinite(pid) && pid > 0)
        ? `location.href='product-detail.php?id=${pid}&lang=${encodeURIComponent(lang)}'`
        : `alert('상품 정보를 준비 중입니다.');`;

    const imgHtml = pkg.imageUrl
        ? `<img src="${pkg.imageUrl}" alt="${pkg.packageName}" loading="lazy" onerror="this.style.display='none'" style="border-radius: 8px;">`
        : `<div class="no-image" style="width:100%;aspect-ratio: 16/9;background:#f2f2f2;border-radius: 8px;"></div>`;

    const cardHtml = `
        <li onclick="${onClick}" role="gridcell" tabindex="0" 
            onkeypress="handleCardKeyPress(event, '${Number.isFinite(pid) ? pid : ''}', '${escapeHtml(String(lang))}')">
            <article class="card-type1">
                ${imgHtml}
                <div class="card-content">
                    <div class="info">${pkg.packageName}</div>
                    <div class="price">${priceDisplay}</div>
                    ${hasAvailableSeats ? `<div class="label confirmed-label">${confirmedText}</div>` : ''}
                </div>
            </article>
        </li>
    `;
    
    console.log('Generated card HTML:', cardHtml);
    return cardHtml;
}

// 카드 키보드 이벤트 처리
function handleCardKeyPress(event, packageId, lang) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        const pid = Number(packageId);
        const l = lang || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'ko'));
        if (Number.isFinite(pid) && pid > 0) {
            location.href = `product-detail.php?id=${pid}&lang=${encodeURIComponent(l)}`;
        }
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// 검색 토글
function toggleSearch() {
    const searchContainer = document.getElementById('searchContainer');
    const isActive = searchContainer.classList.contains('active');
    
    searchContainer.classList.toggle('active');
    searchContainer.setAttribute('aria-hidden', isActive ? 'true' : 'false');
    
    if (!isActive) {
        const searchInput = document.getElementById('searchInput');
        setTimeout(() => searchInput.focus(), 300);
    }
}

// 검색 수행
function performSearch() {
    const searchInput = document.getElementById('searchInput');
    appliedFilters.search = searchInput.value.trim();
    currentPage = 1;
    filterAndRenderPackages();
}

// 정렬 변경
function handleSortChange() {
    const sortSelect = document.getElementById('sortSelect');
    currentSort = sortSelect.value;
    currentPage = 1;
    filterAndRenderPackages();
}

// 뷰 타입 변경
function setViewType(viewType) {
    const gridBtn = document.querySelector('.btn-view.grid');
    const listBtn = document.querySelector('.btn-view.list');
    const packageList = document.getElementById('packageList');
    
    if (viewType === 'list') {
        gridBtn.classList.remove('active');
        gridBtn.setAttribute('aria-pressed', 'false');
        listBtn.classList.add('active');
        listBtn.setAttribute('aria-pressed', 'true');
        packageList.classList.add('list-view');
    } else {
        listBtn.classList.remove('active');
        listBtn.setAttribute('aria-pressed', 'false');
        gridBtn.classList.add('active');
        gridBtn.setAttribute('aria-pressed', 'true');
        packageList.classList.remove('list-view');
    }
    
    currentView = viewType;
}

// 더 보기
function loadMorePackages() {
    currentPage++;
    renderPackages();
}

// 필터 모달 토글
function toggleFilterModal() {
    const modal = document.getElementById('filterModal');
    const isVisible = modal.style.display !== 'none';
    
    modal.style.display = isVisible ? 'none' : 'flex';
    
    if (!isVisible) {
        updateFilterModal();
    }
}

// 필터 모달 업데이트
function updateFilterModal() {
    document.getElementById('minPrice').value = appliedFilters.priceMin;
    document.getElementById('maxPrice').value = appliedFilters.priceMax;
    updatePriceLabels();
}

// 가격 라벨 업데이트
function updatePriceLabels() {
    const minPrice = document.getElementById('minPrice').value;
    const maxPrice = document.getElementById('maxPrice').value;
    const minLabel = document.getElementById('minPriceLabel');
    const maxLabel = document.getElementById('maxPriceLabel');
    
    if (minLabel) minLabel.textContent = `₱${new Intl.NumberFormat('ko-KR').format(minPrice)}`;
    if (maxLabel) maxLabel.textContent = `₱${new Intl.NumberFormat('ko-KR').format(maxPrice)}`;
}

// 필터 적용
function applyFilters() {
    appliedFilters.priceMin = parseInt(document.getElementById('minPrice').value);
    appliedFilters.priceMax = parseInt(document.getElementById('maxPrice').value);
    
    // 기간 필터
    appliedFilters.duration = Array.from(document.querySelectorAll('input[name="duration"]:checked'))
        .map(cb => cb.value);
    
    // 포함사항 필터
    appliedFilters.features = Array.from(document.querySelectorAll('input[name="features"]:checked'))
        .map(cb => cb.value);
    
    currentPage = 1;
    filterAndRenderPackages();
    toggleFilterModal();
    showNotification('필터가 적용되었습니다.', 'success');
}

// 필터 초기화
function resetFilters() {
    appliedFilters = {
        priceMin: 0,
        priceMax: 10000000, // 1000만원으로 상한선 증가
        duration: [],
        features: [],
        search: appliedFilters.search // 검색어는 유지
    };
    
    updateFilterModal();
    
    // 체크박스 초기화
    document.querySelectorAll('input[name="duration"], input[name="features"]')
        .forEach(cb => cb.checked = false);
    
    currentPage = 1;
    filterAndRenderPackages();
    showNotification('필터가 초기화되었습니다.', 'info');
}

// 패키지 개수 업데이트
function updatePackageCount() {
    const countElement = document.getElementById('packageCount');
    if (countElement) {
        countElement.textContent = filteredPackages.length;
    }
}

// Load More 버튼 업데이트
function updateLoadMoreButton() {
    const loadMoreSection = document.getElementById('loadMoreSection');
    const remainingCount = document.getElementById('remainingCount');
    const totalShown = currentPage * packagesPerPage;
    const remaining = Math.max(0, filteredPackages.length - totalShown);
    
    if (remaining > 0) {
        loadMoreSection.style.display = 'block';
        if (remainingCount) {
            remainingCount.textContent = remaining;
        }
    } else {
        loadMoreSection.style.display = 'none';
    }
}

// 로딩 상태 표시
function showLoading(show) {
    const loadingSection = document.getElementById('loadingSection');
    if (loadingSection) {
        loadingSection.style.display = show ? 'block' : 'none';
        loadingSection.setAttribute('aria-hidden', show ? 'false' : 'true');
    }
}

// 빈 상태 표시
function showEmptyState(show) {
    console.log('showEmptyState called with:', show);
    const emptyState = document.getElementById('emptyState');
    if (emptyState) {
        emptyState.style.display = show ? 'block' : 'none';
        console.log('Empty state display set to:', emptyState.style.display);
    } else {
        console.error('Empty state element not found');
    }
}

// 오류 표시
function showError(message) {
    showNotification(message, 'error');
}

// 알림 표시
function showNotification(message, type = 'info') {
    // 기존 알림 제거
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        max-width: 300px;
    `;
    
    const colors = {
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#FF9800',
        error: '#F44336'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// Fallback 데이터
function getFallbackPackages(category) {
    const fallbackPackages = {
        season: [
            { packageId: 1, packageName: '서울 벚꽃 명소 완전정복 6일 투어', packagePrice: 3200000, packageImage: '@img_banner1.jpg', destination: '서울', duration_days: 6, subCategory: 'spring' },
            { packageId: 2, packageName: '제주도 자연과 모험의 완벽한 조화 5일 패키지', packagePrice: 2800000, packageImage: '@img_card1.jpg', destination: '제주', duration_days: 5, subCategory: 'summer' },
            { packageId: 3, packageName: '부산 가을 미식투어와 문화체험 4일 패키지', packagePrice: 1800000, packageImage: '@img_card2.jpg', destination: '부산', duration_days: 4, subCategory: 'autumn' },
            { packageId: 4, packageName: '강원도 겨울 스키 & 온천 힐링 5일 패키지', packagePrice: 3500000, packageImage: '@img_travel.jpg', destination: '강원도', duration_days: 5, subCategory: 'winter' }
        ],
        region: [
            { packageId: 21, packageName: '전주·군산 레트로 투어 3박 4일', packagePrice: 220000, packageImage: '@img_card2.jpg', destination: '전주', duration_days: 3 },
            { packageId: 22, packageName: '경주 역사문화 탐방 2박 3일', packagePrice: 160000, packageImage: '@img_banner1.jpg', destination: '경주', duration_days: 2 },
            { packageId: 23, packageName: '강원도 동해안 드라이브 4박 5일', packagePrice: 320000, packageImage: '@img_card1.jpg', destination: '강원도', duration_days: 4 },
            { packageId: 24, packageName: '제주도 올레길 트레킹 5박 6일', packagePrice: 420000, packageImage: '@img_travel.jpg', destination: '제주', duration_days: 5 }
        ],
        theme: [
            { packageId: 31, packageName: '한국 전통문화 체험 3박 4일', packagePrice: 290000, packageImage: '@img_card1.jpg', destination: '서울', duration_days: 3 },
            { packageId: 32, packageName: 'K-POP 성지순례 2박 3일', packagePrice: 250000, packageImage: '@img_card2.jpg', destination: '서울', duration_days: 2 },
            { packageId: 33, packageName: '템플스테이 힐링여행 2박 3일', packagePrice: 180000, packageImage: '@img_banner1.jpg', destination: '강원도', duration_days: 2 }
        ],
        private: [
            { packageId: 41, packageName: '프리미엄 서울 프라이빗 투어', packagePrice: 850000, packageImage: '@img_banner1.jpg', destination: '서울', duration_days: 3 },
            { packageId: 42, packageName: 'VIP 제주도 럭셔리 패키지', packagePrice: 1200000, packageImage: '@img_card1.jpg', destination: '제주', duration_days: 4 }
        ],
        daytrip: [
            { packageId: 12, packageName: '남이섬 당일치기 투어', packagePrice: 120000, packageImage: '@img_banner1.jpg', destination: '가평', duration_days: 1 }
        ]
    };
    
    return fallbackPackages[category] || [];
}

// 유틸리티: 디바운스
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}