//  JavaScript

// DOM    
document.addEventListener('DOMContentLoaded', function() {
    initializeProductManagement();
    setupEventListeners();
});

//  
function initializeProductManagement() {
    console.log('  ');
    loadProducts();
}

//   
function setupEventListeners() {
    //  
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            filterProducts();
        });
    }

    //  
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            filterProducts();
        });
    }

    //  
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            filterProducts();
        });
    }

    //   
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            clearFilters();
        });
    }
}

//   
function loadProducts() {
    //     PHP      
    filterProducts();
}

//  
function filterProducts() {
    const searchValue = document.getElementById('search').value.toLowerCase();
    const categoryValue = document.getElementById('categoryFilter').value;
    const statusValue = document.getElementById('statusFilter').value;

    const tableBody = document.getElementById('productTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    let visibleCount = 0;

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const packageName = row.querySelector('.package-name');

        if (!packageName) continue;

        const packageNameText = packageName.textContent.toLowerCase();
        const category = row.getAttribute('data-category');
        const status = row.getAttribute('data-status');

        let showRow = true;

        //  
        if (searchValue && !packageNameText.includes(searchValue)) {
            showRow = false;
        }

        //  
        if (categoryValue !== 'All' && category !== categoryValue) {
            showRow = false;
        }

        //  
        if (statusValue !== 'All' && status !== statusValue) {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
        if (showRow) visibleCount++;
    }

    //     
    if (visibleCount === 0 && rows.length > 0) {
        const existingMessage = document.querySelector('.no-results-message');
        if (!existingMessage) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-message';
            noResultsRow.innerHTML = '<td colspan="11" class="text-center">  .</td>';
            tableBody.appendChild(noResultsRow);
        }
    } else {
        const existingMessage = document.querySelector('.no-results-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }
}

//  
function clearFilters() {
    document.getElementById('search').value = '';
    document.getElementById('categoryFilter').value = 'All';
    document.getElementById('statusFilter').value = 'All';
    filterProducts();
}

//   
function saveProduct() {
    const form = document.getElementById('addProductForm');
    const formData = new FormData(form);

    // isActive   
    formData.set('isActive', document.getElementById('isActive').checked ? 1 : 0);

    // FormData JSON 
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // : test_admin  
    fetch('../../backend/api/packages.php?test_admin=super_admin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('  .');
            //  
            const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
            modal.hide();
            //  
            location.reload();
        } else {
            alert(': ' + (result.message || '  .'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('    .');
    });
}

//    
function editProduct(packageId) {
    // API    (: test_admin  )
    fetch(`../../backend/api/packages.php?id=${packageId}&test_admin=super_admin`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const product = data.data;

            //    
            document.getElementById('editPackageId').value = product.packageId;
            document.getElementById('editPackageName').value = product.packageName;
            document.getElementById('editPackageCategory').value = product.packageCategory;
            document.getElementById('editPackageType').value = product.packageType || 'standard';
            document.getElementById('editPackagePrice').value = product.packagePrice;
            document.getElementById('editPackageDuration').value = product.packageDuration;
            document.getElementById('editMinParticipants').value = product.minParticipants || 1;
            document.getElementById('editMaxParticipants').value = product.maxParticipants || 50;
            document.getElementById('editDescription').value = product.description || '';
            document.getElementById('editPackageImage').value = product.packageImage || '';
            document.getElementById('editDifficulty').value = product.difficulty || 'easy';
            document.getElementById('editIsActive').checked = product.isActive == 1;

            //  
            const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
            modal.show();
        } else {
            alert('    .');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('     .');
    });
}

//   
function updateProduct() {
    const form = document.getElementById('editProductForm');
    const formData = new FormData(form);

    // isActive   
    formData.set('isActive', document.getElementById('editIsActive').checked ? 1 : 0);

    const packageId = document.getElementById('editPackageId').value;

    // FormData JSON 
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // : test_admin  
    fetch(`../../backend/api/packages.php?id=${packageId}&test_admin=super_admin`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('  .');
            //  
            const modal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
            modal.hide();
            //  
            location.reload();
        } else {
            alert(': ' + (result.message || '  .'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('    .');
    });
}

//  
function deleteProduct(packageId) {
    if (!confirm('   ?')) {
        return;
    }

    // : test_admin  
    fetch(`../../backend/api/packages.php?id=${packageId}&test_admin=super_admin`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('  .');
            //  
            location.reload();
        } else {
            alert(': ' + (result.message || '  .'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('    .');
    });
}

//  
function viewProduct(packageId) {
    //    
    window.location.href = `../../user/product-detail.php?id=${packageId}`;
}
