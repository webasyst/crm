import{d as f,o,b as c,w as n,c as a,i as t,r as i,p,f as d,U as v,ao as y,l as w,F as _,n as u,k as b,q as $,s as C,_ as D}from"./main-e63d6b61.js";import{M as I,D as M,_ as g}from"./DropDown-b752b133.js";const m=l=>($("data-v-02e7996b"),l=l(),C(),l),S={key:0,class:"item-header-wrapper item"},B={class:"item__header"},z={class:"item__title tw-uppercase tw-font-semibold"},F=["onClick"],L=m(()=>t("i",{class:"fas fa-times"},null,-1)),N=[L],P={key:2,class:"item"},V={class:"item__empty"},q=["onClick"],E={class:"label"},H=m(()=>t("hr",{class:"tw-mb-2"},null,-1)),U=f({__name:"DropdownModal",props:{items:{}},setup(l){const h=l;return(s,j)=>(o(),c(M,{width:"245px","disable-click-outside":!!s.$slots.title},{body:n(r=>[s.$slots.title?(o(),a("div",S,[t("div",B,[t("span",z,[i(s.$slots,"title",{},void 0,!0)]),t("span",{class:"item__btn",onClick:p(e=>r.hide(),["prevent"])},N,8,F)])])):d("",!0),s.$slots.body?i(s.$slots,"body",v(y({key:1},r)),void 0,!0):s.$slots.empty?(o(),a("div",P,[t("div",V,[i(s.$slots,"empty",{},void 0,!0)])])):s.items?(o(),c(I,{key:3},{default:n(()=>[(o(!0),a(_,null,w(h.items,(e,k)=>(o(),a(_,{key:k},[e.hide?d("",!0):(o(),c(g,{key:0},{default:n(()=>[t("a",{class:u(["actionItem",{"tw-opacity-50":e.lighten,"!tw-cursor-no-drop":e.disableClick}]),onClick:p(A=>{r.hide(),!e.disableClick&&e.clickHandler()},["prevent"])},[(o(),a("span",{key:e.classIcon,class:"icon"},[t("i",{class:u([e.classIcon])},null,2)])),t("span",E,b(e.label),1)],10,q)]),_:2},1024))],64))),128))]),_:2},1024)):d("",!0),s.$slots.footer?(o(),a(_,{key:4},[H,i(s.$slots,"footer",{},void 0,!0)],64)):d("",!0)]),default:n(()=>[i(s.$slots,"default",{},void 0,!0)]),_:3},8,["disable-click-outside"]))}});const K=D(U,[["__scopeId","data-v-02e7996b"]]);export{K as D};