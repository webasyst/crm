import{U as C}from"./UserPic-87c13a4a.js";import{d as D,a as v,z as F,a0 as z,N as B,aM as L,o as i,b as h,w as f,c as _,i as o,a5 as k,e as w,F as g,l as $,f as I,p as x,n as b,r as S,O as E,J as U,L as N,t as A,k as M,A as K}from"./main-ec0df6a4.js";import{M as R,_ as O}from"./MenuList-466c4134.js";import{D as W}from"./DropDown-5d25a522.js";import{C as V}from"./CustomColumn-959e6ffe.js";const j=["placeholder","value"],J=["onClick"],P={class:"tw-flex-auto tw-overflow-hidden"},q=["onClick"],G=o("i",{class:"fas fa-trash-alt gray"},null,-1),H=[G],Q=D({__name:"InputWithDropDown",props:{items:{},value:{},textValue:{},isMultiple:{type:Boolean},placeholder:{},onEnter:{type:Function},onItemSelect:{type:Function}},emits:["input","update"],setup(m,{emit:c}){const t=m,p=v(),l=v(!1),s=v(new Set(t.value)),u=F(()=>z([null,...t.items]));B(s,e=>{c("update",[...e])},{deep:!0}),L(p,()=>{l.value=!1});function d(e){c("input",e.target.value),l.value=!0}function r(e,a){if(e){if(t.onItemSelect){t.onItemSelect(e),l.value=!1;return}t.isMultiple||s.value.clear(),s.value.add(e),l.value=!1,a&&a.target.blur()}else t.onEnter&&a&&a.target.value&&(t.onEnter(a.target.value),a.target.blur(),l.value=!1)}return(e,a)=>(i(),h(V,{space:"4"},{default:f(()=>[t.isMultiple||!s.value.size?(i(),_("div",{key:0,ref_key:"inputRef",ref:p,class:"tw-w-full"},[o("input",{type:"text",placeholder:t.placeholder,value:t.textValue,class:"tw-w-full",onInput:d,onKeyup:a[0]||(a[0]=k(n=>{r(u.value.state.value,n)},["enter"])),onKeydown:[a[1]||(a[1]=k(x(n=>u.value.prev(),["prevent"]),["up"])),a[2]||(a[2]=k(x(n=>u.value.next(),["prevent"]),["down"]))],onFocus:d},null,40,j),w(W,{open:l.value,"disable-click-outside":!0,"max-height":"400px"},{body:f(()=>[t.items.length?(i(),h(R,{key:0},{default:f(()=>[(i(!0),_(g,null,$(t.items,(n,y)=>(i(),h(O,{key:y},{default:f(()=>[o("a",{class:b({"tw-bg-waBackground":u.value.index.value===y+1}),onClick:x(se=>r(n),["prevent"])},[S(e.$slots,"item",{item:n})],10,J)]),_:2},1024))),128))]),_:3})):I("",!0)]),_:3},8,["open"])],512)):I("",!0),w(V,{space:"2"},{default:f(()=>[(i(!0),_(g,null,$(s.value,n=>(i(),_("div",{key:n.id,class:"tw-flex tw-items-center tw-space-x-2"},[o("div",P,[S(e.$slots,"listItem",{item:n})]),o("a",{onClick:y=>s.value.delete(n)},H,8,q)]))),128))]),_:3})]),_:3}))}}),T=E("users",()=>{const m=v([]),{data:c,isFetching:t,error:p,isFinished:l,execute:s}=U("crm.user.list").get().json();N(()=>{Array.isArray(c.value)&&(m.value=c.value)});function u(){l.value&&s()}return{users:m,isFetching:t,error:p,refetch:u}}),X={class:"icon size-20"},Y=["title"],Z={class:"tw-flex tw-items-center tw-space-x-2"},ee={class:"icon size-20"},te=["title"],re=D({__name:"InputWithUsers",props:{modelValue:{},isMultiple:{type:Boolean,default:!0}},emits:["update:modelValue"],setup(m,{emit:c}){const t=m,p=T(),{users:l}=A(p);p.refetch();const s=v([]);function u(d){s.value=d.length?l.value.filter(e=>e.name.toLowerCase().includes(d.toLowerCase())):l.value;const r=s.value.findIndex(e=>e.id===K.user.id);if(r>-1){const e=s.value.splice(r,1)[0];s.value.splice(0,0,e)}}return(d,r)=>(i(),h(Q,{items:s.value,value:t.modelValue,"is-multiple":t.isMultiple,placeholder:d.$t("search"),onInput:u,onUpdate:r[0]||(r[0]=e=>{c("update:modelValue",e)})},{item:f(({item:e})=>[o("div",X,[w(C,{url:e.userpic,size:20},null,8,["url"])]),o("span",{class:"tw-truncate",title:e.name},M(e.name),9,Y)]),listItem:f(({item:e})=>[o("div",Z,[o("div",ee,[w(C,{url:e.userpic,size:20},null,8,["url"])]),o("span",{class:"tw-truncate small",title:e.name},M(e.name),9,te)])]),_:1},8,["items","value","is-multiple","placeholder"]))}});export{re as _,Q as a};
