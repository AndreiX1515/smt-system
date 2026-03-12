// 여권 사진 셀을 "업로드 버튼 상태"로 바꾸는 함수
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
  
// 여권 사진 셀을 "파일 있음 + X 버튼" 상태로 바꾸는 함수
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
  
// X 버튼 눌렀을 때: 업로드 버튼 상태로 전환
function deletePassportFile(button) {
  const td = button.closest('td');
  if (!td) return;
  renderPassportUploadCell(td);
}
  
// 업로드에서 파일을 선택했을 때: 다시 파일 있음 상태로 전환
function onPassportUpload(input) {
  if (!input.files || !input.files.length) {
    // 파일 선택 취소한 경우: 그대로 업로드 버튼 상태 유지
    return;
  }

  const td = input.closest('td');
  if (!td) return;

  const fileName = input.files[0].name;
  renderPassportFileCell(td, fileName);
}


// preview 포함 버전 - 업로드 버튼 상태로 만들기
function renderPassportUploadCell_withPreview(box) {
  // box = .upload-box div
  box.innerHTML = `
    <label class="inputFile passport-upload">
      <input type="file" accept="image/*" onchange="onPassportUpload_withPreview(this)">
      <button type="button" class="jw-button typeE" style="width: 216px;" onclick="triggerPassportInput(this)">
        <img src="../image/upload.svg" alt="">
        <span data-lan-eng="Image upload">Image upload</span>
      </button>
    </label>
  `;
}

// preview 포함 버전 - 버튼 → 파일 input.click()
function triggerPassportInput(btn) {
  const label = btn.closest('label.inputFile');
  if (!label) return;
  const input = label.querySelector('input[type="file"]');
  if (input) input.click();
}

// preview 포함 버전 - 파일 있음 상태로 만들기
function renderPassportFileCell_withPreview(box, fileName) {
  const displayName = fileName || 'Image';

  box.innerHTML = `
    <input id="file-passport" type="file" accept="image/*">
    <label for="file-passport" class="thumb" aria-label="preview"></label>
    <div class="upload-meta">
      <div class="file-title">${displayName}</div>
      <div class="file-info">jpg, 328KB</div>
      <div class="file-controller">
        <button type="button" class="btn-icon" aria-label="다운로드">
          <img src="../image/button-download.svg" alt="">
        </button>
        <button type="button" class="btn-icon" aria-label="삭제" onclick="deletePassportFile_withPreview(this)">
          <img src="../image/button-close2.svg" alt="">
        </button>
      </div>
    </div>
  `;
}

// preview 포함 버전 - X 버튼 → 업로드 상태로 회복
function deletePassportFile_withPreview(button) {
  const box = button.closest('.upload-box');
  if (!box) return;
  renderPassportUploadCell_withPreview(box);
}

// preview 포함 버전 - 파일 선택 시 → 파일 있음 상태로 전환
function onPassportUpload_withPreview(input) {
  if (!input.files || !input.files.length) return;

  const box = input.closest('.upload-box');
  if (!box) return;

  const fileName = input.files[0].name;
  renderPassportFileCell_withPreview(box, fileName);
}