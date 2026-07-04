(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.squash = function (el) { var i = num(el, 'intensity', 0.5), poke = reactScale(el, 'center', 'bottom'); bindTrigger(el, function () { poke(i * 0.4, -i * 0.6); }); };
})();
