import{Q as y,a as s,z as b,$ as p,M as w,P as a}from"./main-ad3d4b2a.js";const x=y("funnels",()=>{const t=s([]),i=s(),n=s({}),r=b(()=>`crm.funnel.list?${p.stringify(n.value)}`),{data:c,isFetching:o,isFinished:f,error:l,execute:h,canAbort:m,abort:d}=w(r,{immediate:!1,refetch:!1}).get().json();a(r,u),a(c,e=>{Array.isArray(e)&&(t.value=[...e])});function u(){m.value&&d(),setTimeout(()=>{h()},0)}function v(){n.value={...n.value,with_count:1}}function F(e){if(e)return t.value.find(g=>g.id===e)}return{funnels:t,selectedFunnel:i,isFetching:o,isFinished:f,error:l,refetch:u,getFunnelById:F,withCount:v}});export{x as u};
