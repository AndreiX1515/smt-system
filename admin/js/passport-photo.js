// ============================================
// Passport photo upload for customer-register.html
// ============================================
function handlePassportUpload(input) {
  if (!input.files || !input.files.length) return;

  const file = input.files[0];
  const fileName = file.name;
  const ext = (fileName.split('.').pop() || '').toLowerCase();
  const sizeKB = Math.round(file.size / 1024);
  const sizeText = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + 'MB' : sizeKB + 'KB';

  // Hide upload container, show preview container
  const uploadContainer = document.getElementById('passportUploadContainer');
  const previewContainer = document.getElementById('passportPreviewContainer');

  if (uploadContainer) uploadContainer.style.display = 'none';
  if (previewContainer) previewContainer.style.display = '';

  // Update file info
  const fileNameEl = document.getElementById('passportFileName');
  const fileInfoEl = document.getElementById('passportFileInfo');
  const thumbEl = document.getElementById('passportPreview');

  if (fileNameEl) fileNameEl.textContent = '이미지';
  if (fileInfoEl) fileInfoEl.textContent = `${ext}, ${sizeText}`;

  // Show image preview
  if (thumbEl && file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      thumbEl.style.backgroundImage = `url('${e.target.result}')`;
      thumbEl.style.backgroundSize = 'cover';
      thumbEl.style.backgroundPosition = 'center';
    };
    reader.readAsDataURL(file);
  }
}

function resetPassportUpload() {
  // Show upload container, hide preview container
  const uploadContainer = document.getElementById('passportUploadContainer');
  const previewContainer = document.getElementById('passportPreviewContainer');
  const fileInput = document.getElementById('file-passport');
  const thumbEl = document.getElementById('passportPreview');

  if (uploadContainer) uploadContainer.style.display = '';
  if (previewContainer) previewContainer.style.display = 'none';

  // Reset file input
  if (fileInput) fileInput.value = '';

  // Clear preview
  if (thumbEl) {
    thumbEl.style.backgroundImage = '';
  }
}

// ============================================
// Legacy functions for table-based passport upload
// ============================================

//    "  "
function renderPassportUploadCell(td) {
    td.innerHTML = `
      <div class="cell">
        <label class="inputFile passport-upload">
          <input type="file" accept="image/*" onchange="onPassportUpload(this)">
          <button type="button" class="jw-button typeE" style="width: 216px;">
            <img src="../image/upload.svg" alt="">
            <span data-lan-eng="Image upload">Image upload</span>
          </button>
        </label>
      </div>
    `;
}
  
//    "  + X "   
function renderPassportFileCell(td, fileName) {
  const displayName = fileName || 'Passport photo';

  td.innerHTML = `
    <div class="cell">
      <div class="field-row jw-center">
        <div class="jw-center jw-gap10">
          <img src="../image/file.svg" alt="">
          <span data-lan-eng="Passport photo"">Passport photo</span>
        </div>
        <div class="jw-center jw-gap10">
          <i></i>
          <button type="button" class="jw-button typeF">
            <img src="../image/buttun-download.svg" alt="">
          </button>
          <button type="button" class="jw-button typeF" onclick="deletePassportFile(this)">
            <img src="../image/button-close2.svg" alt="">
          </button>
        </div>
      </div>
    </div>
  `;
}
  
// X   :    
function deletePassportFile(button) {
  const td = button.closest('td');
  if (!td) return;
  renderPassportUploadCell(td);
}
  
//    :     
function onPassportUpload(input) {
  if (!input.files || !input.files.length) {
    //    :     
    return;
  }

  const td = input.closest('td');
  if (!td) return;

  const fileName = input.files[0].name;
  renderPassportFileCell(td, fileName);
}


// preview   -    
function renderPassportUploadCell_withPreview(box, inputId = 'file-passport') {
  // box = .upload-box div
  const id = String(inputId || 'file-passport');
  box.dataset.inputId = id;
  box.innerHTML = `
    <label class="inputFile passport-upload">
      <input id="${id}" type="file" accept="image/*" onchange="onPassportUpload_withPreview(this)">
      <button type="button" class="jw-button typeE" style="width: 216px;" onclick="triggerPassportInput(this)">
        <img src="../image/upload.svg" alt="">
        <span data-lan-eng="Image upload">Image upload</span>
      </button>
    </label>
  `;
}

// preview   -  →  input.click()
function triggerPassportInput(btn) {
  const label = btn.closest('label.inputFile');
  if (!label) return;
  const input = label.querySelector('input[type="file"]');
  if (input) input.click();
}

// preview   -    
function renderPassportFileCell_withPreview(box, fileName, inputEl) {
  const displayName = fileName || 'Image';
  const inputId = (inputEl && inputEl.id) ? inputEl.id : (box.dataset.inputId || 'file-passport');
  box.dataset.inputId = inputId;

  // IMPORTANT:  input  (files )      .
  // innerHTML  input  files .
  const existingInput = inputEl || box.querySelector('input[type="file"]');
  if (existingInput) {
    existingInput.id = inputId;
    existingInput.setAttribute('accept', 'image/*');
    existingInput.onchange = function () { onPassportUpload_withPreview(existingInput); };
  }

  // clear box and rebuild with DOM ops (preserve input)
  while (box.firstChild) box.removeChild(box.firstChild);

  if (existingInput) box.appendChild(existingInput);

  const thumb = document.createElement('label');
  thumb.className = 'thumb';
  thumb.setAttribute('for', inputId);
  thumb.setAttribute('aria-label', 'preview');
  box.appendChild(thumb);

  const meta = document.createElement('div');
  meta.className = 'upload-meta';
  meta.innerHTML = `
    <div class="file-title">${displayName}</div>
    <div class="file-info"></div>
    <div class="file-controller">
      <button type="button" class="btn-icon" aria-label="" disabled style="opacity:.4; pointer-events:none;">
        <img src="../image/button-download.svg" alt="">
      </button>
      <button type="button" class="btn-icon" aria-label="" onclick="deletePassportFile_withPreview(this)">
        <img src="../image/button-close2.svg" alt="">
      </button>
    </div>
  `;
  box.appendChild(meta);
}

// preview   - X  →   
function deletePassportFile_withPreview(button) {
  const box = button.closest('.upload-box');
  if (!box) return;
  const inputId = box.dataset.inputId || 'file-passport';
  renderPassportUploadCell_withPreview(box, inputId);
}

// preview   -    →    
function onPassportUpload_withPreview(input) {
  if (!input.files || !input.files.length) return;

  const box = input.closest('.upload-box');
  if (!box) return;

  const fileName = input.files[0].name;
  box.dataset.inputId = input.id || (box.dataset.inputId || 'file-passport');
  renderPassportFileCell_withPreview(box, fileName, input);

  // preview render
  try {
    const file = input.files[0];
    const thumb = box.querySelector('label.thumb');
    const infoEl = box.querySelector('.upload-meta .file-info');
    if (infoEl && file) {
      const sizeKB = Math.round(file.size / 1024);
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      infoEl.textContent = `${ext}, ${sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + 'MB' : sizeKB + 'KB'}`;
    }
    if (thumb && file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        thumb.style.backgroundImage = `url('${e.target.result}')`;
        thumb.style.backgroundSize = 'cover';
        thumb.style.backgroundPosition = 'center';
      };
      reader.readAsDataURL(file);
    }
  } catch (_) {}
}