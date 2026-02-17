/**
 * Google Apps Script - 시트 Extensions > Apps Script에 붙여넣기
 *
 * 시트에서 편집 발생 시 해당 행의 packageId, date, R, APP 값을
 * 서버 webhook으로 전송하여 DB capacity를 실시간 업데이트합니다.
 *
 * 설정 후 트리거 등록 필요:
 * 1. Extensions > Apps Script에서 이 코드 붙여넣기
 * 2. 트리거 탭 > + Add Trigger > onEdit > From spreadsheet > On edit
 */

// ─── 설정 ───
var WEBHOOK_URL = 'https://smt-escape.com/backend/api/sheets-webhook.php';
var WEBHOOK_SECRET = 'smt_sheets_webhook_2026_secret';
var COL_PACKAGE_ID = 29;   // AC열 (packageId)
var COL_DATE = 3;          // C열 (출발일)
var COL_R = 7;             // G열 (잔여좌석)
var COL_APP = 26;          // Z열 (앱 예약 인원)
var HEADER_ROWS = 5;       // 데이터는 6행부터

function onEdit(e) {
  var sheet = e.source.getActiveSheet();
  var row = e.range.getRow();

  // 헤더 행 무시
  if (row <= HEADER_ROWS) return;

  // 해당 행의 packageId 확인 (없으면 skip)
  var packageId = sheet.getRange(row, COL_PACKAGE_ID).getValue();
  if (!packageId) return;

  // 해당 행의 date, R(수식 결과), APP 값 읽기
  var dateVal = sheet.getRange(row, COL_DATE).getValue();
  var rVal = sheet.getRange(row, COL_R).getValue();
  var appVal = sheet.getRange(row, COL_APP).getValue();

  // 날짜 변환 (Date 객체 → YYYY-MM-DD)
  if (dateVal instanceof Date) {
    dateVal = Utilities.formatDate(dateVal, 'Asia/Manila', 'yyyy-MM-dd');
  }

  // Webhook 호출
  try {
    UrlFetchApp.fetch(WEBHOOK_URL, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify({
        secret: WEBHOOK_SECRET,
        packageId: Number(packageId),
        date: String(dateVal),
        r: Number(rVal) || 0,
        app: Number(appVal) || 0
      }),
      muteHttpExceptions: true
    });
  } catch (err) {
    Logger.log('Webhook failed: ' + err);
  }
}
