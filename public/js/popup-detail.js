init({
	headerUrl: '../inc/header.html',
	navUrl: '../inc/nav_super.html'
});

let popupId = null;
let uploadedImageUrl = '';
let exposurePeriodPicker = null;

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

		// Initialize daterangepicker
		initExposurePeriodPicker();

		const urlParams = new URLSearchParams(window.location.search);
		popupId = urlParams.get('id') || urlParams.get('popupId');

		if (popupId) {
			await loadPopupDetail();
		}
	} catch (error) {
		console.error('Session check error:', error);
		window.location.href = '../index.html';
		return;
	}
});

function initExposurePeriodPicker() {
	const $input = $('#exposurePeriod');
	const $wrap = $('#exposurePeriodWrap');

	$input.daterangepicker({
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
		}
	});

	$input.on('apply.daterangepicker', function(ev, picker) {
		$(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
	});

	$input.on('cancel.daterangepicker', function() {
		$(this).val('');
	});

	// Wrapper click opens daterangepicker
	$wrap.on('click', function() {
		$input.trigger('click');
	});

	exposurePeriodPicker = $input.data('daterangepicker');
}

async function loadPopupDetail() {
	try {
		const response = await fetch('../backend/api/super-api.php?action=getPopupDetail&id=' + popupId, {
			credentials: 'same-origin'
		});

		if (!response.ok) throw new Error('HTTP ' + response.status);

		const result = await response.json();

		if (result.success && result.data) {
			const popup = result.data.popup;

			document.getElementById('popupTitle').value = popup.title || '';
			document.getElementById('link').value = popup.link || '';

			// Set exposure period daterangepicker
			if (popup.startDate && popup.endDate && exposurePeriodPicker) {
				const startMoment = moment(popup.startDate, 'YYYY-MM-DD');
				const endMoment = moment(popup.endDate, 'YYYY-MM-DD');
				exposurePeriodPicker.setStartDate(startMoment);
				exposurePeriodPicker.setEndDate(endMoment);
				$('#exposurePeriod').val(popup.startDate + ' ~ ' + popup.endDate);
			}

			const registrationDate = document.getElementById('registrationDate');
			if (popup.createdAt || popup.registrationDate) {
				registrationDate.value = popup.createdAt || popup.registrationDate || '';
			}

			const statusInput = document.getElementById('popupStatus');
			if (popup.status) {
				statusInput.value = popup.status.toLowerCase() === 'active' ? 'Activate' : 'Inactivate';
			}

			uploadedImageUrl = popup.imageUrl || '';
			renderPopupImagePreview(uploadedImageUrl);
		}
	} catch (error) {
		console.error('Error loading popup detail:', error);
	}
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

function handleImageDelete() {
	uploadedImageUrl = '';
	try {
		const inp = document.getElementById('imageInput');
		if (inp) inp.value = '';
	} catch (_) {}
	renderPopupImagePreview('');
}

function renderPopupImagePreview(url) {
	const img = document.getElementById('popupImagePreviewImg');
	const delBtn = document.getElementById('popupImageDeleteBtn');
	const uploadLabel = document.getElementById('uploadLabel');
	if (!img || !delBtn) return;
	const u = String(url || '').trim();
	if (!u) {
		img.src = '';
		img.style.display = 'none';
		delBtn.style.display = 'none';
		if (uploadLabel) uploadLabel.style.display = '';
		return;
	}
	img.src = u;
	img.style.display = '';
	delBtn.style.display = 'flex';
	if (uploadLabel) uploadLabel.style.display = 'none';
}

async function handleSave() {
	try {
		const title = document.getElementById('popupTitle').value.trim();
		if (!title) {
			alert('Please enter a popup title.');
			return;
		}

		// Parse exposure period
		const exposurePeriodVal = document.getElementById('exposurePeriod').value.trim();
		let startDate = '';
		let endDate = '';
		if (exposurePeriodVal && exposurePeriodVal.includes(' ~ ')) {
			const parts = exposurePeriodVal.split(' ~ ');
			startDate = parts[0] || '';
			endDate = parts[1] || '';
		}

		const formData = {
			action: 'updatePopup',
			popupId: popupId,
			title: title,
			startDate: startDate,
			endDate: endDate,
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
			console.error('popup update failed', { status: response.status, rawText: rawText, result: result });
			alert(('Failed to save popup. (HTTP ' + response.status + ') ' + (result.message || '')).trim());
			return;
		}

		if (result && result.success) {
			alert('Popup saved.');
			window.location.href = 'popup-management.html';
			return;
		}

		console.error('popup update returned success=false', { rawText: rawText, result: result });
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
