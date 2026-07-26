( function() {
	tinymce.PluginManager.add( 'polls', function( editor ) {
		editor.addCommand( 'WP-Polls-Insert_Poll', function() {
			let pollId = ( prompt( tinymce.translate( 'Enter Poll ID' ) ) || '' ).trim();
			while ( isNaN( pollId ) ) {
				pollId = ( prompt( tinymce.translate( 'Error: Poll ID must be numeric' ) + '\n\n' + tinymce.translate( 'Please enter Poll ID again' ) ) || '' ).trim();
			}
			if ( pollId !== '' && Number( pollId ) >= -1 ) {
				editor.insertContent( '[poll id="' + pollId + '"]' );
			}
		} );
		editor.addButton( 'polls', {
			text: false,
			tooltip: tinymce.translate( 'Insert Poll' ),
			icon: 'polls dashicons-before dashicons-chart-bar',
			onclick() {
				tinyMCE.activeEditor.execCommand( 'WP-Polls-Insert_Poll' );
			},
		} );
	} );
}() );
