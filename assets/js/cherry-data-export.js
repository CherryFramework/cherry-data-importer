( function( $, CherryDataExport ) {

	"use strict";

	CherryDataExport = {

		globalProgress: null,

		init: function(){

			$( function() {

				$( '#cherry-export' ).on( 'click', function( event ) {

					event.preventDefault();

					$.ajax({
						url: window.ajaxurl,
						type: 'get',
						dataType: 'json',
						data: {
							action: 'cherry-data-export',
							nonce: cherry_ajax,
						},
						error: function() {
							return !1;
						}
					}).done( function( response ) {
						if ( true === response.success ) {

						}
					});

				});

			} );

		},

	}

	CherryDataExport.init();

}( jQuery, window.CherryDataExport ) );