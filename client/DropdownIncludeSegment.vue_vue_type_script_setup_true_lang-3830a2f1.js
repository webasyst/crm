import{d as V,o as e,b as S,c as o,k as t,r as W,S as P,y as T,X as j,a8 as x,ab as B,g as d,w as v,m as b,i as l,F as h,p as M,e as L,f as D,l as F,W as z,z as J,a as N,A as C,C as O,t as A,M as E,n as I}from"./main-e63d6b61.js";import{U}from"./UserPic-e63dca94.js";import{W as X}from"./WaSpinner-7a41251d.js";import{_ as q}from"./InputWithUsers.vue_vue_type_script_setup_true_lang-32066fbd.js";import{M as G,_ as H}from"./DropDown-b752b133.js";const K={key:1},Q=V({__name:"WaDialogLoading",props:{isFetching:{type:Boolean},error:{}},setup(k){const m=k;return(s,y)=>m.isFetching?(e(),S(X,{key:0})):m.error?(e(),o("div",K,t(s.error),1)):W(s.$slots,"default",{key:2})}}),Y=["disabled","onClick"],Z={key:0},ee={class:"text-orange"},te={class:"tw-flex tw-flex-auto tw-w-1/2 tw-space-x-2 tw-items-center"},se={class:"tw-truncate"},ae={class:"tw-flex-none"},ne={class:"tw-whitespace-nowrap"},Se=V({__name:"FormContactDelete",props:{contactIds:{},disableContactsPageRedirect:{type:Boolean}},emits:["close"],setup(k,{emit:m}){const s=k,y=P(),c=T(),p=c.deleteContacts(s.contactIds),i=j(`crm.contact.links?${x.stringify({id:s.contactIds})}`).get().json();async function $(){if(i.error.value){i.execute();return}await p.execute()&&(m("close"),s.disableContactsPageRedirect||y.push(c.lastRouteLocation?c.lastRouteLocation:{name:"contacts"}))}return(a,_)=>(e(),S(z,{onClose:_[0]||(_[0]=u=>m("close"))},B({header:v(()=>[b(t(a.$t("deleteContacts",s.contactIds.length)),1)]),submit:v(()=>[l("button",{disabled:d(i).isFetching.value||d(p).isFetching.value,onClick:M($,["prevent"])},[d(i).isFetching.value?(e(),o(h,{key:0},[b(t(a.$t("checkingLinks")),1)],64)):d(i).error.value?(e(),o(h,{key:1},[b(t(a.$t("recheckLinks")),1)],64)):d(p).isFetching.value?(e(),o(h,{key:2},[b(t(a.$t("deleting")),1)],64)):(e(),o(h,{key:3},[b(t(a.$t("deleteAnyway")),1)],64))],8,Y)]),default:v(()=>[L(Q,{"is-fetching":d(i).isFetching.value,error:d(i).error.value},B({_:2},[d(i).data.value&&"linked_contacts"in d(i).data.value?{name:"default",fn:v(()=>[l("p",null,t(a.$t("deleteContactsMessage1")),1),d(i).data.value.linked_contacts.length?(e(),o("p",Z,[l("strong",ee,t(a.$t("importantNote")),1),b(" "+t(a.$t("deleteContactsMessage2"))+" "+t(a.$t("deleteContactsMessage3")),1)])):D("",!0),(e(!0),o(h,null,F(d(i).data.value.linked_contacts,u=>(e(),o("div",{key:u.id},[(e(!0),o(h,null,F(u.links,(g,w)=>(e(),o("div",{key:w},[(e(!0),o(h,null,F(g.roles,(R,n)=>(e(),o("div",{key:n,class:"tw-flex tw-space-x-2 tw-justify-between tw-py-1 bordered-bottom tw-text-sm"},[l("div",te,[L(U,{url:u.userpic,size:16},null,8,["url"]),l("div",se,t(u.name),1),l("div",ae,t(g.app)+"/"+t(R.role),1)]),l("div",ne,t(a.$t("links",R.count)),1)]))),128))]))),128))]))),128))]),key:"0"}:void 0]),1032,["is-fetching","error"])]),_:2},[d(p).isFetching.value?void 0:{name:"error",fn:v(()=>[b(t(d(p).error.value),1)]),key:"0"}]),1024))}}),oe=["disabled","onClick"],le={key:0},ie={class:"hint tw-mb-2"},Re=V({__name:"FormContactChangeResponsible",props:{contactIds:{},currentResponsible:{},contactName:{}},emits:["close"],setup(k,{emit:m}){const s=k,y=J(),c=N(s.currentResponsible??null),p=C(()=>y.responsibleAssign(s.contactIds,c.value)),i=C(()=>s.currentResponsible?JSON.stringify(s.currentResponsible)===JSON.stringify(c.value):!1),$=C(()=>s.currentResponsible&&!c.value),a=C({get(){return c.value?[c.value]:[]},set(u){c.value=u[0]}});async function _(){await p.value.execute(),p.value.error.value||m("close")}return(u,g)=>(e(),S(z,{"use-cancel-as-button-label":!0,onClose:g[1]||(g[1]=w=>m("close"))},{header:v(()=>[b(t(u.$t(s.currentResponsible?"changeResponsible":"addResponsible")),1)]),submit:v(()=>[l("button",{disabled:i.value||p.value.isFetching.value||!$.value&&!c.value,onClick:M(_,["prevent"])},t(u.$t($.value?"clearResponsible":"save")),9,oe)]),error:v(()=>[b(t(p.value.error.value),1)]),default:v(()=>[s.contactName?(e(),o("p",le,t(`${u.$t("addResponsibleMessage")} ${s.contactName}`),1)):D("",!0),l("div",ie,t(u.$t(c.value?i.value?"currentResponsible":"newResponsible":"selectResponsible")),1),L(q,{modelValue:a.value,"onUpdate:modelValue":g[0]||(g[0]=w=>a.value=w),"is-multiple":!1},null,8,["modelValue"])]),_:1}))}}),re={class:"tw-sticky tw-top-0 tw-p-2 tw-bg-waBlank tw-z-10"},ce={class:"toggle rounded smallest"},ue={key:0,class:"tw-p-2"},de=l("div",{class:"spinner"},null,-1),pe=[de],ve=["disabled","onClick"],me={key:0,class:"icon"},fe=l("i",{class:"fas fa-spinner text-yellow tw-animate-spin"},null,-1),he=[fe],_e=["title"],ge={key:1,class:"small tw-px-4 tw-pb-4"},Fe=V({__name:"DropdownIncludeSegment",props:{modelValue:{},contactIds:{}},emits:["update:modelValue"],setup(k,{emit:m}){const s=k,y=O(),{sharedSegment:c,mySegment:p,isFetching:i}=A(y);y.refetch();const{contact:$}=A(E()),a=N(!1),_=N(new Set),u=C(()=>p.value.filter(n=>n.type==="category").filter(n=>{var f;return!((f=$.value)!=null&&f.segments.map(r=>r.id).includes(n.id))})),g=C(()=>c.value.filter(n=>n.type==="category").filter(n=>{var f;return!((f=$.value)!=null&&f.segments.map(r=>r.id).includes(n.id))})),w=C(()=>a.value?g.value:u.value);async function R(n){Array.isArray(s.contactIds)&&(_.value.has(n)||(_.value.add(n),await y.includeContactsToSegment(n,s.contactIds).execute(),_.value.delete(n))),"modelValue"in s&&m("update:modelValue",n)}return(n,f)=>(e(),o(h,null,[l("div",re,[l("div",ce,[l("span",{class:I({selected:!a.value}),onClick:f[0]||(f[0]=r=>a.value=!1)},t(n.$t("my",2)),3),l("span",{class:I({selected:a.value}),onClick:f[1]||(f[1]=r=>a.value=!0)},t(n.$t("shared",2)),3)])]),d(i)?(e(),o("div",ue,pe)):(e(),o(h,{key:1},[w.value.length?(e(),S(G,{key:0},{default:v(()=>[(e(!0),o(h,null,F(w.value,r=>(e(),S(H,{key:r.id,class:I({selected:s.modelValue===r.id})},{default:v(()=>[l("a",{disabled:!r.is_editable,onClick:M(be=>R(r.id),["prevent"])},[_.value.has(r.id)?(e(),o("span",me,he)):(e(),S(U,{key:1,size:16,"fa-icon":r.icon,url:r.icon_path},null,8,["fa-icon","url"])),l("span",{title:r.name,class:"tw-truncate"},t(r.name),9,_e)],8,ve)]),_:2},1032,["class"]))),128))]),_:1})):(e(),o("div",ge,t(n.$t("notFound")),1))],64))],64))}});export{Fe as _,Q as a,Re as b,Se as c};
