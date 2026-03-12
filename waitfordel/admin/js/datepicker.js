$(function () {

  /** --------------------------------------------------
   * 계약 기간 (Range Picker)
   --------------------------------------------------**/
  const $contractInput = $('#contract-period');

  if ($contractInput.length) {
    $contractInput.daterangepicker({
      autoUpdateInput: false,
      locale: {
        format: 'YYYY-MM-DD',
        separator: ' ~ ',
        applyLabel: '확인',
        cancelLabel: '취소',
        daysOfWeek: ['일','월','화','수','목','금','토'],
        monthNames: [
          '1월','2월','3월','4월','5월','6월',
          '7월','8월','9월','10월','11월','12월'
        ],
        firstDay: 0
      }
    });

    $contractInput.on('apply.daterangepicker', function (ev, picker) {
      $(this).val(
        picker.startDate.format('YYYY-MM-DD') +
        ' ~ ' +
        picker.endDate.format('YYYY-MM-DD')
      );
    });

    $contractInput.on('cancel.daterangepicker', function () {
      $(this).val('');
    });
  }


  /** --------------------------------------------------
   * 여행 시작일 (Single Date Picker)
   --------------------------------------------------**/
  const $travelInput = $('#travelStartDate');

  if ($travelInput.length) {
    $travelInput.daterangepicker({
      singleDatePicker: true,
      autoUpdateInput: false,
      locale: {
        format: 'YYYY-MM-DD',
        applyLabel: '확인',
        cancelLabel: '취소'
      }
    });

    $travelInput.on('apply.daterangepicker', function (ev, picker) {
      $(this).val(picker.startDate.format('YYYY-MM-DD'));
    });

    $travelInput.on('cancel.daterangepicker', function () {
      $(this).val('');
    });
  }

    /** --------------------------------------------------
   * 선금 입금 기한 (Single Date Picker)
   --------------------------------------------------**/
   const $depositInput  = $('#deposit_due');

   if ($depositInput .length) {
     $depositInput .daterangepicker({
       singleDatePicker: true,
       autoUpdateInput: false,
       locale: {
         format: 'YYYY-MM-DD',
         applyLabel: '확인',
         cancelLabel: '취소'
       }
     });
 
     $depositInput .on('apply.daterangepicker', function (ev, picker) {
       $(this).val(picker.startDate.format('YYYY-MM-DD'));
     });
 
     $depositInput .on('cancel.daterangepicker', function () {
       $(this).val('');
     });
   }
 

  /** --------------------------------------------------
   * 공통: 달력 아이콘 클릭 → data-target에 지정된 input 열기
   --------------------------------------------------**/
  $(document).on('click', '.btn-icon.calendar', function () {
    const targetSelector = $(this).data('target');   // 예: "#contract-period" 또는 "#travelStartDate"
    if (!targetSelector) return;

    const $target = $(targetSelector);
    if ($target.length) {
      $target.trigger('click').focus();
    }
  });

});
