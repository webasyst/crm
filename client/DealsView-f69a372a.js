import{d as y,J as ne,t as F,au as R,A as N,o as a,b,w as u,e as k,m as P,k as g,i as s,p as w,f as S,c as $,l as x,g as h,F as V,n as B,H as ee,av as se,ab as oe,aw as ie,R as A,a as L,ax as re,D as ce,h as H,ay as ue,ao as de,an as _e,q as j,s as Q,_ as q,az as pe,v as W,B as te,W as fe,S as K,I as me,T as U,r as ae,aA as le,aB as ve,aC as he,aD as $e,a6 as be,j as ke,aE as ge,P as ye,aF as X,aG as we,aH as Ce}from"./main-e63d6b61.js";import{U as D}from"./UserPic-e63dca94.js";import{M as z,_ as I,D as M}from"./DropDown-b752b133.js";import{D as T}from"./InputWithPerformers.vue_vue_type_script_setup_true_lang-4c7d29d0.js";import{C as Se,a as Fe,v as De}from"./ChipsList-f1962714.js";import{Q as Ve}from"./index-6448b8a4.js";import{D as Ie}from"./DropdownModal-872f106f.js";import{W as Be}from"./WaSpinner-7a41251d.js";import{_ as Re}from"./FormAddTag.vue_vue_type_script_setup_true_lang-911d3b89.js";import{a as Ne,_ as Pe}from"./DealNavigateBack.vue_vue_type_script_setup_true_lang-9550d30a.js";import{F as Te}from"./FieldCheckbox-c1b80b55.js";import{e as xe}from"./emit-6e8c94d0.js";import{_ as Le}from"./WaDialogOpener.vue_vue_type_script_setup_true_lang-2e62a6a1.js";import Ee from"./FormDealAdd-d8c5da51.js";import"./index-48d220da.js";import"./recentContacts-2c7dd6f4.js";import"./FieldSelect-d021e2ab.js";import"./CustomColumn-72664405.js";import"./DropdownAddTags.vue_vue_type_script_setup_true_lang-264ee207.js";import"./ButtonSubmit-f072bf17.js";import"./fields-d5743e8f.js";import"./FormContactUpdateInfo.vue_vue_type_script_setup_true_lang-bbb2cba4.js";import"./SortableList.vue_vue_type_script_setup_true_lang-a7709f9a.js";import"./useSortable-6edb9dbf.js";import"./InputWithContacts.vue_vue_type_script_setup_true_lang-51f96cc1.js";import"./dayjs-e48e80eb.js";import"./TextAreaAutoresize-03f16fa4.js";const He=["onClick"],Ae={class:"tw-p-2"},ze=["onClick"],Me=s("i",{class:"fas fa-hashtag"},null,-1),Oe={class:"tw-flex-auto tw-truncate"},Ue={class:"count tw-mt-0.5"},je=y({__name:"DropdownTags",setup(m){const c=ne(),{tags:t}=F(c),_=R(),{filterParams:l,firstDeal:f}=F(_),p=N(()=>{var v,d;if(typeof l.value.tag=="number"){const o=t.value.find(e=>e.id===l.value.tag);return!o&&((d=(v=f.value)==null?void 0:v.tags)!=null&&d.length)?f.value.tags.find(e=>e.id===l.value.tag):o}});return(v,d)=>(a(),b(M,{width:"270px"},{default:u(()=>[k(T,{small:!0,width:"9rem"},{icon:u(()=>[k(D,{size:14,"fa-icon":"hashtag"})]),title:u(()=>[P(g(p.value?p.value.name:v.$t("Tags")),1)]),_:1})]),body:u(({hide:o})=>[p.value?(a(),b(z,{key:0},{default:u(()=>[k(I,null,{default:u(()=>[s("a",{onClick:w(e=>{h(_).deleteFilterParam("tag"),o()},["prevent"])},[s("span",null,g(v.$t("showAll")),1)],8,He)]),_:2},1024)]),_:2},1024)):S("",!0),s("div",Ae,[k(Se,null,{default:u(()=>[(a(!0),$(V,null,x(h(t),e=>{var n;return a(),b(Fe,{key:e.id,class:B(["small",{selected:((n=p.value)==null?void 0:n.id)===e.id}])},{default:u(()=>[s("a",{onClick:w(i=>{h(_).updateFilterParams({tag:e.id}),o()},["prevent"])},[Me,s("span",Oe,g(e.name),1),s("span",Ue,g(e.count),1)],8,ze)]),_:2},1032,["class"])}),128))]),_:2},1024)])]),_:1}))}}),Qe=["onClick"],qe=["onClick"],We={class:"icon small"},Ke={class:"count"},Ye=s("hr",{class:"tw-m-0"},null,-1),Ge=["onClick"],Je={class:"icon small"},Xe=y({__name:"DropdownStages",setup(m){const{t:c}=ee(),{selectedFunnel:t}=F(se()),_=R(),{filterParams:l,firstDeal:f}=F(_),p=N(()=>{var e;return t.value&&((e=t.value.stages)!=null&&e.length)?t.value.stages:[]}),v=N(()=>p.value.length),d=N(()=>{var e,n;return[{id:"won",name:c("wons"),color:((e=t.value)==null?void 0:e.color)||"",icon:"flag-checkered"},{id:"lost",name:c("losts"),color:((n=t.value)==null?void 0:n.color)||"",icon:"ban"}]}),o=N(()=>{var e,n,i;switch(typeof((e=l.value)==null?void 0:e.stage)){case"number":if(v.value)return p.value.find(r=>r.id===l.value.stage);if((n=f.value)!=null&&n.stage)return{...(i=f.value)==null?void 0:i.stage,icon:null};break;case"string":return d.value.find(r=>r.id===l.value.stage)}});return(e,n)=>(a(),b(M,{disabled:!v.value},oe({default:u(()=>[k(T,{small:!0,disabled:!v.value},{icon:u(()=>[o.value?(a(),$(V,{key:0},[o.value.icon?(a(),b(D,{key:o.value.color,size:10,"fa-icon":o.value.icon,"icon-color":o.value.color},null,8,["fa-icon","icon-color"])):(a(),b(D,{key:o.value.id,size:10,"disable-rounded":"","bg-color":o.value.color},null,8,["bg-color"]))],64)):S("",!0)]),title:u(()=>[P(g(o.value?o.value.name:e.$t("allStages")),1)]),_:1},8,["disabled"])]),_:2},[v.value?{name:"body",fn:u(({hide:i})=>[k(z,null,{default:u(()=>[(a(),b(I,{key:"all"},{default:u(()=>[s("a",{onClick:w(r=>{h(_).deleteFilterParam("stage"),i()},["prevent"])},[s("span",null,g(e.$t("allStages")),1)],8,Qe)]),_:2},1024)),(a(!0),$(V,null,x(p.value,r=>(a(),b(I,{key:r.id},{default:u(()=>[s("a",{onClick:w(C=>{h(_).updateFilterParams({stage:r.id}),i()},["prevent"])},[s("div",We,[k(D,{size:10,"disable-rounded":"","bg-color":r.color},null,8,["bg-color"])]),s("span",null,g(r.name),1),s("span",Ke,g(r.deal_count),1)],8,qe)]),_:2},1024))),128)),Ye,(a(!0),$(V,null,x(d.value,r=>(a(),b(I,{key:r.id},{default:u(()=>[s("a",{onClick:w(C=>{h(_).updateFilterParams({stage:r.id}),i()},["prevent"])},[s("div",Je,[(a(),b(D,{key:r.color,size:10,"fa-icon":r.icon,"icon-color":r.color},null,8,["fa-icon","icon-color"]))]),s("span",null,g(r.name),1)],8,Ge)]),_:2},1024))),128))]),_:2},1024)]),key:"0"}:void 0]),1032,["disabled"]))}}),Ze=s("i",{class:"fas fa-user-slash"},null,-1),es=s("i",{class:"fas fa-users"},null,-1),ss=["onClick"],ts=s("div",{class:"icon"},[s("i",{class:"fas fa-users"})],-1),as=["onClick"],ls=s("div",{class:"icon"},[s("i",{class:"fas fa-user-slash"})],-1),ns=["onClick"],os={class:"icon"},is={class:"count"},rs=y({__name:"DropdownResponsibles",setup(m){const c=ie(),{responsibles:t}=F(c),_=R(),{filterParams:l}=F(_),f=N(()=>{if(typeof l.value.user_id=="number")return c.getResponsibleById(l.value.user_id)}),p=N(()=>{var d;return((d=l.value)==null?void 0:d.user_id)===0});function v(d){d==="all"?_.deleteFilterParam("user_id"):_.updateFilterParams({user_id:d})}return(d,o)=>(a(),b(M,null,{default:u(()=>[f.value?(a(),b(T,{key:0,small:!0},{icon:u(()=>[k(D,{size:16,url:f.value.responsible.userpic},null,8,["url"])]),title:u(()=>[P(g(f.value.responsible.name),1)]),_:1})):p.value?(a(),b(T,{key:1,small:!0},{icon:u(()=>[Ze]),title:u(()=>[P(g(d.$t("noResponsible")),1)]),_:1})):(a(),b(T,{key:2,small:!0},{icon:u(()=>[es]),title:u(()=>[P(g(d.$t("allResponsibles")),1)]),_:1}))]),body:u(({hide:e})=>[k(z,null,{default:u(()=>[(a(),b(I,{key:"all"},{default:u(()=>[s("a",{onClick:w(n=>{v("all"),e()},["prevent"])},[ts,s("span",null,g(d.$t("allResponsibles")),1)],8,ss)]),_:2},1024)),(a(),b(I,{key:0},{default:u(()=>[s("a",{onClick:w(n=>{v(0),e()},["prevent"])},[ls,s("span",null,g(d.$t("noResponsible")),1)],8,as)]),_:2},1024)),(a(!0),$(V,null,x(h(t),n=>(a(),b(I,{key:n.responsible.id},{default:u(()=>[s("a",{onClick:w(i=>{v(n.responsible.id),e()},["prevent"])},[s("div",os,[k(D,{size:10,url:n.responsible.userpic},null,8,["url"])]),s("span",null,g(n.responsible.name),1),s("span",is,g(n.count),1)],8,ns)]),_:2},1024))),128))]),_:2},1024)]),_:1}))}}),cs=["onClick"],us=["onClick"],ds={class:"icon"},_s={class:"count"},ps=y({__name:"DropdownFunnels",props:{hideOptionAll:{type:Boolean}},setup(m){const c=m,t=se();t.withCount();const{funnels:_,selectedFunnel:l,isFinished:f}=F(t),p=R(),{filterParams:v,firstDeal:d}=F(p);A(()=>[d.value,v.value.funnel,f.value],([,e,n])=>{var i;n&&(typeof e=="number"?(l.value=t.getFunnelById(e),!l.value&&((i=d.value)!=null&&i.funnel)&&(l.value={...d.value.funnel,stages:[d.value.stage]})):l.value=void 0)},{immediate:!0});function o(e,n){var i;e==="all"?p.updateFilterParams({funnel:void 0,stage:void 0}):p.updateFilterParams({funnel:e,...((i=v.value)==null?void 0:i.funnel)!==e?{stage:void 0}:{}}),typeof n=="function"&&n()}return(e,n)=>(a(),b(M,null,{default:u(()=>[k(T,{small:!0},{icon:u(()=>[h(l)?(a(),b(D,{key:h(l).id,size:12,"fa-icon":"filter","icon-color":h(l).color},null,8,["icon-color"])):S("",!0)]),title:u(()=>[P(g(h(l)?h(l).name:e.$t("allFunnels")),1)]),_:1})]),body:u(({hide:i})=>[k(z,null,{default:u(()=>[c.hideOptionAll?S("",!0):(a(),b(I,{key:"all"},{default:u(()=>[s("a",{onClick:w(r=>o("all",i),["prevent"])},[s("span",null,g(e.$t("allFunnels")),1)],8,cs)]),_:2},1024)),(a(!0),$(V,null,x(h(_),r=>(a(),b(I,{key:r.id},{default:u(()=>[s("a",{onClick:w(C=>o(r.id,i),["prevent"])},[s("div",ds,[k(D,{size:10,"fa-icon":"filter","icon-color":r.color},null,8,["icon-color"])]),s("span",null,g(r.name),1),s("span",_s,g(r.deal_count),1)],8,us)]),_:2},1024))),128))]),_:2},1024)]),_:1}))}}),O=m=>(j("data-v-b18d977a"),m=m(),Q(),m),fs={key:0,class:"state-with-inner-icon left"},ms=["placeholder"],vs={key:0,class:"icon icon-spinner"},hs=O(()=>s("i",{class:"fas fa-spinner wa-animation-spin"},null,-1)),$s=[hs],bs={key:1,class:"icon icon-search"},ks=O(()=>s("i",{class:"fas fa-search"},null,-1)),gs=[ks],ys=O(()=>s("i",{class:"fas fa-times"},null,-1)),ws=[ys],Cs=O(()=>s("span",{class:"icon"},[s("i",{class:"fas fa-search"})],-1)),Ss=[Cs],Fs=y({__name:"InputSearch",props:{modelValue:{},isFetching:{type:Boolean}},emits:["update:modelValue","close"],setup(m,{emit:c}){const t=m,_=L(),l=re(t,"modelValue"),f=L(!1);return ce(_,p=>{p.focus()}),(p,v)=>(a(),$("div",null,[f.value?(a(),$("div",fs,[H(s("input",de({ref_key:"inputEl",ref:_,"onUpdate:modelValue":v[0]||(v[0]=d=>_e(l)?l.value=d:null),type:"search",class:"small",placeholder:p.$t("search")},p.$attrs),null,16,ms),[[ue,h(l)]]),p.isFetching?(a(),$("span",vs,$s)):(a(),$("span",bs,gs)),s("span",{class:"icon icon-close",onClick:v[1]||(v[1]=d=>{f.value=!1,c("close")})},ws)])):(a(),$("button",{key:1,class:"button square light-gray",onClick:v[2]||(v[2]=w(d=>f.value=!0,["prevent"]))},Ss))]))}});const Ds=q(Fs,[["__scopeId","data-v-b18d977a"]]),Vs=y({__name:"DealsHeaderSearch",setup(m){const{updateOrDeleteSearchParam:c,onBeforeClearFilters:t,filterParams:_}=R(),l=L(""),f=pe(v=>{c(v.trim())},550);t(p);function p(){l.value="",c("","search"in _&&Object.keys(_).length>1)}return(v,d)=>(a(),b(Ds,{modelValue:l.value,"onUpdate:modelValue":d[0]||(d[0]=o=>l.value=o),onInput:d[1]||(d[1]=o=>h(f)(o.target.value)),onClose:p},null,8,["modelValue"]))}}),Is=["onClick"],Bs=s("i",{class:"fas fa-star"},null,-1),Rs=[Bs],Ns=y({__name:"DealsHeaderFavorites",setup(m){const c=R(),t=N(()=>!!c.filterParams.pinned_only);function _(){c.updateFilterParams({pinned_only:t.value?void 0:1})}return(l,f)=>(a(),$("div",null,[H((a(),$("button",{class:B(["button square",[t.value?"yellow":"light-gray"]]),onClick:w(_,["prevent"])},[s("span",{class:B(["icon size-14",{"!tw-text-waBlank":t.value}])},Rs,2)],10,Is)),[[h(De),l.$t("showFavorites"),void 0,{bottom:!0}]])]))}}),Ps=m=>(j("data-v-cac879f0"),m=m(),Q(),m),Ts={class:"deals-header__filters-wrapper"},xs={class:"deals-header__filters"},Ls=Ps(()=>s("i",{class:"fas fa-times-circle text-gray"},null,-1)),Es=[Ls],Hs={key:0,class:"skeleton deals-header__filters"},As=y({__name:"DealsHeaderFilters",props:{hideStages:{type:Boolean},hasFilters:{type:Boolean},showSkeleton:{type:Boolean},isKanban:{type:Boolean}},emits:["clear"],setup(m,{emit:c}){const t=m;return(_,l)=>(a(),$("div",Ts,[H(s("div",xs,[k(ps,{"hide-option-all":t.isKanban},null,8,["hide-option-all"]),t.hideStages?S("",!0):(a(),b(Xe,{key:0})),k(rs),k(je),k(Ns),k(Vs),t.hasFilters?(a(),$("button",{key:1,class:"deals-header__filters-clear button small nobutton light-gray",onClick:l[0]||(l[0]=f=>c("clear"))},Es)):S("",!0)],512),[[W,!t.showSkeleton]]),t.showSkeleton?(a(),$("div",Hs,[(a(!0),$(V,null,x(t.hideStages?3:4,f=>(a(),$("span",{key:f,class:"skeleton-line deals-header__filters-skeleton"}))),128))])):S("",!0)]))}});const zs=q(As,[["__scopeId","data-v-cac879f0"]]),Ms=["disabled","onClick"],Os={class:"tw-flex tw-items-center tw-gap-2"},Us={class:"icon"},js=y({__name:"ActionButtonList",props:{items:{},space:{}},setup(m){const c=m;return(t,_)=>(a(),$("div",{class:B(`tw-flex tw-gap-${c.space||0}`)},[(a(!0),$(V,null,x(c.items,(l,f)=>(a(),$(V,{key:f},[l.hide?S("",!0):(a(),$("button",{key:0,disabled:l.disableClick,class:"light-gray",onClick:p=>!l.disableClick&&l.clickHandler()},[s("div",Os,[s("span",Us,[s("i",{class:B(["fas",l.classIcon])},null,2)]),s("span",null,g(l.label),1)])],8,Ms))],64))),128))],2))}}),Qs=["src"],qs=y({__name:"FormDealExportCSV",props:{ids:{}},setup(m){const c=m,t=L(!0),_={height:"400px",width:"100%"};return(l,f)=>(a(),b(fe,{"hide-buttons":!0},{default:u(()=>[t.value?(a(),b(Be,{key:0,style:_})):S("",!0),H(s("iframe",{src:`${h(te).baseUrl}?module=deal&action=export&ids=${c.ids.join(",")}`,style:_,frameborder:"0",onLoad:f[0]||(f[0]=p=>t.value=!1)},null,40,Qs),[[W,!t.value]])]),_:1}))}}),Ws={class:"deals-header__edit small"},Ks={class:"deals-header__edit-selected-deals"},Ys={class:"tw-hidden xl:tw-inline-block tw-mr-2"},Gs={class:"badge user tw-bg-waAccent tw-min-w-[0.5rem] tw-justify-center"},Js={class:"deals-header__edit-buttons"},Xs=s("span",{class:"icon"},[s("i",{class:"fas fa-times-circle"})],-1),Zs={class:"tw-hidden xl:tw-inline-block tw-ml-2"},et=y({__name:"DealsHeaderBulkEdit",setup(m){const c=K(),{t}=ee(),_=R(),{countChecked:l,isAllChecked:f,bulkSelectIds:p}=F(_),v=me(Ve),d=L(f.value?"1":""),o=L([{label:t("export"),classIcon:"fas fa-download",clickHandler:()=>{U(qs,{ids:p.value}).show()}},{label:t("merge"),classIcon:"fas fa-users",hide:!0,clickHandler:()=>c.push({name:"dealMerge",query:{ids:p.value.join(",")}})},{label:t("changeResponsible"),classIcon:"fas fa-user-check",hide:!0,clickHandler:()=>({})},{label:t("changeFunnelOrStage"),classIcon:"fas fa-exchange-alt",hide:!0,clickHandler:()=>({})},{label:t("Tags"),classIcon:"fas fa-hashtag",clickHandler:()=>U(Re,{entityType:"deal",entityIds:p.value}).show()},{label:t("close"),classIcon:"fas fa-flag-checkered",hide:!0,clickHandler:()=>({})},{label:t("delete"),classIcon:"fas fa-trash-alt text-red",clickHandler:()=>U(Ne,{ids:p.value,isBulkMode:!0}).show()}]);A(l,n=>{o.value[1].hide=n<2},{immediate:!0}),A(f,n=>{n?d.value="1":d.value="",(l.value===0||n)&&e(n)});function e(n){_.toggleChecked(n)}return(n,i)=>(a(),$("div",Ws,[s("div",Ks,[k(Te,{modelValue:d.value,"onUpdate:modelValue":i[0]||(i[0]=r=>d.value=r),size:16,onChange:i[1]||(i[1]=r=>e(!!r))},{default:u(()=>[s("span",Ys,g(n.$t("selected")),1),s("span",Gs,g(h(l)),1)]),_:1},8,["modelValue"])]),s("div",Js,[h(v)?(a(),b(Ie,{key:0,items:o.value},{default:u(()=>[k(T,{width:"8rem"},{title:u(()=>[P(g(n.$t("actions")),1)]),_:1})]),_:1},8,["items"])):(a(),b(js,{key:1,class:"deals-header__edit-actions",items:o.value,space:"2"},null,8,["items"])),s("button",{type:"button",class:"button outlined gray small",onClick:i[2]||(i[2]=w(r=>e(!1),["prevent"]))},[Xs,s("span",Zs,g(n.$t("cancel")),1)])])]))}});const Y=m=>(j("data-v-c3b60b56"),m=m(),Q(),m),st={class:"filters-button"},tt={key:0,class:"tw-p-2"},at=Y(()=>s("i",{class:"fas fa-spinner wa-animation-spin"},null,-1)),lt=[at],nt=Y(()=>s("i",{class:"fas fa-filter"},null,-1)),ot=[nt],it={key:2,class:"icon size-8 rounded filters-button__has"},rt=Y(()=>s("i",{class:"fas fa-circle text-red circle"},null,-1)),ct=[rt],ut=y({__name:"DealsFiltersControlButton",props:{modelValue:{type:Boolean},hasFilters:{type:Boolean},loading:{type:Boolean}},emits:["update:modelValue"],setup(m,{emit:c}){const t=m;return(_,l)=>(a(),$("span",st,[t.loading?(a(),$("div",tt,lt)):(a(),$("button",{key:1,class:B(["button circle blank !tw-shadow-none",{"filters-button--active":t.modelValue}]),onClick:l[0]||(l[0]=w(f=>c("update:modelValue",!t.modelValue),["prevent"]))},ot,2)),!t.loading&&t.hasFilters?(a(),$("span",it,ct)):S("",!0)]))}});const dt=q(ut,[["__scopeId","data-v-c3b60b56"]]),Z=y({__name:"ToggleListItem",props:{modelValue:{},value:{}},emits:["update:modelValue"],setup(m,{emit:c}){return(t,_)=>(a(),$("span",{class:B({selected:t.modelValue===t.value}),onClick:_[0]||(_[0]=l=>c("update:modelValue",t.value))},[ae(t.$slots,"default")],2))}}),_t=y({__name:"ToggleList",props:{size:{},rounded:{type:Boolean}},setup(m){const c=m;return(t,_)=>(a(),$("div",{class:B(["toggle",c.size,c.rounded?"rounded":""])},[ae(t.$slots,"default")],2))}}),pt={class:"deals-header"},ft={class:"deals-header__inner"},mt={key:0,class:"deals-header__heading--native"},vt={key:1,class:"deals-header__heading--web tw-flex tw-space-x-4 tw-items-center"},ht={class:"tw-mb-2"},$t=["title","onClick","onVnodeMounted"],bt=s("i",{class:"fas fa-plus"},null,-1),kt=[bt],gt=s("i",{class:"fas fa-columns"},null,-1),yt=s("i",{class:"fas fa-list-ul"},null,-1),wt=y({__name:"DealsHeader",setup(m){const c=R(),{hasFilters:t,countChecked:_}=F(c),{currentView:l,isKanban:f}=F(le()),p=K(),v=L(!1),d=p.currentRoute.value.path==="/deal/new/";function o(e){l.value!==e&&(l.value=e,p.push({name:ve[e],query:p.currentRoute.value.query}).then(()=>c.changeTabView()))}return(e,n)=>(a(),$("div",pt,[h(_)?(a(),b(et,{key:0})):S("",!0),H(s("div",ft,[h(te).webView?(a(),$("div",mt,[k(Pe)])):(a(),$("div",vt,[s("div",ht,[s("h1",null,g(e.$t("Deals")),1)]),k(Le,{component:Ee,"component-props":{funnelId:h(c).filterParams.funnel,...d&&e.$route.query.contact?{contactId:e.$route.query.contact}:{},onAdded:h(xe)}},{default:u(({open:i})=>[s("button",{title:e.$t("newDeal"),class:"circle",onClick:w(i,["prevent"]),onVnodeMounted:r=>d&&i()},kt,8,$t)]),_:1},8,["component-props"]),h(l)?(a(),b(_t,{key:0,rounded:""},{default:u(()=>[k(Z,{"model-value":h(l),value:"kanban","onUpdate:modelValue":n[0]||(n[0]=i=>o(i))},{default:u(()=>[gt]),_:1},8,["model-value"]),k(Z,{"model-value":h(l),value:"list","onUpdate:modelValue":n[1]||(n[1]=i=>o(i))},{default:u(()=>[yt]),_:1},8,["model-value"])]),_:1})):S("",!0)])),k(dt,{modelValue:v.value,"onUpdate:modelValue":n[2]||(n[2]=i=>v.value=i),class:"deals-header__filters-button tw-hidden","has-filters":h(t),loading:!h(c).filtersIsLoaded},null,8,["modelValue","has-filters","loading"]),s("div",{class:B(["deals-header__filters-container",{"deals-header__filters-container--hide":!v.value}])},[k(zs,{"has-filters":h(t),"hide-stages":h(f),"show-skeleton":!h(c).filtersIsLoaded,"is-kanban":h(f),onClear:h(c).clearFilters},null,8,["has-filters","hide-stages","show-skeleton","is-kanban","onClear"])],2)],512),[[W,!h(_)]])]))}});const Ct={class:"deals blank box contentbox"},Xt=y({__name:"DealsView",props:{routeName:{},routeQuery:{}},setup(m){const c=m,t=K(),_=le(),l=R(),f=he();if(t.currentRoute.value.path!=="/deal/new/"&&(p(c.routeName,c.routeQuery),c.routeName!=="deals")){const o=d(c.routeQuery).filters();l.updateOrClearFilterParams(o);const e=d(c.routeQuery).sortList();f.updateSort(e)}A([()=>l.filterParams,()=>f.sort],([o,e])=>{v({...o,...e.sort===X.sort&&e.asc===X.asc?{}:e})},{immediate:!0,deep:!0}),$e((o,e)=>{o.name==="deals"&&e.meta.menuItemType==="deals"?setTimeout(()=>p(e.name,e.query,!0)):o.name!==e.name&&(_.changeRouteTab(o.name),l.$resetDeals())}),be(we).on(l.reinitView);function p(o,e,n){const i=n?t.replace:t.push,r=_.changeRouteTab(o);i({name:r,query:e})}function v(o){const e={};for(const[n,i]of Object.entries(o))Ce.includes(n)&&i!==null&&(e[n]=i);t.push({name:t.currentRoute.value.name&&t.currentRoute.value.name!=="deals"?t.currentRoute.value.name:_.lastRouteNameTab,query:e})}function d(o){const e=(r,C)=>{if(r=String(r),C!=null&&C.includes(r))return r;const E=Number(r);return isNaN(E)?null:E},n=r=>typeof r=="string"?r:null,i={};return{filters(){if(!Object.keys(o).length)return i;const r=n(o.funnel);r&&(i.funnel=e(r));const C=n(o.stage);C&&(i.stage=e(C,["won","lost"]));const E=n(o.tag);E&&(i.tag=e(E));const G=n(o.user_id);G&&(i.user_id=e(G));const J=n(o.pinned_only);return J&&(i.pinned_only=e(J)),i},sortList(){if(!Object.keys(o).length)return i;const r=n(o.sort);r&&(i.sort=r);const C=n(o.asc);return C&&(i.asc=e(C)?1:0),i}}}return(o,e)=>{const n=ke("router-view");return a(),$("div",Ct,[k(wt),k(n,null,{default:u(({Component:i})=>[k(ge,{name:"fade",mode:"out-in"},{default:u(()=>[(a(),b(ye(i)))]),_:2},1024)]),_:1})])}}});export{Xt as default};