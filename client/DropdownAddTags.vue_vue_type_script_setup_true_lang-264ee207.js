import{d as w,J as C,a as _,A as T,o as a,b as c,w as p,i as l,k as m,g as d,c as u,m as b,F as f,l as x,f as y,ah as A,p as F}from"./main-e63d6b61.js";import{C as S}from"./CustomColumn-72664405.js";import{C as B,a as L}from"./ChipsList-f1962714.js";import{a as I}from"./FieldCheckbox-c1b80b55.js";const D={class:"hint"},E={key:0},K=l("div",{class:"spinner"},null,-1),M=[K],$=["onClick"],z={key:0},J=l("i",{class:"fas fa-spinner tw-animate-spin"},null,-1),N=[J],U={key:1},j=l("i",{class:"fas fa-hashtag"},null,-1),q=[j],G={class:"tw-flex-auto tw-truncate"},H={class:"count tw-mt-0.5"},W=w({__name:"DropdownAddTags",props:{modelValue:{},entityType:{},entityIds:{}},emits:["update:modelValue"],setup(g,{emit:v}){const n=g,o=C();o.refetch();const i=_(""),r=_(new Set),V=T(()=>o.tags.filter(e=>!("tags"in n.modelValue?n.modelValue.tags:n.modelValue).map(s=>typeof s=="string"?s:s.name).includes(e.name)).sort((e,s)=>s.count-e.count).slice(0,10));function k(e){h(i.value),i.value="",e.target.blur()}async function h(e){Array.isArray(n.modelValue)?v("update:modelValue",[...n.modelValue,e]):(r.value.add(e),await o.tagAssign(n.entityType,n.entityIds,[e]).execute(),r.value.delete(e))}return(e,s)=>(a(),c(S,{space:"2"},{default:p(()=>[l("div",D,m(e.$t("popularTags")),1),d(o).isFetching?(a(),u("div",E,M)):d(o).error?(a(),u(f,{key:1},[b(m(d(o).error),1)],64)):d(o).tags.length?(a(),c(B,{key:2},{default:p(()=>[(a(!0),u(f,null,x(V.value,t=>(a(),c(L,{key:t.id,class:"smaller"},{default:p(()=>[l("a",{onClick:F(()=>{!r.value.has(t.name)&&h(t.name)},["prevent"])},[r.value.has(t.name)?(a(),u("span",z,N)):(a(),u("span",U,q)),l("span",G,m(t.name),1),l("span",H,m(t.count),1)],8,$)]),_:2},1024))),128))]),_:1})):y("",!0),d(o).isFetching?y("",!0):(a(),c(I,{key:3,modelValue:i.value,"onUpdate:modelValue":s[0]||(s[0]=t=>i.value=t),modelModifiers:{trim:!0},disabled:r.value.size,type:"text",placeholder:e.$t("createNewTag"),onKeyup:s[1]||(s[1]=A(t=>{i.value.length&&k(t)},["enter"]))},null,8,["modelValue","disabled","placeholder"]))]),_:1}))}});export{W as _};