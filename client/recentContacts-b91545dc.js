import{O as b,J as a,a as i,N as o}from"./main-ec0df6a4.js";const C=b("recentContacts",()=>{const{data:u,isFetching:f,error:l,isFinished:p,canAbort:h,abort:d,execute:v}=a("crm.contact.recent",{immediate:!1}).get().json(),r=i(new Set),t=i([]),c=i([]);o(u,e=>{e&&"pinned"in e&&(t.value=e.pinned,c.value=e.recent)});function m(){h.value&&d(),setTimeout(()=>{v()})}function g(e,s){const{isFetching:F,response:x}=a(`crm.contact.${s?"pin":"unpin"}`).post({id:e});o(F,n=>{r.value[n?"add":"delete"](e)}),o(()=>{var n;return(n=x.value)==null?void 0:n.ok},n=>{if(n){const S=(s?c:t).value.splice((s?c:t).value.findIndex(T=>T.id===e),1)[0];(s?t:c).value[s?"push":"unshift"](S)}})}return{pinned:t,recent:c,isFetching:f,isFinished:p,error:l,fetchingIds:r,refetch:m,pin:g}});export{C as u};
