(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.blobs = function (host) { blobField(host, [host.getAttribute('data-bg-color') || '#6a8dff', host.getAttribute('data-bg-color2') || '#c56cff'], 5, 40, 90); };
})();
