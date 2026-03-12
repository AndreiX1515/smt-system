const lang = getCookie('lang') || 'eng';
let sdfg;
function init(options) {
	language_audit_eng_json_map();
	const start = async () => {
		await runInit(options);
		layoutNav();
		nav_status();
		setCookie('lang', lang, 365);
		await Promise.resolve(language_apply(lang));
		jw_select();
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
	sdfg = document.querySelector('.dfghs')?.cloneNode(true);
	board();
}

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
function runInit(options) {
	options = options || {};
	const pHeader = loadIntoSlot('.layout-header', options.headerUrl);
	const pNav = loadIntoSlot('.layout-nav', options.navUrl);
	return Promise.all([pHeader, pNav]).then(() => true);
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
function b2b_booking_detail() {
	const target = document.getElementById('b2b_booking_detail_target');
	if (!target) return;
	target.innerHTML = `
	<div class="cell">
		<div class="field-row jw-center">
			<div class="jw-center jw-gap10"><img src="../image/file.svg" alt=""> <span data-lan-eng="Advance payment proof file">선금 증빙 파일</span> [pdf, 328KB]</div>
			<div class="jw-center jw-gap10">
				<i></i>
				<button type="button" class="jw-button typeF"><img src="../image/buttun-download.svg" alt=""></button>
			</div>
		</div>
	</div>
	`;
	language_apply(lang);
}
function template_detail_save(it) {
	const msg = essentialCheck2(it);
	if (msg) {
		modal('template-detail-modal1.html', '600px', '252px');
		setTimeout(() => {
			const d = document.querySelector('dialog:last-of-type');
			const tg = d && d.querySelector('.dialog-content #lllll');
			if (tg) tg.textContent = msg;
		}, 100);
		return;
	}
	temp_link('template-list.html');
}
function template_detail_sdaf(btn) {
	const list = (btn.closest('.aside') || document).querySelector('.aside-row-list');
	if (!list) return;

	const day = list.querySelectorAll('.aside-row').length + 1;

	const li = document.createElement('li');
	li.className = 'aside-row';
	li.innerHTML = `
    <div class="aside-row__subtitle" data-lan-eng="Day ${day}">${day}일차</div>
    <button type="button" class="jw-button aside-add-section" onclick="template_detail_sdaf_delete(this)"><img src="../image/minus.svg" alt=""></button>
	`;
	list.appendChild(li);

	const lang = getCookie('lang') || 'eng';
	if (typeof language_apply === 'function') language_apply(lang);
}
function template_detail_sdaf_delete(btn) {
	const row = btn.closest('.aside-row');
	if (!row) return;
	const list = row.closest('.aside-row-list');
	row.remove();
	if (!list) return;
	list.querySelectorAll('.aside-row').forEach((el, i) => {
		const n = i + 1;
		const sub = el.querySelector('.aside-row__subtitle');
		if (!sub) return;
		sub.setAttribute('data-lan-eng', `Day ${n}`);
		sub.textContent = `${n}일차`;
	});
	const lang = getCookie('lang') || 'eng';
	if (typeof language_apply === 'function') language_apply(lang);
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
			hasValue = !!area.querySelector(`input[type="radio"][name="${CSS.escape(cat1.name)}"]:checked`);
		} else hasValue = cat1.checked;
	} else if (type === 'checkbox') {
		hasValue = cat1.checked;
	} else if (type === 'file') {
		hasValue = !!(cat1.files && cat1.files.length);
	} else if (tag === 'SELECT') {
		hasValue = (cat1.value ?? '') !== '';
	} else {
		hasValue = ((cat1.value || '').trim().length > 0);
	}
	if (!hasValue) sel2.setAttribute('disabled', '');
	else sel2.removeAttribute('disabled');
	const wrap = sel2.closest('.jw-select');
	if (wrap) {
		wrap.classList.toggle('is-disabled', sel2.disabled);
		const btn = wrap.querySelector('.jw-selected');
		if (btn) {
			btn.setAttribute('aria-disabled', sel2.disabled ? 'true' : 'false');
			btn.tabIndex = sel2.disabled ? -1 : 0;
		}
	}
}
function ndasdtoggle() {
	const el = document.getElementById('nday');
	if (!el) return false;
	el.classList.toggle('off');
	return el.classList.contains('off');
}
function template_detail_dfghs(btn) {
	const panel = btn.closest('.sub-panel');
	if (!panel) return;

	const copy = sdfg.cloneNode(true);
	copy.removeAttribute('id');
	panel.appendChild(copy);

	// 🔹 새로 추가된 영역 안의 select에도 커스텀 셀렉트 적용
	if (typeof jw_select === 'function') {
		jw_select();  // select.select 를 다시 스캔해서 새로 변환
	}

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
		if (focusable) try { focusable.focus({ preventScroll: true }); } catch { }
	});
	return copy;
}
function template_detail_hsdfghsh(it) {
	const box = it.closest('.dfghs');
	if (!box) return false;
	box.remove();
	return true;
}
function template_detail_sfg1(btn) {
	const panel = btn.closest('.card-panel');
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
	const tr = btn.closest('tr');
	if (!tr) return;
	const tbody = tr.parentElement;
	tr.remove();
	Array.from(tbody.querySelectorAll('tr')).forEach((row, i) => {
		const firstTd = row.querySelector('td');
		if (firstTd) firstTd.textContent = i + 1;
	});
}
function template_detail_sfg3(btn) {
	const panel = btn.closest('.card-panel');
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
function product_detail_save(it) {
	const msg = essentialCheck2(it);
	if (msg) {
		modal('template-detail-modal1.html', '600px', '252px');
		setTimeout(() => {
			const d = document.querySelector('dialog:last-of-type');
			const tg = d && d.querySelector('.dialog-content #lllll');
			if (tg) tg.textContent = msg;
		}, 100);
		return;
	}
	modal('product-preview.html', '1000px', '100%')
}
function product_registration_save(it) {
	const msg = essentialCheck2(it);
	if (msg) {
		modal('template-detail-modal1.html', '600px', '252px');
		setTimeout(() => {
			const d = document.querySelector('dialog:last-of-type');
			const tg = d && d.querySelector('.dialog-content #lllll');
			if (tg) tg.textContent = msg;
		}, 100);
		return;
	}
	modal('product-preview.html', '1000px', '100%')
}







