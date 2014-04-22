(function() {
	tinymce.PluginManager.add('polls', function(editor) {
		editor.addCommand('WP-Polls-Insert_Poll', function() {
			var poll_id = jQuery.trim(prompt(tinymce.translate('Enter Poll ID')));
			while(isNaN(poll_id)) {
				poll_id = jQuery.trim(prompt(tinymce.translate('Error: Poll ID must be numeric') + "\n\n" + tinymce.translate('Please enter Poll ID again')));
			}
			if (poll_id >= -1 && poll_id != null && poll_id != "") {
				editor.insertContent('[poll id="' + poll_id + '"]');
			}
		});
		editor.addButton('polls', {
			text: false,
			tooltip: tinymce.translate('Insert Poll'),
			icon: 'polls dashicons-before dashicons-chart-bar',
			onclick: function() {
				tinyMCE.activeEditor.execCommand('WP-Polls-Insert_Poll')
			}
		});
	});
})();