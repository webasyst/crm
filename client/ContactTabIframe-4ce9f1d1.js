import{d as h,a as r,be as v,ai as w,P as b,B as p,o as a,c as s,g as i,k as y,f as l,h as g,v as k,F as I,q as x,s as C,i as S,_ as $}from"./main-49799bbb.js";import{u as B}from"./iframeObserver-aae39d6d.js";import"./index-55296fc3.js";const j=e=>(x("data-v-d86997be"),e=e(),C(),e),q={key:0},D={key:1,class:"tw-absolute tw-top-1/2 tw-left-1/2 -tw-translate-x-1/2 -tw-translate-y-1/2"},E=j(()=>S("div",{class:"spinner custom-p-16"},null,-1)),F=[E],T=["srcdoc"],A=h({__name:"ContactTabIframe",props:{tab:{}},setup(e){const u=e,d=r(),o=r(""),c=r(!1),{data:f,error:m,isFetching:_}=v(u.tab.url).get().text();return B(d),w(()=>{b(f,n=>{if(n){const t=`
<!DOCTYPE html>
  <html data-theme="${document.documentElement.getAttribute("data-theme")}">
    <head>
      <meta charset="utf-8">
      <link rel="icon" href="data:;base64,iVBORw0KGgo=">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="viewport-fit=cover, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
      <link href="${p.waUrl}wa-content/css/wa/wa-2.0.css" rel="stylesheet" type="text/css">
      <script src="${p.waUrl}wa-content/js/jquery/jquery-3.6.0.min.js"><\/script>
    </head>
  <body style="padding: 1rem;">
    %content%
    <script>
      $(document).on('click', 'a', function (e) {
        e.preventDefault();
        window.top.location = $(this).attr('href');
      });
    <\/script>
  </body>
<html>
`;o.value=t.replace("%content%",n)}})}),(n,t)=>(a(),s(I,null,[i(m)?(a(),s("div",q,y(i(m)),1)):l("",!0),i(_)||!c.value?(a(),s("div",D,F)):l("",!0),o.value?g((a(),s("iframe",{key:2,ref_key:"iframeRef",ref:d,frameborder:"0",srcdoc:o.value,onLoad:t[0]||(t[0]=O=>c.value=!0)},null,40,T)),[[k,c.value]]):l("",!0)],64))}});const N=$(A,[["__scopeId","data-v-d86997be"]]);export{N as default};
