import{aN as J,a as b,R as q,d as X,aO as P,o as s,c as p,e as R,$ as se,X as W,aP as le,ae as H,aQ as ee,A as L,b as V,w as Q,F as C,l as te,P as N,g as T,H as re,a0 as ne,a4 as z,i as v,n as j,k as M,f as S,r as G,m as K,p as D}from"./main-e63d6b61.js";import{_ as ue}from"./SortableList.vue_vue_type_script_setup_true_lang-a7709f9a.js";import{_ as ie}from"./InputWithContacts.vue_vue_type_script_setup_true_lang-51f96cc1.js";import{C as ae}from"./CustomColumn-72664405.js";import{a as oe}from"./FieldCheckbox-c1b80b55.js";import{F as I}from"./FieldSelect-d021e2ab.js";import{f as O}from"./fields-d5743e8f.js";function ce(d){let h=JSON.stringify(J(d));const r=b(!1);q(d,()=>{r.value=h!==JSON.stringify(J(d))},{deep:!0});function g(){setTimeout(()=>{h=JSON.stringify(J(d)),r.value=!1})}return{modified:r,reset:g}}const de={class:"tw-flex tw-space-x-2"},me=X({__name:"FieldBirthday",props:{modelValue:{},optionsForDaySelect:{},optionsForMonthSelect:{},errors:{}},emits:["update:modelValue"],setup(d,{emit:h}){const r=d,g=P({day:"",month:"",year:"",...Object.fromEntries(r.modelValue.map(m=>[m.field,m.value??""]))});q(g,m=>{h("update:modelValue",Object.entries(m).filter(i=>i[1]).map(i=>({field:i[0],value:i[1]})))});function f(m){if(r.errors)return r.errors.find(i=>i.field.split(".")[1]===m)}return(m,i)=>{var $,F,_;return s(),p("div",de,[R(I,{modelValue:g.day,"onUpdate:modelValue":i[0]||(i[0]=k=>g.day=k),options:r.optionsForDaySelect,"error-message":($=f("day"))==null?void 0:$.description,class:"tw-w-18 md:tw-w-20"},null,8,["modelValue","options","error-message"]),R(I,{modelValue:g.month,"onUpdate:modelValue":i[1]||(i[1]=k=>g.month=k),options:r.optionsForMonthSelect,"error-message":(F=f("month"))==null?void 0:F.description,class:"tw-w-auto md:tw-w-36"},null,8,["modelValue","options","error-message"]),R(oe,{modelValue:g.year,"onUpdate:modelValue":i[2]||(i[2]=k=>g.year=k),"error-message":(_=f("year"))==null?void 0:_.description,type:"number",class:"tw-w-20"},null,8,["modelValue","error-message"])])}}}),pe=se("regions",()=>{const d=b({});function h(r){return r in d.value||(d.value[r]=W(`crm.region.list?country=${r}`).get().json()),d.value[r]}return{regions:d,getRegionByCode:h}}),ve=X({__name:"FieldAddress",props:{modelValue:{},fields:{},errors:{}},emits:["update:modelValue"],setup(d,{emit:h}){const r=d,{getRegionByCode:g}=pe(),f=b(F()),m=b([]),{pause:i,resume:$}=le(f,()=>h("update:modelValue",f.value.filter(l=>l.value).map(l=>({field:l.id,value:l.value}))),{deep:!0,immediate:!0});q(()=>r.modelValue,async()=>{i(),f.value=F(),await H(),$()}),q(()=>{var l;return(l=f.value.find(c=>c.id==="country"))==null?void 0:l.value},async l=>{if(m.value=[],l){const{data:c}=await g(l);c.value&&(m.value=c.value.sort((a,A)=>+A.is_favorite-+a.is_favorite))}},{immediate:!0}),q(()=>{var l;return(l=f.value.find(c=>c.id==="country"))==null?void 0:l.value},()=>{const l=f.value.find(c=>c.id==="region");l&&(l.value="")});function F(){return r.fields.map(l=>{var c;return{...l,value:((c=r.modelValue.find(a=>a.field===l.id))==null?void 0:c.value)??""}})}async function _(l){var c;if(await H(),l.el){const a=document.createElement("option");a.innerText="──────────",a.setAttribute("disabled","disabled"),(c=l.el.querySelector("select").querySelectorAll("option")[m.value.filter(A=>A.is_favorite).length||-1])==null||c.after(a)}}const k=ee((l,c)=>c&&c.find(a=>a.field.split(".")[1]===l)),U=L(()=>l=>k(l,r.errors));return(l,c)=>(s(),V(ae,{space:"2"},{default:Q(()=>[(s(!0),p(C,null,te(f.value,a=>{var A,B,E,n,o;return s(),p(C,{key:a.id},[a.id==="country"?(s(),V(I,{key:0,modelValue:a.value,"onUpdate:modelValue":e=>a.value=e,options:a.type==="select"?a.option_values:[],placeholder:a.name,"error-message":(A=U.value(a.id))==null?void 0:A.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"])):a.id==="region"?(s(),p(C,{key:1},[m.value.length?(s(),V(I,{key:m.value.length,modelValue:a.value,"onUpdate:modelValue":e=>a.value=e,options:m.value.map(e=>({id:e.code,value:e.name})),placeholder:a.name,"error-message":(B=U.value(a.id))==null?void 0:B.description,onVnodeMounted:c[0]||(c[0]=e=>_(e))},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"])):(s(),V(oe,{key:1,modelValue:a.value,"onUpdate:modelValue":e=>a.value=e,type:"text",placeholder:a.name,"error-message":(E=U.value(a.id))==null?void 0:E.description},null,8,["modelValue","onUpdate:modelValue","placeholder","error-message"]))],64)):a.type==="select"||a.type==="radio"?(s(),V(N(T(O)[a.type]),{key:2,modelValue:a.value,"onUpdate:modelValue":e=>a.value=e,options:a.option_values,vertical:!0,placeholder:a.name,"error-message":(n=U.value(a.id))==null?void 0:n.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"])):(s(),V(N(T(O)[a.type]),{key:3,modelValue:a.value,"onUpdate:modelValue":e=>a.value=e,placeholder:a.name,"error-message":(o=U.value(a.id))==null?void 0:o.description},null,8,["modelValue","onUpdate:modelValue","placeholder","error-message"]))],64)}),128))]),_:1}))}});async function Y(){var r;await H();const d=document.querySelector(".dialog-content"),h=d==null?void 0:d.querySelector(".state-error");d&&h&&(d.scrollTop=(h.offsetTop||((r=h.offsetParent)==null?void 0:r.offsetTop)||0)-200)}const ye={key:0},we={class:"toggle rounded small"},_e={key:1,class:"tw-text-center"},he=v("div",{class:"spinner custom-p-16 tw-mt-6"},null,-1),fe=[he],ge={key:2},Ve={key:0,class:"tw-flex tw-flex-col md:tw-flex-row tw-space-y-1 md:tw-space-y-0 md:tw-space-x-2"},xe={class:"md:tw-flex-none md:tw-w-32 small gray tw-break-words md:tw-pt-1.5"},ke={class:"tw-flex-auto"},Fe={class:"tw-w-full md:tw-w-60 tw-flex-none tw-flex tw-space-x-2"},Ue={class:"tw-flex-auto"},Se={key:0,class:"text-yellow"},$e={class:"tw-flex-auto"},Ae={key:0,class:"handle tw-pt-1.5"},be=v("i",{class:"fas fa-grip-vertical gray"},null,-1),qe=[be],Ce={class:"tw-flex tw-flex-wrap tw-flex-auto tw-flex-col md:tw-flex-row tw-gap-2 tw-items-start"},Ee={class:"tw-w-full md:tw-w-60 tw-flex-none tw-flex tw-space-x-2"},Me={class:"tw-flex-auto"},Ne=["onClick"],Te=v("i",{class:"fas fa-trash-alt"},null,-1),Oe=[Te],Be={class:"tw-w-full md:tw-w-auto tw-flex tw-space-x-2 empty:tw-hidden"},je={key:0,class:"tw-w-1/2 md:tw-w-28 tw-flex-none"},Re={key:1,class:"tw-w-1/2 md:tw-w-28 tw-flex-none"},Ie=["onClick"],Je=v("i",{class:"fas fa-trash-alt"},null,-1),ze=[Je],De=["onClick"],Pe=v("i",{class:"fas fa-plus"},null,-1),Ke=X({__name:"FormContactUpdateInfo",props:{contact:{}},setup(d,{expose:h}){var E;const r=d,{t:g}=re(),f=ne(),m=!!r.contact,i=b(((E=r.contact)==null?void 0:E.details.is_company)??!1),$=b(!1),F=b(null),_=b([]),k=P({company_contact_id:m&&r.contact.main.company_contact_id?r.contact.main.company_contact_id:"",is_company:L(()=>+i.value)}),U=P({person:null,company:null}),l={person:null,company:null};h({modified:L(()=>{var n;return(n=U[i.value?"company":"person"])==null?void 0:n.modified}),submit:A});const c=ee(async n=>{const{data:o,error:e}=await W(`crm.field.list?scope=${n}`).get().json();return{fields:o.value,error:e.value}});q(i,async n=>{var t;$.value=!0;const{fields:o,error:e}=await c(n?"company":"person");if($.value=!1,Array.isArray(o)){l[n?"person":"company"]=_.value.length?_.value:null;const w=l[n?"company":"person"];w?_.value=w:(a(o),U[n?"company":"person"]=ce(_),(t=U[n?"company":"person"])==null||t.reset())}else F.value=e,c.clear()},{immediate:!0});function a(n){if(_.value=n.map(o=>{var w;const e=(w=r.contact)==null?void 0:w.data.filter(y=>y.field===o.id);let t;if(o.type==="composite"){const y={value:[],...o.ext?{ext:o.id==="address"?"":o.ext[0].id}:{}};t={...o,error:[],dummy:{...y},value:Array.isArray(e)&&e.length?e.map(x=>({value:Array.isArray(x.value)?x.value:[],...o.ext?{ext:x.ext}:{}})):[{...y}]}}else{const y={value:"",...o.ext?{ext:o.ext[0].id}:{}};t={...o,error:[],dummy:{...y},value:Array.isArray(e)&&e.length?e.map(x=>({value:Array.isArray(x.value)?"":x.value,...o.ext?{ext:x.ext}:{}})):[{...y}]}}return{...t,...o.is_required?{validate:z.object({value:z.string().min(1,{message:g("validation.required")})}).array()}:{}}}),f.query.phone){const o=_.value.find(e=>e.id==="phone");o&&(o.value[0].value=f.query.phone.toString())}}function A(){let n;if(_.value.forEach(e=>{if(["name","company","firstname","middlename","lastname"].includes(e.id)&&(e.value[0].value=String(e.value[0].value).trim()),e.validate)try{e.validate.parse(e.value)}catch(t){if(t instanceof z.ZodError){if(e.is_required&&t.errors[0]){const w=Array.isArray(e.value[0].value)?e.value[0].value[0].value:e.value[0].value;e.error=[{code:t.errors[0].code,description:t.errors[0].message,field:e.id,value:w}]}n=!0}}}),n)return setTimeout(()=>Y()),null;const o=_.value.filter(e=>e.value.filter(t=>t.value&&(Array.isArray(t.value)?t.value.filter(w=>w).length:!0)).length).reduce((e,t)=>{const{value:w}=t;return e.push(...w.map(y=>({field:t.id,value:y.value,ext:y.ext}))),e},[]);return o.push(...Object.entries(k).filter(e=>e[1]!=="").map(e=>({field:e[0],value:e[1]}))),W(`crm.contact.${m?`update?id=${r.contact.id}`:"add"}`,{onFetchError(e){try{const t=JSON.parse(e.data);"error_fields"in t&&B(t.error_fields)}catch{}return e}})[m?"put":"post"](JSON.stringify(o))}function B(n){var o;for(const e of n)for(const t of e.field.split(", ")){const w=t.includes(".")?t.split(".")[0]:t,y=_.value.find(x=>x.id===w);y&&(y.type==="composite"?y.error=n:(o=y.error)==null||o.push(e))}Y()}return(n,o)=>(s(),V(ae,{space:"4"},{default:Q(()=>[m?S("",!0):(s(),p("div",ye,[v("div",we,[v("span",{class:j({selected:!i.value}),onClick:o[0]||(o[0]=e=>i.value=!1)},M(n.$t("person")),3),v("span",{class:j({selected:i.value}),onClick:o[1]||(o[1]=e=>i.value=!0)},M(n.$t("company")),3)])])),$.value?(s(),p("div",_e,fe)):F.value?(s(),p("div",ge,M(F.value),1)):(s(),p(C,{key:3},[n.$slots.firstFieldValue?(s(),p("div",Ve,[v("div",xe,[G(n.$slots,"firstFieldTitle")]),v("div",ke,[v("div",Fe,[v("div",Ue,[G(n.$slots,"firstFieldValue")])])])])):S("",!0),(s(!0),p(C,null,te(_.value,e=>(s(),p("div",{key:e.id,class:"tw-flex tw-flex-col md:tw-flex-row tw-space-y-1 md:tw-space-y-0 md:tw-space-x-2"},[v("div",{class:j(["md:tw-flex-none md:tw-w-32 small gray tw-break-words",{"md:tw-pt-1.5":!["radio","checkbox"].includes(e.type)}])},[K(M(e.name),1),e.is_required?(s(),p("span",Se,"*")):S("",!0)],2),v("div",$e,[R(ue,{list:e.value,"use-sort":!0,"min-length":2,onUpdate:t=>{e.value=t}},{default:Q(({index:t})=>{var w,y,x,Z;return[v("div",{class:j(["tw-flex tw-space-x-2",t>0&&"bordered-top tw-pt-2"])},[e.value.length>1?(s(),p("div",Ae,qe)):S("",!0),v("div",Ce,[e.id==="birthday"&&e.type==="composite"?(s(),V(me,{key:0,modelValue:e.value[t].value,"onUpdate:modelValue":u=>e.value[t].value=u,"options-for-day-select":((w=e.fields.find(u=>u.id==="day"&&u.type==="select"))==null?void 0:w.option_values)||[],"options-for-month-select":((y=e.fields.find(u=>u.id==="month"&&u.type==="select"))==null?void 0:y.option_values)||[],errors:e.error},null,8,["modelValue","onUpdate:modelValue","options-for-day-select","options-for-month-select","errors"])):(s(),p(C,{key:1},[v("div",Ee,[v("div",Me,[e.id==="company"&&!i.value?(s(),V(ie,{key:0,"is-multiple":!1,"text-value":e.value[t].value,"is-company":!0,"on-item-select":u=>{k.company_contact_id=u.id,e.value[t].value=u.name},onInput:u=>{k.company_contact_id="",e.value[t].value=u}},null,8,["text-value","on-item-select","onInput"])):e.id==="address"&&e.type==="composite"?(s(),V(ve,{key:1,modelValue:e.value[t].value,"onUpdate:modelValue":u=>e.value[t].value=u,fields:e.fields,errors:e.error},null,8,["modelValue","onUpdate:modelValue","fields","errors"])):(s(),V(N(T(O)[e.type]),{key:2,modelValue:e.value[t].value,"onUpdate:modelValue":u=>e.value[t].value=u,"error-message":(Z=(x=e.error)==null?void 0:x.find(u=>u.value===e.value[t].value))==null?void 0:Z.description,options:e.type==="select"||e.type==="radio"?e.option_values:void 0,fields:e.type==="composite"?e.fields:void 0,required:e.is_required,placeholder:t>0?`${e.name} ${t+1}`:void 0,vertical:e.type==="radio"?!0:void 0},null,8,["modelValue","onUpdate:modelValue","error-message","options","fields","required","placeholder","vertical"]))]),t>0?(s(),p("a",{key:0,class:"md:tw-hidden !tw-mt-1.5",onClick:D(u=>e.value.splice(t,1),["prevent"])},Oe,8,Ne)):S("",!0)]),v("div",Be,[e.ext?(s(),p("div",je,[(s(),V(N(T(O).select),{modelValue:e.value[t].ext,"onUpdate:modelValue":u=>e.value[t].ext=u,required:!0,options:[...e.ext,{id:e.ext.filter(u=>u.id===e.value[t].ext).length?"":e.value[t].ext??"",value:n.$t("other")}]},null,8,["modelValue","onUpdate:modelValue","options"]))])):S("",!0),Array.isArray(e.ext)&&!e.ext.find(u=>u.id===e.value[t].ext)?(s(),p("div",Re,[(s(),V(N(T(O).string),{modelValue:e.value[t].ext,"onUpdate:modelValue":u=>e.value[t].ext=u},null,8,["modelValue","onUpdate:modelValue"]))])):S("",!0)])],64)),t>0?(s(),p("a",{key:2,class:"tw-hidden md:tw-block !tw-mt-1.5",onClick:D(()=>{e.value.splice(t,1)},["prevent"])},ze,8,Ie)):S("",!0)])],2)]}),_:2},1032,["list","onUpdate"]),e.is_multi?(s(),p("a",{key:0,class:"button rounded outlined light-gray smaller !tw-mt-2",onClick:D(t=>e.value.push({...e.dummy}),["prevent"])},[Pe,K(" "+M(n.$t("addMore")),1)],8,De)):S("",!0)])]))),128))],64))]),_:3}))}});export{Ke as _,Y as s,ce as u};
