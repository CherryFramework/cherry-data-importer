!function(n,r){"use strict";r={globalProgress:null,init:function(){n(function(){n("#cherry-export").on("click",function(r){var t=n(this),e=t.attr("href"),i=t.next(".cdi-loader");r.preventDefault(),i.removeClass("cdi-hidden"),window.location=e+"&nonce="+cherry_ajax})})}},r.init()}(jQuery,window.CherryDataExport);