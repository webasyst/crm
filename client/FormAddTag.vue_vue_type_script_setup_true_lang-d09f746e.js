import{d as w,a as m,o,b as d,w as s,m as p,k as n,i as l,p as f,e as v,n as b,c as g,f as y,F as T,l as V}from"./main-ec0df6a4.js";import{_ as B}from"./DropdownAddTags.vue_vue_type_script_setup_true_lang-87b6a8e2.js";import{C as N}from"./CustomColumn-959e6ffe.js";import{C as $,a as A}from"./ChipsList-64240846.js";import{W as F}from"./WaDialog-c7c19903.js";import{u as I}from"./tags-11dad524.js";const S=["disabled","onClick"],x={key:0,class:"hint"},L=["onClick"],D=l("i",{class:"fas fa-hashtag"},null,-1),W={class:"tw-flex-auto tw-truncate"},G=w({__name:"FormAddTag",props:{entityIds:{},entityType:{}},emits:["close"],setup(_,{emit:c}){const i=_,h=I(),t=m([]),a=m();async function C(){a.value=h.tagAssign(i.entityType,i.entityIds,t.value,!0),await a.value.execute(),a.value.error||c("close")}return(u,r)=>(o(),d(F,{"use-cancel-as-button-label":!0,onClose:r[1]||(r[1]=e=>c("close"))},{header:s(()=>[p(n(u.$t("addTags")),1)]),submit:s(()=>{var e;return[l("button",{disabled:!t.value.length||!!((e=a.value)!=null&&e.isFetching),onClick:f(C,["prevent"])},n(u.$t("save")),9,S)]}),error:s(()=>{var e;return[p(n((e=a.value)==null?void 0:e.error),1)]}),default:s(()=>[v(N,{space:"6"},{default:s(()=>[l("div",{class:b(["tw-p-4 tw-rounded-md tw-bg-waBackground",{"tw-pb-2":t.value.length}])},[t.value.length?y("",!0):(o(),g("div",x,n(u.$t("tagsToAssign")),1)),t.value.length?(o(),d($,{key:1},{default:s(()=>[(o(!0),g(T,null,V(t.value,e=>(o(),d(A,{key:e,class:"smaller"},{default:s(()=>[l("a",{onClick:f(()=>{t.value=t.value.filter(k=>k!==e)},["prevent"])},[D,l("span",W,n(e),1)],8,L)]),_:2},1024))),128))]),_:1})):y("",!0)],2),v(B,{modelValue:t.value,"onUpdate:modelValue":r[0]||(r[0]=e=>t.value=e),"entity-ids":i.entityIds,"entity-type":i.entityType},null,8,["modelValue","entity-ids","entity-type"])]),_:1})]),_:1}))}});export{G as _};
