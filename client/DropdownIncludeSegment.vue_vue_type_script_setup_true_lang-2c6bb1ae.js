import{d as z,C as F,t as h,E as I,a as y,z as u,o as a,c as l,i as n,n as p,k as d,g as L,F as m,b as _,w,l as x,p as M}from"./main-512a5cdd.js";import{U as A}from"./UserPic-2094e1a0.js";import{M as D,_ as E}from"./MenuList-bac784e3.js";const U={class:"tw-sticky tw-top-0 tw-p-2 tw-bg-waBlank tw-z-10"},N={class:"toggle rounded smallest"},P={key:0,class:"tw-p-2"},R=n("div",{class:"spinner"},null,-1),T=[R],j=["disabled","onClick"],q={key:0,class:"icon"},G=n("i",{class:"fas fa-spinner text-yellow tw-animate-spin"},null,-1),H=[G],J={class:"tw-truncate"},K={key:1,class:"small tw-px-4 tw-pb-4"},Y=z({__name:"DropdownIncludeSegment",props:{modelValue:{},contactIds:{}},emits:["update:modelValue"],setup(k,{emit:g}){const i=k,r=F(),{sharedSegment:S,mySegment:C,isFetching:$}=h(r);r.refetch();const{contact:f}=h(I()),o=y(!1),c=y(new Set),b=u(()=>C.value.filter(e=>e.type==="category").filter(e=>{var t;return!((t=f.value)!=null&&t.segments.map(s=>s.id).includes(e.id))})),V=u(()=>S.value.filter(e=>e.type==="category").filter(e=>{var t;return!((t=f.value)!=null&&t.segments.map(s=>s.id).includes(e.id))})),v=u(()=>o.value?V.value:b.value);async function B(e){Array.isArray(i.contactIds)&&(c.value.has(e)||(c.value.add(e),await r.includeContactsToSegment(e,i.contactIds).execute(),c.value.delete(e))),"modelValue"in i&&g("update:modelValue",e)}return(e,t)=>(a(),l(m,null,[n("div",U,[n("div",N,[n("span",{class:p({selected:!o.value}),onClick:t[0]||(t[0]=s=>o.value=!1)},d(e.$t("my",2)),3),n("span",{class:p({selected:o.value}),onClick:t[1]||(t[1]=s=>o.value=!0)},d(e.$t("shared",2)),3)])]),L($)?(a(),l("div",P,T)):(a(),l(m,{key:1},[v.value.length?(a(),_(D,{key:0},{default:w(()=>[(a(!0),l(m,null,x(v.value,s=>(a(),_(E,{key:s.id,class:p({selected:i.modelValue===s.id})},{default:w(()=>[n("a",{disabled:!s.is_editable,onClick:M(O=>B(s.id),["prevent"])},[c.value.has(s.id)?(a(),l("span",q,H)):(a(),_(A,{key:1,size:16,"fa-icon":s.icon,url:s.icon_path},null,8,["fa-icon","url"])),n("span",J,d(s.name),1)],8,j)]),_:2},1032,["class"]))),128))]),_:1})):(a(),l("div",K,d(e.$t("notFound")),1))],64))],64))}});export{Y as _};
