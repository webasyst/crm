import{d as $,S as b,a as B,j as L,o as s,c,b as f,w as p,F as _,l as R,f as m,p as d,k as g,e as V,n as h,i as o,g as y,B as N,a2 as S}from"./main-e63d6b61.js";import{C as x,a as D}from"./ChipsList-f1962714.js";import{a as F}from"./emit-6e8c94d0.js";const M=["onClick"],j=o("i",{class:"fas fa-hashtag"},null,-1),q={class:"tw-flex-auto tw-truncate"},z={class:"count tw-mt-0.5"},A=["onClick"],E=o("i",{class:"fas fa-times-circle text-gray !tw-opacity-70 hover:!tw-opacity-100"},null,-1),P=[E],k=8,I=$({__name:"TagsCloud",props:{entityType:{},tags:{},editable:{type:Boolean}},emits:["deleteTag"],setup(w,{emit:C}){const t=w,l=b(),r=B(!0);function v(a,n){if(t.entityType==="deal"){if(!a){F();const u=l.currentRoute.value.meta.tabView||l.currentRoute.value.name;l.push({name:u,query:{tag:n}})}}else l.push(a?{name:"contacts"}:{name:"tag",params:{id:n}})}return(a,n)=>{const u=L("RouterLink");return s(),c(_,null,[t.tags.length?(s(),f(x,{key:0},{default:p(()=>[(s(!0),c(_,null,R([...t.tags].sort((e,i)=>i.count-e.count).slice(0,r.value?k:t.tags.length),e=>(s(),f(u,{key:e.id,to:{name:"tag",params:{id:e.id}},custom:""},{default:p(({isActive:i})=>[V(D,{class:h(["small",{selected:i}])},{default:p(()=>[o("a",{class:h(["tw-group",a.$constant.parentAppDisableRouterClass]),onClick:d(T=>y(N).webView?y(S).emit("spa:navigateTo",{name:`${t.entityType}sByTag`,id:e.id}):v(i,e.id),["prevent"])},[j,o("span",q,g(e.name),1),o("span",z,g(e.count),1),t.editable?(s(),c("span",{key:0,class:"tw-rounded-full tw-bg-waBackground",onClick:d(T=>C("deleteTag",e.name),["stop","prevent"])},P,8,A)):m("",!0)],10,M)]),_:2},1032,["class"])]),_:2},1032,["to"]))),128))]),_:1})):m("",!0),t.tags.length>k?(s(),c("a",{key:1,class:"gray small",onClick:n[0]||(n[0]=d(e=>r.value=!r.value,["prevent"]))},g(r.value?`${a.$t("showAll")} (${t.tags.length})`:a.$t("collapse")),1)):m("",!0)],64)}}});export{I as _};