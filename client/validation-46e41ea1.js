import{H as l,a3 as s,a as c}from"./main-07d84941.js";function v(t,o){const{t:u}=l(),n={required:s.string().min(1,{message:u("validation.required")})},r=c(Object.fromEntries(Object.entries(o).map(([e,a])=>[e,{rule:a,error:!1,errorMessage:""}])));function i(){for(const e in r.value)try{return n[r.value[e].rule].parse(t.value[e]),r.value[e].error=!1,r.value[e].errorMessage="",!0}catch(a){a instanceof s.ZodError&&(r.value[e].error=!0,r.value[e].errorMessage=a.issues[0].message)}}return{validation:r,validate:i}}export{v as u};
