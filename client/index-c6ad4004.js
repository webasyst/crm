import{d as D,a as w,b5 as x,b9 as P,bu as E,aJ as B,aQ as H,bv as g,bw as h,A,aI as y,bx as U,P as b,bm as _,by as Y,aP as j,bz as R,b3 as T,ao as X}from"./main-07d84941.js";const G=D({name:"OnClickOutside",props:["as","options"],emits:["trigger"],setup(e,{slots:t,emit:n}){const o=w();return x(o,a=>{n("trigger",a)},e.options),()=>{if(t.default)return P(e.as||"div",{ref:o},t.default())}}});function m(e){var t;const n=y(e);return(t=n==null?void 0:n.$el)!=null?t:n}const C=R?window:void 0;function O(...e){let t,n,o,a;if(typeof e[0]=="string"||Array.isArray(e[0])?([n,o,a]=e,t=C):[t,n,o,a]=e,!t)return h;Array.isArray(n)||(n=[n]),Array.isArray(o)||(o=[o]);const d=[],v=()=>{d.forEach(l=>l()),d.length=0},u=(l,c,s,r)=>(l.addEventListener(c,s,r),()=>l.removeEventListener(c,s,r)),f=b(()=>[m(t),y(a)],([l,c])=>{if(v(),!l)return;const s=Y(c)?{...c}:c;d.push(...n.flatMap(r=>o.map(i=>u(l,r,i,s))))},{immediate:!0,flush:"post"}),p=()=>{f(),v()};return _(p),p}let S=!1;function M(e,t,n={}){const{window:o=C,ignore:a=[],capture:d=!0,detectIframe:v=!1}=n;if(!o)return;g&&!S&&(S=!0,Array.from(o.document.body.children).forEach(s=>s.addEventListener("click",h)),o.document.documentElement.addEventListener("click",h));let u=!0;const f=s=>a.some(r=>{if(typeof r=="string")return Array.from(o.document.querySelectorAll(r)).some(i=>i===s.target||s.composedPath().includes(i));{const i=m(r);return i&&(s.target===i||s.composedPath().includes(i))}}),l=[O(o,"click",s=>{const r=m(e);if(!(!r||r===s.target||s.composedPath().includes(r))){if(s.detail===0&&(u=!f(s)),!u){u=!0;return}t(s)}},{passive:!0,capture:d}),O(o,"pointerdown",s=>{const r=m(e);r&&(u=!s.composedPath().includes(r)&&!f(s))},{passive:!0}),v&&O(o,"blur",s=>{setTimeout(()=>{var r;const i=m(e);((r=o.document.activeElement)==null?void 0:r.tagName)==="IFRAME"&&!(i!=null&&i.contains(o.document.activeElement))&&t(s)},0)})].filter(Boolean);return()=>l.forEach(s=>s())}const K={[E.mounted](e,t){const n=!t.modifiers.bubble;if(typeof t.value=="function")e.__onClickOutside_stop=M(e,t.value,{capture:n});else{const[o,a]=t.value;e.__onClickOutside_stop=M(e,o,Object.assign({capture:n},a))}},[E.unmounted](e){e.__onClickOutside_stop()}};function $(){const e=w(!1);return T()&&X(()=>{e.value=!0}),e}function q(e){const t=$();return A(()=>(t.value,!!e()))}const Z=D({name:"UseElementVisibility",props:["as"],setup(e,{slots:t}){const n=w(),o=B({isVisible:H(n)});return()=>{if(t.default)return P(e.as||"div",{ref:n},t.default(o))}}});function W(e,t,n={}){const{root:o,rootMargin:a="0px",threshold:d=.1,window:v=C,immediate:u=!0}=n,f=q(()=>v&&"IntersectionObserver"in v),p=A(()=>{const i=y(e);return(Array.isArray(i)?i:[i]).map(m).filter(U)});let l=h;const c=w(u),s=f.value?b(()=>[p.value,m(o),c.value],([i,N])=>{if(l(),!c.value||!i.length)return;const L=new IntersectionObserver(t,{root:m(N),rootMargin:a,threshold:d});i.forEach(I=>I&&L.observe(I)),l=()=>{L.disconnect(),l=h}},{immediate:u,flush:"post"}):h,r=()=>{l(),s(),c.value=!1};return _(r),{isSupported:f,isActive:c,pause(){l(),c.value=!1},resume(){c.value=!0},stop:r}}function k(e){return typeof Window<"u"&&e instanceof Window?e.document.documentElement:typeof Document<"u"&&e instanceof Document?e.documentElement:e}const ee={[E.mounted](e,t){typeof t.value=="function"?W(e,t.value):W(e,...t.value)}};function V(e){const t=window.getComputedStyle(e);if(t.overflowX==="scroll"||t.overflowY==="scroll"||t.overflowX==="auto"&&e.clientWidth<e.scrollWidth||t.overflowY==="auto"&&e.clientHeight<e.scrollHeight)return!0;{const n=e.parentNode;return!n||n.tagName==="BODY"?!1:V(n)}}function z(e){const t=e||window.event,n=t.target;return V(n)?!1:t.touches.length>1?!0:(t.preventDefault&&t.preventDefault(),!1)}function F(e,t=!1){const n=w(t);let o=null,a;b(j(e),u=>{const f=k(y(u));if(f){const p=f;a=p.style.overflow,n.value&&(p.style.overflow="hidden")}},{immediate:!0});const d=()=>{const u=k(y(e));!u||n.value||(g&&(o=O(u,"touchmove",f=>{z(f)},{passive:!1})),u.style.overflow="hidden",n.value=!0)},v=()=>{const u=k(y(e));!u||!n.value||(g&&(o==null||o()),u.style.overflow=a,n.value=!1)};return _(v),A({get(){return n.value},set(u){u?d():v()}})}function J(){let e=!1;const t=w(!1);return(n,o)=>{if(t.value=o.value,e)return;e=!0;const a=F(n,o.value);b(t,d=>a.value=d)}}J();export{G as O,Z as U,K as a,ee as v};
