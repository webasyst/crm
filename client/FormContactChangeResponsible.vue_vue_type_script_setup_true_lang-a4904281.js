import{d as C,z as N,a as _,A as l,o as b,b as h,w as r,m as v,k as o,i as m,p as $,c as k,f as V,e as w,W as S}from"./main-07d84941.js";import{_ as y}from"./InputWithUsers.vue_vue_type_script_setup_true_lang-d1c57d10.js";const B=["disabled","onClick"],A={key:0},M={class:"hint tw-mb-2"},F=C({__name:"FormContactChangeResponsible",props:{contactIds:{},currentResponsible:{},contactName:{}},emits:["close"],setup(R,{emit:i}){const e=R,f=N(),s=_(e.currentResponsible??null),a=l(()=>f.responsibleAssign(e.contactIds,s.value)),u=l(()=>e.currentResponsible?JSON.stringify(e.currentResponsible)===JSON.stringify(s.value):!1),c=l(()=>e.currentResponsible&&!s.value),p=l({get(){return s.value?[s.value]:[]},set(t){s.value=t[0]}});async function g(){await a.value.execute(),a.value.error.value||i("close")}return(t,n)=>(b(),h(S,{"use-cancel-as-button-label":!0,onClose:n[1]||(n[1]=d=>i("close"))},{header:r(()=>[v(o(t.$t(e.currentResponsible?"changeResponsible":"addResponsible")),1)]),submit:r(()=>[m("button",{disabled:u.value||a.value.isFetching.value||!c.value&&!s.value,onClick:$(g,["prevent"])},o(t.$t(c.value?"clearResponsible":"save")),9,B)]),error:r(()=>[v(o(a.value.error.value),1)]),default:r(()=>[e.contactName?(b(),k("p",A,o(`${t.$t("addResponsibleMessage")} ${e.contactName}`),1)):V("",!0),m("div",M,o(t.$t(s.value?u.value?"currentResponsible":"newResponsible":"selectResponsible")),1),w(y,{modelValue:p.value,"onUpdate:modelValue":n[0]||(n[0]=d=>p.value=d),"is-multiple":!1},null,8,["modelValue"])]),_:1}))}});export{F as _};
