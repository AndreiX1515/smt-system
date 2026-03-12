function init(options) {
	const start = async () => {
		await runInit(options);
		layoutNav();
		nav_status();

		const lang = getCookie('lang') || 'eng';
		setCookie('lang', lang, 365);
		await Promise.resolve(language_apply(lang));
		jw_select();
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
}


//  : Promise 
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

// /      
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
