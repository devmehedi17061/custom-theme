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

	document.querySelectorAll('form.bfd-form[data-ajax]').forEach(function (form) {
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
					setMsg(form, data.message, 'success');
					return;
				}
				var errors = data.errors || {};
				Object.keys(errors).forEach(function (key) {
					var slot = form.querySelector('[data-error-for="' + key + '"]');
					if (slot) {
						slot.textContent = errors[key];
						var input = slot.parentNode.querySelector('input,select,textarea');
						if (input) input.setAttribute('aria-invalid', 'true');
					}
				});
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
