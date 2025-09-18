import{d as h,h as a,an as v,bF as y,B as o,o as n,c as r,p as b,j as d,k as _,v as j,n as g,a9 as k,F as B,a as $}from"./main-d7f03521.js";import{u as O}from"./iframeObserver-7b20ae08.js";const q={key:0},x={key:1,class:"tw-absolute tw-top-1/2 tw-left-1/2 -tw-translate-x-1/2 -tw-translate-y-1/2"},C=$("div",{class:"spinner custom-p-16"},null,-1),E=[C],F=["srcdoc"],U=h({__name:"TabContentIframe",props:{tab:{},forceResize:{type:Boolean},style:{},contentOutsideTabBody:{type:Boolean}},setup(w){const e=w,m=a(),c=a(""),l=a(""),i=a("url"in e.tab),u=a("html"in e.tab);O(m,!1,e.forceResize),e.contentOutsideTabBody?p():v(p);async function p(){if("url"in e.tab){i.value=!0;const t=await y(e.tab.url).get().text();l.value=t.error.value,i.value=!1,t.data.value&&f(t.data.value)}else f(e.tab.html)}function f(t){if(typeof t!="string")throw new Error("content not parse string");const s=`
    <!DOCTYPE html>
      <html data-theme="${document.documentElement.getAttribute("data-theme")}">
        <head>
          <meta charset="utf-8">
          <link rel="icon" href="data:;base64,iVBORw0KGgo=">
          <meta http-equiv="X-UA-Compatible" content="IE=edge">
          <meta name="viewport" content="viewport-fit=cover, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
          <link href="${o.waUrl}wa-content/css/wa/wa-2.0.css" rel="stylesheet" type="text/css">
          <script src="${o.waUrl}wa-content/js/jquery/jquery-3.6.0.min.js"><\/script>
          <script src="${o.waUrl}wa-content/js/jquery/jquery-migrate-1.2.1.min.js"><\/script>
          <script src="${o.waUrl}wa-content/js/jquery-wa/wa.js"><\/script>
        </head>
      <body style="padding: ${e.contentOutsideTabBody?"0":"1rem"};">
        %content%
        <script>
          $(document).on('click', 'a', function (e) {
            e.preventDefault();
            window.top.location = $(this).attr('href');
          });
        <\/script>
      </body>
    <html>
    `;c.value=s.replace("%content%",t)}return(t,s)=>(n(),r(B,null,[l.value?(n(),r("div",q,b(l.value),1)):d("",!0),i.value||!u.value?(n(),r("div",x,E)):d("",!0),c.value?_((n(),r("iframe",{key:2,ref_key:"iframeRef",ref:m,frameborder:"0",srcdoc:c.value,class:g(["tw-block tw-w-full tw-h-full",{"md:tw-absolute":!e.contentOutsideTabBody}]),style:k(e.style),onLoad:s[0]||(s[0]=R=>u.value=!0)},null,46,F)),[[j,u.value]]):d("",!0)],64))}});export{U as _};
