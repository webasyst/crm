import{_ as re}from"./ViewWithSidebar.vue_vue_type_style_index_0_lang-4094c7ab.js";import{d as C,t as k,o as t,c,a as s,b as m,w as p,e as T,f as ce,u,_ as z,r as K,g as le,h as A,i as y,j as v,n as I,k as W,v as de,l as F,m as M,p as g,F as w,q as O,s as U,x as j,y as ue,z as X,A as P,B as V,C as Y,D as _e,E as pe,G as me,H as he,I as fe,J as ye,K as ge}from"./main-82f77a11.js";import{u as ve,_ as $e}from"./FormSegmentUpdate.vue_vue_type_script_setup_true_lang-08fcfca6.js";import{_ as Se}from"./InputWithContacts.vue_vue_type_script_setup_true_lang-cc1fd72c.js";import{_ as Z}from"./WaDialogOpener.vue_vue_type_script_setup_true_lang-04eb8058.js";import{_ as be,u as ke}from"./FormContactUpdate.vue_vue_type_script_setup_true_lang-ff1e46e6.js";import{u as we}from"./recentContacts-19c74dc7.js";import{U as H}from"./UserPic-92d000dd.js";import{M as ee,_ as te}from"./DropDown-79d42973.js";import{C as q}from"./CustomColumn-096f9d5b.js";import{u as Ce}from"./useSortable-19b28137.js";import{v as xe}from"./ChipsList-bf475e53.js";import{h as Ie}from"./longTouch-2a1e2d51.js";import{a as Le}from"./alert-14355c48.js";import{_ as Ne}from"./TagsCloud.vue_vue_type_script_setup_true_lang-5f9b5876.js";import"./FieldCheckbox-c89d2635.js";import"./validation-102b744f.js";import"./fields-b3506619.js";import"./FieldSelect-342147aa.js";import"./InputWithUsers.vue_vue_type_script_setup_true_lang-89800614.js";import"./FormContactUpdateInfo.vue_vue_type_script_setup_true_lang-7139646c.js";import"./SortableList.vue_vue_type_script_setup_true_lang-70c99b80.js";import"./index-0a4f4028.js";import"./emit-75ad975c.js";const Re={class:"tw-px-4"},Be={class:"tw-flex tw-space-x-2 tw-items-center tw-w-full"},Ve={class:"tw-flex-auto"},He=s("i",{class:"fas fa-filter gray"},null,-1),Te=[He],Ae={class:"tw-flex-none"},Fe=["onClick","onVnodeMounted"],Oe=s("i",{class:"fas fa-user-plus"},null,-1),ze=[Oe],Me={class:"tw-flex-none md:tw-hidden"},De=s("i",{class:"fas fa-sliders-h black"},null,-1),Ee=[De],Ke=C({__name:"ContactsSidebarFilter",setup(i){const{showSidebar:e}=k(ve());function r(_,n){if(!n)return;n=ce(n);const a=n.includes("@")?"email":/^[+\-0-9() ]+$/.test(n)?"phone":"name.name";_.push({name:"search",params:{id:`contact_info.${a}*=${n}`}})}return(_,n)=>(t(),c("div",Re,[s("div",Be,[s("div",Ve,[m(Se,{"on-enter":a=>r(_.$router,a),"on-item-select":a=>{_.$router.push({name:"contact",params:{id:a.id}})}},null,8,["on-enter","on-item-select"])]),s("div",{class:"tw-flex-none icon tw-cursor-pointer",onClick:n[0]||(n[0]=a=>_.$router.push({name:"detailedSearch"}))},Te),s("div",Ae,[m(Z,{component:be},{default:p(({open:a})=>[s("button",{class:"circle",onClick:T(a,["prevent"]),onVnodeMounted:f=>_.$route.path==="/contact/new/"&&a()},ze,8,Fe)]),_:1})]),s("div",Me,[s("button",{class:"circle transparent",onClick:n[1]||(n[1]=T(a=>e.value=!u(e),["prevent"]))},Ee)])])]))}});const Pe={},Ue={class:"heading"};function je(i,e){return t(),c("div",Ue,[K(i.$slots,"default",{},void 0,!0)])}const qe=z(Pe,[["render",je],["__scopeId","data-v-4abea35f"]]),Ge={key:0,class:"caret tw-w-3 tw-flex-none"},Je=s("i",{class:"fas fa-caret-right"},null,-1),Qe=[Je],se=C({__name:"AccordionSidebar",props:{showCaret:{type:Boolean},openOnInitial:{type:Boolean},disabled:{type:Boolean},storageKey:{}},setup(i){const e=i,r=e.storageKey?le(`sidebar-accordions-${e.storageKey}`,e.openOnInitial):A(e.openOnInitial);return(_,n)=>(t(),y(q,{space:"2"},{default:p(()=>[m(qe,{class:I({open:u(r)}),onClick:n[0]||(n[0]=()=>{e.disabled||(r.value=!u(r))})},{default:p(()=>[e.showCaret?(t(),c("span",Ge,Qe)):v("",!0),K(_.$slots,"header",{isOpen:u(r)})]),_:3},8,["class"]),W(s("div",null,[K(_.$slots,"default")],512),[[de,u(r)]])]),_:3}))}});const G=i=>(U("data-v-afde8db4"),i=i(),j(),i),We={class:"tw-px-4"},Xe={key:0,class:"skeleton"},Ye={key:1,class:"box state-error-hint"},Ze={key:0,class:"tw-pl-4 small gray"},et=["title"],tt=["onClick"],st={key:0},nt=G(()=>s("i",{class:"fas fa-spinner text-yellow tw-animate-spin"},null,-1)),ot=[nt],at={key:1},it=G(()=>s("i",{class:"fas fa-star text-yellow"},null,-1)),rt=[it],ct={key:2},lt=G(()=>s("i",{class:"far fa-star text-light-gray"},null,-1)),dt=[lt],ut=C({__name:"ContactsSidebarContacts",props:{type:{},headerName:{},refetch:{type:Boolean}},setup(i){const e=i,r=we(),_=k(r),{isFetching:n,error:a,fetchingIds:f,pin:$}=_;e.refetch&&r.refetch();const o=F(()=>_[e.type].value);return(S,x)=>{const l=M("RouterLink");return t(),y(se,{"show-caret":!0,"open-on-initial":!0,"storage-key":e.type},{header:p(()=>[s("span",null,g(e.headerName),1)]),default:p(()=>[s("div",We,[u(n)?(t(),c("div",Xe,[(t(),c(w,null,O(4,h=>s("div",{ref_for:!0,ref:"lineRef",key:h,class:"skeleton-line"})),64))])):u(a)?(t(),c("span",Ye,g(u(a)),1)):(t(),c(w,{key:2},[!o.value.length&&!u(n)?(t(),c("div",Ze,g(S.$t("emptyList")),1)):v("",!0),m(ee,null,{default:p(()=>[(t(!0),c(w,null,O(o.value,h=>(t(),y(te,{key:h.id,class:"rounded selected"},{default:p(()=>[m(l,{to:{name:"contact",params:{id:h.id}},class:I(S.$constant.parentAppDisableRouterClass)},{default:p(()=>[m(H,{url:h.userpic,size:20},null,8,["url"]),s("div",{class:"tw-flex-auto tw-truncate",title:h.name},g(h.name),9,et),s("span",{class:"count",onClick:T(L=>u($)(h,e.type==="recent"),["stop","prevent"])},[u(f).has(h.id)?(t(),c("span",st,ot)):v("",!0),!u(f).has(h.id)&&e.type==="pinned"?(t(),c("span",at,rt)):v("",!0),!u(f).has(h.id)&&e.type==="recent"?(t(),c("span",ct,dt)):v("",!0)],8,tt)]),_:2},1032,["to","class"])]),_:2},1024))),128))]),_:1})],64))])]),_:1},8,["storage-key"])}}});const J=z(ut,[["__scopeId","data-v-afde8db4"]]),_t=i=>(U("data-v-12cf976d"),i=i(),j(),i),pt={class:"bricks"},mt=["onClick"],ht={class:"icon"},ft=["src","alt"],yt={class:"count"},gt=["onClick"],vt=_t(()=>s("span",{class:"icon"},[s("i",{class:"fas fa-users"})],-1)),$t={class:"count"},St=C({__name:"ContactsSidebarBricks",setup(i){const{allContactsTotal:e}=k(ue()),{responsibles:r}=k(X()),_=F(()=>{var n;return((n=r.value.find(a=>a.responsible.id===V.user.id))==null?void 0:n.count)??0});return(n,a)=>{const f=M("RouterLink");return t(),c("div",pt,[m(f,{to:{name:"myContacts"},custom:""},{default:p(({isExactActive:$,navigate:o})=>[s("div",{class:I(["brick",{selected:$}]),onClick:o},[s("span",ht,[s("img",{src:u(V).user.userpic,alt:u(V).user.name,class:"userpic"},null,8,ft)]),s("span",yt,g(_.value),1),P(" "+g(n.$t("myContacts")),1)],10,mt)]),_:1}),m(f,{to:{name:"contacts"},custom:""},{default:p(({isExactActive:$,navigate:o})=>[s("div",{class:I(["brick",{selected:$}]),onClick:o},[vt,s("span",$t,g(u(e)),1),P(" "+g(n.$t("allContacts")),1)],10,gt)]),_:1})])}}});const bt=z(St,[["__scopeId","data-v-12cf976d"]]),ne=i=>(U("data-v-a3053bb5"),i=i(),j(),i),kt=["href","onClick"],wt={key:0,class:"icon size-20"},Ct=ne(()=>s("i",{class:"fas fa-spinner wa-animation-spin"},null,-1)),xt=[Ct],It={class:"count"},Lt={key:0,class:"icon handle tw-hidden group-hover/segment:tw-block"},Nt=ne(()=>s("i",{class:"fas fa-grip-vertical"},null,-1)),Rt=[Nt],Bt={class:"tw-truncate"},Vt={class:"tw-inline-block tw-max-w-full tw-truncate"},Ht=C({__name:"ContactsSidebarSegmentList",props:{isSortList:{type:Boolean},id:{},routeName:{},items:{},hasHover:{type:Boolean}},setup(i){const e=i,r={mySegment:"my",sharedSegment:"shared"},{updateOrderInSegment:_}=Y(),n=A(),a=A(null);let f;e.isSortList&&_e(()=>{var o;return n.value&&((o=e.items)==null?void 0:o.length)},()=>{f=Ce(n.value,e.items,{handle:".handle",animation:150,delayOnTouchOnly:!0,delay:150,forceFallback:!0,fallbackOnBody:!1,onUpdate:async o=>{const{itemId:S}=o.item.dataset,{newIndex:x,oldIndex:l}=o,h=()=>{typeof x=="number"&&typeof l=="number"&&me(o.from,o.item,x<l?l+1:l)},L=Array.from(o.from.children).map(B=>{const{itemId:D}=B.dataset;return $(D)}),N=e.id;a.value=$(S),f.option("disabled",!0);const{error:R}=await _(r[N],L);R.value&&(h(),Le(R.value)),setTimeout(()=>{f.option("disabled",!1),a.value=null},100)}})}),pe(()=>{f&&f.stop()});function $(o){if(!o||isNaN(Number(o)))throw new Error(`parse data-item-id="${o}"`);return Number(o)}return(o,S)=>{const x=M("RouterLink");return t(),y(ee,{ref_key:"ulRef",ref:n,"data-list-id":e.id},{default:p(()=>[(t(!0),c(w,null,O(e.items,l=>(t(),y(x,{key:l.id,to:{name:e.routeName,params:{id:l.id}},custom:""},{default:p(({isActive:h,href:L,navigate:N})=>[m(te,{selected:h,"data-item-id":l.id,class:I(["tw-group/segment",{handle:!o.hasHover}])},{default:p(()=>[s("a",{href:L,class:I([o.$constant.parentAppDisableRouterClass,"segment-link"]),onClick:N,onContextmenu:S[0]||(S[0]=R=>u(Ie)()&&R.preventDefault())},[a.value===l.id?(t(),c("span",wt,xt)):(t(),c(w,{key:1},["responsible"in l?(t(),y(H,{key:0,size:20,url:l.responsible.userpic},null,8,["url"])):"icon"in l?(t(),y(H,{key:1,size:20,url:l.icon_path,"fa-icon":l.icon,"disable-rounded":!0},null,8,["url","fa-icon"])):"color"in l?(t(),y(H,{key:2,size:20,"fa-icon":"circle","icon-color":l.color},null,8,["icon-color"])):v("",!0)],64)),s("span",It,[s("span",{class:I(["count-value",{"group-hover/segment:tw-hidden":e.isSortList&&o.hasHover}])},g(l.count),3),e.isSortList&&o.hasHover&&!a.value?(t(),c("span",Lt,Rt)):v("",!0)]),s("span",Bt,[W((t(),c("span",Vt,[P(g(l.name),1)])),[[u(xe),o.hasHover?l.name:null,void 0,{right:!0,700:!0}]])])],42,kt)]),_:2},1032,["selected","data-item-id","class"])]),_:2},1032,["to"]))),128))]),_:1},8,["data-list-id"])}}});const Tt=z(Ht,[["__scopeId","data-v-a3053bb5"]]),At={key:0,class:"count"},Ft=["onClick"],Ot=s("i",{class:"fas fa-plus-circle"},null,-1),zt=[Ot],Mt={key:0,class:"tw-mx-4"},Dt={key:0,class:"tw-pl-4 small gray"},Et={key:0,class:"tw-pl-8 small gray"},Q=C({__name:"ContactsSidebarSegments",props:{only:{},except:{}},setup(i){const e=i,{t:r}=he(),_=fe("(hover: hover)"),n=Y(),{sharedSegment:a,archivedSegment:f,mySegment:$,isFetching:o}=k(n);n.refetch();const S=ke(),{vaults:x}=k(S);S.refetch();const l=X(),{responsibles:h}=k(l);l.refetch();const L=F(()=>h.value.map(b=>({...b,...b.responsible}))),N=ye(),{tags:R}=k(N);N.refetch();const B=A([{id:"mySegment",name:r("mySegments"),items:$,loading:"segments",routeName:"segment",showPlus:!0},{id:"sharedSegment",name:r("sharedSegments"),items:a,loading:"segments",routeName:"segment",showPlus:!0},{id:"archivedSegment",name:r("archivedSegments"),items:f,loading:"segments",routeName:"segment",showCounter:!0},{id:"tags",name:r("tags"),items:R,loading:"tags",routeName:"tag"},{id:"vaults",name:r("vaults"),items:x,loading:"vaults",routeName:"vault"},{id:"responsibles",name:r("responsibles"),items:L,loading:"responsibles",routeName:"responsible"}]),D=F(()=>b=>{var E;return b==="mySegment"||!!((E=V.rights)!=null&&E.is_admin)&&["sharedSegment"].includes(b)});return(b,E)=>(t(),y(q,{space:"4"},{default:p(()=>[(t(!0),c(w,null,O(e.only?B.value.filter(d=>d.id===e.only):e.except?B.value.filter(d=>d.id!==e.except):B.value,(d,oe)=>(t(),c(w,{key:d.id},[d.id!=="archivedSegment"||d.items.length?(t(),y(se,{key:0,"show-caret":!0,"open-on-initial":!!d.items.length&&oe===0,"storage-key":d.id},{header:p(({isOpen:ae})=>[s("span",null,g(d.name),1),d.showCounter&&!ae?(t(),c("span",At,g(d.items.length||""),1)):v("",!0),d.showPlus?(t(),y(Z,{key:1,component:$e,"component-props":{initialShared:d.id==="mySegment"?"0":"1"}},{default:p(({open:ie})=>[s("span",{class:"icon",onClick:T(ie,["stop"])},zt,8,Ft)]),_:2},1032,["component-props"])):v("",!0)]),default:p(()=>[d.id==="tags"?(t(),c("div",Mt,[!d.items.length&&!u(o)?(t(),c("div",Dt,g(b.$t("emptyList")),1)):v("",!0),m(Ne,{"entity-type":"contact",tags:d.items},null,8,["tags"])])):(t(),c(w,{key:1},[!d.items.length&&!u(o)?(t(),c("div",Et,g(b.$t("emptyList")),1)):(t(),y(Tt,{key:1,id:d.id,"route-name":d.routeName,items:d.items,"is-sort-list":D.value(d.id),"has-hover":u(_)},null,8,["id","route-name","items","is-sort-list","has-hover"]))],64))]),_:2},1032,["open-on-initial","storage-key"])):v("",!0)],64))),128))]),_:1}))}}),Kt=C({__name:"ContactsSidebar",setup(i){return(e,r)=>(t(),y(q,{space:"4",class:"tw-my-4"},{default:p(()=>[m(Ke),m(bt),m(Q,{only:"mySegment"}),m(J,{type:"pinned","header-name":e.$t("favorites"),refetch:""},null,8,["header-name"]),m(J,{type:"recent","header-name":e.$t("recentContacts")},null,8,["header-name"]),m(Q,{except:"mySegment"})]),_:1}))}}),ps=C({__name:"ContactsView",setup(i){return(e,r)=>{const _=M("RouterView");return t(),y(re,{slidable:!0,onVnodeMounted:r[0]||(r[0]=n=>{var a;return(a=u(ge))==null?void 0:a.setTitle(e.$t("contacts"))})},{sidebar:p(()=>[m(Kt)]),default:p(()=>[m(_)]),_:1},512)}}});export{ps as default};
