import{d as n,o as t,c as o,i as p,aq as _,k as c,f as i,_ as u}from"./main-ec0df6a4.js";const m=["value","type","placeholder"],y={key:0,class:"smaller text-red"},g=n({__name:"FieldString",props:{modelValue:{},type:{},error:{type:Boolean},errorMessage:{},placeholder:{},required:{type:Boolean}},emits:["update:modelValue"],setup(a,{emit:s}){const e=a;return(l,r)=>(t(),o("div",null,[p("input",_({value:e.modelValue,type:e.type,class:{"state-error":e.error},placeholder:e.placeholder},l.$attrs,{onInput:r[0]||(r[0]=d=>s("update:modelValue",d.target.value))}),null,16,m),e.error?(t(),o("div",y,c(e.errorMessage),1)):i("",!0)]))}});const V=u(g,[["__scopeId","data-v-1773d48e"]]);export{V as F};