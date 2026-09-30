var R = [];
function ok(name, cond, extra) { R.push((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  ' + extra : '')); }
function finish() {
	var pre = document.createElement('pre');
	pre.id = 'uitest';
	pre.textContent = R.join('\n');
	document.body.appendChild(pre);
}
function wait(cond, cb, tries) {
	tries = tries || 80;
	(function poll() {
		var v; try { v = cond(); } catch (e) { v = false; }
		if (v) return cb(null, v);
		if (--tries <= 0) return cb(new Error('timeout'));
		setTimeout(poll, 100);
	})();
}
function q(sel, root) { return (root || document).querySelector(sel); }
function qa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
function btn(scope, text) { return qa('button', scope).filter(function (b) { return b.textContent === text; })[0]; }
function fire(node, type) { node.dispatchEvent(new Event(type, { bubbles: true })); }
function setVal(node, v) { node.value = v; fire(node, 'input'); fire(node, 'change'); }
function fieldByLabel(scope, label) {
	var l = qa('.bfd-field__label', scope).filter(function (n) { return n.textContent.indexOf(label) === 0; })[0];
	return l && l.parentNode;
}
function postForm(form, extra) {
	var fd = new FormData(form);
	Object.keys(extra || {}).forEach(function (k) { fd.set(k, extra[k]); });
	// form.action is shadowed by WordPress's <input name="action">, so read the attribute.
	return fetch(form.getAttribute('action') || location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
}
function guard(fn) {
	window.addEventListener('load', function () {
		try { fn(); } catch (e) { R.push('ERROR ' + e.message); finish(); }
	});
}
