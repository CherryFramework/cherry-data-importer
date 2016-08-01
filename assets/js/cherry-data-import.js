( function( $, CherryDataImport ) {

	"use strict";

	CherryDataImport = {

		selectors: {
			trigger: '#cherry-import-start',
			upload: '#cherry-file-upload',
			globalProgress: '#cherry-import-progress'
		},

		globalProgress: null,

		init: function(){

			$( function() {

				CherryDataImport.globalProgress = $( CherryDataImport.selectors.globalProgress ).find( '.cdi-progress__bar' )

				$( 'body' ).on( 'click', CherryDataImport.selectors.trigger, CherryDataImport.goToImport );

				if ( window.CherryDataImportVars.autorun ) {
					CherryDataImport.startImport();
				}

				CherryDataImport.fileUpload();

			} );

		},

		ajaxRequest: function( data ) {

			data.nonce = window.CherryDataImportVars.nonce;
			data.file  = window.CherryDataImportVars.file;

			$.ajax({
				url: window.ajaxurl,
				type: 'get',
				dataType: 'json',
				data: data,
				error: function() {
				}
			}).done( function( response ) {
				/*if ( true === response.success && ! response.data.import_end ) {
					CherryDataImport.ajaxRequest( response.data );
				}

				if ( response.data && response.data.redirect ) {
					window.location = response.data.redirect;
				}

				if ( response.data && response.data.complete ) {
					CherryDataImport.globalProgress
						.css( 'width', response.data.complete + '%' )
						.find( '.cdi-progress__label' ).text( response.data.complete + '%' );
				}

				if ( response.data && response.data.processed ) {
					$.each( response.data.processed, CherryDataImport.updateSummary );
				}*/

			});

		},

		updateSummary: function( index, value ) {

			var $row       = $( 'tr[data-item="' + index + '"]' ),
				total      = parseInt( $row.data( 'total' ) ),
				$done      = $( '.cdi-install-summary__done', $row ),
				$percent   = $( '.cdi-install-summary__percent', $row ),
				$progress  = $( '.cdi-progress__bar', $row ),
				percentVal = Math.round( ( parseInt( value ) / total ) * 100 );

			$done.html( value );
			$percent.html( percentVal );
			$progress.css( 'width', percentVal + '%' );

		},

		startImport: function() {

			var data    = {
					action: 'cherry-data-import-chunk',
					chunk:  1
				};

			CherryDataImport.ajaxRequest( data );

		},

		prepareImportArgs: function() {

			var file    = null,
				$upload = $( 'input[name="upload_file"]' ),
				$select = $( 'select[name="import_file"]' );

			if ( $upload.length && '' !== $upload.val() ) {
				file = $upload.val();
			}

			if ( $select.length && null == file ) {
				file = $( 'option:selected', $select ).val();
			}

			return '&step=2&file=' + file;

		},

		goToImport: function() {

			var url = $('input[name="referrer"]').val();
			window.location = url + CherryDataImport.prepareImportArgs();

		},

		fileUpload: function() {

			var $button      = $( CherryDataImport.selectors.upload ),
				$container   = $button.closest('.import-file'),
				$placeholder = $container.find('.import-file__placeholder'),
				$input       = $container.find('.import-file__input'),
				uploader     = wp.media.frames.file_frame = wp.media({
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

				$placeholder.val( xmlData.url );
				CherryDataImport.getFilePath( xmlData.url, $input );

			} );

		},

		getFilePath: function( fileUrl, $input ) {

			var $importBtn = $( CherryDataImport.selectors.trigger ),
				path       = '';

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
					return !1;
				}
			}).done( function( response ) {
				$importBtn.removeClass( 'disabled' );
				if ( true === response.success ) {
					$input.val( response.data.path );
				}
			});

		}

	}

	CherryDataImport.init();

}( jQuery, window.CherryDataImport ) );