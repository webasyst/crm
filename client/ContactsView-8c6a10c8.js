import{_ as oe}from"./ViewWithSidebar.vue_vue_type_style_index_0_lang-7a5f9733.js";import{_ as D,o as e,c as n,r as F,d as S,u as ae,a as U,b as m,w as r,e as d,f as $,n as I,g as i,h as j,v as ie,i as t,t as k,j as V,k as p,F as g,l as R,m as T,p as N,q as E,s as G,x as ce,y as J,z as Q,A,B as re,C as le}from"./main-ec0df6a4.js";import{u as _e}from"./recentContacts-b91545dc.js";import{U as L}from"./UserPic-87c13a4a.js";import{M as W,_ as X}from"./MenuList-466c4134.js";import{C as P}from"./CustomColumn-959e6ffe.js";import{u as de,_ as ue}from"./FormSegmentUpdate.vue_vue_type_script_setup_true_lang-98eec75b.js";import{_ as pe}from"./InputWithContacts.vue_vue_type_script_setup_true_lang-86c741fd.js";import{_ as Y}from"./WaDialogOpener.vue_vue_type_script_setup_true_lang-e3cb498b.js";import{_ as me,u as fe}from"./FormContactUpdate.vue_vue_type_script_setup_true_lang-56fc78b5.js";import{u as he}from"./tags-11dad524.js";import{v as ge}from"./vTooltip-c8fe82a0.js";import{_ as $e}from"./TagsCloud.vue_vue_type_script_setup_true_lang-b5fb5453.js";import"./FieldString-0827de0f.js";import"./FieldCheckbox-be3e2148.js";import"./WaDialog-c7c19903.js";import"./validation-981dc8b6.js";import"./InputWithUsers.vue_vue_type_script_setup_true_lang-b6c3d351.js";import"./DropDown-5d25a522.js";import"./index-8d873219.js";import"./SortableList.vue_vue_type_script_setup_true_lang-79d2c918.js";import"./useSortable-5c8ee39e.js";import"./FieldSelect-df2e3574.js";import"./fields-5ca407fb.js";import"./dialog-efb0af25.js";import"./helpers-a2ed4cd9.js";import"./ChipsList-64240846.js";import"./emit-727b9eb7.js";const ye={},ve={class:"heading"};function ke(l,o){return e(),n("div",ve,[F(l.$slots,"default",{},void 0,!0)])}const Ce=D(ye,[["render",ke],["__scopeId","data-v-e4f563ea"]]),Se={key:0,class:"caret tw-w-3 tw-flex-none"},we=t("i",{class:"fas fa-caret-right"},null,-1),be=[we],Z=S({__name:"AccordionSidebar",props:{showCaret:{type:Boolean},openOnInitial:{type:Boolean},disabled:{type:Boolean},storageKey:{}},setup(l){const o=l,a=o.storageKey?ae(`sidebar-accordions-${o.storageKey}`,o.openOnInitial):U(o.openOnInitial);return(_,s)=>(e(),m(P,{space:"2"},{default:r(()=>[d(Ce,{class:I({open:i(a)}),onClick:s[0]||(s[0]=()=>{o.disabled||(a.value=!i(a))})},{default:r(()=>[o.showCaret?(e(),n("span",Se,be)):$("",!0),F(_.$slots,"header")]),_:3},8,["class"]),j(t("div",null,[F(_.$slots,"default")],512),[[ie,i(a)]])]),_:3}))}});const K=l=>(E("data-v-9a18827c"),l=l(),G(),l),xe={class:"tw-px-4"},Re={key:0,class:"skeleton"},Ie={key:0,class:"tw-pl-4 small gray"},Be=["title"],Le=["onClick"],Ne={key:0},Ve=K(()=>t("i",{class:"fas fa-spinner text-yellow tw-animate-spin"},null,-1)),ze=[Ve],Ae={key:1},Fe=K(()=>t("i",{class:"fas fa-star text-yellow"},null,-1)),Te=[Fe],De={key:2},Pe=K(()=>t("i",{class:"far fa-star text-light-gray"},null,-1)),Ke=[Pe],Me=S({__name:"ContactsSidebarRecent",setup(l){const o=_e(),{pinned:a,recent:_,isFetching:s,error:C,fetchingIds:y,pin:v}=k(o);return o.refetch(),(h,M)=>{const B=V("RouterLink");return e(),m(Z,{"show-caret":!0,"open-on-initial":!0,"storage-key":"recent"},{header:r(()=>[t("span",null,p(h.$t("recentContacts")),1)]),default:r(()=>[t("div",xe,[i(s)?(e(),n("div",Re,[(e(),n(g,null,R(8,x=>t("div",{ref_for:!0,ref:"lineRef",key:x,class:"skeleton-line"})),64))])):i(C)?(e(),n(g,{key:1},[T(p(i(C)),1)],64)):(e(),n(g,{key:2},[![...i(a),...i(_)].length&&!i(s)?(e(),n("div",Ie,p(h.$t("emptyList")),1)):$("",!0),d(W,null,{default:r(()=>[(e(!0),n(g,null,R([i(a),i(_)],(x,w)=>(e(),n(g,{key:w},[(e(!0),n(g,null,R(x,f=>(e(),m(X,{key:f.id,class:"rounded selected"},{default:r(()=>[d(B,{to:{name:"contact",params:{id:f.id}},class:I(h.$constant.parentAppDisableRouterClass)},{default:r(()=>[d(L,{url:f.userpic,size:20},null,8,["url"]),t("div",{class:"tw-flex-auto tw-truncate",title:f.name},p(f.name),9,Be),t("span",{class:"count",onClick:N(O=>i(v)(f.id,!!w),["stop","prevent"])},[i(y).has(f.id)?(e(),n("span",Ne,ze)):$("",!0),!i(y).has(f.id)&&w===0?(e(),n("span",Ae,Te)):$("",!0),!i(y).has(f.id)&&w===1?(e(),n("span",De,Ke)):$("",!0)],8,Le)]),_:2},1032,["to","class"])]),_:2},1024))),128))],64))),128))]),_:1})],64))])]),_:1})}}});const Oe=D(Me,[["__scopeId","data-v-9a18827c"]]),He={class:"tw-px-4"},Ue={class:"tw-flex tw-space-x-2 tw-items-center tw-w-full"},je={class:"tw-flex-auto"},Ee=t("i",{class:"fas fa-filter gray"},null,-1),Ge=[Ee],Je={class:"tw-flex-none"},Qe=["onClick"],We=t("i",{class:"fas fa-user-plus"},null,-1),Xe=[We],Ye={class:"tw-flex-none md:tw-hidden"},Ze=t("i",{class:"fas fa-sliders-h black"},null,-1),qe=[Ze],et=S({__name:"ContactsSidebarFilter",setup(l){const{showSidebar:o}=k(de());return(a,_)=>(e(),n("div",He,[t("div",Ue,[t("div",je,[d(pe,{"on-enter":s=>{s&&a.$router.push({name:"search",params:{id:`contact_info.${s.includes("@")?"email":/^[+\-0-9() ]+$/.test(s)?"phone":"name.name"}*=${s}`}})},"on-item-select":s=>{a.$router.push({name:"contact",params:{id:s.id}})}},null,8,["on-enter","on-item-select"])]),t("div",{class:"tw-flex-none icon tw-cursor-pointer",onClick:_[0]||(_[0]=s=>a.$router.push({name:"detailedSearch"}))},Ge),t("div",Je,[d(Y,{component:me},{default:r(({open:s})=>[t("button",{class:"circle",onClick:N(s,["prevent"])},Xe,8,Qe)]),_:1})]),t("div",Ye,[t("button",{class:"circle transparent",onClick:_[1]||(_[1]=N(s=>o.value=!i(o),["prevent"]))},qe)])])]))}}),tt=l=>(E("data-v-12cf976d"),l=l(),G(),l),st={class:"bricks"},nt=["onClick"],ot={class:"icon"},at=["src","alt"],it={class:"count"},ct=["onClick"],rt=tt(()=>t("span",{class:"icon"},[t("i",{class:"fas fa-users"})],-1)),lt={class:"count"},_t=S({__name:"ContactsSidebarBricks",setup(l){const{allContactsTotal:o}=k(ce()),{responsibles:a}=k(J()),_=Q(()=>{var s;return((s=a.value.find(C=>C.responsible.id===A.user.id))==null?void 0:s.count)??0});return(s,C)=>{const y=V("RouterLink");return e(),n("div",st,[d(y,{to:{name:"myContacts"},custom:""},{default:r(({isExactActive:v,navigate:h})=>[t("div",{class:I(["brick",{selected:v}]),onClick:h},[t("span",ot,[t("img",{src:i(A).user.userpic,alt:i(A).user.name,class:"userpic"},null,8,at)]),t("span",it,p(_.value),1),T(" "+p(s.$t("myContacts")),1)],10,nt)]),_:1}),d(y,{to:{name:"contacts"},custom:""},{default:r(({isExactActive:v,navigate:h})=>[t("div",{class:I(["brick",{selected:v}]),onClick:h},[rt,t("span",lt,p(i(o)),1),T(" "+p(s.$t("allContacts")),1)],10,ct)]),_:1})])}}});const dt=D(_t,[["__scopeId","data-v-12cf976d"]]),ut={key:0,class:"count"},pt=["onClick"],mt=t("i",{class:"fas fa-plus-circle"},null,-1),ft=[mt],ht={key:0,class:"tw-mx-4"},gt={key:0,class:"tw-pl-4 small gray"},$t={key:0,class:"tw-pl-8 small gray"},yt=["href","onClick"],vt={class:"count"},kt={class:"tw-truncate"},H=S({__name:"ContactsSidebarSegments",props:{only:{},except:{}},setup(l){const o=l,{t:a}=re(),_=le(),{sharedSegment:s,archivedSegment:C,mySegment:y,isFetching:v}=k(_);_.refetch();const h=fe(),{vaults:M}=k(h);h.refetch();const B=J(),{responsibles:x}=k(B);B.refetch();const w=Q(()=>x.value.map(b=>({...b,...b.responsible}))),f=he(),{tags:O}=k(f);f.refetch();const z=U([{id:"mySegment",name:a("mySegments"),items:y,loading:"segments",routeName:"segment",showPlus:!0},{id:"sharedSegment",name:a("sharedSegments"),items:s,loading:"segments",routeName:"segment",showPlus:!0},{id:"archivedSegment",name:a("archivedSegments"),items:C,loading:"segments",routeName:"segment",showCounter:!0},{id:"tags",name:a("tags"),items:O,loading:"tags",routeName:"tag"},{id:"vaults",name:a("vaults"),items:M,loading:"vaults",routeName:"vault"},{id:"responsibles",name:a("responsibles"),items:w,loading:"responsibles",routeName:"responsible"}]);return(b,St)=>{const q=V("RouterLink");return e(),m(P,{space:"4"},{default:r(()=>[(e(!0),n(g,null,R(o.only?z.value.filter(c=>c.id===o.only):o.except?z.value.filter(c=>c.id!==o.except):z.value,(c,ee)=>(e(),n(g,{key:c.id},[c.id!=="archivedSegment"||c.items.length?(e(),m(Z,{key:0,"show-caret":!0,"open-on-initial":!!c.items.length&&ee===0,"storage-key":c.id},{header:r(()=>[t("span",null,p(c.name),1),c.showCounter?(e(),n("span",ut,p(c.items.length||""),1)):$("",!0),c.showPlus?(e(),m(Y,{key:1,component:ue,"component-props":{initialShared:c.id==="mySegment"?"0":"1"}},{default:r(({open:u})=>[t("span",{class:"icon",onClick:N(u,["stop"])},ft,8,pt)]),_:2},1032,["component-props"])):$("",!0)]),default:r(()=>[c.id==="tags"?(e(),n("div",ht,[!c.items.length&&!i(v)?(e(),n("div",gt,p(b.$t("emptyList")),1)):$("",!0),d($e,{"entity-type":"contact",tags:c.items},null,8,["tags"])])):(e(),n(g,{key:1},[!c.items.length&&!i(v)?(e(),n("div",$t,p(b.$t("emptyList")),1)):(e(),m(W,{key:1},{default:r(()=>[(e(!0),n(g,null,R(c.items,u=>(e(),m(q,{key:u.id,to:{name:c.routeName,params:{id:u.id}},custom:""},{default:r(({isActive:te,href:se,navigate:ne})=>[d(X,{selected:te},{default:r(()=>[j((e(),n("a",{href:se,class:I(b.$constant.parentAppDisableRouterClass),onClick:ne},["icon"in u?(e(),m(L,{key:0,size:20,url:u.icon_path,"fa-icon":u.icon,"disable-rounded":!0},null,8,["url","fa-icon"])):"responsible"in u?(e(),m(L,{key:1,size:20,url:u.responsible.userpic},null,8,["url"])):"color"in u?(e(),m(L,{key:2,size:20,"fa-icon":"circle","icon-color":u.color},null,8,["icon-color"])):$("",!0),t("span",vt,p(u.count),1),t("span",kt,p(u.name),1)],10,yt)),[[i(ge),u.name,void 0,{right:!0,700:!0}]])]),_:2},1032,["selected"])]),_:2},1032,["to"]))),128))]),_:2},1024))],64))]),_:2},1032,["open-on-initial","storage-key"])):$("",!0)],64))),128))]),_:1})}}}),Ct=S({__name:"ContactsSidebar",setup(l){return(o,a)=>(e(),m(P,{space:"4",class:"tw-my-4"},{default:r(()=>[d(et),d(dt),d(H,{only:"mySegment"}),d(Oe),d(H,{except:"mySegment"})]),_:1}))}}),Zt=S({__name:"ContactsView",setup(l){return(o,a)=>{const _=V("RouterView");return e(),m(oe,{slidable:!0},{sidebar:r(()=>[d(Ct)]),default:r(()=>[d(_)]),_:1})}}});export{Zt as default};
