( function() {
	tinymce.PluginManager.add( 'polls', function( editor ) {
		editor.addCommand( 'WP-Polls-Insert_Poll', function() {
			let poll_id = ( prompt( tinymce.translate( 'Enter Poll ID' ) ) || '' ).trim();
			while ( isNaN( poll_id ) ) {
				poll_id = ( prompt( tinymce.translate( 'Error: Poll ID must be numeric' ) + '\n\n' + tinymce.translate( 'Please enter Poll ID again' ) ) || '' ).trim();
			}
			if ( poll_id !== '' && Number( poll_id ) >= -1 ) {
				editor.insertContent( '[poll id="' + poll_id + '"]' );
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
