$(function () {

  /** --------------------------------------------------
   *   (Range Picker)
   --------------------------------------------------**/
  const $contractInput = $('#contract-period');

  if ($contractInput.length) {
    $contractInput.daterangepicker({
      autoUpdateInput: false,
      locale: {
        format: 'YYYY-MM-DD',
        separator: ' ~ ',
        applyLabel: 'Apply',
        cancelLabel: 'Cancel',
        daysOfWeek: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        monthNames: [
          'January','February','March','April','May','June',
          'July','August','September','October','November','December'
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
   * Exposure Period (Range Picker) - for notice-detail.html
   --------------------------------------------------**/
  const $exposurePeriodInput = $('#exposurePeriod');

  if ($exposurePeriodInput.length) {
    $exposurePeriodInput.daterangepicker({
      autoUpdateInput: false,
      locale: {
        format: 'YYYY-MM-DD',
        separator: ' ~ ',
        applyLabel: 'Apply',
        cancelLabel: 'Cancel',
        daysOfWeek: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        monthNames: [
          'January','February','March','April','May','June',
          'July','August','September','October','November','December'
        ],
        firstDay: 0
      }
    });

    $exposurePeriodInput.on('apply.daterangepicker', function (ev, picker) {
      const startDate = picker.startDate.format('YYYY-MM-DD');
      const endDate = picker.endDate.format('YYYY-MM-DD');
      $(this).val(startDate + ' ~ ' + endDate);
      // Update data attributes for the notice-detail.html to read
      $(this).attr('data-start', startDate);
      $(this).attr('data-end', endDate);
    });

    $exposurePeriodInput.on('cancel.daterangepicker', function () {
      $(this).val('');
      $(this).removeAttr('data-start');
      $(this).removeAttr('data-end');
    });
  }


  /** --------------------------------------------------
   * Date Filter (Range Picker - 하루 또는 기간 선택 가능)
   --------------------------------------------------**/
  const $travelInput = $('#travelStartDate');

  if ($travelInput.length) {
    $travelInput.daterangepicker({
      autoUpdateInput: false,
      locale: {
        format: 'YYYY-MM-DD',
        separator: ' ~ ',
        applyLabel: 'Apply',
        cancelLabel: 'Clear',
        daysOfWeek: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        monthNames: [
          'January','February','March','April','May','June',
          'July','August','September','October','November','December'
        ],
        firstDay: 0
      }
    });

    $travelInput.on('apply.daterangepicker', function (ev, picker) {
      const startDate = picker.startDate.format('YYYY-MM-DD');
      const endDate = picker.endDate.format('YYYY-MM-DD');
      // 같은 날이면 하루만 표시, 다르면 기간으로 표시
      if (startDate === endDate) {
        $(this).val(startDate);
      } else {
        $(this).val(startDate + ' ~ ' + endDate);
      }
    });

    $travelInput.on('cancel.daterangepicker', function () {
      $(this).val('');
    });
  }

    /** --------------------------------------------------
   *    (Single Date Picker)
   --------------------------------------------------**/
   const $depositInput  = $('#deposit_due');

   if ($depositInput .length) {
     $depositInput .daterangepicker({
       singleDatePicker: true,
       autoUpdateInput: false,
       locale: {
         format: 'YYYY-MM-DD',
         applyLabel: 'Apply',
         cancelLabel: 'Cancel'
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
   * :    → data-target  input
   --------------------------------------------------**/
  $(document).on('click', '.btn-icon.calendar, .calendar-trigger', function () {
    const targetSelector = $(this).data('target');   // : "#contract-period"  "#travelStartDate"
    if (!targetSelector) return;

    const $target = $(targetSelector);
    if ($target.length) {
      $target.trigger('click').focus();
    }
  });

});
