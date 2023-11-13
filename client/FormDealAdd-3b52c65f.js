import{d as z,ao as ne,B as Q,M as H,a as I,P as re,g as A,o as p,c as k,e as r,w as v,m as q,k as i,i as e,F as B,l as J,f as V,W as M,b as S,G as me,z as ie,p as j,am as pe,q as de,s as ue,_ as G,ap as fe,aq as _e,h as ce,ar as he,as as ge,at as ye,au as $e,av as be,H as we,S as Ce,A as W,j as Ve,v as ke,n as ee,U as Ie}from"./main-512a5cdd.js";import{W as Se}from"./WaDialog-079c5577.js";import{C as Ae,I as Fe,_ as De,D as K,a as Ue,b as Ee,u as Ne}from"./InputWithPerformers.vue_vue_type_script_setup_true_lang-41c27d28.js";import{f as qe}from"./fields-536d59a3.js";import{s as Y}from"./dialog-e5c60953.js";import{F as E}from"./FieldString-0d7a3814.js";import{d as Oe}from"./dayjs-63c9a14c.js";import{b as Me}from"./index-f44a2c60.js";import{W as Re}from"./WaSpinner-c8c65e65.js";import{U as x}from"./UserPic-2094e1a0.js";import{M as ae,_ as le}from"./MenuList-bac784e3.js";import{D as te}from"./DropDown-f7af334e.js";import{u as je}from"./helpers-c15b26e3.js";import{C as Te}from"./CustomColumn-351ad915.js";import{B as se}from"./ButtonSubmit-00e8fa47.js";import"./recentContacts-971ea6fa.js";import"./FieldSelect-46bbc3cb.js";import"./index-0b315a89.js";import"./FieldCheckbox-a8d5da5b.js";const xe={key:0,class:"deal-add-custom-fields"},Be={class:"fields"},ze={class:"name"},Le={class:"value"},We=z({__name:"FormDealAddSectionFields",props:{modelValue:{}},setup(y){const c=ne(y,"modelValue"),{t:h}=Q(),_=H("crm.field.list?scope=deal").get().json(),m=I([]);return re(_.data,a=>{Array.isArray(a)&&(m.value=a.map(l=>(c.value.push({value:{field:l.id,value:""},error:"",...l.is_required?{validate:M.object({value:M.string().min(1,{message:h("validation.required")})}).array()}:{}}),l)))}),(a,l)=>A(c).length?(p(),k("section",xe,[r(Ae,null,{name:v(()=>[q(i(a.$t("addtnlInfo")),1)]),default:v(()=>[e("div",Be,[(p(!0),k(B,null,J(m.value,(f,b)=>(p(),k("div",{key:f.id,class:"field"},[e("div",ze,i(f.name),1),e("div",Le,[(p(),S(me(A(qe)[f.type]),{modelValue:A(c)[b].value.value,"onUpdate:modelValue":g=>A(c)[b].value.value=g,vertical:f.type==="radio",options:(f.type==="select"||f.type==="radio")&&f.option_values,required:f.is_required,"error-message":A(c)[b].error},null,8,["modelValue","onUpdate:modelValue","vertical","options","required","error-message"]))])]))),128))])]),_:1})])):V("",!0)}}),He=y=>(de("data-v-48f88a12"),y=y(),ue(),y),Ye={class:"deal-add-customer"},Ge={class:"fields tw-mt-5 tw-mb-5"},Pe={class:"field"},Je={class:"name"},Ke={class:"value"},Qe={key:0,class:"tw-flex tw-gap-x-1 tw-mt-2"},Ze=["disabled","onClick"],Xe=He(()=>e("span",{class:"tw-mr-1"},[e("i",{class:"fas fa-plus-circle"})],-1)),ea={key:0,class:"fields"},aa={class:"field"},la={class:"name"},ta={class:"value"},sa={class:"field"},oa={class:"name"},na={class:"value"},ra={class:"field"},ia={class:"name"},da={class:"value"},ua={class:"field"},ca={class:"name"},va={class:"value"},ma={class:"field"},pa={class:"name"},fa={class:"value"},_a={class:"field"},ha={class:"name"},ga={class:"value"},ya={class:"field"},$a={class:"name"},ba={class:"value"},wa={class:"field"},Ca={class:"name"},Va={class:"value"},ka=z({__name:"FormDealAddSectionCustomer",props:{initContactId:{}},emits:["updateContactId","updateContactLabel"],setup(y,{expose:C,emit:c}){const h=y,{t:_}=Q(),m=I(),a=I(!1),l=I(R()),f=I("");C({validate:T,createContactOrGetExistsId:P,isFetchingContacts:ie(()=>{var s;return(s=m==null?void 0:m.value)==null?void 0:s.isFetchingContacts})});function b(s){var o,n;s?(a.value=!1,l.value={id:{value:s.id},name:{value:s.name},jobtitle:{value:s.jobtitle||""},company:{value:s.company||""},email:{value:((o=s.fields.find(d=>d.id==="email"))==null?void 0:o.value[0])??""},phone:{value:((n=s.fields.find(d=>d.id==="phone"))==null?void 0:n.value[0])??""}}):l.value=R(),c("updateContactId",(s==null?void 0:s.id)||null)}function g(){var s;a.value||(a.value=!0,(s=m.value)==null||s.clearValue(),f.value="",c("updateContactId",null))}function R(){return{id:{value:null,is_required:!a.value,validate:M.coerce.number().min(1,{message:_("validation.required")})},name:{value:""},firstname:{value:"",is_required:!0,validate:M.string().trim().min(1,{message:_("validation.required")})},middlename:{value:""},lastname:{value:""},jobtitle:{value:""},company:{value:""},email:{value:""},phone:{value:""},is_company:{value:0}}}function T(){let s=!1;return Object.entries(l.value).forEach(([,o])=>{if(o.error="",o.is_required&&o.validate)try{o.validate.parse(o.value)}catch(n){n instanceof M.ZodError&&(o.error=n.issues[0].message,s=!0)}}),s?(Y(),!1):!0}async function P(){var d;if((d=l.value.id)!=null&&d.value)return l.value.id.value;if(l.value.name.value||(l.value.name.value=`${l.value.firstname.value} ${l.value.middlename.value} ${l.value.lastname.value}`),!T())return null;const s=[];Object.entries(l.value).forEach(([t,$])=>{t!=="id"&&s.push({field:t,value:$.value})});const{data:o,error:n}=await H("crm.contact.add",{onFetchError(t){var $;return typeof t.data=="string"&&(($=JSON.parse(t.data).error_fields)==null||$.forEach(F=>{if(l.value){if(F.field==="name")l.value.firstname.error=F.description;else if(F.field){const u=F.field;l.value[u].error=F.description}}}),Y()),t}}).post(JSON.stringify(s));return n.value?n.value:Number(o.value)}return(s,o)=>(p(),k("section",Ye,[e("div",Ge,[e("div",Pe,[e("div",Je,i(s.$t("customers")),1),e("div",Ke,[r(Fe,{ref_key:"inputWithCustomersRef",ref:m,width:"320px",placeholder:s.$t("searchByNameEmailPhone"),"with-fields":["email","phone","company","jobtitle"],"init-contact-id":h.initContactId,disabled:!!h.initContactId,"error-message":l.value.id.error,onUpdate:b,onClear:o[0]||(o[0]=n=>b(null))},null,8,["placeholder","init-contact-id","disabled","error-message"]),h.initContactId?V("",!0):(p(),k("div",Qe,[e("span",null,i(s.$t("or")),1),e("a",{class:"tw-font-semibold",disabled:a.value,onClick:j(g,["prevent"])},[Xe,e("span",null,i(s.$t("addNew")),1)],8,Ze)]))])])]),r(pe,{name:"fade",mode:"out-in"},{default:v(()=>[a.value||l.value.id.value?(p(),k("div",ea,[a.value?(p(),k(B,{key:0},[e("h3",null,i(s.$t("addContact")),1),e("div",aa,[e("div",la,i(s.$t("firstName")),1),e("div",ta,[r(E,{modelValue:l.value.firstname.value,"onUpdate:modelValue":o[1]||(o[1]=n=>l.value.firstname.value=n),type:"text",readonly:!a.value,"error-message":l.value.firstname.error,onKeydown:o[2]||(o[2]=n=>l.value.firstname.error="")},null,8,["modelValue","readonly","error-message"])])]),e("div",sa,[e("div",oa,i(s.$t("middleName")),1),e("div",na,[r(E,{modelValue:l.value.middlename.value,"onUpdate:modelValue":o[3]||(o[3]=n=>l.value.middlename.value=n),type:"text",readonly:!a.value,"error-message":l.value.middlename.error},null,8,["modelValue","readonly","error-message"])])]),e("div",ra,[e("div",ia,i(s.$t("lastName")),1),e("div",da,[r(E,{modelValue:l.value.lastname.value,"onUpdate:modelValue":o[4]||(o[4]=n=>l.value.lastname.value=n),type:"text",readonly:!a.value,"error-message":l.value.lastname.error},null,8,["modelValue","readonly","error-message"])])])],64)):V("",!0),e("div",ua,[e("div",ca,i(s.$t("company")),1),e("div",va,[r(E,{modelValue:l.value.company.value,"onUpdate:modelValue":o[5]||(o[5]=n=>l.value.company.value=n),type:"text",readonly:!a.value,"error-message":l.value.company.error},null,8,["modelValue","readonly","error-message"])])]),e("div",ma,[e("div",pa,i(s.$t("jobTitle")),1),e("div",fa,[r(E,{modelValue:l.value.jobtitle.value,"onUpdate:modelValue":o[6]||(o[6]=n=>l.value.jobtitle.value=n),type:"text",readonly:!a.value,"error-message":l.value.jobtitle.error},null,8,["modelValue","readonly","error-message"])])]),e("div",_a,[e("div",ha,i(s.$t("roleInDeal")),1),e("div",ga,[r(De,{modelValue:f.value,"onUpdate:modelValue":[o[7]||(o[7]=n=>f.value=n),o[8]||(o[8]=n=>c("updateContactLabel",n))],scope:"CLIENT",right:!1,width:"320px"},null,8,["modelValue"])])]),e("div",ya,[e("div",$a,i(s.$t("phone")),1),e("div",ba,[r(E,{modelValue:l.value.phone.value,"onUpdate:modelValue":o[9]||(o[9]=n=>l.value.phone.value=n),type:"text",readonly:!a.value,"error-message":l.value.phone.error},null,8,["modelValue","readonly","error-message"])])]),e("div",wa,[e("div",Ca,i(s.$t("email")),1),e("div",Va,[r(E,{modelValue:l.value.email.value,"onUpdate:modelValue":o[10]||(o[10]=n=>l.value.email.value=n),type:"email",readonly:!a.value,"error-message":l.value.email.error},null,8,["modelValue","readonly","error-message"])])])])):V("",!0)]),_:1})]))}});const Ia=G(ka,[["__scopeId","data-v-48f88a12"]]),Sa={key:0,class:"state-error-hint tw-mt-1"},Aa=1e3,Fa=26,Da=z({__name:"TextAreaAutoresize",props:{modelValue:{},maxRows:{},errorMessage:{}},setup(y){const C=y,c=ne(C,"modelValue"),h=I();fe(h,_),_e(h,["change","cut","paste","drop","input"],_);function _(){if(h.value){if((C.maxRows||Aa)*Fa<h.value.scrollHeight)return;h.value.style.height="auto",h.value.style.height=h.value.scrollHeight+"px"}}return(m,a)=>(p(),k(B,null,[ce(e("textarea",ge({ref_key:"textAreaRef",ref:h,"onUpdate:modelValue":a[0]||(a[0]=l=>ye(c)?c.value=l:null),rows:"1",class:{"state-error":C.errorMessage}},m.$attrs),null,16),[[he,A(c)]]),C.errorMessage?(p(),k("div",Sa,i(C.errorMessage),1)):V("",!0)],64))}});const oe=G(Da,[["__scopeId","data-v-71a56661"]]),Ua={class:"select-funnel-and-stage"},Ea=["onClick"],Na={class:"icon small"},qa=["onClick"],Oa={class:"icon small"},Ma=z({__name:"SelectFunnelAndStage",props:{funnelId:{},forceFetch:{type:Boolean}},emits:["updateStageId"],setup(y,{emit:C}){const c=y,h=je(),_=$e(h,"funnels");c.forceFetch&&!_.value.length&&h.refetch();const m=I(),a=I();return be(()=>_.value.length,()=>{const l=_.value[0];m.value=_.value.find(f=>f.id===c.funnelId)||l,a.value=m.value.stages[0],a.value&&C("updateStageId",a.value.id)},{immediate:!0}),re(a,l=>{l&&C("updateStageId",l.id)}),(l,f)=>(p(),k("div",Ua,[!_.value.length||_.value.length>1?(p(),S(te,{key:0,class:"select-dropdown"},{body:v(({hide:b})=>[_.value.length?(p(),S(ae,{key:0},{default:v(()=>[(p(!0),k(B,null,J(_.value,g=>(p(),S(le,{key:g.id},{default:v(()=>[e("a",{onClick:j(R=>{m.value=g,a.value=g.stages[0],b()},["prevent"])},[e("div",Na,[r(x,{size:10,"disable-rounded":"","bg-color":g.color},null,8,["bg-color"])]),e("span",null,i(g.name),1)],8,Ea)]),_:2},1024))),128))]),_:2},1024)):V("",!0)]),default:v(()=>[r(K,{"color-class":"blank","mobile-w-full":"","is-skeleton":!_.value.length},{icon:v(()=>[m.value?(p(),S(x,{key:m.value.id,size:10,"disable-rounded":"","bg-color":m.value.color},null,8,["bg-color"])):V("",!0)]),title:v(()=>[q(i(m.value?m.value.name:l.$t("allStages")),1)]),_:1},8,["is-skeleton"])]),_:1})):V("",!0),r(te,{class:"select-dropdown",disabled:!a.value},{body:v(({hide:b})=>[m.value?(p(),S(ae,{key:0},{default:v(()=>[(p(!0),k(B,null,J(m.value.stages,g=>(p(),S(le,{key:g.id},{default:v(()=>[e("a",{onClick:j(R=>{a.value=g,b()},["prevent"])},[e("div",Oa,[r(x,{size:10,"disable-rounded":"","bg-color":g.color},null,8,["bg-color"])]),e("span",null,i(g.name),1)],8,qa)]),_:2},1024))),128))]),_:2},1024)):V("",!0)]),default:v(()=>[r(K,{"color-class":"blank","mobile-w-full":"","is-skeleton":!_.value.length},{icon:v(()=>[a.value?(p(),S(x,{key:a.value.id,size:10,"disable-rounded":"","bg-color":a.value.color},null,8,["bg-color"])):V("",!0)]),title:v(()=>[q(i(a.value?a.value.name:l.$t("allStages")),1)]),_:1},8,["is-skeleton"])]),_:1},8,["disabled"])]))}});const Ra=G(Ma,[["__scopeId","data-v-7a630f80"]]),ja=y=>(de("data-v-6de88aa0"),y=y(),ue(),y),Ta={class:"deal-add-main"},xa={class:"fields"},Ba={class:"field"},za={class:"name"},La={class:"value"},Wa={class:"field"},Ha={class:"name"},Ya={class:"value"},Ga={class:"field"},Pa={class:"name"},Ja={class:"value"},Ka={class:"field"},Qa={class:"name"},Za={class:"value"},Xa={class:"field-amount-wrapper"},el={key:0,class:"hint break-word"},al=["href"],ll=ja(()=>e("i",{class:"fas fa-external-link-alt fa-sm"},null,-1)),tl={class:"field"},sl={class:"name"},ol={class:"value"},nl={class:"field"},rl={class:"tw-flex tw-gap-x-3 tw-mr-2"},il={class:"state-error-hint"},dl=z({__name:"FormDealAdd",props:{funnelId:{},contactId:{},conversationId:{},disableNavigateToDeal:{type:Boolean},onAdded:{type:Function}},emits:["close"],setup(y,{emit:C}){const c=y,{t:h}=Q(),_=we(),m=Ce(Me),a=I({stage_id:{value:null,is_required:!0},name:{value:"",is_required:!0,validate:M.string().trim().min(1,{message:h("validation.required")})},description:{value:""},expected_date:{value:Oe().add(2,"w").format("YYYY-MM-DD")},amount:{value:""},currency_id:{value:""},contact_id:{value:Number(c.contactId)||null},contact_label:{value:""},user_contact_id:{value:W.user.id},fields:[]}),l=I(new Map),f=I(""),b=I(null),g=ie(()=>Ne().isEmptyCurrencies);function R(){var t;let d=!1;return Object.entries(a.value).forEach(([,$])=>{if($.error="",$.is_required&&$.validate)try{$.validate.parse($.value)}catch(D){D instanceof M.ZodError&&($.error=D.issues[0].message,d=!0)}}),d||!((t=b.value)!=null&&t.validate())?(Y(),!1):!0}async function T(d){var L,Z;if(l.value.clear(),f.value="",!R())return null;l.value.set(d,!0);const t=await((L=b.value)==null?void 0:L.createContactOrGetExistsId());if(typeof t=="number")a.value.contact_id.value=t;else return t instanceof Error&&(f.value=t.message),l.value.clear(),null;const $=new Map;Object.keys(a.value).forEach(N=>{const U=N,O=a.value[U];if(Array.isArray(O))$.set(U,O.filter(w=>w.value.value).map(w=>({field:w.value.field,value:w.value.value})));else if(O!=null&&O.value){let{value:w}=O;U==="description"&&(w=w.replace(/\n/g,"<br>")),$.set(U,w)}});const{data:D,response:F,error:u}=await H("crm.deal.add",{onFetchError(N){var U;return typeof N.data=="string"&&((U=JSON.parse(N.data).error_fields)==null||U.forEach(w=>{if(w.code==="fields"){const X=a.value.fields.find(ve=>ve.value.field===w.field);X&&(X.error=w.description)}else w.field in a.value&&(a.value[w.field].error=w.description)}),Y()),N}}).post(Object.fromEntries($));if(l.value.clear(),f.value=u.value instanceof Error?u.value.message:"",!f.value&&((Z=F.value)!=null&&Z.ok)&&D.value){const N=Number(D.value);if(!isNaN(Number(c.conversationId))){const{error:U}=await P(N,Number(c.conversationId));if(f.value=U.value,f.value)return null}return C("close"),N}return null}async function P(d,t){return await H("crm.conversation.deal").post({deal_id:d,conversation_id:t})}function s(){Ie.emit("spa:navigateBack"),C("close")}async function o(){await T("create")&&typeof c.onAdded=="function"&&c.onAdded()}async function n(){const d=await T("createAndOpen");d&&(typeof c.onAdded=="function"&&c.onAdded(),c.disableNavigateToDeal||_.push({name:"deal",params:{id:d},replace:!!W.webView}))}return(d,t)=>{const $=Ve("i18n-t");return p(),S(Se,{"vertical-stretch":!0,"use-cancel-as-button-label":!0,"hide-close-icon":"",onClose:s},{header:v(()=>[q(i(d.$t("newDeal")),1)]),default:v(()=>{var D,F;return[ce(r(Te,{space:"4",class:"tw-relative tw-h-full"},{default:v(()=>[e("section",Ta,[e("div",xa,[e("div",Ba,[e("div",za,i(d.$t("title")),1),e("div",La,[r(oe,{modelValue:a.value.name.value,"onUpdate:modelValue":t[0]||(t[0]=u=>a.value.name.value=u),class:"field-title-textarea tw-font-bold","aria-required":"true","max-rows":10,"error-message":a.value.name.error,onKeydown:t[1]||(t[1]=u=>a.value.name.error="")},null,8,["modelValue","error-message"])])]),e("div",Wa,[e("div",Ha,i(d.$t("currentStage")),1),e("div",Ya,[r(Ra,{"funnel-id":c.funnelId,"force-fetch":d.$route.meta.menuItemType==="frame",onUpdateStageId:t[2]||(t[2]=u=>a.value.stage_id.value=u)},null,8,["funnel-id","force-fetch"])])]),e("div",Ga,[e("div",Pa,i(d.$t("responsible")),1),e("div",Ja,[r(Ue,{contact:A(W).user,width:"320px",class:"width-100 tw-h-[30px]","hide-clear":"",onUpdate:t[3]||(t[3]=u=>a.value.user_contact_id.value=u.id)},{preview:v(({model:u,showInput:L})=>[r(K,{class:"tw-pl-1","color-class":"blank","mobile-w-full":"",onClick:j(L,["prevent"])},{icon:v(()=>[r(x,{size:20,url:u.userpic},null,8,["url"])]),title:v(()=>[q(i(u.name),1)]),_:2},1032,["onClick"])]),_:1},8,["contact"])])]),e("div",Ka,[e("div",Qa,i(d.$t("estimatedAmount")),1),e("div",Za,[e("div",Xa,[r(E,{modelValue:a.value.amount.value,"onUpdate:modelValue":t[4]||(t[4]=u=>a.value.amount.value=u),"error-message":a.value.amount.error,disabled:g.value,class:"!tw-text-left",min:"0",type:"number"},null,8,["modelValue","error-message","disabled"]),r(Ee,{"model-value":a.value.currency_id.value,"error-message":a.value.currency_id.error,disabled:g.value,required:!g.value,"onUpdate:modelValue":t[5]||(t[5]=u=>a.value.currency_id.value=u)},null,8,["model-value","error-message","disabled","required"])]),g.value?(p(),k("div",el,[r($,{keypath:"currenciesNotSetUp"},{default:v(()=>[e("a",{class:ee(d.$constant.parentAppDisableRouterClass),href:`${A(W).baseUrl}settings/currencies/`,target:"_blank"},[q(i(d.$t("settingsCurrencies"))+" ",1),ll],10,al)]),_:1})])):V("",!0)])]),e("div",tl,[e("div",sl,i(d.$t("expectedCloseDate")),1),e("div",ol,[r(E,{modelValue:a.value.expected_date.value,"onUpdate:modelValue":t[6]||(t[6]=u=>a.value.expected_date.value=u),type:"date",class:"!tw-w-auto","error-message":a.value.expected_date.error},null,8,["modelValue","error-message"])])]),e("div",nl,[r(oe,{modelValue:a.value.description.value,"onUpdate:modelValue":t[7]||(t[7]=u=>a.value.description.value=u),class:"field-description-textarea",rows:"2",placeholder:d.$t("dealDescr"),"error-message":a.value.description.error,onKeydown:t[8]||(t[8]=u=>a.value.description.error="")},null,8,["modelValue","placeholder","error-message"])])])]),r(Ia,{ref_key:"formDealAddSectionCustomerRef",ref:b,"init-contact-id":Number(c.contactId),class:"tw-pb-1",onUpdateContactId:t[9]||(t[9]=u=>a.value.contact_id.value=u),onUpdateContactLabel:t[10]||(t[10]=u=>a.value.contact_label.value=u)},null,8,["init-contact-id"]),r(We,{modelValue:a.value.fields,"onUpdate:modelValue":t[11]||(t[11]=u=>a.value.fields=u),class:"tw-pb-7"},null,8,["modelValue"])]),_:1},512),[[ke,!((D=b.value)!=null&&D.isFetchingContacts)]]),(F=b.value)!=null&&F.isFetchingContacts?(p(),S(Re,{key:0})):V("",!0)]}),submit:v(()=>[e("div",rl,[r(se,{"is-fetching":l.value.get("createAndOpen"),class:ee([A(m)?"":"gray"]),onClick:j(n,["prevent"])},{default:v(()=>[q(i(d.$t(A(m)?"create":"createAndOpen")),1)]),_:1},8,["is-fetching","class","onClick"]),A(m)?V("",!0):(p(),S(se,{key:0,"is-fetching":l.value.get("create"),onClick:j(o,["prevent"])},{default:v(()=>[q(i(d.$t("create")),1)]),_:1},8,["is-fetching","onClick"]))])]),error:v(()=>[e("span",il,i(f.value),1)]),_:1})}}});const Fl=G(dl,[["__scopeId","data-v-6de88aa0"]]);export{Fl as default};
