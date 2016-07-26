( function( $, CherryDataExport ) {

	"use strict";

	CherryDataExport = {

		globalProgress: null,

		init: function(){

			$( function() {

				$( '#cherry-export' ).on( 'click', function( event ) {

					var $this   = $( this ),
						href    = $this.attr( 'href' ),
						$loader = $this.next( '.cdi-loader' );

					event.preventDefault();

					$loader.removeClass( 'cdi-hidden' );
					window.location = href + '&nonce=' + cherry_ajax;

				});

			} );

		},

	}

	CherryDataExport.init();

}( jQuery, window.CherryDataExport ) );