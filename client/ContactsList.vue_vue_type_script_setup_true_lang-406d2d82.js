import{v as te}from"./index-8d873219.js";import{S as ue}from"./SkeletonList-192ed29f.js";import{E as de}from"./EmptyList-9d13a159.js";import{D as X}from"./DropDown-5d25a522.js";import{d as I,E as le,C as J,j as W,o as d,b,w as s,m as V,k as i,i as e,g as t,p as $,e as l,f as P,n as E,t as R,x as T,G as A,z as B,c as w,D as me,H as pe,I as fe,A as ce,a as q,B as _e,J as se,L as he,l as ne,F as ae,M as ve,N as $e,h as oe}from"./main-ec0df6a4.js";import{_ as g,M as x}from"./MenuList-466c4134.js";import{_ as j}from"./WaDialogOpener.vue_vue_type_script_setup_true_lang-e3cb498b.js";import{_ as ie,a as ge,u as we}from"./FormSegmentUpdate.vue_vue_type_script_setup_true_lang-98eec75b.js";import{W as G}from"./WaDialog-c7c19903.js";import{C as ke}from"./ContactsListItems-80e6e8c7.js";import{_ as be,a as Ce}from"./FormContactDelete.vue_vue_type_script_setup_true_lang-3ecd652a.js";import{_ as ye}from"./FormContactChangeResponsible.vue_vue_type_script_setup_true_lang-bb461b79.js";import{_ as Se}from"./DropdownIncludeSegment.vue_vue_type_script_setup_true_lang-d79f5cda.js";import{_ as Fe}from"./FormAddTag.vue_vue_type_script_setup_true_lang-d09f746e.js";import{F as Y}from"./FieldCheckbox-be3e2148.js";import{u as xe}from"./useSortable-5c8ee39e.js";import{C as Ie}from"./CustomColumn-959e6ffe.js";const De=["disabled","onClick"],Pe=e("br",null,null,-1),Ve=e("br",null,null,-1),Ae=I({__name:"FormSegmentDelete",props:{segment:{}},emits:["close"],setup(k,{emit:c}){const u=k,p=le(),{isFetching:n,error:r,execute:h}=J().deleteSegment(u.segment.id);async function a(){await h()&&(p.push({name:"contacts"}),c("close"))}return(o,m)=>{const C=W("i18n-t");return d(),b(G,{onClose:m[0]||(m[0]=D=>c("close"))},{header:s(()=>[V(i(o.$t("deleteSegment")),1)]),submit:s(()=>[e("button",{class:"button",disabled:t(n),onClick:$(a,["prevent"])},i(o.$t("delete")),9,De)]),error:s(()=>[V(i(t(r)),1)]),default:s(()=>[l(C,{tag:"p",keypath:"segmentDeleteMessage"},{newline:s(()=>[Pe,Ve]),_:1})]),_:1})}}}),Re=["onClick"],Le=["onClick"],Be=["onClick"],Te=I({__name:"DropdownSegment",props:{segment:{}},setup(k){const c=k,{isFetching:u,execute:p}=J().toggleSegmentArchive(c.segment.id);return(n,r)=>{const h=W("RouterLink");return d(),b(x,null,{default:s(()=>[l(g,null,{default:s(()=>[l(j,{component:ie,"component-props":{segment:c.segment}},{default:s(({open:a})=>[e("a",{onClick:$(a,["prevent"])},i(n.$t("mainSettings")),9,Re)]),_:1},8,["component-props"])]),_:1}),n.segment.type==="category"?(d(),b(g,{key:0},{default:s(()=>[l(j,{component:ge,"component-props":{segment:c.segment}},{default:s(({open:a})=>[e("a",{onClick:$(a,["prevent"])},i(n.$t("addContactsToSegment")),9,Le)]),_:1},8,["component-props"])]),_:1})):P("",!0),n.segment.type==="search"?(d(),b(g,{key:1},{default:s(()=>{var a;return[l(h,{class:E(n.$constant.parentAppDisableRouterClass),to:{name:"updateSegmentFilter",params:{id:n.segment.id,searchHash:(a=n.segment.hash)==null?void 0:a.replace("crmSearch/","")}}},{default:s(()=>[V(i(n.$t("updateSegmentFilter")),1)]),_:1},8,["class","to"])]}),_:1})):P("",!0),l(g,null,{default:s(()=>[e("a",{onClick:r[0]||(r[0]=$(()=>{t(u)||t(p)()},["prevent"]))},i(n.$t(n.segment.archived?"fromArchive":"toArchive")),1)]),_:1}),l(g,null,{default:s(()=>[l(j,{component:Ae,"component-props":{segment:c.segment}},{default:s(({open:a})=>[e("a",{onClick:$(a,["prevent"])},i(n.$t("deleteSegment")),9,Be)]),_:1},8,["component-props"])]),_:1})]),_:1})}}}),Ue=I({__name:"DropdownSearch",setup(k){var p,n;const{previousFetchParams:c}=R(T()),{show:u}=A(ie,{initialType:"search",initialName:(p=c.value)==null?void 0:p.title,initialHash:(n=c.value)==null?void 0:n.hash});return(r,h)=>{const a=W("RouterLink");return d(),b(x,null,{default:s(()=>[l(g,null,{default:s(()=>{var o,m;return[l(a,{to:{name:"detailedSearch",params:{searchHash:(m=(o=t(c))==null?void 0:o.hash)==null?void 0:m.replace("crmSearch/","")}},class:E(r.$constant.parentAppDisableRouterClass)},{default:s(()=>[V(i(r.$t("updateSearchConditions")),1)]),_:1},8,["to","class"])]}),_:1}),l(g,null,{default:s(()=>[e("a",{onClick:h[0]||(h[0]=$((...o)=>t(u)&&t(u)(...o),["prevent"]))},i(r.$t("saveAsFilter")),1)]),_:1})]),_:1})}}}),Me={class:"tw-flex tw-items-center tw-space-x-2 tw-flex-auto tw-w-1/2"},Ne=["title"],ze=e("button",{class:"circle light-gray"},[e("i",{class:"fas fa-pen"})],-1),Oe=I({__name:"ContactsListTitle",setup(k){const{getSegmentByHash:c}=J(),{previousFetchParams:u}=R(T()),p=B(()=>{var r;return(r=u.value)!=null&&r.hash?c(u.value.hash):null}),n=B(()=>{var r,h;return p.value?p.value.is_editable?Te:null:(h=(r=u.value)==null?void 0:r.hash)!=null&&h.includes("crmSearch/")?Ue:null});return(r,h)=>{var a,o,m,C;return d(),w("div",Me,[e("div",{class:"tw-text-xl tw-font-bold tw-truncate",title:((a=p.value)==null?void 0:a.name)??((o=t(u))==null?void 0:o.title)},i(((m=p.value)==null?void 0:m.name)??((C=t(u))==null?void 0:C.title)),9,Ne),n.value?(d(),b(X,{key:0},{body:s(()=>[(d(),b(me(n.value),pe(fe({segment:p.value})),null,16))]),default:s(()=>[ze]),_:1})):P("",!0)])}}}),He=["src"],re=I({__name:"FormContactsExportCSV",props:{contactIds:{},hash:{},checkedCount:{}},setup(k){const c=k;return(u,p)=>(d(),b(G,{"hide-buttons":!0},{default:s(()=>[e("iframe",{src:`${t(ce).baseUrl}?module=contactOperation&action=export${c.contactIds?`&ids=${c.contactIds.join(",")}`:c.hash?`&hash=${encodeURIComponent(c.hash)}`:""}&checked_count=${c.checkedCount}`,frameborder:"0",class:"tw-w-full",style:{height:"400px"}},null,8,He)]),_:1}))}}),je=["disabled","onClick"],Ee={style:{height:"320px","overflow-y":"auto"}},qe=I({__name:"FormContactAddToSegment",props:{contactIds:{}},emits:["close"],setup(k,{emit:c}){const u=k,p=J(),n=q(),r=q();async function h(){n.value&&(r.value=p.includeContactsToSegment(n.value,u.contactIds),await r.value.execute(),r.value.error||c("close"))}return(a,o)=>(d(),b(G,{"use-cancel-as-button-label":!0,onClose:o[1]||(o[1]=m=>c("close"))},{header:s(()=>[V(i(a.$t("addToSegment")),1)]),submit:s(()=>{var m;return[e("button",{disabled:!!((m=r.value)!=null&&m.isFetching)||!n.value,onClick:$(h,["prevent"])},i(a.$t("save")),9,je)]}),error:s(()=>{var m;return[V(i((m=r.value)==null?void 0:m.error),1)]}),default:s(()=>[e("div",Ee,[l(Se,{modelValue:n.value,"onUpdate:modelValue":o[0]||(o[0]=m=>n.value=m)},null,8,["modelValue"])])]),_:1}))}}),Je=["data-id"],We=e("div",{class:"handle tw-flex tw-justify-center tw-w-4"},[e("i",{class:"fas fa-grip-vertical gray"})],-1),Ge={class:"small"},Ke={class:"small"},Qe=I({__name:"FormContactsFields",emits:["close"],setup(k,{emit:c}){const{t:u}=_e(),p=["name","lastname","firstname","middlename"],n=[{id:"last_action",name:u("lastAction"),value:"",sort:0},{id:"create_datetime",name:u("createDate"),value:"",sort:0},{id:"tags",name:u("tags"),value:"",sort:0}],{data:r,isFetching:h,error:a}=se("crm.field.list?scope=contact").get().json(),o=T(),{previousFetchParams:m}=R(o),C=q([]),D=B(()=>{var f;return(f=C.value)==null?void 0:f.filter(_=>_.value).sort((_,v)=>_.sort<0?1:_.sort-v.sort)}),K=B(()=>{var f;return(f=C.value)==null?void 0:f.filter(_=>!_.value)}),U=q(null);he(()=>{Array.isArray(r.value)&&(C.value=[...n,...r.value.filter(f=>!p.includes(f.id))].reduce((f,_)=>{var M,N;const{id:v,name:y}=_,F=((N=(M=m.value)==null?void 0:M.fields)==null?void 0:N.indexOf(v))??-1;if(f.push({id:v,name:y,value:F>-1?"1":"",sort:F}),"fields"in _){const O=_.fields.map(S=>{var Z,ee;const z=`${_.id}:${S.id}`,H=((ee=(Z=m.value)==null?void 0:Z.fields)==null?void 0:ee.indexOf(z))??-1;return{id:z,name:`${_.name} – ${S.name}`,value:H>-1?"1":"",sort:H}});f.push(...O)}return f},[]))});function L(){xe(U,D,{animation:150,handle:".handle"})}function Q(){var f;o.fields=Array.from(((f=U.value)==null?void 0:f.querySelectorAll("[data-id]"))||[]).map(_=>_.getAttribute("data-id")??""),o.updateFetchParams(),se("crm.user.settings.save").patch({contact_list_columns:o.fields.map(_=>({field:_,width:"m"}))}),c("close")}return(f,_)=>(d(),b(G,{onClose:_[0]||(_[0]=v=>c("close"))},{header:s(()=>[V(i(f.$t("selectFields")),1)]),submit:s(()=>[C.value?(d(),w("button",{key:0,class:"button",onClick:Q},i(f.$t("save")),1)):P("",!0)]),default:s(()=>[l(be,{"is-fetching":t(h),error:t(a)},{default:s(()=>[l(Ie,{space:"4",class:"fields-list"},{default:s(()=>[e("div",{ref_key:"sortableRef",ref:U,class:"tw-sticky tw-top-0 tw-bg-waBlank tw-z-20 tw-pb-4 tw-border-0 tw-border-solid tw-border-b tw-border-b-waBorder",onVnodeMounted:L},[(d(!0),w(ae,null,ne(D.value,v=>(d(),w("div",{key:v.id,"data-id":v.id,class:"tw-flex tw-items-center tw-space-x-2 tw-mb-1"},[We,l(Y,{modelValue:v.value,"onUpdate:modelValue":y=>v.value=y},{default:s(()=>[e("span",Ge,i(v.name),1)]),_:2},1032,["modelValue","onUpdate:modelValue"])],8,Je))),128))],512),e("div",null,[(d(!0),w(ae,null,ne(K.value,v=>(d(),w("div",{key:v.id,class:"tw-flex tw-items-center tw-space-x-2 tw-mb-1"},[l(Y,{modelValue:v.value,"onUpdate:modelValue":y=>v.value=y},{default:s(()=>[e("span",Ke,i(v.name),1)]),_:2},1032,["modelValue","onUpdate:modelValue"])]))),128))])]),_:1})]),_:1},8,["is-fetching","error"])]),_:1}))}}),Xe=["onClick"],Ye=e("div",{class:"icon"},[e("i",{class:"fas fa-table"})],-1),Ze=e("div",{class:"icon"},[e("i",{class:"fas fa-exchange-alt"})],-1),et=["href"],tt=e("div",{class:"icon"},[e("i",{class:"fas fa-upload"})],-1),st=e("div",{class:"icon"},[e("i",{class:"fas fa-download"})],-1),nt=I({__name:"DropdownSettings",setup(k){const{previousFetchParams:c}=R(T());return(u,p)=>{const n=W("RouterLink");return d(),b(x,null,{default:s(()=>[l(g,null,{default:s(()=>[l(j,{component:Qe},{default:s(({open:r})=>[e("a",{onClick:$(r,["prevent"])},[Ye,e("span",null,i(u.$t("customizeColumns")),1)],8,Xe)]),_:1})]),_:1}),l(g,null,{default:s(()=>[l(n,{to:{name:"mergeDuplicates"}},{default:s(()=>[Ze,e("span",null,i(u.$t("mergeDuplicates")),1)]),_:1})]),_:1}),l(g,null,{default:s(()=>[e("a",{href:`${t(ce).baseUrl}contact/import/`},[tt,e("span",null,i(u.$t("importContacts")),1)],8,et)]),_:1}),l(g,null,{default:s(()=>[e("a",{onClick:p[0]||(p[0]=$(r=>{var h,a;return t(A)(re,{hash:(h=t(c))==null?void 0:h.hash,checkedCount:((a=t(c))==null?void 0:a.total_count)??0}).show()},["prevent"]))},[st,e("span",null,i(u.$t("export")),1)])]),_:1})]),_:1})}}}),at={key:0,class:"tw-flex tw-space-x-2"},ot={class:"tw-relative"},lt={class:"light-gray tw-flex tw-items-center tw-space-x-2"},ct=e("i",{class:"fas fa-caret-down"},null,-1),it=["onClick"],rt=e("hr",{class:"tw-m-0"},null,-1),ut=e("span",{class:"icon"},[e("i",{class:"fas fa-folder-open"})],-1),dt=e("span",{class:"icon"},[e("i",{class:"fas fa-hashtag"})],-1),mt=e("span",{class:"icon"},[e("i",{class:"fas fa-users"})],-1),pt=e("span",{class:"icon"},[e("i",{class:"fas fa-user-circle"})],-1),ft=e("span",{class:"icon"},[e("i",{class:"fas fa-download"})],-1),_t=e("span",{class:"icon"},[e("i",{class:"fas fa-trash-alt"})],-1),ht=e("i",{class:"fas fa-times"},null,-1),vt=[ht],$t={key:1,class:"tw-flex tw-space-x-2"},gt=e("i",{class:"fas fa-check-square"},null,-1),wt={class:"light-gray"},kt=e("i",{class:"fas fa-ellipsis-h"},null,-1),bt=e("i",{class:"fas fa-caret-down"},null,-1),Ct=I({__name:"ContactsListControls",setup(k){const c=le(),{contacts:u,bulkSelectMode:p,bulkSelectIds:n}=R(T()),r=B(()=>JSON.stringify(n.value)===JSON.stringify(u.value.map(a=>a.id)));ve(()=>{p.value=!1});function h(){const a=u.value.map(o=>o.id);n.value=r.value?[]:a}return(a,o)=>t(p)?(d(),w("div",at,[e("div",ot,[l(X,{right:!0,"disable-click-outside":!0},{body:s(()=>[l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:$(h,["prevent"])},[l(Y,{"model-value":r.value?"1":""},{default:s(()=>[V(i(a.$t("selectAll")),1)]),_:1},8,["model-value"])],8,it)]),_:1})]),_:1}),rt,e("div",{class:E({"tw-opacity-40 tw-pointer-events-none":!t(n).length})},[l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[0]||(o[0]=$(m=>t(A)(qe,{contactIds:t(n)}).show(),["prevent"]))},[ut,e("span",null,i(a.$t("addToSegment")),1)])]),_:1})]),_:1}),l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[1]||(o[1]=$(m=>t(A)(Fe,{entityType:"contact",entityIds:t(n)}).show(),["prevent"]))},[dt,e("span",null,i(a.$t("tags")),1)])]),_:1})]),_:1}),l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[2]||(o[2]=$(()=>{t(c).push({name:"merge",query:{ids:t(n).join(",")}})},["prevent"]))},[mt,e("span",null,i(a.$t("merge")),1)])]),_:1})]),_:1}),l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[3]||(o[3]=$(m=>t(A)(ye,{contactIds:t(n)}).show(),["prevent"]))},[pt,e("span",null,i(a.$t("addResponsible")),1)])]),_:1})]),_:1}),l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[4]||(o[4]=$(m=>t(A)(re,{contactIds:t(n),checkedCount:t(n).length}).show(),["prevent"]))},[ft,e("span",null,i(a.$t("export")),1)])]),_:1})]),_:1}),l(x,null,{default:s(()=>[l(g,null,{default:s(()=>[e("a",{onClick:o[5]||(o[5]=$(m=>t(A)(Ce,{contactIds:t(n),disableContactsPageRedirect:!0}).show(),["prevent"]))},[_t,e("span",null,i(a.$t("delete")),1)])]),_:1})]),_:1})],2)]),default:s(()=>[e("button",lt,[e("span",null,i(a.$t("selected")),1),e("span",{class:E(["badge smaller",t(n).length?"blue":"gray"])},i(t(n).length),3),ct])]),_:1})]),e("button",{class:"light-gray",onClick:o[6]||(o[6]=$(m=>p.value=!1,["prevent"]))},vt)])):(d(),w("div",$t,[e("button",{class:"light-gray tw-flex tw-items-center tw-space-x-2",onClick:o[7]||(o[7]=$(m=>p.value=!0,["prevent"]))},[gt,e("span",null,i(a.$t("select")),1)]),l(X,{right:!0},{body:s(()=>[l(nt)]),default:s(()=>[e("button",wt,[kt,V("  "+i(a.$t("settings"))+"  ",1),bt])]),_:1})]))}}),yt={class:"tw-@container/contacts tw-flex tw-flex-col tw-h-full tw-bg-waBlank"},St={key:0,class:"tw-p-4"},Ft={class:"tw-flex tw-items-center tw-space-x-4 tw-justify-between tw-h-8"},xt={key:0,class:"skeleton tw-w-48"},It=e("div",{class:"skeleton-line",style:{height:"2.2rem"}},null,-1),Dt=[It],Pt={key:2,class:"tw-flex-none tw-ml-auto tw-hidden md:tw-flex"},Vt={class:"md:tw-hidden"},At=e("i",{class:"fas fa-sliders-h black"},null,-1),Rt=[At],Lt={key:1,class:"tw-p-4 tw-flex-auto tw-min-h-[80vh]"},Bt={key:2},Tt=e("div",{class:"icon size-80"},[e("i",{class:"fas fa-user-friends"})],-1),Ut={key:0,class:"tw-text-center tw-py-4"},Mt=e("div",{class:"spinner custom-p-16"},null,-1),Nt=[Mt],zt={key:0,class:"tw-text-center tw-py-4"},Ot=e("div",{class:"spinner custom-p-16"},null,-1),Ht=[Ot],ls=I({__name:"ContactsList",props:{fetchParams:{},hideHeader:{type:Boolean},activeContactId:{}},setup(k){const c=k,u=T(),{fetchNextPage:p,fetchPrevPage:n,updateFetchParams:r,bulkSelectToggle:h}=u,{contacts:a,prevOffset:o,nextOffset:m,fetchParams:C,previousFetchParams:D,bulkSelectMode:K,bulkSelectIds:U,isFetching:L,isFinished:Q,error:f}=R(u),{showSidebar:_}=R(we()),v=B(()=>{var y;return(((y=D.value)==null?void 0:y.columns)||[]).filter(F=>F.id!=="name")});return $e(()=>c.fetchParams,r,{immediate:!0}),(y,F)=>{var M,N,O;return d(),w("div",yt,[y.hideHeader?P("",!0):(d(),w("div",St,[e("div",Ft,[t(L)&&!t(C).offset||((M=t(f))==null?void 0:M.code)===20?(d(),w("div",xt,Dt)):(d(),b(Oe,{key:1})),t(a).length?(d(),w("div",Pt,[l(Ct)])):P("",!0),e("div",Vt,[e("button",{class:"circle transparent",onClick:F[0]||(F[0]=S=>_.value=!t(_))},Rt)])])])),t(L)&&!t(C).offset&&(!c.activeContactId||!t(o))||((N=t(f))==null?void 0:N.code)===20?(d(),w("div",Lt,[l(ue)])):t(f)?(d(),w("div",Bt,i(t(f)),1)):t(Q)&&!t(a).length?(d(),b(de,{key:3,message:y.$t("noContacts")},{default:s(()=>[Tt]),_:1},8,["message"])):t(a).length&&t(D)?(d(),b(ke,{key:4,contacts:t(a),"header-columns":v.value,"fetch-params":t(C),sort:t(D).sort,"active-contact-id":c.activeContactId,"context-contact-id":(O=y.fetchParams)==null?void 0:O.context_contact_id,"prev-offset":t(o),"bulk-select-mode":t(K),"bulk-select-ids":t(U),onBulkSelectToggle:F[1]||(F[1]=S=>t(h)(...S)),onUpdateFetchParams:F[2]||(F[2]=S=>t(r)(S))},{lazyloadUp:s(()=>[t(o)>0?oe((d(),w("div",Ut,Nt)),[[t(te),([{isIntersecting:S}])=>{S&&!t(L)&&t(n)()}]]):P("",!0)]),lazyloadDown:s(()=>{var S,z;return[t(m)+(((S=t(D))==null?void 0:S.limit)??0)<(((z=t(D))==null?void 0:z.total_count)??0)?oe((d(),w("div",zt,Ht)),[[t(te),([{isIntersecting:H}])=>{H&&!t(L)&&t(p)()}]]):P("",!0)]}),_:1},8,["contacts","header-columns","fetch-params","sort","active-contact-id","context-contact-id","prev-offset","bulk-select-mode","bulk-select-ids"])):P("",!0)])}}});export{ls as _};