let isHtmlView;
let quill;
let imageStorage = []; 
let imageIndex = 0;

function jw_select() {
	if (!window.__jwselect_docbound) {
		document.addEventListener('click', () => {
			document.querySelectorAll('div.jw-selected.active').forEach(box => {
				box.classList.remove('active');
				const list = box.nextElementSibling;
				if (list && list.classList.contains('jw-select-list')) list.style.display = 'none';
			});
		});
		window.__jwselect_docbound = true;
	}

	const measure = (txt) => {
		const t = document.createElement('div');
		t.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;display:inline-block;';
		t.textContent = txt; document.body.appendChild(t);
		const w = t.offsetWidth; t.remove(); return w;
	};

	const triggerChange = (sel) => {
		sel.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
		sel.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
		if (typeof sel.onchange === 'function') { try { sel.onchange.call(sel); } catch { } }
		if (window.jQuery) { const $sel = window.jQuery(sel); $sel.addClass('select').trigger('change').removeClass('select'); }
	};

	document.querySelectorAll('select.select').forEach(nativeSel => {
		let maxWidth = 0;

		nativeSel.classList.remove('select');
		nativeSel.classList.add('jw-sr-only');

		const wrap = document.createElement('div');
		wrap.className = 'jw-select';
		nativeSel.parentNode.insertBefore(wrap, nativeSel);
		wrap.appendChild(nativeSel);

		const styled = document.createElement('div');
		styled.className = 'jw-selected';
		wrap.appendChild(styled);

		if (nativeSel.disabled) {
			wrap.classList.add('is-disabled');
			styled.setAttribute('aria-disabled', 'true');
		}

		const selectedOpt = nativeSel.selectedOptions[0] || nativeSel.options[0];
		const selectedText = selectedOpt ? selectedOpt.textContent : '';
		const selectedIcon = selectedOpt && selectedOpt.dataset ? selectedOpt.dataset.icon : null;
		if (selectedIcon) styled.innerHTML = `<i class="${selectedIcon}"></i> ${selectedText}`;
		else styled.textContent = selectedText;

		const list = document.createElement('ul');
		list.className = 'jw-select-list';
		styled.insertAdjacentElement('afterend', list);

		const numberOfOptions = nativeSel.options.length;
		for (let i = 0; i < numberOfOptions; i++) {
			const o = nativeSel.options[i];
			const li = document.createElement('li');
			li.className = 'jw-select-item';
			li.setAttribute('rel', o.value);

			const iconClass = o.dataset ? o.dataset.icon : null;
			const iconWidth = o.dataset && o.dataset.iconwidth ? Number(o.dataset.iconwidth) : 0;

			if (iconClass) {
				li.innerHTML = `<i class="${iconClass}"></i> ${o.textContent}`;
				if (iconWidth) maxWidth = iconWidth;
			} else {
				li.textContent = o.textContent;
			}

			if (o.disabled) {
				li.classList.add('is-disabled');
				li.setAttribute('aria-disabled', 'true');
			}

			list.appendChild(li);
		}

		const listItems = Array.from(list.children);
		if (listItems.length > 0) {
			listItems.forEach(li => {
				const w = measure(li.textContent);
				if (w > maxWidth) maxWidth = w;
			});
			maxWidth += 40;
			wrap.style.minWidth = `${maxWidth}px`;
		}

		styled.addEventListener('click', (e) => {
			e.stopPropagation();
			if (nativeSel.disabled) return;

			document.querySelectorAll('div.jw-selected.active').forEach(box => {
				if (box !== styled) {
					box.classList.remove('active');
					const ul = box.nextElementSibling;
					if (ul && ul.classList.contains('jw-select-list')) ul.style.display = 'none';
				}
			});
			const next = styled.nextElementSibling;
			styled.classList.toggle('active');
			if (next && next.classList.contains('jw-select-list')) {
				next.style.display = (next.style.display === 'block') ? 'none' : 'block';
			}
		});

		listItems.forEach(li => {
			li.addEventListener('click', (e) => {
				e.stopPropagation();
				if (li.classList.contains('is-disabled') || li.getAttribute('aria-disabled') === 'true') return;

				const selectedText2 = li.textContent;
				const iconEl = li.querySelector('i');
				const selectedIcon2 = iconEl ? iconEl.className : null;

				if (selectedIcon2) styled.innerHTML = `<i class="${selectedIcon2}"></i> ${selectedText2}`;
				else styled.textContent = selectedText2;

				styled.classList.remove('active');
				list.style.display = 'none';

				const newVal = li.getAttribute('rel');
				if (nativeSel.value !== newVal) {
					nativeSel.value = newVal;
					triggerChange(nativeSel);
				}
			});
		});
	});
}

function setCookie(name, value, days) { const d = new Date(); d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000); document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`; }
function getCookie(name) { const seg = `; ${document.cookie}`.split(`; ${name}=`); return (seg.length === 2) ? decodeURIComponent(seg.pop().split(';').shift()) : null; }

function getLangText(el, lang) {
	if (lang === 'eng') return el.getAttribute('data-lan-eng');
	return el.getAttribute('data-lan-kor') || el.getAttribute('data-lan-ko');
}

function language_apply(lang) {
	document.querySelectorAll('[data-lan-eng],[data-lan-kor],[data-lan-ko]').forEach(el => {
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

	document.querySelectorAll('.lang-text').forEach(node => {
		node.textContent = (lang === 'eng') ? 'Korea' : 'English';
	});
}

function language_set() {
	const cur = getCookie('lang') || 'eng';
	const next = (cur === 'eng') ? 'kor' : 'eng';
	setCookie('lang', next, 365);
	if (next === 'kor') {
		location.reload();
	} else {
		language_apply(next);
	}
}

function language_audit_eng_json_map() {
	const path = location.pathname;
	const file = decodeURIComponent(path.split('/').filter(Boolean).pop() || 'index.html');

	const getBefore = (el) => {
		const tag = el.tagName;
		if (tag === 'INPUT' || tag === 'TEXTAREA') {
			if (el.hasAttribute('placeholder')) return el.getAttribute('placeholder') || '';
			return el.value || '';
		}
		if (tag === 'IMG') return el.getAttribute('alt') || '';
		return (el.textContent || '').trim();
	};

	const getAfter = (el) => {
		if (typeof getLangText === 'function') return getLangText(el, 'eng') || '';
		return el.getAttribute('data-lan-eng') || '';
	};

	const pairs = [];
	document.querySelectorAll('[data-lan-eng]').forEach(el => {
		pairs.push([getBefore(el), getAfter(el)]);
	});

	const out = { [file]: pairs };
	console.log( out );
	//console.log(JSON.stringify(out, null, 2));
}

function language_audit_eng_json_map2(action) {
	const dialogs = document.querySelectorAll('dialog');
	if (!dialogs.length) { console.warn('No <dialog> found'); return; }
	const container = dialogs[dialogs.length - 1];

	const key = (action && String(action).trim()) || 'dialog';

	const getBefore = (el) => {
		const tag = el.tagName;
		if (tag === 'INPUT' || tag === 'TEXTAREA') {
			if (el.hasAttribute('placeholder')) return el.getAttribute('placeholder') || '';
			return el.value || '';
		}
		if (tag === 'IMG') return el.getAttribute('alt') || '';
		return (el.textContent || '').trim();
	};

	const getAfter = (el) => {
		if (typeof getLangText === 'function') return getLangText(el, 'eng') || '';
		return el.getAttribute('data-lan-eng') || '';
	};

	const pairs = [];
	container.querySelectorAll('[data-lan-eng]').forEach(el => {
		pairs.push([getBefore(el), getAfter(el)]);
	});

	const out = { [key]: pairs };
	console.log(out);
	// console.log(JSON.stringify(out, null, 2));
}

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

	async _loadHTML(url, data) {
		const ts = Date.now();
		const u = new URL(url, location.href);
		u.searchParams.set('_', ts);

		if (this._abort) this._abort.abort();
		this._abort = new AbortController();

		let res;
		try {
			if (data) {
				const body = Modal._toQuery(data);
				res = await fetch(u.toString(), {
					method: 'POST',
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

		if (this.action) {
			try {
				const html = await this._loadHTML(this.action, this.sq);
				d.innerHTML = html;

				const lang = getCookie('lang') || 'eng';
				setCookie('lang', lang, 365);
				language_audit_eng_json_map2(this.action);
				language_apply(lang);
				if (typeof jw_select === 'function') jw_select();
				
				const closeBtn = d.querySelector('#closeDialog');
				if (closeBtn) {
					closeBtn.addEventListener('click', () => this.close(), { once: true });
				}
			} catch (err) {
				d.innerHTML = `<div style="padding:12px">Load error: ${String(err)}</div>`;
			}
		}

		if (typeof d.showModal === 'function') d.showModal();
		else d.setAttribute('open', '');
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
			const closeBtn = this.el.querySelector('#closeDialog');
			if (closeBtn) {
				closeBtn.addEventListener('click', () => this.close(), { once: true });
			}
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
	const root = btn.closest('.membermenu') || btn;
	const box = root.querySelector('.position');
	let wrap = box.querySelector('.wrap');
	if (!wrap) { wrap = document.createElement('div'); wrap.className = 'wrap'; box.appendChild(wrap); }

	if (box.classList.contains('on')) { return closeBox(); }

	if (!wrap.innerHTML.trim()) {
		wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#6b7280;">불러오는 중…</div>';
		fetch(page + '?' + Date.now(), { cache: 'no-store' })
			.then(r => { if (!r.ok) throw 0; return r.text(); })
			.then(html => { wrap.innerHTML = html; openBox(); })
			.catch(() => { wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#ef4444;">불러오기에 실패했어요.</div>'; openBox(); });
	} else {
		openBox();
		
	}

	function openBox() {
		const lang = getCookie('lang') || 'eng';
		language_apply(lang);
			
		box.classList.add('on');

		const onDocDown = (e) => { if (!root.contains(e.target)) closeBox(); };

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

function helpTextChange(target) {
	const el = document.querySelector(target);
	if (!el) return;
	el.classList.remove('active');
}

function layerToggle(btn) {
	const menu = btn.closest('.layerToggleWrap')?.querySelector(':scope > .fab-menu');
	if (!menu) return;
	menu.classList.toggle('on');
}
/*
function filePreview(el) {
	const f = el.files && el.files[0]; if (!f) return;
	const box = el.closest('.image-preview') || el.closest('.banner-item')?.querySelector('.image-preview') || el.closest('.popup-image-wrap')?.querySelector('.image-preview') || el.closest('.banner-image-wrap') || el.closest('.upload-item') || null;
	if (!box) return;
	const url = URL.createObjectURL(f);
	box.style.backgroundImage = `url("${url}")`;
	box.style.backgroundSize = 'cover';
	box.style.backgroundPosition = 'center';
	box.classList.add('has-image');
	setTimeout(() => URL.revokeObjectURL(url), 2000);
}
*/
function filePreview(el) {
	const wrap =
		el.closest('.upload-item') ||
		el.closest('.banner-image-wrap') ||
		el.closest('.popup-image-wrap') ||
		el.closest('.banner-item');
	if (!wrap) return;
	const box = wrap.querySelector('.image-preview') || wrap;

	const f = el.files && el.files[0]; if (!f) return;
	const url = URL.createObjectURL(f);
	box.style.backgroundImage = `url("${url}")`;
	box.style.backgroundSize = 'cover';
	box.style.backgroundPosition = 'center';
	box.classList.add('has-image');
	setTimeout(() => URL.revokeObjectURL(url), 2000);
	const inputWrap = el.closest('label.inputFile');
	if (inputWrap) inputWrap.remove();
	if (!wrap.querySelector('.jw-button.btn-close')) {
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'jw-button btn-close';
		btn.setAttribute('aria-label', 'remove');
		btn.innerHTML = `<div class="ic-close" style="width:15px; height:15px; --line:1px; --color:#969696"></div>`;
		btn.addEventListener('click', () => resetPreview(wrap, box));
		wrap.appendChild(btn);
	}
}
function resetPreview(wrap, box) {
	const target = box || wrap;
	target.style.aspectRatio = '1 / 1';
	target.style.backgroundImage = 'linear-gradient(45deg, #f3f3f3 25%, transparent 25%), linear-gradient(-45deg, #f3f3f3 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f3f3f3 75%), linear-gradient(-45deg, #fff 75%, #f3f3f3 75%)';
	target.style.backgroundSize = '16px 16px';
	target.style.backgroundPosition = '0 0, 0 8px, 8px -8px, -8px 0';
	target.classList.remove('has-image');
	const closeBtn = wrap.querySelector('.jw-button.btn-close');
	if (closeBtn) closeBtn.remove();
	if (!wrap.querySelector(':scope > label.inputFile')) {
		wrap.insertAdjacentElement('afterbegin', createUploadLabel());
	}
}
function createUploadLabel() {
	const label = document.createElement('label');
	label.className = 'inputFile';
	label.innerHTML = `
    <input name="" type="file" onchange="filePreview(this);">
    <button type="button" class="btn-upload">
      <img src="../image/upload3.svg" alt="">
      <span data-lan-eng="Image upload">Image upload</span>
    </button>
  `;
	return label;
}
function template_upload_close(btn) {
	const wrap = btn.closest('.upload-item');
	if (!wrap) return;
	wrap.style.aspectRatio = '1 / 1';
	wrap.style.backgroundImage = 'linear-gradient(45deg, #f3f3f3 25%, transparent 25%), linear-gradient(-45deg, #f3f3f3 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f3f3f3 75%), linear-gradient(-45deg, #fff 75%, #f3f3f3 75%)';
	wrap.style.backgroundSize = '16px 16px';
	wrap.style.backgroundPosition = '0 0, 0 8px, 8px -8px, -8px 0';
	wrap.classList.remove('is-filled', 'has-image');

	const img = wrap.querySelector('img.preview');
	if (img) img.remove();

	btn.remove();

	if (!wrap.querySelector(':scope > label.inputFile')) {
		wrap.insertAdjacentHTML('afterbegin', `
      <label class="inputFile">
        <input name="" type="file" onchange="filePreview(this);">
        <button type="button" class="btn-upload">
          <img src="../image/upload3.svg" alt=""> 
          <span data-lan-eng="Image upload">Image upload</span>
        </button>
      </label>
    `);
	}
}
function download(filename, text) {
	const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url; a.download = filename || 'tohell.txt';
	document.body.appendChild(a); a.click(); a.remove();
	URL.revokeObjectURL(url);
}
function deletefile(btn) {
	console.log( 11 );
	const grid = btn.closest('.grid-item'); if (!grid) return;
	const cell = grid.querySelector('.cell'); if (cell) cell.remove();
	grid.insertAdjacentHTML('beforeend', `
    <label class="inputFile">
      <input name="" type="file" data-essential="y" onchange="noticeUploadChange(this)">
      <button type="button" class="btn-upload"><img src="../image/upload3.svg" alt="">File upload</button>
    </label>
  `);
}
function deletefile3(btn) {
	console.log( 11 );
	const grid = btn.closest('.grid-item'); if (!grid) return;
	const cell = grid.querySelector('.cell'); if (cell) cell.remove();
	grid.insertAdjacentHTML('beforeend', `
    <label class="inputFile jw-mgt32">
      <input name="" type="file" data-essential="y" onchange="noticeUploadChange3(this)">
      <button type="button" class="btn-upload"><img src="../image/upload3.svg" alt="">File upload</button>
    </label>
  `);
}
function deletefile4(btn) {
	console.log( 11 );
	const grid = btn.closest('.grid-item'); if (!grid) return;
	const cell = grid.querySelector('.cell'); if (cell) cell.remove();
	grid.insertAdjacentHTML('beforeend', `
    <label class="inputFile">
      <input name="" type="file" data-essential="y" onchange="noticeUploadChange4(this)">
      <button type="button" class="btn-upload"><img src="../image/upload3.svg" alt="">&nbsp;File upload</button>
    </label>
  `);
}
function noticeUploadChange(input) {
	const f = input.files && input.files[0]; if (!f) return;
	const grid = input.closest('.grid-item'); if (!grid) return;

	const label = input.closest('label.inputFile'); if (label) label.remove();

	const name = f.name;
	const ext = (name.split('.').pop() || '').toLowerCase() || 'file';
	const size = f.size ? (f.size < 1024 ? f.size + ' B' : (f.size / 1024).toFixed(1) + ' KB') : '';

	const cell = document.createElement('div');
	cell.className = 'cell';
	cell.innerHTML = `
    <div class="field-row jw-center">
      <div class="jw-center jw-gap10">
        <img src="../image/file.svg" alt="">
        <span data-lan-eng="File">${name}</span> [${ext}${size ? ', ' + size : ''}]
      </div>
      <div class="jw-center jw-gap10">
        <i></i>
        <button type="button" class="jw-button typeF" onclick="console.log('download noop');">
          <img src="../image/buttun-download.svg" alt="">
        </button>
        <button type="button" class="jw-button typeF" onclick="deletefile(this)">
          <img src="../image/button-close2.svg" alt="">
        </button>
      </div>
    </div>
  `;
	grid.appendChild(cell);
}

function noticeUploadChange2(input) {
	const f = input.files && input.files[0]; if (!f) return;

	const grid = input.closest('.grid-item');
	const list = grid && grid.querySelector('.attach-list');
	if (!list) return;
	const sizeTxt = input.getAttribute('data-size') || '328KB';
	const name = f.name || 'file';
	const ext = (input.getAttribute('data-ext')) || (name.split('.').pop() || 'file').toLowerCase();

	const cell = document.createElement('div');
	cell.className = 'cell';
	cell.innerHTML = `
    <div class="field-row jw-center">
      <div class="jw-center jw-gap10">
        <img src="../image/file.svg" alt="">
        <span data-lan-eng="File">파일</span> [${ext}, ${sizeTxt}]
      </div>
      <div class="jw-center jw-gap10">
        <i></i>
        <button type="button" class="jw-button typeF" onclick="console.log('download noop')">
          <img src="../image/buttun-download.svg" alt="">
        </button>
        <button type="button" class="jw-button typeF" onclick="attachItemDelete(this)">
          <img src="../image/button-close2.svg" alt="">
        </button>
      </div>
    </div>
  `;
	list.appendChild(cell);
	input.value = '';
	if (typeof language_apply === 'function') {
		const lang = (typeof getCookie === 'function' && getCookie('lang')) || 'eng';
		language_apply(lang);
	}
}

function noticeUploadChange3(input) {
	const f = input.files && input.files[0]; if (!f) return;
	const grid = input.closest('.grid-item'); if (!grid) return;

	const label = input.closest('label.inputFile'); if (label) label.remove();

	const name = f.name;
	const ext = (name.split('.').pop() || '').toLowerCase() || 'file';
	const size = f.size ? (f.size < 1024 ? f.size + ' B' : (f.size / 1024).toFixed(1) + ' KB') : '';

	const cell = document.createElement('div');
	cell.className = 'cell';
	cell.innerHTML = `
    <div class="field-row jw-center jw-mgt32">
      <div class="jw-center jw-gap10">
        <img src="../image/file.svg" alt="">
        <span data-lan-eng="File">File</span> [${ext}${size ? ', ' + size : ''}]
      </div>
      <div class="jw-center jw-gap10">
        <i></i>
        <button type="button" class="jw-button typeF" onclick="console.log('download noop');">
          <img src="../image/buttun-download.svg" alt="">
        </button>
        <button type="button" class="jw-button typeF" onclick="deletefile3(this)">
          <img src="../image/button-close2.svg" alt="">
        </button>
      </div>
    </div>
  `;
	grid.appendChild(cell);
}

function noticeUploadChange4(input) {
	const f = input.files && input.files[0]; if (!f) return;
	const grid = input.closest('.grid-item'); if (!grid) return;

	const label = input.closest('label.inputFile'); if (label) label.remove();

	const name = f.name;
	const ext = (name.split('.').pop() || '').toLowerCase() || 'file';
	const size = f.size ? (f.size < 1024 ? f.size + ' B' : (f.size / 1024).toFixed(1) + ' KB') : '';

	const cell = document.createElement('div');
	cell.className = 'cell';
	cell.innerHTML = `
    <div class="field-row jw-center">
      <div class="jw-center jw-gap10">
        <img src="../image/file.svg" alt="">
        <span data-lan-eng="File">${name}</span> [${ext}${size ? ', ' + size : ''}]
      </div>
      <div class="jw-center jw-gap10">
        <i></i>
        <button type="button" class="jw-button typeF" onclick="console.log('download noop');">
          <img src="../image/buttun-download.svg" alt="">
        </button>
        <button type="button" class="jw-button typeF" onclick="deletefile4(this)">
          <img src="../image/button-close2.svg" alt="">
        </button>
      </div>
    </div>
  `;
	grid.appendChild(cell);
}

function attachItemDelete(btn) {
	const cell = btn.closest('.cell');
	if (cell) cell.remove();
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
	wrap.querySelectorAll('[data-essentialTarget="y"]').forEach(tg => {
		tg.disabled = !allOK;
		tg.classList.toggle('active', allOK);
		tg.classList.toggle('inactive', !allOK);
	});
	return allOK;
}
function essentialCheck2(it) {
	const wrap = it.closest('[data-essentialWrap="y"]');
	if (!wrap) return '';
	const essentials = wrap.querySelectorAll('[data-essential="y"]');
	if (!essentials.length) return '';
	const clean = (s) => (s || '').replace(/[\*\:\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();
	const getGroupChecked = (el) => {
		if (!el.name) return el.checked;
		const sel = `input[type="${el.type}"][name="${CSS.escape(el.name)}"]`;
		const scope = el.form || wrap;
		return Array.prototype.some.call(scope.querySelectorAll(sel), n => n.checked);
	};

	const findLabelText = (el) => {
		const custom = el.getAttribute('data-essential-label') || el.closest('[data-essential-label]')?.getAttribute('data-essential-label');
		if (custom && clean(custom)) return clean(custom);

		const aria = el.getAttribute('aria-label');
		if (aria && clean(aria)) return clean(aria);

		if (el.id) {
			const byFor = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
			if (byFor && clean(byFor.textContent)) return clean(byFor.textContent);
		}

		const parentLabel = el.closest('label');
		if (parentLabel && clean(parentLabel.textContent)) return clean(parentLabel.textContent);

		const prev = el.previousElementSibling;
		if (prev && (prev.tagName === 'LABEL' || prev.classList.contains('label') || prev.classList.contains('label-name'))) {
			const t = clean(prev.textContent); if (t) return t;
		}

		const fieldWrap = el.closest('.grid-item, .form-row, .field, .form-group, .editor-box-wrap, .form-cell');
		if (fieldWrap) {
			const inWrapLabel = fieldWrap.querySelector('label, .label, .label-name, .field-title, .title, .name');
			if (inWrapLabel) {
				const t = clean(inWrapLabel.textContent); if (t) return t;
			}
			if (/radio|checkbox/i.test(el.type || '')) {
				const legend = fieldWrap.querySelector('legend') || el.closest('fieldset')?.querySelector('legend');
				if (legend) {
					const t = clean(legend.textContent); if (t) return t;
				}
			}
		}
		return clean(el.getAttribute('placeholder') || el.name || el.id || '');
	};

	const seenRadio = new Set();

	for (const el of essentials) {
		const tag = el.tagName;
		const type = (el.type || '').toLowerCase();

		if (type === 'radio' && el.name) {
			if (seenRadio.has(el.name)) continue;
			seenRadio.add(el.name);
		}

		let ok = true;
		if (type === 'radio') ok = getGroupChecked(el);
		else if (type === 'checkbox') ok = el.checked;
		else if (tag === 'SELECT') ok = (el.value ?? '') !== '';
		else if (type === 'file') ok = !!(el.files && el.files.length > 0);
		else ok = ((el.value || '').trim().length > 0);

		if (!ok) return findLabelText(el);
	}

	return '';
}

function togglePlusMinus(btn) {
	var hasOn = btn.classList.contains('on');
	var img = btn.querySelector('img');
	var target = btn.getAttribute('data-target-section');
	var targets = target ? document.querySelectorAll('[data-section-name="' + target + '"]') : [];
	var row = btn.closest('.aside-row');

	if (hasOn) {
		btn.classList.remove('on');
		targets.forEach(function (el) { if (el !== btn) el.classList.remove('hidden'); });
		img.src = '../image/minus.svg';
		img.alt = 'minus';
		if (row) row.classList.remove('off');
	} else {
		btn.classList.add('on');
		targets.forEach(function (el) { if (el !== btn) el.classList.add('hidden'); });
		img.src = '../image/plus2.svg';
		img.alt = 'plus';
		if (row) row.classList.add('off');
	}
}

function trash_typeA(it) {
	var tbody = it.closest('tbody');
	if (!tbody) return;

	var rows = tbody.querySelectorAll('tr');
	if (rows.length === 1) {
		modal('product-detail_modal_5.html', '600px', '252px');
		return;
	}
	var tr = it.closest('tr');
	if (tr) tr.remove();
}

function toggle_password(btn) {
	const targetSel = btn.dataset.target;
	const box = btn.closest('.input-box');
	const input = targetSel
		? document.querySelector(targetSel)
		: (box ? box.querySelector('input[type="password"], input[type="text"]') : null);
	if (!input) return;

	const show = input.type === 'password';
	input.type = show ? 'text' : 'password';
}

function checkLogin(e) {
	if (e && e.preventDefault) e.preventDefault();
	const form = document.getElementById('loginForm');
	if (!form) return true;

	const idInput = form.querySelector('input[name="id"]');
	const pwdInput = form.querySelector('.input-box input[type="password"]');

	const id = (idInput && idInput.value || '').trim();
	const pwd = (pwdInput && pwdInput.value || '').trim();

	if (!id) {
		modal('member/false_case_1.html', '600px', '252px');
		if (idInput) idInput.focus();
		return false;
	}
	if (!pwd) {
		modal('member/false_case_2.html', '600px', '252px');
		if (pwdInput) pwdInput.focus();
		return false;
	}
	modal('member/false_case_3.html', '600px', '252px');
	//location.href = '/smart_travel/admin/super/overview.html';
	return true;
}

function change_category_1(el) {
	const head = el.closest('.gpc-head');
	if (!head) return;
	head.innerHTML =
		'<div class="gpc-form iptA">' +
		'<input type="text" placeholder="Enter main category name" data-lan-eng="Enter main category name">' +
		'<button type="button" class="btn-ghost" onclick="change_category_5(this);"><img src="../image/category-check2.svg" alt=""></button>' +
		'</div>';
	const input = head.querySelector('input');
	if (input) input.focus();
}

function change_category_2(el) {
	const item = el.closest('.gpc-item');
	if (!item) return;
	item.innerHTML = '<div class="gpc-form iptB jw-mgt4"><input type="text" placeholder="Enter category name" data-lan-eng="Enter category name"><button type="button" class="btn-ghost" onclick="change_category_4(this);"><img src="../image/category-check2.svg" alt=""></button></div>';
	const input = item.querySelector('input');
	if (input) input.focus();
}

function change_category_3(el) {
	const card = el.closest('.grid-panel-card');
	const body = card ? card.querySelector('.gpc-body') : document.querySelector('.gpc-body');
	if (!body) return;

	const item = document.createElement('div');
	item.className = 'gpc-item';
	item.innerHTML = `
    <div class="gpc-form iptB jw-mgt4"><input type="text" placeholder="Enter category name" data-lan-eng="Enter category name"><button type="button" class="btn-ghost" onclick="change_category_4(this);"><img src="../image/category-check2.svg" alt=""></button></div>
  `;
	body.appendChild(item);
}

function change_category_4(el) {
	const item = el.closest('.gpc-item');
	if (!item) return;
	const input = item.querySelector('input');
	const raw = (input && input.value || '').trim();
	const name = raw || 'text';
	const esc = (s) => String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
	item.innerHTML = `
    <div class="category">
      <span class="drag"><img src="../image/category-sort.svg" alt=""></span>
      <span class="name">${esc(name)}</span>
    </div>
	<div class="actions">
		<button class="icon-btn" onclick="change_category_2(this);"><img src="../image/category-edit.svg" alt=""></button>
		<button class="icon-btn" onclick="modal('category-management-m2.html', '600px', '252px')"><img src="../image/category-delete.svg" alt=""></button>
	</div>
  `;
}

function change_category_5(el) {
	const head = el.closest('.gpc-head');
	if (!head) return;

	const input = head.querySelector('input');
	const title = (input && input.value.trim()) || '제목없음';

	head.innerHTML =
		'<div class="gpc-title" data-lan-eng="Seasonal">' + title + '</div>' +
		'<div class="gpc-head-actions">' +
		'<button type="button" class="icon-btn" title="이름 수정" onclick="change_category_1(this);"><img src="../image/category-edit.svg" alt=""></button>' +
		'<button type="button" class="icon-btn" title="삭제" onclick="modal(\'category-management-m2.html\', \'600px\', \'252px\')"><img src="../image/category-delete.svg" alt=""></button>' +
		'</div>';
}

function makepassword(btn) {
	const box = btn.closest('.input-box');
	if (!box) return;
	const input = box.querySelector('input[type="password"], input[type="text"]');
	if (!input) return;

	const lowers = 'abcdefghijklmnopqrstuvwxyz';
	const uppers = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const digits = '0123456789';
	const specials = '!@#$%^&*()-_=+[]{};:,.?/';

	const all = lowers + uppers + digits + specials;
	const len = 8 + Math.floor(Math.random() * 5); // 8~12
	const pick = (set) => set[Math.floor(Math.random() * set.length)];
	const chars = [
		pick(lowers),
		pick(uppers),
		pick(digits),
		pick(specials)
	];

	for (let i = chars.length; i < len; i++) {
		chars.push(pick(all));
	}
	for (let i = chars.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		[chars[i], chars[j]] = [chars[j], chars[i]];
	}

	const pwd = chars.join('');
	input.value = pwd;
}

function temp_link(v){
	location.href=v;
}

function detail_submit(it) {
	// it.remove();
	// const targets = document.querySelectorAll('.temp-targetA');
	// if (!targets.length) return;

	// targets.forEach(el => {
	// 	const hiddenAncestor = el.closest('.hidden, [hidden]');
	// 	if (hiddenAncestor) {
	// 		hiddenAncestor.classList.remove('hidden');
	// 		if (hiddenAncestor.hasAttribute('hidden')) hiddenAncestor.removeAttribute('hidden');
	// 	}

	// 	if ('disabled' in el) el.disabled = true; else el.setAttribute('disabled', '');
	// });
	// 1) 기존 동작 그대로 유지
	it.remove();
	const targets = document.querySelectorAll('.temp-targetA');
  
	// 공통: 에디터 잠그는 함수
	function lockEditors() {
	  document.querySelectorAll('.jw-editor').forEach(root => {
		const editorArea = root.querySelector('.jweditor');
		if (!editorArea) return;
  
		let quill = null;
  
		// Quill에서 인스턴스 찾기
		if (window.Quill && typeof Quill.find === 'function') {
		  quill = Quill.find(editorArea);
		}
  
		// 혹시 __quill로 붙어 있으면 그것도 확인
		if (!quill && editorArea.__quill) {
		  quill = editorArea.__quill;
		}
  
		if (!quill) return;
  
		quill.enable(false);          // 🔒 입력 막기
		root.classList.add('editor-readonly');
	  });
	}
  
	// temp-targetA 없을 때도 에디터는 잠그고 끝
	if (!targets.length) {
	  lockEditors();
	  return;
	}
  
	// 원래 하던 처리
	targets.forEach(el => {
	  const hiddenAncestor = el.closest('.hidden, [hidden]');
	  if (hiddenAncestor) {
		hiddenAncestor.classList.remove('hidden');
		if (hiddenAncestor.hasAttribute('hidden')) hiddenAncestor.removeAttribute('hidden');
	  }
  
	  if ('disabled' in el) el.disabled = true;
	  else el.setAttribute('disabled', '');
	});
  
	// 2) 답변 처리 완료 후 에디터 잠그기
	lockEditors();
}

function board() {
	if (typeof window.Quill === 'undefined') return;
	quill = new Quill('.jweditor', {
		modules: {
			toolbar: {
				container: '#custom-toolbar',
				handlers: {
					align: function (value) {
						this.quill.format('align', value || false);
					}
				},
			},
		},
	});

	const Size = Quill.import('attributors/style/size');
	Size.whitelist = ['12px', '14px', '16px', '18px', '24px']; // 커스텀 값 추가
	Quill.register(Size, true);
	
	const fontSizeSelect = document.getElementById('fontSize');
	fontSizeSelect.value = '';
	quill.on('selection-change', () => {
		const format = quill.getFormat();
		if (format.size) {
			fontSizeSelect.value = format.size;
		} else {
			fontSizeSelect.value = '';
		}
		
		if (format.color) {
			document.querySelector('.font-color').style.color = format.color;
		}else{
			document.querySelector('.font-color').style.color = "#000";
		}
		if (format.background) {
			document.querySelector('.background-color').style.background = format.background;
		}else{
			document.querySelector('.background-color').style.background = "#000";
		}
	});
	
	const BlockEmbed = Quill.import('blots/block/embed');
	class CustomImageBlot extends BlockEmbed {
		static create(value) {
			const node = super.create(value.src);
			node.setAttribute('src', value.src);
			node.setAttribute('data-index', value.index); // 고유 인덱스 추가
			node.classList.add('custom-image-class');
			return node;
		}

		static value(node) {
			return {
				src: node.getAttribute('src'),
				index: node.getAttribute('data-index'),
			};
		}
	}
	CustomImageBlot.blotName = 'jw-image';
	CustomImageBlot.tagName = 'img';
	Quill.register(CustomImageBlot);

}

function toggleHtmlView() {
    const editor = document.querySelector('.ql-editor');
    if (isHtmlView) {
        editor.innerHTML = editor.textContent;
    } else {
        editor.textContent = editor.innerHTML; 
    }
    isHtmlView = !isHtmlView;
}

function removeHtmlTags() {
    const editor = document.querySelector('.ql-editor');
    editor.innerHTML = editor.innerText; 
}

function fontsize(it) {
	const size = it.value;
	quill.format('size', size); 
}

function setColor(button, type=1) {
    if (document.querySelector('.colorBox')) return;

    const colorBox = document.createElement('div');
    colorBox.classList.add('colorBox');

    const colors = getSpectrumColors(20);
    colors.push('#ffffff', '#000000');

    colors.forEach(color => {
        const colorButton = document.createElement('button');
        colorButton.style.backgroundColor = color;
        colorButton.onclick = (event) => {
            console.log(`${color}`);
			if(type!=2){
				button.style.color = color;
				quill.format('color', color);
			}else{
				button.style.color = color;
				quill.format('background', color);
			}

            setFontColorClose();
            event.stopPropagation(); // 클릭 이벤트가 body로 전파되지 않도록 방지
        };
        colorBox.appendChild(colorButton);
    });

    // body에 colorBox 추가
    button.appendChild(colorBox);

    // 외곽 클릭 이벤트 추가 (전역에서 한 번만 등록)
    document.addEventListener('click', removeColorBox);

    // colorBox 닫기 함수
    function setFontColorClose() {
        if (colorBox) {
            colorBox.remove();
            document.removeEventListener('click', removeColorBox);
        }
    }

    // 외곽 클릭 감지 함수
    function removeColorBox(event) {
        if (!colorBox.contains(event.target) && event.target !== button) {
            setFontColorClose();
        }
    }
}

function getSpectrumColors(count) {
    let colors = [];
    for (let i = 0; i < count; i++) {
        let hue = (i / count) * 360; // 0° ~ 360° 범위를 균등 분배
        colors.push(hslToHex(hue, 100, 50)); // HSL을 Hex 코드로 변환하여 추가
    }
    return colors;
}

function hslToHex(h, s, l) {
	s /= 100;
	l /= 100;
	let c = (1 - Math.abs(2 * l - 1)) * s;
	let x = c * (1 - Math.abs((h / 60) % 2 - 1));
	let m = l - c / 2;
	let r, g, b;

	if (h < 60) {
	    r = c;
	    g = x;
	    b = 0;
	} else if (h < 120) {
	    r = x;
	    g = c;
	    b = 0;
	} else if (h < 180) {
	    r = 0;
	    g = c;
	    b = x;
	} else if (h < 240) {
	    r = 0;
	    g = x;
	    b = c;
	} else if (h < 300) {
	    r = x;
	    g = 0;
	    b = c;
	} else {
	    r = c;
	    g = 0;
	    b = x;
	}

	r = Math.round((r + m) * 255);
	g = Math.round((g + m) * 255);
	b = Math.round((b + m) * 255);

	return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
}

function insertImage(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        const base64Image = e.target.result;

        const range = quill.getSelection();
        quill.insertEmbed(range.index, 'jw-image', {
            src: base64Image,
            index: imageIndex++,
        });
    };
    reader.readAsDataURL(file);
}

// 이미지 검색
function findImage(index) {
    return imageStorage.find((image) => image.index === index);
}

// 이미지 삭제
function removeImage(index) {
    imageStorage = imageStorage.filter((image) => image.index !== index);
    const img = document.querySelector(`.ql-editor img[data-index="${index}"]`);
    if (img) img.remove();
}

// 모든 이미지 출력
function listImages() {
    console.log('Current images:', imageStorage);
}