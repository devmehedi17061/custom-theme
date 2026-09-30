guard(function () {
	wait(function () { return q('#bfd-section-builder .bfd-section-card'); }, function (err) {
		if (err) { ok('builder rendered', false, 'timeout'); return finish(); }
		var root = q('#bfd-section-builder'), input = q('#bfd-sections-input');
		var cards = function () { return qa('.bfd-section-card', root); };

		ok('Page Sections box present', !!q('#bfd-sections'));
		ok('SEO box present with fields', !!q('#bfd-seo') && !!q('#bfd_seo_title') && !!q('#bfd_seo_description') && !!q('#bfd-seo .bfd-media-pick'));
		ok('Gutenberg not loaded on pages', !document.body.classList.contains('block-editor-page'));
		ok('2 saved sections start collapsed', cards().length === 2 && !q('.bfd-section-card__body', root));

		// Add a Rich Text section -> real TinyMCE
		q('.bfd-add select', root).value = 'rich_text';
		btn(q('.bfd-add', root), 'Add section').click();
		wait(function () { return window.tinymce && tinymce.editors.length && tinymce.editors[0].initialized; }, function (e2) {
			ok('TinyMCE visual editor initializes', !e2);
			if (e2) return finish();
			var ed = tinymce.editors[0];
			ed.setContent('<p>Hello <strong>world</strong></p>');
			ed.fire('change');
			var st = JSON.parse(input.value);
			ok('visual editor content synced to state', /<strong>world<\/strong>/.test(st[2].fields.content), (st[2].fields.content || '').slice(0, 50));

			var card = cards()[2];
			setVal(q('input', fieldByLabel(card, 'Heading')), 'UI Rich Heading');
			setVal(q('select', fieldByLabel(card, 'Vertical spacing')), 'large');
			setVal(q('input', fieldByLabel(card, 'Anchor ID')), 'My Anchor!');
			setVal(q('input', fieldByLabel(card, 'Extra CSS classes')), 'foo bar');

			// Preview through real admin-ajax + theme
			btn(cards()[0], 'Preview').click();
			wait(function () { var f = q('iframe', cards()[0]); return f && f.srcdoc && f.srcdoc.length > 200; }, function (e3) {
				var html = e3 ? '' : q('iframe', cards()[0]).srcdoc;
				ok('live preview returns themed HTML', /bfd-section--hero/.test(html) && /UI Hero/.test(html) && /main(\.min)?\.css/.test(html));

				// Save with the real WordPress form (nonce, capability, sanitize)
				var form = q('#post');
				postForm(form, { save: 'Update' }).then(function () {
					return fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); });
				}).then(function (page) {
					var doc = new DOMParser().parseFromString(page, 'text/html');
					var saved = JSON.parse(doc.getElementById('bfd-sections-input').value);
					var s = saved[2] || {};
					ok('saved through wp-admin form', saved.length === 3 && s.type === 'rich_text', saved.map(function (x) { return x.type; }).join(','));
					ok('settings sanitized + stored', s.settings && s.settings.padding === 'large' && s.settings.anchor === 'my-anchor' && s.settings.css_class === 'foo bar', JSON.stringify(s.settings || {}).slice(0, 110));
					ok('rich text stored', s.fields && /<strong>world<\/strong>/.test(s.fields.content));
					var pid = q('#post_ID').value;
					return fetch('/?page_id=' + pid, { credentials: 'same-origin' }).then(function (r) { return r.text(); });
				}).then(function (front) {
					ok('front-end shows heading + bold text', /UI Rich Heading/.test(front) && /<strong>world<\/strong>/.test(front));
					ok('front-end applies anchor, spacing, class', /id="my-anchor"/.test(front) && /bfd-pad--large/.test(front) && /foo bar/.test(front));
					finish();
				}).catch(function (e4) { ok('save/verify flow', false, e4.message); finish(); });
			});
		});
	});
});
