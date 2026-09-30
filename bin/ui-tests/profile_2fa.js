function b32decode(s) {
	var A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', bits = '', out = [];
	s.replace(/[^A-Z2-7]/gi, '').toUpperCase().split('').forEach(function (c) { bits += ('00000' + A.indexOf(c).toString(2)).slice(-5); });
	for (var i = 0; i + 8 <= bits.length; i += 8) out.push(parseInt(bits.substr(i, 8), 2));
	return new Uint8Array(out);
}
// Same maths an authenticator app does (RFC 6238, SHA-1, 6 digits, 30 s).
function totp(secret, stepOffset) {
	var counter = Math.floor(Date.now() / 30000) + (stepOffset || 0);
	var msg = new Uint8Array(8), c = counter;
	for (var i = 7; i >= 0; i--) { msg[i] = c & 0xff; c = Math.floor(c / 256); }
	return crypto.subtle.importKey('raw', b32decode(secret), { name: 'HMAC', hash: 'SHA-1' }, false, ['sign']).then(function (k) {
		return crypto.subtle.sign('HMAC', k, msg);
	}).then(function (sig) {
		var h = new Uint8Array(sig), o = h[19] & 0xf;
		var n = ((h[o] & 0x7f) << 24 | h[o + 1] << 16 | h[o + 2] << 8 | h[o + 3]) % 1000000;
		return ('000000' + n).slice(-6);
	});
}
var PAGE = location.origin + '/wp-admin/users.php?page=bfd-2fa';
function parse(html) { return new DOMParser().parseFromString(html, 'text/html'); }
function get(url) { return fetch(url || PAGE, { credentials: 'same-origin' }).then(function (r) { return r.text(); }); }
function box(html) { return parse(html).querySelector('#bfd-2fa'); }
function submit(form, extra) {
	var fd = new FormData(form);
	Object.keys(extra || {}).forEach(function (k) { fd.set(k, extra[k]); });
	return fetch(form.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); });
}
function formFor(html, action) { var i = box(html).querySelector('input[value=' + action + ']'); return i && i.form; }

guard(function () {
	var secret;
	ok('profile page has a status block + link to the setup page', !!q('#bfd-2fa-status') && !!q('a[href*="page=bfd-2fa"]'));
	ok('no 2FA forms nested inside the profile form', !q('#your-profile input[value=bfd_2fa_start]') && !q('#your-profile input[value=bfd_2fa_enable]'));
	ok('admin nag shown while off', /Protect your admin account/i.test(document.body.textContent));

	get().then(function (html) {
		ok('setup page reachable, offers setup', !!box(html) && !!formFor(html, 'bfd_2fa_start'));
		return submit(formFor(html, 'bfd_2fa_start'));
	}).then(function (html) {
		var keyEl = parse(html).querySelector('#bfd-2fa .bfd-2fa-key');
		secret = keyEl ? keyEl.textContent.replace(/\s/g, '') : '';
		ok('setup key displayed (32 chars)', /^[A-Z2-7]{32}$/.test(secret), secret.length + ' chars');
		ok('otpauth link offered', !!parse(html).querySelector('a[href^="otpauth://totp/"]'));
		return submit(formFor(html, 'bfd_2fa_enable'), { code: '000000' });
	}).then(function () { return get(); }).then(function (html) {
		ok('wrong code does not enable', !!formFor(html, 'bfd_2fa_enable') && !/Enabled\./.test(box(html).textContent));
		return totp(secret).then(function (code) { return submit(formFor(html, 'bfd_2fa_enable'), { code: code }); });
	}).then(function (html) { // the page we are redirected to right after enabling
		var text = box(html).textContent, codes = text.match(/[a-z0-9]{5}-[a-z0-9]{5}/g) || [];
		ok('correct code enables 2FA', /Enabled\./.test(text));
		ok('8 backup codes shown once', codes.length === 8, codes.length + ' found');
		return get();
	}).then(function (html) {
		var text = box(html).textContent;
		ok('backup codes are not shown again', !/[a-z0-9]{5}-[a-z0-9]{5}/.test(text) && /8 backup codes left/.test(text));
		return submit(formFor(html, 'bfd_2fa_disable'), { code: '123456' });
	}).then(function () { return get(); }).then(function (html) {
		ok('wrong code cannot disable', /Enabled\./.test(box(html).textContent));
		// The current 30 s window was consumed by enabling, so use the next one (still inside the accepted window).
		return totp(secret, 1).then(function (next) { return submit(formFor(html, 'bfd_2fa_disable'), { code: next }); });
	}).then(function () { return get(); }).then(function (html) {
		var text = box(html).textContent;
		ok('valid code turns 2FA off again', !/Enabled\./.test(text) && !!formFor(html, 'bfd_2fa_start'));
		return get(location.origin + '/wp-admin/profile.php');
	}).then(function (html) {
		ok('profile page status reflects Off', /Off/.test(parse(html).querySelector('#bfd-2fa-status').nextElementSibling.textContent));
		finish();
	}).catch(function (e) { ok('2FA setup flow', false, e.message); finish(); });
});
