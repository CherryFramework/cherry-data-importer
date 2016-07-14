!function(t,e){"use strict";e={selectors:{trigger:"#cherry-import-start",upload:"#cherry-file-upload",globalProgress:"#cherry-import-progress"},globalProgress:null,init:function(){t(function(){e.globalProgress=t(e.selectors.globalProgress).find(".cdi-progress__bar"),t("body").on("click",e.selectors.trigger,e.goToImport),window.CherryDataImportVars.autorun&&e.startImport(),e.fileUpload()})},ajaxRequest:function(r){r.nonce=window.CherryDataImportVars.nonce,r.file=window.CherryDataImportVars.file,t.ajax({url:window.ajaxurl,type:"get",dataType:"json",data:r,error:function(){}}).done(function(r){!0!==r.success||r.data.import_end||e.ajaxRequest(r.data),r.data.complete&&e.globalProgress.css("width",r.data.complete+"%").find(".cdi-progress__label").text(r.data.complete+"%"),r.data.processed&&t.each(r.data.processed,e.updateSummary)})},updateSummary:function(e,r){var a=t('tr[data-item="'+e+'"]'),o=parseInt(a.data("total")),n=t(".cdi-install-summary__done",a),l=t(".cdi-install-summary__percent",a),i=t(".cdi-progress__bar",a),s=Math.round(parseInt(r)/o*100);n.html(r),l.html(s),i.css("width",s+"%")},startImport:function(){var t={action:"cherry-data-import-chunk",chunk:1};e.ajaxRequest(t)},prepareImportArgs:function(){var e=null,r=t('input[name="upload_file"]'),a=t('select[name="import_file"]');return r.length&&""!==r.val()&&(e=r.val()),a.length&&null==e&&(e=t("option:selected",a).val()),"&step=2&file="+e},goToImport:function(){var r=t('input[name="referrer"]').val();window.location=r+e.prepareImportArgs()},fileUpload:function(){var r=t(e.selectors.upload),a=r.closest(".import-file"),o=a.find(".import-file__placeholder"),n=a.find(".import-file__input"),l=wp.media.frames.file_frame=wp.media({title:CherryDataImportVars.uploadTitle,button:{text:CherryDataImportVars.uploadBtn},multiple:!1});r.on("click",function(){return l.open(),!1}),l.on("select",function(){var t=l.state().get("selection").toJSON(),r=t[0];o.val(r.url),e.getFilePath(r.url,n)})},getFilePath:function(r,a){var o=t(e.selectors.trigger);o.addClass("disabled"),t.ajax({url:window.ajaxurl,type:"get",dataType:"json",data:{action:"cherry-data-import-get-file-path",file:r,nonce:window.CherryDataImportVars.nonce},error:function(){return o.removeClass("disabled"),!1}}).done(function(t){o.removeClass("disabled"),!0===t.success&&a.val(t.data.path)})}},e.init()}(jQuery,window.CherryDataImport);