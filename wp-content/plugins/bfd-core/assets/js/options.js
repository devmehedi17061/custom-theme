(function () {
	'use strict';

	document.querySelectorAll('.bfd-media-field').forEach(function (box) {
		var input = box.querySelector('input[type="hidden"]');
		var img = box.querySelector('.bfd-media-preview');
		var frame;

		box.querySelector('.bfd-media-pick').addEventListener('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					input.value = a.id;
					img.src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
					img.style.display = 'block';
				});
			}
			frame.open();
		});

		box.querySelector('.bfd-media-clear').addEventListener('click', function () {
			input.value = '';
			img.removeAttribute('src');
			img.style.display = 'none';
		});
	});
})();
