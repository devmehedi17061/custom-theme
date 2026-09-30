(function () {
	'use strict';

	var input = document.getElementById('bfd-cs-fields-input');
	var root = document.getElementById('bfd-field-definer');
	if (!input || !root) return;

	var TYPES = [
		['text', 'Text'], ['textarea', 'Textarea'], ['richtext', 'Rich text (HTML)'],
		['url', 'URL'], ['image', 'Image'], ['select', 'Select'], ['checkbox', 'Checkbox'],
		['color', 'Color'], ['number', 'Number'], ['repeater', 'Repeater']
	];
	// Optional per-screen config: data-types (JSON [[value,label],...]), data-repeater="0",
	// data-ph="0" (hide {{placeholder}} hint), data-required="1" (show Required checkbox).
	var cfg = root.dataset;
	if (cfg.types) { try { TYPES = JSON.parse(cfg.types); } catch (e) { /* keep defaults */ } }
	var ALLOW_REPEATER = cfg.repeater !== '0';
	var SHOW_PH = cfg.ph !== '0';
	var SHOW_REQ = cfg.required === '1';

	var state;
	try { state = JSON.parse(input.value || '[]'); } catch (e) { state = []; }
	if (!Array.isArray(state)) state = [];

	var manualKey = new WeakSet();

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

	function slug(s) {
		return s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
	}

	function labeled(text, node) {
		var w = el('div');
		w.appendChild(el('label', '', text));
		w.appendChild(node);
		return w;
	}

	function newField() {
		return { label: '', key: '', type: 'text', options: '', required: false, sub_fields: [] };
	}

	function renderList(arr, allowRepeater) {
		var box = el('div', 'bfd-fd-list');

		arr.forEach(function (f, i) {
			if (f.key) manualKey.add(f);
			var row = el('div', 'bfd-fd-row');
			var main = el('div', 'bfd-fd-row__main');

			var ph = el('div', 'bfd-fd-row__ph');
			function paintPh() {
				if (!SHOW_PH) return;
				ph.textContent = f.key
					? (f.type === 'repeater' ? '{{#' + f.key + '}} … {{/' + f.key + '}}' : '{{' + f.key + '}}')
					: '';
			}

			var label = el('input', 'widefat');
			label.type = 'text';
			label.value = f.label || '';
			var keyInput = el('input', 'widefat');
			keyInput.type = 'text';
			keyInput.value = f.key || '';

			label.addEventListener('input', function () {
				f.label = label.value;
				if (!manualKey.has(f)) { f.key = slug(label.value); keyInput.value = f.key; }
				paintPh(); sync();
			});
			keyInput.addEventListener('input', function () {
				manualKey.add(f);
				f.key = slug(keyInput.value);
				paintPh(); sync();
			});

			var type = el('select');
			TYPES.forEach(function (t) {
				if (t[0] === 'repeater' && !allowRepeater) return;
				var o = el('option', '', t[1]);
				o.value = t[0];
				type.appendChild(o);
			});
			type.value = f.type;
			type.addEventListener('change', function () {
				f.type = type.value;
				sync(); render();
			});

			var actions = el('div', 'bfd-fd-row__actions');
			if (SHOW_REQ && f.type !== 'checkbox_hidden') {
				var reqLabel = el('label', 'bfd-fd-req');
				var req = el('input');
				req.type = 'checkbox';
				req.checked = !!f.required;
				req.addEventListener('change', function () { f.required = req.checked; sync(); });
				reqLabel.appendChild(req);
				reqLabel.appendChild(document.createTextNode(' Required'));
				actions.appendChild(reqLabel);
			}
			actions.appendChild(button('↑', 'button-small', function () { swap(arr, i, -1); }));
			actions.appendChild(button('↓', 'button-small', function () { swap(arr, i, 1); }));
			actions.appendChild(button('Remove', 'button-small', function () { arr.splice(i, 1); sync(); render(); }));

			main.appendChild(labeled('Label', label));
			main.appendChild(labeled('Key', keyInput));
			main.appendChild(labeled('Type', type));
			main.appendChild(actions);
			row.appendChild(main);
			paintPh();
			row.appendChild(ph);

			if (f.type === 'select') {
				var ta = el('textarea', 'widefat');
				ta.rows = 3;
				ta.placeholder = 'One option per line: value|Label  (or just Label)';
				ta.value = f.options || '';
				ta.addEventListener('input', function () { f.options = ta.value; sync(); });
				var extra = el('div', 'bfd-fd-row__extra');
				extra.appendChild(labeled('Options', ta));
				row.appendChild(extra);
			}

			if (f.type === 'repeater') {
				if (!Array.isArray(f.sub_fields)) f.sub_fields = [];
				var sub = el('div', 'bfd-fd-sub');
				sub.appendChild(el('strong', '', 'Repeater sub-fields'));
				sub.appendChild(renderList(f.sub_fields, false));
				row.appendChild(sub);
			}

			box.appendChild(row);
		});

		box.appendChild(button('+ Add field', '', function () { arr.push(newField()); sync(); render(); }));
		return box;
	}

	function swap(arr, i, dir) {
		var j = i + dir;
		if (j < 0 || j >= arr.length) return;
		var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
		sync(); render();
	}

	function render() {
		root.textContent = '';
		root.appendChild(renderList(state, ALLOW_REPEATER));
	}

	sync();
	render();
})();
