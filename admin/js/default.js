function jw_select() {
	const $ = (s, c = document) => c.querySelector(s);
	const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
	const el = (t, cls) => { const n = document.createElement(t); if (cls) n.className = cls; return n; };
	const offWidth = (s) => {
		const t = el('div'); t.style.cssText =
			'position:absolute;visibility:hidden;white-space:nowrap;display:inline-block;'; t.textContent = s;
		document.body.appendChild(t); const w = t.offsetWidth; t.remove(); return w;
	};

	if (!window.__jwselect_docbound) {
		document.addEventListener('click', () => {
			document.querySelectorAll('.jw-select.open').forEach(wrap => {
				wrap.classList.remove('open');
				const box = wrap.querySelector('.jw-selected');
				if (box) box.classList.remove('active'), box.setAttribute('aria-expanded', 'false');
			});
		});
		window.__jwselect_docbound = true;
	}

	document.querySelectorAll('.select').forEach(nativeSel => {
		nativeSel.classList.remove('select');
		nativeSel.classList.add('jw-sr-only');

		const wrap = el('div', 'jw-select');
		nativeSel.parentNode.insertBefore(wrap, nativeSel);
		wrap.appendChild(nativeSel);

		const box = el('div', 'jw-selected');
		box.setAttribute('role', 'button');
		box.setAttribute('aria-haspopup', 'listbox');
		box.setAttribute('aria-expanded', 'false');
		wrap.appendChild(box);

		const setBox = () => {
			const opt = nativeSel.selectedOptions[0] || nativeSel.options[0];
			const label = opt ? opt.textContent : '';
			const icon = opt?.dataset?.icon;
			box.innerHTML = icon ? `<i class="${icon}"></i> ${label}` : label;
		};
		setBox();

		const list = el('ul', 'jw-select-list');
		list.setAttribute('role', 'listbox');
		wrap.appendChild(list);

		let maxWidth = 0;
		Array.from(nativeSel.options).forEach(o => {
			const li = el('li', 'jw-select-item');
			li.setAttribute('role', 'option');
			li.dataset.value = o.value;
			li.innerHTML = o.dataset?.icon ? `<i class="${o.dataset.icon}"></i> ${o.textContent}` : o.textContent;

			// ✅ disabled 옵션 반영
			if (o.disabled) {
				li.setAttribute('aria-disabled', 'true');
				li.classList.add('is-disabled');
			}

			if (o.selected) li.setAttribute('aria-selected', 'true');
			list.appendChild(li);
			maxWidth = Math.max(maxWidth, offWidth(o.textContent));
		});

		if (maxWidth) wrap.style.minWidth = (maxWidth + 40) + 'px';
		if (nativeSel.dataset.direction === 'up') wrap.classList.add('up');

		// ✅ 전체 select disabled 처리
		if (nativeSel.disabled) {
			wrap.classList.add('is-disabled');
			box.setAttribute('aria-disabled', 'true');
			box.tabIndex = -1;
		}

		const openList = () => {
			if (nativeSel.disabled) return; // 전체 비활성화면 열지 않음
			wrap.classList.add('open'); box.classList.add('active'); box.setAttribute('aria-expanded', 'true');
		};
		const closeList = () => {
			wrap.classList.remove('open'); box.classList.remove('active');
			box.setAttribute('aria-expanded', 'false');
		};

		const choose = (itemEl) => {
			// ✅ 항목이 disabled면 선택 막기
			if (itemEl.getAttribute('aria-disabled') === 'true') return;

			list.querySelectorAll('.jw-select-item[aria-selected="true"]').forEach(li => li.removeAttribute('aria-selected'));
			itemEl.setAttribute('aria-selected', 'true');
			nativeSel.value = itemEl.dataset.value;
			nativeSel.dispatchEvent(new Event('change', { bubbles: true }));
			setBox(); closeList();
		};

		box.addEventListener('click', (e) => {
			e.stopPropagation(); wrap.classList.contains('open') ? closeList() : openList();
		});
		list.addEventListener('click', (e) => {
			const item = e.target.closest('.jw-select-item'); if (!item) return;
			e.stopPropagation(); choose(item);
		});
	});
}

function setCookie(name, value, days) { const d = new Date(); d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000); document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`; }
function getCookie(name) { const seg = `; ${document.cookie}`.split(`; ${name}=`); return (seg.length === 2) ? decodeURIComponent(seg.pop().split(';').shift()) : null; }

// =========================================================
// Popup message i18n safety-net (Admin)
// - Some pages still have Korean hard-coded alert/confirm/throw messages.
// - Admin UI supports eng/tl only, but popups must be English as requested.
// - If a message contains Korean, translate common phrases to English.
//   Unknown Korean messages fall back to a generic English message.
// =========================================================
(function patchPopupToEnglish() {
	if (window.__jwPopupEnglishPatched) return;
	window.__jwPopupEnglishPatched = true;

	const hasKorean = (s) => /[가-힣]/.test(String(s || ''));

	const translateAlert = (msg) => {
		let s = String(msg ?? '');
		if (!hasKorean(s)) return s;

		// normalize whitespace
		s = s.replace(/\s+/g, ' ').trim();

		// common replacements (order matters: more specific first)
		const rules = [
			[/^관리자 로그인이 필요합니다\.?$/g, 'Admin login required.'],
			[/^로그인이 필요합니다\.?$/g, 'Login required.'],
			[/^저장되었습니다\.?$/g, 'Saved.'],
			[/^임시저장되었습니다\.?$/g, 'Temporarily saved.'],
			[/^삭제되었습니다\.?$/g, 'Deleted.'],
			[/^삭제할 .* 없습니다\.?$/g, 'Nothing to delete.'],
			[/^상품명이 필요합니다\.?$/g, 'Product name is required.'],
			[/^카테고리를 선택해주세요\.?$/g, 'Please select a category.'],
			[/^필수 필드가 누락되었습니다.*$/g, 'Required fields are missing.'],
			[/^필수 항목을 모두 입력해주세요.*$/g, 'Please fill in all required fields.'],
			[/^시작일과 종료일을 모두 선택해주세요\.?$/g, 'Please select both start and end dates.'],
			[/^다운로드 중 오류가 발생했습니다\.?$/g, 'An error occurred while downloading.'],
			[/불러오는 중 오류가 발생했습니다\.?$/g, 'An error occurred while loading.'],
			[/저장 중 오류가 발생했습니다\.?$/g, 'An error occurred while saving.'],
			[/삭제 중 오류가 발생했습니다\.?$/g, 'An error occurred while deleting.'],
			[/업로드 중 오류가 발생했습니다\.?$/g, 'An error occurred while uploading.'],
			[/등록 중 오류가 발생했습니다\.?$/g, 'An error occurred while creating.'],
			[/수정 중 오류가 발생했습니다.*$/g, 'An error occurred while updating.'],
			[/^올바른 이메일 형식을 입력해주세요\.?$/g, 'Please enter a valid email address.'],
			[/^아이디를 입력해주세요\.?$/g, 'Please enter your ID.'],
			[/^비밀번호를 입력해주세요.*$/g, 'Please enter a password.'],
			[/^계정 정보를 찾을 수 없습니다\.?$/g, 'Account information not found.'],
			[/^객실을 선택해주세요\.?$/g, 'Please select rooms.'],
			[/^객실 수용 인원이 부족합니다\.?$/g, 'Insufficient room capacity.'],
			[/^비밀번호가 변경되었습니다\.?$/g, 'Password has been changed.'],
		];

		for (const [re, out] of rules) {
			if (re.test(s)) return out;
		}

		// Requirement: NO Korean anywhere → unknown Korean must fall back to English.
		return 'Please check the information.';
	};

	const translateConfirm = (msg) => {
		let s = String(msg ?? '');
		if (!hasKorean(s)) return s;
		s = s.replace(/\s+/g, ' ').trim();

		const rules = [
			[/삭제하시겠습니까\??/g, 'Are you sure you want to delete?'],
			[/저장하시겠습니까\??/g, 'Do you want to save?'],
			[/로그아웃하시겠습니까\??/g, 'Do you want to log out?'],
		];
		for (const [re, out] of rules) {
			if (re.test(s)) return out;
		}
		return 'Are you sure?';
	};

	const __alert = window.alert ? window.alert.bind(window) : (m) => void m;
	const __confirm = window.confirm ? window.confirm.bind(window) : () => true;

	window.alert = (message) => __alert(translateAlert(message));
	window.confirm = (message) => __confirm(translateConfirm(message));
})();

function getLangText(el, lang) {
	lang = String(lang || '').toLowerCase();
	// Admin UI requirement: only eng/tl are supported. Default to English.
	if (lang === 'tl') return el.getAttribute('data-lan-tl') || el.getAttribute('data-lan-eng');
	return el.getAttribute('data-lan-eng') || el.getAttribute('data-lan-tl');
}

// =========================================================
// Admin "NO Korean" guard
// - Admin supports ONLY English/Tagalog. English is the default.
// - Ensure Korean never appears (even briefly) by:
//   1) Hiding body via CSS until we mark ready (see admin/css/a_reset.css)
//   2) Forcing lang cookie to eng/tl only
//   3) Applying language to data-lan-* nodes
//   4) Scrubbing any remaining Hangul text/attributes as a safety net
// =========================================================
function __admin_force_lang_cookie() {
	try {
		const cur = String(getCookie('lang') || '').toLowerCase();
		const lang = (cur === 'tl') ? 'tl' : 'eng';
		setCookie('lang', lang, 365);
		return lang;
	} catch (_) {
		try { setCookie('lang', 'eng', 365); } catch (_) {}
		return 'eng';
	}
}

function __admin_scrub_hangul(root = document.body) {
	try {
		if (!root) return;
		const hasHangul = (s) => /[가-힣]/.test(String(s || ''));
		const stripHangul = (s) => String(s || '').replace(/[가-힣]+/g, '').replace(/\s{2,}/g, ' ').trim();
		const ensureEnglish = (orig, cleaned, kind) => {
			// Policy update:
			// - "No Korean" applies to UI strings (i18n-marked), NOT DB content.
			// - Never inject a generic error-like message into normal page content.
			if (!hasHangul(orig)) return cleaned;
			return (cleaned && cleaned.length) ? cleaned : orig;
		};

		const isI18nUiEl = (el) => {
			try {
				if (!el || !el.closest) return false;
				return !!el.closest('[data-lan-eng],[data-lan-tl],[data-lan-ko],[data-lan-kor],[data-i18n],[data-i18n-placeholder],[data-i18n-title],[data-i18n-alt]');
			} catch (_) {
				return false;
			}
		};

		// Text nodes
		try {
			const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
			const nodes = [];
			while (walker.nextNode()) nodes.push(walker.currentNode);
			for (const n of nodes) {
				if (!n || !n.nodeValue) continue;
				// Never touch script/style text to avoid breaking JS/CSS parsing/execution.
				try {
					const p = n.parentElement;
					if (p && (p.tagName === 'SCRIPT' || p.tagName === 'STYLE' || p.tagName === 'NOSCRIPT')) continue;
				} catch (_) {}
				// Do not scrub content explicitly marked as "skip" (DB content areas)
				try {
					const p = n.parentElement || n.parentNode;
					if (p && p.closest && p.closest('[data-skip-hangul-scrub="1"]')) continue;
				} catch (_) {}
				// Only scrub i18n-managed UI text
				try {
					const p = n.parentElement;
					if (!isI18nUiEl(p)) continue;
				} catch (_) { continue; }
				if (!hasHangul(n.nodeValue)) continue;
				const orig = n.nodeValue;
				const cleaned = ensureEnglish(orig, stripHangul(orig), 'text');
				n.nodeValue = cleaned;
			}
		} catch (_) {}

		// Common attributes
		try {
			const attrs = ['placeholder', 'title', 'aria-label', 'alt'];
			root.querySelectorAll('*').forEach((el) => {
				if (!el || !el.getAttribute) return;
				// Do not scrub content explicitly marked as "skip" (DB content areas)
				try { if (el.closest && el.closest('[data-skip-hangul-scrub="1"]')) return; } catch (_) {}
				// Only scrub i18n-managed UI attributes
				if (!isI18nUiEl(el)) return;
				for (const a of attrs) {
					const v = el.getAttribute(a);
					if (!v || !hasHangul(v)) continue;
					if (a === 'alt') el.setAttribute(a, 'Image');
					else if (a === 'placeholder') el.setAttribute(a, 'Please enter a value.');
					else {
						const kind = (a === 'placeholder') ? 'placeholder' : (a === 'alt' ? 'alt' : 'title');
						el.setAttribute(a, ensureEnglish(v, stripHangul(v), kind));
					}
				}
			});
		} catch (_) {}

		// Dynamic content safety net:
		// Many admin pages render rows/cards asynchronously after initial load.
		// Ensure newly inserted DOM is also scrubbed so no Hangul leaks through.
		try {
			if (!window.__admin_hangul_observer && root && root.ownerDocument) {
				let busy = false;
				const obs = new MutationObserver((mutations) => {
					if (busy) return;
					busy = true;
					try {
						for (const m of mutations) {
							if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
								m.addedNodes.forEach((node) => {
									try {
										if (node && node.nodeType === Node.ELEMENT_NODE) {
											// Skip scripts/styles to avoid altering code.
											const tag = node.tagName;
											if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') return;
											__admin_scrub_hangul(node);
										}
										else if (node && node.nodeType === Node.TEXT_NODE && node.nodeValue && hasHangul(node.nodeValue)) {
											try {
												const p = node.parentElement;
												if (p && (p.tagName === 'SCRIPT' || p.tagName === 'STYLE' || p.tagName === 'NOSCRIPT')) return;
											} catch (_) {}
											const orig = node.nodeValue;
											node.nodeValue = ensureEnglish(orig, stripHangul(orig), 'text');
										}
									} catch (_) {}
								});
							} else if (m.type === 'attributes' && m.target && m.target.nodeType === Node.ELEMENT_NODE) {
								try { __admin_scrub_hangul(m.target); } catch (_) {}
							}
						}
					} finally {
						busy = false;
					}
				});
				obs.observe(root, {
					childList: true,
					subtree: true,
					attributes: true,
					attributeFilter: ['placeholder', 'title', 'aria-label', 'alt']
				});
				window.__admin_hangul_observer = obs;
			}
		} catch (_) {}
	} catch (_) {}
}

function language_apply(lang) {
	lang = String(lang || '').toLowerCase();
	if (lang !== 'eng' && lang !== 'tl' && lang !== 'kor' && lang !== 'ko') lang = 'eng';
	if (lang === 'ko') lang = 'kor'; // normalize
	document.querySelectorAll('[data-lan-eng],[data-lan-tl],[data-lan-kor],[data-lan-ko]').forEach(el => {
		const txt = getLangText(el, lang);
		if (!txt) return;

		const tag = el.tagName;
		if (tag === 'INPUT' || tag === 'TEXTAREA') {
			if (el.hasAttribute('placeholder')) el.setAttribute('placeholder', txt);
			else el.value = txt;
		} else if (tag === 'IMG') {
			el.setAttribute('alt', txt);
		} else {
			el.textContent = txt;
		}
	});
	
	// placeholder 다국어 처리 (data-lan-eng-placeholder 속성)
	document.querySelectorAll('[data-lan-eng-placeholder]').forEach(el => {
		const placeholderEng = el.getAttribute('data-lan-eng-placeholder');
		const placeholderTl = el.getAttribute('data-lan-tl-placeholder');
		const placeholderKor = el.getAttribute('data-lan-kor-placeholder') || el.getAttribute('data-lan-ko-placeholder');
		if (lang === 'eng' && placeholderEng) {
			el.setAttribute('placeholder', placeholderEng);
		} else if (lang === 'tl') {
			el.setAttribute('placeholder', placeholderTl || placeholderEng || el.getAttribute('placeholder') || '');
		} else if ((lang === 'kor' || lang === 'ko') && placeholderKor) {
			el.setAttribute('placeholder', placeholderKor);
		}
	});
	
	// 이미지 alt 다국어 처리 (data-lan-eng-alt 속성)
	document.querySelectorAll('[data-lan-eng-alt]').forEach(el => {
		const altEng = el.getAttribute('data-lan-eng-alt');
		const altTl = el.getAttribute('data-lan-tl-alt');
		const altKor = el.getAttribute('data-lan-kor-alt') || el.getAttribute('data-lan-ko-alt');
		if (lang === 'eng' && altEng) {
			el.setAttribute('alt', altEng);
		} else if (lang === 'tl') {
			el.setAttribute('alt', altTl || altEng || el.getAttribute('alt') || '');
		} else if ((lang === 'kor' || lang === 'ko') && altKor) {
			el.setAttribute('alt', altKor);
		}
	});
	
	// select 옵션 다국어 처리
	document.querySelectorAll('select option[data-lan-eng]').forEach(option => {
		const txtEng = option.getAttribute('data-lan-eng');
		const txtTl = option.getAttribute('data-lan-tl');
		const txtKor = option.getAttribute('data-lan-kor') || option.getAttribute('data-lan-ko');
		if (lang === 'eng' && txtEng) {
			option.textContent = txtEng;
		} else if (lang === 'tl') {
			option.textContent = txtTl || txtEng || option.textContent;
		} else if ((lang === 'kor' || lang === 'ko') && txtKor) {
			option.textContent = txtKor;
		} else if (lang === 'eng') {
			option.textContent = txtEng;
		} else {
			option.textContent = txtKor || option.getAttribute('data-lan-eng') || option.textContent;
		}
	});

	// jw_select(커스텀 셀렉트) 텍스트 동기화:
	// - option.textContent는 바뀌지만, 커스텀 UI(.jw-selected/.jw-select-list)는 자동 반영이 안 됨
	try {
		if (typeof window.refreshAllJwSelect === 'function') window.refreshAllJwSelect();
	} catch (_) { }

	document.querySelectorAll('.lang-text').forEach(node => {
		// 버튼 텍스트는 "다음으로 바뀔 언어"를 표시
		if (lang === 'eng') node.textContent = '한국어';
		else if (lang === 'kor') node.textContent = 'Tagalog';
		else node.textContent = 'English'; // tl -> eng
	});
	
	// HTML lang 속성 업데이트
	const htmlLang = document.getElementById('html-lang');
	if (htmlLang) {
		if (lang === 'tl') htmlLang.setAttribute('lang', 'tl');
		else if (lang === 'kor') htmlLang.setAttribute('lang', 'ko');
		else htmlLang.setAttribute('lang', 'en');
	}
	
	// 언어 변경 이벤트 발생 (다른 스크립트에서 동적 콘텐츠 업데이트용)
	window.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang } }));
}

// jw_select로 렌더된 커스텀 셀렉트 전체 갱신 (옵션 텍스트/disabled/selected 반영)
window.refreshAllJwSelect = function refreshAllJwSelect() {
	try {
		document.querySelectorAll('.jw-select').forEach((wrap) => {
			const nativeSel = wrap.querySelector('select');
			const box = wrap.querySelector('.jw-selected');
			const list = wrap.querySelector('.jw-select-list');
			if (!nativeSel || !box || !list) return;

			// disabled 동기화
			if (nativeSel.disabled) {
				wrap.classList.add('is-disabled');
				box.setAttribute('aria-disabled', 'true');
				box.tabIndex = -1;
			} else {
				wrap.classList.remove('is-disabled');
				box.removeAttribute('aria-disabled');
				box.tabIndex = 0;
			}

			// selected 박스 텍스트
			const opt = nativeSel.selectedOptions?.[0] || nativeSel.options?.[0] || null;
			const icon = opt?.dataset?.icon || null;
			const label = opt ? (opt.textContent || '') : '';
			box.innerHTML = icon ? `<i class="${icon}"></i> ${label}` : label;

			// 리스트 재생성
			list.innerHTML = '';
			Array.from(nativeSel.options || []).forEach((o) => {
				const li = document.createElement('li');
				li.className = 'jw-select-item';
				li.setAttribute('role', 'option');
				li.dataset.value = o.value;
				li.innerHTML = o.dataset?.icon ? `<i class="${o.dataset.icon}"></i> ${o.textContent}` : (o.textContent || '');

				if (o.disabled) {
					li.setAttribute('aria-disabled', 'true');
					li.classList.add('is-disabled');
				}
				if (o.selected) li.setAttribute('aria-selected', 'true');
				list.appendChild(li);
			});
		});
	} catch (_) { }
};

function language_set() {
	const cur = getCookie('lang') || 'eng';
	// 언어 순환: eng → kor → tl → eng
	let next = 'eng';
	if (cur === 'eng') next = 'kor';
	else if (cur === 'kor' || cur === 'ko') next = 'tl';
	else next = 'eng'; // tl → eng
	setCookie('lang', next, 365);
	// 즉시 반영 + include/header 동적 로드/커스텀셀렉트 등 지연 DOM에도 재반영
	try { language_apply(next); } catch (_) {}
	try {
		// next tick에도 한번 더 적용(동적 삽입/렌더 순서 이슈 방어)
		setTimeout(() => { try { language_apply(next); } catch (_) {} }, 0);
	} catch (_) {}
	// 일부 페이지는 실제 변경을 "리로드 후"에만 확인하는 경우가 있어 안전하게 유지
	// (필요 시 아래를 활성화)
	// try { location.reload(); } catch (_) {}
}

// debug: 이 파일이 실제로 최신 버전으로 로드되는지 확인용(캐시 문제 진단)
try {
	console.log('[ADMIN] default.js loaded:', new Date().toISOString());
} catch (_) { }

// Mark admin i18n ready after language is applied (anti-flicker)
(function __admin_i18n_bootstrap() {
	try {
		const root = document.documentElement;
		// start hidden (CSS uses this attribute)
		try { root.setAttribute('data-admin-i18n-ready', '0'); } catch (_) {}

		const lang = __admin_force_lang_cookie();
		// Document language must be en/tl only (no ko)
		try {
			root.setAttribute('data-lang', (lang === 'tl') ? 'tl' : 'en');
			root.lang = (lang === 'tl') ? 'tl' : 'en';
		} catch (_) {}
		try { language_apply(lang); } catch (_) {}
		try { __admin_scrub_hangul(document.body); } catch (_) {}

		// show
		try { root.setAttribute('data-admin-i18n-ready', '1'); } catch (_) {}
	} catch (_) {
		try { document.documentElement.setAttribute('data-admin-i18n-ready', '1'); } catch (_) {}
	}
})();

class Modal {
	constructor(action, width, height, sq) {
		this.action = action;
		this.width = width;
		this.height = height;
		this.sq = sq;
		this.el = null;
		this._abort = null;
	}

	static _toQuery(data) {
		if (!data) return '';
		if (typeof data === 'string') return data.replace(/^\?/, '');
		if (data instanceof FormData) {
			return new URLSearchParams([...data.entries()]).toString();
		}
		return new URLSearchParams(Object.entries(data)).toString();
	}

	static _execScripts(container) {
		try {
			if (!container) return;
			// innerHTML로 삽입된 <script>는 브라우저가 실행하지 않으므로, 재삽입하여 실행시킨다.
			const scripts = Array.from(container.querySelectorAll('script'));
			scripts.forEach((old) => {
				const s = document.createElement('script');
				// copy attributes
				for (const attr of Array.from(old.attributes || [])) {
					s.setAttribute(attr.name, attr.value);
				}
				if (old.src) {
					s.src = old.src;
				} else {
					s.text = old.textContent || '';
				}
				old.parentNode && old.parentNode.replaceChild(s, old);
			});
		} catch (e) {
			try { console.error('[ADMIN] Modal script exec failed:', e); } catch (_) {}
		}
	}

	async _loadHTML(url, data) {
		const ts = Date.now();
		const u = new URL(url, location.href);
		u.searchParams.set('_', ts);

		if (this._abort) this._abort.abort();
		this._abort = new AbortController();
		const t = setTimeout(() => {
			try { this._abort && this._abort.abort(); } catch (_) { }
		}, 15000);

		let res;
		try {
			if (data) {
				const body = Modal._toQuery(data);
				res = await fetch(u.toString(), {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
					body,
					signal: this._abort.signal
				});
			} else {
				res = await fetch(u.toString(), { signal: this._abort.signal, credentials: 'same-origin' });
			}
			if (!res.ok) throw new Error(`HTTP ${res.status}`);
			return await res.text();
		} finally {
			clearTimeout(t);
			this._abort = null;
		}
	}

	async open() {
		const d = document.createElement('dialog');
		d.style.width = (typeof this.width === 'number') ? `${this.width}px` : (this.width || '');
		d.style.height = (typeof this.height === 'number') ? `${this.height}px` : (this.height || '');
		d.innerHTML = 'Loading';
		document.body.appendChild(d);
		this.el = d;

		// UX: 먼저 모달을 즉시 띄우고(Loading), 이후 컨텐츠를 비동기로 로드한다.
		// (컨텐츠 로드가 느리거나 hang 될 때 "버튼 눌러도 아무 일도 안 일어남"으로 보이는 문제 방지)
		if (typeof d.showModal === 'function') d.showModal();
		else d.setAttribute('open', '');

		if (this.action) {
			try {
				const html = await this._loadHTML(this.action, this.sq);
				d.innerHTML = html;
				Modal._execScripts(d);

				const lang = getCookie('lang') || 'eng';
				setCookie('lang', lang, 365);
				await Promise.resolve(language_apply(lang));
				if (typeof jw_select === 'function') await Promise.resolve(jw_select());

				const closeBtn = d.querySelector('#closeDialog');
				if (closeBtn) {
					closeBtn.addEventListener('click', () => this.close(), { once: true });
				}

				document.dispatchEvent(new CustomEvent('modal:loaded', {
					detail: { dialog: d, action: this.action, data: this.sq }
				}));
			} catch (err) {
				d.innerHTML = `<div style="padding:12px">Load error: ${String(err)}</div>`;
			}
		}
		return this;
	}

	async update(action, sq) {
		if (!this.el) return;
		const url = action || this.action;
		const data = (typeof sq !== 'undefined') ? sq : this.sq;
		if (!url) return;

		try {
			const html = await this._loadHTML(url, data);
			this.el.innerHTML = html;
			Modal._execScripts(this.el);
			const closeBtn = this.el.querySelector('#closeDialog');
			if (closeBtn) {
				closeBtn.addEventListener('click', () => this.close(), { once: true });
			}
			document.dispatchEvent(new CustomEvent('modal:loaded', {
				detail: { dialog: this.el, action: url, data: data }
			}));
		} catch (err) {
			this.el.innerHTML = `<div style="padding:12px">Load error: ${String(err)}</div>`;
		}
	}

	close() {
		if (!this.el) return;
		if (this._abort) this._abort.abort();
		if (typeof this.el.close === 'function') {
			this.el.close();
		}
		this.el.remove();
		this.el = null;
	}
}

function modal(page, w, h, sq) {
	const m = new Modal(page, w, h, sq);
	m.open();
	return m;
}

function modal_close() {
	const dialogs = document.querySelectorAll('dialog');
	if (!dialogs.length) return;
	const last = dialogs[dialogs.length - 1];
	if (typeof last.close === 'function') {
		last.close();
	}
	last.remove();
}

function member_info(btn, page) {
	const root = btn.closest('.membermenu') || btn; // 버튼+메뉴 래퍼
	const box = root.querySelector('.position');
	// 일부 페이지/헤더 조합에서 .position이 없을 수 있음(예: 헤더 마크업 변경/미로드)
	// 이 경우 토글 UI를 열 수 없으므로 조용히 종료하여 런타임 에러를 방지한다.
	if (!box) return;
	let wrap = box.querySelector('.wrap');
	if (!wrap) { wrap = document.createElement('div'); wrap.className = 'wrap'; box.appendChild(wrap); }

	// 토글: 열려있으면 닫기
	if (box.classList.contains('on')) { return closeBox(); }

	// 열 때만 fetch
	if (!wrap.innerHTML.trim()) {
		wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#6b7280;">Loading...</div>';
		fetch(page + '?' + Date.now(), { cache: 'no-store' })
			.then(r => { if (!r.ok) throw 0; return r.text(); })
			.then(html => { 
				wrap.innerHTML = html; 
				// 동적으로 로드된 후 로그아웃/표시명 바인딩
				initLogoutButton(wrap);
				hydrateAdminIdentityUI(wrap);
				openBox(); 
			})
			.catch(() => { wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#ef4444;">Failed to load.</div>'; openBox(); });
	} else {
		openBox();
	}

	function openBox() {
		box.classList.add('on');

		// root(버튼+메뉴) 바깥 클릭 시에만 닫기 => 버튼 클릭은 바깥으로 취급 안 함
		const onDocDown = (e) => { if (!root.contains(e.target)) closeBox(); };

		// 내부 닫기 버튼(.closeMember_info)로 닫기
		const onInsideClick = (e) => {
			const t = e.target.closest('.closeMember_info');
			if (t) { e.preventDefault(); closeBox(); }
		};

		removeListeners();
		document.addEventListener('pointerdown', onDocDown, true);
		box.addEventListener('click', onInsideClick);
		box._off = () => {
			document.removeEventListener('pointerdown', onDocDown, true);
			box.removeEventListener('click', onInsideClick);
		};
	}

	function closeBox() {
		box.classList.remove('on');
		removeListeners();
	}

	function removeListeners() {
		if (box._off) { box._off(); box._off = null; }
	}
}

function helpTextChange(target, v) {
	const el = document.querySelector(target);
	if (!el) return;
	el.textContent = v || '';
}

// 로그아웃 버튼 초기화 함수
function initLogoutButton(container) {
	const logoutBtn = container ? container.querySelector('#logoutBtn') : document.getElementById('logoutBtn');
	if (logoutBtn && !logoutBtn.hasAttribute('data-logout-initialized')) {
		logoutBtn.setAttribute('data-logout-initialized', 'true');
		logoutBtn.addEventListener('click', handleLogout);
	}

	const changePasswordBtn = container ? container.querySelector('#changePasswordBtn') : document.getElementById('changePasswordBtn');
	if (changePasswordBtn && !changePasswordBtn.hasAttribute('data-change-password-initialized')) {
		changePasswordBtn.setAttribute('data-change-password-initialized', 'true');
		changePasswordBtn.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			// 절대경로 사용: 현재 위치(super/agent/guide 등)와 무관하게 동작
			modal('/admin/member/change-password.html', '580px', '520px');
		});
	}
}

// Change Password 버튼은 header_memberinfo가 동적으로 로드되어 이벤트 바인딩 타이밍 이슈가 자주 발생함.
// 안전장치: 전역 이벤트 위임(캡처 단계)로 클릭을 확실히 잡아 모달을 띄운다.
(function bindChangePasswordDelegatedOnce() {
	try {
		if (window.__ST_ADMIN_CHANGE_PW_DELEGATED__) return;
		window.__ST_ADMIN_CHANGE_PW_DELEGATED__ = true;
		document.addEventListener('click', function (e) {
			try { console.log('[ADMIN] changePassword click captured:', e && e.target); } catch (_) {}
			const t = e.target && e.target.closest ? e.target.closest('#changePasswordBtn') : null;
			if (!t) return;
			try { console.log('[ADMIN] opening change-password modal'); } catch (_) {}
			e.preventDefault();
			e.stopPropagation();
			try {
				modal('/admin/member/change-password.html', '580px', '520px');
			} catch (err) {
				// 최소한의 가시성 확보
				console.error('Failed to open change-password modal:', err);
				alert('Failed to open Change Password.');
			}
		}, true);
	} catch (_) { }
})();

// 헤더/프로필 카드 사용자 표시 동기화
async function hydrateAdminIdentityUI(root) {
	try {
		const res = await fetch('/admin/backend/api/check-session.php', { credentials: 'same-origin', cache: 'no-store' });
		const data = await res.json().catch(() => ({}));
		if (!data || !data.authenticated) return;

		// 헤더 user-name
		const headerName = document.querySelector('.layout-header .user-name');
		if (headerName) {
			// 헤더 요구사항: Admin/Agent/Guide/CS 담당자 유형별 표시
			const ut = String(data.userType || '');
			if (ut === 'admin') headerName.textContent = 'Admin';
			else if (ut === 'agent') headerName.textContent = data.displayName || 'Agent';
			else if (ut === 'guide') headerName.textContent = data.displayName || 'Guide';
			else if (ut === 'cs') headerName.textContent = 'CS';
			else headerName.textContent = data.displayName || 'Admin';
		}

		// 프로필 카드(드롭다운) name/role
		const scope = root || document;
		const nameEl = scope.querySelector('.header_memberinfo .name');
		const roleEl = scope.querySelector('.header_memberinfo .role');
		if (nameEl) nameEl.textContent = data.displayName || 'ADMIN';
		if (roleEl) roleEl.textContent = data.roleLabel || 'Employee';
	} catch (_) {
		// ignore
	}
}

// 로그아웃 처리 함수
let isLoggingOut = false; // 중복 실행 방지 플래그

async function handleLogout(e) {
	if (e) {
		e.preventDefault();
		e.stopPropagation();
	}
	
	// 이미 로그아웃 진행 중이면 무시
	if (isLoggingOut) {
		return;
	}
	
	if (!confirm('Do you want to log out?')) {
		return;
	}
	
	// 로그아웃 진행 중 플래그 설정
	isLoggingOut = true;
	
	try {
		// 상대경로 계산 오류로 /index.html(사용자 언어설정)로 튀는 문제 방지:
		// - API/리다이렉트 모두 절대경로 사용
		const apiPath = '/admin/backend/api/logout.php';
		
		const response = await fetch(apiPath, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			}
		});
		
		if (!response.ok) {
			throw new Error('HTTP ' + response.status);
		}
		
		const data = await response.json();
		
		if (data.success) {
			// B2B/B2C 판별용 localStorage 정리
			try { localStorage.removeItem('accountType'); } catch (_) {}
			// 항상 관리자 로그인으로 이동
			window.location.href = '/admin/index.html';
		} else {
			isLoggingOut = false; // 실패 시 플래그 리셋
			alert(data.message || 'Logout failed.');
		}
	} catch (error) {
		console.error('Logout error:', error);
		isLoggingOut = false; // 에러 시 플래그 리셋
		// B2B/B2C 판별용 localStorage 정리
		try { localStorage.removeItem('accountType'); } catch (_) {}
		// 에러가 발생해도 관리자 로그인으로 이동(요구사항: 불필요한 오류 알럿 노출 금지)
		window.location.href = '/admin/index.html';
	}
}

function setFabMenuState(menu, open) {
	if (!menu) return;
	if (open) {
		menu.classList.add('on');
		menu.setAttribute('aria-hidden', 'false');
		if ('inert' in menu) menu.inert = false;
	} else {
		menu.classList.remove('on');
		menu.setAttribute('aria-hidden', 'true');
		if ('inert' in menu) menu.inert = true;
	}
}

function layerToggle(btn) {
	const menu = btn.closest('.layerToggleWrap')?.querySelector(':scope > .fab-menu');
	if (!menu) return;
	const willOpen = !menu.classList.contains('on');
	setFabMenuState(menu, willOpen);
	if (willOpen) {
		const firstFocusable = menu.querySelector('button, [tabindex]:not([tabindex="-1"])');
		if (firstFocusable) firstFocusable.focus();
	} else {
		btn.focus();
	}
}

function filePreview(el) {
	const f = el.files && el.files[0]; if (!f) return;
	const box = el.closest('.image-preview') || el.closest('.banner-item')?.querySelector('.image-preview') || el.closest('.banner-image-wrap') || el.closest('.upload-item') || null;
	if (!box) return;
	const url = URL.createObjectURL(f);

	// upload-item(템플릿/상품 이미지 등): img.preview 기반으로 통일해야 저장 로직이 src를 읽을 수 있음
	if (box.classList && box.classList.contains('upload-item')) {
		box.classList.add('is-filled');
		box.classList.add('has-image');
		try { box.style.backgroundImage = ''; } catch (_) {}

		// 업로드 UI(label/inputFile)가 있으면 미리보기 상태에서는 숨겨서 삭제 버튼 클릭을 막지 않게 한다.
		try {
			const lab = box.querySelector('label.inputFile');
			const btn = lab?.querySelector('.btn-upload') || null;
			if (lab) lab.style.display = 'none';
			if (btn) btn.style.display = 'none';
		} catch (_) {}

		let img = box.querySelector('img.preview');
		if (!img) {
			img = document.createElement('img');
			img.className = 'preview';
			img.alt = '';
			// img가 뒤에 붙으면 close 버튼 위를 덮을 수 있어 앞에 둔다.
			box.insertAdjacentElement('afterbegin', img);
		}
		// blob URL revoke는 너무 빨리 하면(환경/브라우저/리소스 상황에 따라) 이미지가 로드되기 전에 사라져
		// "X만 보이고 이미지가 안 보이는" 현상이 발생할 수 있음.
		// → onload 후 revoke (fallback으로만 타임아웃)
		try {
			img.onload = () => {
				try { URL.revokeObjectURL(url); } catch (_) {}
				try { img.onload = null; } catch (_) {}
			};
		} catch (_) {}
		img.src = url;

		// 삭제 버튼이 없으면 생성
		try {
			if (!box.querySelector('.btn-close')) {
				const closeBtn = document.createElement('button');
				closeBtn.type = 'button';
				closeBtn.className = 'jw-button btn-close';
				closeBtn.setAttribute('aria-label', 'remove');
				closeBtn.innerHTML = '<div class="ic-close" style="width:15px; height:15px; --line:1px; --color:#969696"></div>';
				box.appendChild(closeBtn);
			}
			const b = box.querySelector('.btn-close');
			if (b) b.style.zIndex = '5';
		} catch (_) {}

		// blob URL은 현재 세션에서만 유효하므로, 저장 시 업로드를 통해 영구 URL로 변환되어야 함
		// fallback: onload가 안 오는 경우를 대비해 넉넉하게 지연 후 해제
		setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 60000);
		return;
	}

	// 기존(배너/기타) 동작 유지
	box.style.backgroundImage = `url("${url}")`;
	box.style.backgroundSize = 'cover';
	box.style.backgroundPosition = 'center';
	box.classList.add('has-image');
	setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 60000);
}

function essentialCheck(it) {
	const wrap = it.closest('[data-essentialWrap="y"]');
	if (!wrap) return false;
	const essentials = wrap.querySelectorAll('[data-essential="y"]');
	if (!essentials.length) return false;

	const allOK = Array.prototype.every.call(essentials, el => {
		const tag = el.tagName;
		const type = (el.type || '').toLowerCase();

		if (type === 'checkbox' || type === 'radio') return el.checked;
		if (tag === 'SELECT') return (el.value ?? '') !== '';
		if (type === 'file') return (el.files && el.files.length > 0);
		return ((el.value || '').trim().length > 0);
	});
	console.log( 11 );
	wrap.querySelectorAll('[data-essentialTarget="y"]').forEach(tg => {
		tg.disabled = !allOK;
		tg.classList.toggle('active', allOK);
		tg.classList.toggle('inactive', !allOK);
	});

	return allOK;
}

function togglePlusMinus(btn) {
	var hasOn = btn.classList.contains('on');
	var img = btn.querySelector('img');
	var target = btn.getAttribute('data-target-section');
	var targets = target ? document.querySelectorAll('[data-section-name="' + target + '"]') : [];

	if (hasOn) {
		// on 이 있으면: on 제거 + 아이콘 minus
		btn.classList.remove('on');
		targets.forEach(function (el) { if (el !== btn) el.classList.remove('hidden'); });
		img.src = '../image/minus.svg';
		img.alt = 'minus';
	} else {
		// on 이 없으면: on 추가 + 아이콘 plus
		btn.classList.add('on');
		targets.forEach(function (el) { if (el !== btn) el.classList.add('hidden'); });
		img.src = '../image/plus.svg';
		img.alt = 'plus';
	}
}

// 사이드바에서 섹션 제목 클릭 시 해당 섹션으로 스크롤
function scrollToSection(sectionId) {
	var target = document.getElementById(sectionId);
	if (!target) {
		// data-section-name으로도 찾기
		target = document.querySelector('[data-section-name="' + sectionId + '"]');
	}
	if (!target) {
		// data-lan-eng로도 찾기
		target = document.querySelector('h2.section-title[data-lan-eng="' + sectionId + '"]');
	}
	if (target) {
		// 섹션이 숨겨져 있으면 먼저 표시
		if (target.classList.contains('hidden')) {
			var btn = document.querySelector('button.aside-add-section[data-target-section="' + sectionId + '"]');
			if (btn && btn.classList.contains('on')) {
				togglePlusMinus(btn);
			}
		}
		// 스크롤
		var headerOffset = 120;
		var elementPosition = target.getBoundingClientRect().top;
		var offsetPosition = elementPosition + window.pageYOffset - headerOffset;
		window.scrollTo({ top: offsetPosition, behavior: 'smooth' });

		// 사이드바 active 상태 업데이트
		updateAsideActiveState(sectionId);
	}
}

// 사이드바 active 상태 업데이트
function updateAsideActiveState(sectionId) {
	var asideRows = document.querySelectorAll('.aside-row');
	asideRows.forEach(function(row) {
		row.classList.remove('is-active');
		var titleEl = row.querySelector('.aside-row__title, .aside-row__subtitle');
		if (titleEl && titleEl.getAttribute('data-scroll-target') === sectionId) {
			row.classList.add('is-active');
		}
	});
}

// 사이드바에서 Day 클릭 시 해당 Day 패널로 스크롤
function scrollToDayPanel(dayNum) {
	// nday-panel with data-day attribute 또는 id="nday" (Day 1)
	var target = null;
	if (dayNum === 1) {
		target = document.getElementById('nday');
	}
	if (!target) {
		target = document.querySelector('.nday-panel[data-day="' + dayNum + '"]');
	}
	if (target) {
		var headerOffset = 120;
		var elementPosition = target.getBoundingClientRect().top;
		var offsetPosition = elementPosition + window.pageYOffset - headerOffset;
		window.scrollTo({ top: offsetPosition, behavior: 'smooth' });

		// 사이드바 active 상태 업데이트
		updateAsideActiveState('day-' + dayNum);
	}
}

function trash_typeA(it) {
	var tbody = it.closest('tbody');
	if (!tbody) return;

	var rows = tbody.querySelectorAll('tr');
	if (rows.length === 1) {
		// 기획서: 테이블은 최소 1개 행은 유지되어야 함(삭제 불가 안내)
		// 절대경로 사용(어느 페이지에서 호출되든 동일 모달 로드)
		modal('/admin/super/template-detail-modal2.html', '580px', '252px');
		return;
	}
	var tr = it.closest('tr');
	if (tr) tr.remove();

	// No 컬럼 재정렬(첫 번째 td가 숫자일 때만)
	try {
		var rs = tbody.querySelectorAll('tr');
		Array.prototype.forEach.call(rs, function (row, idx) {
			var first = row.querySelector('td');
			if (!first) return;
			var n = parseInt((first.textContent || '').trim(), 10);
			if (!Number.isFinite(n)) return;
			first.textContent = String(idx + 1);
		});
	} catch (_) { }
}

/* 임시 */
function temp_link(v){
	location.href=v;
}

// show agent id
function waitForHeaderUserNameAndHydrate(timeoutMs = 3000) {
	const start = Date.now();
	(function loop() {
		const el = document.querySelector('.layout-header .user-name');
		if (el) return hydrateAdminIdentityUI(document);
		if (Date.now() - start > timeoutMs) return;
		requestAnimationFrame(loop);
	})();
}