( function( $, CherryDataExport ) {
	"use strict";
	CherryDataExport = {
		globalProgress: null,
		init: function(){
			$( '#cherry-export' ).on( 'click', function( event ) {
				var $this   = $( this ),
					href    = $this.attr( 'href' );
				event.preventDefault();
				window.location = href + '&nonce=' + window.CherryDataExportVars.nonce;
			});
		},
	}
	CherryDataExport.init();

}( jQuery, window.CherryDataExport ) );