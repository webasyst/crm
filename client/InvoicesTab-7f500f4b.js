import{d as L,a as m,M as S,z as g,A as n,Y as T,P as D,g as o,o as t,b as c,c as a,k as C,w as d,F as f,l as q,e as b,m as z,p as A,f as $,h as M,i as v,$ as j}from"./main-512a5cdd.js";import{v as H}from"./index-0b315a89.js";import{S as P}from"./SkeletonList-675defe7.js";import O from"./IframeView-95d9d201.js";import{I as U}from"./IconButton-a1e804cb.js";import{_ as Y}from"./AccessDeniedDummy.vue_vue_type_script_setup_true_lang-aad20bf1.js";import{E as G}from"./EmptyList-256bbb87.js";import{C as J}from"./CustomColumn-351ad915.js";import{H as K}from"./HistoryTabLogInvoiceSummary-fbff19f8.js";import"./iframeObserver-b7e5eaf8.js";import"./index-f44a2c60.js";import"./dayjs-63c9a14c.js";const Q={key:1,class:"state-error-hint"},R={key:2,class:"tw-max-w-3xl tw-mx-auto"},W=v("div",{class:"icon size-80"},[v("i",{class:"fa fa-file-invoice-dollar"})],-1),X={key:0,class:"tw-flex tw-justify-center tw-mt-4"},Z={class:"tw-h-20"},ee={key:0,class:"tw-text-center tw-py-4"},te=v("div",{class:"spinner custom-p-16"},null,-1),ie=[te],p=30,ve=L({__name:"InvoicesTab",props:{entityType:{},entityId:{},isNew:{type:Boolean},invoiceId:{}},setup(x){var I;const s=x,u=m({[`${s.entityType}_id`]:s.entityId,sort:"create_datetime",asc:0,offset:0,limit:p}),y=m(0),r=m(null),{data:B,isFetching:_,statusCode:N,error:w,execute:h}=S(g(()=>`crm.invoice.list?${j.stringify(u.value)}`),{immediate:!1,refetch:!0}).get().json();((I=n.rights)!=null&&I.invoice||n.webView)&&!s.isNew&&!s.invoiceId&&(h(),T("InvoicesTab:refresh").on(()=>{r.value&&(r.value=null,h())}));const V=g(()=>`${n.baseUrl}invoice/`+(s.invoiceId?`${s.invoiceId}/?iframe=1`:`new/?iframe=1&${s.entityType==="deal"?"deal_id":"contact"}=${s.entityId}`));D(B,e=>{e&&("params"in e&&(y.value=e.params.total_count),"data"in e&&Array.isArray(e.data)&&(r.value=[...r.value||[],...e.data]))});function E(){u.value.offset+p>=y.value||(u.value.offset+=p)}function F(e){typeof(e==null?void 0:e.detail)=="string"&&T(`InvoicesTab:${e.detail}`).emit()}return(e,l)=>o(n).rights&&!o(n).rights.invoice||o(n).webView&&o(N)===403?(t(),c(Y,{key:0})):s.isNew||s.invoiceId?(t(),c(O,{id:"iframe-invoice",key:Date.now(),src:V.value,"force-resize":!0,"static-position":!0,onClose:l[0]||(l[0]=i=>{var k;return e.$router.push({query:{...(k=i==null?void 0:i.detail)!=null&&k.id?{id:i.detail.id}:{}}}).then(()=>F(i))})},null,8,["src"])):(t(),a(f,{key:2},[r.value?o(w)?(t(),a("div",Q,C(o(w)),1)):(t(),a("div",R,[r.value.length?(t(),c(J,{key:0,space:"8"},{default:d(()=>[(t(!0),a(f,null,q(r.value,i=>(t(),c(K,{key:i.id,invoice:i,"route-location":{query:{id:i.id}},class:"invoice-wrapper"},null,8,["invoice","route-location"]))),128))]),_:1})):(t(),a(f,{key:1},[b(G,{message:e.$t(`noInvoicesBy${e.$filters.toTitleCase(s.entityType)}`)},{default:d(()=>[W]),_:1},8,["message"]),e.$route.meta.menuItemType!=="frame"?(t(),a("div",X,[b(U,{icon:"plus",onClick:l[1]||(l[1]=A(i=>e.$router.push({query:{is_new:"1"}}),["prevent"]))},{default:d(()=>[z(C(e.$t("addInvoice")),1)]),_:1})])):$("",!0)],64)),M((t(),a("div",Z,[o(_)?(t(),a("div",ee,ie)):$("",!0)])),[[o(H),([{isIntersecting:i}])=>{i&&!o(_)&&E()}]])])):(t(),c(P,{key:0,class:"tw-max-w-3xl tw-mx-auto"}))],64))}});export{ve as default};
