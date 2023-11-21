import{O as Vt}from"./index-f0757591.js";import{z as M,a as D,b1 as Nt,P as st,b2 as Ht,b3 as $,g as N,b4 as Wt,d as zt,N as $t,o as gt,b as It,w as Xt,i as ht,r as rt,c as Yt,V as wt,n as jt,f as qt,_ as Ut}from"./main-ad3d4b2a.js";const X=Math.min,A=Math.max,tt=Math.round,Z=Math.floor,P=t=>({x:t,y:t}),Gt={left:"right",right:"left",bottom:"top",top:"bottom"},Kt={start:"end",end:"start"};function yt(t,e,n){return A(t,X(e,n))}function G(t,e){return typeof t=="function"?t(e):t}function _(t){return t.split("-")[0]}function K(t){return t.split("-")[1]}function Ot(t){return t==="x"?"y":"x"}function Et(t){return t==="y"?"height":"width"}function J(t){return["top","bottom"].includes(_(t))?"y":"x"}function Tt(t){return Ot(J(t))}function Jt(t,e,n){n===void 0&&(n=!1);const o=K(t),i=Tt(t),r=Et(i);let s=i==="x"?o===(n?"end":"start")?"right":"left":o==="start"?"bottom":"top";return e.reference[r]>e.floating[r]&&(s=et(s)),[s,et(s)]}function Qt(t){const e=et(t);return[lt(t),e,lt(e)]}function lt(t){return t.replace(/start|end/g,e=>Kt[e])}function Zt(t,e,n){const o=["left","right"],i=["right","left"],r=["top","bottom"],s=["bottom","top"];switch(t){case"top":case"bottom":return n?e?i:o:e?o:i;case"left":case"right":return e?r:s;default:return[]}}function te(t,e,n,o){const i=K(t);let r=Zt(_(t),n==="start",o);return i&&(r=r.map(s=>s+"-"+i),e&&(r=r.concat(r.map(lt)))),r}function et(t){return t.replace(/left|right|bottom|top/g,e=>Gt[e])}function ee(t){return{top:0,right:0,bottom:0,left:0,...t}}function ne(t){return typeof t!="number"?ee(t):{top:t,right:t,bottom:t,left:t}}function nt(t){return{...t,top:t.y,left:t.x,right:t.x+t.width,bottom:t.y+t.height}}function vt(t,e,n){let{reference:o,floating:i}=t;const r=J(e),s=Tt(e),l=Et(s),c=_(e),a=r==="y",u=o.x+o.width/2-i.width/2,d=o.y+o.height/2-i.height/2,p=o[l]/2-i[l]/2;let f;switch(c){case"top":f={x:u,y:o.y-i.height};break;case"bottom":f={x:u,y:o.y+o.height};break;case"right":f={x:o.x+o.width,y:d};break;case"left":f={x:o.x-i.width,y:d};break;default:f={x:o.x,y:o.y}}switch(K(e)){case"start":f[s]-=p*(n&&a?-1:1);break;case"end":f[s]+=p*(n&&a?-1:1);break}return f}const oe=async(t,e,n)=>{const{placement:o="bottom",strategy:i="absolute",middleware:r=[],platform:s}=n,l=r.filter(Boolean),c=await(s.isRTL==null?void 0:s.isRTL(e));let a=await s.getElementRects({reference:t,floating:e,strategy:i}),{x:u,y:d}=vt(a,o,c),p=o,f={},m=0;for(let h=0;h<l.length;h++){const{name:y,fn:g}=l[h],{x:w,y:x,data:R,reset:b}=await g({x:u,y:d,initialPlacement:o,placement:p,strategy:i,middlewareData:f,rects:a,platform:s,elements:{reference:t,floating:e}});if(u=w??u,d=x??d,f={...f,[y]:{...f[y],...R}},b&&m<=50){m++,typeof b=="object"&&(b.placement&&(p=b.placement),b.rects&&(a=b.rects===!0?await s.getElementRects({reference:t,floating:e,strategy:i}):b.rects),{x:u,y:d}=vt(a,p,c)),h=-1;continue}}return{x:u,y:d,placement:p,strategy:i,middlewareData:f}};async function ct(t,e){var n;e===void 0&&(e={});const{x:o,y:i,platform:r,rects:s,elements:l,strategy:c}=t,{boundary:a="clippingAncestors",rootBoundary:u="viewport",elementContext:d="floating",altBoundary:p=!1,padding:f=0}=G(e,t),m=ne(f),y=l[p?d==="floating"?"reference":"floating":d],g=nt(await r.getClippingRect({element:(n=await(r.isElement==null?void 0:r.isElement(y)))==null||n?y:y.contextElement||await(r.getDocumentElement==null?void 0:r.getDocumentElement(l.floating)),boundary:a,rootBoundary:u,strategy:c})),w=d==="floating"?{...s.floating,x:o,y:i}:s.reference,x=await(r.getOffsetParent==null?void 0:r.getOffsetParent(l.floating)),R=await(r.isElement==null?void 0:r.isElement(x))?await(r.getScale==null?void 0:r.getScale(x))||{x:1,y:1}:{x:1,y:1},b=nt(r.convertOffsetParentRelativeRectToViewportRelativeRect?await r.convertOffsetParentRelativeRectToViewportRelativeRect({rect:w,offsetParent:x,strategy:c}):w);return{top:(g.top-b.top+m.top)/R.y,bottom:(b.bottom-g.bottom+m.bottom)/R.y,left:(g.left-b.left+m.left)/R.x,right:(b.right-g.right+m.right)/R.x}}const ie=function(t){return t===void 0&&(t={}),{name:"flip",options:t,async fn(e){var n,o;const{placement:i,middlewareData:r,rects:s,initialPlacement:l,platform:c,elements:a}=e,{mainAxis:u=!0,crossAxis:d=!0,fallbackPlacements:p,fallbackStrategy:f="bestFit",fallbackAxisSideDirection:m="none",flipAlignment:h=!0,...y}=G(t,e);if((n=r.arrow)!=null&&n.alignmentOffset)return{};const g=_(i),w=_(l)===l,x=await(c.isRTL==null?void 0:c.isRTL(a.floating)),R=p||(w||!h?[et(l)]:Qt(l));!p&&m!=="none"&&R.push(...te(l,h,m,x));const b=[l,...R],C=await ct(e,y),v=[];let T=((o=r.flip)==null?void 0:o.overflows)||[];if(u&&v.push(C[g]),d){const V=Jt(i,s,x);v.push(C[V[0]],C[V[1]])}if(T=[...T,{placement:i,overflows:v}],!v.every(V=>V<=0)){var B,dt;const V=(((B=r.flip)==null?void 0:B.index)||0)+1,pt=b[V];if(pt)return{data:{index:V,overflows:T},reset:{placement:pt}};let j=(dt=T.filter(W=>W.overflows[0]<=0).sort((W,z)=>W.overflows[1]-z.overflows[1])[0])==null?void 0:dt.placement;if(!j)switch(f){case"bestFit":{var mt;const W=(mt=T.map(z=>[z.placement,z.overflows.filter(q=>q>0).reduce((q,Ft)=>q+Ft,0)]).sort((z,q)=>z[1]-q[1])[0])==null?void 0:mt[0];W&&(j=W);break}case"initialPlacement":j=l;break}if(i!==j)return{reset:{placement:j}}}return{}}}};async function se(t,e){const{placement:n,platform:o,elements:i}=t,r=await(o.isRTL==null?void 0:o.isRTL(i.floating)),s=_(n),l=K(n),c=J(n)==="y",a=["left","top"].includes(s)?-1:1,u=r&&c?-1:1,d=G(e,t);let{mainAxis:p,crossAxis:f,alignmentAxis:m}=typeof d=="number"?{mainAxis:d,crossAxis:0,alignmentAxis:null}:{mainAxis:0,crossAxis:0,alignmentAxis:null,...d};return l&&typeof m=="number"&&(f=l==="end"?m*-1:m),c?{x:f*u,y:p*a}:{x:p*a,y:f*u}}const re=function(t){return t===void 0&&(t=0),{name:"offset",options:t,async fn(e){const{x:n,y:o}=e,i=await se(e,t);return{x:n+i.x,y:o+i.y,data:i}}}},le=function(t){return t===void 0&&(t={}),{name:"shift",options:t,async fn(e){const{x:n,y:o,placement:i}=e,{mainAxis:r=!0,crossAxis:s=!1,limiter:l={fn:y=>{let{x:g,y:w}=y;return{x:g,y:w}}},...c}=G(t,e),a={x:n,y:o},u=await ct(e,c),d=J(_(i)),p=Ot(d);let f=a[p],m=a[d];if(r){const y=p==="y"?"top":"left",g=p==="y"?"bottom":"right",w=f+u[y],x=f-u[g];f=yt(w,f,x)}if(s){const y=d==="y"?"top":"left",g=d==="y"?"bottom":"right",w=m+u[y],x=m-u[g];m=yt(w,m,x)}const h=l.fn({...e,[p]:f,[d]:m});return{...h,data:{x:h.x-n,y:h.y-o}}}}},ce=function(t){return t===void 0&&(t={}),{name:"size",options:t,async fn(e){const{placement:n,rects:o,platform:i,elements:r}=e,{apply:s=()=>{},...l}=G(t,e),c=await ct(e,l),a=_(n),u=K(n),d=J(n)==="y",{width:p,height:f}=o.floating;let m,h;a==="top"||a==="bottom"?(m=a,h=u===(await(i.isRTL==null?void 0:i.isRTL(r.floating))?"start":"end")?"left":"right"):(h=a,m=u==="end"?"top":"bottom");const y=f-c[m],g=p-c[h],w=!e.middlewareData.shift;let x=y,R=g;if(d){const C=p-c.left-c.right;R=u||w?X(g,C):C}else{const C=f-c.top-c.bottom;x=u||w?X(y,C):C}if(w&&!u){const C=A(c.left,0),v=A(c.right,0),T=A(c.top,0),B=A(c.bottom,0);d?R=p-2*(C!==0||v!==0?C+v:A(c.left,c.right)):x=f-2*(T!==0||B!==0?T+B:A(c.top,c.bottom))}await s({...e,availableWidth:R,availableHeight:x});const b=await i.getDimensions(r.floating);return p!==b.width||f!==b.height?{reset:{rects:!0}}:{}}}};function F(t){return St(t)?(t.nodeName||"").toLowerCase():"#document"}function O(t){var e;return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window}function k(t){var e;return(e=(St(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement}function St(t){return t instanceof Node||t instanceof O(t).Node}function L(t){return t instanceof Element||t instanceof O(t).Element}function S(t){return t instanceof HTMLElement||t instanceof O(t).HTMLElement}function xt(t){return typeof ShadowRoot>"u"?!1:t instanceof ShadowRoot||t instanceof O(t).ShadowRoot}function Q(t){const{overflow:e,overflowX:n,overflowY:o,display:i}=E(t);return/auto|scroll|overlay|hidden|clip/.test(e+o+n)&&!["inline","contents"].includes(i)}function ae(t){return["table","td","th"].includes(F(t))}function at(t){const e=ft(),n=E(t);return n.transform!=="none"||n.perspective!=="none"||(n.containerType?n.containerType!=="normal":!1)||!e&&(n.backdropFilter?n.backdropFilter!=="none":!1)||!e&&(n.filter?n.filter!=="none":!1)||["transform","perspective","filter"].some(o=>(n.willChange||"").includes(o))||["paint","layout","strict","content"].some(o=>(n.contain||"").includes(o))}function fe(t){let e=Y(t);for(;S(e)&&!ot(e);){if(at(e))return e;e=Y(e)}return null}function ft(){return typeof CSS>"u"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")}function ot(t){return["html","body","#document"].includes(F(t))}function E(t){return O(t).getComputedStyle(t)}function it(t){return L(t)?{scrollLeft:t.scrollLeft,scrollTop:t.scrollTop}:{scrollLeft:t.pageXOffset,scrollTop:t.pageYOffset}}function Y(t){if(F(t)==="html")return t;const e=t.assignedSlot||t.parentNode||xt(t)&&t.host||k(t);return xt(e)?e.host:e}function Dt(t){const e=Y(t);return ot(e)?t.ownerDocument?t.ownerDocument.body:t.body:S(e)&&Q(e)?e:Dt(e)}function U(t,e,n){var o;e===void 0&&(e=[]),n===void 0&&(n=!0);const i=Dt(t),r=i===((o=t.ownerDocument)==null?void 0:o.body),s=O(i);return r?e.concat(s,s.visualViewport||[],Q(i)?i:[],s.frameElement&&n?U(s.frameElement):[]):e.concat(i,U(i,[],n))}function Lt(t){const e=E(t);let n=parseFloat(e.width)||0,o=parseFloat(e.height)||0;const i=S(t),r=i?t.offsetWidth:n,s=i?t.offsetHeight:o,l=tt(n)!==r||tt(o)!==s;return l&&(n=r,o=s),{width:n,height:o,$:l}}function ut(t){return L(t)?t:t.contextElement}function I(t){const e=ut(t);if(!S(e))return P(1);const n=e.getBoundingClientRect(),{width:o,height:i,$:r}=Lt(e);let s=(r?tt(n.width):n.width)/o,l=(r?tt(n.height):n.height)/i;return(!s||!Number.isFinite(s))&&(s=1),(!l||!Number.isFinite(l))&&(l=1),{x:s,y:l}}const ue=P(0);function kt(t){const e=O(t);return!ft()||!e.visualViewport?ue:{x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop}}function de(t,e,n){return e===void 0&&(e=!1),!n||e&&n!==O(t)?!1:e}function H(t,e,n,o){e===void 0&&(e=!1),n===void 0&&(n=!1);const i=t.getBoundingClientRect(),r=ut(t);let s=P(1);e&&(o?L(o)&&(s=I(o)):s=I(t));const l=de(r,n,o)?kt(r):P(0);let c=(i.left+l.x)/s.x,a=(i.top+l.y)/s.y,u=i.width/s.x,d=i.height/s.y;if(r){const p=O(r),f=o&&L(o)?O(o):o;let m=p.frameElement;for(;m&&o&&f!==p;){const h=I(m),y=m.getBoundingClientRect(),g=E(m),w=y.left+(m.clientLeft+parseFloat(g.paddingLeft))*h.x,x=y.top+(m.clientTop+parseFloat(g.paddingTop))*h.y;c*=h.x,a*=h.y,u*=h.x,d*=h.y,c+=w,a+=x,m=O(m).frameElement}}return nt({width:u,height:d,x:c,y:a})}function me(t){let{rect:e,offsetParent:n,strategy:o}=t;const i=S(n),r=k(n);if(n===r)return e;let s={scrollLeft:0,scrollTop:0},l=P(1);const c=P(0);if((i||!i&&o!=="fixed")&&((F(n)!=="body"||Q(r))&&(s=it(n)),S(n))){const a=H(n);l=I(n),c.x=a.x+n.clientLeft,c.y=a.y+n.clientTop}return{width:e.width*l.x,height:e.height*l.y,x:e.x*l.x-s.scrollLeft*l.x+c.x,y:e.y*l.y-s.scrollTop*l.y+c.y}}function pe(t){return Array.from(t.getClientRects())}function Bt(t){return H(k(t)).left+it(t).scrollLeft}function ge(t){const e=k(t),n=it(t),o=t.ownerDocument.body,i=A(e.scrollWidth,e.clientWidth,o.scrollWidth,o.clientWidth),r=A(e.scrollHeight,e.clientHeight,o.scrollHeight,o.clientHeight);let s=-n.scrollLeft+Bt(t);const l=-n.scrollTop;return E(o).direction==="rtl"&&(s+=A(e.clientWidth,o.clientWidth)-i),{width:i,height:r,x:s,y:l}}function he(t,e){const n=O(t),o=k(t),i=n.visualViewport;let r=o.clientWidth,s=o.clientHeight,l=0,c=0;if(i){r=i.width,s=i.height;const a=ft();(!a||a&&e==="fixed")&&(l=i.offsetLeft,c=i.offsetTop)}return{width:r,height:s,x:l,y:c}}function we(t,e){const n=H(t,!0,e==="fixed"),o=n.top+t.clientTop,i=n.left+t.clientLeft,r=S(t)?I(t):P(1),s=t.clientWidth*r.x,l=t.clientHeight*r.y,c=i*r.x,a=o*r.y;return{width:s,height:l,x:c,y:a}}function bt(t,e,n){let o;if(e==="viewport")o=he(t,n);else if(e==="document")o=ge(k(t));else if(L(e))o=we(e,n);else{const i=kt(t);o={...e,x:e.x-i.x,y:e.y-i.y}}return nt(o)}function Mt(t,e){const n=Y(t);return n===e||!L(n)||ot(n)?!1:E(n).position==="fixed"||Mt(n,e)}function ye(t,e){const n=e.get(t);if(n)return n;let o=U(t,[],!1).filter(l=>L(l)&&F(l)!=="body"),i=null;const r=E(t).position==="fixed";let s=r?Y(t):t;for(;L(s)&&!ot(s);){const l=E(s),c=at(s);!c&&l.position==="fixed"&&(i=null),(r?!c&&!i:!c&&l.position==="static"&&!!i&&["absolute","fixed"].includes(i.position)||Q(s)&&!c&&Mt(t,s))?o=o.filter(u=>u!==s):i=l,s=Y(s)}return e.set(t,o),o}function ve(t){let{element:e,boundary:n,rootBoundary:o,strategy:i}=t;const s=[...n==="clippingAncestors"?ye(e,this._c):[].concat(n),o],l=s[0],c=s.reduce((a,u)=>{const d=bt(e,u,i);return a.top=A(d.top,a.top),a.right=X(d.right,a.right),a.bottom=X(d.bottom,a.bottom),a.left=A(d.left,a.left),a},bt(e,l,i));return{width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top}}function xe(t){return Lt(t)}function be(t,e,n){const o=S(e),i=k(e),r=n==="fixed",s=H(t,!0,r,e);let l={scrollLeft:0,scrollTop:0};const c=P(0);if(o||!o&&!r)if((F(e)!=="body"||Q(i))&&(l=it(e)),o){const a=H(e,!0,r,e);c.x=a.x+e.clientLeft,c.y=a.y+e.clientTop}else i&&(c.x=Bt(i));return{x:s.left+l.scrollLeft-c.x,y:s.top+l.scrollTop-c.y,width:s.width,height:s.height}}function Rt(t,e){return!S(t)||E(t).position==="fixed"?null:e?e(t):t.offsetParent}function Pt(t,e){const n=O(t);if(!S(t))return n;let o=Rt(t,e);for(;o&&ae(o)&&E(o).position==="static";)o=Rt(o,e);return o&&(F(o)==="html"||F(o)==="body"&&E(o).position==="static"&&!at(o))?n:o||fe(t)||n}const Re=async function(t){let{reference:e,floating:n,strategy:o}=t;const i=this.getOffsetParent||Pt,r=this.getDimensions;return{reference:be(e,await i(n),o),floating:{x:0,y:0,...await r(n)}}};function Ce(t){return E(t).direction==="rtl"}const Ae={convertOffsetParentRelativeRectToViewportRelativeRect:me,getDocumentElement:k,getClippingRect:ve,getOffsetParent:Pt,getElementRects:Re,getClientRects:pe,getDimensions:xe,getScale:I,isElement:L,isRTL:Ce};function Oe(t,e){let n=null,o;const i=k(t);function r(){clearTimeout(o),n&&n.disconnect(),n=null}function s(l,c){l===void 0&&(l=!1),c===void 0&&(c=1),r();const{left:a,top:u,width:d,height:p}=t.getBoundingClientRect();if(l||e(),!d||!p)return;const f=Z(u),m=Z(i.clientWidth-(a+d)),h=Z(i.clientHeight-(u+p)),y=Z(a),w={rootMargin:-f+"px "+-m+"px "+-h+"px "+-y+"px",threshold:A(0,X(1,c))||1};let x=!0;function R(b){const C=b[0].intersectionRatio;if(C!==c){if(!x)return s();C?s(!1,C):o=setTimeout(()=>{s(!1,1e-7)},100)}x=!1}try{n=new IntersectionObserver(R,{...w,root:i.ownerDocument})}catch{n=new IntersectionObserver(R,w)}n.observe(t)}return s(!0),r}function Ee(t,e,n,o){o===void 0&&(o={});const{ancestorScroll:i=!0,ancestorResize:r=!0,elementResize:s=typeof ResizeObserver=="function",layoutShift:l=typeof IntersectionObserver=="function",animationFrame:c=!1}=o,a=ut(t),u=i||r?[...a?U(a):[],...U(e)]:[];u.forEach(g=>{i&&g.addEventListener("scroll",n,{passive:!0}),r&&g.addEventListener("resize",n)});const d=a&&l?Oe(a,n):null;let p=-1,f=null;s&&(f=new ResizeObserver(g=>{let[w]=g;w&&w.target===a&&f&&(f.unobserve(e),cancelAnimationFrame(p),p=requestAnimationFrame(()=>{f&&f.observe(e)})),n()}),a&&!c&&f.observe(a),f.observe(e));let m,h=c?H(t):null;c&&y();function y(){const g=H(t);h&&(g.x!==h.x||g.y!==h.y||g.width!==h.width||g.height!==h.height)&&n(),h=g,m=requestAnimationFrame(y)}return n(),()=>{u.forEach(g=>{i&&g.removeEventListener("scroll",n),r&&g.removeEventListener("resize",n)}),d&&d(),f&&f.disconnect(),f=null,c&&cancelAnimationFrame(m)}}const Te=(t,e,n)=>{const o=new Map,i={platform:Ae,...n},r={...i.platform,_c:o};return oe(t,e,{...i,platform:r})};function Ct(t){var e;return(e=t==null?void 0:t.$el)!=null?e:t}function _t(t){return typeof window>"u"?1:(t.ownerDocument.defaultView||window).devicePixelRatio||1}function At(t,e){const n=_t(t);return Math.round(e*n)/n}function Se(t,e,n){n===void 0&&(n={});const o=n.whileElementsMounted,i=M(()=>{var v;return(v=N(n.open))!=null?v:!0}),r=M(()=>N(n.middleware)),s=M(()=>{var v;return(v=N(n.placement))!=null?v:"bottom"}),l=M(()=>{var v;return(v=N(n.strategy))!=null?v:"absolute"}),c=M(()=>{var v;return(v=N(n.transform))!=null?v:!0}),a=M(()=>Ct(t.value)),u=M(()=>Ct(e.value)),d=D(0),p=D(0),f=D(l.value),m=D(s.value),h=Nt({}),y=D(!1),g=M(()=>{const v={position:f.value,left:"0",top:"0"};if(!u.value)return v;const T=At(u.value,d.value),B=At(u.value,p.value);return c.value?{...v,transform:"translate("+T+"px, "+B+"px)",..._t(u.value)>=1.5&&{willChange:"transform"}}:{position:f.value,left:T+"px",top:B+"px"}});let w;function x(){a.value==null||u.value==null||Te(a.value,u.value,{middleware:r.value,placement:s.value,strategy:l.value}).then(v=>{d.value=v.x,p.value=v.y,f.value=v.strategy,m.value=v.placement,h.value=v.middlewareData,y.value=!0})}function R(){typeof w=="function"&&(w(),w=void 0)}function b(){if(R(),o===void 0){x();return}if(a.value!=null&&u.value!=null){w=o(a.value,u.value,x);return}}function C(){i.value||(y.value=!1)}return st([r,s,l],x,{flush:"sync"}),st([a,u],b,{flush:"sync"}),st(i,C,{flush:"sync"}),Ht()&&Wt(R),{x:$(d),y:$(p),strategy:$(f),placement:$(m),middlewareData:$(h),isPositioned:$(y),floatingStyles:g,update:x}}const De=500,Le=zt({__name:"DropDown",props:{right:{type:Boolean},open:{type:Boolean},width:{},maxHeight:{},disabled:{type:Boolean},disableClickOutside:{type:Boolean},disableFloating:{type:Boolean},preventToggler:{type:Boolean}},setup(t){const e=t,n=D(!1),o=D(null),i=D(null),r=D();let s;return e.disableFloating||(s=Se(o,i,{placement:e.right?"bottom-end":"bottom-start",middleware:[re(),le(),ie(),ce({padding:5,apply({availableHeight:l}){r.value=`${Math.max(150,Math.min(e.maxHeight?Number(e.maxHeight.replace("px","")):De,l))}px`}})],whileElementsMounted:Ee}).floatingStyles),$t(()=>{n.value=!!e.open}),(l,c)=>(gt(),It(N(Vt),{onTrigger:c[1]||(c[1]=()=>{e.disableClickOutside||(n.value=!1)})},{default:Xt(()=>[ht("div",{ref_key:"togglerRef",ref:o,onClick:c[0]||(c[0]=()=>{!e.preventToggler&&!e.disabled&&(n.value=!n.value)})},[rt(l.$slots,"default",{},void 0,!0)],512),n.value?(gt(),Yt("div",{key:0,ref_key:"dropdownRef",ref:i,style:wt(N(s)),class:jt(["dropdown",{"is-opened":n.value}])},[ht("div",{class:"dropdown-body",style:wt({width:e.width,"max-height":r.value})},[rt(l.$slots,"body",{hide:()=>n.value=!1},void 0,!0)],4),rt(l.$slots,"auxBody",{parentProps:e},void 0,!0)],6)):qt("",!0)]),_:3}))}});const Me=Ut(Le,[["__scopeId","data-v-b2d4337e"]]);export{Me as D};
