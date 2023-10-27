import{d as V,z as u,o as s,c as l,F as L,e as v,i as a,k as w,b as C,h as I,g as m,m as F,n as g,a as S,v as E,p as M,r as T,f as o,Q as H,a1 as U,a2 as Y,ah as j,j as Q,T as P,l as X,w as B}from"./main-ec0df6a4.js";import{_ as q,a as G,b as J,c as K,d as Z}from"./DealsItemAmount.vue_vue_type_style_index_0_lang-d3817178.js";import{d as ee}from"./dayjs-44a66bd3.js";import{v as W}from"./vTooltip-c8fe82a0.js";import{U as z}from"./UserPic-87c13a4a.js";import{_ as se}from"./ContactsListItemAction.vue_vue_type_script_setup_true_lang-37a8a184.js";import{d as te}from"./date-8ac6c57c.js";import{a as le}from"./index-4793d891.js";import{S as ie}from"./SkeletonList-192ed29f.js";import{E as ae}from"./EmptyList-9d13a159.js";const oe={key:0,class:"deals-item-stage__closed"},ne={class:"deals-item-stage__text"},de={class:"deals-item-stage__text"},ce={class:"deals-item-stage__closed-datetime"},re={key:1,class:"deals-item-stage__open"},me={key:1,class:"icon size-12 tw-flex",style:{color:"var(--deal-color-gray-primary)"}},ue=a("i",{class:"fas fa-info-circle"},null,-1),_e=[ue],pe={class:"deals-item-stage__text"},ve=V({__name:"DealsListItemStage",props:{deal:{},closed:{type:Boolean},isMobile:{type:Boolean},fontNormal:{type:Boolean}},setup(y){const e=y,t=u(()=>ee(e.deal.closed_datetime)),_=u(()=>t.value.isYesterday()),d=u(()=>t.value.isToday()),r=u(()=>t.value.format("HH:mm")),p=u(()=>t.value.format("L")),b=u(()=>e.isMobile?14:10);return(f,R)=>{var x,i;return s(),l("div",{class:g(["deals-item-stage",e.fontNormal||e.isMobile?"tw-font-normal":"tw-font-semibold",{"deals-item-stage--mobile":e.isMobile}])},[e.closed?(s(),l("div",oe,[e.deal.status_id==="WON"?(s(),l(L,{key:0},[v(z,{size:b.value,"fa-icon":"flag-checkered","icon-color":e.deal.stage.color},null,8,["size","icon-color"]),a("div",ne,w(f.$t("won")),1)],64)):(s(),l(L,{key:1},[v(z,{size:b.value,"fa-icon":"ban","icon-color":e.deal.stage.color},null,8,["size","icon-color"]),a("div",de,w(f.$t("lost")),1)],64)),a("span",ce,w(_.value?`${f.$t("yesterday")} ${r.value}`:d.value?`${f.$t("today")} ${r.value}`:p.value),1)])):(s(),l("div",re,[(x=e.deal.stage)!=null&&x.color?(s(),C(z,{key:0,"disable-rounded":"","bg-color":e.deal.stage.color,size:b.value},null,8,["bg-color","size"])):(s(),l("span",me,_e)),I((s(),l("div",pe,[F(w(e.deal.stage?e.deal.stage.color?e.deal.stage.name:f.$t("stageDeleted"):f.$t("funnelDeleted")),1)])),[[m(W),(i=e.deal.stage)==null?void 0:i.name,void 0,{right:!0,500:!0}]])]))],2)}}});const fe={class:"deals-item-last_action"},he=V({__name:"DealsListItemLastAction",props:{lastAction:{},disableTootltip:{type:Boolean}},setup(y){const e=y,t=S(null),_=u(()=>!e.disableTootltip&&e.lastAction&&t.value?t.value.innerHTML:"");return(d,r)=>I((s(),l("div",fe,[a("div",{ref_key:"lastActionRef",ref:t,class:"deals-item-last_action__inner",style:{"min-width":"170px"}},[v(se,{action:e.lastAction,"disable-line-clamp":!0},null,8,["action"])],512)])),[[m(W),{delay:500,interactive:!0,allowHTML:!0,placement:"left",content:_.value},void 0,{updated:!0}]])}}),ge={key:1,class:"deal-list__item item-stage_id"},ye={key:2,class:"deal-list__item item-name"},we={key:3,class:"deal-list__item item-states"},be={key:4,class:"deal-list__item item-create_datetime"},$e={class:"deals-item-create_datetime mobile:tw-text-[var(--deal-color-gray-secondary)] mobile:tw-text-[13px]"},ke={key:0,class:"gray tw-mr-1"},Ce={class:"md:tw-contents tw-col-span-2 tw-flex tw-flex-row-reverse tw-items-center tw-justify-between"},xe={key:0,class:"deal-list__item item-amount"},Te={key:1,class:"deal-list__item item-user_name"},Le={key:6,class:"deal-list__item item-last_action"},Me=V({__name:"DealsListItem",props:{deal:{},useColumnsIds:{},isSelected:{type:Boolean},isMobile:{type:Boolean},isTileView:{type:Boolean}},setup(y){const e=y,t=u(()=>r=>e.useColumnsIds.includes(r)),_=u(()=>e.deal.status_id!=="OPEN"),d=u(()=>te(e.deal.create_datetime));return(r,p)=>(s(),l("div",{class:g(["deal-list__row",{highlighted:e.isSelected,"deal-list__row--deal-closed":_.value}])},[t.value("checkbox")?I((s(),l("div",{key:0,class:"deal-list__item deal-list__item--not-opacity",onClick:p[0]||(p[0]=M(()=>{},["stop"]))},[T(r.$slots,"checkbox")],512)),[[E,!e.isMobile]]):o("",!0),t.value("stage_id")?(s(),l("div",ge,[v(ve,{deal:e.deal,closed:_.value,"is-mobile":e.isMobile,"font-normal":e.isTileView},null,8,["deal","closed","is-mobile","font-normal"])])):o("",!0),t.value("name")?(s(),l("div",ye,[v(q,{deal:e.deal,"use-userpic":!e.isMobile&&!e.isTileView,"disable-tootltip":e.isMobile||e.isTileView,size:e.isMobile||e.isTileView?"15px":null},null,8,["deal","use-userpic","disable-tootltip","size"])])):o("",!0),t.value("states")?(s(),l("div",we,[v(G,{deal:e.deal},null,8,["deal"])])):o("",!0),t.value("create_datetime")?(s(),l("div",be,[a("div",$e,[e.isTileView?(s(),l("span",ke,w(r.$t("created")),1)):o("",!0),a("span",{class:g({"tw-lowercase":e.isTileView})},w(d.value),3)])])):o("",!0),a("div",Ce,[t.value("amount")?(s(),l("div",xe,[v(J,{deal:e.deal,size:e.isTileView?"base":e.isMobile?"lg":void 0,"swap-icon":e.isMobile,"big-icon":e.isMobile},null,8,["deal","size","swap-icon","big-icon"])])):o("",!0),t.value("user_name")?(s(),l("div",Te,[e.deal.user?(s(),C(K,{key:0,user:e.deal.user,"align-right":e.isTileView,"default-color":e.isMobile||e.isTileView,"disable-tootltip":e.isMobile},null,8,["user","align-right","default-color","disable-tootltip"])):o("",!0)])):o("",!0)]),t.value("tags")&&!e.isMobile?(s(),l("div",{key:5,class:"deal-list__item deal-list__item--not-opacity item-tags",onClick:p[1]||(p[1]=M(()=>{},["prevent"]))},[v(Z,{tags:e.deal.tags,"disable-overflow-soft":e.isTileView,small:e.isTileView},null,8,["tags","disable-overflow-soft","small"])])):o("",!0),t.value("last_action")&&!e.isMobile?(s(),l("div",Le,["last_action"in e.deal?(s(),C(he,{key:0,"last-action":e.deal.last_action,"disable-tootltip":e.isTileView},null,8,["last-action","disable-tootltip"])):o("",!0)])):o("",!0)],2))}});const Ve={class:"deals-list__wrapper"},Ie=a("i",{class:"fas fa-chevron-left"},null,-1),Ae=[Ie],ze=a("i",{class:"fas fa-chevron-right"},null,-1),Se=[ze],Re={key:1,class:"deals-list__skeleton-body tw-h-full md:tw-pt-3"},De=a("div",{class:"icon size-80"},[a("i",{class:"fas fa-funnel-dollar"})],-1),Qe=V({__name:"DealsListTable",props:{deals:{},useColumnsIds:{},isFetching:{type:Boolean},navigateRouteName:{},tileView:{type:Boolean}},setup(y){const e=y,t=S(null),_=S(!1),d=H(le),{x:r,arrivedState:p}=U(t,{throttle:200}),b=u(()=>!d.value&&_.value&&!p.right);Y(t,i=>{const n=i[0];_.value=n.target.scrollWidth>n.target.clientWidth}),j(()=>e.isFetching,i=>{if(!i){const n=t.value;n&&(_.value=n.scrollWidth>n.clientWidth)}},{debounce:500});function f(i){var N;const $=Array.from(((N=t.value)==null?void 0:N.querySelectorAll(".deal-list__row:last-of-type > *"))||[]),c=$.map(h=>h.offsetLeft),k=$.length-1,D=c[k]+350,A=$[k].offsetWidth;if(A>D){let h=D;for(;h<A;)c.push(h),h+=350;c.push(A)}const O=i==="next"?c.find(h=>h>r.value+1):c.reverse().find(h=>h<r.value-1);r.value=O??c[2]}const R={checkbox:"minmax(40px, 40px)",stage_id:"minmax(230px, 2fr)",name:"minmax(240px, 2fr)",states:"minmax(100px, 0.5fr)",create_datetime:"minmax(135px, 1fr)",amount:"minmax(150px, 1fr)",tags:"minmax(180px, 1.33fr)",user_name:"minmax(250px, 2fr)",last_action:"minmax(auto, 100%)"},x=u(()=>{let i="";return e.useColumnsIds.forEach(n=>{i+=R[n]+" "}),i});return(i,n)=>{const $=Q("RouterLink");return s(),l("div",Ve,[a("div",{class:g(["deals-list",{"deals-list--mobile":m(d),"deals-list--tiles":!m(d)&&e.tileView,"refresh-list":e.deals.length&&e.isFetching}])},[i.deals.length?(s(),l("div",{key:0,ref_key:"scrollableContainerRef",ref:t,class:g(["deals-list__table-wrapper",{"overflow-soft-hide-x":b.value}])},[a("div",{class:"deals-list__table",style:P({"--deals-list-row-grid-template-columns":x.value})},[!e.tileView&&!m(d)?(s(),l(L,{key:0},[a("div",{class:g([{"tw-hidden":!_.value},"tw-absolute tw-top-1 tw-left-0 tw-w-full tw-z-30"])},[I(a("div",{class:g([{"tw-opacity-30":m(p).left},"col-nav col-nav--left tw-left-0"]),onClick:n[0]||(n[0]=M(c=>f("prev"),["prevent"]))},Ae,2),[[E,!m(p).left]]),a("div",{class:g([{"tw-opacity-30":m(p).right},"col-nav col-nav--right tw-right-0"]),onClick:n[1]||(n[1]=M(c=>f("next"),["prevent"]))},Se,2)],2),T(i.$slots,"headers",{useColumnsIds:i.useColumnsIds})],64)):o("",!0),(s(!0),l(L,null,X(e.deals,c=>(s(),C($,{key:c.id,to:{name:e.navigateRouteName||"deal",params:{id:c.id}},custom:""},{default:B(({navigate:k})=>[T(i.$slots,"listItem",{deal:c,isMobile:!!m(d),useColumnsIds:i.useColumnsIds,navigate:k},()=>[v(Me,{deal:c,"use-columns-ids":i.useColumnsIds,"is-mobile":!!m(d),"is-tile-view":!m(d)&&e.tileView,onClick:k},null,8,["deal","use-columns-ids","is-mobile","is-tile-view","onClick"])])]),_:2},1032,["to"]))),128))],4),T(i.$slots,"lazy")],2)):o("",!0),e.isFetching?(s(),l("div",Re,[v(ie,{"row-height":m(d)?"108px":"38px"},null,8,["row-height"])])):o("",!0),e.deals.length===0&&!e.isFetching?(s(),C(ae,{key:2,message:i.$t("noDeals")},{default:B(()=>[De]),_:1},8,["message"])):o("",!0)],2)])}}});export{Qe as _,Me as a};
