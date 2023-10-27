import{d as N,a as u,J as E,z as I,W as F,X as h,A as L,N as S,o as t,b as c,c as o,g as n,k,w as m,F as d,l as V,e as $,m as D,p as q,f as T,h as z,i as v}from"./main-ec0df6a4.js";import{v as A}from"./index-8d873219.js";import{S as j}from"./SkeletonList-192ed29f.js";import H from"./IframeView-4c5bf049.js";import{I as M}from"./IconButton-16cdf9d0.js";import{E as J}from"./EmptyList-9d13a159.js";import{C as O}from"./CustomColumn-959e6ffe.js";import{H as P}from"./HistoryTabLogInvoiceSummary-8257421c.js";import"./iframeObserver-be2a61ac.js";import"./index-4793d891.js";import"./dayjs-44a66bd3.js";const U={key:1,class:"state-error-hint"},W={key:2,class:"tw-max-w-3xl tw-mx-auto"},X=v("div",{class:"icon size-80"},[v("i",{class:"fa fa-file-invoice-dollar"})],-1),G={key:0,class:"tw-flex tw-justify-center tw-mt-4"},K={class:"tw-h-20"},Q={key:0,class:"tw-text-center tw-py-4"},R=v("div",{class:"spinner custom-p-16"},null,-1),Y=[R],f=30,ue=N({__name:"InvoicesTab",props:{entityType:{},entityId:{},isNew:{type:Boolean},invoiceId:{}},setup(g){const s=g,l=u({[`${s.entityType}_id`]:s.entityId,sort:"create_datetime",asc:0,offset:0,limit:f}),p=u(0),a=u(null),{data:C,isFetching:y,error:_,execute:w}=E(I(()=>`crm.invoice.list?${F.stringify(l.value)}`),{immediate:!1,refetch:!0}).get().json();!s.isNew&&!s.invoiceId&&(w(),h("InvoicesTab:refresh").on(()=>{a.value&&(a.value=null,w())}));const b=I(()=>`${L.baseUrl}invoice/`+(s.invoiceId?`${s.invoiceId}/?iframe=1`:`new/?iframe=1&${s.entityType==="deal"?"deal_id":"contact"}=${s.entityId}`));S(C,e=>{e&&("params"in e&&(p.value=e.params.total_count),"data"in e&&Array.isArray(e.data)&&(a.value=[...a.value||[],...e.data]))});function x(){l.value.offset+f>=p.value||(l.value.offset+=f)}function B(e){typeof(e==null?void 0:e.detail)=="string"&&h(`InvoicesTab:${e.detail}`).emit()}return(e,r)=>s.isNew||s.invoiceId?(t(),c(H,{id:"iframe-invoice",key:Date.now(),src:b.value,"force-resize":!0,"static-position":!0,onClose:r[0]||(r[0]=i=>e.$router.push({query:{}}).then(()=>B(i)))},null,8,["src"])):(t(),o(d,{key:1},[a.value?n(_)?(t(),o("div",U,k(n(_)),1)):(t(),o("div",W,[a.value.length?(t(),c(O,{key:0,space:"8"},{default:m(()=>[(t(!0),o(d,null,V(a.value,i=>(t(),c(P,{key:i.id,invoice:i,"route-location":{query:{id:i.id}},class:"invoice-wrapper"},null,8,["invoice","route-location"]))),128))]),_:1})):(t(),o(d,{key:1},[$(J,{message:e.$t(`noInvoicesBy${e.$filters.toTitleCase(s.entityType)}`)},{default:m(()=>[X]),_:1},8,["message"]),e.$route.meta.menuItemType!=="frame"?(t(),o("div",G,[$(M,{icon:"plus",onClick:r[1]||(r[1]=q(i=>e.$router.push({query:{is_new:"1"}}),["prevent"]))},{default:m(()=>[D(k(e.$t("addInvoice")),1)]),_:1})])):T("",!0)],64)),z((t(),o("div",K,[n(y)?(t(),o("div",Q,Y)):T("",!0)])),[[n(A),([{isIntersecting:i}])=>{i&&!n(y)&&x()}]])])):(t(),c(j,{key:0,class:"tw-max-w-3xl tw-mx-auto"}))],64))}});export{ue as default};