init({
	headerUrl: '../inc/header.html',
	navUrl: '../inc/nav_super.html'
});

let uploadedImageUrl = '';

document.addEventListener('DOMContentLoaded', async () => {
	try {
		const sessionResponse = await fetch('../backend/api/check-session.php', {
			credentials: 'same-origin'
		});
		const sessionData = await sessionResponse.json();

		if (!sessionData.authenticated) {
			window.location.href = '../index.html';
			return;
		}
	} catch (error) {
		console.error('Session check error:', error);
		window.location.href = '../index.html';
		return;
	}

	// Initialize daterangepicker for exposure period
	initDateRangePicker();
});

function initDateRangePicker() {
	const $exposurePeriod = $('#exposurePeriod');
	const $exposurePeriodBtn = $('#exposurePeriodBtn');

	if (!$exposurePeriod.length) return;

	$exposurePeriod.daterangepicker({
		autoUpdateInput: false,
		locale: {
			format: 'YYYY-MM-DD',
			separator: ' ~ ',
			applyLabel: 'Apply',
			cancelLabel: 'Cancel',
			fromLabel: 'From',
			toLabel: 'To',
			customRangeLabel: 'Custom',
			weekLabel: 'W',
			daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
			monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
			firstDay: 0
		},
		opens: 'center'
	});

	$exposurePeriod.on('apply.daterangepicker', function(ev, picker) {
		const startDate = picker.startDate.format('YYYY-MM-DD');
		const endDate = picker.endDate.format('YYYY-MM-DD');
		$(this).val(startDate + ' ~ ' + endDate);
		$('#startDate').val(startDate);
		$('#endDate').val(endDate);
	});

	$exposurePeriod.on('cancel.daterangepicker', function() {
		$(this).val('');
		$('#startDate').val('');
		$('#endDate').val('');
	});

	// Calendar button click opens picker
	$exposurePeriodBtn.on('click', function() {
		$exposurePeriod.trigger('click');
	});
}

function handleImageUpload(input) {
	const file = input.files[0];
	if (file) {
		const reader = new FileReader();
		reader.onload = function(e) {
			uploadedImageUrl = e.target.result || '';
			renderPopupImagePreview(uploadedImageUrl);
		};
		reader.readAsDataURL(file);
	}
}

function renderPopupImagePreview(url) {
	const img = document.getElementById('popupImagePreviewImg');
	const uploadLabel = document.getElementById('uploadLabel');
	if (!img) return;
	const u = String(url || '').trim();
	if (!u) {
		img.src = '';
		img.style.display = 'none';
		if (uploadLabel) uploadLabel.style.display = '';
		return;
	}
	img.src = u;
	img.style.display = '';
	if (uploadLabel) uploadLabel.style.display = 'none';
}

async function handleSave() {
	try {
		const title = document.getElementById('popupTitle').value.trim();
		if (!title) {
			alert('Please enter a popup title.');
			return;
		}

		const formData = {
			action: 'createPopup',
			title: title,
			startDate: document.getElementById('startDate').value,
			endDate: document.getElementById('endDate').value,
			imageUrl: uploadedImageUrl,
			link: document.getElementById('link').value.trim(),
			status: 'active'
		};

		const response = await fetch('../backend/api/super-api.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(formData),
			credentials: 'same-origin'
		});

		const rawText = await response.text();
		let result = {};
		try { result = JSON.parse(rawText); } catch (_) { result = {}; }

		if (!response.ok) {
			console.error('popup create failed', { status: response.status, rawText: rawText, result: result });
			alert(('Failed to save popup. (HTTP ' + response.status + ') ' + (result.message || '')).trim());
			return;
		}

		if (result && result.success) {
			alert('Popup registered successfully.');
			window.location.href = 'popup-management.html';
			return;
		}

		console.error('popup create returned success=false', { rawText: rawText, result: result });
		alert(('Failed to save popup. ' + (result.message || '')).trim());
	} catch (error) {
		console.error('Error saving popup:', error);
		alert('An error occurred while saving.');
	}
}

function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
