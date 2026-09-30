guard(function () {
	wait(function () { return q('#bfd-field-definer .bfd-fd-list'); }, function (err) {
		if (err) { ok('form builder rendered', false, 'timeout'); return finish(); }
		var root = q('#bfd-field-definer'), input = q('#bfd-cs-fields-input');
		var add = function () { btn(root, '+ Add field').click(); };
		var rows = function () { return qa('.bfd-fd-row', root); };

		ok('Fields / Settings / Use boxes present', !!q('#bfd-form-fields') && !!q('#bfd-form-settings') && !!q('#bfd-form-usage'));
		add(); add();
		ok('two field rows added', rows().length === 2);
		var typeOpts = qa('select option', rows()[0]).map(function (o) { return o.value; });
		ok('form types offered (no repeater)', typeOpts.indexOf('file') > -1 && typeOpts.indexOf('email') > -1 && typeOpts.indexOf('repeater') === -1, typeOpts.join(','));
		ok('Required checkbox shown', !!q('.bfd-fd-req input', rows()[0]));

		var label = q('input[type=text]', rows()[0]);
		setVal(label, 'Full name');
		ok('key auto-generated from label', JSON.parse(input.value)[0].key === 'full_name');
		q('.bfd-fd-req input', rows()[0]).click();
		setVal(qa('input[type=text]', rows()[1])[0], 'Your CV');
		var sel = q('select', rows()[1]); sel.value = 'file'; fire(sel, 'change');

		// Add a conditional field and a step break
		add(); add();
		setVal(qa('input[type=text]', rows()[2])[0], 'Nickname');
		var condBox = q('.bfd-fd-cond', rows()[2]);
		ok('"Show only if" control offered on later fields', !!condBox && !q('.bfd-fd-cond', rows()[0]));
		var condCb = q('input[type=checkbox]', condBox); condCb.click();
		var condSelects = qa('select', q('.bfd-fd-cond', rows()[2]));
		setVal(condSelects[1], 'not_empty');
		ok('condition stored in state', JSON.parse(input.value)[2].show_if && JSON.parse(input.value)[2].show_if.op === 'not_empty' && JSON.parse(input.value)[2].show_if.field === 'full_name', JSON.stringify(JSON.parse(input.value)[2].show_if));
		setVal(qa('input[type=text]', rows()[3])[0], 'More details');
		var stepSel = q('select', rows()[3]); stepSel.value = 'step'; fire(stepSel, 'change');
		ok('step break has no Required/Show-if controls', !q('.bfd-fd-req', rows()[3]) && !q('.bfd-fd-cond', rows()[3]));

		setVal(q('#title'), 'UI Form Test');
		q('#bfd_f_recipients').value = 'owner@example.com';
		var form = q('#post');
		postForm(form, { publish: 'Publish' }).then(function (r) { return r.text(); }).then(function (page) {
			var doc = new DOMParser().parseFromString(page, 'text/html');
			var el = doc.getElementById('bfd-cs-fields-input');
			var saved = el ? JSON.parse(el.value) : [];
			ok('form saved via wp-admin', saved.length === 4, 'rows=' + saved.length);
			ok('required + types persisted', saved[0] && saved[0].required === true && saved[1] && saved[1].type === 'file', JSON.stringify(saved).slice(0, 120));
			var use = doc.getElementById('bfd-form-usage');
			ok('condition + step persisted', saved[2] && saved[2].show_if && saved[2].show_if.op === 'not_empty' && saved[3] && saved[3].type === 'step', JSON.stringify([saved[2] && saved[2].show_if, saved[3] && saved[3].type]));
			ok('shortcode shown after publish', use && /\[bfd_form id="\d+"\]/.test(use.textContent));
			finish();
		}).catch(function (e) { ok('form save', false, e.message); finish(); });
	});
});
