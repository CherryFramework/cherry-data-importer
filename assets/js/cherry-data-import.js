( function( $, CherryDataImport ) {

	"use strict";

	CherryDataImport = {

		selectors: {
			trigger: '#cherry-import-start'
		},

		init: function(){
			$( function() {
				$( 'body' ).on( 'click', CherryDataImport.selectors.trigger, CherryDataImport.startImport );
			} );
		},

		ajaxRequest: function( data ) {

			$.ajax({
				url: window.ajaxurl,
				type: 'get',
				dataType: 'json',
				data: data,
				error: function() {
				}
			}).done( function( response ) {
				if ( true === response.success && ! response.data.import_end ) {
					CherryDataImport.ajaxRequest( response.data );
				}

				if ( response.data.complete ) {
					$('progress').attr( 'value', response.data.complete );
				}

			});

		},

		startImport: function() {

			var $button = $(this),
				data    = {
					action: 'cherry-data-import-chunk',
					chunk:  1
				};

			$button.attr( 'disabled', 'disabled' );
			CherryDataImport.ajaxRequest( data );

		}

	}

	CherryDataImport.init();

}( jQuery, window.CherryDataImport ) );