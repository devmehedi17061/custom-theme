guard(function () {
	wait(function () { return q('form.bfd-form .bfd-form__step'); }, function (err) {
		if (err) { ok('multi-step form rendered', false, 'timeout'); return finish(); }
		var form = q('form.bfd-form');
		var steps = qa('.bfd-form__step', form);
		var wrap = function (key) { return q('[data-field="' + key + '"]', form); };
		var input = function (key) { return q('[name="f_' + key + '"]', form); };
		var next = q('[data-next]', form), prev = q('[data-prev]', form), actions = q('.bfd-form__actions', form);

		ok('two steps rendered', steps.length === 2);
		ok('step 1 visible, step 2 hidden', !steps[0].hidden && steps[1].hidden);
		ok('progress text', /Step 1 of 2/.test(q('.bfd-form__progress', form).textContent), q('.bfd-form__progress', form).textContent);
		ok('submit hidden until the last step', actions.hidden && !next.hidden && prev.hidden);
		ok('conditional field starts hidden + disabled', wrap('order_number').hidden && input('order_number').disabled);

		setVal(input('topic'), 'b');
		ok('condition true -> field shown + enabled', !wrap('order_number').hidden && !input('order_number').disabled);
		setVal(input('topic'), 'a');
		ok('condition false again -> hidden + disabled', wrap('order_number').hidden && input('order_number').disabled);

		next.click();
		ok('Next blocked while a required field is empty', !steps[0].hidden);

		setVal(input('name'), 'Ann');
		setVal(input('topic'), 'b');
		next.click();
		ok('Next blocked by a required field that just became visible', !steps[0].hidden);
		setVal(input('order_number'), 'A-100');
		next.click();
		ok('advances to step 2', steps[0].hidden && !steps[1].hidden && /Step 2 of 2/.test(q('.bfd-form__progress', form).textContent));
		ok('step 2 shows submit, hides Next, shows Back', !actions.hidden && next.hidden && !prev.hidden);

		prev.click();
		ok('Back returns to step 1 with values kept', !steps[0].hidden && input('name').value === 'Ann' && input('order_number').value === 'A-100');

		setVal(input('topic'), 'a'); // hides the order number: must not be submitted or validated
		next.click();
		ok('hidden required field no longer blocks', !steps[1].hidden);

		setVal(input('email'), 'ann@example.com');
		var sent = null, realFetch = window.fetch;
		window.fetch = function (url, opts) {
			if (opts && opts.body instanceof FormData) { sent = []; opts.body.forEach(function (v, k) { sent.push(k); }); }
			return realFetch.apply(this, arguments);
		};
		form.requestSubmit();
		wait(function () { return q('.bfd-form__msg', form).textContent.length > 0 && !/Sending/.test(q('.bfd-form__msg', form).textContent); }, function (e2) {
			var msg = q('.bfd-form__msg', form);
			ok('AJAX submit shows a server message', !e2 && /is-(success|error)/.test(msg.className), msg.className + ' | ' + msg.textContent);
			ok('hidden conditional field not sent', !!sent && sent.indexOf('f_order_number') === -1 && sent.indexOf('f_name') > -1 && sent.indexOf('f_email') > -1, (sent || []).join(','));
			ok('honeypot, nonce and timestamp are sent', !!sent && sent.indexOf('bfd_website') > -1 && sent.indexOf('_bfd_nonce') > -1 && sent.indexOf('bfd_ts') > -1);
			ok('submit button re-enabled', !q('button[type=submit]', form).disabled);
			finish();
		});
	});
});
