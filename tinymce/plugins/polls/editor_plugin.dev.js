(function() {
	tinymce.PluginManager.requireLangPack('polls');
	tinymce.create('tinymce.plugins.PollsPlugin', {
		init : function(ed, url) {
			ed.addCommand('mcePollInsert', function() {
				ed.execCommand('mceInsertContent', 0, insertPoll('visual', ''));
			});
			ed.addButton('polls', {
				title : 'polls.insert_poll',
				cmd : 'mcePollInsert',
				image : url + '/img/poll.gif'
			});
			ed.onNodeChange.add(function(ed, cm, n) {
				cm.setActive('polls', n.nodeName == 'IMG');
			});
		},

		createControl : function(n, cm) {
			return null;
		},
		getInfo : function() {
			return {
				longname : 'WP-Polls',
				author : 'Lester Chan',
				authorurl : 'http://lesterchan.net',
				infourl : 'http://lesterchan.net/portfolio/programming/php/',
				version : '2.62'
			};
		}
	});
	tinymce.PluginManager.add('polls', tinymce.plugins.PollsPlugin);
})();