guard(function () {
	wait(function () { return q('#bfd-field-definer .bfd-fd-list'); }, function (err) {
		if (err) { ok('custom section editor rendered', false, 'timeout'); return finish(); }
		var root = q('#bfd-field-definer');
		ok('field definer shows saved fields', qa('.bfd-fd-row', root).length >= 1);
		ok('placeholder hints shown', /\{\{[a-z_]+\}\}/.test(root.textContent));
		ok('repeater type available here', qa('select option', root).some(function (o) { return o.value === 'repeater'; }));
		ok('template, CSS and settings boxes present', !!q('textarea[name=bfd_cs_template]') && !!q('textarea[name=bfd_cs_css]') && !!q('input[name=bfd_cs_container]'));
		ok('syntax help present', /Placeholder syntax/.test(document.body.textContent));
		finish();
	});
});
