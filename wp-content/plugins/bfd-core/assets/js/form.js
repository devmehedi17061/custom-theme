(function () {
	'use strict';

	function setMsg(form, text, type) {
		var box = form.querySelector('.bfd-form__msg');
		if (!box) return;
		box.textContent = text || '';
		box.className = 'bfd-form__msg' + (type ? ' is-' + type : '');
	}

	function clearErrors(form) {
		form.querySelectorAll('[data-error-for]').forEach(function (n) { n.textContent = ''; });
		form.querySelectorAll('[aria-invalid]').forEach(function (n) { n.removeAttribute('aria-invalid'); });
	}

	/* ---------- Conditional fields ---------- */

	function valueOf(form, key) {
		var el = form.querySelector('[name="f_' + key + '"]');
		if (!el) return '';
		if (el.type === 'checkbox') return el.checked ? '1' : '';
		return (el.value || '').trim();
	}

	function conditionMet(c, val) {
		switch (c.op) {
			case 'equals': return val === String(c.v);
			case 'not_equals': return val !== String(c.v);
			case 'not_empty': return val !== '';
			case 'empty': return val === '';
		}
		return true;
	}

	// Hidden fields are disabled so they are neither validated nor submitted (the server ignores them too).
	function applyConditions(form) {
		form.querySelectorAll('[data-show-if]').forEach(function (wrap) {
			var c;
			try { c = JSON.parse(wrap.getAttribute('data-show-if')); } catch (e) { return; }
			var ref = form.querySelector('[data-field="' + c.f + '"]');
			var show = !(ref && ref.hidden) && conditionMet(c, valueOf(form, c.f));
			wrap.hidden = !show;
			wrap.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !show; });
		});
	}

	/* ---------- Multi-step ---------- */

	function setupSteps(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('.bfd-form__step'));
		if (steps.length < 2) return null;

		var nav = form.querySelector('.bfd-form__nav');
		var progress = form.querySelector('.bfd-form__progress');
		var actions = form.querySelector('.bfd-form__actions');
		var prev = form.querySelector('[data-prev]');
		var next = form.querySelector('[data-next]');
		var current = 0;

		function show(n) {
			current = Math.max(0, Math.min(steps.length - 1, n));
			steps.forEach(function (s, i) { s.hidden = i !== current; });
			var last = current === steps.length - 1;
			prev.hidden = current === 0;
			next.hidden = last;
			actions.hidden = !last;
			var title = steps[current].getAttribute('data-title');
			progress.textContent = 'Step ' + (current + 1) + ' of ' + steps.length + (title ? ': ' + title : '');
		}

		function currentValid() {
			var inputs = steps[current].querySelectorAll('input,select,textarea');
			for (var i = 0; i < inputs.length; i++) {
				if (!inputs[i].disabled && !inputs[i].checkValidity()) { inputs[i].reportValidity(); return false; }
			}
			return true;
		}

		nav.hidden = false;
		progress.hidden = false;
		prev.addEventListener('click', function () { show(current - 1); });
		next.addEventListener('click', function () { if (currentValid()) show(current + 1); });
		show(0);

		return {
			show: show,
			showFieldStep: function (key) {
				var slot = form.querySelector('[data-error-for="' + key + '"]');
				var step = slot && slot.closest('.bfd-form__step');
				if (step) show(steps.indexOf(step));
			}
		};
	}

	document.querySelectorAll('form.bfd-form[data-ajax]').forEach(function (form) {
		var steps = setupSteps(form);
		applyConditions(form);
		form.addEventListener('input', function () { applyConditions(form); });
		form.addEventListener('change', function () { applyConditions(form); });

		form.addEventListener('submit', function (e) {
			if (!window.fetch || !window.FormData) return; // fall back to normal submit
			e.preventDefault();
			if (!form.reportValidity()) return;

			var btn = form.querySelector('button[type="submit"]');
			clearErrors(form);
			setMsg(form, 'Sending…', 'info');
			if (btn) btn.disabled = true;

			fetch(form.getAttribute('data-ajax'), {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin'
			}).then(function (r) {
				return r.json().catch(function () { return { success: false, data: { message: 'Something went wrong. Please try again.' } }; });
			}).then(function (res) {
				var data = res.data || {};
				if (res.success) {
					form.reset();
					applyConditions(form);
					if (steps) steps.show(0);
					setMsg(form, data.message, 'success');
					return;
				}
				var errors = data.errors || {};
				var first = null;
				Object.keys(errors).forEach(function (key) {
					var slot = form.querySelector('[data-error-for="' + key + '"]');
					if (slot) {
						slot.textContent = errors[key];
						var input = slot.parentNode.querySelector('input,select,textarea');
						if (input) input.setAttribute('aria-invalid', 'true');
						if (!first) first = key;
					}
				});
				if (steps && first) steps.showFieldStep(first);
				setMsg(form, data.message || 'Please try again.', 'error');
			}).catch(function () {
				setMsg(form, 'Network error. Please try again.', 'error');
			}).then(function () {
				if (window.turnstile) { try { window.turnstile.reset(); } catch (e) { /* ignore */ } }
				if (btn) btn.disabled = false;
			});
		});
	});
})();
