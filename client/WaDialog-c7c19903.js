import{d as p,P as _,aF as h,S as l,M as f,ag as v,aB as g,o as a,c as n,i as e,p as B,f as i,r as c,n as w,k as b,A as y,q as k,s as C,_ as S}from"./main-ec0df6a4.js";const u=t=>(k("data-v-da143c4b"),t=t(),C(),t),I={class:"dialog"},M=u(()=>e("div",{class:"dialog-background"},null,-1)),$={class:"dialog-body"},D=["onClick"],L=u(()=>e("i",{class:"fas fa-times"},null,-1)),W=[L],A={key:1,class:"dialog-header"},E={key:2,class:"dialog-footer"},F={class:"tw-flex tw-flex-wrap tw-gap-2 tw-items-center"},V={key:0},N=p({__name:"WaDialog",props:{hideCloseIcon:{type:Boolean},hideButtons:{type:Boolean},useCancelAsButtonLabel:{type:Boolean},verticalStretch:{type:Boolean}},emits:["close"],setup(t,{emit:m}){const s=t,d=_();h(()=>{d.name==="contactFrame"&&l.emit("spa:beforeShowModal")}),f(()=>{d.name==="contactFrame"&&l.emit("spa:beforeCloseModal")}),v(()=>{window.document.documentElement.classList.add("modal-open")}),g(()=>{window.document.documentElement.classList.remove("modal-open")});function r(){y.webView&&d.meta.menuItemType==="frame"?l.emit("spa:navigateBack"):l.emit("spa:closeModal"),m("close")}return(o,U)=>(a(),n("div",I,[M,e("div",$,[s.hideCloseIcon?i("",!0):(a(),n("a",{key:0,class:"dialog-close",onClick:B(r,["prevent"])},W,8,D)),o.$slots.header?(a(),n("header",A,[e("h1",null,[c(o.$slots,"header",{},void 0,!0)])])):i("",!0),e("div",{class:w(["dialog-content",{"dialog-content--verticalStretch":s.verticalStretch,"dialog-content--withoutButtons":s.hideButtons}])},[c(o.$slots,"default",{close:r},void 0,!0)],2),s.hideButtons?i("",!0):(a(),n("footer",E,[e("div",F,[o.$slots.submit?(a(),n("div",V,[c(o.$slots,"submit",{},void 0,!0)])):i("",!0),e("button",{class:"button light-gray tw-m-0",onClick:r},b(o.$t(s.useCancelAsButtonLabel?"cancel":"close")),1),e("div",null,[c(o.$slots,"error",{},void 0,!0)])])]))])]))}});const z=S(N,[["__scopeId","data-v-da143c4b"]]);export{z as W};
