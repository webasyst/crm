import{Q as le,a as $,M as W,N as se,d as O,t as ie,o as u,b as x,w as S,e as M,c as h,l as Y,g as B,F as R,i as a,k as C,p as T,ab as G,P as j,z as I,m as z,h as Z,v as K,ac as X,ad as ce,a6 as te,ae as re,G as J,B as de,R as me,W as Q,n as D,f as q,H as pe,E as ve,A as _e,U as ye}from"./main-512a5cdd.js";import{W as fe}from"./WaDialog-079c5577.js";import{U as oe}from"./UserPic-2094e1a0.js";import{M as we,_ as he}from"./MenuList-bac784e3.js";import{D as ge}from"./DropDown-f7af334e.js";import{_ as Ve}from"./InputWithUsers.vue_vue_type_script_setup_true_lang-e9424cff.js";import{C as H}from"./CustomColumn-351ad915.js";import{_ as ke}from"./SortableList.vue_vue_type_script_setup_true_lang-0632140e.js";import{_ as $e}from"./InputWithContacts.vue_vue_type_script_setup_true_lang-3d5e0b02.js";import{F as ne}from"./FieldString-0d7a3814.js";import{F as L}from"./FieldSelect-46bbc3cb.js";import{f as P}from"./fields-536d59a3.js";import{s as ae}from"./dialog-e5c60953.js";const be=le("vaults",()=>{const f=$([]),{data:g,isFetching:l,error:w,isFinished:_,execute:i}=W("crm.vault.list").get().json();se(()=>{Array.isArray(g.value)&&(f.value=g.value)});function n(){_.value&&i()}return{vaults:f,isFetching:l,error:w,refetch:n}}),Ue=["disabled"],xe={key:0,class:"tw-flex tw-items-center tw-space-x-2"},Ce=["title"],Fe=a("div",null,[a("i",{class:"fas fa-caret-down"})],-1),Se={key:1},Ae=["onClick"],Me=["title"],Ee=O({__name:"SelectWithVaults",props:{modelValue:{}},emits:["update:modelValue"],setup(f,{emit:g}){const l=f,w=be(),{vaults:_,isFetching:i,error:n}=ie(w);return w.refetch(),l.modelValue||se(()=>{Array.isArray(_.value)&&g("update:modelValue",_.value[0])}),(b,k)=>(u(),x(ge,{disabled:!!(B(i)||B(n))},{body:S(({hide:y})=>[M(we,null,{default:S(()=>[(u(!0),h(R,null,Y(B(_),d=>(u(),x(he,{key:d.id},{default:S(()=>[a("a",{onClick:T(()=>{g("update:modelValue",d),y()},["prevent"])},[M(oe,{size:20,"fa-icon":"circle","icon-color":d.color},null,8,["icon-color"]),a("span",{class:"tw-truncate",title:d.name},C(d.name),9,Me)],8,Ae)]),_:2},1024))),128))]),_:2},1024)]),default:S(()=>[a("button",{class:"button light-gray",disabled:B(i)},[l.modelValue?(u(),h("div",xe,[(u(),x(oe,{key:l.modelValue.color,size:20,"fa-icon":"circle","icon-color":l.modelValue.color},null,8,["icon-color"])),a("span",{class:"tw-truncate",title:l.modelValue.name},C(l.modelValue.name),9,Ce),Fe])):(u(),h("div",Se," error "))],8,Ue)]),_:1},8,["disabled"]))}});function ue(f){let g=JSON.stringify(G(f));const l=$(!1);j(f,()=>{l.value=g!==JSON.stringify(G(f))},{deep:!0});function w(){setTimeout(()=>{g=JSON.stringify(G(f)),l.value=!1})}return{modified:l,reset:w}}const qe={class:"wa-radio"},Ne=["checked"],Be=a("span",null,null,-1),Te={class:"wa-radio"},De=["checked"],Re=a("span",null,null,-1),je={class:"wa-radio"},ze=["checked"],Oe=a("span",null,null,-1),We={class:"tw-ml-6 tw-mt-4"},Ie=O({__name:"FormContactUpdateScope",props:{contact:{}},setup(f,{expose:g}){var y,d,F,c;const l=f,w=$((y=l.contact)==null?void 0:y.vault),_=$(((d=l.contact)==null?void 0:d.owners)||[]),i=$((F=l.contact)!=null&&F.vault?"vault":(c=l.contact)!=null&&c.owners?"owners":"all"),n=I(()=>{var s;return i.value==="owners"?{owner_id:_.value.map(t=>t.id)}:i.value==="vault"?{vault_id:(s=w.value)==null?void 0:s.id}:{vault_id:0}}),{modified:b}=ue(n);g({modified:b,submit:k});function k(s){return W(`crm.contact.access.update?id=${s}`).post(n.value)}return(s,t)=>(u(),x(H,{space:"4"},{default:S(()=>[a("div",null,C(s.$t("scopeMessage")),1),M(H,{space:"2"},{default:S(()=>[a("div",null,[a("label",{onClick:t[0]||(t[0]=v=>i.value="all")},[a("span",qe,[a("input",{type:"radio",checked:i.value==="all"},null,8,Ne),Be]),z(" "+C(s.$t("allUsers")),1)])]),a("div",null,[a("label",{class:"tw-flex tw-flex-col tw-space-y-2 md:tw-space-y-0 md:tw-flex-row md:tw-space-x-2 md:tw-items-center",onClick:t[2]||(t[2]=v=>i.value="vault")},[a("div",null,[a("span",Te,[a("input",{type:"radio",checked:i.value==="vault"},null,8,De),Re]),z(" "+C(s.$t("onlyUsersWithVault")),1)]),M(Ee,{modelValue:w.value,"onUpdate:modelValue":t[1]||(t[1]=v=>w.value=v)},null,8,["modelValue"])])]),a("div",null,[a("label",{onClick:t[3]||(t[3]=v=>i.value="owners")},[a("span",je,[a("input",{type:"radio",checked:i.value==="owners"},null,8,ze),Oe]),z(" "+C(s.$t("onlyUsers")),1)]),Z(a("div",We,[M(Ve,{modelValue:_.value,"onUpdate:modelValue":t[4]||(t[4]=v=>_.value=v)},null,8,["modelValue"])],512),[[K,i.value==="owners"]])])]),_:1})]),_:1}))}}),Je={class:"tw-flex tw-space-x-2"},Pe=O({__name:"FieldBirthday",props:{modelValue:{},optionsForDaySelect:{},optionsForMonthSelect:{},errors:{}},emits:["update:modelValue"],setup(f,{emit:g}){const l=f,w=X({day:"",month:"",year:"",...Object.fromEntries(l.modelValue.map(i=>[i.field,i.value??""]))});j(w,i=>{g("update:modelValue",Object.entries(i).filter(n=>n[1]).map(n=>({field:n[0],value:n[1]})))});function _(i){if(l.errors)return l.errors.find(n=>n.field.split(".")[1]===i)}return(i,n)=>{var b,k,y;return u(),h("div",Je,[M(L,{modelValue:w.day,"onUpdate:modelValue":n[0]||(n[0]=d=>w.day=d),options:l.optionsForDaySelect,"error-message":(b=_("day"))==null?void 0:b.description,class:"tw-w-18 md:tw-w-20"},null,8,["modelValue","options","error-message"]),M(L,{modelValue:w.month,"onUpdate:modelValue":n[1]||(n[1]=d=>w.month=d),options:l.optionsForMonthSelect,"error-message":(k=_("month"))==null?void 0:k.description,class:"tw-w-auto md:tw-w-36"},null,8,["modelValue","options","error-message"]),M(ne,{modelValue:w.year,"onUpdate:modelValue":n[2]||(n[2]=d=>w.year=d),"error-message":(y=_("year"))==null?void 0:y.description,type:"number",class:"tw-w-20"},null,8,["modelValue","error-message"])])}}}),He=le("regions",()=>{const f=$({});function g(l){return l in f.value||(f.value[l]=W(`crm.region.list?country=${l}`).get().json()),f.value[l]}return{regions:f,getRegionByCode:g}}),Le=O({__name:"FieldAddress",props:{modelValue:{},fields:{},errors:{}},emits:["update:modelValue"],setup(f,{emit:g}){const l=f,{getRegionByCode:w}=He(),_=$(k()),i=$([]),{pause:n,resume:b}=ce(_,()=>g("update:modelValue",_.value.filter(c=>c.value).map(c=>({field:c.id,value:c.value}))),{deep:!0,immediate:!0});j(()=>l.modelValue,async()=>{n(),_.value=k(),await te(),b()}),j(()=>{var c;return(c=_.value.find(s=>s.id==="country"))==null?void 0:c.value},async c=>{if(i.value=[],c){const{data:s}=await w(c);s.value&&(i.value=s.value.sort((t,v)=>+v.is_favorite-+t.is_favorite))}},{immediate:!0}),j(()=>{var c;return(c=_.value.find(s=>s.id==="country"))==null?void 0:c.value},()=>{const c=_.value.find(s=>s.id==="region");c&&(c.value="")});function k(){return l.fields.map(c=>{var s;return{...c,value:((s=l.modelValue.find(t=>t.field===c.id))==null?void 0:s.value)??""}})}async function y(c){var s;if(await te(),c.el){const t=document.createElement("option");t.innerText="──────────",t.setAttribute("disabled","disabled"),(s=c.el.querySelector("select").querySelectorAll("option")[i.value.filter(v=>v.is_favorite).length||-1])==null||s.after(t)}}const d=re((c,s)=>s&&s.find(t=>t.field.split(".")[1]===c)),F=I(()=>c=>d(c,l.errors));return(c,s)=>(u(),x(H,{space:"2"},{default:S(()=>[(u(!0),h(R,null,Y(_.value,t=>{var v,E,N,m;return u(),h(R,{key:t.id},[t.id==="country"?(u(),x(L,{key:0,modelValue:t.value,"onUpdate:modelValue":r=>t.value=r,options:t.type==="select"?t.option_values:[],placeholder:t.name,"error-message":(v=F.value(t.id))==null?void 0:v.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"])):t.id==="region"?(u(),h(R,{key:1},[i.value.length?(u(),x(L,{key:i.value.length,modelValue:t.value,"onUpdate:modelValue":r=>t.value=r,options:i.value.map(r=>({id:r.code,value:r.name})),placeholder:t.name,"error-message":(E=F.value(t.id))==null?void 0:E.description,onVnodeMounted:s[0]||(s[0]=r=>y(r))},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"])):(u(),x(ne,{key:1,modelValue:t.value,"onUpdate:modelValue":r=>t.value=r,type:"text",placeholder:t.name,"error-message":(N=F.value(t.id))==null?void 0:N.description},null,8,["modelValue","onUpdate:modelValue","placeholder","error-message"]))],64)):(u(),x(J(B(P)[t.type]),{key:2,modelValue:t.value,"onUpdate:modelValue":r=>t.value=r,options:t.type==="select"||t.type==="radio"?t.option_values:[],placeholder:t.name,"error-message":(m=F.value(t.id))==null?void 0:m.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error-message"]))],64)}),128))]),_:1}))}}),Ge={key:0},Qe={class:"toggle rounded small"},Ze={key:1,class:"tw-text-center"},Ke=a("div",{class:"spinner custom-p-16 tw-mt-6"},null,-1),Xe=[Ke],Ye={key:2},et={key:0,class:"text-yellow"},tt={class:"tw-flex-auto"},ot={key:0,class:"handle tw-pt-1.5"},at=a("i",{class:"fas fa-grip-vertical gray"},null,-1),lt=[at],st={class:"tw-flex tw-flex-wrap tw-flex-auto tw-flex-col md:tw-flex-row tw-gap-2 tw-items-start"},rt={key:1,class:"tw-flex tw-space-x-2 tw-w-full md:tw-w-60 tw-flex-none"},nt=["onClick"],ut=a("i",{class:"fas fa-trash-alt"},null,-1),it=[ut],ct={class:"tw-w-full md:tw-w-60 tw-flex-none tw-flex tw-space-x-2"},dt={class:"tw-flex-auto"},mt=["onClick"],pt=a("i",{class:"fas fa-trash-alt"},null,-1),vt=[pt],_t={class:"tw-w-full md:tw-w-auto tw-flex tw-space-x-2 empty:tw-hidden"},yt={key:0,class:"tw-w-1/2 md:tw-w-28 tw-flex-none"},ft={key:1,class:"tw-w-1/2 md:tw-w-28"},wt=["onClick"],ht=a("i",{class:"fas fa-trash-alt"},null,-1),gt=[ht],Vt=["onClick"],kt=a("i",{class:"fas fa-plus"},null,-1),$t=O({__name:"FormContactUpdateInfo",props:{contact:{}},setup(f,{expose:g}){var N;const l=f,{t:w}=de(),_=me(),i=!!l.contact,n=$(((N=l.contact)==null?void 0:N.details.is_company)??!1),b=$(!1),k=$(null),y=$([]),d=X({company_contact_id:i&&l.contact.main.company_contact_id?l.contact.main.company_contact_id:"",is_company:I(()=>+n.value)}),F=X({person:null,company:null}),c={person:null,company:null};g({modified:I(()=>{var m;return(m=F[n.value?"company":"person"])==null?void 0:m.modified}),submit:v});const s=re(async m=>{const{data:r,error:e}=await W(`crm.field.list?scope=${m}`).get().json();return{fields:r.value,error:e.value}});j(n,async m=>{var o;b.value=!0;const{fields:r,error:e}=await s(m?"company":"person");if(b.value=!1,Array.isArray(r)){c[m?"person":"company"]=y.value.length?y.value:null;const U=c[m?"company":"person"];U?y.value=U:(t(r),F[m?"company":"person"]=ue(y),(o=F[m?"company":"person"])==null||o.reset())}else k.value=e,s.clear()},{immediate:!0});function t(m){if(y.value=m.map(r=>{var U;const e=(U=l.contact)==null?void 0:U.data.filter(V=>V.field===r.id);let o;if(r.type==="composite"){const V={value:[],...r.ext?{ext:r.ext[0].id}:{}};o={...r,error:[],dummy:{...V},value:Array.isArray(e)&&e.length?e.map(A=>({value:Array.isArray(A.value)?A.value:[],...r.ext?{ext:A.ext}:{}})):[{...V}]}}else{const V={value:"",...r.ext?{ext:r.ext[0].id}:{}};o={...r,error:[],dummy:{...V},value:Array.isArray(e)&&e.length?e.map(A=>({value:Array.isArray(A.value)?"":A.value,...r.ext?{ext:A.ext}:{}})):[{...V}]}}return{...o,...r.is_required?{validate:Q.object({value:Q.string().min(1,{message:w("validation.required")})}).array()}:{}}}),_.query.phone){const r=y.value.find(e=>e.id==="phone");r&&(r.value[0].value=_.query.phone.toString())}}function v(){let m;if(y.value.forEach(e=>{if(e.validate)try{e.validate.parse(e.value)}catch(o){if(o instanceof Q.ZodError){if(e.is_required&&o.errors[0]){const U=Array.isArray(e.value[0].value)?e.value[0].value[0].value:e.value[0].value;e.error=[{code:o.errors[0].code,description:o.errors[0].message,field:e.id,value:U}]}m=!0}}}),m)return ae(),null;const r=y.value.filter(e=>e.value.filter(o=>o.value&&(Array.isArray(o.value)?o.value.filter(U=>U).length:!0)).length).reduce((e,o)=>{const{value:U}=o;return e.push(...U.map(V=>({field:o.id,value:V.value,ext:V.ext}))),e},[]);return r.push(...Object.entries(d).filter(e=>e[1]!=="").map(e=>({field:e[0],value:e[1]}))),W(`crm.contact.${i?`update?id=${l.contact.id}`:"add"}`,{onFetchError(e){try{const o=JSON.parse(e.data);"error_fields"in o&&E(o.error_fields)}catch{}return e}})[i?"put":"post"](JSON.stringify(r))}function E(m){var r;for(const e of m)for(const o of e.field.split(", ")){const U=o.includes(".")?o.split(".")[0]:o,V=y.value.find(A=>A.id===U);V&&(V.type==="composite"?V.error=m:(r=V.error)==null||r.push(e))}ae()}return(m,r)=>(u(),x(H,{space:"4"},{default:S(()=>[i?q("",!0):(u(),h("div",Ge,[a("div",Qe,[a("span",{class:D({selected:!n.value}),onClick:r[0]||(r[0]=e=>n.value=!1)},C(m.$t("person")),3),a("span",{class:D({selected:n.value}),onClick:r[1]||(r[1]=e=>n.value=!0)},C(m.$t("company")),3)])])),b.value?(u(),h("div",Ze,Xe)):k.value?(u(),h("div",Ye,C(k.value),1)):(u(!0),h(R,{key:3},Y(y.value,e=>(u(),h("div",{key:e.id,class:"tw-flex tw-flex-col md:tw-flex-row tw-space-y-1 md:tw-space-y-0 md:tw-space-x-2"},[a("div",{class:D(["md:tw-flex-none md:tw-w-32 small gray tw-break-words",{"md:tw-pt-1.5":!["radio","checkbox"].includes(e.type)}])},[z(C(e.name),1),e.is_required?(u(),h("span",et,"*")):q("",!0)],2),a("div",tt,[M(ke,{list:e.value,"use-sort":!0,"min-length":2,onUpdate:o=>{e.value=o}},{default:S(({index:o})=>{var U,V,A,ee;return[a("div",{class:D(["tw-flex tw-space-x-2",o>0&&"bordered-top tw-pt-2"])},[e.value.length>1?(u(),h("div",ot,lt)):q("",!0),a("div",st,[e.id==="birthday"&&e.type==="composite"?(u(),x(Pe,{key:0,modelValue:e.value[o].value,"onUpdate:modelValue":p=>e.value[o].value=p,"options-for-day-select":((U=e.fields.find(p=>p.id==="day"&&p.type==="select"))==null?void 0:U.option_values)||[],"options-for-month-select":((V=e.fields.find(p=>p.id==="month"&&p.type==="select"))==null?void 0:V.option_values)||[],errors:e.error},null,8,["modelValue","onUpdate:modelValue","options-for-day-select","options-for-month-select","errors"])):e.id==="address"&&e.type==="composite"?(u(),h("div",rt,[M(Le,{modelValue:e.value[o].value,"onUpdate:modelValue":p=>e.value[o].value=p,fields:e.fields,errors:e.error},null,8,["modelValue","onUpdate:modelValue","fields","errors"]),o>0?(u(),h("a",{key:0,class:"md:tw-hidden !tw-mt-1.5",onClick:T(p=>e.value.splice(o,1),["prevent"])},it,8,nt)):q("",!0)])):(u(),h(R,{key:2},[a("div",ct,[a("div",dt,[e.id==="company"&&!n.value?(u(),x($e,{key:0,"is-multiple":!1,"text-value":e.value[o].value,"is-company":!0,"on-item-select":p=>{d.company_contact_id=p.id,e.value[o].value=p.name},onInput:p=>{d.company_contact_id="",e.value[o].value=p}},null,8,["text-value","on-item-select","onInput"])):(u(),x(J(B(P)[e.type]),{key:1,modelValue:e.value[o].value,"onUpdate:modelValue":p=>e.value[o].value=p,"error-message":(ee=(A=e.error)==null?void 0:A.find(p=>p.value===e.value[o].value))==null?void 0:ee.description,options:e.type==="select"||e.type==="radio"?e.option_values:void 0,fields:e.type==="composite"?e.fields:void 0,required:e.is_required,placeholder:o>0?`${e.name} ${o+1}`:void 0,vertical:e.type==="radio"?!0:void 0},null,8,["modelValue","onUpdate:modelValue","error-message","options","fields","required","placeholder","vertical"]))]),o>0?(u(),h("a",{key:0,class:"md:tw-hidden !tw-mt-1.5",onClick:T(p=>e.value.splice(o,1),["prevent"])},vt,8,mt)):q("",!0)]),a("div",_t,[e.ext?(u(),h("div",yt,[(u(),x(J(B(P).select),{modelValue:e.value[o].ext,"onUpdate:modelValue":p=>e.value[o].ext=p,required:!0,options:[...e.ext,{id:e.ext.filter(p=>p.id===e.value[o].ext).length?"":e.value[o].ext??"",value:m.$t("other")}]},null,8,["modelValue","onUpdate:modelValue","options"]))])):q("",!0),e.ext&&!e.ext.find(p=>p.id===e.value[o].ext)?(u(),h("div",ft,[(u(),x(J(B(P).string),{modelValue:e.value[o].ext,"onUpdate:modelValue":p=>e.value[o].ext=p},null,8,["modelValue","onUpdate:modelValue"]))])):q("",!0)])],64)),o>0?(u(),h("a",{key:3,class:"tw-hidden md:tw-block !tw-mt-1.5",onClick:T(()=>{e.value.splice(o,1)},["prevent"])},gt,8,wt)):q("",!0)])],2)]}),_:2},1032,["list","onUpdate"]),e.is_multi?(u(),h("a",{key:0,class:"button rounded outlined light-gray smaller !tw-mt-2",onClick:T(o=>e.value.push({...e.dummy}),["prevent"])},[kt,z(" "+C(m.$t("addMore")),1)],8,Vt)):q("",!0)])]))),128))]),_:1}))}}),bt=["disabled","onClick"],Ut={class:"text-red"},xt={class:"tabs bordered-bottom tw-sticky tw-left-0 tw-top-0 tw-bg-waBlank tw-z-10 !tw-mb-4"},zt=O({__name:"FormContactUpdate",props:{contact:{default:void 0},initialTab:{default:"info"}},emits:["close"],setup(f,{emit:g}){const l=f,w=pe(),_=ve(),i=!!l.contact,n=$(l.initialTab),b=$(null),k=$(null),y=$(!1),d=$(""),F=I(()=>{var s;return typeof d.value=="object"?d.value.message.replace("Error: ",""):(s=d.value)==null?void 0:s.replace("Error: ","")});async function c(){var t,v,E,N,m,r;y.value=!0,d.value="";let s;if((t=b.value)!=null&&t.modified){const e=await((v=b.value)==null?void 0:v.submit());if((E=e==null?void 0:e.response.value)!=null&&E.ok)s=e.data.value;else{y.value=!1,d.value=e==null?void 0:e.error.value;return}}if((N=k.value)!=null&&N.modified&&!d.value){const e=await((r=k.value)==null?void 0:r.submit(s||((m=l.contact)==null?void 0:m.id)));if(e!=null&&e.error.value){y.value=!1,d.value=e.error.value;return}}d.value||(s?w.push({name:"contact",params:{id:s}}):_e.webView?ye.emit("spa:navigateBack"):_.refetch()),g("close")}return(s,t)=>(u(),x(fe,{"vertical-stretch":!0,"use-cancel-as-button-label":!0,onClose:t[2]||(t[2]=v=>g("close"))},{header:S(()=>{var v;return[z(C(i?`${s.$t("editContact")} ${(v=l.contact)==null?void 0:v.main.name}`:s.$t("addContact")),1)]}),submit:S(()=>{var v,E;return[a("button",{class:"button",disabled:y.value||!((v=b.value)!=null&&v.modified||(E=k.value)!=null&&E.modified),onClick:T(c,["prevent"])},C(s.$t("save")),9,bt)]}),error:S(()=>[a("span",Ut,C(F.value),1)]),default:S(()=>[a("div",null,[a("ul",xt,[a("li",{class:D({selected:n.value==="info"})},[a("a",{onClick:t[0]||(t[0]=T(v=>n.value="info",["prevent"]))},C(s.$t("information")),1)],2),a("li",{class:D({selected:n.value==="scope"})},[a("a",{onClick:t[1]||(t[1]=T(v=>n.value="scope",["prevent"]))},C(s.$t("scope")),1)],2)]),Z(a("div",null,[M($t,{ref_key:"FormContactUpdateInfoRef",ref:b,contact:l.contact},null,8,["contact"])],512),[[K,n.value==="info"]]),Z(a("div",null,[M(Ie,{ref_key:"formContactUpdateScopeRef",ref:k,contact:l.contact},null,8,["contact"])],512),[[K,n.value==="scope"]])])]),_:1}))}});export{zt as _,be as u};
