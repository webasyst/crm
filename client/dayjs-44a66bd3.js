import{aQ as U,a4 as Z,aC as R,aN as st}from"./main-ec0df6a4.js";var X={exports:{}};(function(g,V){(function(v,h){g.exports=h()})(U,function(){var v={LTS:"h:mm:ss A",LT:"h:mm A",L:"MM/DD/YYYY",LL:"MMMM D, YYYY",LLL:"MMMM D, YYYY h:mm A",LLLL:"dddd, MMMM D, YYYY h:mm A"};return function(h,x,y){var m=x.prototype,w=m.format;y.en.formats=v,m.format=function(Y){Y===void 0&&(Y="YYYY-MM-DDTHH:mm:ssZ");var D=this.$locale().formats,H=function(M,C){return M.replace(/(\[[^\]]+])|(LTS?|l{1,4}|L{1,4})/g,function(S,_,O){var P=O&&O.toUpperCase();return _||C[O]||v[O]||C[P].replace(/(\[[^\]]+])|(MMMM|MM|DD|dddd)/g,function(B,Q,W){return Q||W.slice(1)})})}(Y,D===void 0?{}:D);return w.call(this,H)}}})})(X);var it=X.exports;const at=Z(it);var tt={exports:{}};(function(g,V){(function(v,h){g.exports=h()})(U,function(){return function(v,h,x){h.prototype.isYesterday=function(){var y="YYYY-MM-DD",m=x().subtract(1,"day");return this.format(y)===m.format(y)}}})})(tt);var ut=tt.exports;const ot=Z(ut);var et={exports:{}};(function(g,V){(function(v,h){g.exports=h()})(U,function(){return function(v,h,x){h.prototype.isToday=function(){var y="YYYY-MM-DD",m=x();return this.format(y)===m.format(y)}}})})(et);var ct=et.exports;const ft=Z(ct);var rt={exports:{}};(function(g,V){(function(v,h){g.exports=h()})(U,function(){var v=1e3,h=6e4,x=36e5,y="millisecond",m="second",w="minute",Y="hour",D="day",H="week",M="month",C="quarter",S="year",_="date",O="Invalid Date",P=/^(\d{4})[-/]?(\d{1,2})?[-/]?(\d{0,2})[Tt\s]*(\d{1,2})?:?(\d{1,2})?:?(\d{1,2})?[.:]?(\d+)?$/,B=/\[([^\]]+)]|Y{1,4}|M{1,4}|D{1,2}|d{1,4}|H{1,2}|h{1,2}|a|A|m{1,2}|s{1,2}|Z{1,2}|SSS/g,Q={name:"en",weekdays:"Sunday_Monday_Tuesday_Wednesday_Thursday_Friday_Saturday".split("_"),months:"January_February_March_April_May_June_July_August_September_October_November_December".split("_"),ordinal:function(s){var r=["th","st","nd","rd"],t=s%100;return"["+s+(r[(t-20)%10]||r[t]||r[0])+"]"}},W=function(s,r,t){var n=String(s);return!n||n.length>=r?s:""+Array(r+1-n.length).join(t)+s},nt={s:W,z:function(s){var r=-s.utcOffset(),t=Math.abs(r),n=Math.floor(t/60),e=t%60;return(r<=0?"+":"-")+W(n,2,"0")+":"+W(e,2,"0")},m:function s(r,t){if(r.date()<t.date())return-s(t,r);var n=12*(t.year()-r.year())+(t.month()-r.month()),e=r.clone().add(n,M),i=t-e<0,a=r.clone().add(n+(i?-1:1),M);return+(-(n+(t-e)/(i?e-a:a-e))||0)},a:function(s){return s<0?Math.ceil(s)||0:Math.floor(s)},p:function(s){return{M,y:S,w:H,d:D,D:_,h:Y,m:w,s:m,ms:y,Q:C}[s]||String(s||"").toLowerCase().replace(/s$/,"")},u:function(s){return s===void 0}},j="en",b={};b[j]=Q;var q=function(s){return s instanceof z},I=function s(r,t,n){var e;if(!r)return j;if(typeof r=="string"){var i=r.toLowerCase();b[i]&&(e=i),t&&(b[i]=t,e=i);var a=r.split("-");if(!e&&a.length>1)return s(a[0])}else{var o=r.name;b[o]=r,e=o}return!n&&e&&(j=e),e||!n&&j},f=function(s,r){if(q(s))return s.clone();var t=typeof r=="object"?r:{};return t.date=s,t.args=arguments,new z(t)},u=nt;u.l=I,u.i=q,u.w=function(s,r){return f(s,{locale:r.$L,utc:r.$u,x:r.$x,$offset:r.$offset})};var z=function(){function s(t){this.$L=I(t.locale,null,!0),this.parse(t)}var r=s.prototype;return r.parse=function(t){this.$d=function(n){var e=n.date,i=n.utc;if(e===null)return new Date(NaN);if(u.u(e))return new Date;if(e instanceof Date)return new Date(e);if(typeof e=="string"&&!/Z$/i.test(e)){var a=e.match(P);if(a){var o=a[2]-1||0,c=(a[7]||"0").substring(0,3);return i?new Date(Date.UTC(a[1],o,a[3]||1,a[4]||0,a[5]||0,a[6]||0,c)):new Date(a[1],o,a[3]||1,a[4]||0,a[5]||0,a[6]||0,c)}}return new Date(e)}(t),this.$x=t.x||{},this.init()},r.init=function(){var t=this.$d;this.$y=t.getFullYear(),this.$M=t.getMonth(),this.$D=t.getDate(),this.$W=t.getDay(),this.$H=t.getHours(),this.$m=t.getMinutes(),this.$s=t.getSeconds(),this.$ms=t.getMilliseconds()},r.$utils=function(){return u},r.isValid=function(){return this.$d.toString()!==O},r.isSame=function(t,n){var e=f(t);return this.startOf(n)<=e&&e<=this.endOf(n)},r.isAfter=function(t,n){return f(t)<this.startOf(n)},r.isBefore=function(t,n){return this.endOf(n)<f(t)},r.$g=function(t,n,e){return u.u(t)?this[n]:this.set(e,t)},r.unix=function(){return Math.floor(this.valueOf()/1e3)},r.valueOf=function(){return this.$d.getTime()},r.startOf=function(t,n){var e=this,i=!!u.u(n)||n,a=u.p(t),o=function(k,$){var L=u.w(e.$u?Date.UTC(e.$y,$,k):new Date(e.$y,$,k),e);return i?L:L.endOf(D)},c=function(k,$){return u.w(e.toDate()[k].apply(e.toDate("s"),(i?[0,0,0,0]:[23,59,59,999]).slice($)),e)},d=this.$W,l=this.$M,p=this.$D,A="set"+(this.$u?"UTC":"");switch(a){case S:return i?o(1,0):o(31,11);case M:return i?o(1,l):o(0,l+1);case H:var T=this.$locale().weekStart||0,E=(d<T?d+7:d)-T;return o(i?p-E:p+(6-E),l);case D:case _:return c(A+"Hours",0);case Y:return c(A+"Minutes",1);case w:return c(A+"Seconds",2);case m:return c(A+"Milliseconds",3);default:return this.clone()}},r.endOf=function(t){return this.startOf(t,!1)},r.$set=function(t,n){var e,i=u.p(t),a="set"+(this.$u?"UTC":""),o=(e={},e[D]=a+"Date",e[_]=a+"Date",e[M]=a+"Month",e[S]=a+"FullYear",e[Y]=a+"Hours",e[w]=a+"Minutes",e[m]=a+"Seconds",e[y]=a+"Milliseconds",e)[i],c=i===D?this.$D+(n-this.$W):n;if(i===M||i===S){var d=this.clone().set(_,1);d.$d[o](c),d.init(),this.$d=d.set(_,Math.min(this.$D,d.daysInMonth())).$d}else o&&this.$d[o](c);return this.init(),this},r.set=function(t,n){return this.clone().$set(t,n)},r.get=function(t){return this[u.p(t)]()},r.add=function(t,n){var e,i=this;t=Number(t);var a=u.p(n),o=function(l){var p=f(i);return u.w(p.date(p.date()+Math.round(l*t)),i)};if(a===M)return this.set(M,this.$M+t);if(a===S)return this.set(S,this.$y+t);if(a===D)return o(1);if(a===H)return o(7);var c=(e={},e[w]=h,e[Y]=x,e[m]=v,e)[a]||1,d=this.$d.getTime()+t*c;return u.w(d,this)},r.subtract=function(t,n){return this.add(-1*t,n)},r.format=function(t){var n=this,e=this.$locale();if(!this.isValid())return e.invalidDate||O;var i=t||"YYYY-MM-DDTHH:mm:ssZ",a=u.z(this),o=this.$H,c=this.$m,d=this.$M,l=e.weekdays,p=e.months,A=e.meridiem,T=function($,L,F,N){return $&&($[L]||$(n,i))||F[L].slice(0,N)},E=function($){return u.s(o%12||12,$,"0")},k=A||function($,L,F){var N=$<12?"AM":"PM";return F?N.toLowerCase():N};return i.replace(B,function($,L){return L||function(F){switch(F){case"YY":return String(n.$y).slice(-2);case"YYYY":return u.s(n.$y,4,"0");case"M":return d+1;case"MM":return u.s(d+1,2,"0");case"MMM":return T(e.monthsShort,d,p,3);case"MMMM":return T(p,d);case"D":return n.$D;case"DD":return u.s(n.$D,2,"0");case"d":return String(n.$W);case"dd":return T(e.weekdaysMin,n.$W,l,2);case"ddd":return T(e.weekdaysShort,n.$W,l,3);case"dddd":return l[n.$W];case"H":return String(o);case"HH":return u.s(o,2,"0");case"h":return E(1);case"hh":return E(2);case"a":return k(o,c,!0);case"A":return k(o,c,!1);case"m":return String(c);case"mm":return u.s(c,2,"0");case"s":return String(n.$s);case"ss":return u.s(n.$s,2,"0");case"SSS":return u.s(n.$ms,3,"0");case"Z":return a}return null}($)||a.replace(":","")})},r.utcOffset=function(){return 15*-Math.round(this.$d.getTimezoneOffset()/15)},r.diff=function(t,n,e){var i,a=this,o=u.p(n),c=f(t),d=(c.utcOffset()-this.utcOffset())*h,l=this-c,p=function(){return u.m(a,c)};switch(o){case S:i=p()/12;break;case M:i=p();break;case C:i=p()/3;break;case H:i=(l-d)/6048e5;break;case D:i=(l-d)/864e5;break;case Y:i=l/x;break;case w:i=l/h;break;case m:i=l/v;break;default:i=l}return e?i:u.a(i)},r.daysInMonth=function(){return this.endOf(M).$D},r.$locale=function(){return b[this.$L]},r.locale=function(t,n){if(!t)return this.$L;var e=this.clone(),i=I(t,n,!0);return i&&(e.$L=i),e},r.clone=function(){return u.w(this.$d,this)},r.toDate=function(){return new Date(this.valueOf())},r.toJSON=function(){return this.isValid()?this.toISOString():null},r.toISOString=function(){return this.$d.toISOString()},r.toString=function(){return this.$d.toUTCString()},s}(),G=z.prototype;return f.prototype=G,[["$ms",y],["$s",m],["$m",w],["$H",Y],["$W",D],["$M",M],["$y",S],["$D",_]].forEach(function(s){G[s[1]]=function(r){return this.$g(r,s[0],s[1])}}),f.extend=function(s,r){return s.$i||(s(r,z,f),s.$i=!0),f},f.locale=I,f.isDayjs=q,f.unix=function(s){return f(1e3*s)},f.en=b[j],f.Ls=b,f.p={},f})})(rt);var dt=rt.exports;const J=Z(dt);J.extend(at);J.extend(ft);J.extend(ot);const K={ru:()=>st(()=>import("./ru-1445afd2.js").then(g=>g.r),["./ru-1445afd2.js","./main-ec0df6a4.js"],import.meta.url)};R!=="en"&&R in K&&K.ru().then(()=>{J.locale("ru")});export{dt as a,J as d};