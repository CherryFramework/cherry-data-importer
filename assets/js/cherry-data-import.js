( function( $, CherryDataImport ) {

	"use strict";

	CherryDataImport = {

		selectors: {
			trigger: '#cherry-import-start',
			upload: '#cherry-file-upload'
		},

		init: function(){

			$( function() {

				$( 'body' ).on( 'click', CherryDataImport.selectors.trigger, CherryDataImport.goToImport );

				if ( window.CherryDataImportVars.autorun ) {
					CherryDataImport.startImport();
				}

				CherryDataImport.fileUpload();

			} );

		},

		ajaxRequest: function( data ) {

			data.nonce = window.CherryDataImportVars.nonce;

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

		},

		prepareImportArgs: function() {

			var file = null;

			if ( $( 'select[name="import_file"]' ).length ) {
				file = $( 'select[name="import_file"] option:selected' ).val();
			}

			return '&step=2&file=' + file;

		},

		goToImport: function() {

			var url = $('input[name="referrer"]').val();
			window.location = url + CherryDataImport.prepareImportArgs();

		},

		fileUpload: function() {

			var $button    = $( CherryDataImport.selectors.upload ),
				$container = $button.closest('.import-file'),
				$input     = $container.find('.import-file__input'),
				uploader   = wp.media.frames.file_frame = wp.media({
					title: CherryDataImportVars.uploadTitle,
					button: {
						text: CherryDataImportVars.uploadBtn
					},
					multiple: false
				});

			$button.on( 'click', function() {
				uploader.open();
				return !1;
			} );

			uploader.on('select', function() {
				var attachment = uploader.state().get( 'selection' ).toJSON(),
					xmlData    = attachment[0],
					inputVal   = '';

				$input.val( xmlData.url );
				CherryDataImport.getFilePath( xmlData.url );

			} );

		},

		getFilePath: function( fileUrl ) {

			var $importBtn = $( CherryDataImport.selectors.trigger );

			$importBtn.addClass( 'disabled' );

			$.ajax({
				url: window.ajaxurl,
				type: 'get',
				dataType: 'json',
				data: {
					action: 'cherry-data-import-get-file-path',
					file: fileUrl,
					nonce: window.CherryDataImportVars.nonce
				},
				error: function() {
					$importBtn.removeClass( 'disabled' );
				}
			}).done( function( response ) {

				$importBtn.removeClass( 'disabled' );

			});

		}

	}

	CherryDataImport.init();

}( jQuery, window.CherryDataImport ) );