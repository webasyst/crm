import{af as es,d as ne,o as L,c as J,i as h,e as ht,n as M,r as Oe,b as se,f as Ct,h as Ht,g as N,m as ss,k as Ce,aM as ns,t as is,A as Qt,a as ut,O as rs,ac as as,ao as os,aN as ls,v as Ne,p as te,F as cs,l as us,w as ee,aO as hs,a3 as Ft,aP as fs,an as ds,ad as vs,G as ps}from"./main-07d84941.js";import{_ as ms,c as Ts,b as _s,d as gs,a as Es}from"./DealsItemAmount.vue_vue_type_style_index_0_lang-3bd2ae6b.js";import{W as ys}from"./WaSpinner-40f3d131.js";import{v as Is}from"./index-c6ad4004.js";import{v as Cs}from"./vTooltip-05cbcfb0.js";import{u as Ns}from"./dealStageChanger-e8e62609.js";import{_ as Ss}from"./SortableList.vue_vue_type_script_setup_true_lang-e995921a.js";import"./ChipsList-c29e2863.js";import"./UserPic-6b99ec27.js";import"./index-00e882cb.js";import"./dayjs-ec756e3b.js";import"./currency-format-3aa2cf22.js";import"./emit-60490a02.js";import"./alert-cd6f2520.js";import"./ButtonSubmit-7647b2fd.js";import"./FieldSelect-efa962b1.js";import"./useSortable-85e98846.js";var Pe={exports:{}};/*! Hammer.JS - v2.0.7 - 2016-04-22
 * http://hammerjs.github.io/
 *
 * Copyright (c) 2016 Jorik Tangelder;
 * Licensed under the MIT license */(function(Q){(function(a,H,ft,c){var B=["","webkit","Moz","MS","ms","o"],Nt=H.createElement("div"),St="function",R=Math.round,Y=Math.abs,it=Date.now;function rt(t,e,s){return setTimeout(X(t,s),e)}function w(t,e,s){return Array.isArray(t)?(C(t,s[e],s),!0):!1}function C(t,e,s){var n;if(t)if(t.forEach)t.forEach(e,s);else if(t.length!==c)for(n=0;n<t.length;)e.call(s,t[n],n,t),n++;else for(n in t)t.hasOwnProperty(n)&&e.call(s,t[n],n,t)}function at(t,e,s){var n="DEPRECATED METHOD: "+e+`
`+s+` AT 
`;return function(){var i=new Error("get-stack-trace"),r=i&&i.stack?i.stack.replace(/^[^\(]+?[\n$]/gm,"").replace(/^\s+at\s+/gm,"").replace(/^Object.<anonymous>\s*\(/gm,"{anonymous}()@"):"Unknown Stack Trace",o=a.console&&(a.console.warn||a.console.log);return o&&o.call(a.console,n,r),t.apply(this,arguments)}}var E;typeof Object.assign!="function"?E=function(e){if(e===c||e===null)throw new TypeError("Cannot convert undefined or null to object");for(var s=Object(e),n=1;n<arguments.length;n++){var i=arguments[n];if(i!==c&&i!==null)for(var r in i)i.hasOwnProperty(r)&&(s[r]=i[r])}return s}:E=Object.assign;var Ot=at(function(e,s,n){for(var i=Object.keys(s),r=0;r<i.length;)(!n||n&&e[i[r]]===c)&&(e[i[r]]=s[i[r]]),r++;return e},"extend","Use `assign`."),dt=at(function(e,s){return Ot(e,s,!0)},"merge","Use `assign`.");function m(t,e,s){var n=e.prototype,i;i=t.prototype=Object.create(n),i.constructor=t,i._super=n,s&&E(i,s)}function X(t,e){return function(){return t.apply(e,arguments)}}function tt(t,e){return typeof t==St?t.apply(e&&e[0]||c,e):t}function et(t,e){return t===c?e:t}function A(t,e,s){C(ot(e),function(n){t.addEventListener(n,s,!1)})}function S(t,e,s){C(ot(e),function(n){t.removeEventListener(n,s,!1)})}function vt(t,e){for(;t;){if(t==e)return!0;t=t.parentNode}return!1}function b(t,e){return t.indexOf(e)>-1}function ot(t){return t.trim().split(/\s+/g)}function W(t,e,s){if(t.indexOf&&!s)return t.indexOf(e);for(var n=0;n<t.length;){if(s&&t[n][s]==e||!s&&t[n]===e)return n;n++}return-1}function l(t){return Array.prototype.slice.call(t,0)}function T(t,e,s){for(var n=[],i=[],r=0;r<t.length;){var o=e?t[r][e]:t[r];W(i,o)<0&&n.push(t[r]),i[r]=o,r++}return s&&(e?n=n.sort(function(g,y){return g[e]>y[e]}):n=n.sort()),n}function f(t,e){for(var s,n,i=e[0].toUpperCase()+e.slice(1),r=0;r<B.length;){if(s=B[r],n=s?s+i:e,n in t)return n;r++}return c}var D=1;function d(){return D++}function v(t){var e=t.ownerDocument||t;return e.defaultView||e.parentWindow||a}var $=/mobile|tablet|ip(ad|hone|od)|android/i,lt="ontouchstart"in a,Pt=f(a,"PointerEvent")!==c,pt=lt&&$.test(navigator.userAgent),K="touch",Yt="pen",Z="mouse",Xt="kinect",Wt=25,_=1,V=2,u=4,I=8,wt=1,mt=2,Tt=4,_t=8,gt=16,U=mt|Tt,st=_t|gt,ie=U|st,re=["x","y"],At=["clientX","clientY"];function O(t,e){var s=this;this.manager=t,this.callback=e,this.element=t.element,this.target=t.options.inputTarget,this.domHandler=function(n){tt(t.options.enable,[t])&&s.handler(n)},this.init()}O.prototype={handler:function(){},init:function(){this.evEl&&A(this.element,this.evEl,this.domHandler),this.evTarget&&A(this.target,this.evTarget,this.domHandler),this.evWin&&A(v(this.element),this.evWin,this.domHandler)},destroy:function(){this.evEl&&S(this.element,this.evEl,this.domHandler),this.evTarget&&S(this.target,this.evTarget,this.domHandler),this.evWin&&S(v(this.element),this.evWin,this.domHandler)}};function we(t){var e,s=t.options.inputClass;return s?e=s:Pt?e=qt:pt?e=xt:lt?e=zt:e=Dt,new e(t,Ae)}function Ae(t,e,s){var n=s.pointers.length,i=s.changedPointers.length,r=e&_&&n-i===0,o=e&(u|I)&&n-i===0;s.isFirst=!!r,s.isFinal=!!o,r&&(t.session={}),s.eventType=e,be(t,s),t.emit("hammer.input",s),t.recognize(s),t.session.prevInput=s}function be(t,e){var s=t.session,n=e.pointers,i=n.length;s.firstInput||(s.firstInput=ae(e)),i>1&&!s.firstMultiple?s.firstMultiple=ae(e):i===1&&(s.firstMultiple=!1);var r=s.firstInput,o=s.firstMultiple,p=o?o.center:r.center,g=e.center=oe(n);e.timeStamp=it(),e.deltaTime=e.timeStamp-r.timeStamp,e.angle=Vt(p,g),e.distance=bt(p,g),De(s,e),e.offsetDirection=ce(e.deltaX,e.deltaY);var y=le(e.deltaTime,e.deltaX,e.deltaY);e.overallVelocityX=y.x,e.overallVelocityY=y.y,e.overallVelocity=Y(y.x)>Y(y.y)?y.x:y.y,e.scale=o?Le(o.pointers,n):1,e.rotation=o?Me(o.pointers,n):0,e.maxPointers=s.prevInput?e.pointers.length>s.prevInput.maxPointers?e.pointers.length:s.prevInput.maxPointers:e.pointers.length,xe(s,e);var F=t.element;vt(e.srcEvent.target,F)&&(F=e.srcEvent.target),e.target=F}function De(t,e){var s=e.center,n=t.offsetDelta||{},i=t.prevDelta||{},r=t.prevInput||{};(e.eventType===_||r.eventType===u)&&(i=t.prevDelta={x:r.deltaX||0,y:r.deltaY||0},n=t.offsetDelta={x:s.x,y:s.y}),e.deltaX=i.x+(s.x-n.x),e.deltaY=i.y+(s.y-n.y)}function xe(t,e){var s=t.lastInterval||e,n=e.timeStamp-s.timeStamp,i,r,o,p;if(e.eventType!=I&&(n>Wt||s.velocity===c)){var g=e.deltaX-s.deltaX,y=e.deltaY-s.deltaY,F=le(n,g,y);r=F.x,o=F.y,i=Y(F.x)>Y(F.y)?F.x:F.y,p=ce(g,y),t.lastInterval=e}else i=s.velocity,r=s.velocityX,o=s.velocityY,p=s.direction;e.velocity=i,e.velocityX=r,e.velocityY=o,e.direction=p}function ae(t){for(var e=[],s=0;s<t.pointers.length;)e[s]={clientX:R(t.pointers[s].clientX),clientY:R(t.pointers[s].clientY)},s++;return{timeStamp:it(),pointers:e,center:oe(e),deltaX:t.deltaX,deltaY:t.deltaY}}function oe(t){var e=t.length;if(e===1)return{x:R(t[0].clientX),y:R(t[0].clientY)};for(var s=0,n=0,i=0;i<e;)s+=t[i].clientX,n+=t[i].clientY,i++;return{x:R(s/e),y:R(n/e)}}function le(t,e,s){return{x:e/t||0,y:s/t||0}}function ce(t,e){return t===e?wt:Y(t)>=Y(e)?t<0?mt:Tt:e<0?_t:gt}function bt(t,e,s){s||(s=re);var n=e[s[0]]-t[s[0]],i=e[s[1]]-t[s[1]];return Math.sqrt(n*n+i*i)}function Vt(t,e,s){s||(s=re);var n=e[s[0]]-t[s[0]],i=e[s[1]]-t[s[1]];return Math.atan2(i,n)*180/Math.PI}function Me(t,e){return Vt(e[1],e[0],At)+Vt(t[1],t[0],At)}function Le(t,e){return bt(e[0],e[1],At)/bt(t[0],t[1],At)}var Re={mousedown:_,mousemove:V,mouseup:u},Ue="mousedown",ke="mousemove mouseup";function Dt(){this.evEl=Ue,this.evWin=ke,this.pressed=!1,O.apply(this,arguments)}m(Dt,O,{handler:function(e){var s=Re[e.type];s&_&&e.button===0&&(this.pressed=!0),s&V&&e.which!==1&&(s=u),this.pressed&&(s&u&&(this.pressed=!1),this.callback(this.manager,s,{pointers:[e],changedPointers:[e],pointerType:Z,srcEvent:e}))}});var Fe={pointerdown:_,pointermove:V,pointerup:u,pointercancel:I,pointerout:I},He={2:K,3:Yt,4:Z,5:Xt},ue="pointerdown",he="pointermove pointerup pointercancel";a.MSPointerEvent&&!a.PointerEvent&&(ue="MSPointerDown",he="MSPointerMove MSPointerUp MSPointerCancel");function qt(){this.evEl=ue,this.evWin=he,O.apply(this,arguments),this.store=this.manager.session.pointerEvents=[]}m(qt,O,{handler:function(e){var s=this.store,n=!1,i=e.type.toLowerCase().replace("ms",""),r=Fe[i],o=He[e.pointerType]||e.pointerType,p=o==K,g=W(s,e.pointerId,"pointerId");r&_&&(e.button===0||p)?g<0&&(s.push(e),g=s.length-1):r&(u|I)&&(n=!0),!(g<0)&&(s[g]=e,this.callback(this.manager,r,{pointers:s,changedPointers:[e],pointerType:o,srcEvent:e}),n&&s.splice(g,1))}});var Ye={touchstart:_,touchmove:V,touchend:u,touchcancel:I},Xe="touchstart",We="touchstart touchmove touchend touchcancel";function fe(){this.evTarget=Xe,this.evWin=We,this.started=!1,O.apply(this,arguments)}m(fe,O,{handler:function(e){var s=Ye[e.type];if(s===_&&(this.started=!0),!!this.started){var n=Ve.call(this,e,s);s&(u|I)&&n[0].length-n[1].length===0&&(this.started=!1),this.callback(this.manager,s,{pointers:n[0],changedPointers:n[1],pointerType:K,srcEvent:e})}}});function Ve(t,e){var s=l(t.touches),n=l(t.changedTouches);return e&(u|I)&&(s=T(s.concat(n),"identifier",!0)),[s,n]}var qe={touchstart:_,touchmove:V,touchend:u,touchcancel:I},ze="touchstart touchmove touchend touchcancel";function xt(){this.evTarget=ze,this.targetIds={},O.apply(this,arguments)}m(xt,O,{handler:function(e){var s=qe[e.type],n=Ge.call(this,e,s);n&&this.callback(this.manager,s,{pointers:n[0],changedPointers:n[1],pointerType:K,srcEvent:e})}});function Ge(t,e){var s=l(t.touches),n=this.targetIds;if(e&(_|V)&&s.length===1)return n[s[0].identifier]=!0,[s,s];var i,r,o=l(t.changedTouches),p=[],g=this.target;if(r=s.filter(function(y){return vt(y.target,g)}),e===_)for(i=0;i<r.length;)n[r[i].identifier]=!0,i++;for(i=0;i<o.length;)n[o[i].identifier]&&p.push(o[i]),e&(u|I)&&delete n[o[i].identifier],i++;if(p.length)return[T(r.concat(p),"identifier",!0),p]}var Be=2500,de=25;function zt(){O.apply(this,arguments);var t=X(this.handler,this);this.touch=new xt(this.manager,t),this.mouse=new Dt(this.manager,t),this.primaryTouch=null,this.lastTouches=[]}m(zt,O,{handler:function(e,s,n){var i=n.pointerType==K,r=n.pointerType==Z;if(!(r&&n.sourceCapabilities&&n.sourceCapabilities.firesTouchEvents)){if(i)$e.call(this,s,n);else if(r&&Ke.call(this,n))return;this.callback(e,s,n)}},destroy:function(){this.touch.destroy(),this.mouse.destroy()}});function $e(t,e){t&_?(this.primaryTouch=e.changedPointers[0].identifier,ve.call(this,e)):t&(u|I)&&ve.call(this,e)}function ve(t){var e=t.changedPointers[0];if(e.identifier===this.primaryTouch){var s={x:e.clientX,y:e.clientY};this.lastTouches.push(s);var n=this.lastTouches,i=function(){var r=n.indexOf(s);r>-1&&n.splice(r,1)};setTimeout(i,Be)}}function Ke(t){for(var e=t.srcEvent.clientX,s=t.srcEvent.clientY,n=0;n<this.lastTouches.length;n++){var i=this.lastTouches[n],r=Math.abs(e-i.x),o=Math.abs(s-i.y);if(r<=de&&o<=de)return!0}return!1}var pe=f(Nt.style,"touchAction"),me=pe!==c,Te="compute",_e="auto",Gt="manipulation",nt="none",Et="pan-x",yt="pan-y",Mt=je();function Bt(t,e){this.manager=t,this.set(e)}Bt.prototype={set:function(t){t==Te&&(t=this.compute()),me&&this.manager.element.style&&Mt[t]&&(this.manager.element.style[pe]=t),this.actions=t.toLowerCase().trim()},update:function(){this.set(this.manager.options.touchAction)},compute:function(){var t=[];return C(this.manager.recognizers,function(e){tt(e.options.enable,[e])&&(t=t.concat(e.getTouchAction()))}),Ze(t.join(" "))},preventDefaults:function(t){var e=t.srcEvent,s=t.offsetDirection;if(this.manager.session.prevented){e.preventDefault();return}var n=this.actions,i=b(n,nt)&&!Mt[nt],r=b(n,yt)&&!Mt[yt],o=b(n,Et)&&!Mt[Et];if(i){var p=t.pointers.length===1,g=t.distance<2,y=t.deltaTime<250;if(p&&g&&y)return}if(!(o&&r)&&(i||r&&s&U||o&&s&st))return this.preventSrc(e)},preventSrc:function(t){this.manager.session.prevented=!0,t.preventDefault()}};function Ze(t){if(b(t,nt))return nt;var e=b(t,Et),s=b(t,yt);return e&&s?nt:e||s?e?Et:yt:b(t,Gt)?Gt:_e}function je(){if(!me)return!1;var t={},e=a.CSS&&a.CSS.supports;return["auto","manipulation","pan-y","pan-x","pan-x pan-y","none"].forEach(function(s){t[s]=e?a.CSS.supports("touch-action",s):!0}),t}var Lt=1,P=2,ct=4,j=8,q=j,It=16,k=32;function z(t){this.options=E({},this.defaults,t||{}),this.id=d(),this.manager=null,this.options.enable=et(this.options.enable,!0),this.state=Lt,this.simultaneous={},this.requireFail=[]}z.prototype={defaults:{},set:function(t){return E(this.options,t),this.manager&&this.manager.touchAction.update(),this},recognizeWith:function(t){if(w(t,"recognizeWith",this))return this;var e=this.simultaneous;return t=Rt(t,this),e[t.id]||(e[t.id]=t,t.recognizeWith(this)),this},dropRecognizeWith:function(t){return w(t,"dropRecognizeWith",this)?this:(t=Rt(t,this),delete this.simultaneous[t.id],this)},requireFailure:function(t){if(w(t,"requireFailure",this))return this;var e=this.requireFail;return t=Rt(t,this),W(e,t)===-1&&(e.push(t),t.requireFailure(this)),this},dropRequireFailure:function(t){if(w(t,"dropRequireFailure",this))return this;t=Rt(t,this);var e=W(this.requireFail,t);return e>-1&&this.requireFail.splice(e,1),this},hasRequireFailures:function(){return this.requireFail.length>0},canRecognizeWith:function(t){return!!this.simultaneous[t.id]},emit:function(t){var e=this,s=this.state;function n(i){e.manager.emit(i,t)}s<j&&n(e.options.event+ge(s)),n(e.options.event),t.additionalEvent&&n(t.additionalEvent),s>=j&&n(e.options.event+ge(s))},tryEmit:function(t){if(this.canEmit())return this.emit(t);this.state=k},canEmit:function(){for(var t=0;t<this.requireFail.length;){if(!(this.requireFail[t].state&(k|Lt)))return!1;t++}return!0},recognize:function(t){var e=E({},t);if(!tt(this.options.enable,[this,e])){this.reset(),this.state=k;return}this.state&(q|It|k)&&(this.state=Lt),this.state=this.process(e),this.state&(P|ct|j|It)&&this.tryEmit(e)},process:function(t){},getTouchAction:function(){},reset:function(){}};function ge(t){return t&It?"cancel":t&j?"end":t&ct?"move":t&P?"start":""}function Ee(t){return t==gt?"down":t==_t?"up":t==mt?"left":t==Tt?"right":""}function Rt(t,e){var s=e.manager;return s?s.get(t):t}function x(){z.apply(this,arguments)}m(x,z,{defaults:{pointers:1},attrTest:function(t){var e=this.options.pointers;return e===0||t.pointers.length===e},process:function(t){var e=this.state,s=t.eventType,n=e&(P|ct),i=this.attrTest(t);return n&&(s&I||!i)?e|It:n||i?s&u?e|j:e&P?e|ct:P:k}});function Ut(){x.apply(this,arguments),this.pX=null,this.pY=null}m(Ut,x,{defaults:{event:"pan",threshold:10,pointers:1,direction:ie},getTouchAction:function(){var t=this.options.direction,e=[];return t&U&&e.push(yt),t&st&&e.push(Et),e},directionTest:function(t){var e=this.options,s=!0,n=t.distance,i=t.direction,r=t.deltaX,o=t.deltaY;return i&e.direction||(e.direction&U?(i=r===0?wt:r<0?mt:Tt,s=r!=this.pX,n=Math.abs(t.deltaX)):(i=o===0?wt:o<0?_t:gt,s=o!=this.pY,n=Math.abs(t.deltaY))),t.direction=i,s&&n>e.threshold&&i&e.direction},attrTest:function(t){return x.prototype.attrTest.call(this,t)&&(this.state&P||!(this.state&P)&&this.directionTest(t))},emit:function(t){this.pX=t.deltaX,this.pY=t.deltaY;var e=Ee(t.direction);e&&(t.additionalEvent=this.options.event+e),this._super.emit.call(this,t)}});function $t(){x.apply(this,arguments)}m($t,x,{defaults:{event:"pinch",threshold:0,pointers:2},getTouchAction:function(){return[nt]},attrTest:function(t){return this._super.attrTest.call(this,t)&&(Math.abs(t.scale-1)>this.options.threshold||this.state&P)},emit:function(t){if(t.scale!==1){var e=t.scale<1?"in":"out";t.additionalEvent=this.options.event+e}this._super.emit.call(this,t)}});function Kt(){z.apply(this,arguments),this._timer=null,this._input=null}m(Kt,z,{defaults:{event:"press",pointers:1,time:251,threshold:9},getTouchAction:function(){return[_e]},process:function(t){var e=this.options,s=t.pointers.length===e.pointers,n=t.distance<e.threshold,i=t.deltaTime>e.time;if(this._input=t,!n||!s||t.eventType&(u|I)&&!i)this.reset();else if(t.eventType&_)this.reset(),this._timer=rt(function(){this.state=q,this.tryEmit()},e.time,this);else if(t.eventType&u)return q;return k},reset:function(){clearTimeout(this._timer)},emit:function(t){this.state===q&&(t&&t.eventType&u?this.manager.emit(this.options.event+"up",t):(this._input.timeStamp=it(),this.manager.emit(this.options.event,this._input)))}});function Zt(){x.apply(this,arguments)}m(Zt,x,{defaults:{event:"rotate",threshold:0,pointers:2},getTouchAction:function(){return[nt]},attrTest:function(t){return this._super.attrTest.call(this,t)&&(Math.abs(t.rotation)>this.options.threshold||this.state&P)}});function jt(){x.apply(this,arguments)}m(jt,x,{defaults:{event:"swipe",threshold:10,velocity:.3,direction:U|st,pointers:1},getTouchAction:function(){return Ut.prototype.getTouchAction.call(this)},attrTest:function(t){var e=this.options.direction,s;return e&(U|st)?s=t.overallVelocity:e&U?s=t.overallVelocityX:e&st&&(s=t.overallVelocityY),this._super.attrTest.call(this,t)&&e&t.offsetDirection&&t.distance>this.options.threshold&&t.maxPointers==this.options.pointers&&Y(s)>this.options.velocity&&t.eventType&u},emit:function(t){var e=Ee(t.offsetDirection);e&&this.manager.emit(this.options.event+e,t),this.manager.emit(this.options.event,t)}});function kt(){z.apply(this,arguments),this.pTime=!1,this.pCenter=!1,this._timer=null,this._input=null,this.count=0}m(kt,z,{defaults:{event:"tap",pointers:1,taps:1,interval:300,time:250,threshold:9,posThreshold:10},getTouchAction:function(){return[Gt]},process:function(t){var e=this.options,s=t.pointers.length===e.pointers,n=t.distance<e.threshold,i=t.deltaTime<e.time;if(this.reset(),t.eventType&_&&this.count===0)return this.failTimeout();if(n&&i&&s){if(t.eventType!=u)return this.failTimeout();var r=this.pTime?t.timeStamp-this.pTime<e.interval:!0,o=!this.pCenter||bt(this.pCenter,t.center)<e.posThreshold;this.pTime=t.timeStamp,this.pCenter=t.center,!o||!r?this.count=1:this.count+=1,this._input=t;var p=this.count%e.taps;if(p===0)return this.hasRequireFailures()?(this._timer=rt(function(){this.state=q,this.tryEmit()},e.interval,this),P):q}return k},failTimeout:function(){return this._timer=rt(function(){this.state=k},this.options.interval,this),k},reset:function(){clearTimeout(this._timer)},emit:function(){this.state==q&&(this._input.tapCount=this.count,this.manager.emit(this.options.event,this._input))}});function G(t,e){return e=e||{},e.recognizers=et(e.recognizers,G.defaults.preset),new Jt(t,e)}G.VERSION="2.0.7",G.defaults={domEvents:!1,touchAction:Te,enable:!0,inputTarget:null,inputClass:null,preset:[[Zt,{enable:!1}],[$t,{enable:!1},["rotate"]],[jt,{direction:U}],[Ut,{direction:U},["swipe"]],[kt],[kt,{event:"doubletap",taps:2},["tap"]],[Kt]],cssProps:{userSelect:"none",touchSelect:"none",touchCallout:"none",contentZooming:"none",userDrag:"none",tapHighlightColor:"rgba(0,0,0,0)"}};var Je=1,ye=2;function Jt(t,e){this.options=E({},G.defaults,e||{}),this.options.inputTarget=this.options.inputTarget||t,this.handlers={},this.session={},this.recognizers=[],this.oldCssProps={},this.element=t,this.input=we(this),this.touchAction=new Bt(this,this.options.touchAction),Ie(this,!0),C(this.options.recognizers,function(s){var n=this.add(new s[0](s[1]));s[2]&&n.recognizeWith(s[2]),s[3]&&n.requireFailure(s[3])},this)}Jt.prototype={set:function(t){return E(this.options,t),t.touchAction&&this.touchAction.update(),t.inputTarget&&(this.input.destroy(),this.input.target=t.inputTarget,this.input.init()),this},stop:function(t){this.session.stopped=t?ye:Je},recognize:function(t){var e=this.session;if(!e.stopped){this.touchAction.preventDefaults(t);var s,n=this.recognizers,i=e.curRecognizer;(!i||i&&i.state&q)&&(i=e.curRecognizer=null);for(var r=0;r<n.length;)s=n[r],e.stopped!==ye&&(!i||s==i||s.canRecognizeWith(i))?s.recognize(t):s.reset(),!i&&s.state&(P|ct|j)&&(i=e.curRecognizer=s),r++}},get:function(t){if(t instanceof z)return t;for(var e=this.recognizers,s=0;s<e.length;s++)if(e[s].options.event==t)return e[s];return null},add:function(t){if(w(t,"add",this))return this;var e=this.get(t.options.event);return e&&this.remove(e),this.recognizers.push(t),t.manager=this,this.touchAction.update(),t},remove:function(t){if(w(t,"remove",this))return this;if(t=this.get(t),t){var e=this.recognizers,s=W(e,t);s!==-1&&(e.splice(s,1),this.touchAction.update())}return this},on:function(t,e){if(t!==c&&e!==c){var s=this.handlers;return C(ot(t),function(n){s[n]=s[n]||[],s[n].push(e)}),this}},off:function(t,e){if(t!==c){var s=this.handlers;return C(ot(t),function(n){e?s[n]&&s[n].splice(W(s[n],e),1):delete s[n]}),this}},emit:function(t,e){this.options.domEvents&&Qe(t,e);var s=this.handlers[t]&&this.handlers[t].slice();if(!(!s||!s.length)){e.type=t,e.preventDefault=function(){e.srcEvent.preventDefault()};for(var n=0;n<s.length;)s[n](e),n++}},destroy:function(){this.element&&Ie(this,!1),this.handlers={},this.session={},this.input.destroy(),this.element=null}};function Ie(t,e){var s=t.element;if(s.style){var n;C(t.options.cssProps,function(i,r){n=f(s.style,r),e?(t.oldCssProps[n]=s.style[n],s.style[n]=i):s.style[n]=t.oldCssProps[n]||""}),e||(t.oldCssProps={})}}function Qe(t,e){var s=H.createEvent("Event");s.initEvent(t,!0,!0),s.gesture=e,e.target.dispatchEvent(s)}E(G,{INPUT_START:_,INPUT_MOVE:V,INPUT_END:u,INPUT_CANCEL:I,STATE_POSSIBLE:Lt,STATE_BEGAN:P,STATE_CHANGED:ct,STATE_ENDED:j,STATE_RECOGNIZED:q,STATE_CANCELLED:It,STATE_FAILED:k,DIRECTION_NONE:wt,DIRECTION_LEFT:mt,DIRECTION_RIGHT:Tt,DIRECTION_UP:_t,DIRECTION_DOWN:gt,DIRECTION_HORIZONTAL:U,DIRECTION_VERTICAL:st,DIRECTION_ALL:ie,Manager:Jt,Input:O,TouchAction:Bt,TouchInput:xt,MouseInput:Dt,PointerEventInput:qt,TouchMouseInput:zt,SingleTouchInput:fe,Recognizer:z,AttrRecognizer:x,Tap:kt,Pan:Ut,Swipe:jt,Pinch:$t,Rotate:Zt,Press:Kt,on:A,off:S,each:C,merge:dt,extend:Ot,assign:E,inherit:m,bindFn:X,prefixed:f});var ts=typeof a<"u"?a:typeof self<"u"?self:{};ts.Hammer=G,typeof c=="function"&&c.amd?c(function(){return G}):Q.exports?Q.exports=G:a[ft]=G})(window,document,"Hammer")})(Pe);var Os=Pe.exports;const Ps=es(Os),ws={class:"list-item"},As={class:"list-item__top"},bs={class:"tw-flex tw-justify-between tw-flex-wrap tw-gap-2"},Ds=ne({__name:"DealsKanbanListItem",props:{deal:{},isSelected:{type:Boolean},isFetchingDrop:{type:Boolean},disableInteractions:{type:Boolean}},setup(Q){const a=Q;return(H,ft)=>{var c,B;return L(),J("div",{class:M(["list-item__wrapper",{"is-overlay":a.isFetchingDrop}])},[h("div",ws,[h("div",As,[ht(ms,{deal:a.deal,"use-link":!0,class:M(["tw-w-0 tw-flex-auto",{"tw-pointer-events-none":a.disableInteractions}])},null,8,["deal","class"]),h("div",{class:M(["list-item__checkbox",{"!tw-opacity-100":a.isSelected}])},[Oe(H.$slots,"checkbox")],2)]),a.deal.contact?(L(),se(Ts,{key:0,user:a.deal.contact},null,8,["user"])):Ct("",!0),h("div",bs,[ht(_s,{deal:a.deal},null,8,["deal"]),h("div",{class:M(["tw-grid tw-grid-flow-col tw-gap-2",{"tw-w-full":(B=(c=a.deal)==null?void 0:c.tags)==null?void 0:B.length}])},[ht(gs,{tags:a.deal.tags},null,8,["tags"]),ht(Es,{class:"!tw-self-center tw-ml-auto tw-justify-self-end",deal:a.deal},null,8,["deal"])],2)])]),a.isFetchingDrop?(L(),se(ys,{key:0,class:"tw-absolute tw-top-1/2 tw-left-1/2 -tw-translate-x-1/2 -tw-translate-y-1/2"})):Ct("",!0)],2)}}});const xs={class:"deals-kanban__list-header"},Ms={class:"deals-kanban__list-header__name"},Ls={class:"deals-kanban__list-header__count"},Rs={class:"deals-kanban__list-items hide-scrollbar"},Us={key:0,class:"tw-absolute tw-left-1/2 tw-top-1/2 -tw-translate-x-1/2 -tw-translate-y-1/2"},ks=h("div",{class:"spinner tw-p-5"},null,-1),Fs=[ks],Hs={key:1,class:"tw-w-full tw-text-center tw-py-4 tw-flex-none"},Ys=h("div",{class:"spinner tw-p-5"},null,-1),Xs=[Ys],Ws=ne({__name:"DealsKanbanList",props:{column:{}},setup(Q){const a=Q;return(H,ft)=>(L(),J("div",{class:M(["deals-kanban__list",{"is-overlay":!a.column.isLazyLoad&&a.column.isFetching}])},[h("div",xs,[Ht((L(),J("h3",Ms,[ss(Ce(a.column.stage.name),1)])),[[N(Cs),a.column.stage.name,void 0,{"top-start":!0,500:!0}]]),h("div",Ls,Ce(a.column.stage.deal_count),1)]),h("div",Rs,[Oe(H.$slots,"listItem",{deals:a.column.deals}),!a.column.isLazyLoad&&a.column.isFetching?(L(),J("div",Us,Fs)):Ct("",!0),a.column.hasMoreDeals?Ht((L(),J("div",Hs,Xs)),[[N(Is),([{isIntersecting:c}])=>{c&&a.column.fetchNextPage()}]]):Ct("",!0)])],2))}});const Vs={class:"deals-kanban__wrapper"},qs=h("i",{class:"fas fa-chevron-left"},null,-1),zs=[qs],Gs=h("i",{class:"fas fa-chevron-right"},null,-1),Bs=[Gs],$s=["onClick"],Ks=["checked"],Zs=h("span",{class:"!tw-h-[1rem] !tw-w-[1rem]"},[h("span",{class:"icon"},[h("i",{class:"fas fa-check"})])],-1),js={key:0,class:"tw-h-full tw-w-full tw-flex tw-items-center"},Js=h("div",{class:"spinner tw-p-10 tw-mx-auto"},null,-1),Qs=[Js],Se="deals-kanban__list",_n=ne({__name:"DealsKanban",setup(Q){const a=`.${Se}`,H=ns(),{init:ft,bulkSelectToggle:c,findColumnByStageId:B}=H,{columnsDeals:Nt,isCachedFunnels:St,bulkSelectIds:R,isFetching:Y}=is(H),it=Qt(()=>!!R.value.length),rt=Qt(()=>Nt.value.length>1),w=ut(null),C=ut(!1),{x:at,arrivedState:E}=rs(w,{behavior:"smooth",throttle:100});as(w,l=>{const T=l[0];C.value=T.target.scrollWidth>T.target.clientWidth});const Ot=Qt(()=>C.value&&!E.right);let dt;os(()=>{ft(),dt=new Ps(document.getElementById("deals-kanban"))});function m(l){var d;const f=Array.from(((d=w.value)==null?void 0:d.querySelectorAll(a))||[]).map(v=>v.offsetLeft),D=l==="next"?f.find(v=>v>at.value+1):f.reverse().find(v=>v<at.value);at.value=D??0}const X=ut(),tt=[],et=ut(null),A=ut(null),S=ut(new Map),vt=ls(l=>{m(l)},700),b=()=>{X.value&&[...X.value.children].forEach(l=>{l.classList.remove("!tw-bg-waHighlighted")})},ot={sort:!1,scroll:!1,filter:".disabled",fallbackOnBody:!0,revertOnSpill:!0,delay:150,delayOnTouchOnly:!0,dragClass:"!tw-opacity-90",group:{name:"kanban",pull:"clone",revertClone:!0},onStart(l){document.body.classList.add("tw-overflow-hidden"),l.clone.classList.add("tw-hidden");const T=l.from.closest(a);X.value&&[...X.value.children].forEach(f=>{f!==T&&tt.push(hs(f,"mouseleave",b))}),dt.on("panleft panright",f=>{const d=document.body.clientWidth/2,v=f.center.x,$=parseFloat(((d-v)/d).toFixed(2));$>.35&&vt("prev"),$<-.6&&vt("next")})},onChange(l){b(),l.to.closest(a).classList.add("!tw-bg-waHighlighted")},onAdd(l){const T={dealId:l.item.dataset.id,fromStageId:l.from.dataset.stageId,toStageId:l.to.dataset.stageId},f=Ft.object({dealId:Ft.coerce.number().min(1),fromStageId:Ft.string(),toStageId:Ft.string()}),{oldIndex:D,newIndex:d}=l,v=async()=>{ps(l.from,l.item,D),l.clone.remove(),T.dealId?W("warning"):(et.value=null,A.value=null,S.value.clear())},$=f.safeParse(T);if(!$.success){console.error($.error),v();return}(async()=>{const{dealId:lt,fromStageId:Pt,toStageId:pt}=$.data;if(A.value=lt,S.value.set(lt,"pending"),Pt===pt){v();return}const K=B(Number(Pt)),Yt=B(Number(pt));if(typeof D=="number"){et.value=lt;const Z=fs(K.deals[D]),{changeOpenStage:Xt,onSuccess:Wt,onError:_,onCancel:V}=Ns(Z);Wt(()=>{setTimeout(async()=>{const{deal:u}=ds();(u==null?void 0:u.id)===Z.value.id&&(u.stage_id=Z.value.stage_id),l.clone.remove(),typeof d=="number"&&Yt.deals.splice(d,0,Z.value),K.deals.splice(D,1),await vs(),W("success")},150)}),_(v),V(v),await Xt({stage_id:+pt}),et.value=null}})()},onEnd(){document.body.classList.remove("tw-overflow-hidden"),dt.off("panleft panright"),b(),tt.forEach(l=>l()),tt.length=0}};function W(l){A.value&&(S.value.set(A.value,l),setTimeout(()=>A.value=null,100)),setTimeout(()=>S.value.clear(),800)}return(l,T)=>(L(),J("div",Vs,[h("div",{id:"deals-kanban",ref_key:"scrollableContainerRef",ref:w,class:M(["deals-kanban tw-select-none",{"overflow-soft-hide-x":Ot.value,"tw-pointer-events-none":A.value||N(Y)}])},[h("div",{class:M(["tw-absolute tw-top-1 tw-left-0 tw-w-full tw-z-30",{"md:tw-hidden":!C.value,"mobile:tw-hidden":!rt.value}])},[Ht(h("div",{class:M([{"tw-opacity-30":N(E).left},"col-nav -tw-left-6 mobile:-tw-left-5"]),onClick:T[0]||(T[0]=te(f=>m("prev"),["prevent"]))},zs,2),[[Ne,!N(E).left]]),h("div",{class:M([{"tw-opacity-30":N(E).right},"col-nav -tw-right-5 mobile:-tw-right-4"]),onClick:T[1]||(T[1]=te(f=>m("next"),["prevent"]))},Bs,2)],2),h("div",{ref_key:"listsRef",ref:X,class:"deals-kanban__lists overflow-soft-hide-y"},[(L(!0),J(cs,null,us(N(Nt),f=>Ht((L(),se(Ws,{key:f.stage.id,column:f,class:M(Se)},{listItem:ee(({deals:D})=>[ht(Ss,{list:D,"min-length":-1,"ext-options":ot,"item-key":"id","data-stage-id":f.stage.id,class:"tw-w-full tw-flex-1"},{default:ee(({item:d})=>[ht(Ds,{deal:d,"is-selected":N(R).includes(d.id),"is-fetching-drop":et.value===d.id,"disable-interactions":it.value,class:M([rt.value?"handle":"disabled",{[`animation-bg-pulse ${S.value.get(d.id)}`]:S.value.has(d.id)?S.value.get(d.id)!=="pending":!1}]),onClick:v=>it.value&&N(c)(d.id,v.shiftKey)},{checkbox:ee(()=>[h("span",{class:"wa-checkbox",onClick:te(v=>N(c)(d.id,v.shiftKey),["stop"])},[h("input",{type:"checkbox",checked:N(R).includes(d.id)},null,8,Ks),Zs],8,$s)]),_:2},1032,["deal","is-selected","is-fetching-drop","disable-interactions","class","onClick"])]),_:2},1032,["list","data-stage-id"])]),_:2},1032,["column"])),[[Ne,N(St)]])),128)),N(St)?Ct("",!0):(L(),J("div",js,Qs))],512)],2)]))}});export{_n as default};
