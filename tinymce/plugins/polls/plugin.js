(function() {
	tinymce.PluginManager.add('polls', function(editor, url) {
		editor.addCommand('WP-Polls-Insert_Poll', function() {
			var poll_id = jQuery.trim(prompt(pollsEdL10n.enter_poll_id));
			while(isNaN(poll_id)) {
				poll_id = jQuery.trim(prompt(pollsEdL10n.error_poll_id_numeric + "\n\n" + pollsEdL10n.enter_poll_id_again));
			}
			if (poll_id >= -1 && poll_id != null && poll_id != "") {
				editor.insertContent('[poll="' + poll_id + '"]');
			}
		});
		editor.addButton('polls', {
			text: false,
			tooltip: pollsEdL10n.insert_poll,
			icon: 'polls dashicons-before dashicons-chart-bar',
			onclick: function() {
				tinyMCE.activeEditor.execCommand( 'WP-Polls-Insert_Poll' )
			}
		});
	});
})();