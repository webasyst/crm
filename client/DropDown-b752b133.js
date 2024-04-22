import{d as _t,o as et,c as at,r as U,n as Tt,_ as Dt,A as k,a as D,be as It,R as lt,bf as zt,bg as I,g as N,bh as Xt,N as Yt,bi as wt,at as jt,b as qt,w as Ut,i as vt,h as Gt,v as Kt,a3 as yt,f as Jt}from"./main-e63d6b61.js";import{O as Qt}from"./index-48d220da.js";const We=_t({__name:"MenuListItem",props:{selected:{type:Boolean}},setup(t){return(e,n)=>(et(),at("li",{class:Tt({selected:e.selected})},[U(e.$slots,"default")],2))}});const Zt={},te={class:"menu"};function ee(t,e){return et(),at("ul",te,[U(t.$slots,"default",{},void 0,!0)])}const $e=Dt(Zt,[["render",ee],["__scopeId","data-v-361bd61c"]]),X=Math.min,C=Math.max,nt=Math.round,tt=Math.floor,M=t=>({x:t,y:t}),ne={left:"right",right:"left",bottom:"top",top:"bottom"},oe={start:"end",end:"start"};function xt(t,e,n){return C(t,X(e,n))}function K(t,e){return typeof t=="function"?t(e):t}function F(t){return t.split("-")[0]}function J(t){return t.split("-")[1]}function Lt(t){return t==="x"?"y":"x"}function St(t){return t==="y"?"height":"width"}function Q(t){return["top","bottom"].includes(F(t))?"y":"x"}function Bt(t){return Lt(Q(t))}function ie(t,e,n){n===void 0&&(n=!1);const o=J(t),i=Bt(t),r=St(i);let s=i==="x"?o===(n?"end":"start")?"right":"left":o==="start"?"bottom":"top";return e.reference[r]>e.floating[r]&&(s=ot(s)),[s,ot(s)]}function se(t){const e=ot(t);return[ct(t),e,ct(e)]}function ct(t){return t.replace(/start|end/g,e=>oe[e])}function re(t,e,n){const o=["left","right"],i=["right","left"],r=["top","bottom"],s=["bottom","top"];switch(t){case"top":case"bottom":return n?e?i:o:e?o:i;case"left":case"right":return e?r:s;default:return[]}}function le(t,e,n,o){const i=J(t);let r=re(F(t),n==="start",o);return i&&(r=r.map(s=>s+"-"+i),e&&(r=r.concat(r.map(ct)))),r}function ot(t){return t.replace(/left|right|bottom|top/g,e=>ne[e])}function ce(t){return{top:0,right:0,bottom:0,left:0,...t}}function ae(t){return typeof t!="number"?ce(t):{top:t,right:t,bottom:t,left:t}}function it(t){return{...t,top:t.y,left:t.x,right:t.x+t.width,bottom:t.y+t.height}}function bt(t,e,n){let{reference:o,floating:i}=t;const r=Q(e),s=Bt(e),l=St(s),c=F(e),a=r==="y",u=o.x+o.width/2-i.width/2,d=o.y+o.height/2-i.height/2,p=o[l]/2-i[l]/2;let f;switch(c){case"top":f={x:u,y:o.y-i.height};break;case"bottom":f={x:u,y:o.y+o.height};break;case"right":f={x:o.x+o.width,y:d};break;case"left":f={x:o.x-i.width,y:d};break;default:f={x:o.x,y:o.y}}switch(J(e)){case"start":f[s]-=p*(n&&a?-1:1);break;case"end":f[s]+=p*(n&&a?-1:1);break}return f}const fe=async(t,e,n)=>{const{placement:o="bottom",strategy:i="absolute",middleware:r=[],platform:s}=n,l=r.filter(Boolean),c=await(s.isRTL==null?void 0:s.isRTL(e));let a=await s.getElementRects({reference:t,floating:e,strategy:i}),{x:u,y:d}=bt(a,o,c),p=o,f={},m=0;for(let h=0;h<l.length;h++){const{name:v,fn:g}=l[h],{x:w,y:x,data:R,reset:b}=await g({x:u,y:d,initialPlacement:o,placement:p,strategy:i,middlewareData:f,rects:a,platform:s,elements:{reference:t,floating:e}});if(u=w??u,d=x??d,f={...f,[v]:{...f[v],...R}},b&&m<=50){m++,typeof b=="object"&&(b.placement&&(p=b.placement),b.rects&&(a=b.rects===!0?await s.getElementRects({reference:t,floating:e,strategy:i}):b.rects),{x:u,y:d}=bt(a,p,c)),h=-1;continue}}return{x:u,y:d,placement:p,strategy:i,middlewareData:f}};async function ft(t,e){var n;e===void 0&&(e={});const{x:o,y:i,platform:r,rects:s,elements:l,strategy:c}=t,{boundary:a="clippingAncestors",rootBoundary:u="viewport",elementContext:d="floating",altBoundary:p=!1,padding:f=0}=K(e,t),m=ae(f),v=l[p?d==="floating"?"reference":"floating":d],g=it(await r.getClippingRect({element:(n=await(r.isElement==null?void 0:r.isElement(v)))==null||n?v:v.contextElement||await(r.getDocumentElement==null?void 0:r.getDocumentElement(l.floating)),boundary:a,rootBoundary:u,strategy:c})),w=d==="floating"?{...s.floating,x:o,y:i}:s.reference,x=await(r.getOffsetParent==null?void 0:r.getOffsetParent(l.floating)),R=await(r.isElement==null?void 0:r.isElement(x))?await(r.getScale==null?void 0:r.getScale(x))||{x:1,y:1}:{x:1,y:1},b=it(r.convertOffsetParentRelativeRectToViewportRelativeRect?await r.convertOffsetParentRelativeRectToViewportRelativeRect({rect:w,offsetParent:x,strategy:c}):w);return{top:(g.top-b.top+m.top)/R.y,bottom:(b.bottom-g.bottom+m.bottom)/R.y,left:(g.left-b.left+m.left)/R.x,right:(b.right-g.right+m.right)/R.x}}const ue=function(t){return t===void 0&&(t={}),{name:"flip",options:t,async fn(e){var n,o;const{placement:i,middlewareData:r,rects:s,initialPlacement:l,platform:c,elements:a}=e,{mainAxis:u=!0,crossAxis:d=!0,fallbackPlacements:p,fallbackStrategy:f="bestFit",fallbackAxisSideDirection:m="none",flipAlignment:h=!0,...v}=K(t,e);if((n=r.arrow)!=null&&n.alignmentOffset)return{};const g=F(i),w=F(l)===l,x=await(c.isRTL==null?void 0:c.isRTL(a.floating)),R=p||(w||!h?[ot(l)]:se(l));!p&&m!=="none"&&R.push(...le(l,h,m,x));const b=[l,...R],O=await ft(e,v),y=[];let _=((o=r.flip)==null?void 0:o.overflows)||[];if(u&&y.push(O[g]),d){const H=ie(i,s,x);y.push(O[H[0]],O[H[1]])}if(_=[..._,{placement:i,overflows:y}],!y.every(H=>H<=0)){var B,pt;const H=(((B=r.flip)==null?void 0:B.index)||0)+1,ht=b[H];if(ht)return{data:{index:H,overflows:_},reset:{placement:ht}};let j=(pt=_.filter(W=>W.overflows[0]<=0).sort((W,$)=>W.overflows[1]-$.overflows[1])[0])==null?void 0:pt.placement;if(!j)switch(f){case"bestFit":{var gt;const W=(gt=_.map($=>[$.placement,$.overflows.filter(q=>q>0).reduce((q,$t)=>q+$t,0)]).sort(($,q)=>$[1]-q[1])[0])==null?void 0:gt[0];W&&(j=W);break}case"initialPlacement":j=l;break}if(i!==j)return{reset:{placement:j}}}return{}}}};async function de(t,e){const{placement:n,platform:o,elements:i}=t,r=await(o.isRTL==null?void 0:o.isRTL(i.floating)),s=F(n),l=J(n),c=Q(n)==="y",a=["left","top"].includes(s)?-1:1,u=r&&c?-1:1,d=K(e,t);let{mainAxis:p,crossAxis:f,alignmentAxis:m}=typeof d=="number"?{mainAxis:d,crossAxis:0,alignmentAxis:null}:{mainAxis:0,crossAxis:0,alignmentAxis:null,...d};return l&&typeof m=="number"&&(f=l==="end"?m*-1:m),c?{x:f*u,y:p*a}:{x:p*a,y:f*u}}const me=function(t){return t===void 0&&(t=0),{name:"offset",options:t,async fn(e){const{x:n,y:o}=e,i=await de(e,t);return{x:n+i.x,y:o+i.y,data:i}}}},pe=function(t){return t===void 0&&(t={}),{name:"shift",options:t,async fn(e){const{x:n,y:o,placement:i}=e,{mainAxis:r=!0,crossAxis:s=!1,limiter:l={fn:v=>{let{x:g,y:w}=v;return{x:g,y:w}}},...c}=K(t,e),a={x:n,y:o},u=await ft(e,c),d=Q(F(i)),p=Lt(d);let f=a[p],m=a[d];if(r){const v=p==="y"?"top":"left",g=p==="y"?"bottom":"right",w=f+u[v],x=f-u[g];f=xt(w,f,x)}if(s){const v=d==="y"?"top":"left",g=d==="y"?"bottom":"right",w=m+u[v],x=m-u[g];m=xt(w,m,x)}const h=l.fn({...e,[p]:f,[d]:m});return{...h,data:{x:h.x-n,y:h.y-o}}}}},ge=function(t){return t===void 0&&(t={}),{name:"size",options:t,async fn(e){const{placement:n,rects:o,platform:i,elements:r}=e,{apply:s=()=>{},...l}=K(t,e),c=await ft(e,l),a=F(n),u=J(n),d=Q(n)==="y",{width:p,height:f}=o.floating;let m,h;a==="top"||a==="bottom"?(m=a,h=u===(await(i.isRTL==null?void 0:i.isRTL(r.floating))?"start":"end")?"left":"right"):(h=a,m=u==="end"?"top":"bottom");const v=f-c[m],g=p-c[h],w=!e.middlewareData.shift;let x=v,R=g;if(d){const O=p-c.left-c.right;R=u||w?X(g,O):O}else{const O=f-c.top-c.bottom;x=u||w?X(v,O):O}if(w&&!u){const O=C(c.left,0),y=C(c.right,0),_=C(c.top,0),B=C(c.bottom,0);d?R=p-2*(O!==0||y!==0?O+y:C(c.left,c.right)):x=f-2*(_!==0||B!==0?_+B:C(c.top,c.bottom))}await s({...e,availableWidth:R,availableHeight:x});const b=await i.getDimensions(r.floating);return p!==b.width||f!==b.height?{reset:{rects:!0}}:{}}}};function P(t){return kt(t)?(t.nodeName||"").toLowerCase():"#document"}function A(t){var e;return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window}function S(t){var e;return(e=(kt(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement}function kt(t){return t instanceof Node||t instanceof A(t).Node}function L(t){return t instanceof Element||t instanceof A(t).Element}function T(t){return t instanceof HTMLElement||t instanceof A(t).HTMLElement}function Rt(t){return typeof ShadowRoot>"u"?!1:t instanceof ShadowRoot||t instanceof A(t).ShadowRoot}function Z(t){const{overflow:e,overflowX:n,overflowY:o,display:i}=E(t);return/auto|scroll|overlay|hidden|clip/.test(e+o+n)&&!["inline","contents"].includes(i)}function he(t){return["table","td","th"].includes(P(t))}function ut(t){const e=dt(),n=E(t);return n.transform!=="none"||n.perspective!=="none"||(n.containerType?n.containerType!=="normal":!1)||!e&&(n.backdropFilter?n.backdropFilter!=="none":!1)||!e&&(n.filter?n.filter!=="none":!1)||["transform","perspective","filter"].some(o=>(n.willChange||"").includes(o))||["paint","layout","strict","content"].some(o=>(n.contain||"").includes(o))}function we(t){let e=Y(t);for(;T(e)&&!st(e);){if(ut(e))return e;e=Y(e)}return null}function dt(){return typeof CSS>"u"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")}function st(t){return["html","body","#document"].includes(P(t))}function E(t){return A(t).getComputedStyle(t)}function rt(t){return L(t)?{scrollLeft:t.scrollLeft,scrollTop:t.scrollTop}:{scrollLeft:t.pageXOffset,scrollTop:t.pageYOffset}}function Y(t){if(P(t)==="html")return t;const e=t.assignedSlot||t.parentNode||Rt(t)&&t.host||S(t);return Rt(e)?e.host:e}function Mt(t){const e=Y(t);return st(e)?t.ownerDocument?t.ownerDocument.body:t.body:T(e)&&Z(e)?e:Mt(e)}function G(t,e,n){var o;e===void 0&&(e=[]),n===void 0&&(n=!0);const i=Mt(t),r=i===((o=t.ownerDocument)==null?void 0:o.body),s=A(i);return r?e.concat(s,s.visualViewport||[],Z(i)?i:[],s.frameElement&&n?G(s.frameElement):[]):e.concat(i,G(i,[],n))}function Ft(t){const e=E(t);let n=parseFloat(e.width)||0,o=parseFloat(e.height)||0;const i=T(t),r=i?t.offsetWidth:n,s=i?t.offsetHeight:o,l=nt(n)!==r||nt(o)!==s;return l&&(n=r,o=s),{width:n,height:o,$:l}}function mt(t){return L(t)?t:t.contextElement}function z(t){const e=mt(t);if(!T(e))return M(1);const n=e.getBoundingClientRect(),{width:o,height:i,$:r}=Ft(e);let s=(r?nt(n.width):n.width)/o,l=(r?nt(n.height):n.height)/i;return(!s||!Number.isFinite(s))&&(s=1),(!l||!Number.isFinite(l))&&(l=1),{x:s,y:l}}const ve=M(0);function Pt(t){const e=A(t);return!dt()||!e.visualViewport?ve:{x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop}}function ye(t,e,n){return e===void 0&&(e=!1),!n||e&&n!==A(t)?!1:e}function V(t,e,n,o){e===void 0&&(e=!1),n===void 0&&(n=!1);const i=t.getBoundingClientRect(),r=mt(t);let s=M(1);e&&(o?L(o)&&(s=z(o)):s=z(t));const l=ye(r,n,o)?Pt(r):M(0);let c=(i.left+l.x)/s.x,a=(i.top+l.y)/s.y,u=i.width/s.x,d=i.height/s.y;if(r){const p=A(r),f=o&&L(o)?A(o):o;let m=p.frameElement;for(;m&&o&&f!==p;){const h=z(m),v=m.getBoundingClientRect(),g=E(m),w=v.left+(m.clientLeft+parseFloat(g.paddingLeft))*h.x,x=v.top+(m.clientTop+parseFloat(g.paddingTop))*h.y;c*=h.x,a*=h.y,u*=h.x,d*=h.y,c+=w,a+=x,m=A(m).frameElement}}return it({width:u,height:d,x:c,y:a})}function xe(t){let{rect:e,offsetParent:n,strategy:o}=t;const i=T(n),r=S(n);if(n===r)return e;let s={scrollLeft:0,scrollTop:0},l=M(1);const c=M(0);if((i||!i&&o!=="fixed")&&((P(n)!=="body"||Z(r))&&(s=rt(n)),T(n))){const a=V(n);l=z(n),c.x=a.x+n.clientLeft,c.y=a.y+n.clientTop}return{width:e.width*l.x,height:e.height*l.y,x:e.x*l.x-s.scrollLeft*l.x+c.x,y:e.y*l.y-s.scrollTop*l.y+c.y}}function be(t){return Array.from(t.getClientRects())}function Ht(t){return V(S(t)).left+rt(t).scrollLeft}function Re(t){const e=S(t),n=rt(t),o=t.ownerDocument.body,i=C(e.scrollWidth,e.clientWidth,o.scrollWidth,o.clientWidth),r=C(e.scrollHeight,e.clientHeight,o.scrollHeight,o.clientHeight);let s=-n.scrollLeft+Ht(t);const l=-n.scrollTop;return E(o).direction==="rtl"&&(s+=C(e.clientWidth,o.clientWidth)-i),{width:i,height:r,x:s,y:l}}function Oe(t,e){const n=A(t),o=S(t),i=n.visualViewport;let r=o.clientWidth,s=o.clientHeight,l=0,c=0;if(i){r=i.width,s=i.height;const a=dt();(!a||a&&e==="fixed")&&(l=i.offsetLeft,c=i.offsetTop)}return{width:r,height:s,x:l,y:c}}function Ce(t,e){const n=V(t,!0,e==="fixed"),o=n.top+t.clientTop,i=n.left+t.clientLeft,r=T(t)?z(t):M(1),s=t.clientWidth*r.x,l=t.clientHeight*r.y,c=i*r.x,a=o*r.y;return{width:s,height:l,x:c,y:a}}function Ot(t,e,n){let o;if(e==="viewport")o=Oe(t,n);else if(e==="document")o=Re(S(t));else if(L(e))o=Ce(e,n);else{const i=Pt(t);o={...e,x:e.x-i.x,y:e.y-i.y}}return it(o)}function Nt(t,e){const n=Y(t);return n===e||!L(n)||st(n)?!1:E(n).position==="fixed"||Nt(n,e)}function Ae(t,e){const n=e.get(t);if(n)return n;let o=G(t,[],!1).filter(l=>L(l)&&P(l)!=="body"),i=null;const r=E(t).position==="fixed";let s=r?Y(t):t;for(;L(s)&&!st(s);){const l=E(s),c=ut(s);!c&&l.position==="fixed"&&(i=null),(r?!c&&!i:!c&&l.position==="static"&&!!i&&["absolute","fixed"].includes(i.position)||Z(s)&&!c&&Nt(t,s))?o=o.filter(u=>u!==s):i=l,s=Y(s)}return e.set(t,o),o}function Ee(t){let{element:e,boundary:n,rootBoundary:o,strategy:i}=t;const s=[...n==="clippingAncestors"?Ae(e,this._c):[].concat(n),o],l=s[0],c=s.reduce((a,u)=>{const d=Ot(e,u,i);return a.top=C(d.top,a.top),a.right=X(d.right,a.right),a.bottom=X(d.bottom,a.bottom),a.left=C(d.left,a.left),a},Ot(e,l,i));return{width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top}}function _e(t){return Ft(t)}function Te(t,e,n){const o=T(e),i=S(e),r=n==="fixed",s=V(t,!0,r,e);let l={scrollLeft:0,scrollTop:0};const c=M(0);if(o||!o&&!r)if((P(e)!=="body"||Z(i))&&(l=rt(e)),o){const a=V(e,!0,r,e);c.x=a.x+e.clientLeft,c.y=a.y+e.clientTop}else i&&(c.x=Ht(i));return{x:s.left+l.scrollLeft-c.x,y:s.top+l.scrollTop-c.y,width:s.width,height:s.height}}function Ct(t,e){return!T(t)||E(t).position==="fixed"?null:e?e(t):t.offsetParent}function Vt(t,e){const n=A(t);if(!T(t))return n;let o=Ct(t,e);for(;o&&he(o)&&E(o).position==="static";)o=Ct(o,e);return o&&(P(o)==="html"||P(o)==="body"&&E(o).position==="static"&&!ut(o))?n:o||we(t)||n}const De=async function(t){let{reference:e,floating:n,strategy:o}=t;const i=this.getOffsetParent||Vt,r=this.getDimensions;return{reference:Te(e,await i(n),o),floating:{x:0,y:0,...await r(n)}}};function Le(t){return E(t).direction==="rtl"}const Se={convertOffsetParentRelativeRectToViewportRelativeRect:xe,getDocumentElement:S,getClippingRect:Ee,getOffsetParent:Vt,getElementRects:De,getClientRects:be,getDimensions:_e,getScale:z,isElement:L,isRTL:Le};function Be(t,e){let n=null,o;const i=S(t);function r(){clearTimeout(o),n&&n.disconnect(),n=null}function s(l,c){l===void 0&&(l=!1),c===void 0&&(c=1),r();const{left:a,top:u,width:d,height:p}=t.getBoundingClientRect();if(l||e(),!d||!p)return;const f=tt(u),m=tt(i.clientWidth-(a+d)),h=tt(i.clientHeight-(u+p)),v=tt(a),w={rootMargin:-f+"px "+-m+"px "+-h+"px "+-v+"px",threshold:C(0,X(1,c))||1};let x=!0;function R(b){const O=b[0].intersectionRatio;if(O!==c){if(!x)return s();O?s(!1,O):o=setTimeout(()=>{s(!1,1e-7)},100)}x=!1}try{n=new IntersectionObserver(R,{...w,root:i.ownerDocument})}catch{n=new IntersectionObserver(R,w)}n.observe(t)}return s(!0),r}function ke(t,e,n,o){o===void 0&&(o={});const{ancestorScroll:i=!0,ancestorResize:r=!0,elementResize:s=typeof ResizeObserver=="function",layoutShift:l=typeof IntersectionObserver=="function",animationFrame:c=!1}=o,a=mt(t),u=i||r?[...a?G(a):[],...G(e)]:[];u.forEach(g=>{i&&g.addEventListener("scroll",n,{passive:!0}),r&&g.addEventListener("resize",n)});const d=a&&l?Be(a,n):null;let p=-1,f=null;s&&(f=new ResizeObserver(g=>{let[w]=g;w&&w.target===a&&f&&(f.unobserve(e),cancelAnimationFrame(p),p=requestAnimationFrame(()=>{f&&f.observe(e)})),n()}),a&&!c&&f.observe(a),f.observe(e));let m,h=c?V(t):null;c&&v();function v(){const g=V(t);h&&(g.x!==h.x||g.y!==h.y||g.width!==h.width||g.height!==h.height)&&n(),h=g,m=requestAnimationFrame(v)}return n(),()=>{u.forEach(g=>{i&&g.removeEventListener("scroll",n),r&&g.removeEventListener("resize",n)}),d&&d(),f&&f.disconnect(),f=null,c&&cancelAnimationFrame(m)}}const Me=(t,e,n)=>{const o=new Map,i={platform:Se,...n},r={...i.platform,_c:o};return fe(t,e,{...i,platform:r})};function At(t){var e;return(e=t==null?void 0:t.$el)!=null?e:t}function Wt(t){return typeof window>"u"?1:(t.ownerDocument.defaultView||window).devicePixelRatio||1}function Et(t,e){const n=Wt(t);return Math.round(e*n)/n}function Fe(t,e,n){n===void 0&&(n={});const o=n.whileElementsMounted,i=k(()=>{var y;return(y=N(n.open))!=null?y:!0}),r=k(()=>N(n.middleware)),s=k(()=>{var y;return(y=N(n.placement))!=null?y:"bottom"}),l=k(()=>{var y;return(y=N(n.strategy))!=null?y:"absolute"}),c=k(()=>{var y;return(y=N(n.transform))!=null?y:!0}),a=k(()=>At(t.value)),u=k(()=>At(e.value)),d=D(0),p=D(0),f=D(l.value),m=D(s.value),h=It({}),v=D(!1),g=k(()=>{const y={position:f.value,left:"0",top:"0"};if(!u.value)return y;const _=Et(u.value,d.value),B=Et(u.value,p.value);return c.value?{...y,transform:"translate("+_+"px, "+B+"px)",...Wt(u.value)>=1.5&&{willChange:"transform"}}:{position:f.value,left:_+"px",top:B+"px"}});let w;function x(){a.value==null||u.value==null||Me(a.value,u.value,{middleware:r.value,placement:s.value,strategy:l.value}).then(y=>{d.value=y.x,p.value=y.y,f.value=y.strategy,m.value=y.placement,h.value=y.middlewareData,v.value=!0})}function R(){typeof w=="function"&&(w(),w=void 0)}function b(){if(R(),o===void 0){x();return}if(a.value!=null&&u.value!=null){w=o(a.value,u.value,x);return}}function O(){i.value||(v.value=!1)}return lt([r,s,l],x,{flush:"sync"}),lt([a,u],b,{flush:"sync"}),lt(i,O,{flush:"sync"}),zt()&&Xt(R),{x:I(d),y:I(p),strategy:I(f),placement:I(m),middlewareData:I(h),isPositioned:I(v),floatingStyles:g,update:x}}const Pe=500,He=_t({__name:"DropDown",props:{fallbackOnOpen:{type:Boolean},right:{type:Boolean},open:{type:Boolean},width:{},maxHeight:{},disabled:{type:Boolean},disableClickOutside:{type:Boolean},disableFloating:{type:Boolean},preventToggler:{type:Boolean},strategyFixed:{type:Boolean},hover:{type:Boolean}},setup(t){const e=t,n=D(!1),o=D(null),i=D(null),r=D();Yt(()=>{n.value=!!e.open});let s;if(e.disableFloating||(s=Fe(o,i,{strategy:e.strategyFixed?"fixed":"absolute",placement:e.right?"bottom-end":"bottom-start",middleware:[me(),pe(),ue(),ge({padding:5,apply({availableHeight:l}){r.value=`${Math.max(150,Math.min(e.maxHeight?Number(e.maxHeight.replace("px","")):Pe,l))}px`}})],whileElementsMounted:ke}).floatingStyles),e.hover){const l=wt(o),c=wt(i);jt([l,c],([a,u])=>{a?n.value=!0:u||(n.value=!1)},{debounce:200})}return(l,c)=>(et(),qt(N(Qt),{onTrigger:c[1]||(c[1]=()=>{e.disableClickOutside||(n.value=!1)})},{default:Ut(()=>[vt("div",{ref_key:"togglerRef",ref:o,onClick:c[0]||(c[0]=()=>{!e.hover&&!e.preventToggler&&!e.disabled&&(n.value=!n.value)})},[U(l.$slots,"default",{},void 0,!0)],512),e.fallbackOnOpen||n.value?Gt((et(),at("div",{key:0,ref_key:"dropdownRef",ref:i,style:yt(N(s)),class:Tt(["dropdown",{"is-opened":n.value}])},[vt("div",{class:"dropdown-body",style:yt({width:e.width,"max-height":r.value})},[U(l.$slots,"body",{hide:()=>n.value=!1},void 0,!0)],4),U(l.$slots,"auxBody",{parentProps:e},void 0,!0)],6)),[[Kt,n.value]]):Jt("",!0)]),_:3}))}});const Ie=Dt(He,[["__scopeId","data-v-c0b506be"]]);export{Ie as D,$e as M,We as _};