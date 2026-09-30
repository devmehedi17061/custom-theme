(function () {
	'use strict';

	var defs = window.bfdSectionDefs;
	var input = document.getElementById('bfd-sections-input');
	var root = document.getElementById('bfd-section-builder');
	if (!defs || !input || !root) return;

	var state;
	try { state = JSON.parse(input.value || '[]'); } catch (e) { state = []; }
	if (!Array.isArray(state)) state = [];

	// Cards start collapsed; newly added ones start expanded.
	var expanded = new WeakSet();
	var previewing = new WeakSet();
	var caches = new WeakMap();
	var pageCache = { html: null };
	var pageOpen = false;

	function sync() { input.value = JSON.stringify(state); }

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text != null) n.textContent = text;
		return n;
	}

	function button(label, cls, onClick) {
		var b = el('button', 'button ' + (cls || ''), label);
		b.type = 'button';
		b.addEventListener('click', onClick);
		return b;
	}

	function move(arr, i, dir) {
		var j = i + dir;
		if (j < 0 || j >= arr.length) return;
		var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
		sync(); render();
	}

	function defaultValue(f) {
		switch (f.type) {
			case 'repeater': return [];
			case 'checkbox': return false;
			case 'image': return 0;
			case 'number': return 0;
			case 'select': return f.default != null ? String(f.default) : (f.options && f.options[0] ? f.options[0].value : '');
			default: return '';
		}
	}

	function defaultsFor(fields) {
		var o = {};
		fields.forEach(function (f) { o[f.key] = defaultValue(f); });
		return o;
	}

	function findDef(type) {
		return defs.sections.filter(function (s) { return s.type === type; })[0];
	}

	/* ---------- Field controls ---------- */

	function control(f, obj) {
		var wrap = el('div', 'bfd-field bfd-field--' + f.type);
		var label = el('label', 'bfd-field__label', f.label);
		wrap.appendChild(label);
		if (obj[f.key] === undefined) obj[f.key] = defaultValue(f);

		function set(v) { obj[f.key] = v; sync(); }

		switch (f.type) {
			case 'textarea':
			case 'richtext':
			case 'html':
				var ta = el('textarea', 'widefat');
				ta.rows = f.type === 'textarea' ? 3 : 8;
				if (f.type === 'html') ta.className += ' code';
				ta.value = obj[f.key] || '';
				ta.addEventListener('input', function () { set(ta.value); });
				wrap.appendChild(ta);
				break;

			case 'select':
				var sel = el('select');
				f.options.forEach(function (o) {
					var opt = el('option', '', o.label);
					opt.value = o.value;
					sel.appendChild(opt);
				});
				sel.value = String(obj[f.key]);
				sel.addEventListener('change', function () { set(sel.value); });
				wrap.appendChild(sel);
				break;

			case 'checkbox':
				var cb = el('input');
				cb.type = 'checkbox';
				cb.checked = !!obj[f.key];
				cb.addEventListener('change', function () { set(cb.checked); });
				wrap.appendChild(cb);
				break;

			case 'image':
				wrap.appendChild(imageControl(obj, f, set));
				break;

			case 'repeater':
				wrap.appendChild(repeaterControl(obj, f));
				break;

			default: // text, url, color, number
				var inp = el('input', 'widefat');
				inp.type = f.type === 'url' ? 'url' : (f.type === 'number' ? 'number' : 'text');
				inp.value = obj[f.key] == null ? '' : obj[f.key];
				inp.addEventListener('input', function () {
					set(f.type === 'number' ? Number(inp.value) : inp.value);
				});
				wrap.appendChild(inp);
		}
		return wrap;
	}

	function imageControl(obj, f, set) {
		var box = el('div', 'bfd-image');
		var preview = el('img', 'bfd-image__preview');
		preview.alt = '';
		var pick = button('Select image', '', open);
		var clear = button('Remove', '', function () { set(0); show(0); });

		function show(id) {
			preview.hidden = !id;
			clear.hidden = !id;
			pick.textContent = id ? 'Change image' : 'Select image';
			if (!id) return;
			var att = wp.media.attachment(id);
			function paint() {
				var sizes = att.get('sizes') || {};
				preview.src = (sizes.thumbnail && sizes.thumbnail.url) || att.get('url') || '';
			}
			if (att.get('url')) paint(); else att.fetch().then(paint);
		}

		function open() {
			var frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				set(a.id); show(a.id);
			});
			frame.open();
		}

		box.appendChild(preview);
		box.appendChild(pick);
		box.appendChild(clear);
		show(obj[f.key]);
		return box;
	}

	function repeaterControl(obj, f) {
		var rows = obj[f.key];
		if (!Array.isArray(rows)) rows = obj[f.key] = [];
		var box = el('div', 'bfd-repeater');

		rows.forEach(function (row, i) {
			var r = el('div', 'bfd-repeater__row');
			var bar = el('div', 'bfd-repeater__bar');
			bar.appendChild(el('strong', '', '#' + (i + 1)));
			bar.appendChild(button('↑', 'button-small', function () { move(rows, i, -1); }));
			bar.appendChild(button('↓', 'button-small', function () { move(rows, i, 1); }));
			bar.appendChild(button('Remove', 'button-small', function () { rows.splice(i, 1); sync(); render(); }));
			r.appendChild(bar);
			f.sub_fields.forEach(function (sf) { r.appendChild(control(sf, row)); });
			box.appendChild(r);
		});

		box.appendChild(button('+ Add item', '', function () {
			rows.push(defaultsFor(f.sub_fields));
			sync(); render();
		}));
		return box;
	}

	/* ---------- Preview ---------- */

	function fetchPreview(sections) {
		var fd = new FormData();
		fd.append('action', 'bfd_preview_sections');
		fd.append('nonce', defs.ajax.nonce);
		fd.append('post_id', defs.ajax.postId);
		fd.append('sections', JSON.stringify(sections));
		return fetch(defs.ajax.url, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) {
				if (!r.ok) throw new Error('Preview failed (HTTP ' + r.status + ')');
				return r.text();
			});
	}

	function previewBox(title, getSections, cache) {
		var box = el('div', 'bfd-preview');
		var bar = el('div', 'bfd-preview__bar');
		bar.appendChild(el('strong', '', title));
		var status = el('span', 'bfd-preview__status');
		bar.appendChild(status);
		var frame = el('iframe', 'bfd-preview__frame');
		frame.setAttribute('sandbox', 'allow-same-origin'); // no scripts, no navigation
		frame.title = title;

		function fit() {
			try {
				var h = frame.contentDocument.documentElement.scrollHeight;
				frame.style.height = Math.min(Math.max(h, 120), 720) + 'px';
			} catch (e) { /* ignore */ }
		}
		frame.addEventListener('load', function () { fit(); setTimeout(fit, 400); });

		function load() {
			status.textContent = 'Loading…';
			fetchPreview(getSections()).then(function (html) {
				cache.html = html;
				frame.srcdoc = html;
				status.textContent = '';
			}).catch(function (err) {
				status.textContent = err.message;
			});
		}

		bar.appendChild(button('Refresh', 'button-small', load));
		box.appendChild(bar);
		box.appendChild(frame);

		if (cache.html) frame.srcdoc = cache.html; else load();
		return box;
	}

	function cacheFor(section) {
		var c = caches.get(section);
		if (!c) { c = { html: null }; caches.set(section, c); }
		return c;
	}

	/* ---------- Sections ---------- */

	function summary(section) {
		var f = section.fields || {};
		var t = f.heading || f.title || f.question || '';
		if (!t) {
			Object.keys(f).some(function (k) {
				if (typeof f[k] === 'string' && f[k].trim()) { t = f[k].trim(); return true; }
				return false;
			});
		}
		return t.length > 60 ? t.slice(0, 60) + '…' : t;
	}

	function sectionCard(section, i) {
		var def = findDef(section.type);
		if (!def) return el('div');
		section.fields = section.fields || {};
		section.settings = section.settings || {};

		var isOpen = expanded.has(section);
		var isPreview = previewing.has(section);

		var card = el('div', 'bfd-section-card');
		var head = el('div', 'bfd-section-card__head');
		var title = el('span', 'bfd-section-card__title');
		title.appendChild(el('strong', '', (i + 1) + '. ' + def.label));
		var sum = summary(section);
		if (sum) title.appendChild(el('span', 'bfd-section-card__summary', ' — ' + sum));
		head.appendChild(title);

		var actions = el('span', 'bfd-section-card__actions');
		actions.appendChild(button('↑', 'button-small', function () { move(state, i, -1); }));
		actions.appendChild(button('↓', 'button-small', function () { move(state, i, 1); }));
		actions.appendChild(button(isPreview ? 'Hide preview' : 'Preview', 'button-small', function () {
			if (isPreview) { previewing['delete'](section); } else { previewing.add(section); cacheFor(section).html = null; }
			render();
		}));
		actions.appendChild(button(isOpen ? 'Collapse' : 'Expand', 'button-small', function () {
			if (isOpen) expanded['delete'](section); else expanded.add(section);
			render();
		}));
		actions.appendChild(button('Delete', 'button-small', function () {
			state.splice(i, 1); sync(); render();
		}));
		head.appendChild(actions);
		card.appendChild(head);

		if (isPreview) {
			card.appendChild(previewBox('Preview', function () { return [section]; }, cacheFor(section)));
		}

		if (isOpen) {
			var body = el('div', 'bfd-section-card__body');
			def.fields.forEach(function (f) { body.appendChild(control(f, section.fields)); });

			var settings = el('fieldset', 'bfd-settings');
			settings.appendChild(el('legend', '', 'Section settings'));
			defs.settings.forEach(function (f) { settings.appendChild(control(f, section.settings)); });
			body.appendChild(settings);
			card.appendChild(body);
		}
		return card;
	}

	function render() {
		root.textContent = '';

		if (!state.length) root.appendChild(el('p', 'bfd-empty', 'No sections yet. Add one below.'));
		state.forEach(function (s, i) { root.appendChild(sectionCard(s, i)); });

		var add = el('div', 'bfd-add');
		var sel = el('select');
		defs.sections.forEach(function (d) {
			var o = el('option', '', d.label);
			o.value = d.type;
			sel.appendChild(o);
		});
		add.appendChild(sel);
		add.appendChild(button('Add section', 'button-primary', function () {
			var d = findDef(sel.value);
			var section = { type: d.type, settings: defaultsFor(defs.settings), fields: defaultsFor(d.fields) };
			state.push(section);
			expanded.add(section);
			sync(); render();
		}));
		if (state.length) {
			add.appendChild(button(pageOpen ? 'Hide page preview' : 'Preview full page', '', function () {
				pageOpen = !pageOpen;
				pageCache.html = null;
				render();
			}));
		}
		root.appendChild(add);

		if (pageOpen && state.length) {
			root.appendChild(previewBox('Full page preview', function () { return state; }, pageCache));
		}
	}

	sync();
	render();
})();
