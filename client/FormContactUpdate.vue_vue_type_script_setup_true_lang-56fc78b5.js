import{O as oe,a as $,J as j,L as ae,d as R,t as ne,o as i,b as x,w as S,e as A,c as w,l as X,g as q,F as O,i as l,k as C,p as N,a9 as H,N as z,z as J,m as D,h as G,v as K,aa as Q,ab as ue,a3 as Y,ac as le,D as I,B as ie,P as ce,U as Z,n as T,f as M,E as de,a8 as me,A as pe,S as ve}from"./main-ec0df6a4.js";import{W as ye}from"./WaDialog-c7c19903.js";import{U as ee}from"./UserPic-87c13a4a.js";import{M as _e,_ as fe}from"./MenuList-466c4134.js";import{D as we}from"./DropDown-5d25a522.js";import{_ as he}from"./InputWithUsers.vue_vue_type_script_setup_true_lang-b6c3d351.js";import{C as L}from"./CustomColumn-959e6ffe.js";import{_ as Ve}from"./SortableList.vue_vue_type_script_setup_true_lang-79d2c918.js";import{_ as ge}from"./InputWithContacts.vue_vue_type_script_setup_true_lang-86c741fd.js";import{F as se}from"./FieldString-0827de0f.js";import{F as P}from"./FieldSelect-df2e3574.js";import{f as W}from"./fields-5ca407fb.js";import{s as te}from"./dialog-efb0af25.js";const ke=oe("vaults",()=>{const _=$([]),{data:h,isFetching:s,error:f,isFinished:m,execute:c}=j("crm.vault.list").get().json();ae(()=>{Array.isArray(h.value)&&(_.value=h.value)});function u(){m.value&&c()}return{vaults:_,isFetching:s,error:f,refetch:u}}),$e=["disabled"],be={key:0,class:"tw-flex tw-items-center tw-space-x-2"},Ue=["title"],xe=l("div",null,[l("i",{class:"fas fa-caret-down"})],-1),Ce={key:1},Fe=["onClick"],Se=["title"],Ae=R({__name:"SelectWithVaults",props:{modelValue:{}},emits:["update:modelValue"],setup(_,{emit:h}){const s=_,f=ke(),{vaults:m,isFetching:c,error:u}=ne(f);return f.refetch(),s.modelValue||ae(()=>{Array.isArray(m.value)&&h("update:modelValue",m.value[0])}),(b,g)=>(i(),x(we,{disabled:!!(q(c)||q(u))},{body:S(({hide:y})=>[A(_e,null,{default:S(()=>[(i(!0),w(O,null,X(q(m),v=>(i(),x(fe,{key:v.id},{default:S(()=>[l("a",{onClick:N(()=>{h("update:modelValue",v),y()},["prevent"])},[A(ee,{size:20,"fa-icon":"circle","icon-color":v.color},null,8,["icon-color"]),l("span",{class:"tw-truncate",title:v.name},C(v.name),9,Se)],8,Fe)]),_:2},1024))),128))]),_:2},1024)]),default:S(()=>[l("button",{class:"button light-gray",disabled:q(c)},[s.modelValue?(i(),w("div",be,[(i(),x(ee,{key:s.modelValue.color,size:20,"fa-icon":"circle","icon-color":s.modelValue.color},null,8,["icon-color"])),l("span",{class:"tw-truncate",title:s.modelValue.name},C(s.modelValue.name),9,Ue),xe])):(i(),w("div",Ce," error "))],8,$e)]),_:1},8,["disabled"]))}});function re(_){let h=JSON.stringify(H(_));const s=$(!1);z(_,()=>{s.value=h!==JSON.stringify(H(_))},{deep:!0});function f(){setTimeout(()=>{h=JSON.stringify(H(_)),s.value=!1})}return{modified:s,reset:f}}const Me={class:"wa-radio"},Ee=["checked"],qe=l("span",null,null,-1),Ne={class:"wa-radio"},Be=["checked"],De=l("span",null,null,-1),Te={class:"wa-radio"},Oe=["checked"],ze=l("span",null,null,-1),Re={class:"tw-ml-6 tw-mt-4"},je=R({__name:"FormContactUpdateScope",props:{contact:{}},setup(_,{expose:h}){var y,v,k,r;const s=_,f=$((y=s.contact)==null?void 0:y.vault),m=$(((v=s.contact)==null?void 0:v.owners)||[]),c=$((k=s.contact)!=null&&k.vault?"vault":(r=s.contact)!=null&&r.owners?"owners":"all"),u=J(()=>{var n;return c.value==="owners"?{owner_id:m.value.map(t=>t.id)}:c.value==="vault"?{vault_id:(n=f.value)==null?void 0:n.id}:{vault_id:0}}),{modified:b}=re(u);h({modified:b,submit:g});function g(n){return j(`crm.contact.access.update?id=${n}`).post(u.value)}return(n,t)=>(i(),x(L,{space:"4"},{default:S(()=>[l("div",null,C(n.$t("scopeMessage")),1),A(L,{space:"2"},{default:S(()=>[l("div",null,[l("label",{onClick:t[0]||(t[0]=V=>c.value="all")},[l("span",Me,[l("input",{type:"radio",checked:c.value==="all"},null,8,Ee),qe]),D(" "+C(n.$t("allUsers")),1)])]),l("div",null,[l("label",{class:"tw-flex tw-flex-col tw-space-y-2 md:tw-space-y-0 md:tw-flex-row md:tw-space-x-2 md:tw-items-center",onClick:t[2]||(t[2]=V=>c.value="vault")},[l("div",null,[l("span",Ne,[l("input",{type:"radio",checked:c.value==="vault"},null,8,Be),De]),D(" "+C(n.$t("onlyUsersWithVault")),1)]),A(Ae,{modelValue:f.value,"onUpdate:modelValue":t[1]||(t[1]=V=>f.value=V)},null,8,["modelValue"])])]),l("div",null,[l("label",{onClick:t[3]||(t[3]=V=>c.value="owners")},[l("span",Te,[l("input",{type:"radio",checked:c.value==="owners"},null,8,Oe),ze]),D(" "+C(n.$t("onlyUsers")),1)]),G(l("div",Re,[A(he,{modelValue:m.value,"onUpdate:modelValue":t[4]||(t[4]=V=>m.value=V)},null,8,["modelValue"])],512),[[K,c.value==="owners"]])])]),_:1})]),_:1}))}}),Ie={class:"tw-flex tw-space-x-2"},We=R({__name:"FieldBirthday",props:{modelValue:{},optionsForDaySelect:{},optionsForMonthSelect:{},errors:{}},emits:["update:modelValue"],setup(_,{emit:h}){const s=_,f=Q({day:"",month:"",year:"",...Object.fromEntries(s.modelValue.map(c=>[c.field,c.value??""]))});z(f,c=>{h("update:modelValue",Object.entries(c).filter(u=>u[1]).map(u=>({field:u[0],value:u[1]})))});function m(c){if(s.errors)return s.errors.find(u=>u.field.split(".")[1]===c)}return(c,u)=>{var b,g,y;return i(),w("div",Ie,[A(P,{modelValue:f.day,"onUpdate:modelValue":u[0]||(u[0]=v=>f.day=v),options:s.optionsForDaySelect,error:!!m("day"),"error-message":(b=m("day"))==null?void 0:b.description,class:"tw-w-18 md:tw-w-20"},null,8,["modelValue","options","error","error-message"]),A(P,{modelValue:f.month,"onUpdate:modelValue":u[1]||(u[1]=v=>f.month=v),options:s.optionsForMonthSelect,error:!!m("month"),"error-message":(g=m("month"))==null?void 0:g.description,class:"tw-w-auto md:tw-w-36"},null,8,["modelValue","options","error","error-message"]),A(se,{modelValue:f.year,"onUpdate:modelValue":u[2]||(u[2]=v=>f.year=v),error:!!m("year"),"error-message":(y=m("year"))==null?void 0:y.description,type:"number",class:"tw-w-20"},null,8,["modelValue","error","error-message"])])}}}),Je=oe("regions",()=>{const _=$({});function h(s){return s in _.value||(_.value[s]=j(`crm.region.list?country=${s}`).get().json()),_.value[s]}return{regions:_,getRegionByCode:h}}),Le=R({__name:"FieldAddress",props:{modelValue:{},fields:{},errors:{}},emits:["update:modelValue"],setup(_,{emit:h}){const s=_,{getRegionByCode:f}=Je(),m=$(g()),c=$([]),{pause:u,resume:b}=ue(m,()=>h("update:modelValue",m.value.filter(r=>r.value).map(r=>({field:r.id,value:r.value}))),{deep:!0,immediate:!0});z(()=>s.modelValue,async()=>{u(),m.value=g(),await Y(),b()}),z(()=>{var r;return(r=m.value.find(n=>n.id==="country"))==null?void 0:r.value},async r=>{if(c.value=[],r){const{data:n}=await f(r);n.value&&(c.value=n.value.sort((t,V)=>+V.is_favorite-+t.is_favorite))}},{immediate:!0}),z(()=>{var r;return(r=m.value.find(n=>n.id==="country"))==null?void 0:r.value},()=>{const r=m.value.find(n=>n.id==="region");r&&(r.value="")});function g(){return s.fields.map(r=>{var n;return{...r,value:((n=s.modelValue.find(t=>t.field===r.id))==null?void 0:n.value)??""}})}async function y(r){var n;if(await Y(),r.el){const t=document.createElement("option");t.innerText="──────────",t.setAttribute("disabled","disabled"),(n=r.el.querySelector("select").querySelectorAll("option")[c.value.filter(V=>V.is_favorite).length||-1])==null||n.after(t)}}const v=le((r,n)=>n&&n.find(t=>t.field.split(".")[1]===r)),k=J(()=>r=>v(r,s.errors));return(r,n)=>(i(),x(L,{space:"2"},{default:S(()=>[(i(!0),w(O,null,X(m.value,t=>{var V,B,E,p;return i(),w(O,{key:t.id},[t.id==="country"?(i(),x(P,{key:0,modelValue:t.value,"onUpdate:modelValue":o=>t.value=o,options:t.type==="select"?t.option_values:[],placeholder:t.name,error:!!k.value(t.id),"error-message":(V=k.value(t.id))==null?void 0:V.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error","error-message"])):t.id==="region"?(i(),w(O,{key:1},[c.value.length?(i(),x(P,{key:c.value.length,modelValue:t.value,"onUpdate:modelValue":o=>t.value=o,options:c.value.map(o=>({id:o.code,value:o.name})),placeholder:t.name,error:!!k.value(t.id),"error-message":(B=k.value(t.id))==null?void 0:B.description,onVnodeMounted:n[0]||(n[0]=o=>y(o))},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error","error-message"])):(i(),x(se,{key:1,modelValue:t.value,"onUpdate:modelValue":o=>t.value=o,type:"text",placeholder:t.name,error:!!k.value(t.id),"error-message":(E=k.value(t.id))==null?void 0:E.description},null,8,["modelValue","onUpdate:modelValue","placeholder","error","error-message"]))],64)):(i(),x(I(q(W)[t.type]),{key:2,modelValue:t.value,"onUpdate:modelValue":o=>t.value=o,options:t.type==="select"||t.type==="radio"?t.option_values:[],placeholder:t.name,error:!!k.value(t.id),"error-message":(p=k.value(t.id))==null?void 0:p.description},null,8,["modelValue","onUpdate:modelValue","options","placeholder","error","error-message"]))],64)}),128))]),_:1}))}}),Pe={key:0},He={class:"toggle rounded small"},Ze={key:1,class:"tw-text-center"},Ge=l("div",{class:"spinner custom-p-16 tw-mt-6"},null,-1),Ke=[Ge],Qe={key:2},Xe={key:0,class:"text-yellow"},Ye={class:"tw-flex-auto"},et={key:0,class:"handle tw-pt-1.5"},tt=l("i",{class:"fas fa-grip-vertical gray"},null,-1),ot=[tt],at={class:"tw-flex tw-flex-wrap tw-flex-auto tw-flex-col md:tw-flex-row tw-gap-2 tw-items-start"},lt={key:1,class:"tw-flex tw-space-x-2 tw-w-full md:tw-w-60 tw-flex-none"},st=["onClick"],rt=l("i",{class:"fas fa-trash-alt"},null,-1),nt=[rt],ut={class:"tw-w-full md:tw-w-60 tw-flex-none tw-flex tw-space-x-2"},it={class:"tw-flex-auto"},ct=["onClick"],dt=l("i",{class:"fas fa-trash-alt"},null,-1),mt=[dt],pt={class:"tw-w-full md:tw-w-auto tw-flex tw-space-x-2 empty:tw-hidden"},vt={key:0,class:"tw-w-1/2 md:tw-w-28 tw-flex-none"},yt={key:1,class:"tw-w-1/2 md:tw-w-28"},_t=["onClick"],ft=l("i",{class:"fas fa-trash-alt"},null,-1),wt=[ft],ht=["onClick"],Vt=l("i",{class:"fas fa-plus"},null,-1),gt=R({__name:"FormContactUpdateInfo",props:{contact:{}},setup(_,{expose:h}){var E;const s=_,{t:f}=ie(),m=ce(),c=!!s.contact,u=$(((E=s.contact)==null?void 0:E.details.is_company)??!1),b=$(!1),g=$(null),y=$([]),v=Q({company_contact_id:"",is_company:J(()=>+u.value)}),k=Q({person:null,company:null}),r={person:null,company:null};h({modified:J(()=>{var p;return(p=k[u.value?"company":"person"])==null?void 0:p.modified}),submit:V});const n=le(async p=>{const{data:o,error:e}=await j(`crm.field.list?scope=${p}`).get().json();return{fields:o.value,error:e.value}});z(u,async p=>{var a;b.value=!0;const{fields:o,error:e}=await n(p?"company":"person");if(b.value=!1,Array.isArray(o)){r[p?"person":"company"]=y.value.length?y.value:null;const U=r[p?"company":"person"];U?y.value=U:(t(o),k[p?"company":"person"]=re(y),(a=k[p?"company":"person"])==null||a.reset())}else g.value=e,n.clear()},{immediate:!0});function t(p){if(y.value=p.map(o=>{var U;const e=(U=s.contact)==null?void 0:U.data.filter(F=>F.field===o.id);let a;if(o.type==="composite"){const F={value:[],...o.ext?{ext:o.ext[0].id}:{}};a={...o,error:[],dummy:{...F},value:Array.isArray(e)&&e.length?e.map(d=>({value:Array.isArray(d.value)?d.value:[],...o.ext?{ext:d.ext}:{}})):[{...F}]}}else{const F={value:"",...o.ext?{ext:o.ext[0].id}:{}};a={...o,error:"",dummy:{...F},value:Array.isArray(e)&&e.length?e.map(d=>({value:Array.isArray(d.value)?"":d.value,...o.ext?{ext:d.ext}:{}})):[{...F}]}}return{...a,...o.is_required?{validate:Z.object({value:Z.string().min(1,{message:f("validation.required")})}).array()}:{}}}),m.query.phone){const o=y.value.find(e=>e.id==="phone");o&&(o.value[0].value=m.query.phone.toString())}}function V(){let p;if(y.value.forEach(e=>{if(e.validate)try{e.validate.parse(e.value)}catch(a){a instanceof Z.ZodError&&(e.error=a.issues[0].message,p=!0)}}),p)return te(),null;const o=y.value.filter(e=>e.value.filter(a=>a.value&&(Array.isArray(a.value)?a.value.filter(U=>U).length:!0)).length).reduce((e,a)=>{const{value:U}=a;return e.push(...U.map(F=>({field:a.id,value:F.value,ext:F.ext}))),e},[]);return o.push(...Object.entries(v).filter(e=>e[1]!=="").map(e=>({field:e[0],value:e[1]}))),j(`crm.contact.${c?`update?id=${s.contact.id}`:"add"}`,{onFetchError(e){try{const a=JSON.parse(e.data);"error_fields"in a&&B(a.error_fields)}catch{}return e}})[c?"put":"post"](JSON.stringify(o))}function B(p){for(const o of p)for(const e of o.field.split(", ")){const a=e.includes(".")?e.split(".")[0]:e,U=y.value.find(F=>F.id===a);U&&(U.error=U.type==="composite"?p:o.description)}te()}return(p,o)=>(i(),x(L,{space:"4"},{default:S(()=>[c?M("",!0):(i(),w("div",Pe,[l("div",He,[l("span",{class:T({selected:!u.value}),onClick:o[0]||(o[0]=e=>u.value=!1)},C(p.$t("person")),3),l("span",{class:T({selected:u.value}),onClick:o[1]||(o[1]=e=>u.value=!0)},C(p.$t("company")),3)])])),b.value?(i(),w("div",Ze,Ke)):g.value?(i(),w("div",Qe,C(g.value),1)):(i(!0),w(O,{key:3},X(y.value,e=>(i(),w("div",{key:e.id,class:"tw-flex tw-flex-col md:tw-flex-row tw-space-y-1 md:tw-space-y-0 md:tw-space-x-2"},[l("div",{class:T(["md:tw-flex-none md:tw-w-32 small gray tw-break-words",{"md:tw-pt-1.5":!["radio","checkbox"].includes(e.type)}])},[D(C(e.name),1),e.is_required?(i(),w("span",Xe,"*")):M("",!0)],2),l("div",Ye,[A(Ve,{list:e.value,"use-sort":!0,"min-length":2,onUpdate:a=>{e.value=a}},{default:S(({index:a})=>{var U,F;return[l("div",{class:T(["tw-flex tw-space-x-2",a>0&&"bordered-top tw-pt-2"])},[e.value.length>1?(i(),w("div",et,ot)):M("",!0),l("div",at,[e.id==="birthday"&&e.type==="composite"?(i(),x(We,{key:0,modelValue:e.value[a].value,"onUpdate:modelValue":d=>e.value[a].value=d,"options-for-day-select":((U=e.fields.find(d=>d.id==="day"&&d.type==="select"))==null?void 0:U.option_values)||[],"options-for-month-select":((F=e.fields.find(d=>d.id==="month"&&d.type==="select"))==null?void 0:F.option_values)||[],errors:e.error},null,8,["modelValue","onUpdate:modelValue","options-for-day-select","options-for-month-select","errors"])):e.id==="address"&&e.type==="composite"?(i(),w("div",lt,[A(Le,{modelValue:e.value[a].value,"onUpdate:modelValue":d=>e.value[a].value=d,fields:e.fields,errors:e.error},null,8,["modelValue","onUpdate:modelValue","fields","errors"]),a>0?(i(),w("a",{key:0,class:"md:tw-hidden !tw-mt-1.5",onClick:N(d=>e.value.splice(a,1),["prevent"])},nt,8,st)):M("",!0)])):(i(),w(O,{key:2},[l("div",ut,[l("div",it,[e.id==="company"&&!u.value?(i(),x(ge,{key:0,"is-multiple":!1,"text-value":e.value[a].value,"is-company":!0,"on-item-select":d=>{v.company_contact_id=d.id,e.value[a].value=d.name},onInput:d=>{v.company_contact_id="",e.value[a].value=d}},null,8,["text-value","on-item-select","onInput"])):(i(),x(I(q(W)[e.type]),{key:1,modelValue:e.value[a].value,"onUpdate:modelValue":d=>e.value[a].value=d,error:!!e.error,"error-message":e.error,options:e.type==="select"||e.type==="radio"?e.option_values:void 0,fields:e.type==="composite"?e.fields:void 0,required:e.is_required,placeholder:a>0?`${e.name} ${a+1}`:void 0,vertical:e.type==="radio"?!0:void 0},null,8,["modelValue","onUpdate:modelValue","error","error-message","options","fields","required","placeholder","vertical"]))]),a>0?(i(),w("a",{key:0,class:"md:tw-hidden !tw-mt-1.5",onClick:N(d=>e.value.splice(a,1),["prevent"])},mt,8,ct)):M("",!0)]),l("div",pt,[e.ext?(i(),w("div",vt,[(i(),x(I(q(W).select),{modelValue:e.value[a].ext,"onUpdate:modelValue":d=>e.value[a].ext=d,required:!0,options:[...e.ext,{id:e.ext.filter(d=>d.id===e.value[a].ext).length?"":e.value[a].ext??"",value:p.$t("other")}]},null,8,["modelValue","onUpdate:modelValue","options"]))])):M("",!0),e.ext&&!e.ext.find(d=>d.id===e.value[a].ext)?(i(),w("div",yt,[(i(),x(I(q(W).string),{modelValue:e.value[a].ext,"onUpdate:modelValue":d=>e.value[a].ext=d},null,8,["modelValue","onUpdate:modelValue"]))])):M("",!0)])],64)),a>0?(i(),w("a",{key:3,class:"tw-hidden md:tw-block !tw-mt-1.5",onClick:N(()=>{e.value.splice(a,1)},["prevent"])},wt,8,_t)):M("",!0)])],2)]}),_:2},1032,["list","onUpdate"]),e.is_multi?(i(),w("a",{key:0,class:"button rounded outlined light-gray smaller !tw-mt-2",onClick:N(a=>e.value.push({...e.dummy}),["prevent"])},[Vt,D(" "+C(p.$t("addMore")),1)],8,ht)):M("",!0)])]))),128))]),_:1}))}}),kt=["disabled","onClick"],$t={class:"tabs bordered-bottom tw-sticky tw-left-0 tw-top-0 tw-bg-waBlank tw-z-10 !tw-mb-4"},Tt=R({__name:"FormContactUpdate",props:{contact:{default:void 0},initialTab:{default:"info"}},emits:["close"],setup(_,{emit:h}){const s=_,f=de(),m=me(),c=!!s.contact,u=$(s.initialTab),b=$(null),g=$(null),y=$(!1),v=$("");async function k(){var n,t,V,B,E,p;y.value=!0,v.value="";let r;if((n=b.value)!=null&&n.modified){const o=await((t=b.value)==null?void 0:t.submit());if((V=o==null?void 0:o.response.value)!=null&&V.ok)r=o.data.value;else{y.value=!1,v.value=o==null?void 0:o.error.value;return}}if((B=g.value)!=null&&B.modified&&!v.value){const o=await((p=g.value)==null?void 0:p.submit(r||((E=s.contact)==null?void 0:E.id)));if(o!=null&&o.error.value){y.value=!1,v.value=o.error.value;return}}v.value||(r?f.push({name:"contact",params:{id:r}}):pe.webView?ve.emit("spa:navigateBack"):m.refetch()),h("close")}return(r,n)=>(i(),x(ye,{"vertical-stretch":!0,"use-cancel-as-button-label":!0,onClose:n[2]||(n[2]=t=>h("close"))},{header:S(()=>{var t;return[D(C(c?`${r.$t("editContact")} ${(t=s.contact)==null?void 0:t.main.name}`:r.$t("addContact")),1)]}),submit:S(()=>{var t,V;return[l("button",{class:"button",disabled:y.value||!((t=b.value)!=null&&t.modified||(V=g.value)!=null&&V.modified),onClick:N(k,["prevent"])},C(r.$t("save")),9,kt)]}),error:S(()=>[D(C(v.value),1)]),default:S(()=>[l("div",null,[l("ul",$t,[l("li",{class:T({selected:u.value==="info"})},[l("a",{onClick:n[0]||(n[0]=N(t=>u.value="info",["prevent"]))},C(r.$t("information")),1)],2),l("li",{class:T({selected:u.value==="scope"})},[l("a",{onClick:n[1]||(n[1]=N(t=>u.value="scope",["prevent"]))},C(r.$t("scope")),1)],2)]),G(l("div",null,[A(gt,{ref_key:"FormContactUpdateInfoRef",ref:b,contact:s.contact},null,8,["contact"])],512),[[K,u.value==="info"]]),G(l("div",null,[A(je,{ref_key:"formContactUpdateScopeRef",ref:g,contact:s.contact},null,8,["contact"])],512),[[K,u.value==="scope"]])])]),_:1}))}});export{Tt as _,ke as u};