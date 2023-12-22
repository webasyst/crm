import{d as _,a as h,o as l,c as r,p as g,i as p,r as m,n as f,f as u,e as k,w as y,h as x,v as V,aB as F,_ as C,k as w,P as B,b as v,F as S,l as $,N as T,g as M,b9 as d}from"./main-07d84941.js";import{F as i}from"./FieldString-83da0a5f.js";import{F as N}from"./FieldSelect-efa962b1.js";import{F as D,_ as I}from"./FieldCheckbox-eb6fe774.js";import{C as O}from"./CustomColumn-36c752a1.js";const U={class:"collapsable-wrapper"},q=["onClick"],z={class:"collapsable-handler-caret"},E={key:0,class:"fas fa-caret-down"},L={key:1,class:"fas fa-caret-right"},P={key:0,class:"collapsable-content"},j=_({__name:"CollapsibleContainer",props:{colorCaret:{},fallbackOnShow:{type:Boolean},hideToggler:{type:Boolean}},setup(s,{expose:c}){const a=s,o=h(!1),e=()=>(o.value=!o.value,o.value);return c({toggle:e}),(n,t)=>(l(),r("div",U,[a.hideToggler?u("",!0):(l(),r("a",{key:0,class:"collapsable-handler",onClick:g(e,["prevent"])},[p("span",null,[m(n.$slots,"name",{},void 0,!0)]),p("span",z,[(l(),r("span",{key:o.value?1:0,class:f(["icon",a.colorCaret||""])},[o.value?(l(),r("i",E)):(l(),r("i",L))],2))])],8,q)),k(F,{name:"fade",mode:"out-in"},{default:y(()=>[a.fallbackOnShow||o.value?x((l(),r("div",P,[m(n.$slots,"default",{},void 0,!0)],512)),[[V,o.value]]):u("",!0)]),_:3})]))}});const ee=C(j,[["__scopeId","data-v-4439b65e"]]),A=["value","placeholder"],G={key:0,class:"smaller text-red"},H=_({__name:"FieldText",props:{modelValue:{},errorMessage:{},placeholder:{},required:{type:Boolean}},emits:["update:modelValue"],setup(s,{emit:c}){const a=s;return(o,e)=>(l(),r("div",null,[p("textarea",{value:a.modelValue,rows:"3",class:f({"state-error":a.errorMessage}),placeholder:a.placeholder,onInput:e[0]||(e[0]=n=>c("update:modelValue",n.target.value))},null,42,A),a.errorMessage?(l(),r("div",G,w(a.errorMessage),1)):u("",!0)]))}});const J=C(H,[["__scopeId","data-v-bf93c5de"]]),K=_({__name:"FieldComposite",props:{modelValue:{},fields:{}},emits:["update:modelValue"],setup(s,{emit:c}){const a=s,o=h(a.fields.map(e=>{var n;return{...e,field:e.id,value:((n=a.modelValue.find(t=>t.field===e.id))==null?void 0:n.value)??""}}));return B(o,()=>{c("update:modelValue",o.value.filter(e=>e.value).map(e=>({field:e.id,value:e.value})))},{deep:!0}),(e,n)=>(l(),v(O,{space:"2"},{default:y(()=>[(l(!0),r(S,null,$(o.value,t=>(l(),v(T(M(Q)[t.type]),{key:t.id,modelValue:t.value,"onUpdate:modelValue":b=>t.value=b,options:t.type==="select"||t.type==="radio"?t.option_values:[],placeholder:t.name},null,8,["modelValue","onUpdate:modelValue","options","placeholder"]))),128))]),_:1}))}}),Q={string:s=>d(i,{...s,type:"text"}),date:s=>d(i,{...s,type:"date"}),number:s=>d(i,{...s,type:"number"}),text:J,select:N,checkbox:D,radio:I,composite:K};export{ee as C,Q as f};
