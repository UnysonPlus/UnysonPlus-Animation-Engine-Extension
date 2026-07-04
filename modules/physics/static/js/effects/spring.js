(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.spring = function (el) { follow(el, num(el, 'strength', 0.25), num(el, 'stiffness', 0.12)); };
})();
