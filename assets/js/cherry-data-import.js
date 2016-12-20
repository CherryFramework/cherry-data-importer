( function( $, CherryDataImport ) {

	"use strict";

	CherryDataImport = {

		selectors: {
			trigger: '#cherry-import-start',
			advancedTrigger: 'button[data-action="start-install"]',
			popupTrigger: 'button[data-action="confirm-install"]',
			removeContent: 'button[data-action="remove-content"]',
			upload: '#cherry-file-upload',
			globalProgress: '#cherry-import-progress'
		},

		globalProgress: null,

		init: function(){

			$( function() {

				CherryDataImport.globalProgress = $( CherryDataImport.selectors.globalProgress ).find( '.cdi-progress__bar' );

				$( 'body' )
				.on( 'click.cdiImport', CherryDataImport.selectors.trigger, CherryDataImport.goToImport )
				.on( 'click.cdiImport', CherryDataImport.selectors.advancedTrigger, CherryDataImport.advancedImport )
				.on( 'click.cdiImport', CherryDataImport.selectors.popupTrigger, CherryDataImport.confirmImport )
				.on( 'click.cdiImport', CherryDataImport.selectors.removeContent, CherryDataImport.removeContent )
				.on( 'focus.cdiImport', '.cdi-remove-form__input', CherryDataImport.clearRemoveNotices )
				.on( 'change.cdiImport', 'input[name="install-type"]', CherryDataImport.advancedNotice )
				.on( 'click.cdiImport', '.cdi-advanced-popup__close', CherryDataImport.closePopup );

				$( document ).on( 'tm-wizard-install-finished', CherryDataImport.wizardPopup );

				if ( window.CherryDataImportVars.autorun ) {
					CherryDataImport.startImport();
				}

				if ( undefined !== window.CherryRegenerateData ) {
					CherryDataImport.regenerateThumbnails();
				}

				CherryDataImport.fileUpload();

			} );

		},

		wizardPopup: function () {
			$( '.cdi-advanced-popup' ).removeClass( 'popup-hidden' ).trigger( 'cdi-popup-opened' );
		},

		removeContent: function() {

			var $this    = $( this ),
				$pass    = $this.prev(),
				$form    = $this.closest( '.cdi-remove-form' ),
				$notices = $( '.cdi-remove-form__notices', $form ),
				data     = {};

			if ( $this.hasClass( 'in-progress' ) ) {
				return;
			}

			data.action   = 'cherry-data-import-remove-content';
			data.nonce    = window.CherryDataImportVars.nonce;
			data.password = $pass.val();

			$this.addClass( 'in-progress' );

			$.ajax({
				url: window.ajaxurl,
				type: 'post',
				dataType: 'json',
				data: data,
				error: function() {
					$this.removeClass( 'in-progress' );
				}
			}).done( function( response ) {
				if ( true == response.success ) {
					$form.addClass( 'content-removed' ).html( response.data.message );
					CherryDataImport.startImport();
				} else {
					$notices.addClass( 'cdi-error' ).removeClass( 'cdi-hide' );
					$notices.html( response.data.message );
				}
				$this.removeClass( 'in-progress' );
			});

		},

		clearRemoveNotices: function() {

			var $this = $( this ),
				$form    = $this.closest( '.cdi-remove-form' ),
				$notices = $( '.cdi-remove-form__notices', $form );

			$notices.removeClass( 'cdi-error' ).addClass( 'cdi-hide' );

		},

		closePopup: function() {
			$( '.cdi-advanced-popup' ).addClass( 'popup-hidden' ).data( 'url', null );
			$( '.cdi-btn.in-progress' ).removeClass( 'in-progress' );
		},

		confirmImport: function() {
			var $this     = $( this ),
				$popup    = $this.closest( '.cdi-advanced-popup' ),
				$checkbox = $( '.cdi-advanced-popup__item input[type="radio"]:checked', $popup ),
				type      = 'append',
				url       = $popup.data( 'url' );

			$this.addClass( 'in-progress' );

			if ( undefined !== $checkbox.val() && '' !== $checkbox.val() ) {
				type = $checkbox.val();
			}

			url = url + '&type=' + type;

			window.location = url;
		},

		advancedImport: function() {

			var $this = $( this ),
				$item = $this.closest( '.advanced-item' ),
				$type = $( '.advanced-item__type-checkbox input[type="checkbox"]', $item ),
				url   = window.CherryDataImportVars.advURLMask,
				full  = $item.data( 'full' ),
				min   = $item.data( 'lite' );

			$this.addClass( 'in-progress' );

			if ( $type.is(':checked') ) {
				url = url.replace( '<-file->', min );
			} else {
				url = url.replace( '<-file->', full );
			}

			$( '.cdi-advanced-popup' ).removeClass( 'popup-hidden' ).data( 'url', url );

		},

		advancedNotice: function() {
			var $this   = $( this ),
				$popup  = $this.closest( '.cdi-advanced-popup__content' ),
				$notice = $( '.cdi-advanced-popup__warning', $popup );

			if ( $this.is( ':checked' ) && 'replace' === $this.val() ) {
				$notice.removeClass( 'cdi-hide' );
			} else if ( ! $notice.hasClass( 'cdi-hide' ) ) {
				$notice.addClass( 'cdi-hide' );
			}

		},

		regenerateThumbnails: function() {

			var data = {
				action: 'cherry-regenerate-thumbnails',
				offset: 0,
				step:   window.CherryRegenerateData.step,
				total:  window.CherryRegenerateData.totalSteps
			};

			CherryDataImport.ajaxRequest( data );
		},

		ajaxRequest: function( data ) {

			var complete;

			data.nonce = window.CherryDataImportVars.nonce;
			data.file  = window.CherryDataImportVars.file;

			$.ajax({
				url: window.ajaxurl,
				type: 'get',
				dataType: 'json',
				data: data,
				error: function() {

					if ( data.step ) {

						complete = Math.ceil( ( data.offset + data.step ) * 100 / ( data.total * data.step ) );

						CherryDataImport.globalProgress
							.css( 'width', complete + '%' )
							.find( '.cdi-progress__label' ).text( complete + '%' );

						data.offset = data.offset + data.step;

						CherryDataImport.ajaxRequest( data );
					} else {
						$( '#cherry-import-progress' ).replaceWith(
							'<div class="import-failed">' + window.CherryDataImportVars.error + '</div>'
						);
					}
				}
			}).done( function( response ) {
				if ( true === response.success && ! response.data.isLast ) {
					CherryDataImport.ajaxRequest( response.data );
				}

				if ( response.data && response.data.redirect ) {
					window.location = response.data.redirect;
				}

				if ( response.data && response.data.complete ) {

					CherryDataImport.globalProgress
						.css( 'width', response.data.complete + '%' )
						.find( '.cdi-progress__label' ).text( response.data.complete + '%' );



					CherryDataImport.globalProgress.siblings( '.cdi-progress__placeholder' ).remove();
				}

				if ( response.data && response.data.processed ) {
					$.each( response.data.processed, CherryDataImport.updateSummary );
				}

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

			return '&tab=' + window.CherryDataImportVars.tab + '&step=2&file=' + file;

		},

		goToImport: function() {

			var url = $('input[name="referrer"]').val();

			if ( ! $( this ).hasClass( 'disabled' ) ) {
				window.location = url + CherryDataImport.prepareImportArgs();
			}

		},

		fileUpload: function() {

			var $button      = $( CherryDataImport.selectors.upload ),
				$container   = $button.closest('.import-file'),
				$placeholder = $container.find('.import-file__placeholder'),
				$input       = $container.find('.import-file__input'),
				uploader     = wp.media.frames.file_frame = wp.media({
					title: window.CherryDataImportVars.uploadTitle,
					button: {
						text: window.CherryDataImportVars.uploadBtn
					},
					multiple: false
				}),
				openFrame = function () {
					uploader.open();
					return !1;
				},
				onFileSelect = function() {
					var attachment = uploader.state().get( 'selection' ).toJSON(),
						xmlData    = attachment[0],
						inputVal   = '';

					$placeholder.val( xmlData.url );
					CherryDataImport.getFilePath( xmlData.url, $input );
				};

			$button.on( 'click', openFrame );
			uploader.on('select', onFileSelect );

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