import{U as p}from"./UserPic-6b99ec27.js";import{_ as h}from"./InputWithDropDown.vue_vue_type_script_setup_true_lang-71ea280f.js";import{Z as v,a as _,U as w,V as x,d as V,t as U,o as B,b as I,w as d,i as a,e as m,k as f,B as S}from"./main-07d84941.js";const g=v("users",()=>{const l=_([]),{data:o,isFetching:i,error:n,isFinished:r,execute:s}=w("crm.user.list").get().json();x(()=>{Array.isArray(o.value)&&(l.value=o.value)});function u(){r.value&&s()}return{users:l,isFetching:i,error:n,refetch:u}}),y={class:"icon size-20"},z=["title"],C={class:"tw-flex tw-items-center tw-space-x-2"},F={class:"icon size-20"},k=["title"],N=V({__name:"InputWithUsers",props:{modelValue:{},isMultiple:{type:Boolean,default:!0}},emits:["update:modelValue"],setup(l,{emit:o}){const i=l,n=g(),{users:r}=U(n);n.refetch();const s=_([]);function u(c){s.value=c.length?r.value.filter(e=>e.name.toLowerCase().includes(c.toLowerCase())):r.value;const t=s.value.findIndex(e=>e.id===S.user.id);if(t>-1){const e=s.value.splice(t,1)[0];s.value.splice(0,0,e)}}return(c,t)=>(B(),I(h,{items:s.value,value:i.modelValue,"is-multiple":i.isMultiple,placeholder:c.$t("search"),onInput:u,onFocus:u,onUpdate:t[0]||(t[0]=e=>{o("update:modelValue",e)})},{item:d(({item:e})=>[a("div",y,[m(p,{url:e.userpic,size:20},null,8,["url"])]),a("span",{class:"tw-truncate",title:e.name},f(e.name),9,z)]),listItem:d(({item:e})=>[a("div",C,[a("div",F,[m(p,{url:e.userpic,size:20},null,8,["url"])]),a("span",{class:"tw-truncate small",title:e.name},f(e.name),9,k)])]),_:1},8,["items","value","is-multiple","placeholder"]))}});export{N as _};
