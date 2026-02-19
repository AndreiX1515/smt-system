/**
 * Option Management - 옵션 카테고리 관리 (대분류/중분류/소분류)
 */

const API_URL = '../backend/api/super-api.php';
let selectedMainCategory = '';

// 페이지 초기화
document.addEventListener('DOMContentLoaded', function() {
    loadMainCategories();
});

/**
 * 대분류 목록 로드
 */
async function loadMainCategories() {
    try {
        const response = await fetch(`${API_URL}?action=getMainOptionCategories`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load main categories');
        }

        const mainCategories = result.data?.mainCategories || [];
        const select = document.getElementById('mainCategorySelect');

        select.innerHTML = '<option value="">-- Select Main Category --</option>';
        mainCategories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            select.appendChild(option);
        });

        // 이전에 선택한 대분류가 있으면 다시 선택
        if (selectedMainCategory && mainCategories.includes(selectedMainCategory)) {
            select.value = selectedMainCategory;
            loadSubCategories();
        }

    } catch (error) {
        console.error('Error loading main categories:', error);
        alert('Failed to load main categories: ' + error.message);
    }
}

/**
 * 중분류/소분류 로드 (대분류 선택 시)
 */
async function loadSubCategories() {
    const select = document.getElementById('mainCategorySelect');
    selectedMainCategory = select.value;

    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const container = document.getElementById('categoriesContainer');

    const copyMainCategoryBtn = document.getElementById('copyMainCategoryBtn');

    if (!selectedMainCategory) {
        addCategoryBtn.disabled = true;
        if (copyMainCategoryBtn) copyMainCategoryBtn.disabled = true;
        container.innerHTML = `
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5">
                    <path d="M19 11H5M19 11C20.1046 11 21 11.8954 21 13V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V13C3 11.8954 3.89543 11 5 11M19 11V9C19 7.89543 18.1046 7 17 7M5 11V9C5 7.89543 5.89543 7 7 7M7 7V5C7 3.89543 7.89543 3 9 3H15C16.1046 3 17 3.89543 17 5V7M7 7H17"/>
                </svg>
                <p data-lan-eng="Select a main category to manage options">Select a main category to manage options</p>
            </div>
        `;
        return;
    }

    addCategoryBtn.disabled = false;
    if (copyMainCategoryBtn) copyMainCategoryBtn.disabled = false;

    try {
        const response = await fetch(`${API_URL}?action=getAirlineOptions&mainCategory=${encodeURIComponent(selectedMainCategory)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load categories');
        }

        renderCategories(result.data?.categories || []);

    } catch (error) {
        console.error('Error loading categories:', error);
        alert('Failed to load categories: ' + error.message);
    }
}

// 하위 호환성
function loadOptionCategories() { loadMainCategories(); }
function loadAirlineOptions() { loadSubCategories(); }

/**
 * 카테고리 및 옵션 렌더링
 */
function renderCategories(categories) {
    const container = document.getElementById('categoriesContainer');

    if (categories.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5">
                    <path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <p data-lan-eng="No categories yet. Click '+ Add Sub Category' to create one.">No categories yet. Click '+ Add Sub Category' to create one.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = categories.map(cat => `
        <div class="category-card ${cat.is_active ? '' : 'inactive'}" draggable="true" data-category-id="${cat.category_id}">
            <div class="category-header">
                <div style="display:flex;align-items:center;">
                    <span class="drag-handle js-cat-drag-handle" title="Drag to reorder">&#9776;</span>
                    <span class="category-title">${escapeHtml(cat.category_name)}</span>
                    ${cat.category_name_en ? `<span class="category-title-en">(${escapeHtml(cat.category_name_en)})</span>` : ''}
                    <span class="status-badge ${cat.is_active ? 'active' : 'inactive'}">${cat.is_active ? 'Active' : 'Inactive'}</span>
                </div>
                <div class="category-actions">
                    <button class="btn-icon edit" onclick="openEditCategoryModal(${cat.category_id}, '${escapeHtml(cat.category_name)}', '${escapeHtml(cat.category_name_en || '')}')" title="Edit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-icon delete" onclick="deleteCategory(${cat.category_id})" title="Delete">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="category-body">
                <div class="option-list">
                    ${(cat.options || []).map(opt => `
                        <div class="option-item ${opt.is_active ? '' : 'inactive'}" draggable="true" data-option-id="${opt.option_id}" data-category-id="${cat.category_id}">
                            <div class="option-info">
                                <span class="drag-handle js-opt-drag-handle" title="Drag to reorder">&#9776;</span>
                                <span class="option-name">${escapeHtml(opt.option_name)}</span>
                                ${opt.option_name_en ? `<span class="option-name-en">(${escapeHtml(opt.option_name_en)})</span>` : ''}
                            </div>
                            <div class="option-info">
                                <span class="option-price">PHP ${formatNumber(opt.price)}</span>
                                <div class="option-actions">
                                    <button class="btn-icon edit" onclick="openEditOptionModal(${opt.option_id}, ${cat.category_id}, '${escapeHtml(opt.option_name)}', '${escapeHtml(opt.option_name_en || '')}', ${opt.price})" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <button class="btn-icon delete" onclick="deleteOption(${opt.option_id})" title="Delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <button class="add-option-btn" onclick="openAddOptionModal(${cat.category_id})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span data-lan-eng="Add Option">Add Option</span>
                </button>
            </div>
        </div>
    `).join('');
}

// ============ Main Category Modal ============

function openAddMainCategoryModal() {
    document.getElementById('mainCategoryModalTitle').textContent = 'Add Main Category';
    document.getElementById('editMainCategoryOldName').value = '';
    document.getElementById('mainCategoryName').value = '';
    document.getElementById('mainCategoryModal').style.display = 'flex';
}

function openEditMainCategoryModal(oldName) {
    document.getElementById('mainCategoryModalTitle').textContent = 'Edit Main Category';
    document.getElementById('editMainCategoryOldName').value = oldName;
    document.getElementById('mainCategoryName').value = oldName;
    document.getElementById('mainCategoryModal').style.display = 'flex';
}

function closeMainCategoryModal() {
    document.getElementById('mainCategoryModal').style.display = 'none';
}

async function saveMainCategory() {
    const oldName = document.getElementById('editMainCategoryOldName').value;
    const newName = document.getElementById('mainCategoryName').value.trim();

    if (!newName) {
        alert('Please enter a main category name.');
        return;
    }

    try {
        const formData = new FormData();
        if (oldName) {
            formData.append('action', 'updateMainOptionCategory');
            formData.append('oldName', oldName);
            formData.append('newName', newName);
        } else {
            formData.append('action', 'createMainOptionCategory');
            formData.append('mainCategory', newName);
        }

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save main category');
        }

        closeMainCategoryModal();
        selectedMainCategory = newName;
        loadMainCategories();

    } catch (error) {
        console.error('Error saving main category:', error);
        alert('Failed to save main category: ' + error.message);
    }
}

async function deleteMainCategory(mainCategory) {
    if (!confirm(`Are you sure you want to delete "${mainCategory}"? All sub-categories and options will also be deleted.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'deleteMainOptionCategory');
        formData.append('mainCategory', mainCategory);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to delete main category');
        }

        selectedMainCategory = '';
        loadMainCategories();

    } catch (error) {
        console.error('Error deleting main category:', error);
        alert('Failed to delete main category: ' + error.message);
    }
}

// ============ Copy Main Category Modal ============

function openCopyMainCategoryModal() {
    if (!selectedMainCategory) {
        alert('Please select a main category first.');
        return;
    }
    document.getElementById('copySourceCategory').value = selectedMainCategory;
    document.getElementById('copyNewName').value = '';
    document.getElementById('copyMainCategoryModal').style.display = 'flex';
}

function closeCopyMainCategoryModal() {
    document.getElementById('copyMainCategoryModal').style.display = 'none';
}

async function copyMainCategory() {
    const sourceCategory = document.getElementById('copySourceCategory').value;
    const newName = document.getElementById('copyNewName').value.trim();

    if (!newName) {
        alert('Please enter a new category name.');
        return;
    }

    if (sourceCategory === newName) {
        alert('New name must be different from source.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'copyMainOptionCategory');
        formData.append('sourceCategory', sourceCategory);
        formData.append('newName', newName);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to copy category');
        }

        alert(`Successfully copied to "${newName}"!\n(${result.data?.copiedCategories || 0} categories, ${result.data?.copiedOptions || 0} options)`);
        closeCopyMainCategoryModal();
        selectedMainCategory = newName;
        loadMainCategories();

    } catch (error) {
        console.error('Error copying main category:', error);
        alert('Failed to copy category: ' + error.message);
    }
}

// ============ Category Modal ============

function openAddCategoryModal() {
    document.getElementById('categoryModalTitle').textContent = 'Add Sub Category';
    document.getElementById('editCategoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryNameEn').value = '';
    document.getElementById('categoryModal').style.display = 'flex';
}

function openEditCategoryModal(categoryId, name, nameEn) {
    document.getElementById('categoryModalTitle').textContent = 'Edit Sub Category';
    document.getElementById('editCategoryId').value = categoryId;
    document.getElementById('categoryName').value = name;
    document.getElementById('categoryNameEn').value = nameEn;
    document.getElementById('categoryModal').style.display = 'flex';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

async function saveCategory() {
    const categoryId = document.getElementById('editCategoryId').value;
    const categoryName = document.getElementById('categoryName').value.trim();
    const categoryNameEn = document.getElementById('categoryNameEn').value.trim();

    if (!categoryName) {
        alert('Please enter a category name.');
        return;
    }

    try {
        const formData = new FormData();
        if (categoryId) {
            formData.append('action', 'updateOptionCategory');
            formData.append('categoryId', categoryId);
        } else {
            formData.append('action', 'createOptionCategory');
            formData.append('mainCategory', selectedMainCategory);
        }
        formData.append('categoryName', categoryName);
        formData.append('categoryNameEn', categoryNameEn);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save category');
        }

        closeCategoryModal();
        loadSubCategories();

    } catch (error) {
        console.error('Error saving category:', error);
        alert('Failed to save category: ' + error.message);
    }
}

async function deleteCategory(categoryId) {
    if (!confirm('Are you sure you want to delete this category? All options in this category will also be deleted.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'deleteOptionCategory');
        formData.append('categoryId', categoryId);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to delete category');
        }

        loadSubCategories();

    } catch (error) {
        console.error('Error deleting category:', error);
        alert('Failed to delete category: ' + error.message);
    }
}

// ============ Option Modal ============

function openAddOptionModal(categoryId) {
    document.getElementById('optionModalTitle').textContent = 'Add Option';
    document.getElementById('editOptionId').value = '';
    document.getElementById('optionCategoryId').value = categoryId;
    document.getElementById('optionName').value = '';
    document.getElementById('optionNameEn').value = '';
    document.getElementById('optionPrice').value = '0';
    document.getElementById('optionModal').style.display = 'flex';
}

function openEditOptionModal(optionId, categoryId, name, nameEn, price) {
    document.getElementById('optionModalTitle').textContent = 'Edit Option';
    document.getElementById('editOptionId').value = optionId;
    document.getElementById('optionCategoryId').value = categoryId;
    document.getElementById('optionName').value = name;
    document.getElementById('optionNameEn').value = nameEn;
    document.getElementById('optionPrice').value = price;
    document.getElementById('optionModal').style.display = 'flex';
}

function closeOptionModal() {
    document.getElementById('optionModal').style.display = 'none';
}

async function saveOption() {
    const optionId = document.getElementById('editOptionId').value;
    const categoryId = document.getElementById('optionCategoryId').value;
    const optionName = document.getElementById('optionName').value.trim();
    const optionNameEn = document.getElementById('optionNameEn').value.trim();
    const price = parseFloat(document.getElementById('optionPrice').value) || 0;

    if (!optionName) {
        alert('Please enter an option name.');
        return;
    }

    try {
        const formData = new FormData();
        if (optionId) {
            formData.append('action', 'updateAirlineOption');
            formData.append('optionId', optionId);
        } else {
            formData.append('action', 'createAirlineOption');
            formData.append('categoryId', categoryId);
        }
        formData.append('optionName', optionName);
        formData.append('optionNameEn', optionNameEn);
        formData.append('price', price);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save option');
        }

        closeOptionModal();
        loadSubCategories();

    } catch (error) {
        console.error('Error saving option:', error);
        alert('Failed to save option: ' + error.message);
    }
}

async function deleteOption(optionId) {
    if (!confirm('Are you sure you want to delete this option?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'deleteAirlineOption');
        formData.append('optionId', optionId);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to delete option');
        }

        loadSubCategories();

    } catch (error) {
        console.error('Error deleting option:', error);
        alert('Failed to delete option: ' + error.message);
    }
}

// ============ Drag & Drop Reordering ============

let draggingCategory = null;
let draggingOption = null;
let _mouseDownTarget = null;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('categoriesContainer');
    if (!container) return;

    // mousedown 시점의 실제 클릭 요소를 기록 (dragstart의 e.target은 draggable 요소 자체이므로)
    container.addEventListener('mousedown', (e) => {
        _mouseDownTarget = e.target;
    });

    container.addEventListener('dragstart', (e) => {
        // Option drag
        const optItem = e.target.closest('.option-item[data-option-id]');
        if (optItem) {
            if (!_mouseDownTarget || !_mouseDownTarget.closest('.js-opt-drag-handle')) {
                e.preventDefault();
                return;
            }
            draggingOption = optItem;
            optItem.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', 'opt:' + optItem.dataset.optionId); } catch (_) {}
            return;
        }

        // Category card drag
        const catCard = e.target.closest('.category-card[data-category-id]');
        if (catCard) {
            if (!_mouseDownTarget || !_mouseDownTarget.closest('.js-cat-drag-handle')) {
                e.preventDefault();
                return;
            }
            draggingCategory = catCard;
            catCard.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', 'cat:' + catCard.dataset.categoryId); } catch (_) {}
        }
    });

    container.addEventListener('dragover', (e) => {
        // Option dragover
        if (draggingOption) {
            const overItem = e.target.closest('.option-item[data-option-id]');
            if (!overItem || overItem === draggingOption) return;
            if (overItem.dataset.categoryId !== draggingOption.dataset.categoryId) return;
            e.preventDefault();
            const list = overItem.parentElement;
            const rect = overItem.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            list.insertBefore(draggingOption, after ? overItem.nextSibling : overItem);
            return;
        }

        // Category dragover
        if (draggingCategory) {
            const overCard = e.target.closest('.category-card[data-category-id]');
            if (!overCard || overCard === draggingCategory) return;
            e.preventDefault();
            const rect = overCard.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            container.insertBefore(draggingCategory, after ? overCard.nextSibling : overCard);
        }
    });

    container.addEventListener('dragend', async () => {
        // Option dragend
        if (draggingOption) {
            draggingOption.style.opacity = '';
            const categoryId = parseInt(draggingOption.dataset.categoryId, 10);
            const list = draggingOption.closest('.option-list');
            const ids = Array.from(list.querySelectorAll('.option-item[data-option-id]')).map(el => parseInt(el.dataset.optionId, 10));
            draggingOption = null;
            try {
                const formData = new FormData();
                formData.append('action', 'reorderAirlineOptions');
                formData.append('categoryId', categoryId);
                formData.append('order', JSON.stringify(ids));
                const response = await fetch(API_URL, { method: 'POST', body: formData, credentials: 'same-origin' });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
            } catch (err) {
                alert(err.message || 'Failed to reorder options.');
                loadSubCategories();
            }
            return;
        }

        // Category dragend
        if (draggingCategory) {
            draggingCategory.style.opacity = '';
            const ids = Array.from(container.querySelectorAll('.category-card[data-category-id]')).map(el => parseInt(el.dataset.categoryId, 10));
            draggingCategory = null;
            try {
                const formData = new FormData();
                formData.append('action', 'reorderOptionCategories');
                formData.append('mainCategory', selectedMainCategory);
                formData.append('order', JSON.stringify(ids));
                const response = await fetch(API_URL, { method: 'POST', body: formData, credentials: 'same-origin' });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
            } catch (err) {
                alert(err.message || 'Failed to reorder categories.');
                loadSubCategories();
            }
        }
    });
});

// ============ Utility Functions ============

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
    }[m]));
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num || 0);
}
