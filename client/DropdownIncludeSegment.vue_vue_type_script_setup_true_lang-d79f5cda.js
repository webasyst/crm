import{d as F,C as I,t as h,a8 as L,a as y,z as u,o as a,c as o,i as n,n as p,k as d,g as b,F as m,b as f,w,l as x,p as M}from"./main-ec0df6a4.js";import{U as A}from"./UserPic-87c13a4a.js";import{M as D,_ as U}from"./MenuList-466c4134.js";const E={class:"tw-sticky tw-top-0 tw-p-2 tw-bg-waBlank tw-z-10"},N={class:"toggle rounded smallest"},P={key:0,class:"tw-p-2"},R=n("div",{class:"spinner"},null,-1),T=[R],j=["onClick"],q={key:0,class:"icon"},G=n("i",{class:"fas fa-spinner text-yellow tw-animate-spin"},null,-1),H=[G],J={class:"tw-truncate"},K={key:1,class:"small tw-px-4 tw-pb-4"},Y=F({__name:"DropdownIncludeSegment",props:{modelValue:{},contactIds:{}},emits:["update:modelValue"],setup(k,{emit:g}){const c=k,r=I(),{sharedSegment:S,mySegment:C,isFetching:$}=h(r);r.refetch();const{contact:_}=h(L()),l=y(!1),i=y(new Set),V=u(()=>C.value.filter(e=>e.type==="category").filter(e=>{var s;return!((s=_.value)!=null&&s.segments.map(t=>t.id).includes(e.id))})),B=u(()=>S.value.filter(e=>e.type==="category").filter(e=>{var s;return!((s=_.value)!=null&&s.segments.map(t=>t.id).includes(e.id))})),v=u(()=>l.value?B.value:V.value);async function z(e){Array.isArray(c.contactIds)&&(i.value.has(e)||(i.value.add(e),await r.includeContactsToSegment(e,c.contactIds).execute(),i.value.delete(e))),"modelValue"in c&&g("update:modelValue",e)}return(e,s)=>(a(),o(m,null,[n("div",E,[n("div",N,[n("span",{class:p({selected:!l.value}),onClick:s[0]||(s[0]=t=>l.value=!1)},d(e.$t("my",2)),3),n("span",{class:p({selected:l.value}),onClick:s[1]||(s[1]=t=>l.value=!0)},d(e.$t("shared",2)),3)])]),b($)?(a(),o("div",P,T)):(a(),o(m,{key:1},[v.value.length?(a(),f(D,{key:0},{default:w(()=>[(a(!0),o(m,null,x(v.value,t=>(a(),f(U,{key:t.id,class:p({selected:c.modelValue===t.id})},{default:w(()=>[n("a",{onClick:M(O=>z(t.id),["prevent"])},[i.value.has(t.id)?(a(),o("span",q,H)):(a(),f(A,{key:1,size:16,"fa-icon":t.icon,url:t.icon_path},null,8,["fa-icon","url"])),n("span",J,d(t.name),1)],8,j)]),_:2},1032,["class"]))),128))]),_:1})):(a(),o("div",K,d(e.$t("notFound")),1))],64))],64))}});export{Y as _};