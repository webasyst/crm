import{d as _,a as h,o as t,c as r,p as g,i as p,r as m,n as f,f as u,e as k,w as y,h as x,v as V,aE as w,_ as C,k as F,R as B,b as v,F as S,l as $,P as T,g as M,bd as d}from"./main-e63d6b61.js";import{a as i,F as D,_ as I}from"./FieldCheckbox-c1b80b55.js";import{F as N}from"./FieldSelect-d021e2ab.js";import{C as E}from"./CustomColumn-72664405.js";const O={class:"collapsable-wrapper"},U=["onClick"],q={class:"collapsable-handler-caret"},z={key:0,class:"fas fa-caret-down"},L={key:1,class:"fas fa-caret-right"},P={key:0,class:"collapsable-content"},R=_({__name:"CollapsibleContainer",props:{colorCaret:{},fallbackOnShow:{type:Boolean},hideToggler:{type:Boolean}},setup(s,{expose:c}){const a=s,o=h(!1),e=()=>(o.value=!o.value,o.value);return c({toggle:e}),(n,l)=>(t(),r("div",O,[a.hideToggler?u("",!0):(t(),r("a",{key:0,class:"collapsable-handler",onClick:g(e,["prevent"])},[p("span",null,[m(n.$slots,"name",{},void 0,!0)]),p("span",q,[(t(),r("span",{key:o.value?1:0,class:f(["icon",a.colorCaret||""])},[o.value?(t(),r("i",z)):(t(),r("i",L))],2))])],8,U)),k(w,{name:"fade",mode:"out-in"},{default:y(()=>[a.fallbackOnShow||o.value?x((t(),r("div",P,[m(n.$slots,"default",{},void 0,!0)],512)),[[V,o.value]]):u("",!0)]),_:3})]))}});const Z=C(R,[["__scopeId","data-v-4439b65e"]]),j=["value","placeholder"],A={key:0,class:"smaller text-red"},G=_({__name:"FieldText",props:{modelValue:{},errorMessage:{},placeholder:{},required:{type:Boolean}},emits:["update:modelValue"],setup(s,{emit:c}){const a=s;return(o,e)=>(t(),r("div",null,[p("textarea",{value:a.modelValue,rows:"3",class:f({"state-error":a.errorMessage}),placeholder:a.placeholder,onInput:e[0]||(e[0]=n=>c("update:modelValue",n.target.value))},null,42,j),a.errorMessage?(t(),r("div",A,F(a.errorMessage),1)):u("",!0)]))}});const H=C(G,[["__scopeId","data-v-bf93c5de"]]),J=_({__name:"FieldComposite",props:{modelValue:{},fields:{}},emits:["update:modelValue"],setup(s,{emit:c}){const a=s,o=h(a.fields.map(e=>{var n;return{...e,field:e.id,value:((n=a.modelValue.find(l=>l.field===e.id))==null?void 0:n.value)??""}}));return B(o,()=>{c("update:modelValue",o.value.filter(e=>e.value).map(e=>({field:e.id,value:e.value})))},{deep:!0}),(e,n)=>(t(),v(E,{space:"2"},{default:y(()=>[(t(!0),r(S,null,$(o.value,l=>(t(),v(T(M(K)[l.type]),{key:l.id,modelValue:l.value,"onUpdate:modelValue":b=>l.value=b,options:l.type==="select"||l.type==="radio"?l.option_values:[],placeholder:l.name},null,8,["modelValue","onUpdate:modelValue","options","placeholder"]))),128))]),_:1}))}}),K={string:s=>d(i,{...s,type:"text"}),date:s=>d(i,{...s,type:"date"}),number:s=>d(i,{...s,type:"number"}),text:H,select:N,checkbox:D,radio:I,composite:J};export{Z as C,K as f};