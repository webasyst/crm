import{d as $,E as b,a as B,j as L,o as a,c as i,b as f,w as p,F as g,l as R,f as m,p as d,k as _,e as T,n as h,i as n}from"./main-ec0df6a4.js";import{C as V,a as M}from"./ChipsList-64240846.js";import{a as N}from"./emit-727b9eb7.js";const x=["onClick"],D=n("i",{class:"fas fa-hashtag"},null,-1),E={class:"tw-flex-auto tw-truncate"},F={class:"count tw-mt-0.5"},j=["onClick"],q=n("i",{class:"fas fa-times-circle text-gray !tw-opacity-70 hover:!tw-opacity-100"},null,-1),z=[q],k=8,X=$({__name:"TagsCloud",props:{entityType:{},tags:{},editable:{type:Boolean}},emits:["deleteTag"],setup(C,{emit:w}){const t=C,l=b(),r=B(!0);function y(s,o){if(t.entityType==="deal"){if(!s){N();const u=l.currentRoute.value.meta.tabView||l.currentRoute.value.name;l.push({name:u,query:{tag:o,closeModals:"1"}})}}else l.push(s?{name:"contacts"}:{name:"tag",params:{id:o}})}return(s,o)=>{const u=L("RouterLink");return a(),i(g,null,[t.tags.length?(a(),f(V,{key:0},{default:p(()=>[(a(!0),i(g,null,R([...t.tags].sort((e,c)=>c.count-e.count).slice(0,r.value?k:t.tags.length),e=>(a(),f(u,{key:e.id,to:{name:"tag",params:{id:e.id}},custom:""},{default:p(({isActive:c})=>[T(M,{class:h(["small",{selected:c}])},{default:p(()=>[n("a",{class:h(["tw-group",s.$constant.parentAppDisableRouterClass]),onClick:d(v=>y(c,e.id),["prevent"])},[D,n("span",E,_(e.name),1),n("span",F,_(e.count),1),t.editable?(a(),i("span",{key:0,class:"tw-rounded-full tw-bg-waBackground",onClick:d(v=>w("deleteTag",e.name),["stop","prevent"])},z,8,j)):m("",!0)],10,x)]),_:2},1032,["class"])]),_:2},1032,["to"]))),128))]),_:1})):m("",!0),t.tags.length>k?(a(),i("a",{key:1,class:"gray small",onClick:o[0]||(o[0]=d(e=>r.value=!r.value,["prevent"]))},_(r.value?`${s.$t("showAll")} (${t.tags.length})`:s.$t("collapse")),1)):m("",!0)],64)}}});export{X as _};
