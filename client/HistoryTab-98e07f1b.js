import{d as w,R as X,a as z,j as G,o as a,c as _,e as v,w as r,i as o,n as f,r as U,m as A,k as s,p as I,f as g,g as m,F as j,l as F,h as P,v as V,_ as x,b as $,B,A as Q,S as ae,T as W,J as se,U as t,V as ie,z as ee,W as re,N as ce,X as de,Y as ue,Z as K,D as _e}from"./main-ec0df6a4.js";import{v as me}from"./index-8d873219.js";import{S as ge}from"./SkeletonList-192ed29f.js";import{E as pe}from"./EmptyList-9d13a159.js";import{C as E}from"./CustomColumn-959e6ffe.js";import{U as Y}from"./UserPic-87c13a4a.js";import{d as S}from"./dayjs-44a66bd3.js";import{H as ye}from"./HistoryTabLogInvoiceSummary-8257421c.js";const he={class:"tw-flex-auto black bold"},fe=o("div",null,null,-1),$e={class:"tw-flex-auto tw-text-sm"},ve={key:0},be=["href","onClick"],we=o("span",{class:"icon size-12 baseline tw-mr-2"},[o("i",{class:"fas fa-flag"})],-1),ke={class:"tw-whitespace-normal"},Ce={key:1,class:"tw-flex tw-gap-1.5"},Te=["href","onClick"],Le={class:"gray"},Ie={class:"tw-flex-auto"},Ee={class:"tw-flex-auto tw-text-sm"},Se=w({__name:"HistoryTabLog",props:{icon:{},title:{},title2:{},date:{},children:{},deal:{},contact:{}},setup(p){const e=p,d=X("entityType"),n=z(!1);return(l,u)=>{const i=G("RouterLink");return a(),_("div",{class:f(l.$style.log)},[v(E,{space:"2"},{default:r(()=>[o("div",{class:f([l.$style.logRow,"tw-items-center"])},[o("div",null,[v(Y,{url:l.icon.url,"bg-color":l.icon.color,"fa-icon":l.icon.fa||l.icon.fab,"fa-icon-style":l.icon.fab?"fab":"fas",size:32,"disable-rounded":!!l.icon.url},null,8,["url","bg-color","fa-icon","fa-icon-style","disable-rounded"])]),o("div",he,[U(l.$slots,"header",{},()=>[A(s(e.title),1)])])],2),o("div",{class:f(l.$style.logRow)},[fe,o("div",$e,[v(E,{space:"2"},{default:r(()=>[U(l.$slots,"summary"),e.deal?(a(),_("div",ve,[v(i,{to:{name:"deal",params:{id:e.deal.id}},custom:""},{default:r(({href:c,navigate:y})=>[o("a",{href:c,class:f([l.$constant.parentAppDisableRouterClass,"tw-whitespace-nowrap"]),onClick:I(h=>l.$route.meta.menuItemType!=="frame"&&y(h),["stop"])},[we,o("span",ke,s(e.deal.name),1)],10,be)]),_:1},8,["to"])])):g("",!0),m(d)==="live"&&e.contact?(a(),_("div",Ce,[v(Y,{url:e.contact.userpic,size:20},null,8,["url"]),v(i,{to:{name:"contact",params:{id:e.contact.id}},custom:""},{default:r(({href:c,navigate:y})=>[o("a",{href:c,class:f([l.$constant.parentAppDisableRouterClass,"tw-line-clamp-2 tw-self-center"]),onClick:I(h=>l.$route.meta.menuItemType!=="frame"&&y(h),["stop"])},s(e.contact.name),11,Te)]),_:1},8,["to"])])):g("",!0),o("div",Le,s(m(S)(e.date).format("HH:mm"))+" "+s(e.title2),1)]),_:3})])],2),n.value&&Array.isArray(e.children)?(a(!0),_(j,{key:0},F(e.children,c=>(a(),_("div",{key:c.id},[o("div",{class:f([l.$style.logRow,"tw-items-start"])},[o("div",null,[o("div",{class:f([l.$style.pointer,"tw-translate-y-2"])},null,2)]),o("div",Ie,[U(l.$slots,"children",{child:c})])],2)]))),128)):g("",!0),typeof e.children=="number"&&e.children>1||Array.isArray(e.children)&&e.children.length?(a(),_("div",{key:1,class:f(l.$style.logRow)},[o("div",null,[P(o("div",{class:f(l.$style.pointer)},null,2),[[V,!n.value]])]),o("div",Ee,[U(l.$slots,"counter",{showChildren:n.value,click:()=>n.value=!n.value})])],2)):g("",!0)]),_:3})],2)}}}),Re="_log_vip2u_1",De="_logRow_vip2u_13",je="_pointer_vip2u_24",He={log:Re,logRow:De,pointer:je},Ae={$style:He},R=x(Se,[["__cssModules",Ae]]),Ne={key:0,class:"tw-line-clamp-2"},Me=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),Oe=w({__name:"HistoryTabLogReminder",props:{log:{},children:{}},setup(p){const e=p;return(d,n)=>{var l;return a(),$(R,{title:d.$filters.toTitleCase(e.log.action_name),text:(l=d.log.reminder)==null?void 0:l.content,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[e.log.reminder?(a(),_("div",Ne,s(e.log.reminder.content),1)):g("",!0)]),counter:r(()=>[]),menu:r(()=>[Me]),_:1},8,["title","text","date","icon","children","contact","deal"])}}}),Pe={key:0},ze=["onClick"],Fe={class:"gray tw-text-sm"},xe=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),Be=w({__name:"HistoryTabLogOrder",props:{log:{},children:{}},setup(p){const e=p;return(d,n)=>{var l;return a(),$(R,{title:d.$filters.toTitleCase(e.log.action_name),title2:(l=e.log.actor)==null?void 0:l.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[v(E,{space:"2"},{default:r(()=>[e.log.order?(a(),_("div",Pe,[A(s(d.$t("order"))+" "+s(e.log.order.number)+" ",1),o("strong",null,s(d.$filters.toCurrency(e.log.order.total,e.log.order.currency)),1)])):g("",!0),e.log.content?(a(),_("div",{key:1,class:f(d.$style.comment)},s(e.log.content),3)):g("",!0)]),_:1})]),counter:r(({click:u,showChildren:i})=>[o("a",{onClick:I(u,["prevent"])},s(i?d.$t("collapse"):`+${d.$t("events",e.children.length)}`),9,ze)]),children:r(({child:u})=>[v(E,{space:"2"},{default:r(()=>{var i;return[o("div",null,s(d.$filters.toTitleCase(u.action_name)),1),u.content?(a(),_("div",{key:0,class:f(d.$style.comment)},s(u.content),3)):g("",!0),o("div",Fe,s(m(S)(u.create_datetime).format("HH:mm"))+" "+s((i=u.actor)==null?void 0:i.name),1)]}),_:2},1024)]),menu:r(()=>[xe]),_:1},8,["title","title2","date","icon","children","contact","deal"])}}}),Ue="_comment_1wsv4_1",Ve={comment:Ue},Ge={$style:Ve},Ye=x(Be,[["__cssModules",Ge]]),qe=["onClick"],Je={class:"gray tw-text-sm"},Ke=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),We=w({__name:"HistoryTabLogNote",props:{log:{},children:{}},setup(p){const e=p,{t:d}=B();return(n,l)=>{var u;return a(),$(R,{title:n.$filters.toTitleCase(e.log.action_name),title2:(u=e.log.actor)==null?void 0:u.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[e.log.content?(a(),_("div",{key:0,class:f(n.$style.note)},s(e.log.content),3)):g("",!0)]),counter:r(({click:i,showChildren:c})=>[o("a",{onClick:I(i,["prevent"])},s(c?m(d)("collapse"):`+${m(d)("events",e.children.length)}`),9,qe)]),children:r(({child:i})=>[v(E,{space:"2"},{default:r(()=>{var c;return[o("div",null,s(n.$filters.toTitleCase(i.action_name)),1),o("div",Je,s(m(S)(i.create_datetime).format("HH:mm"))+" "+s((c=i.actor)==null?void 0:c.name),1)]}),_:2},1024)]),menu:r(()=>[Ke]),_:1},8,["title","title2","date","icon","children","contact","deal"])}}}),Xe="_note_x4pr5_1",Ze={note:Xe},Qe={$style:Ze},et=x(We,[["__cssModules",Qe]]),tt={key:0},ot={key:0,class:"bold tw-mb-2"},st={class:"tw-line-clamp-2"},nt={class:"tw-flex tw-items-center tw-space-x-2"},lt=["href"],at=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),it=w({__name:"HistoryTabLogMessage",props:{log:{},children:{}},setup(p){const e=p,d=X("entityType");return(n,l)=>{var c,y;const u=G("temaplate"),i=G("RouterLink");return a(),$(R,{title:((c=e.log.actor)==null?void 0:c.name)||n.$t("noName"),date:e.log.create_datetime,icon:e.log.icon,children:(y=e.log.message)==null?void 0:y.conversation_count,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>{var h,T;return[(h=e.log.message)!=null&&h.body_plain?(a(),_("div",tt,[o("div",{class:f(n.$style.baloon)},[e.log.message.subject?(a(),_("div",ot,s(e.log.message.subject),1)):g("",!0),o("div",st,s(e.log.message.body_plain),1)],2),(((T=e.log.message)==null?void 0:T.conversation_count)??0)>1?(a(),_(j,{key:0},[o("div",{class:f(n.$style.baloonShadow1)},null,2),o("div",{class:f(n.$style.baloonShadow2)},null,2)],64)):g("",!0)])):g("",!0)]}),counter:r(()=>{var h,T;return[o("div",nt,[o("div",{class:f(n.$style.userPics)},[(a(!0),_(j,null,F((h=e.log.message)==null?void 0:h.conversation_participants,M=>(a(),$(Y,{key:M.id,size:32,url:M.userpic},null,8,["url"]))),128))],2),(T=e.log.message)!=null&&T.conversation_id?(a(),$(u,{key:0},{default:r(()=>[n.$route.meta.menuItemType==="frame"?(a(),$(u,{key:0},{default:r(()=>[o("a",{href:`${m(Q).baseUrl}message/conversation/${e.log.message.conversation_id}/?view=chat`,class:f(n.$constant.parentAppDisableRouterClass)},s(`+${n.$t("messages",(e.log.message.conversation_count??1)-1)}`),11,lt)]),_:1})):m(Q).webView?(a(),_("a",{key:2,onClick:l[0]||(l[0]=I(M=>m(ae).emit("spa:navigateTo",{name:"conversation",id:e.log.message.conversation_id}),["prevent"]))},s(`+${n.$t("messages",(e.log.message.conversation_count??1)-1)}`),1)):(a(),$(i,{key:1,to:{name:`${m(d)}MessagesTab`,query:{conversationId:e.log.message.conversation_id}},class:f(n.$constant.parentAppDisableRouterClass)},{default:r(()=>[A(s(`+${n.$t("messages",(e.log.message.conversation_count??1)-1)}`),1)]),_:1},8,["to","class"]))]),_:1})):g("",!0)])]}),children:r(({child:h})=>[A(s(h.action_name),1)]),menu:r(()=>[at]),_:1},8,["title","date","icon","children","contact","deal"])}}}),rt="_userPics_1h7lu_1",ct="_baloon_1h7lu_8",dt="_baloonShadow1_1h7lu_16",ut="_baloonShadow2_1h7lu_24",_t={userPics:rt,baloon:ct,baloonShadow1:dt,baloonShadow2:ut},mt={$style:_t},gt=x(it,[["__cssModules",mt]]),pt=["onClick"],yt={class:"tw-text-sm gray"},ht=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),ft=w({__name:"HistoryTabLogInvoice",props:{log:{},children:{}},setup(p){const e=p,{t:d}=B();return(n,l)=>{var u;return a(),$(R,{title:n.$filters.toTitleCase(e.log.action_name),title2:(u=e.log.actor)==null?void 0:u.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[v(ye,{invoice:e.log.invoice},null,8,["invoice"])]),counter:r(({click:i,showChildren:c})=>[o("a",{onClick:I(i,["prevent"])},s(c?m(d)("collapse"):`+${m(d)("events",e.children.length)}`),9,pt)]),children:r(({child:i})=>[v(E,{space:"2"},{default:r(()=>{var c;return[o("div",null,s(n.$filters.toTitleCase(i.action_name)),1),o("div",yt,s(m(S)(i.create_datetime).format("HH:mm"))+" "+s((c=i.actor)==null?void 0:c.name),1)]}),_:2},1024)]),menu:r(()=>[ht]),_:1},8,["title","title2","date","icon","children","contact","deal"])}}}),$t={key:0},vt=["href"],bt={class:"gray small"},wt=["onClick"],kt={class:"gray tw-text-sm"},Ct=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),Tt=w({__name:"HistoryTabLogFile",props:{log:{},children:{}},setup(p){const e=p,{t:d}=B();return(n,l)=>{var u;return a(),$(R,{title:n.$filters.toTitleCase(e.log.action_name),title2:(u=e.log.actor)==null?void 0:u.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[e.log.file?(a(),_("div",$t,[o("a",{href:e.log.file.url,class:f(n.$constant.parentAppDisableRouterClass)},s(e.log.file.name),11,vt),A("  "),o("span",bt,s(e.log.file.size)+" KB",1)])):g("",!0)]),counter:r(({click:i,showChildren:c})=>[o("a",{onClick:I(i,["prevent"])},s(c?m(d)("collapse"):`+${m(d)("events",e.children.length)}`),9,wt)]),children:r(({child:i})=>[v(E,{space:"2"},{default:r(()=>{var c;return[o("div",null,s(n.$filters.toTitleCase(i.action_name)),1),o("div",kt,s(m(S)(i.create_datetime).format("HH:mm"))+" "+s((c=i.actor)==null?void 0:c.name),1)]}),_:2},1024)]),menu:r(()=>[Ct]),_:1},8,["title","title2","date","icon","children","contact","deal"])}}}),Lt={key:0,class:"tw-flex tw-space-x-2 tw-items-center"},It={key:0,class:"tw-line-through gray"},Et={key:1,class:"gray"},St={key:2},Rt={key:1,class:"tw-flex tw-space-x-2 tw-items-center"},Dt={key:0,class:"log-deal-badge"},jt={class:"icon size-12 rounded"},Ht={class:"log-deal-badge__text"},At={key:1,class:"gray"},Nt={key:2,class:"log-deal-badge"},Mt={class:"icon size-12 rounded"},Ot={class:"log-deal-badge__text"},Pt=w({__name:"HistoryTabLogDealSummary",props:{log:{}},setup(p){const e=p;return(d,n)=>(a(),_(j,null,[e.log.before||e.log.after?(a(),_("div",Lt,[e.log.before?(a(),_("div",It,s(e.log.before),1)):g("",!0),e.log.before&&e.log.after?(a(),_("span",Et,"→")):g("",!0),e.log.after?(a(),_("div",St,s(e.log.after),1)):g("",!0)])):g("",!0),e.log.stage_before||e.log.stage_after?(a(),_("div",Rt,[e.log.stage_before?(a(),_("div",Dt,[o("span",jt,[o("i",{class:"fas fa-circle circle",style:W({color:e.log.stage_before.color})},null,4)]),o("span",Ht,s(e.log.stage_before.name),1)])):g("",!0),e.log.stage_before&&e.log.stage_after?(a(),_("span",At,"→")):g("",!0),e.log.stage_after?(a(),_("div",Nt,[o("span",Mt,[o("i",{class:"fas fa-circle circle",style:W({color:e.log.stage_after.color})},null,4)]),o("span",Ot,s(e.log.stage_after.name),1)])):g("",!0)])):g("",!0)],64))}});const te=x(Pt,[["__scopeId","data-v-df4a6a10"]]),zt=["href","onClick"],Ft=["onClick"],xt={class:"tw-text-sm empty:tw-hidden"},Bt={class:"gray tw-text-sm"},Ut=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),Vt=w({__name:"HistoryTabLogDeal",props:{log:{},children:{}},setup(p){const e=p,d=X("entityType");return(n,l)=>{var i;const u=G("RouterLink");return a(),$(R,{title:n.$filters.toTitleCase(e.log.action_name),title2:(i=e.log.actor)==null?void 0:i.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children},{header:r(()=>[A(s(n.$filters.toTitleCase(e.log.action_name))+" ",1),["contact","live"].includes(m(d))?(a(),_(j,{key:0},[e.log.deal?(a(),$(u,{key:0,to:{name:"deal",params:{id:e.log.deal.id}},custom:""},{default:r(({href:c,navigate:y})=>[o("a",{href:c,class:f(n.$constant.parentAppDisableRouterClass),onClick:I(h=>n.$route.meta.menuItemType!=="frame"&&y(h),["stop"])},s(e.log.deal.name),11,zt)]),_:1},8,["to"])):g("",!0)],64)):g("",!0)]),summary:r(()=>[v(te,{log:e.log},null,8,["log"])]),counter:r(({click:c,showChildren:y})=>[o("a",{onClick:I(c,["prevent"])},s(y?n.$t("collapse"):`+${n.$t("events",e.children.length)}`),9,Ft)]),children:r(({child:c})=>[v(E,{space:"2"},{default:r(()=>{var y;return[o("div",null,s(n.$filters.toTitleCase(c.action_name)),1),o("div",xt,[v(te,{log:c},null,8,["log"])]),o("div",Bt,s(m(S)(c.create_datetime).format("HH:mm"))+" "+s((y=c.actor)==null?void 0:y.name),1)]}),_:2},1024)]),menu:r(()=>[Ut]),_:1},8,["title","title2","date","icon","children"])}}}),Gt={key:0},Yt=["onClick"],qt={class:"gray tw-text-sm"},Jt=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),Kt=w({__name:"HistoryTabLogContact",props:{log:{},children:{}},setup(p){const e=p,{t:d}=B();return(n,l)=>{var u;return a(),$(R,{title:n.$filters.toTitleCase(e.log.action_name),title2:(u=e.log.actor)==null?void 0:u.name,date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[e.log.content?(a(),_("div",Gt,s(e.log.content),1)):g("",!0)]),counter:r(({click:i,showChildren:c})=>[o("a",{onClick:I(i,["prevent"])},s(c?m(d)("collapse"):`+${m(d)("events",e.children.length)}`),9,Yt)]),children:r(({child:i})=>[v(E,{space:"2"},{default:r(()=>{var c;return[o("div",null,s(n.$filters.toTitleCase(i.action_name)),1),o("div",qt,s(m(S)(i.create_datetime).format("HH:mm"))+" "+s((c=i.actor)==null?void 0:c.name),1)]}),_:2},1024)]),menu:r(()=>[Jt]),_:1},8,["title","title2","date","icon","children","contact","deal"])}}}),Wt=o("i",{class:"fas fa-play-circle text-green"},null,-1),Xt=[Wt],Zt=o("i",{class:"fas fa-pause-circle text-green"},null,-1),Qt=[Zt],eo=o("i",{class:"fas fa-exclamation-circle text-yellow"},null,-1),to=[eo],oo=w({__name:"TabLogCallRecord",props:{pluginRecordId:{},pluginCallId:{},pluginId:{}},setup(p){const e=p,d=z(!1),n=z(!1);let l;async function u(){if(!l){const{data:i}=await se(`crm.call.recordUrl?call_id=${e.pluginCallId}&record_id=${e.pluginRecordId}&plugin=${e.pluginId}`).get().json();i&&(l=new Audio(i.value),l.onplaying=function(){d.value=!0},l.onpause=function(){d.value=!1},l.onerror=function(){n.value=!0,d.value=!1})}l&&(d.value?l.pause():l.play().catch(i=>i))}return(i,c)=>(a(),_("span",{class:"tw-cursor-pointer",onClick:u},[P(o("span",null,Xt,512),[[V,!d.value&&!n.value]]),P(o("span",null,Qt,512),[[V,d.value]]),P(o("span",null,to,512),[[V,n.value]])]))}}),so={key:0,class:"tw-flex tw-items-center tw-space-x-2 gray"},no={key:0},lo={key:1},ao=o("i",{class:"fas fa-spinner"},null,-1),io=[ao],ro={class:"tw-flex tw-items-center tw-space-x-2"},co={class:"tw-flex -tw-space-x-2"},uo=["onClick"],_o={key:0,class:"tw-text-sm gray"},mo={class:"gray tw-text-sm"},go=o("i",{class:"fas fa-ellipsis-h gray"},null,-1),po=w({__name:"HistoryTabLogCall",props:{log:{},children:{}},setup(p){const e=p,{t:d}=B();function n(l){var u,i,c,y;return l.direction==="IN"?`${((u=l.contact)==null?void 0:u.name)||l.client_phone_formatted} → ${((i=l.user)==null?void 0:i.name)||l.plugin_user_number}`:`${((c=l.user)==null?void 0:c.name)||l.plugin_user_number} → ${((y=l.contact)==null?void 0:y.name)||l.client_phone_formatted}`}return(l,u)=>(a(),$(R,{title:e.log.content||e.log.action_name,title2:n(e.log.call),date:e.log.create_datetime,icon:e.log.icon,children:e.children,contact:e.log.contact,deal:e.log.deal},{summary:r(()=>[e.log.call.status_id!=="DROPPED"?(a(),_("div",so,[e.log.call.duration?(a(),_("div",no,[A(s(m(d)("duration"))+": "+s(l.$filters.secsToMinutes(e.log.call.duration))+" ",1),e.log.call.plugin_record_id?(a(),$(oo,{key:0,"plugin-record-id":e.log.call.plugin_record_id,"plugin-call-id":e.log.call.plugin_call_id,"plugin-id":e.log.call.plugin_id},null,8,["plugin-record-id","plugin-call-id","plugin-id"])):g("",!0)])):g("",!0),["PENDING","CONNECTED"].includes(e.log.call.status_id)?(a(),_("div",lo,io)):g("",!0)])):g("",!0)]),counter:r(({click:i,showChildren:c})=>[o("div",ro,[o("div",co,[(a(!0),_(j,null,F([e.log,...e.children].reduce((y,h)=>{var T;return(T=h.call.user)!=null&&T.userpic&&!y.includes(h.call.user.userpic)&&y.push(h.call.user.userpic),y},[]),(y,h)=>(a(),_("div",{key:h,class:"tw-outline-4 tw-outline tw-outline-waBlank tw-rounded-full",style:W(`z-index: ${h}`)},[v(Y,{size:32,url:y},null,8,["url"])],4))),128))]),o("a",{onClick:I(i,["prevent"])},s(c?m(d)("collapse"):`+${m(d)("calls",e.children.length)}`),9,uo)])]),children:r(({child:i})=>[i.object_type==="CALL"?(a(),$(E,{key:0,space:"2"},{default:r(()=>[o("div",null,s(i.content),1),i.call.duration?(a(),_("div",_o,s(m(d)("duration"))+": "+s(l.$filters.secsToMinutes(i.call.duration)),1)):g("",!0),o("div",mo,s(m(S)(i.create_datetime).format("HH:mm"))+" "+s(n(i.call)),1)]),_:2},1024)):g("",!0)]),menu:r(()=>[go]),_:1},8,["title","title2","date","icon","children","contact","deal"]))}}),q=t.object({id:t.number(),name:t.string(),userpic:t.string()}),D=t.object({id:t.number(),create_datetime:t.string().datetime(),action:t.string().nullable(),action_name:t.string(),object_id:t.number().nullable(),actor:q.nullable().optional(),before:t.string().optional().nullable(),after:t.string().optional().nullable(),content:t.string().optional().nullable(),icon:t.object({url:t.string(),color:t.string(),fa:t.string(),fab:t.string()}).partial(),deal:t.object({id:t.number(),name:t.string()}).optional().nullable()}),yo=D.extend({call:t.object({id:t.number(),client_contact_id:t.number(),client_phone_formatted:t.string(),comment:t.string().nullable(),direction:t.union([t.literal("IN"),t.literal("OUT")]),duration:t.number(),contact:q.optional(),user:q.optional().nullable(),plugin_id:t.string(),plugin_call_id:t.string(),plugin_record_id:t.string().nullable(),plugin_user_number:t.string(),status_id:t.union([t.literal("PENDING"),t.literal("CONNECTED"),t.literal("FINISHED"),t.literal("DROPPED"),t.literal("REDIRECTED"),t.literal("VOICEMAIL")])}),object_type:t.literal("CALL")}),ho=D.extend({message:t.object({id:t.number(),subject:t.string().optional().nullable(),body_plain:t.string(),conversation_id:t.number().nullable(),conversation_count:t.number().optional(),conversation_participants:t.array(q).optional()}).optional(),object_type:t.literal("MESSAGE")}),fo=D.extend({reminder:t.object({id:t.number(),content:t.string()}).nullable(),object_type:t.literal("REMINDER")}),$o=D.extend({object_type:t.literal("NOTE")}),vo=D.extend({invoice:t.object({amount:t.number(),currency_id:t.string(),id:t.number(),invoice_date:t.string(),number:t.string(),summary:t.string().nullable(),state_id:t.enum(["PENDING","PAID","REFUNDED","ARCHIVED","DRAFT","PROCESSING"]),state_name:t.string()}).optional(),object_type:t.literal("INVOICE")}),bo=D.extend({stage_before:t.object({id:t.number(),name:t.string(),color:t.string()}).optional(),stage_after:t.object({id:t.number(),name:t.string(),color:t.string()}).optional(),object_type:t.literal("DEAL")}),wo=D.extend({file:t.object({id:t.number(),comment:t.string().nullable(),create_datetime:t.string(),name:t.string(),size:t.number(),url:t.string()}).optional(),object_type:t.literal("FILE")}),ko=D.extend({object_type:t.literal("CONTACT")}),Co=D.extend({order:t.object({id:t.number(),number:t.string(),currency:t.string(),total:t.number()}).optional(),object_type:t.literal("ORDER")}),To=t.discriminatedUnion("object_type",[yo,ho,fo,$o,vo,bo,wo,ko,Co]);function oe(p){return Array.isArray(p)?p.filter(e=>{const d=To.safeParse(e);return d.success||console.warn(e,e.object_type,d.error.issues),d.success}):[]}const Lo={key:1},Io={key:2,class:"tw-max-w-3xl tw-mx-auto"},Eo={class:"gray uppercase smaller"},So={class:"tw-h-1"},Ro=o("div",{class:"spinner custom-p-16"},null,-1),Do=[Ro],jo=o("div",{class:"icon size-80"},[o("i",{class:"fas fa-bolt"})],-1),Ho=100,Bo=w({__name:"HistoryTab",props:{entityType:{},entityId:{},filter:{},userId:{}},setup(p){const e=p,d={CONTACT:Kt,REMINDER:Oe,CALL:po,MESSAGE:gt,NOTE:et,INVOICE:ft,DEAL:Vt,FILE:Tt,ORDER:Ye},n=["notes","files","invoices","deals","contacts","messages","calls","reminders","orders"],l={note:n[0],file:n[1],invoice:n[2],deal:n[3],contact:n[4],message:n[5],call:n[6],reminder:n[7],order_log:n[8]};ie("entityType",e.entityType);const u=z([]),i=ee(()=>le(u.value)),c=z({filters:e.filter&&e.filter!=="all"?[l[e.filter]]:[...n],...e.entityType!=="live"&&e.entityId?{[`${e.entityType}_id`]:e.entityId}:{},...!isNaN(Number(e.userId))&&e.userId!=="0"?{user_id:Number(e.userId)}:{},limit:Ho}),{data:y,isFetching:h,error:T}=se(ee(()=>`crm.history?${re.stringify(c.value)}`),{refetch:!0}).get().json();ce(y,L=>{L&&Array.isArray(L.log)&&(u.value="min_id"in c.value?[...oe(L.log),...u.value]:[...u.value,...oe(L.log)])}),de(ue).on(M);function M(){u.value[0]&&(c.value.min_id=u.value[0].id)}function ne(){if(!u.value.length)return;const L=u.value[u.value.length-1].id;L&&(c.value.max_id=L)}function le(L){const J=L.reduce((k,C)=>{const b=S(C.create_datetime).format("MM/DD/YYYY");return b in k?k[b].push(C):k[b]=[C],k},{}),N=[],H={};for(const k in J)H[k]=J[k].reduce((C,b)=>{var Z;const O=`${b.object_type}_${b.object_type==="MESSAGE"?((Z=b.message)==null?void 0:Z.conversation_id)||K().toString():b.object_type==="CALL"?b.call.client_contact_id||K().toString():["NOTE","FILE"].includes(b.object_type)?"":b.object_id||K().toString()}`;return b.object_type==="MESSAGE"&&N.includes(O)||(N.push(O),O in C?C[O].push(b):C[O]=[b]),C},{}),Object.keys(H[k]).length||delete H[k];return H}return(L,J)=>m(h)&&!u.value.length?(a(),$(ge,{key:0,class:"tw-max-w-3xl tw-mx-auto"})):m(T)?(a(),_("div",Lo,s(m(T)),1)):u.value.length?(a(),_("div",Io,[v(E,{space:"4"},{default:r(()=>[(a(!0),_(j,null,F(i.value,(N,H)=>(a(),_("div",{key:H},[o("div",Eo,s(m(S)(H).format("LL")),1),(a(!0),_(j,null,F(N,(k,C)=>(a(),$(_e(d[C.split("_")[0]]),{key:C,log:k[0],children:k.slice(1)},null,8,["log","children"]))),128))]))),128))]),_:1}),P(o("div",So,null,512),[[m(me),([{isIntersecting:N}])=>{N&&!m(h)&&ne()}]]),o("div",{class:f(["tw-text-center tw-py-4",m(h)?"tw-opacity-100":"tw-opacity-0"])},Do,2)])):(a(),$(pe,{key:3,message:L.$t(e.entityType==="live"?"noTimelineByConditions":"emptyTimeline")},{default:r(()=>[jo]),_:1},8,["message"]))}});export{Bo as default};
