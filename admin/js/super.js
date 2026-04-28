// NOTE: 전역 재선언 에러 방지용(중복 로드/모달/디버그 상황에서도 안전)
window.__jw_dfghs_proto = window.__jw_dfghs_proto || null;

function init(options) {
	const start = async () => {
		await runInit(options);
		layoutNav();
		nav_status();

		// Admin UI requirement: only eng/tl are supported. If legacy cookie is ko/kor/unknown, force eng.
		let lang = getCookie('lang') || 'eng';
		lang = String(lang || 'eng').toLowerCase();
		if (lang !== 'eng' && lang !== 'tl') lang = 'eng';
		setCookie('lang', lang, 365);
		await Promise.resolve(language_apply(lang));
		jw_select();

		// template-registration 화면에서 "관광지 추가" 복사용 프로토타입 저장
		try {
			if (!window.__jw_dfghs_proto) window.__jw_dfghs_proto = document.querySelector('.dfghs')?.cloneNode(true) || null;
		} catch (_) { }

		// Quill 에디터 초기화(없으면 무시)
		try {
			if (typeof board === 'function') board();
		} catch (_) { }

		// 주소 검색 모달 연결
		try { initAddressSearch(); } catch (_) { }
		// 이용안내 PDF 업로드 UI 연결
		try { initUsageGuideUpload(); } catch (_) { }
		// 기획서: 우측 구성요소(필수/완료) 상태 동기화
		try { initComponentProgress(); } catch (_) { }

		// Inquiries 안읽은 메시지 뱃지 polling
		try { startInquiryUnreadPolling(); } catch (_) { }
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
}


// 슬롯 로더: Promise 반환
function loadIntoSlot(slotSelector, url) {
	const slotEl = document.querySelector(slotSelector);
	if (!slotEl || !url) return Promise.resolve(false);
	return fetch(url)
		.then(res => res.text())
		.then(htmlText => {
			slotEl.innerHTML = htmlText.trim();
			return true;
		})
		.catch(err => {
			console.error('include load error:', slotSelector, url, err);
			return false;
		});
}

// 헤더/네비 로드 모두 끝난 뒤에 이어서 동작
function runInit(options) {
	options = options || {};
	const pHeader = loadIntoSlot('.layout-header', options.headerUrl);
	const pNav = loadIntoSlot('.layout-nav', options.navUrl);
	return Promise.all([pHeader, pNav]).then(async () => {
		// 헤더/프로필 사용자 표시 동기화(default.js에 정의됨)
		try { if (typeof hydrateAdminIdentityUI === 'function') await hydrateAdminIdentityUI(); } catch (_) { }
		// 메뉴 권한 필터링
		try { await filterMenusByPermission(); } catch (e) { console.error('Menu permission filter error:', e); }
		return true;
	});
}

// 메뉴 권한 필터링 함수
async function filterMenusByPermission() {
	try {
		const response = await fetch('../admin/backend/api/menu-api.php?action=getMenus', {
			credentials: 'same-origin'
		});
		const data = await response.json();

		if (!data.success || !data.menuIds) {
			console.warn('Failed to load menu permissions');
			return;
		}

		const allowedMenuIds = new Set(data.menuIds);

		// 모든 data-menu 속성을 가진 요소 필터링
		document.querySelectorAll('[data-menu]').forEach(el => {
			const menuId = el.getAttribute('data-menu');
			if (!allowedMenuIds.has(menuId)) {
				el.style.display = 'none';
			}
		});

		// 자식 메뉴가 모두 숨겨진 상위 메뉴(nav-item) 숨기기 (서브메뉴가 없는 단독 링크 메뉴는 제외)
		document.querySelectorAll('.nav-item[data-menu]').forEach(navItem => {
			const subMenuItems = navItem.querySelectorAll('.nav-sub [data-menu]');
			if (subMenuItems.length === 0) return;
			const visibleSubMenus = navItem.querySelectorAll('.nav-sub [data-menu]:not([style*="display: none"])');
			if (visibleSubMenus.length === 0) {
				navItem.style.display = 'none';
			}
		});

		console.log('Menu permissions applied. Role:', data.role, 'Allowed menus:', data.menuIds.length);
	} catch (error) {
		console.error('Error filtering menus:', error);
	}
}

function layoutNav() {
	const nav = document.querySelector('.layout-nav'); if (!nav) return;
	let t = null;
	nav.addEventListener('click', e => {
		const i = e.target.closest('.nav-item'); if (!i || !nav.contains(i)) return;
		if (t) clearTimeout(t);
		nav.querySelectorAll('.nav-item.on').forEach(el => el.classList.remove('on'));
		t = setTimeout(() => { i.classList.add('on'); t = null; }, 100);
	}, { passive: true });
}

function nav_status() {
	let file = location.pathname.split('/').pop() || 'index';
	file = decodeURIComponent(file).replace(/\.(html?)$/i, '').toLowerCase();

	document.querySelectorAll('.side-link').forEach(a => {
		const pages = (a.dataset.page || a.getAttribute('data-page') || '')
			.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
		if (pages.includes(file)) {
			a.classList.add('on');
			a.setAttribute('aria-current', 'page');
			a.closest('.nav-item')?.classList.add('on');
		}
	});
}

/* -----------------------------------------------------
   주소 검색(Address Search) 공통 기능
   - 버튼 클릭 시 address-search-modal.html 열고 선택값을 input에 채움
----------------------------------------------------- */
function initAddressSearch() {
	if (window.__jwAddressSearchBound) return;
	window.__jwAddressSearchBound = true;

	// 주소 검색:
	// - 1) 서버(/backend/api/address-search.php)에서 Google Places Text Search 사용(권장)
	// - 2) 서버에 GOOGLE_MAPS_API_KEY가 없거나 실패하면 Kakao JS SDK로 fallback

	// Kakao SDK 로더 (fallback용)
	const ensureKakaoMapsSdk = (() => {
		const SDK_ID = 'kakao-maps-sdk-head';
		const APP_KEY = 'c9d9068a507832cb391cf6ed52897501';
		return () => {
			if (window.kakao && window.kakao.maps && typeof window.kakao.maps.load === 'function') {
				return Promise.resolve(true);
			}
			if (window.__kakaoMapsSdkPromise) return window.__kakaoMapsSdkPromise;
			window.__kakaoMapsSdkPromise = new Promise((resolve) => {
				try {
					const existingHead = document.head ? document.head.querySelector(`#${SDK_ID}`) : null;
					if (existingHead) {
						// 이전 로딩 실패/차단 가능성 → 재시도
						try { existingHead.remove(); } catch (_) {}
					}
					const s = document.createElement('script');
					s.id = SDK_ID;
					s.async = true;
					s.defer = true;
					const cb = Date.now();
					s.src = `https://dapi.kakao.com/v2/maps/sdk.js?appkey=${APP_KEY}&libraries=services&autoload=false&cb=${cb}`;
					s.onload = () => resolve(!!(window.kakao && window.kakao.maps && typeof window.kakao.maps.load === 'function'));
					s.onerror = () => resolve(false);
					(document.head || document.documentElement).appendChild(s);
				} catch (_) {
					resolve(false);
				}
			});
			return window.__kakaoMapsSdkPromise;
		};
	})();

	const isAddressSearchButton = (btn) => {
		if (!btn) return false;
		const t = (btn.textContent || '').replace(/\s+/g, ' ').trim();
		// 퍼블리싱: "Address Search" / "주소 검색"
		return t.includes('Address Search') || t.includes('주소 검색') || t.includes('주소검색');
	};

	const initAddressSearchModal = (dialog) => {
		if (!dialog || dialog.__addrBound) return;
		dialog.__addrBound = true;

		const qEl = dialog.querySelector('#addrQuery');
		const btn = dialog.querySelector('#addrSearchBtn');
		const list = dialog.querySelector('#addrResults');
		if (!qEl || !btn || !list) return;

		const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
		}[m]));

		const render = (items) => {
			list.innerHTML = '';
			if (!items || !items.length) {
				list.innerHTML = '<div style="padding:10px; color:#6b7684;">No results found.</div>';
				return;
			}
			items.forEach((it) => {
				const b = document.createElement('button');
				b.type = 'button';
				b.className = 'jw-button typeA jw-w';
				b.setAttribute('data-name', it.name || it.address || '');
				b.setAttribute('data-addr', it.address || it.name || '');
				b.innerHTML = escapeHtml(it.name || it.address || '');
				list.appendChild(b);
			});
		};

		const run = async () => {
			const q = (qEl.value || '').trim();
			if (!q) {
				list.innerHTML = '<div style="padding:10px; color:#6b7684;">검색어를 입력하세요.</div>';
				return;
			}
			list.innerHTML = '<div style="padding:10px; color:#6b7684;">검색 중...</div>';
			try {
				const langCookie = (typeof getCookie === 'function') ? String(getCookie('lang') || 'eng').toLowerCase() : 'eng';
				const apiLang = (langCookie === 'tl') ? 'tl' : 'en';
				const res = await fetch(`/backend/api/address-search.php?q=${encodeURIComponent(q)}&lang=${encodeURIComponent(apiLang)}`, {
					credentials: 'same-origin'
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok || !data?.success) {
					// Google 키 미설정/실패 → Kakao fallback
					const sdkOk = await ensureKakaoMapsSdk();
					if (!sdkOk) throw new Error(data?.message || ('HTTP ' + res.status));
					const ok = await new Promise((resolve) => {
						if (window.kakao && kakao.maps && typeof kakao.maps.load === 'function') {
							kakao.maps.load(() => resolve(true));
							return;
						}
						resolve(false);
					});
					if (!ok || !(kakao.maps && kakao.maps.services)) throw new Error(data?.message || 'Kakao Maps services not available');

					// 1) addressSearch (도로명/지번)
					const geocoder = new kakao.maps.services.Geocoder();
					let items = await new Promise((resolve) => {
						geocoder.addressSearch(q, (result, status) => {
							if (status !== kakao.maps.services.Status.OK || !Array.isArray(result)) return resolve([]);
							resolve(result.slice(0, 10).map((r) => ({
								name: r.address_name || r.road_address?.address_name || '',
								address: r.address_name || r.road_address?.address_name || '',
								lat: r.y || null,
								lng: r.x || null,
							})));
						});
					});

					// 2) keywordSearch (POI)
					if (!items || items.length === 0) {
						try {
							const places = new kakao.maps.services.Places();
							items = await new Promise((resolve) => {
								places.keywordSearch(q, (result, status) => {
									if (status !== kakao.maps.services.Status.OK || !Array.isArray(result)) return resolve([]);
									resolve(result.slice(0, 10).map((r) => ({
										name: r.place_name || r.address_name || r.road_address_name || '',
										address: r.address_name || r.road_address_name || r.place_name || '',
										lat: r.y || null,
										lng: r.x || null,
									})));
								});
							});
						} catch (_) { /* ignore */ }
					}
					render(items);
					return;
				}
				const items = Array.isArray(data.results) ? data.results : [];
				render(items);
			} catch (e) {
				const msg = (e && e.message) ? String(e.message) : String(e || '');
				list.innerHTML = '<div style="padding:10px; color:#d32f2f;">Address search failed.'
					+ (msg ? ('<div style="margin-top:6px; font-size:12px; color:#6b7684;">' + escapeHtml(msg) + '</div>') : '')
					+ '</div>';
				console.error(e);
			}
		};

		btn.addEventListener('click', (e) => { e.preventDefault(); run(); });
		qEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); run(); } });

		// opener에서 전달한 초기 검색어
		try {
			const initial = (window.__addressSearchQuery || '').trim();
			if (initial) {
				qEl.value = initial;
				run();
			}
		} catch (_) { }

		// 결과 선택 → 원래 화면 input에 반영
		list.addEventListener('click', (e) => {
			const b = e.target.closest('button[data-name][data-addr]');
			if (!b) return;
			e.preventDefault();
			const name = b.getAttribute('data-name') || '';
			const addr = b.getAttribute('data-addr') || '';
			try {
				const tg = window.__addressTarget || null;
				if (tg?.nameInput) { tg.nameInput.disabled = false; tg.nameInput.readOnly = false; tg.nameInput.value = name; }
				if (tg?.addressInput) { tg.addressInput.disabled = false; tg.addressInput.readOnly = false; tg.addressInput.value = addr; }
			} catch (_) { }
			try { if (typeof modal_close === 'function') modal_close(); } catch (_) { }
		});
	};

	// 모달 로드 시점에 스크립트가 실행되지 않으므로 여기서 바인딩
	document.addEventListener('modal:loaded', (event) => {
		const { dialog, action } = event.detail || {};
		if (!dialog || !action) return;
		const act = String(action);
		if (act.includes('address-search-modal.html')) {
			initAddressSearchModal(dialog);
		}
		// Admin 로그인 화면: 아이디 찾기/비밀번호 재설정 모달은 HTML에 포함된 <script>가 실행되지 않으므로 여기서 바인딩
		try {
			if (act.includes('member/agent-id-find.html')) initAdminIdFindModal(dialog);
			if (act.includes('member/agent-id-find-result.html')) initAdminIdFindResultModal(dialog);
			if (act.includes('member/agent-password-reset.html')) initAdminPasswordResetModal(dialog);
			if (act.includes('member/guide-password-reset.html')) initAdminPasswordResetModal(dialog);
		} catch (_) { }
	});

	document.addEventListener('click', (e) => {
		const btn = e.target?.closest?.('button') || null;
		// button이 아닌 곳을 클릭하면 closest가 null → isAddressSearchButton에서 classList 접근 시 터질 수 있음
		if (!btn) return;
		if (!isAddressSearchButton(btn)) return;
		e.preventDefault();

		// name input은 버튼과 같은 row에서 찾기
		const row = btn.closest('.jw-center') || btn.closest('.field-row') || btn.parentElement;
		const nameInput = row?.querySelector('input.form-control') || null;

		// address input은 같은 블록(미팅/관광지/숙소 등) 안에서 "주소" 라벨을 가진 input 찾기
		const scope = btn.closest('.grid-wrap.grid-highlight') || btn.closest('.grid-wrap') || btn.closest('.card-panel') || document;
		let addressInput = null;
		try {
			const addrLabel = Array.from(scope.querySelectorAll('.label-name'))
				.find(l => /주소|address/i.test(l.textContent || '') && !/검색/i.test(l.textContent || ''));
			addressInput = addrLabel?.closest('.grid-item')?.querySelector('input.form-control') || null;
		} catch (_) { addressInput = null; }

		if (!nameInput || !addressInput) return;

		// disabled/readOnly 해제(퍼블리싱 초기값 대비)
		try {
			nameInput.disabled = false; nameInput.readOnly = false;
			addressInput.disabled = false; addressInput.readOnly = false;
		} catch (_) { }

		window.__addressTarget = { nameInput, addressInput };
		try { window.__addressSearchQuery = (nameInput.value || '').trim(); } catch (_) { }

		// 같은 디렉토리(super/)의 모달 호출
		if (typeof modal === 'function') modal('address-search-modal.html', '580px', '420px');
	});
}

// ===== Admin index 모달 바인딩(아이디 찾기/비밀번호 재설정) =====
function initAdminIdFindModal(dialog) {
	if (!dialog || dialog.__boundAdminIdFind) return;
	dialog.__boundAdminIdFind = true;

	const nameEl = dialog.querySelector('#managerName');
	const emailEl = dialog.querySelector('#managerEmail');
	const btn = dialog.querySelector('#findIdBtn');
	const err = dialog.querySelector('#findIdError');
	if (!btn || !nameEl || !emailEl) return;

	const setErr = (msg) => {
		if (!err) return;
		err.textContent = String(msg || '');
		err.style.display = msg ? 'block' : 'none';
	};

	btn.addEventListener('click', async (e) => {
		e.preventDefault();
		const managerName = String(nameEl.value || '').trim();
		const managerEmail = String(emailEl.value || '').trim();
		if (!managerName || !managerEmail) return setErr('Please enter your name and email correctly.');
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRegex.test(managerEmail)) return setErr('Please enter your name and email correctly.');

		setErr('');
		btn.disabled = true;
		const prev = btn.textContent;
		btn.textContent = 'Searching...';

		try {
			const tryPost = async (url, fd) => {
				const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
				const json = await res.json().catch(() => ({}));
				return { ok: res.ok, status: res.status, json };
			};

			// agent 먼저
			const fd1 = new FormData();
			fd1.append('managerName', managerName);
			fd1.append('managerEmail', managerEmail);
			const r1 = await tryPost('/admin/backend/api/find-agent-id.php', fd1);
			if (r1.ok && r1.json?.success) {
				window.__adminFoundEmail = String(r1.json.email || '');
				if (typeof modal === 'function') modal('member/agent-id-find-result.html', '580px', '358px');
				return;
			}

			// guide
			const fd2 = new FormData();
			fd2.append('guideName', managerName);
			fd2.append('guideEmail', managerEmail);
			const r2 = await tryPost('/admin/backend/api/find-guide-id.php', fd2);
			if (r2.ok && r2.json?.success) {
				window.__adminFoundEmail = String(r2.json.email || '');
				if (typeof modal === 'function') modal('member/agent-id-find-result.html', '580px', '358px');
				return;
			}

			setErr(r2.json?.message || r1.json?.message || 'Member information cannot be found.');
		} catch (ex) {
			console.error('admin id find failed:', ex);
			setErr('An error occurred while finding your ID.');
		} finally {
			btn.disabled = false;
			btn.textContent = prev || 'Find ID';
		}
	});
}

function initAdminIdFindResultModal(dialog) {
	if (!dialog || dialog.__boundAdminIdFindResult) return;
	dialog.__boundAdminIdFindResult = true;
	const emailInput = dialog.querySelector('#foundEmail');
	if (!emailInput) return;
	const v = String(window.__adminFoundEmail || '').trim();
	if (v) emailInput.value = v;
}

function initAdminPasswordResetModal(dialog) {
	if (!dialog || dialog.__boundAdminPwReset) return;
	dialog.__boundAdminPwReset = true;

	const idEl = dialog.querySelector('#resetEmail');
	const nameEl = dialog.querySelector('#resetManagerName');
	const emailEl = dialog.querySelector('#resetManagerEmail');
	// agent와 guide 모두 지원 (버튼 ID가 다를 수 있음)
	const btn = dialog.querySelector('#resetPasswordBtn') || dialog.querySelector('#resetGuidePasswordBtn');
	const err = dialog.querySelector('#resetPasswordError') || dialog.querySelector('#resetGuidePasswordError');
	if (!btn || !idEl || !nameEl || !emailEl) return;
	
	// 이미 이벤트가 바인딩되어 있으면 무시 (HTML 스크립트에서 이미 바인딩했을 수 있음)
	if (btn.hasAttribute('data-reset-bound')) {
		return;
	}
	btn.setAttribute('data-reset-bound', 'true');
	
	// guide인지 agent인지 확인하여 적절한 모달 경로 결정
	const isGuide = btn.id === 'resetGuidePasswordBtn';
	const successModalPath = isGuide 
		? '/admin/member/guide-password-issued.html'
		: '/admin/member/agent-password-issued.html';

	const setErr = (msg) => {
		if (!err) return;
		err.textContent = String(msg || '');
		err.style.display = msg ? 'block' : 'none';
	};
	
	// 중복 실행 방지 플래그
	let isProcessing = false;

	btn.addEventListener('click', async (e) => {
		e.preventDefault();
		e.stopPropagation();
		
		// 이미 처리 중이면 무시
		if (isProcessing) {
			return;
		}
		
		const loginId = String(idEl.value || '').trim();
		const managerName = String(nameEl.value || '').trim();
		const managerEmail = String(emailEl.value || '').trim();

		if (!loginId) return setErr('Please enter your ID.');
		if (!managerName) return setErr('Please enter your name.');
		if (!managerEmail) return setErr('Please enter your email.');
		if (managerName.length < 2) return setErr('Name must be at least 2 characters.');
		const nameRegex = /^[-a-zA-Z\s]+$/;
		if (!nameRegex.test(managerName)) return setErr('Name can only contain letters, spaces, and hyphens.');
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRegex.test(managerEmail)) return setErr('Please enter a valid email address.');

		isProcessing = true;
		setErr('');
		btn.disabled = true;
		const prev = btn.textContent;
		btn.textContent = 'Processing...';
		try {
			const fd = new FormData();
			// API key는 email 로 유지(하위호환). 실제 의미는 loginId.
			fd.append('email', loginId);
			fd.append('managerName', managerName);
			fd.append('managerEmail', managerEmail);

			const res = await fetch('/admin/backend/api/reset-agent-password.php', {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			});
			const json = await res.json().catch(() => ({}));
			if (res.ok && json?.success) {
				if (typeof modal === 'function') modal(successModalPath, '580px', '394px');
				return;
			}
			setErr(json?.message || 'Password reset failed.');
		} catch (ex) {
			console.error('admin pw reset failed:', ex);
			setErr(ex?.message || 'An error occurred while resetting password.');
		} finally {
			isProcessing = false;
			btn.disabled = false;
			btn.textContent = prev || 'Get Temporary Password';
		}
	});
}

/* -----------------------------------------------------
   이용안내 PDF 업로드(안내문) - 템플릿 등록/상세 공용
----------------------------------------------------- */
function initUsageGuideUpload() {
	if (window.__jwUsageGuideBound) return;
	window.__jwUsageGuideBound = true;

	// 상태 저장
	window.__usageGuideFile = window.__usageGuideFile || null;          // File
	window.__usageGuideFileUploaded = window.__usageGuideFileUploaded || null; // {filePath, originalName, ...}

	const findUsageGuideGrid = () => {
		const h2 = Array.from(document.querySelectorAll('h2.section-title'))
			.find(h =>
				(h.textContent || '').trim() === '이용안내' ||
				(h.getAttribute('data-lan-eng') || '').trim() === 'Usage Guide'
			);
		const panel = h2?.nextElementSibling;
		if (!panel) return null;
		// Notice(안내문) grid-item 찾기: 언어 무관(data-lan-eng="Notice" 우선)
		const grid = Array.from(panel.querySelectorAll('.grid-item')).find((g) => {
			const label = g.querySelector('.label-name');
			if (!label) return false;
			const engSpan = label.querySelector('[data-lan-eng="Notice"]');
			if (engSpan) return true;
			const txt = (label.textContent || '').trim();
			return txt.includes('안내문') || /notice/i.test(txt);
		});
		return grid || null;
	};

	const formatName = (name, size) => {
		if (!name) return '파일을 업로드하세요. (pdf)';
		const kb = size ? Math.max(1, Math.round(Number(size) / 1024)) : null;
		return kb ? `${name} [pdf, ${kb}KB]` : `${name} [pdf]`;
	};

	const openPicker = (grid) => {
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = 'application/pdf';
		input.style.display = 'none';
		grid.appendChild(input);
		input.addEventListener('change', () => {
			const f = input.files && input.files[0];
			if (!f) return;
			window.__usageGuideFile = f;
			window.__usageGuideFileUploaded = null;
			render(grid);
		}, { once: true });
		input.click();
	};

	const render = (grid) => {
		const row = grid.querySelector('.field-row') || grid.querySelector('.cell .field-row') || null;
		if (!row) return;
		const centers = Array.from(row.querySelectorAll('.jw-center'));
		const left = centers[0] || row.firstElementChild;
		let right = centers.length > 1 ? centers[centers.length - 1] : (row.lastElementChild !== left ? row.lastElementChild : null);

		const nameSpan = left;
		const fileMeta = window.__usageGuideFileUploaded || null;
		const fileLocal = window.__usageGuideFile || null;
		const displayName = fileMeta?.originalName || fileLocal?.name || '';
		const displaySize = fileMeta?.fileSize || fileLocal?.size || null;
		if (nameSpan) nameSpan.innerHTML = `<img src="../image/file.svg" alt=""> <span data-lan-eng="File">File</span> ${formatName(displayName, displaySize)}`;

		// 버튼: 다운로드/삭제
		if (!right) {
			right = document.createElement('div');
			right.className = 'jw-center jw-gap10';
			row.appendChild(right);
		}
		let buttons = right ? Array.from(right.querySelectorAll('button')) : [];
		let downloadBtn = buttons[0] || null;
		let deleteBtn = buttons[1] || null;

		// 퍼블리싱 마크업에 버튼이 없거나 구조가 다른 경우를 대비해 생성
		if (!downloadBtn) {
			downloadBtn = document.createElement('button');
			downloadBtn.type = 'button';
			downloadBtn.className = 'jw-button typeF';
			downloadBtn.innerHTML = `<img src="../image/buttun-download.svg" alt="">`;
			right.appendChild(downloadBtn);
		}
		if (!deleteBtn) {
			deleteBtn = document.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'jw-button typeF';
			deleteBtn.innerHTML = `<img src="../image/button-close2.svg" alt="">`;
			right.appendChild(deleteBtn);
		}

		if (downloadBtn) {
			downloadBtn.onclick = (e) => {
				e.preventDefault();
				// 업로드 된 파일이면 다운로드, 아니면 파일 선택
				const path = window.__usageGuideFileUploaded?.filePath || '';
				if (path) {
					window.open(path, '_blank');
					return;
				}
				openPicker(grid);
			};
		}
		if (deleteBtn) {
			deleteBtn.onclick = (e) => {
				e.preventDefault();
				window.__usageGuideFile = null;
				window.__usageGuideFileUploaded = null;
				render(grid);
			};
		}
	};

	const boot = () => {
		const grid = findUsageGuideGrid();
		if (!grid) return;
		render(grid);
	};
	// init()의 start(DOMContentLoaded) 안에서 호출될 수 있으므로 즉시 실행도 지원
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
	else boot();
}

/* -----------------------------------------------------
   Template Registration (publishing) helpers
   - template-registration.html 에서 inline handler로 호출됨
----------------------------------------------------- */

// ===== Upload preview remove handler (Template Registration/Detail 공통) =====
// 퍼블리싱 마크업에는 삭제 버튼(.btn-close)이 있지만 클릭 핸들러가 없는 케이스가 있어
// 여기서 전역 이벤트 위임으로 처리한다.
(function bindUploadItemRemoveDelegation() {
	try {
		const ensureUploaderUi = (item) => {
			if (!item) return;
			// 이미 file input이 있으면(=업로드 UI가 존재할 가능성) 표시만 복구
			const existingInput = item.querySelector('input[type="file"]');
			if (existingInput) {
				const lab = existingInput.closest('label.inputFile') || item.querySelector('label.inputFile');
				const btn = lab?.querySelector('.btn-upload') || item.querySelector('.btn-upload');
				if (lab) lab.style.display = '';
				if (btn) btn.style.display = '';
				return;
			}

			// template-registration 퍼블리싱과 유사한 uploader UI 삽입
			item.classList.add('jw-center');
			const label = document.createElement('label');
			label.className = 'inputFile';

			const input = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/*';
			input.setAttribute('data-essential', 'y');
			input.addEventListener('change', function () {
				try {
					if (typeof filePreview === 'function') filePreview(input);
				} catch (_) {}
			});

			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'btn-upload';
			btn.innerHTML = '<img src="../image/upload3.svg" alt=""> <span data-lan-eng="Image upload">Image upload</span>';

			label.appendChild(input);
			label.appendChild(btn);
			// 퍼블리싱 resetPublishingDefaults와 동일하게 앞에 넣어 버튼이 잘 보이게
			item.insertAdjacentElement('afterbegin', label);
		};

		const clearFileInput = (input) => {
			if (!input) return;
			// file input은 value 직접 할당이 제한적이라 clone으로 초기화
			try {
				const clone = input.cloneNode(true);
				clone.removeAttribute('onchange');
				clone.addEventListener('change', function () {
					try {
						if (typeof filePreview === 'function') filePreview(clone);
					} catch (_) {}
				});
				input.parentNode?.replaceChild(clone, input);
			} catch (_) {
				try { input.value = ''; } catch (_) {}
			}
		};

		const removePreviewFromItem = (item) => {
			if (!item) return;
			item.classList.remove('is-filled');
			item.classList.remove('has-image');
			try {
				item.style.backgroundImage = '';
				item.style.backgroundSize = '';
				item.style.backgroundPosition = '';
			} catch (_) {}

			item.querySelectorAll('img.preview').forEach((img) => img.remove());

			const input = item.querySelector('input[type="file"]');
			if (input) {
				// 같은 파일도 다시 선택 가능하도록 input 초기화
				clearFileInput(input);
				// 테스트 입력/미리보기 로직에서 숨겨둔 업로드 UI 복구
				const lab = item.querySelector('label.inputFile');
				const btn = item.querySelector('.btn-upload');
				if (lab) lab.style.display = '';
				if (btn) btn.style.display = '';
			} else {
				ensureUploaderUi(item);
			}

			item.querySelectorAll('.btn-close, .jw-button.btn-close').forEach((b) => b.remove());
		};

		const onClick = (e) => {
			const btn = e.target?.closest?.('button.btn-close, .jw-button.btn-close, button[aria-label="remove"]');
			if (!btn) return;
			const item = btn.closest('.upload-item');
			if (!item) return;
			e.preventDefault();
			e.stopPropagation();
			removePreviewFromItem(item);
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				document.addEventListener('click', onClick, true);
			}, { once: true });
		} else {
			document.addEventListener('click', onClick, true);
		}
	} catch (_) {
		// ignore
	}
})();

function essentialCheck2(it) {
	// designedfile에 있는 essentialCheck2의 운영용 축약/호환 버전
	// - data-essential="y" 필드 기준으로 검사
	// - file input은 .upload-item.is-filled 또는 img.preview가 있으면 통과 처리
	const wrap = (it && it.closest) ? (it.closest('[data-essentialWrap="y"]') || document) : document;
	const essentials = wrap.querySelectorAll('[data-essential="y"]');
	const reqLabels = wrap.querySelectorAll('.label-name .req');
	if (!essentials.length && !reqLabels.length) return '';

	const clean = (s) => (s || '').replace(/[\*\:\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();
	const findLabelText = (el) => {
		const custom = el.getAttribute('data-essential-label') || el.closest('[data-essential-label]')?.getAttribute('data-essential-label');
		if (custom && clean(custom)) return clean(custom);

		const aria = el.getAttribute('aria-label');
		if (aria && clean(aria)) return clean(aria);

		const gridItem = el.closest('.grid-item');
		const label = gridItem?.querySelector('.label-name');
		const t1 = clean(label?.textContent || '');
		if (t1) return t1;

		const ph = clean(el.getAttribute('placeholder') || el.name || el.id || '');
		return ph || '필수 항목';
	};

	const isHiddenBySection = (node) => {
		try {
			if (!node || !node.closest) return false;
			// 구성요소 토글로 숨겨진 섹션은 필수 검사에서 제외
			if (node.closest('[data-section-name].hidden')) return true;
			if (node.closest('.hidden')) return true;
			// 항공편 정보가 숨겨진 경우, 일자별 판매 조정(iljastar)은 항공편 연동 섹션이므로 제외
			const flightHidden = !!document.querySelector('[data-section-name="flight-information"].hidden');
			if (flightHidden && node.closest('[data-section-name="iljastar"]')) return true;
		} catch (_) {}
		return false;
	};

	for (const el of essentials) {
		if (isHiddenBySection(el)) continue;
		const tag = el.tagName;
		const type = (el.type || '').toLowerCase();

		let ok = true;
		if (type === 'checkbox' || type === 'radio') ok = !!el.checked;
		else if (tag === 'SELECT') ok = (el.value ?? '') !== '';
		else if (type === 'file') {
			ok = !!(el.files && el.files.length > 0);
			if (!ok) {
				const uploadItem = el.closest('.upload-item');
				if (uploadItem) ok = uploadItem.classList.contains('is-filled') || !!uploadItem.querySelector('img.preview');
			}
		} else ok = ((el.value || '').trim().length > 0);

		if (!ok) return findLabelText(el);
	}

	// 퍼블리싱 화면은 label에 *만 있고 data-essential이 없을 수 있어, label 기준으로 보강 체크
	try {
		const labels = Array.from(wrap.querySelectorAll('.label-name'))
			.filter(l => l.querySelector('.req'));
		for (const label of labels) {
			if (isHiddenBySection(label)) continue;
			const grid = label.closest('.grid-item');
			if (!grid) continue;
			if (isHiddenBySection(grid)) continue;

			// 이미 data-essential로 체크된 필드는 건너뜀
			if (grid.querySelector('[data-essential="y"]')) continue;

			// upload-grid: preview 또는 is-filled 필요
			const uploadGrid = grid.querySelector('.upload-grid');
			if (uploadGrid) {
				const has = !!(grid.querySelector('.upload-item.is-filled') || grid.querySelector('img.preview'));
				if (!has) return clean(label.textContent || '필수 항목');
				continue;
			}

			// Quill editor: 텍스트가 있어야 함 (img만 있는 경우도 허용)
			const editorArea = grid.querySelector('.jweditor');
			if (editorArea) {
				const text = (editorArea.textContent || '').replace(/\s+/g, '').trim();
				const hasImg = !!editorArea.querySelector('img');
				if (!text && !hasImg) return clean(label.textContent || '필수 항목');
				continue;
			}

			// input/select/textarea
			const field = grid.querySelector('input:not([type="file"]), select, textarea');
			if (field) {
				const tag = field.tagName;
				const type = (field.type || '').toLowerCase();
				let ok = true;
				if (type === 'checkbox' || type === 'radio') ok = field.checked;
				else if (tag === 'SELECT') ok = (field.value ?? '') !== '';
				else ok = ((field.value || '').trim().length > 0);
				if (!ok) return clean(label.textContent || '필수 항목');
			}
		}
	} catch (_) { }
	return '';
}

function essentialCheck2_list(it) {
	const wrap = (it && it.closest) ? (it.closest('[data-essentialWrap="y"]') || document) : document;
	const essentials = wrap.querySelectorAll('[data-essential="y"]');
	const missing = [];

	const isHiddenBySection = (node) => {
		try {
			if (!node || !node.closest) return false;
			if (node.closest('[data-section-name].hidden')) return true;
			if (node.closest('.hidden')) return true;
			const flightHidden = !!document.querySelector('[data-section-name="flight-information"].hidden');
			if (flightHidden && node.closest('[data-section-name="iljastar"]')) return true;
		} catch (_) {}
		return false;
	};

	const clean = (s) => (s || '').replace(/[\*\:\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();
	const findLabelText = (el) => {
		const custom = el.getAttribute('data-essential-label') || el.closest('[data-essential-label]')?.getAttribute('data-essential-label');
		if (custom && clean(custom)) return clean(custom);
		const aria = el.getAttribute('aria-label');
		if (aria && clean(aria)) return clean(aria);
		const gridItem = el.closest('.grid-item');
		const label = gridItem?.querySelector('.label-name');
		const t1 = clean(label?.textContent || '');
		if (t1) return t1;
		const ph = clean(el.getAttribute('placeholder') || el.name || el.id || '');
		return ph || '필수 항목';
	};

	for (const el of essentials) {
		if (isHiddenBySection(el)) continue;
		const tag = el.tagName;
		const type = (el.type || '').toLowerCase();

		let ok = true;
		if (type === 'checkbox' || type === 'radio') ok = !!el.checked;
		else if (tag === 'SELECT') ok = (el.value ?? '') !== '';
		else if (type === 'file') {
			ok = !!(el.files && el.files.length > 0);
			if (!ok) {
				const uploadItem = el.closest('.upload-item');
				if (uploadItem) ok = uploadItem.classList.contains('is-filled') || !!uploadItem.querySelector('img.preview');
			}
		} else ok = ((el.value || '').trim().length > 0);

		if (!ok) missing.push(findLabelText(el));
	}

	// label(*) 기반 보강
	try {
		const labels = Array.from(wrap.querySelectorAll('.label-name'))
			.filter(l => l.querySelector('.req'));
		for (const label of labels) {
			if (isHiddenBySection(label)) continue;
			const grid = label.closest('.grid-item');
			if (!grid) continue;
			if (isHiddenBySection(grid)) continue;
			if (grid.querySelector('[data-essential="y"]')) continue;

			const uploadGrid = grid.querySelector('.upload-grid');
			if (uploadGrid) {
				const has = !!(grid.querySelector('.upload-item.is-filled') || grid.querySelector('img.preview'));
				if (!has) missing.push(clean(label.textContent || '필수 항목'));
				continue;
			}

			const editorArea = grid.querySelector('.jweditor');
			if (editorArea) {
				const text = (editorArea.textContent || '').replace(/\s+/g, '').trim();
				const hasImg = !!editorArea.querySelector('img');
				if (!text && !hasImg) missing.push(clean(label.textContent || '필수 항목'));
				continue;
			}

			const field = grid.querySelector('input:not([type="file"]), select, textarea');
			if (field) {
				const tag = field.tagName;
				const type = (field.type || '').toLowerCase();
				let ok = true;
				if (type === 'checkbox' || type === 'radio') ok = field.checked;
				else if (tag === 'SELECT') ok = (field.value ?? '') !== '';
				else ok = ((field.value || '').trim().length > 0);
				if (!ok) missing.push(clean(label.textContent || '필수 항목'));
			}
		}
	} catch (_) { }

	// 중복 제거
	return Array.from(new Set(missing.filter(Boolean)));
}

function essentialCheck2_list_scope(scopeEl) {
	const wrap = scopeEl || document;
	const essentials = wrap.querySelectorAll('[data-essential="y"]');
	const missing = [];

	const isHiddenBySection = (node) => {
		try {
			if (!node || !node.closest) return false;
			if (node.closest('[data-section-name].hidden')) return true;
			if (node.closest('.hidden')) return true;
			const flightHidden = !!document.querySelector('[data-section-name="flight-information"].hidden');
			if (flightHidden && node.closest('[data-section-name="iljastar"]')) return true;
		} catch (_) {}
		return false;
	};

	const clean = (s) => (s || '').replace(/[\*\:\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();
	const findLabelText = (el) => {
		const custom = el.getAttribute('data-essential-label') || el.closest('[data-essential-label]')?.getAttribute('data-essential-label');
		if (custom && clean(custom)) return clean(custom);
		const aria = el.getAttribute('aria-label');
		if (aria && clean(aria)) return clean(aria);
		const gridItem = el.closest('.grid-item');
		const label = gridItem?.querySelector('.label-name');
		const t1 = clean(label?.textContent || '');
		if (t1) return t1;
		const ph = clean(el.getAttribute('placeholder') || el.name || el.id || '');
		return ph || '필수 항목';
	};

	for (const el of essentials) {
		if (isHiddenBySection(el)) continue;
		const tag = el.tagName;
		const type = (el.type || '').toLowerCase();
		let ok = true;
		if (type === 'checkbox' || type === 'radio') ok = !!el.checked;
		else if (tag === 'SELECT') ok = (el.value ?? '') !== '';
		else if (type === 'file') {
			ok = !!(el.files && el.files.length > 0);
			if (!ok) {
				const uploadItem = el.closest('.upload-item');
				if (uploadItem) ok = uploadItem.classList.contains('is-filled') || !!uploadItem.querySelector('img.preview');
			}
		} else ok = ((el.value || '').trim().length > 0);
		if (!ok) missing.push(findLabelText(el));
	}

	// label(*) 기반 보강
	try {
		const labels = Array.from(wrap.querySelectorAll('.label-name'))
			.filter(l => l.querySelector('.req'));
		for (const label of labels) {
			if (isHiddenBySection(label)) continue;
			const grid = label.closest('.grid-item');
			if (!grid) continue;
			if (isHiddenBySection(grid)) continue;
			if (grid.querySelector('[data-essential="y"]')) continue;

			const uploadGrid = grid.querySelector('.upload-grid');
			if (uploadGrid) {
				const has = !!(grid.querySelector('.upload-item.is-filled') || grid.querySelector('img.preview'));
				if (!has) missing.push(clean(label.textContent || '필수 항목'));
				continue;
			}

			const editorArea = grid.querySelector('.jweditor');
			if (editorArea) {
				const text = (editorArea.textContent || '').replace(/\s+/g, '').trim();
				const hasImg = !!editorArea.querySelector('img');
				if (!text && !hasImg) missing.push(clean(label.textContent || '필수 항목'));
				continue;
			}

			const field = grid.querySelector('input:not([type="file"]), select, textarea');
			if (field) {
				const tag = field.tagName;
				const type = (field.type || '').toLowerCase();
				let ok = true;
				if (type === 'checkbox' || type === 'radio') ok = field.checked;
				else if (tag === 'SELECT') ok = (field.value ?? '') !== '';
				else ok = ((field.value || '').trim().length > 0);
				if (!ok) missing.push(clean(label.textContent || '필수 항목'));
			}
		}
	} catch (_) { }

	return Array.from(new Set(missing.filter(Boolean)));
}

function initComponentProgress() {
	if (window.__jwComponentProgressBound) return;
	window.__jwComponentProgressBound = true;

	// IMPORTANT:
	// 언어(ko/eng/tl) 변경 시 textContent 기반 탐색은 깨지기 쉬우므로,
	// 번역 키(data-lan-eng) 기반으로 섹션/행을 찾는다.
	const findSectionH2ByLan = (lanKey) => {
		if (!lanKey) return null;
		return document.querySelector(`h2.section-title[data-lan-eng="${lanKey}"]`);
	};

	const setRowState = (row, ok) => {
		if (!row) return;
		row.classList.toggle('is-complete', !!ok);
		row.classList.toggle('is-incomplete', !ok);
	};

	const update = () => {
		// 이 페이지가 아니면 패스
		const aside = document.querySelector('.content-aside[aria-label="구성 요소"]');
		if (!aside) return;

		// 섹션 스코프 구성 (data-lan-eng)
		const templateH2 = findSectionH2ByLan('Template Information');
		const productH2 = findSectionH2ByLan('Product Information');
		const scheduleH2 = findSectionH2ByLan('Schedule');
		const feeH2 = findSectionH2ByLan('Fee information');
		const includeH2 = findSectionH2ByLan('Included/Not Included Items');
		const usageH2 = findSectionH2ByLan('Usage Guide');
		const cancelH2 = findSectionH2ByLan('Cancellation/Refund Policy');
		const visaH2 = findSectionH2ByLan('Visa Application Guide');

		const scopeOfH2 = (h2) => {
			if (!h2) return null;
			// 다음 section-title 전까지의 블록을 감싸는 임시 scope를 만든다(실제 DOM 변경은 안 하고 범위로 검사)
			const start = h2;
			const nodes = [];
			let cur = start.nextElementSibling;
			while (cur) {
				if (cur.matches && cur.matches('h2.section-title')) break;
				nodes.push(cur);
				cur = cur.nextElementSibling;
			}
			const box = document.createElement('div');
			nodes.forEach(n => box.appendChild(n.cloneNode(true))); // 검사 목적이라 clone 사용
			return { nodes, clone: box };
		};

		// 메인 row 매핑(data-lan-eng로 매칭)
		const rows = Array.from(aside.querySelectorAll('.aside-row'));
		const findRowByLan = (lanKey) => {
			if (!lanKey) return null;
			return rows.find(r => r.querySelector(`.aside-row__title[data-lan-eng="${lanKey}"], .aside-row__subtitle[data-lan-eng="${lanKey}"]`)) || null;
		};

		// 템플릿 정보
		try {
			const row = findRowByLan('Template Information');
			const scope = scopeOfH2(templateH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }

		// 상품 정보
		try {
			const row = findRowByLan('Product Information');
			const scope = scopeOfH2(productH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }

		// 요금 정보
		try {
			const row = findRowByLan('Fee information');
			const scope = scopeOfH2(feeH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }

		// 포함/불포함(필수 표시는 없지만 기획서 상태표시를 위해 체크)
		try {
			const row = findRowByLan('Included/Not Included Items');
			const scopes = Array.from(document.querySelectorAll('[data-section-name="poham"]'));
			const clone = document.createElement('div');
			scopes.forEach(n => clone.appendChild(n.cloneNode(true)));
			const ok = essentialCheck2_list_scope(clone).length === 0;
			setRowState(row, ok);
		} catch (_) { }

		// 이용안내 / 취소환불 / 비자
		try {
			const row = findRowByLan('Usage Guide');
			const scope = scopeOfH2(usageH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }
		try {
			const row = findRowByLan('Cancellation/Refund Policy');
			const scope = scopeOfH2(cancelH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }
		try {
			const row = findRowByLan('Visa Application Guide');
			const scope = scopeOfH2(visaH2);
			const ok = scope ? essentialCheck2_list_scope(scope.clone).length === 0 : true;
			setRowState(row, ok);
		} catch (_) { }

		// 일정표(미팅 + day별)
		try {
			const scheduleRow = findRowByLan('Schedule');
			const scheduleScope = scopeOfH2(scheduleH2);
			const okSchedule = scheduleScope ? essentialCheck2_list_scope(scheduleScope.clone).length === 0 : true;
			setRowState(scheduleRow, okSchedule);

			// 미팅 정보 (data-lan-eng로 탐지)
			const meetingPanel = document.querySelector('h3.grid-wrap-title[data-lan-eng="Meeting Information"]')?.closest('.card-panel') || null;
			const meetingRow = findRowByLan('Meeting Information');
			if (meetingPanel) {
				const ok = essentialCheck2_list_scope(meetingPanel).length === 0;
				setRowState(meetingRow, ok);
			}

			// Day 패널/우측 리스트는 "표시 문구"가 아니라 "순서"로 매칭
			const dayPanels = Array.from(document.querySelectorAll('.card-panel[id^="nday"]'));
			const dayRows = Array.from(aside.querySelectorAll('.aside-row-list .aside-row'));
			dayPanels.forEach((p, idx) => {
				const row = dayRows[idx] || null;
				const ok = essentialCheck2_list_scope(p).length === 0;
				setRowState(row, ok);
			});
		} catch (_) { }
	};

	window.__updateTemplateComponentProgress = update;

	// 이벤트 바인딩: 입력/선택/에디터 변화/업로드 프리뷰 변경
	const scheduleUpdate = (() => {
		let t = null;
		return () => {
			if (t) cancelAnimationFrame(t);
			t = requestAnimationFrame(() => { t = null; update(); });
		};
	})();

	document.addEventListener('input', scheduleUpdate, true);
	document.addEventListener('change', scheduleUpdate, true);
	document.addEventListener('keyup', (e) => { if (e.target && e.target.closest && e.target.closest('.ql-editor')) scheduleUpdate(); }, true);
	document.addEventListener('paste', (e) => { if (e.target && e.target.closest && e.target.closest('.ql-editor')) scheduleUpdate(); }, true);

	// 최초 1회
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => setTimeout(update, 50), { once: true });
	} else {
		setTimeout(update, 50);
	}
}

function template_detail_save(it) {
	const missing = essentialCheck2_list(it);
	if (missing.length) {
		if (typeof modal === 'function') {
			// 모달 HTML은 비동기로 로드되므로, modal:loaded 시점에 누락 항목을 주입해야 안정적
			const esc = (s) => String(s)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
			const html = missing.map(esc).join('<br>');
			window.__jwLastRequiredMissingHtml = html;

			const applyToDialog = (dialog) => {
				if (!dialog) return false;
				const tg = dialog.querySelector('#lllll');
				if (!tg) return false;
				tg.innerHTML = html;
				return true;
			};

			const onLoaded = (event) => {
				const { dialog, action } = event.detail || {};
				if (!dialog || !action) return;
				if (!String(action).includes('template-detail-modal1.html')) return;
				applyToDialog(dialog);
				document.removeEventListener('modal:loaded', onLoaded);
			};
			document.addEventListener('modal:loaded', onLoaded);

			modal('template-detail-modal1.html', '600px', '252px');

			// 혹시 이미 로드된 경우/지연되는 경우를 대비한 보조 시도
			setTimeout(() => applyToDialog(document.querySelector('dialog:last-of-type')), 50);
			setTimeout(() => applyToDialog(document.querySelector('dialog:last-of-type')), 300);
			return;
		}
		alert(`Please fill in: ${missing[0]}`);
		return;
	}

	const btn = (it && it.tagName) ? it : null;
	if (btn) {
		btn.disabled = true;
		btn.classList.add('is-loading');
	}

	const safeText = (s) => (s == null ? '' : String(s)).trim();
	const safeHtml = (node) => {
		if (!node) return '';
		// Quill 컨테이너(.jweditor)가 이미 초기화되면 실제 내용은 .ql-editor 안에 있음
		try {
			const ql = node.querySelector?.('.ql-editor');
			if (ql) return String(ql.innerHTML || '').trim();
		} catch (_) { }
		return String(node.innerHTML || '').trim();
	};

	const getSelectedText = (sel) => {
		try {
			const opt = sel?.selectedOptions?.[0] || sel?.options?.[sel?.selectedIndex] || null;
			return safeText(opt?.textContent || '');
		} catch (_) {
			return '';
		}
	};

	// label-name 내부 span[data-lan-eng="..."] 기반으로 grid-item 찾기 (등록/상세 공통)
	// NOTE: schedule/day/숙소/관광지 저장에서도 사용되므로 함수 스코프 최상단에 둔다.
	const findGridByLanEngKey = (lanKey, scope) => {
		try {
			const root = scope || document;
			const key = String(lanKey || '').trim();
			if (!key) return null;
			// CSS.escape가 없는 환경도 있어 안전 처리(키는 대부분 안전한 ASCII라 그대로 사용해도 OK)
			const esc = (window.CSS && typeof window.CSS.escape === 'function') ? window.CSS.escape(key) : key;

			// 1) label-name 자체가 data-lan-eng를 가진 경우 (예: 교통편 정보의 "설명" 라벨)
			const direct = root.querySelector(`.label-name[data-lan-eng="${esc}"]`);
			if (direct) return direct.closest('.grid-item') || null;

			// 2) label-name 내부 span[data-lan-eng] 형태
			const inner = root.querySelector(`.label-name [data-lan-eng="${esc}"]`);
			return inner ? inner.closest('.grid-item') : null;
		} catch (_) { return null; }
	};

	// ----- 필수 저장 컬럼 뽑기 (createTemplate 요구사항) -----
	const templateNameEl =
		document.querySelector('.content-main .card-panel input.form-control[data-essential="y"]') ||
		document.querySelector('input.form-control[data-essential="y"]');
	const templateName = safeText(templateNameEl?.value || '');

	const mainCategorySel = document.querySelector('select[data-category="1"]');
	const subCategorySel = document.querySelector('select[data-category="2"]');

	// 판매대상(첫 번째 "판매 대상" select)
	const targetMarketSel = Array.from(document.querySelectorAll('select')).find(s => {
		const wrap = s.closest('.grid-item');
		const label = wrap?.querySelector('.label-name');
		return /판매 대상/i.test(label?.textContent || '') || /sales target/i.test(label?.textContent || '');
	});

	const mainCategory = safeText(mainCategorySel?.value || getSelectedText(mainCategorySel));
	const subCategory = safeText(subCategorySel?.value || getSelectedText(subCategorySel));
	const targetMarket = safeText(targetMarketSel?.value || getSelectedText(targetMarketSel));

	// 일정 기간: aside의 "n일차" 개수 기반으로 계산 (없으면 1Day)
	let schedulePeriod = '1Day';
	try {
		// 언어 무관: "1일차", "Day 2", "1st day" 모두 숫자 추출
		const dayTitles = Array.from(document.querySelectorAll('.aside-row__subtitle'))
			.map(el => safeText(el.textContent || ''))
			.filter(Boolean);
		const nums = dayTitles
			.map(t => parseInt(String(t).replace(/[^\d]/g, ''), 10))
			.filter(n => Number.isFinite(n) && n > 0);
		// 실제 본문 day 패널 수가 더 크면 그걸 우선(사이드/본문 불일치 방지)
		const panelCount = document.querySelectorAll('.card-panel[id^="nday"]').length || 0;
		const maxDay = Math.max(nums.length ? Math.max(...nums) : 1, panelCount || 1);
		schedulePeriod = `${maxDay}Day`;
	} catch (_) { }

	// ----- 전체 데이터(상세 JSON) 수집 -----
	// 운영 안정성을 위해 "DOM 순서"가 아니라 "의미있는 키 구조"로 저장한다.
	const data = {
		version: 1,
		components: {},
		template: {},
		product: {},
		schedule: {},
		pricing: {},
		included: [],
		excluded: [],
		usageGuide: {},
		cancellationPolicy: {},
		visaGuide: {},
		raw: {},
		createdAtClient: new Date().toISOString(),
	};

	const findGridByLabelContains = (needle) => {
		const n = safeText(needle);
		if (!n) return null;
		// 언어 무관: data-lan-eng 우선
		const byDirect = Array.from(document.querySelectorAll('.label-name[data-lan-eng]'))
			.find(l => safeText(l.getAttribute('data-lan-eng')) === n);
		if (byDirect) return byDirect.closest('.grid-item') || null;
		const byInner = Array.from(document.querySelectorAll('.label-name [data-lan-eng]'))
			.find(s => safeText(s.getAttribute('data-lan-eng')) === n);
		if (byInner) return byInner.closest('.grid-item') || null;
		// 렌더된 텍스트 fallback
		const label = Array.from(document.querySelectorAll('.label-name'))
			.find(l => (l.textContent || '').replace('*', '').includes(n));
		return label?.closest('.grid-item') || null;
	};
	const inputFromGrid = (grid) => grid ? (grid.querySelector('input.form-control') || grid.querySelector('input')) : null;
	const editorHtmlFromGrid = (grid) => grid ? safeHtml(grid.querySelector('.jweditor')) : '';

	data.template.templateName = templateName;

	// 모든 input/select 기본 스냅샷(디버깅/이관용 - 운영에서 "마지막 보험" 역할)
	try {
		data.raw.fields = {
			inputs: Array.from(document.querySelectorAll('input.form-control')).map((el) => ({
				name: el.name || null,
				placeholder: el.getAttribute('placeholder') || null,
				value: safeText(el.value || ''),
				disabled: !!el.disabled,
				readOnly: !!el.readOnly
			})),
			selects: Array.from(document.querySelectorAll('select')).map((el) => ({
				value: safeText(el.value || ''),
				text: getSelectedText(el),
				disabled: !!el.disabled,
				dataCategory: el.getAttribute('data-category') || null,
			}))
		};
	} catch (_) { }

	// 에디터(Quill) 내용
	try {
		data.raw.editors = Array.from(document.querySelectorAll('.jw-editor .jweditor')).map((area) => {
			const grid = area.closest('.grid-item');
			const label = safeText(grid?.querySelector('.label-name')?.textContent || '');
			return { label, html: safeHtml(area) };
		});
	} catch (_) { }

	// 업로드 미리보기 이미지
	try {
		data.raw.uploads = Array.from(document.querySelectorAll('.upload-item')).map((item) => {
			const img = item.querySelector('img.preview');
			return {
				filled: item.classList.contains('is-filled'),
				src: safeText(img?.getAttribute('src') || ''),
			};
		});
	} catch (_) { }

	// ===== 구조화 필드 저장 (퍼블리싱 마크업 기반) =====
	try {
		// 등록/상세 공통: 상품명
		const productNameGrid = findGridByLanEngKey('Product Name') || findGridByLabelContains('Product Name') || findGridByLabelContains('상품명');
		data.product.productName = safeText(inputFromGrid(productNameGrid)?.value || '');

		// 판매대상/카테고리
		data.product.targetMarket = targetMarket;
		data.product.mainCategory = mainCategory;
		data.product.subCategory = subCategory;

		// 상품 설명(첫 번째 "상품 설명" 에디터)
		const productDescGrid = findGridByLanEngKey('Product Description') || findGridByLabelContains('Product Description') || findGridByLabelContains('상품 설명');
		data.product.descriptionHtml = editorHtmlFromGrid(productDescGrid);

		// 이미지
		const thumbGrid = findGridByLanEngKey('Thumbnail image') || findGridByLabelContains('Thumbnail image') || findGridByLabelContains('썸네일 이미지');
		const detailGrid = findGridByLanEngKey('Detailed introduction image') || findGridByLabelContains('Detailed introduction image') || findGridByLabelContains('상세 소개 이미지');
		const productGrid = findGridByLanEngKey('Product image') || findGridByLabelContains('Product image') || findGridByLabelContains('상품 이미지');
		const thumbImg = thumbGrid?.querySelector('img.preview') || null;
		const detailImg = detailGrid?.querySelector('img.preview') || null;
		const productImgs = productGrid?.querySelectorAll('img.preview') || [];
		data.product.images = {
			thumbnail: safeText(thumbImg?.getAttribute('src') || ''),
			productImages: Array.from(productImgs).map(i => safeText(i.getAttribute('src') || '')).filter(Boolean),
			detailImage: safeText(detailImg?.getAttribute('src') || ''),
		};
	} catch (_) { }

	// 포함/불포함 사항 테이블
	try {
		// 구성요소 토글 상태 저장 (포함/불포함)
		// - togglePlusMinus(default.js)는 data-section-name="poham" 영역에 .hidden을 붙여 숨김 처리
		// - 이 상태를 저장하지 않으면 상세 로드시 기본(보임)으로 복원되어 "빼고 저장했는데 다시 추가"가 발생
		const pohamHidden = !!document.querySelector('[data-section-name="poham"].hidden');
		data.components.poham = !pohamHidden;

		// 섹션이 숨김이면 데이터도 저장하지 않음(다시 나타나는 현상 방지)
		if (pohamHidden) {
			data.included = [];
			data.excluded = [];
		} else {
		const readTableTexts = (lanKey) => {
			const title = Array.from(document.querySelectorAll('h3.grid-wrap-title'))
				.find(h => safeText(h.getAttribute('data-lan-eng') || '') === lanKey);
			const panel = title?.closest('.card-panel');
			if (!panel) return [];
			// NOTE:
			// - 일부 삭제 UI는 <tr>을 제거하지 않고 display:none 처리하는 경우가 있음
			// - 이런 경우 숨겨진 행의 기존 값이 다시 저장되어 "삭제했는데 다시 생김" 현상이 발생
			const isHiddenRow = (tr) => {
				try {
					if (!tr) return true;
					if (tr.closest('.hidden')) return true;
					const st = window.getComputedStyle(tr);
					if (st && (st.display === 'none' || st.visibility === 'hidden')) return true;
					// offsetParent가 null인 경우(대부분 display:none/hidden)
					if (tr.offsetParent === null && st.position !== 'fixed') return true;
				} catch (_) { }
				return false;
			};

			return Array.from(panel.querySelectorAll('tbody tr'))
				.filter(tr => !isHiddenRow(tr))
				.map(tr => safeText(tr.querySelector('input.form-control')?.value || ''))
				.filter(Boolean);
		};
		data.included = readTableTexts('Included items');
		data.excluded = readTableTexts('Excluded items');
		}
	} catch (_) { }

	// 요금(인원별 요금) / 싱글룸
	try {
		const perPersonTitle = Array.from(document.querySelectorAll('h3.grid-wrap-title'))
			.find(h => safeText(h.getAttribute('data-lan-eng') || '') === 'Number of people fee');
		const perPanel = perPersonTitle?.closest('.card-panel');
		if (perPanel) {
			data.pricing.perPerson = Array.from(perPanel.querySelectorAll('tbody tr')).map(tr => {
				const inputs = tr.querySelectorAll('input.form-control');
				return {
					optionName: safeText(inputs[0]?.value || ''),
					price: safeText(inputs[1]?.value || ''),
				};
			});
		}

		const singleTitle = Array.from(document.querySelectorAll('h3.grid-wrap-title'))
			.find(h => safeText(h.getAttribute('data-lan-eng') || '') === 'Room rate for single occupancy');
		const singlePanel = singleTitle?.closest('.card-panel');
		if (singlePanel) {
			const ip = singlePanel.querySelector('input.form-control');
			data.pricing.singleRoomFee = safeText(ip?.value || '');
		}
	} catch (_) { }

	// 일정(미팅 + 1Day 블록)
	try {
		const meetingTitle = Array.from(document.querySelectorAll('h3.grid-wrap-title'))
			.find(h =>
				safeText(h.getAttribute('data-lan-eng') || '') === 'Meeting Information'
			);
		const meetingPanel = meetingTitle?.closest('.card-panel');
		if (meetingPanel) {
			const selects = meetingPanel.querySelectorAll('select');
			const placeNameGrid = findGridByLanEngKey('Meeting place name', meetingPanel);
			const placeAddrGrid = findGridByLanEngKey('Meeting place address', meetingPanel);
			data.schedule.meeting = {
				hour: safeText(selects[0]?.value || ''),
				minute: safeText(selects[1]?.value || ''),
				placeName: safeText(inputFromGrid(placeNameGrid)?.value || ''),
				placeAddress: safeText(inputFromGrid(placeAddrGrid)?.value || ''),
			};
		}

		// Day 카드 패널들(#nday, #nday_2, ...)
		const maxDay = (() => {
			try {
				const titles = Array.from(document.querySelectorAll('.aside-row__subtitle')).map(el => safeText(el.textContent || '')).filter(Boolean);
				const nums = titles.map(t => parseInt(String(t).replace(/[^\d]/g, ''), 10)).filter(n => Number.isFinite(n) && n > 0);
				return nums.length ? Math.max(...nums) : null;
			} catch (_) { return null; }
		})();

		// id 패턴이 깨져도 저장 누락되지 않게, day 번호 기반으로 패널을 찾아온다.
		const findDayPanel = (n) => {
			if (n === 1) return document.getElementById('nday') || document.querySelector('.card-panel[id="nday"]');
			const byId = document.getElementById(`nday_${n}`) ||
				document.getElementById(`nday${n}`) ||
				document.querySelector(`.card-panel[id="nday_${n}"]`) ||
				document.querySelector(`.card-panel[id="nday${n}"]`) ||
				null;
			if (byId) return byId;
			return null;
		};
		let dayPanels = Array.from(document.querySelectorAll('.card-panel[id^="nday"]'));
		if (maxDay && maxDay > 0) {
			const byNum = [];
			for (let i = 1; i <= maxDay; i++) {
				const p = findDayPanel(i);
				if (p) byNum.push(p);
			}
			// 숫자 기반이 더 많으면 그걸 사용
			if (byNum.length >= dayPanels.length) dayPanels = byNum;
		}
		if (dayPanels.length) {
			data.schedule.days = dayPanels.map((dayPanel, idx) => {
				const dayIndex = idx + 1;
				const day = { day: dayIndex, summary: '', attractions: [], accommodation: {}, transportation: {}, meals: {} };

				// Schedule Summary:
				// - template-detail.html 마크업은 placeholder가 아니라 label(data-lan-eng="Schedule Summary") 기반
				// - placeholder 기반으로만 찾으면 저장 시 항상 빈값이 되어 "요약이 사라짐" 현상이 발생
				const summaryGrid =
					dayPanel.querySelector('.label-name[data-lan-eng="Schedule Summary"]')?.closest('.grid-item') ||
					dayPanel.querySelector('.label-name [data-lan-eng="Schedule Summary"]')?.closest('.grid-item') ||
					null;
				const summaryInput =
					summaryGrid?.querySelector('input.form-control') ||
					dayPanel.querySelector('input.form-control[placeholder="Schedule Summary"]') ||
					null;
				day.summary = safeText(summaryInput?.value || '');

				// 관광지(복수)
				const blocks = Array.from(dayPanel.querySelectorAll('.dfghs'));
				day.attractions = blocks.map((b) => {
					const selects = Array.from(b.querySelectorAll('.meeting-wrap select'));
					const s1 = selects[0] || null;
					const s2 = selects[1] || null;
					const e1 = selects[2] || null;
					const e2 = selects[3] || null;
					const nameGrid = findGridByLanEngKey('Sight Name', b);
					const addrGrid = findGridByLanEngKey('Sight Address', b);
					const infoGrid = findGridByLanEngKey('Sight Information', b);
					const img = b.querySelector('.upload-grid img.preview');
					return {
						startHour: safeText(s1?.value || ''),
						startMinute: safeText(s2?.value || ''),
						endHour: safeText(e1?.value || ''),
						endMinute: safeText(e2?.value || ''),
						name: safeText(inputFromGrid(nameGrid)?.value || ''),
						address: safeText(inputFromGrid(addrGrid)?.value || ''),
						infoHtml: editorHtmlFromGrid(infoGrid),
						image: safeText(img?.getAttribute('src') || ''),
					};
				});

				// 숙소
				const accomPanel = Array.from(dayPanel.querySelectorAll('.sub-panel'))
					.find(p => {
						const h4 = p.querySelector('h4.grid-wrap-title');
						return safeText(h4?.getAttribute('data-lan-eng') || '') === 'Accommodation Information';
					});
				if (accomPanel) {
					const findByEng = (eng) => Array.from(accomPanel.querySelectorAll('.label-name [data-lan-eng]'))
						.find(s => safeText(s.getAttribute('data-lan-eng') || '') === eng)?.closest('.grid-item') || null;
					const nameGrid = findByEng('Accommodation name');
					const addrGrid = findByEng('Accommodation address');
					const descGrid = findByEng('Accommodation Description');
					const imgGrid = findByEng('Accommodation image');
					const img = (imgGrid || accomPanel).querySelector('.upload-grid img.preview');
					day.accommodation = {
						name: safeText(inputFromGrid(nameGrid)?.value || ''),
						address: safeText(inputFromGrid(addrGrid)?.value || ''),
						descriptionHtml: editorHtmlFromGrid(descGrid),
						image: safeText(img?.getAttribute('src') || ''),
					};
				}

				// 교통편
				const transPanel = Array.from(dayPanel.querySelectorAll('.sub-panel'))
					.find(p => {
						const h4 = p.querySelector('h4.grid-wrap-title');
						return safeText(h4?.getAttribute('data-lan-eng') || '') === 'Transportation Information';
					});
				if (transPanel) {
					// NOTE: 교통편의 "설명" 라벨은 페이지에 따라
					// - <label class="label-name" data-lan-eng="Description">설명</label>
					// - <label class="label-name"><span data-lan-eng="Description">설명</span></label>
					// 두 형태가 모두 존재함 → 공용 helper로 탐지해야 저장 누락이 안 생김
					const descGrid =
						findGridByLanEngKey('Description', transPanel) ||
						Array.from(transPanel.querySelectorAll('.label-name')).find(l => /설명|Description/i.test(l.textContent || ''))?.closest('.grid-item') ||
						null;
					day.transportation = { descriptionHtml: editorHtmlFromGrid(descGrid) };
				}

				// 식사
				const mealPanel = Array.from(dayPanel.querySelectorAll('.sub-panel'))
					.find(p => {
						const h4 = p.querySelector('h4.grid-wrap-title');
						return safeText(h4?.getAttribute('data-lan-eng') || '') === 'Meal Information';
					});
				if (mealPanel) {
					const inputs = mealPanel.querySelectorAll('input.form-control');
					day.meals = {
						breakfast: safeText(inputs[0]?.value || ''),
						lunch: safeText(inputs[1]?.value || ''),
						dinner: safeText(inputs[2]?.value || ''),
					};
				}

				return day;
			});
		}
	} catch (_) { }

	// 이용안내/취소/비자 (에디터/필드 저장)
	try {
		const findSectionByH2 = (lanKey) => {
			const h2 = document.querySelector(`h2.section-title[data-lan-eng="${CSS.escape(lanKey)}"]`);
			const panel = h2?.nextElementSibling || null;
			return { h2, panel };
		};
		const findEditorByPanel = (panel) => {
			if (!panel) return null;
			const label =
				Array.from(panel.querySelectorAll('.label-name [data-lan-eng]'))
					.find(s => safeText(s.getAttribute('data-lan-eng') || '') === 'Description')?.closest('.grid-item')?.querySelector('.jweditor') ||
				Array.from(panel.querySelectorAll('.grid-item'))
					.find(g => /설명|Description/i.test(g.querySelector('.label-name')?.textContent || ''))?.querySelector('.jweditor') ||
				null;
			return label;
		};

		// 이용안내
		{
			const { panel } = findSectionByH2('Usage Guide');
			const ed = findEditorByPanel(panel);
			data.usageGuide.descriptionHtml = safeHtml(ed);
		}

		// 취소/환불 규정
		{
			const { panel } = findSectionByH2('Cancellation/Refund Policy');
			if (panel) {
				const refundGrid =
					Array.from(panel.querySelectorAll('.label-name [data-lan-eng]'))
						.find(s => safeText(s.getAttribute('data-lan-eng') || '') === 'Full refund available standard date')?.closest('.grid-item') ||
					null;
				const refundInput = refundGrid ? (refundGrid.querySelector('input.form-control') || refundGrid.querySelector('input')) : null;
				data.cancellationPolicy.refundDays = safeText(refundInput?.value || '');
			}
			const ed = findEditorByPanel(panel);
			data.cancellationPolicy.descriptionHtml = safeHtml(ed);
		}

		// 비자 신청 안내
		{
			const { panel } = findSectionByH2('Visa Application Guide');
			const ed = findEditorByPanel(panel);
			data.visaGuide.descriptionHtml = safeHtml(ed);
		}
	} catch (_) { }

	// 템플릿 상세 페이지(id)가 있으면 update, 없으면 create
	let templateId = null;
	try {
		const urlParams = new URLSearchParams(window.location.search);
		templateId = urlParams.get('id') || urlParams.get('templateId') || window.__templateId || null;
	} catch (_) { templateId = window.__templateId || null; }

	// (과거 호환) 최상위 key도 일부 남겨둠
	try {
		data.templateName = data.template.templateName;
	} catch (_) { }

	const payload = {
		templateName,
		mainCategory,
		subCategory,
		targetMarket,
		schedulePeriod,
		data,
	};
	if (templateId) payload.templateId = templateId;

	const action = templateId ? 'updateTemplate' : 'createTemplate';

	// ===== 안내문(PDF) 업로드: 선택된 경우 먼저 업로드하고 data.usageGuide.file로 저장 =====
	const uploadUsageGuideIfNeeded = async () => {
		const f = window.__usageGuideFile || null;
		if (!f) return window.__usageGuideFileUploaded || null;
		if (window.__usageGuideFileUploaded?.filePath) return window.__usageGuideFileUploaded;

		const fd = new FormData();
		fd.append('file', f);
		fd.append('type', 'template_guides');
		fd.append('related_id', String(templateId || 0));
		const up = await fetch('/backend/api/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
		if (up.status === 401) throw new Error('안내문 업로드 권한이 없습니다. 다시 로그인 후 시도해주세요.');
		const uj = await up.json().catch(() => ({}));
		if (!up.ok || !uj.success) throw new Error(uj.message || `안내문 업로드 실패 (HTTP ${up.status})`);
		window.__usageGuideFileUploaded = uj.data || null;
		window.__usageGuideFile = null;
		return window.__usageGuideFileUploaded;
	};

	// ===== 템플릿 이미지 업로드(선택): file input에 선택된 파일은 업로드 후 /uploads/... 경로로 저장 =====
	const uploadTemplateImagesIfNeeded = async () => {
		const uploadOne = async (file) => {
			if (!file) return null;
			const fd = new FormData();
			fd.append('file', file);
			fd.append('type', 'template_images');
			fd.append('related_id', String(templateId || 0));
			const up = await fetch('/backend/api/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
			if (up.status === 401) throw new Error('이미지 업로드 권한이 없습니다. 다시 로그인 후 시도해주세요.');
			const uj = await up.json().catch(() => ({}));
			if (!up.ok || !uj.success) throw new Error(uj.message || `이미지 업로드 실패 (HTTP ${up.status})`);
			return uj.data?.filePath || null; // e.g. /uploads/template_images/...
		};

		// helper: 기존 src에서 blob/demo 제거
		const isBlob = (s) => /^blob:/i.test(String(s || '').trim());
		const isData = (s) => /^data:/i.test(String(s || '').trim());
		const isDemo = (s) => /picsum\.photos/i.test(String(s || ''));
		const cleanExisting = (s) => {
			const v = String(s || '').trim();
			if (!v) return '';
			if (isBlob(v)) return '';
			if (isData(v)) return '';
			if (isDemo(v)) return '';
			return v;
		};

		// 단일 이미지(썸네일/상세소개 등): 기존 src가 blob/demo면 저장 불가이므로, 파일 선택이 없으면 에러
		const uploadSingleFromGrid = async (labelKo, labelEn, assign, requiredLabel) => {
			const grid =
				findGridByLabelContains(labelKo) || findGridByLabelContains(labelEn) ||
				Array.from(document.querySelectorAll('.grid-item')).find(g => (g.querySelector('.label-name')?.textContent || '').includes(labelKo)) ||
				null;
			if (!grid) return null;

			const img = grid.querySelector('img.preview') || null;
			const rawSrc = safeText(img?.getAttribute('src') || '');
			const existing = cleanExisting(rawSrc);

			// 파일 선택이 있으면 업로드 우선
			const fileInput = grid.querySelector('input[type="file"]') || null;
			const file = fileInput?.files?.[0] || null;
			if (file) {
				const saved = await uploadOne(file);
				if (saved) {
					assign(saved);
					// UI도 영구 경로로 맞춰두기
					try {
						const item = fileInput.closest('.upload-item') || grid.querySelector('.upload-item') || null;
						if (item) {
							item.classList.add('is-filled');
							let pv = item.querySelector('img.preview');
							if (!pv) {
								pv = document.createElement('img');
								pv.className = 'preview';
								pv.alt = '';
								item.appendChild(pv);
							}
							pv.src = saved;
						}
					} catch (_) {}
					try { fileInput.value = ''; } catch (_) {}
					return saved;
				}
			}

			// 파일이 없으면 기존 경로 유지(단, blob/demo는 불가)
			if (existing) {
				assign(existing);
				return existing;
			}
			if (rawSrc && (isBlob(rawSrc) || isDemo(rawSrc) || isData(rawSrc))) {
				throw new Error(`${requiredLabel} image is a temporary preview (sample/blob/data URL) and cannot be saved. Please remove it and upload a real image file, then save again.`);
			}
			assign('');
			return '';
		};

		// 일정표(관광지/숙소) 이미지 업로드: 선택된 파일만 업로드해서 data에 반영
		const uploadScheduleImagesIfNeeded = async () => {
			try {
				const dayPanels = Array.from(document.querySelectorAll('.card-panel[id^="nday"]'));
				for (let di = 0; di < dayPanels.length; di++) {
					const dayPanel = dayPanels[di];
					const dayObj = data?.schedule?.days?.[di];
					if (!dayObj) continue;

					// 관광지 블록(.dfghs)
					const blocks = Array.from(dayPanel.querySelectorAll('.dfghs'));
					for (let bi = 0; bi < blocks.length; bi++) {
						const block = blocks[bi];
						const aObj = dayObj.attractions?.[bi];
						if (!aObj) continue;
						const inp = block.querySelector('.upload-grid input[type="file"]') || null;
						const file = inp?.files?.[0] || null;
						if (!file) {
							// blob/demo 저장 방지(선택 파일 없으면 기존만 유지)
							const img = block.querySelector('.upload-grid img.preview') || null;
							const raw = safeText(img?.getAttribute('src') || '');
							const existing = cleanExisting(raw);
							if (existing) aObj.image = existing;
							else if (raw && isBlob(raw)) aObj.image = '';
							continue;
						}
						const saved = await uploadOne(file);
						if (!saved) continue;
						aObj.image = saved;
						try {
							const item = inp.closest('.upload-item') || block.querySelector('.upload-grid .upload-item') || null;
							if (item) {
								item.classList.add('is-filled');
								let pv = item.querySelector('img.preview');
								if (!pv) {
									pv = document.createElement('img');
									pv.className = 'preview';
									pv.alt = '';
									item.appendChild(pv);
								}
								pv.src = saved;
							}
						} catch (_) {}
						try { inp.value = ''; } catch (_) {}
					}

					// 숙소 이미지(첫 Accommodation panel 기준)
					try {
						const accomPanel = Array.from(dayPanel.querySelectorAll('.sub-panel'))
							.find(p => {
								const h4 = p.querySelector('h4.grid-wrap-title');
								const eng = safeText(h4?.getAttribute('data-lan-eng') || '');
								return eng === 'Accommodation Information';
							});
						if (accomPanel && dayObj.accommodation) {
							const inp = accomPanel.querySelector('.upload-grid input[type="file"]') || null;
							const file = inp?.files?.[0] || null;
							if (file) {
								const saved = await uploadOne(file);
								if (saved) {
									dayObj.accommodation.image = saved;
									try {
										const item = inp.closest('.upload-item') || accomPanel.querySelector('.upload-grid .upload-item') || null;
										if (item) {
											item.classList.add('is-filled');
											let pv = item.querySelector('img.preview');
											if (!pv) {
												pv = document.createElement('img');
												pv.className = 'preview';
												pv.alt = '';
												item.appendChild(pv);
											}
											pv.src = saved;
										}
									} catch (_) {}
									try { inp.value = ''; } catch (_) {}
								}
							} else {
								const img = accomPanel.querySelector('.upload-grid img.preview') || null;
								const raw = safeText(img?.getAttribute('src') || '');
								const existing = cleanExisting(raw);
								if (existing) dayObj.accommodation.image = existing;
								else if (raw && isBlob(raw)) dayObj.accommodation.image = '';
							}
						}
					} catch (_) {}
				}
			} catch (e) {
				console.error('Template schedule images upload failed:', e);
				throw e;
			}
		};

		// 썸네일/상세 소개 이미지 업로드(필수)
		try {
			data.product = data.product || {};
			data.product.images = data.product.images || {};
			await uploadSingleFromGrid(
				'썸네일 이미지',
				'Thumbnail image',
				(v) => { data.product.images.thumbnail = v; },
				'Thumbnail'
			);
			await uploadSingleFromGrid(
				'상세 소개 이미지',
				'Detailed introduction image',
				(v) => { data.product.images.detailImage = v; },
				'Detailed introduction'
			);
		} catch (e) {
			// 썸네일/상세는 필수이므로 그대로 throw
			throw e;
		}

		// 상품 이미지 영역: 기존(표시 중인) 이미지 + 새로 선택된 파일 업로드 결과를 합쳐 저장
		try {
			const productGrid = findGridByLabelContains('상품 이미지') || findGridByLabelContains('Product image') ||
				Array.from(document.querySelectorAll('.grid-item')).find(g => (g.querySelector('.label-name')?.textContent || '').trim() === '상품 이미지');
			if (!productGrid) return;

			const productItems = Array.from(productGrid.querySelectorAll('.upload-item') || []);
			const finalList = [];

			// 기존 이미지(img.preview) 수집
			for (const it of productItems) {
				const src = cleanExisting(it.querySelector('img.preview')?.getAttribute('src') || '');
				if (src) finalList.push(src);
			}

			// 선택된 파일 업로드
			const fileInputs = Array.from(productGrid.querySelectorAll('input[type="file"]') || []);
			for (const inp of fileInputs) {
				const file = inp?.files?.[0];
				if (!file) continue;
				const saved = await uploadOne(file);
				if (!saved) continue;

				finalList.push(saved);

				// UI도 영구 경로로 맞춰두기(다음 저장/필수체크/미리보기 일관성)
				try {
					const item = inp.closest('.upload-item');
					if (item) {
						item.classList.add('is-filled');
						let img = item.querySelector('img.preview');
						if (!img) {
							img = document.createElement('img');
							img.className = 'preview';
							img.alt = '';
							item.appendChild(img);
						}
						img.src = saved;
					}
				} catch (_) {}

				// 중복 업로드 방지
				try { inp.value = ''; } catch (_) {}
			}

			data.product = data.product || {};
			data.product.images = data.product.images || {};
			data.product.images.productImages = Array.from(new Set(finalList)).filter(Boolean);
		} catch (e) {
			console.error('Template product images upload failed:', e);
			throw e;
		}

		// 일정표 이미지(관광지/숙소) 업로드 반영
		await uploadScheduleImagesIfNeeded();
	};

	// ----- API 저장 -----
	// createTemplate은 templateId가 있어야 업로드 로그(relatedId=templateId)를 남길 수 있으므로:
	// - 생성(create): 먼저 createTemplate → templateId 확보 → 파일 업로드(related_id=templateId) → updateTemplate로 data 반영
	// - 수정(update): 기존 흐름대로 업로드 → updateTemplate
	Promise.resolve()
		.then(async () => {
			if (action !== 'createTemplate') return null;

			// 1) 우선 템플릿 생성 (파일 없이 data만)
			const res = await fetch('../admin/backend/api/super-api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ action: 'createTemplate', ...payload })
			});
			if (res.status === 401) {
				alert('로그인이 필요합니다.');
				window.location.href = '../index.html';
				return null;
			}
			const json = await res.json().catch(() => ({}));
			if (!res.ok || json.success === false) throw new Error(json.message || `생성에 실패했습니다. (HTTP ${res.status})`);

			const newId = json?.data?.templateId || json?.data?.template?.templateId || null;
			if (!newId) throw new Error('템플릿 ID 생성에 실패했습니다.');

			templateId = String(newId);
			payload.templateId = templateId;
			return json;
		})
		.then(uploadUsageGuideIfNeeded)
		.then((fileMeta) => {
			// 저장 데이터에 안내문 파일 메타 포함
			try {
				if (fileMeta && fileMeta.filePath) {
					data.usageGuide.file = {
						filePath: fileMeta.filePath,
						originalName: fileMeta.originalName || '',
						fileName: fileMeta.fileName || '',
						fileSize: fileMeta.fileSize || null,
						mimeType: fileMeta.mimeType || 'application/pdf',
					};
				}
			} catch (_) { }
			return uploadTemplateImagesIfNeeded();
		})
		.then(async () => {
			// 2) 최종 반영은 updateTemplate로 통일(생성이든 수정이든)
			const finalAction = (templateId ? 'updateTemplate' : action);
			const res = await fetch('../admin/backend/api/super-api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ action: finalAction, ...payload })
			});
			if (res.status === 401) {
				alert('로그인이 필요합니다.');
				window.location.href = '../index.html';
				return null;
			}
			const json = await res.json().catch(() => ({}));
			if (!res.ok || json.success === false) {
				throw new Error(json.message || `저장에 실패했습니다. (HTTP ${res.status})`);
			}
			return json;
		})
		.then((json) => {
			if (!json) return;
			alert('저장되었습니다.');
			window.location.href = 'template-list.html';
		})
		.catch((e) => {
			console.error('Template save error:', e);
			alert(e?.message || '저장 중 오류가 발생했습니다.');
		})
		.finally(() => {
			if (btn) {
				btn.disabled = false;
				btn.classList.remove('is-loading');
			}
		});
}

function template_detail_sdaf(btn) {
	const list = (btn?.closest('.aside') || document).querySelector('.aside-row-list');
	if (!list) return;

	const day = list.querySelectorAll('.aside-row').length + 1;
	const lang = (typeof getCookie === 'function' ? (getCookie('lang') || 'eng') : 'eng');
	const isKo = /^(ko|kor)$/i.test(String(lang));
	const row = document.createElement('div');
	row.className = 'aside-row';
	row.innerHTML = `
		<div class="aside-row__subtitle" data-lan-eng="Day ${day}">${isKo ? `${day}일차` : `Day ${day}`}</div>
		<button type="button" class="jw-button aside-add-section" onclick="template_detail_sdaf_delete(this)"><img src="../image/minus.svg" alt=""></button>
	`;
	list.appendChild(row);

	// 메인 콘텐츠 Day 패널도 추가(1Day 패널을 복제)
	try {
		const base = document.getElementById('nday');
		if (base) {
			const clone = base.cloneNode(true);
			clone.id = `nday_${day}`;

			// jw_select 커스텀 셀렉트는 DOM 복제 시 이벤트가 복제되지 않아 "작동하지 않는 select"가 됩니다.
			// → clone 내부의 jw-select wrapper를 제거하고 native select를 복구한 뒤 jw_select()로 재초기화합니다.
			const unwrapAllJwSelect = (root) => {
				if (!root) return;
				// 1) wrapper 기반(정상 케이스)
				Array.from(root.querySelectorAll('.jw-select')).forEach((wrap) => {
					const sel = wrap.querySelector('select');
					const parent = wrap.parentElement;
					if (!sel || !parent) return;
					try {
						sel.classList.remove('jw-sr-only');
						if (!sel.classList.contains('select')) sel.classList.add('select');
					} catch (_) { }
					parent.insertBefore(sel, wrap);
					try { wrap.remove(); } catch (_) { parent.removeChild(wrap); }
				});
				// 2) sr-only만 남아있는 케이스(방어)
				Array.from(root.querySelectorAll('select.jw-sr-only')).forEach((sel) => {
					try {
						sel.classList.remove('jw-sr-only');
						if (!sel.classList.contains('select')) sel.classList.add('select');
					} catch (_) { }
				});
			};
			unwrapAllJwSelect(clone);

			// 헤더 텍스트 변경 (버튼 내부 텍스트가 "1Day" 형태)
			const headBtn = clone.querySelector('h3.grid-wrap-title button');
			if (headBtn) {
				// 이미지 태그는 유지하고 텍스트만 교체
				const img = headBtn.querySelector('img');
				headBtn.innerHTML = `${day}Day ` + (img ? img.outerHTML : '');
				// 아코디언: 각 Day별로 토글되도록 this 전달
				headBtn.setAttribute('onclick', 'ndasdtoggle(this)');
			}

			// 값 초기화: input/textarea/editor/upload/select
			clone.querySelectorAll('input.form-control').forEach((i) => {
				// 시간 select 제외
				const t = (i.getAttribute('type') || 'text').toLowerCase();
				if (t === 'file') return;
				i.value = '';
				i.disabled = false;
				i.readOnly = false;
			});
			clone.querySelectorAll('textarea').forEach((t) => { t.value = ''; });
			clone.querySelectorAll('.jw-editor .jweditor').forEach((ed) => { ed.innerHTML = ''; });
			clone.querySelectorAll('select').forEach((s) => {
				s.disabled = false;
				try { s.selectedIndex = 0; } catch (_) { }
			});
			clone.querySelectorAll('.upload-item').forEach((it) => {
				it.classList.remove('is-filled');
				it.querySelectorAll('img.preview').forEach((img) => img.remove());
				it.querySelectorAll('.btn-close, .jw-button.btn-close').forEach((b) => b.remove());
				// 업로드 UI가 이전 미리보기 상태에서 숨겨졌을 수 있으므로 다시 표시
				try {
					const lab = it.querySelector('label.inputFile');
					const btnUp = it.querySelector('.btn-upload');
					if (lab) lab.style.display = '';
					if (btnUp) btnUp.style.display = '';
				} catch (_) { }
			});

			// 관광지 블록(dfghs)은 1개만 남기고 나머지 제거
			const blocks = Array.from(clone.querySelectorAll('.dfghs'));
			blocks.forEach((b, idx) => { if (idx > 0) b.remove(); });
			// 남은 1개 블록도 값 초기화(시간/텍스트/이미지)
			try {
				const b0 = blocks[0] || clone.querySelector('.dfghs');
				if (b0) {
					b0.querySelectorAll('input.form-control').forEach((i) => { const t = (i.getAttribute('type')||'text').toLowerCase(); if (t !== 'file') i.value=''; });
					b0.querySelectorAll('.jw-editor .jweditor').forEach((ed) => { ed.innerHTML = ''; });
					b0.querySelectorAll('.upload-item').forEach((it) => {
						it.classList.remove('is-filled');
						it.querySelectorAll('img.preview').forEach((img) => img.remove());
						it.querySelectorAll('.btn-close, .jw-button.btn-close').forEach((b) => b.remove());
						try {
							const lab = it.querySelector('label.inputFile');
							const btnUp = it.querySelector('.btn-upload');
							if (lab) lab.style.display = '';
							if (btnUp) btnUp.style.display = '';
						} catch (_) { }
					});
				}
			} catch (_) { }

			// DOM 삽입: 마지막 Day 패널 뒤로
			const all = Array.from(document.querySelectorAll('.card-panel[id^="nday"]'));
			const last = all.length ? all[all.length - 1] : base;
			last.parentElement?.insertBefore(clone, last.nextSibling);

			// 새로 추가된 에디터/셀렉트 재초기화
			try { if (typeof board === 'function') board(); } catch (_) { }
			try { if (typeof jw_select === 'function') jw_select(); } catch (_) { }
		}
	} catch (_) { }

	// 위에서 계산한 lang을 재사용(중복 선언 방지)
	if (typeof language_apply === 'function') language_apply(lang);
	try { if (typeof window.__updateTemplateComponentProgress === 'function') window.__updateTemplateComponentProgress(); } catch (_) { }
}

function template_detail_sdaf_delete(btn) {
	const row = btn?.closest('.aside-row');
	if (!row) return;
	const list = row.closest('.aside-row-list');
	const title = (row.querySelector('.aside-row__subtitle')?.textContent || '').trim();
	let day = null;
	try {
		// 언어 무관: "1일차", "Day 2", "1st day" 모두 숫자 추출
		const m = title.match(/(\d+)/);
		if (m) day = parseInt(m[1], 10);
	} catch (_) { day = null; }
	row.remove();
	if (!list) return;

	// 메인 콘텐츠 Day 패널 삭제(2일차 이상만 삭제 허용)
	try {
		if (day && day > 1) {
			const panel = document.getElementById(`nday_${day}`);
			if (panel) panel.remove();
		}
	} catch (_) { }

	// 패널/aside 재번호
	try {
		const panels = Array.from(document.querySelectorAll('.card-panel[id^="nday"]'));
		panels.forEach((p, idx) => {
			const n = idx + 1;
			p.id = (n === 1) ? 'nday' : `nday_${n}`;
			const headBtn = p.querySelector('h3.grid-wrap-title button');
			if (headBtn) {
				const img = headBtn.querySelector('img');
				headBtn.innerHTML = `${n}Day ` + (img ? img.outerHTML : '');
				headBtn.setAttribute('onclick', 'ndasdtoggle(this)');
			}
		});
	} catch (_) { }

	list.querySelectorAll('.aside-row').forEach((el, i) => {
		const n = i + 1;
		const sub = el.querySelector('.aside-row__subtitle');
		if (!sub) return;
		sub.setAttribute('data-lan-eng', `Day ${n}`);
		const lang = (typeof getCookie === 'function' ? (getCookie('lang') || 'eng') : 'eng');
		const isKo = /^(ko|kor)$/i.test(String(lang));
		sub.textContent = isKo ? `${n}일차` : `Day ${n}`;
	});

	const lang = getCookie('lang') || 'eng';
	if (typeof language_apply === 'function') language_apply(lang);
	try { if (typeof window.__updateTemplateComponentProgress === 'function') window.__updateTemplateComponentProgress(); } catch (_) { }
}

function template_detail_sdaf_cate(scope) {
	const root = scope ? (scope instanceof Element ? scope : document.querySelector(scope)) : document;
	const cat1 = root.querySelector('[data-category="1"]');
	const sel2 = root.querySelector('select[data-category="2"]');
	if (!cat1 || !sel2) return;

	const tag = cat1.tagName;
	const type = (cat1.type || '').toLowerCase();
	let hasValue = false;

	if (type === 'radio') {
		if (cat1.name) {
			const area = cat1.form || root;
			try {
				hasValue = !!area.querySelector(`input[type="radio"][name="${CSS.escape(cat1.name)}"]:checked`);
			} catch (_) {
				hasValue = !!area.querySelector(`input[type="radio"][name="${cat1.name}"]:checked`);
			}
		} else {
			hasValue = !!cat1.checked;
		}
	} else if (type === 'checkbox') {
		hasValue = !!cat1.checked;
	} else if (type === 'file') {
		hasValue = !!(cat1.files && cat1.files.length);
	} else if (tag === 'SELECT') {
		hasValue = (cat1.value ?? '') !== '';
	} else {
		hasValue = ((cat1.value || '').trim().length > 0);
	}

	if (!hasValue) sel2.setAttribute('disabled', '');
	else sel2.removeAttribute('disabled');

	// jw_select 커스텀 셀렉트의 disabled UI도 동기화
	const wrap = sel2.closest('.jw-select');
	if (wrap) {
		wrap.classList.toggle('is-disabled', sel2.disabled);
		const box = wrap.querySelector('.jw-selected');
		if (box) {
			box.setAttribute('aria-disabled', sel2.disabled ? 'true' : 'false');
			box.tabIndex = sel2.disabled ? -1 : 0;
		}
	}
}

function ndasdtoggle() {
	// 기존(1Day) 호환 + 신규: 버튼(this)을 넘기면 해당 Day 패널만 토글
	const arg = arguments && arguments[0] ? arguments[0] : null;
	let panel = null;
	try {
		if (arg && arg.closest) {
			panel = arg.closest('.card-panel[id^="nday"]');
		}
	} catch (_) { panel = null; }
	if (!panel) panel = document.getElementById('nday');
	if (!panel) return false;
	panel.classList.toggle('off');
	const isOff = panel.classList.contains('off');

	// IMPORTANT:
	// - CSS에 card-panel.off 처리가 없어 실제로 접힘이 보이지 않는 문제가 있어,
	//   JS에서 Day 패널 본문을 직접 hide/show 처리합니다.
	try {
		const children = Array.from(panel.children || []);
		children.forEach((ch) => {
			if (!ch || !(ch instanceof Element)) return;
			// 제목(아코디언 버튼)과 구분선은 유지
			if (ch.matches('h3.grid-wrap-title') || ch.matches('i.line')) {
				ch.style.display = '';
				return;
			}
			ch.style.display = isOff ? 'none' : '';
		});
	} catch (_) { }

	return isOff;
}

function template_detail_dfghs(btn) {
	const panel = btn?.closest('.sub-panel');
	if (!panel) return;

	// DOM 복제 시 jw-select 이벤트가 복제되지 않으므로,
	// "현재 패널의 첫 dfghs"를 기준으로 wrapper를 제거한 clean clone을 생성합니다.
	const source = panel.querySelector('.dfghs') || document.querySelector('.dfghs');
	if (!source) return;
	const copy = source.cloneNode(true);
	copy.removeAttribute('id');
	// jw-select wrapper 제거 → native select 복구
	try {
		Array.from(copy.querySelectorAll('.jw-select')).forEach((wrap) => {
			const sel = wrap.querySelector('select');
			const parent = wrap.parentElement;
			if (!sel || !parent) return;
			sel.classList.remove('jw-sr-only');
			if (!sel.classList.contains('select')) sel.classList.add('select');
			parent.insertBefore(sel, wrap);
			wrap.remove();
		});
		Array.from(copy.querySelectorAll('select.jw-sr-only')).forEach((sel) => {
			sel.classList.remove('jw-sr-only');
			if (!sel.classList.contains('select')) sel.classList.add('select');
		});
	} catch (_) { }
	// 새 관광지 블록은 입력값/이미지/에디터를 비워서 추가되어야 함
	try {
		copy.querySelectorAll('input.form-control').forEach((i) => {
			const t = (i.getAttribute('type') || 'text').toLowerCase();
			if (t !== 'file') i.value = '';
			i.disabled = false;
			i.readOnly = false;
		});
		copy.querySelectorAll('.jw-editor .jweditor').forEach((ed) => { ed.innerHTML = ''; });
		copy.querySelectorAll('select').forEach((s) => { s.disabled = false; try { s.selectedIndex = 0; } catch (_) { } });
		copy.querySelectorAll('.upload-item').forEach((it) => {
			it.classList.remove('is-filled');
			it.querySelectorAll('img.preview').forEach((img) => img.remove());
			it.querySelectorAll('.btn-close, .jw-button.btn-close').forEach((b) => b.remove());
			try {
				const lab = it.querySelector('label.inputFile');
				const btnUp = it.querySelector('.btn-upload');
				if (lab) lab.style.display = '';
				if (btnUp) btnUp.style.display = '';
			} catch (_) { }
		});
	} catch (_) { }

	panel.appendChild(copy);

	// 새로 추가된 select를 jw_select로 변환(이미 변환된 것은 안전하게 무시됨)
	if (typeof jw_select === 'function') {
		try { jw_select(); } catch (_) { }
	}
	// 새 에디터 초기화
	try { if (typeof board === 'function') board(); } catch (_) { }

	// 스크롤/포커스
	const getScrollParent = (el) => {
		let p = el.parentElement;
		while (p && p !== document.body) {
			const st = getComputedStyle(p);
			if ((st.overflowY === 'auto' || st.overflowY === 'scroll') && p.scrollHeight > p.clientHeight) return p;
			p = p.parentElement;
		}
		return document.scrollingElement || document.documentElement;
	};
	const offsetWithin = (el, parent) => {
		let y = 0, cur = el;
		while (cur && cur !== parent) { y += cur.offsetTop; cur = cur.offsetParent; }
		return y;
	};
	requestAnimationFrame(() => {
		const scroller = getScrollParent(copy);
		const top = offsetWithin(copy, scroller);
		try {
			scroller.scrollTo({ top: Math.max(top - 8, 0), behavior: 'smooth' });
		} catch {
			copy.scrollIntoView({ block: 'start', behavior: 'smooth' });
		}

		const focusable = copy.querySelector('input,select,textarea,button,a[href],[tabindex]:not([tabindex="-1"])');
		if (focusable) { try { focusable.focus({ preventScroll: true }); } catch (_) { } }
	});
	return copy;
}

function template_detail_hsdfghsh(it) {
	const box = it?.closest('.dfghs');
	if (!box) return false;
	box.remove();
	try { if (typeof window.__updateTemplateComponentProgress === 'function') window.__updateTemplateComponentProgress(); } catch (_) { }
	return true;
}

function template_detail_sfg1(btn) {
	const panel = btn?.closest('.card-panel');
	if (!panel) return;
	const tbody = panel.querySelector('tbody');
	if (!tbody) return;

	const count = tbody.querySelectorAll('tr').length + 1;
	const tr = document.createElement('tr');
	tr.innerHTML = `
		<td class="is-center">${count}</td>
		<td class="is-center"><div class="cell"><input type="text" class="form-control" value="" placeholder="Option name"></div></td>
		<td class="is-center"><div class="cell"><input type="text" class="form-control" value="" inputmode="numeric" placeholder="Enter numbers"></div></td>
		<td class="is-center jw-center">
			<button type="button" class="jw-button" aria-label="row delete" onclick="template_detail_sfg1d(this)"><img src="../image/trash.svg" alt=""></button>
		</td>
	`;
	tbody.appendChild(tr);
}

function template_detail_sfg1d(btn) {
	const tr = btn?.closest('tr');
	if (!tr) return;
	const tbody = tr.parentElement;
	if (tbody) {
		const rows = tbody.querySelectorAll('tr');
		if (rows.length <= 1) {
			// 기획서(테이블 삭제 시) 모달
			if (typeof modal === 'function') modal('/admin/super/template-detail-modal2.html', '580px', '252px');
			return;
		}
	}
	tr.remove();
	if (!tbody) return;

	Array.from(tbody.querySelectorAll('tr')).forEach((row, i) => {
		const firstTd = row.querySelector('td');
		if (firstTd) firstTd.textContent = i + 1;
	});
}

function template_detail_delete_confirm() {
	// template-detail.html 상단 "삭제" 버튼의 실제 삭제 처리
	let templateId = null;
	try {
		const urlParams = new URLSearchParams(window.location.search);
		templateId = urlParams.get('id') || urlParams.get('templateId') || window.__templateId || null;
	} catch (_) { templateId = window.__templateId || null; }
	if (!templateId) {
		alert('삭제할 템플릿 ID가 없습니다.');
		return;
	}

	fetch('../admin/backend/api/super-api.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		credentials: 'same-origin',
		body: JSON.stringify({ action: 'deleteTemplate', templateId })
	})
		.then(async (res) => {
			if (res.status === 401) {
				alert('로그인이 필요합니다.');
				window.location.href = '../index.html';
				return null;
			}
			const json = await res.json().catch(() => ({}));
			if (!res.ok || json.success === false) throw new Error(json.message || `삭제에 실패했습니다. (HTTP ${res.status})`);
			return json;
		})
		.then((json) => {
			if (!json) return;
			alert('삭제되었습니다.');
			window.location.href = 'template-list.html';
		})
		.catch((e) => {
			console.error(e);
			alert(e?.message || '삭제 중 오류가 발생했습니다.');
		});
}

function template_detail_sfg3(btn) {
	const panel = btn?.closest('.card-panel');
	if (!panel) return;
	const tbody = panel.querySelector('tbody');
	if (!tbody) return;

	const count = tbody.querySelectorAll('tr').length + 1;
	const tr = document.createElement('tr');
	tr.innerHTML = `
		<td class="is-center">${count}</td>
		<td class="is-center">
			<div class="cell">
				<input type="text" class="form-control" value="" placeholder="please enter your content">
			</div>
		</td>
		<td class="is-center jw-center">
			<button type="button" class="jw-button" aria-label="row delete" onclick="trash_typeA(this);"><img src="../image/trash.svg" alt=""></button>
		</td>
	`;
	tbody.appendChild(tr);
	const ip = tr.querySelector('input.form-control');
	if (ip) ip.focus();
}

// ── Inquiries 안읽은 메시지 뱃지 ──
let _inquiryUnreadTimer = null;

function startInquiryUnreadPolling() {
	fetchInquiryUnreadCount();
	_inquiryUnreadTimer = setInterval(fetchInquiryUnreadCount, 10000);
}

function fetchInquiryUnreadCount() {
	fetch('../admin/backend/api/super-api.php?action=getMessageUnreadCount', { credentials: 'same-origin' })
		.then(res => res.json())
		.then(data => {
			const badge = document.getElementById('inquiryUnreadBadge');
			if (!badge) return;
			const count = (data.success && data.data) ? parseInt(data.data.unreadCount, 10) || 0 : 0;
			if (count > 0) {
				badge.textContent = count > 99 ? '99+' : String(count);
				badge.style.display = '';
			} else {
				badge.style.display = 'none';
			}
		})
		.catch(() => {});
}
