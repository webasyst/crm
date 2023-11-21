import{d as W,o as A,b as x,w as _,m as L,k as S,i as y,n as Z,I as R,ac as V,a as C,B as ee,ai as te,e as X,c as q,h as ae,ar as se,f as j,p as P,P as ne,z as F,g as oe,$ as B,M as O}from"./main-ad3d4b2a.js";import{b as Y}from"./emit-32ce2ce5.js";import{W as z}from"./WaDialog-48ef549a.js";import{g as re}from"./helpers-c0c14d27.js";import{u as le}from"./funnels-5e77cb0b.js";import{B as U}from"./ButtonSubmit-66512fc6.js";import{F as ie}from"./FieldSelect-fbb8732d.js";const ue=W({__name:"AlertDialog",props:{message:{},isError:{type:Boolean}},setup(a){const r=a;return(o,u)=>(A(),x(z,{"use-cancel-as-button-label":!1,"hide-close-icon":!0},{header:_(()=>[L(S(o.$t(r.isError?"error":"info")),1)]),default:_(()=>[y("div",{class:Z({"state-error":r.isError})},S(r.message),3)]),_:1}))}});function ce(a){a instanceof Error&&(a=a.toString()),de(!0,a)}function de(a,r){R(ue,{isError:a,message:r}).show()}const fe={class:"gray"},ve={class:"smaller break-words"},me={class:"fields"},_e={class:"field"},he={class:"name"},pe={class:"value"},ge={key:0,class:"field"},ye=y("div",{class:"name"},null,-1),Se={class:"value"},be=["placeholder"],we={key:0,class:"state-error-hint"},M="0",Ee=W({__name:"FormConfirmLostReasonStage",props:{scopeActions:{},dealName:{}},setup(a){const r=a,o=V({reasonId:"",customText:""}),u=V({reasonId:!1,customText:!1}),h=C(!0),p=C([]),v=C(!1),g=C(""),{lostReasonList:I,changeCloseStage:D}=r.scopeActions,{t:b}=ee();te(async()=>{g.value="";const{error:c,data:d}=await I();d.value&&(h.value=d.value.required,d.value.lostreasons.forEach(e=>{var t;(t=p.value)==null||t.push({id:String(e.id),value:e.name})}),d.value.allow_custom&&p.value.push({id:M,value:b("otherReason")})),g.value=c.value||""});function $(){return Object.values(u).every(c=>c===!1)}function k(){for(const c in u)u[c]=!1}async function N(){var n;if(g.value="",u.reasonId=h.value&&!o.reasonId,u.customText=o.reasonId===M&&!o.customText,!$())return;const c=o.reasonId&&o.reasonId!==M,d=c?Number(o.reasonId):null,e=c?((n=p.value.find(s=>s.id===o.reasonId))==null?void 0:n.value)||"":o.reasonId===M?o.customText:"";v.value=!0;const{error:t}=await D({status_id:"LOST",lost_id:d,lost_text:e});v.value=!1,g.value=t.value||""}return(c,d)=>(A(),x(z,{"use-cancel-as-button-label":"","hide-close-icon":""},{header:_(()=>[y("div",fe,S(c.$t("closeDeal")),1),y("div",ve,S(r.dealName),1)]),default:_(()=>[y("div",me,[y("div",_e,[y("div",he,S(c.$t("lostReason")),1),y("div",pe,[X(ie,{modelValue:o.reasonId,"onUpdate:modelValue":[d[0]||(d[0]=e=>o.reasonId=e),k],"error-message":u.reasonId?c.$t("validation.required"):void 0,options:p.value},null,8,["modelValue","error-message","options"])])]),o.reasonId===M?(A(),q("div",ge,[ye,y("div",Se,[ae(y("input",{"onUpdate:modelValue":d[1]||(d[1]=e=>o.customText=e),type:"text",class:Z(["width-100",{"state-error":u.customText}]),placeholder:c.$t("customReason"),onChange:k},null,42,be),[[se,o.customText]])])])):j("",!0)])]),submit:_(()=>[X(U,{bg:"red","is-fetching":v.value,onClick:P(N,["prevent"])},{default:_(()=>[L(S(c.$t("lost")),1)]),_:1},8,["is-fetching","onClick"])]),error:_(()=>[g.value?(A(),q("span",we,S(g.value),1)):j("",!0)]),_:1}))}}),Ce=["innerHTML"],ke={key:0,class:"state-error-hint"},Te=W({__name:"FormConfirmChangeStage",props:{scopeActions:{},orderActionData:{}},setup(a){const r=a,o=C(),u=C(!1),h=C(""),p=document.createElement("div");p.innerHTML=r.orderActionData.dialog_html.trim();let v;p&&(v=p.querySelector(".crm-dialog-content"),((t,n)=>{if(v){const s=v.querySelector(t);s instanceof HTMLElement&&(s.style.display=n)}})(".js-form-footer-actions","none")),ne(o,e=>{if(!e)return;const t=e.getElementsByTagName("script");if(t!=null&&t.length){const n=s=>{const m=window.eval;if(typeof m!="function"||s.src){s.remove();const l=document.createElement("script");s.type&&(l.type=s.type),s.src?l.src=s.src:l.innerHTML=s.innerHTML,e.append(l)}else m(s.innerHTML)};Array.from(t).forEach(n),e.querySelectorAll("input.hasDatepicker").forEach(s=>{s.setAttribute("type","date")}),e.querySelectorAll("input.ui-timepicker-input").forEach(s=>{s.setAttribute("type","time")})}});const{formChangeStageOrderAction:g,forceChangeStage:I,closeModal:D}=r.scopeActions,b=F(()=>p.querySelector(".crm-dialog-header").innerText.trim()),$=F(()=>v.innerHTML),k=F(()=>{var t;const e=(t=o.value)==null?void 0:t.getElementsByTagName("form");return e!=null&&e.length?e[0]:null}),N=F(()=>r.orderActionData.action_id!==null&&k.value);async function c(){if(u.value=!0,h.value="",k.value){const e=await g({action_id:String(r.orderActionData.action_id),order_id:r.orderActionData.order_id},k.value);u.value=!1,h.value=e.error.value||""}}async function d(){u.value=!0,h.value="";const e=await I();u.value=!1,e&&(h.value=e.error.value||"")}return(e,t)=>(A(),x(z,{"use-cancel-as-button-label":"","hide-close-icon":"",onClose:oe(D)},{header:_(()=>[L(S(b.value),1)]),default:_(()=>[y("div",{ref_key:"contentRef",ref:o,class:"break-words",innerHTML:$.value},null,8,Ce)]),submit:_(()=>[N.value?(A(),x(U,{key:0,"is-fetching":u.value,onClick:P(c,["prevent"])},{default:_(()=>[L(S(e.$t("submit")),1)]),_:1},8,["is-fetching","onClick"])):(A(),x(U,{key:1,"is-fetching":u.value,onClick:P(d,["prevent"])},{default:_(()=>[L(S(e.$t("changeStage")),1)]),_:1},8,["is-fetching","onClick"]))]),error:_(()=>[h.value?(A(),q("span",ke,S(h.value),1)):j("",!0)]),_:1},8,["onClose"]))}}),i=C(null),T=C();function xe(a){const r=V({cbSuccess:null,cbError:null,cbCancel:null});function o(e,t=null,n){var s,m;switch((s=e.response.value)==null?void 0:s.status){case 409:if(!t)return;T.value=R(Te,{scopeActions:{formChangeStageOrderAction:h,forceChangeStage:g,closeModal:$},orderActionData:t}),T.value.show();break;case 204:Y(),a.value&&(i.value?("stage_id"in i.value?d(i.value.stage_id,a.value.stage_id):"status_id"in i.value&&d(null,a.value.stage_id),a.value={...a.value,...i.value},i.value=null):d(a.value.stage_id,null)),(m=T.value)==null||m.hide(),b("success");break;default:n&&e.error.value&&ce(e.error.value),i.value=null,b("error");break}}async function u(e,t){var f;i.value=e;let n=null;const s=t?{...e,force:!0}:e,m=B.stringify({id:(f=a.value)==null?void 0:f.id}),l=await O(`crm.deal.move?${m}`,{onFetchError(E){if(typeof E.data=="string"){const w=JSON.parse(E.data);w&&"dialog_html"in w&&(n=w)}return E}}).post(s);return o(l,n,!t),l}async function h(e,t){var w,J,G,K,Q;const n=re(t),s=B.stringify(e);i.value&&"lost_id"in i.value&&Object.assign(n,{crm_change_workflow_data:{id:(w=a.value)==null?void 0:w.id,action:i.value.status_id,lost_id:i.value.lost_id,lost_text:i.value.lost_text}});const m=B.stringify(n),{response:l,data:f,error:E}=await O(`crm.deal.shopaction?${s}`,{beforeFetch({options:H}){return H.headers=new Headers(H.headers),H.headers.set("Content-Type","application/x-www-form-urlencoded"),{options:H}}}).post(m).json();return(J=l.value)!=null&&J.ok&&f.value&&a.value?((G=f.value)==null?void 0:G.status_id)===a.value.status_id&&((K=f.value)==null?void 0:K.stage_id)===a.value.stage_id?b("cancel"):(Y(),a.value={...a.value,stage_id:f.value.stage_id,funnel_id:f.value.funnel_id,status_id:f.value.status_id,lost_id:f.value.lost_id,lost_text:f.value.lost_text,...typeof f.value.shop_order=="object"?f.value.shop_order:{}},b("success")):b("error"),i.value=null,(Q=T.value)==null||Q.hide(),{response:l,data:f,error:E}}async function p(e){var t;return e==="LOST"?(T.value=R(Ee,{scopeActions:{lostReasonList:D,changeCloseStage:v},dealName:((t=a.value)==null?void 0:t.name)||""}),T.value.show(),!1):v({status_id:"WON"})}async function v(e,t){var f;i.value=e;let n=null;const s=t?{...e,force:!0}:e,m=B.stringify({id:(f=a.value)==null?void 0:f.id}),l=await O(`crm.deal.close?${m}`,{onFetchError(E){if(typeof E.data=="string"){const w=JSON.parse(E.data);w&&"dialog_html"in w&&(n=w)}return E}}).post(s);return o(l,n,!t&&(e==null?void 0:e.status_id)==="WON"),l}async function g(){return i.value?"status_id"in i.value?v(i.value,!0):u(i.value,!0):!1}async function I(){var t,n;const e=await O(`crm.deal.reopen?id=${(t=a.value)==null?void 0:t.id}`).post();return((n=e.response.value)==null?void 0:n.status)===204&&a.value&&(a.value={...a.value,status_id:"OPEN"}),o(e,null,!0),e}async function D(){var t;return await O(`crm.deal.lostreason.list?funnel_id=${(t=a.value)==null?void 0:t.funnel_id}`).json()}function b(e){var n;const t=`cb${e[0].toUpperCase()}${e.slice(1)}`;typeof r[t]=="function"&&((n=r[t])==null||n.call(null))}function $(){var e;(e=T.value)==null||e.hide(),b("cancel")}function k(e){r.cbSuccess=e}function N(e){r.cbError=e}function c(e){r.cbCancel=e}function d(e,t){le().funnels.forEach(n=>{n.deal_count=n.stages.reduce((s,m)=>{const l=m;return l.id===e&&(l.deal_count+=1),l.id===t&&(l.deal_count-=1),s+l.deal_count},0)})}return{modal:T,changeOpenStage:u,formChangeStageOrderAction:h,closeDeal:p,changeCloseStage:v,lostReasonList:D,forceChangeStage:g,reopenDeal:I,closeModal:$,onSuccess:k,onError:N,onCancel:c}}export{xe as u};
