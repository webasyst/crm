import{d as _,a as n,aV as h,a7 as v,o as a,c as s,g as i,k as w,f as l,h as y,v as b,F as g,N as k,q as I,s as x,i as C,_ as A}from"./main-ec0df6a4.js";import{u as S}from"./iframeObserver-be2a61ac.js";import"./index-4793d891.js";const q=e=>(I("data-v-37f69fc0"),e=e(),x(),e),E={key:0},j={key:1,class:"tw-absolute tw-top-1/2 tw-left-1/2 -tw-translate-x-1/2 -tw-translate-y-1/2"},B=q(()=>C("div",{class:"spinner custom-p-16"},null,-1)),F=[B],T=["srcdoc"],V=_({__name:"ContactTabIframe",props:{tab:{}},setup(e){const u=e,d=n(),o=n(""),r=n(!1),{data:p,error:m,isFetching:f}=h(u.tab.url).get().text();return S(d),v(()=>{k(p,c=>{if(c){const t=`
<!DOCTYPE html>
  <html data-theme="${document.documentElement.getAttribute("data-theme")}">
    <head>
      <meta charset="utf-8">
      <link rel="icon" href="data:;base64,iVBORw0KGgo=">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="viewport-fit=cover, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
      <link href="/wa-content/css/wa/wa-2.0.css" rel="stylesheet" type="text/css">
      <script src="/wa-content/js/jquery/jquery-3.6.0.min.js"><\/script>
    </head>
  <body style="padding: 1rem;">
    %content%
    <script>
    (() => {
      Array.from(document.querySelectorAll('a')).forEach(l => {
        l.setAttribute('target', '_parent')
      });
    })();
    <\/script>
  </body>
<html>
`;o.value=t.replace("%content%",c)}})}),(c,t)=>(a(),s(g,null,[i(m)?(a(),s("div",E,w(i(m)),1)):l("",!0),i(f)||!r.value?(a(),s("div",j,F)):l("",!0),o.value?y((a(),s("iframe",{key:2,ref_key:"iframeRef",ref:d,frameborder:"0",srcdoc:o.value,onLoad:t[0]||(t[0]=D=>r.value=!0)},null,40,T)),[[b,r.value]]):l("",!0)],64))}});const $=A(V,[["__scopeId","data-v-37f69fc0"]]);export{$ as default};
