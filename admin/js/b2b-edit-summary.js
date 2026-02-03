/**
 * b2b-edit-summary.js
 * Edit Travelers 3단계 플로우 - Summary 페이지 JS
 * sessionStorage에서 데이터 로드, Submit 시에만 DB 저장
 */

let bookingId = null;
let pendingChanges = null;

document.addEventListener('DOMContentLoaded', async function() {
	// URL에서 bookingId 추출
	const urlParams = new URLSearchParams(window.location.search);
	bookingId = urlParams.get('bookingId');

	if (!bookingId) {
		alert('Booking ID is required');
		window.location.href = 'b2b-booking-list.html';
		return;
	}

	// sessionStorage에서 변경 데이터 로드
	const storedData = sessionStorage.getItem('pendingTravelerChanges');
	if (!storedData) {
		alert('No pending changes found. Please start from the booking detail page.');
		window.location.href = `b2b-booking-detail.html?id=${bookingId}`;
		return;
	}

	pendingChanges = JSON.parse(storedData);

	// bookingId 일치 확인
	if (pendingChanges.bookingId !== bookingId) {
		alert('Booking ID mismatch. Please start again from the booking detail page.');
		sessionStorage.removeItem('pendingTravelerChanges');
		window.location.href = `b2b-booking-detail.html?id=${bookingId}`;
		return;
	}

	renderSummary();
});

function renderSummary() {
	if (!pendingChanges) return;

	// Change Summary 렌더링
	renderChangeComparison();

	// Amount Breakdown 렌더링
	renderAmountBreakdown();
}

function renderChangeComparison() {
	const originalTravelers = pendingChanges.originalTravelers || [];
	const pendingTravelers = pendingChanges.travelers || [];
	const originalRooms = pendingChanges.originalRooms || [];
	const pendingRooms = pendingChanges.selectedRooms || [];

	// Before Travelers
	const beforeTravelerCount = formatTravelerCount(originalTravelers);
	document.getElementById('before-travelers').textContent = beforeTravelerCount;
	renderTravelerList('before-traveler-list', originalTravelers);

	// After Travelers
	const afterTravelerCount = formatTravelerCount(pendingTravelers);
	document.getElementById('after-travelers').textContent = afterTravelerCount;
	renderTravelerList('after-traveler-list', pendingTravelers);

	// Before Rooms
	const beforeRoomSummary = formatRoomSummary(originalRooms);
	document.getElementById('before-rooms').textContent = beforeRoomSummary || '-';
	renderRoomList('before-room-list', originalRooms);

	// After Rooms
	const afterRoomSummary = formatRoomSummary(pendingRooms);
	document.getElementById('after-rooms').textContent = afterRoomSummary || '-';
	renderRoomList('after-room-list', pendingRooms);
}

function formatTravelerCount(travelers) {
	if (!Array.isArray(travelers) || travelers.length === 0) return '0 people';

	let adults = 0, children = 0, infants = 0;
	travelers.forEach(t => {
		const type = (t.travelerType || t.type || '').toLowerCase();
		if (type === 'adult') adults++;
		else if (type === 'child') children++;
		else if (type === 'infant') infants++;
	});

	const parts = [];
	if (adults > 0) parts.push(`Adult ${adults}`);
	if (children > 0) parts.push(`Child ${children}`);
	if (infants > 0) parts.push(`Infant ${infants}`);

	return parts.length > 0 ? parts.join(', ') : `${travelers.length} people`;
}

function renderTravelerList(containerId, travelers) {
	const container = document.getElementById(containerId);
	if (!container) return;

	if (!Array.isArray(travelers) || travelers.length === 0) {
		container.innerHTML = '<div style="color: #9CA3AF; font-size: 13px;">No travelers</div>';
		return;
	}

	let html = '';
	travelers.forEach((t, index) => {
		const name = `${t.firstName || ''} ${t.lastName || ''}`.trim() || `Traveler ${index + 1}`;
		const type = (t.travelerType || t.type || 'Adult').charAt(0).toUpperCase() + (t.travelerType || t.type || 'Adult').slice(1).toLowerCase();

		html += `
			<div class="traveler-item">
				<span>${name}</span>
				<span style="color: #6B7280; font-size: 12px;">${type}</span>
			</div>
		`;
	});
	container.innerHTML = html;
}

function formatRoomSummary(rooms) {
	if (!Array.isArray(rooms) || rooms.length === 0) return '';

	const roomParts = rooms
		.filter(r => r.count > 0)
		.map(r => `${r.roomType} x${r.count}`);

	return roomParts.join(', ');
}

function renderRoomList(containerId, rooms) {
	const container = document.getElementById(containerId);
	if (!container) return;

	if (!Array.isArray(rooms) || rooms.length === 0 || rooms.every(r => !r.count || r.count === 0)) {
		container.innerHTML = '<div style="color: #9CA3AF; font-size: 13px;">No rooms selected</div>';
		return;
	}

	let html = '';
	rooms.filter(r => r.count > 0).forEach(room => {
		const price = room.roomPrice > 0 ? `+PHP ${Number(room.roomPrice).toLocaleString()}` : 'Free';
		html += `
			<div class="room-item">
				<span>${room.roomType} x${room.count}</span>
				<span style="color: #0050C8;">${price}</span>
			</div>
		`;
	});
	container.innerHTML = html;
}

function renderAmountBreakdown() {
	const listContainer = document.getElementById('breakdown-list');
	const totalEl = document.getElementById('breakdown-total');
	if (!listContainer || !totalEl) return;

	const items = [];
	let subtotal = 0;

	const travelers = pendingChanges.travelers || [];
	const selectedRooms = pendingChanges.selectedRooms || [];
	const bookingData = pendingChanges.bookingData || {};

	// Sale 할인 정보 (예약에 저장된 할인 정보)
	const saleDiscountAmount = parseFloat(bookingData.saleDiscountAmount || 0);
	const saleName = bookingData.saleName || '';

	// 가격 정보 (이미 할인 적용된 가격)
	const adultPrice = parseFloat(bookingData.adultPrice || 0);
	const childWithRoomPrice = adultPrice;
	const childNoRoomPrice = parseFloat(bookingData.childPrice || 0) || (adultPrice * 0.8);
	const infantPrice = parseFloat(bookingData.infantPrice || 0) || 10000;

	// 할인 전 원래 가격 계산
	const originalAdultPrice = saleDiscountAmount > 0 ? (adultPrice + saleDiscountAmount) : adultPrice;
	const originalChildWithRoomPrice = originalAdultPrice;

	// 인원별 계산
	let adults = 0, childrenWithRoom = 0, childrenNoRoom = 0, infants = 0;
	travelers.forEach(t => {
		const type = (t.travelerType || t.type || '').toLowerCase();
		if (type === 'adult') adults++;
		else if (type === 'child') {
			const hasRoom = parseInt(t.childRoom || 0) === 1;
			if (hasRoom) childrenWithRoom++;
			else childrenNoRoom++;
		}
		else if (type === 'infant') infants++;
	});

	// 할인 적용 대상 인원 (Adult + Child with Room)
	const discountableCount = adults + childrenWithRoom;

	// Package Price 표시 (할인 전 가격 기준)
	if (adults > 0 && originalAdultPrice > 0) {
		const amount = adults * originalAdultPrice;
		subtotal += amount;
		items.push({ label: `Adult x ${adults}`, amount });
	}
	if (childrenWithRoom > 0 && originalChildWithRoomPrice > 0) {
		const amount = childrenWithRoom * originalChildWithRoomPrice;
		subtotal += amount;
		items.push({ label: `Child (Room) x ${childrenWithRoom}`, amount });
	}
	if (childrenNoRoom > 0 && childNoRoomPrice > 0) {
		const amount = childrenNoRoom * childNoRoomPrice;
		subtotal += amount;
		items.push({ label: `Child x ${childrenNoRoom}`, amount: Math.round(amount) });
	}
	if (infants > 0) {
		const amount = infants * infantPrice;
		subtotal += amount;
		items.push({ label: `Infant x ${infants}`, amount });
	}

	// Room Options
	if (Array.isArray(selectedRooms) && selectedRooms.length > 0) {
		selectedRooms.filter(r => r.count > 0).forEach(room => {
			const roomTotal = (room.roomPrice || 0) * room.count;
			subtotal += roomTotal;
			items.push({ label: `${room.roomType} x${room.count}`, amount: roomTotal });
		});
	}

	// Flight Options (각 traveler별로 계산)
	let totalFlightOptionAmount = 0;
	travelers.forEach(t => {
		const flightOptions = t.flightOptions || [];
		const flightOptionPrices = t.flightOptionPrices || {};
		flightOptions.forEach(optId => {
			const price = parseFloat(flightOptionPrices[optId] || 0);
			totalFlightOptionAmount += price;
		});
	});
	if (totalFlightOptionAmount > 0) {
		subtotal += totalFlightOptionAmount;
		items.push({ label: `Flight Options`, amount: totalFlightOptionAmount });
	}

	// Visa Fee
	let groupVisaCount = 0, individualVisaCount = 0;
	travelers.forEach(t => {
		const visaType = String(t.visaType || '').toLowerCase();
		if (visaType === 'group') groupVisaCount++;
		else if (visaType === 'individual') individualVisaCount++;
	});
	if (groupVisaCount > 0) {
		const amount = groupVisaCount * 1500;
		subtotal += amount;
		items.push({ label: `Group Visa x ${groupVisaCount}`, amount });
	}
	if (individualVisaCount > 0) {
		const amount = individualVisaCount * 1900;
		subtotal += amount;
		items.push({ label: `Individual Visa x ${individualVisaCount}`, amount });
	}

	// Sale 할인 금액 계산 (인원당 할인액 × 할인 대상 인원수)
	const totalDiscount = saleDiscountAmount * discountableCount;

	// 최종 총액 계산 (할인 차감)
	const totalAmount = subtotal - totalDiscount;

	// Render items
	let html = '';
	if (items.length > 0) {
		html = items.map(item => `
			<div class="breakdown-item">
				<span class="breakdown-item-label">${item.label}</span>
				<span class="breakdown-item-value">PHP ${item.amount.toLocaleString()}</span>
			</div>
		`).join('');

		// 할인 정보 표시
		if (totalDiscount > 0 && saleName) {
			html += `
				<div class="breakdown-item" style="margin-top: 8px; padding: 8px 12px; background: #FEF2F2; border-radius: 6px; border: 1px solid #FECACA;">
					<span class="breakdown-item-label" style="color: #DC2626; font-weight: 600;">
						<span style="background: #DC2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 8px;">SALE</span>
						${saleName}
					</span>
					<span class="breakdown-item-value" style="color: #DC2626; font-weight: 600;">-PHP ${totalDiscount.toLocaleString()}</span>
				</div>
			`;
		}
	} else {
		html = '<div style="color: #9CA3AF; font-size: 13px;">No breakdown available</div>';
	}

	listContainer.innerHTML = html;
	totalEl.textContent = `PHP ${totalAmount.toLocaleString()}`;
}

// Submit for Approval - DB에 저장
async function submitForApproval() {
	if (!pendingChanges || !bookingId) {
		alert('No changes to submit');
		return;
	}

	try {
		const response = await fetch('../backend/api/super-api.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify({
				action: 'updateB2BBookingTravelersAndRooms',
				bookingId: bookingId,
				travelers: pendingChanges.travelers,
				selectedRooms: pendingChanges.selectedRooms
			})
		});

		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error(result.message || 'Failed to save changes');
		}

		// 성공 시 sessionStorage 클리어
		sessionStorage.removeItem('pendingTravelerChanges');

		alert('Changes submitted successfully!');
		window.location.href = `b2b-booking-detail.html?id=${bookingId}`;
	} catch (error) {
		console.error('Error submitting changes:', error);
		alert(error.message || 'Failed to submit changes');
	}
}

// Back to Detail - 저장 없이 돌아감
function goBack() {
	// sessionStorage 클리어 (변경사항 버림)
	sessionStorage.removeItem('pendingTravelerChanges');

	if (bookingId) {
		window.location.href = `b2b-booking-detail.html?id=${bookingId}`;
	} else {
		window.history.back();
	}
}
