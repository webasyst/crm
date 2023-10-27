import{d as m,z as p,o as t,c as s,b as _,w as y,i as a,g as n,A as c,p as f,k as i,T as b,_ as g}from"./main-ec0df6a4.js";import{C as k}from"./CustomColumn-959e6ffe.js";import{d as C}from"./dayjs-44a66bd3.js";const h={class:"log-invoice-summary"},I=["href"],D={key:1,class:"bold black"},w={class:"bold black"},L={class:"gray"},S={key:1},$=m({__name:"HistoryTabLogInvoiceSummary",props:{invoice:{},routeLocation:{}},setup(d){const e=d,l={PENDING:"var(--invoice-state-pending-color)",PAID:"var(--invoice-state-paid-color)",REFUNDED:"var(--invoice-state-refunded-color)",ARCHIVED:"var(--invoice-state-archived-color)",DRAFT:"var(--invoice-state-draft-color)",PROCESSING:"var(--invoice-state-processing-color)"},v=p(()=>{var o;return{backgroundColor:(o=e.invoice)!=null&&o.state_id?l[e.invoice.state_id]:""}});return(o,r)=>(t(),s("div",null,[e.invoice?(t(),_(k,{key:0,space:"none"},{default:y(()=>[a("div",h,[n(c).webView?(t(),s("span",D,i(o.$t("invoice"))+" #"+i(e.invoice.number),1)):(t(),s("a",{key:0,href:`${n(c).baseUrl}invoice/${e.invoice.id}/`,class:"tw-font-medium",onClick:r[0]||(r[0]=f(u=>{o.$route.meta.menuItemType!=="frame"&&e.routeLocation&&(u.preventDefault(),o.$router.push(e.routeLocation))},["stop"]))},i(o.$t("invoice"))+" #"+i(e.invoice.number),9,I)),a("div",w,i(o.$filters.toCurrency(e.invoice.amount,e.invoice.currency_id)),1),a("div",L,i(n(C)(e.invoice.invoice_date).format("LL")),1),a("div",{class:"badge nowrap tw-inline-flex",style:b([v.value])},i(e.invoice.state_name),5)]),a("div",null,i(e.invoice.summary),1)]),_:1})):(t(),s("div",S,i(o.$t("invoiceDeleted")),1))]))}});const H=g($,[["__scopeId","data-v-c518d22d"]]);export{H};