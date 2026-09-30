guard(function () {
	wait(function () { return qa('.wp-picker-container').length; }, function (err) {
		ok('color pickers initialize', !err && qa('.wp-picker-container').length === 5, 'count=' + qa('.wp-picker-container').length);
		var heading = q('#bfd-opt-heading_font');
		ok('font select has 6 stacks', !!heading && heading.options.length === 6);
		['Header button text', 'Phone', 'Facebook', 'Default social share image', 'Primary color', 'Google Analytics 4 ID', 'Cloudflare Turnstile site key', 'Delete ALL plugin data'].forEach(function (t) {
			ok('option present: ' + t, document.body.textContent.indexOf(t) > -1);
		});
		ok('image picker + media frame available', !!q('.bfd-media-pick') && !!window.wp && !!wp.media);
		ok('turnstile secret is a password field', (q('#bfd-opt-turnstile_secret') || {}).type === 'password');
		finish();
	});
});
