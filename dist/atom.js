var Mt=Object.defineProperty;
var Nt=(t,e,n)=>e in t?Mt(t,e, {
enumerable:!0,configurable:!0,writable:!0,value:n
}):t[e]=n;
var st=(t,e,n)=>Nt(t,typeof e!="symbol"?e+"":e,n);
class gt {
constructor(e,n= {
}) {
st(this,"url");
st(this,"headers");
this.url=e,this.headers= {
...n,"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")
}
}get(e) {
let n=empty(e)?this.url:this.url+"?"+new URLSearchParams(e).toString();
return fetch(n, {
method:"GET",headers: {
"Content-Type":"application/json",...this.headers
}
}).then(i=>i.json())
}post(e) {
return fetch(this.url, {
method:"POST",body:e instanceof FormData?e:JSON.stringify(e),headers:e instanceof FormData?this.headers: {
"Content-Type":"application/json",...this.headers
}
}).then(n=>n.json())
}
}const Wt=t=>new Promise((e,n)=> {
dispatchEvent(new CustomEvent("atom-alert-show", {
detail: {
...t,onDismissed:()=>e()
}
}))
}),It=t=> {
dispatchEvent(new CustomEvent("atom-toast-show", {
detail:t
}))
},jt=(t=null)=>( {
show() {
return dispatchEvent(new CustomEvent("atom-modal-show", {
detail: {
name:t
}
}))
},slide(e=null) {
return dispatchEvent(new CustomEvent("atom-modal-show", {
detail: {
name:t,position:e,variant:"slide"
}
}))
},close() {
return dispatchEvent(new CustomEvent("atom-modal-close", {
detail: {
name:t
}
}))
}
}),Vt=t=>t==null?!0:(t=JSON.parse(JSON.stringify(t)),Array.isArray(t)&&!t.length||typeof t=="object"&&!Object.keys(t).length&&Object.getPrototypeOf(t)===Object.prototype||typeof t=="string"&&t.trim()===""),qt=t=>new Promise((e,n)=> {
dispatchEvent(new CustomEvent("atom-confirm-show", {
detail: {
...t,onAccepted:(i=null,o=null)=>e( {
password:i,passphrase:o
}),onRejected:()=>n()
}
}))
}),J=Math.min,F=Math.max,U=Math.round,Y=Math.floor,R=t=>( {
x:t,y:t
}),Ht= {
left:"right",right:"left",bottom:"top",top:"bottom"
},_t= {
start:"end",end:"start"
};
function wt(t,e,n) {
return F(t,J(e,n))
}function Q(t,e) {
return typeof t=="function"?t(e):t
}function B(t) {
return t.split("-")[0]
}function Z(t) {
return t.split("-")[1]
}function At(t) {
return t==="x"?"y":"x"
}function Et(t) {
return t==="y"?"height":"width"
}function k(t) {
return["top","bottom"].includes(B(t))?"y":"x"
}function Ot(t) {
return At(k(t))
}function zt(t,e,n) {
n===void 0&&(n=!1);
const i=Z(t),o=Ot(t),s=Et(o);
let r=o==="x"?i===(n?"end":"start")?"right":"left":i==="start"?"bottom":"top";
return e.reference[s]>e.floating[s]&&(r=G(r)),[r,G(r)]
}function Xt(t) {
const e=G(t);
return[lt(t),e,lt(e)]
}function lt(t) {
return t.replace(/start|end/g,e=>_t[e])
}function Yt(t,e,n) {
const i=["left","right"],o=["right","left"],s=["top","bottom"],r=["bottom","top"];
switch(t) {
case"top":case"bottom":return n?e?o:i:e?i:o;
case"left":case"right":return e?s:r;
default:return[]
}
}function Jt(t,e,n,i) {
const o=Z(t);
let s=Yt(B(t),n==="start",i);
return o&&(s=s.map(r=>r+"-"+o),e&&(s=s.concat(s.map(lt)))),s
}function G(t) {
return t.replace(/left|right|bottom|top/g,e=>Ht[e])
}function Ut(t) {
return {
top:0,right:0,bottom:0,left:0,...t
}
}function Gt(t) {
return typeof t!="number"?Ut(t): {
top:t,right:t,bottom:t,left:t
}
}function K(t) {
const {
x:e,y:n,width:i,height:o
}=t;
return {
width:i,height:o,top:n,left:e,right:e+i,bottom:n+o,x:e,y:n
}
}function vt(t,e,n) {
let {
reference:i,floating:o
}=t;
const s=k(e),r=Ot(e),l=Et(r),c=B(e),a=s==="y",h=i.x+i.width/2-o.width/2,u=i.y+i.height/2-o.height/2,d=i[l]/2-o[l]/2;
let f;
switch(c) {
case"top":f= {
x:h,y:i.y-o.height
};
break;
case"bottom":f= {
x:h,y:i.y+i.height
};
break;
case"right":f= {
x:i.x+i.width,y:u
};
break;
case"left":f= {
x:i.x-o.width,y:u
};
break;
default:f= {
x:i.x,y:i.y
}
}switch(Z(e)) {
case"start":f[r]-=d*(n&&a?-1:1);
break;
case"end":f[r]+=d*(n&&a?-1:1);
break
}return f
}const Kt=async(t,e,n)=> {
const {
placement:i="bottom",strategy:o="absolute",middleware:s=[],platform:r
}=n,l=s.filter(Boolean),c=await(r.isRTL==null?void 0:r.isRTL(e));
let a=await r.getElementRects( {
reference:t,floating:e,strategy:o
}), {
x:h,y:u
}=vt(a,i,c),d=i,f= {
},p=0;
for(let g=0;
g<l.length;
g++) {
const {
name:w,fn:m
}=l[g], {
x:v,y,data:b,reset:x
}=await m( {
x:h,y:u,initialPlacement:i,placement:d,strategy:o,middlewareData:f,rects:a,platform:r,elements: {
reference:t,floating:e
}
});
h=v??h,u=y??u,f= {
...f,[w]: {
...f[w],...b
}
},x&&p<=50&&(p++,typeof x=="object"&&(x.placement&&(d=x.placement),x.rects&&(a=x.rects===!0?await r.getElementRects( {
reference:t,floating:e,strategy:o
}):x.rects), {
x:h,y:u
}=vt(a,d,c)),g=-1)
}return {
x:h,y:u,placement:d,strategy:o,middlewareData:f
}
};
async function Ct(t,e) {
var n;
e===void 0&&(e= {
});
const {
x:i,y:o,platform:s,rects:r,elements:l,strategy:c
}=t, {
boundary:a="clippingAncestors",rootBoundary:h="viewport",elementContext:u="floating",altBoundary:d=!1,padding:f=0
}=Q(e,t),p=Gt(f),w=l[d?u==="floating"?"reference":"floating":u],m=K(await s.getClippingRect( {
element:(n=await(s.isElement==null?void 0:s.isElement(w)))==null||n?w:w.contextElement||await(s.getDocumentElement==null?void 0:s.getDocumentElement(l.floating)),boundary:a,rootBoundary:h,strategy:c
})),v=u==="floating"? {
x:i,y:o,width:r.floating.width,height:r.floating.height
}:r.reference,y=await(s.getOffsetParent==null?void 0:s.getOffsetParent(l.floating)),b=await(s.isElement==null?void 0:s.isElement(y))?await(s.getScale==null?void 0:s.getScale(y))|| {
x:1,y:1
}: {
x:1,y:1
},x=K(s.convertOffsetParentRelativeRectToViewportRelativeRect?await s.convertOffsetParentRelativeRectToViewportRelativeRect( {
elements:l,rect:v,offsetParent:y,strategy:c
}):v);
return {
top:(m.top-x.top+p.top)/b.y,bottom:(x.bottom-m.bottom+p.bottom)/b.y,left:(m.left-x.left+p.left)/b.x,right:(x.right-m.right+p.right)/b.x
}
}const Qt=function(t) {
return t===void 0&&(t= {
}), {
name:"flip",options:t,async fn(e) {
var n,i;
const {
placement:o,middlewareData:s,rects:r,initialPlacement:l,platform:c,elements:a
}=e, {
mainAxis:h=!0,crossAxis:u=!0,fallbackPlacements:d,fallbackStrategy:f="bestFit",fallbackAxisSideDirection:p="none",flipAlignment:g=!0,...w
}=Q(t,e);
if((n=s.arrow)!=null&&n.alignmentOffset)return {
};
const m=B(o),v=k(l),y=B(l)===l,b=await(c.isRTL==null?void 0:c.isRTL(a.floating)),x=d||(y||!g?[G(l)]:Xt(l)),q=p!=="none";
!d&&q&&x.push(...Jt(l,g,p,b));
const N=[l,...x],it=await Ct(e,w),X=[];
let W=((i=s.flip)==null?void 0:i.overflows)||[];
if(h&&X.push(it[m]),u) {
const P=zt(o,r,b);
X.push(it[P[0]],it[P[1]])
}if(W=[...W, {
placement:o,overflows:X
}],!X.every(P=>P<=0)) {
var dt,pt;
const P=(((dt=s.flip)==null?void 0:dt.index)||0)+1,ot=N[P];
if(ot&&(!(u==="alignment"?v!==k(ot):!1)||W.every(E=>E.overflows[0]>0&&k(E.placement)===v)))return {
data: {
index:P,overflows:W
},reset: {
placement:ot
}
};
let H=(pt=W.filter(D=>D.overflows[0]<=0).sort((D,E)=>D.overflows[1]-E.overflows[1])[0])==null?void 0:pt.placement;
if(!H)switch(f) {
case"bestFit": {
var mt;
const D=(mt=W.filter(E=> {
if(q) {
const L=k(E.placement);
return L===v||L==="y"
}return!0
}).map(E=>[E.placement,E.overflows.filter(L=>L>0).reduce((L,Bt)=>L+Bt,0)]).sort((E,L)=>E[1]-L[1])[0])==null?void 0:mt[0];
D&&(H=D);
break
}case"initialPlacement":H=l;
break
}if(o!==H)return {
reset: {
placement:H
}
}
}return {
}
}
}
};
async function Zt(t,e) {
const {
placement:n,platform:i,elements:o
}=t,s=await(i.isRTL==null?void 0:i.isRTL(o.floating)),r=B(n),l=Z(n),c=k(n)==="y",a=["left","top"].includes(r)?-1:1,h=s&&c?-1:1,u=Q(e,t);
let {
mainAxis:d,crossAxis:f,alignmentAxis:p
}=typeof u=="number"? {
mainAxis:u,crossAxis:0,alignmentAxis:null
}: {
mainAxis:u.mainAxis||0,crossAxis:u.crossAxis||0,alignmentAxis:u.alignmentAxis
};
return l&&typeof p=="number"&&(f=l==="end"?p*-1:p),c? {
x:f*h,y:d*a
}: {
x:d*a,y:f*h
}
}const te=function(t) {
return t===void 0&&(t=0), {
name:"offset",options:t,async fn(e) {
var n,i;
const {
x:o,y:s,placement:r,middlewareData:l
}=e,c=await Zt(e,t);
return r===((n=l.offset)==null?void 0:n.placement)&&(i=l.arrow)!=null&&i.alignmentOffset? {
}: {
x:o+c.x,y:s+c.y,data: {
...c,placement:r
}
}
}
}
},ee=function(t) {
return t===void 0&&(t= {
}), {
name:"shift",options:t,async fn(e) {
const {
x:n,y:i,placement:o
}=e, {
mainAxis:s=!0,crossAxis:r=!1,limiter:l= {
fn:w=> {
let {
x:m,y:v
}=w;
return {
x:m,y:v
}
}
},...c
}=Q(t,e),a= {
x:n,y:i
},h=await Ct(e,c),u=k(B(o)),d=At(u);
let f=a[d],p=a[u];
if(s) {
const w=d==="y"?"top":"left",m=d==="y"?"bottom":"right",v=f+h[w],y=f-h[m];
f=wt(v,f,y)
}if(r) {
const w=u==="y"?"top":"left",m=u==="y"?"bottom":"right",v=p+h[w],y=p-h[m];
p=wt(v,p,y)
}const g=l.fn( {
...e,[d]:f,[u]:p
});
return {
...g,data: {
x:g.x-n,y:g.y-i,enabled: {
[d]:s,[u]:r
}
}
}
}
}
};
function tt() {
return typeof window<"u"
}function V(t) {
return Rt(t)?(t.nodeName||"").toLowerCase():"#document"
}function A(t) {
var e;
return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window
}function T(t) {
var e;
return(e=(Rt(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement
}function Rt(t) {
return tt()?t instanceof Node||t instanceof A(t).Node:!1
}function O(t) {
return tt()?t instanceof Element||t instanceof A(t).Element:!1
}function S(t) {
return tt()?t instanceof HTMLElement||t instanceof A(t).HTMLElement:!1
}function yt(t) {
return!tt()||typeof ShadowRoot>"u"?!1:t instanceof ShadowRoot||t instanceof A(t).ShadowRoot
}function z(t) {
const {
overflow:e,overflowX:n,overflowY:i,display:o
}=C(t);
return/auto|scroll|overlay|hidden|clip/.test(e+i+n)&&!["inline","contents"].includes(o)
}function ne(t) {
return["table","td","th"].includes(V(t))
}function et(t) {
return[":popover-open",":modal"].some(e=> {
try {
return t.matches(e)
}catch {
return!1
}
})
}function at(t) {
const e=ut(),n=O(t)?C(t):t;
return["transform","translate","scale","rotate","perspective"].some(i=>n[i]?n[i]!=="none":!1)||(n.containerType?n.containerType!=="normal":!1)||!e&&(n.backdropFilter?n.backdropFilter!=="none":!1)||!e&&(n.filter?n.filter!=="none":!1)||["transform","translate","scale","rotate","perspective","filter"].some(i=>(n.willChange||"").includes(i))||["paint","layout","strict","content"].some(i=>(n.contain||"").includes(i))
}function ie(t) {
let e=$(t);
for(;
S(e)&&!j(e);
) {
if(at(e))return e;
if(et(e))return null;
e=$(e)
}return null
}function ut() {
return typeof CSS>"u"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")
}function j(t) {
return["html","body","#document"].includes(V(t))
}function C(t) {
return A(t).getComputedStyle(t)
}function nt(t) {
return O(t)? {
scrollLeft:t.scrollLeft,scrollTop:t.scrollTop
}: {
scrollLeft:t.scrollX,scrollTop:t.scrollY
}
}function $(t) {
if(V(t)==="html")return t;
const e=t.assignedSlot||t.parentNode||yt(t)&&t.host||T(t);
return yt(e)?e.host:e
}function St(t) {
const e=$(t);
return j(e)?t.ownerDocument?t.ownerDocument.body:t.body:S(e)&&z(e)?e:St(e)
}function _(t,e,n) {
var i;
e===void 0&&(e=[]),n===void 0&&(n=!0);
const o=St(t),s=o===((i=t.ownerDocument)==null?void 0:i.body),r=A(o);
if(s) {
const l=ct(r);
return e.concat(r,r.visualViewport||[],z(o)?o:[],l&&n?_(l):[])
}return e.concat(o,_(o,[],n))
}function ct(t) {
return t.parent&&Object.getPrototypeOf(t.parent)?t.frameElement:null
}function Tt(t) {
const e=C(t);
let n=parseFloat(e.width)||0,i=parseFloat(e.height)||0;
const o=S(t),s=o?t.offsetWidth:n,r=o?t.offsetHeight:i,l=U(n)!==s||U(i)!==r;
return l&&(n=s,i=r), {
width:n,height:i,$:l
}
}function ft(t) {
return O(t)?t:t.contextElement
}function I(t) {
const e=ft(t);
if(!S(e))return R(1);
const n=e.getBoundingClientRect(), {
width:i,height:o,$:s
}=Tt(e);
let r=(s?U(n.width):n.width)/i,l=(s?U(n.height):n.height)/o;
return(!r||!Number.isFinite(r))&&(r=1),(!l||!Number.isFinite(l))&&(l=1), {
x:r,y:l
}
}const oe=R(0);
function Lt(t) {
const e=A(t);
return!ut()||!e.visualViewport?oe: {
x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop
}
}function se(t,e,n) {
return e===void 0&&(e=!1),!n||e&&n!==A(t)?!1:e
}function M(t,e,n,i) {
e===void 0&&(e=!1),n===void 0&&(n=!1);
const o=t.getBoundingClientRect(),s=ft(t);
let r=R(1);
e&&(i?O(i)&&(r=I(i)):r=I(t));
const l=se(s,n,i)?Lt(s):R(0);
let c=(o.left+l.x)/r.x,a=(o.top+l.y)/r.y,h=o.width/r.x,u=o.height/r.y;
if(s) {
const d=A(s),f=i&&O(i)?A(i):i;
let p=d,g=ct(p);
for(;
g&&i&&f!==p;
) {
const w=I(g),m=g.getBoundingClientRect(),v=C(g),y=m.left+(g.clientLeft+parseFloat(v.paddingLeft))*w.x,b=m.top+(g.clientTop+parseFloat(v.paddingTop))*w.y;
c*=w.x,a*=w.y,h*=w.x,u*=w.y,c+=y,a+=b,p=A(g),g=ct(p)
}
}return K( {
width:h,height:u,x:c,y:a
})
}function ht(t,e) {
const n=nt(t).scrollLeft;
return e?e.left+n:M(T(t)).left+n
}function kt(t,e,n) {
n===void 0&&(n=!1);
const i=t.getBoundingClientRect(),o=i.left+e.scrollLeft-(n?0:ht(t,i)),s=i.top+e.scrollTop;
return {
x:o,y:s
}
}function re(t) {
let {
elements:e,rect:n,offsetParent:i,strategy:o
}=t;
const s=o==="fixed",r=T(i),l=e?et(e.floating):!1;
if(i===r||l&&s)return n;
let c= {
scrollLeft:0,scrollTop:0
},a=R(1);
const h=R(0),u=S(i);
if((u||!u&&!s)&&((V(i)!=="body"||z(r))&&(c=nt(i)),S(i))) {
const f=M(i);
a=I(i),h.x=f.x+i.clientLeft,h.y=f.y+i.clientTop
}const d=r&&!u&&!s?kt(r,c,!0):R(0);
return {
width:n.width*a.x,height:n.height*a.y,x:n.x*a.x-c.scrollLeft*a.x+h.x+d.x,y:n.y*a.y-c.scrollTop*a.y+h.y+d.y
}
}function le(t) {
return Array.from(t.getClientRects())
}function ce(t) {
const e=T(t),n=nt(t),i=t.ownerDocument.body,o=F(e.scrollWidth,e.clientWidth,i.scrollWidth,i.clientWidth),s=F(e.scrollHeight,e.clientHeight,i.scrollHeight,i.clientHeight);
let r=-n.scrollLeft+ht(t);
const l=-n.scrollTop;
return C(i).direction==="rtl"&&(r+=F(e.clientWidth,i.clientWidth)-o), {
width:o,height:s,x:r,y:l
}
}function ae(t,e) {
const n=A(t),i=T(t),o=n.visualViewport;
let s=i.clientWidth,r=i.clientHeight,l=0,c=0;
if(o) {
s=o.width,r=o.height;
const a=ut();
(!a||a&&e==="fixed")&&(l=o.offsetLeft,c=o.offsetTop)
}return {
width:s,height:r,x:l,y:c
}
}function ue(t,e) {
const n=M(t,!0,e==="fixed"),i=n.top+t.clientTop,o=n.left+t.clientLeft,s=S(t)?I(t):R(1),r=t.clientWidth*s.x,l=t.clientHeight*s.y,c=o*s.x,a=i*s.y;
return {
width:r,height:l,x:c,y:a
}
}function xt(t,e,n) {
let i;
if(e==="viewport")i=ae(t,n);
else if(e==="document")i=ce(T(t));
else if(O(e))i=ue(e,n);
else {
const o=Lt(t);
i= {
x:e.x-o.x,y:e.y-o.y,width:e.width,height:e.height
}
}return K(i)
}function $t(t,e) {
const n=$(t);
return n===e||!O(n)||j(n)?!1:C(n).position==="fixed"||$t(n,e)
}function fe(t,e) {
const n=e.get(t);
if(n)return n;
let i=_(t,[],!1).filter(l=>O(l)&&V(l)!=="body"),o=null;
const s=C(t).position==="fixed";
let r=s?$(t):t;
for(;
O(r)&&!j(r);
) {
const l=C(r),c=at(r);
!c&&l.position==="fixed"&&(o=null),(s?!c&&!o:!c&&l.position==="static"&&!!o&&["absolute","fixed"].includes(o.position)||z(r)&&!c&&$t(t,r))?i=i.filter(h=>h!==r):o=l,r=$(r)
}return e.set(t,i),i
}function he(t) {
let {
element:e,boundary:n,rootBoundary:i,strategy:o
}=t;
const r=[...n==="clippingAncestors"?et(e)?[]:fe(e,this._c):[].concat(n),i],l=r[0],c=r.reduce((a,h)=> {
const u=xt(e,h,o);
return a.top=F(u.top,a.top),a.right=J(u.right,a.right),a.bottom=J(u.bottom,a.bottom),a.left=F(u.left,a.left),a
},xt(e,l,o));
return {
width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top
}
}function de(t) {
const {
width:e,height:n
}=Tt(t);
return {
width:e,height:n
}
}function pe(t,e,n) {
const i=S(e),o=T(e),s=n==="fixed",r=M(t,!0,s,e);
let l= {
scrollLeft:0,scrollTop:0
};
const c=R(0);
function a() {
c.x=ht(o)
}if(i||!i&&!s)if((V(e)!=="body"||z(o))&&(l=nt(e)),i) {
const f=M(e,!0,s,e);
c.x=f.x+e.clientLeft,c.y=f.y+e.clientTop
}else o&&a();
s&&!i&&o&&a();
const h=o&&!i&&!s?kt(o,l):R(0),u=r.left+l.scrollLeft-c.x-h.x,d=r.top+l.scrollTop-c.y-h.y;
return {
x:u,y:d,width:r.width,height:r.height
}
}function rt(t) {
return C(t).position==="static"
}function bt(t,e) {
if(!S(t)||C(t).position==="fixed")return null;
if(e)return e(t);
let n=t.offsetParent;
return T(t)===n&&(n=n.ownerDocument.body),n
}function Pt(t,e) {
const n=A(t);
if(et(t))return n;
if(!S(t)) {
let o=$(t);
for(;
o&&!j(o);
) {
if(O(o)&&!rt(o))return o;
o=$(o)
}return n
}let i=bt(t,e);
for(;
i&&ne(i)&&rt(i);
)i=bt(i,e);
return i&&j(i)&&rt(i)&&!at(i)?n:i||ie(t)||n
}const me=async function(t) {
const e=this.getOffsetParent||Pt,n=this.getDimensions,i=await n(t.floating);
return {
reference:pe(t.reference,await e(t.floating),t.strategy),floating: {
x:0,y:0,width:i.width,height:i.height
}
}
};
function ge(t) {
return C(t).direction==="rtl"
}const we= {
convertOffsetParentRelativeRectToViewportRelativeRect:re,getDocumentElement:T,getClippingRect:he,getOffsetParent:Pt,getElementRects:me,getClientRects:le,getDimensions:de,getScale:I,isElement:O,isRTL:ge
};
function Dt(t,e) {
return t.x===e.x&&t.y===e.y&&t.width===e.width&&t.height===e.height
}function ve(t,e) {
let n=null,i;
const o=T(t);
function s() {
var l;
clearTimeout(i),(l=n)==null||l.disconnect(),n=null
}function r(l,c) {
l===void 0&&(l=!1),c===void 0&&(c=1),s();
const a=t.getBoundingClientRect(), {
left:h,top:u,width:d,height:f
}=a;
if(l||e(),!d||!f)return;
const p=Y(u),g=Y(o.clientWidth-(h+d)),w=Y(o.clientHeight-(u+f)),m=Y(h),y= {
rootMargin:-p+"px "+-g+"px "+-w+"px "+-m+"px",threshold:F(0,J(1,c))||1
};
let b=!0;
function x(q) {
const N=q[0].intersectionRatio;
if(N!==c) {
if(!b)return r();
N?r(!1,N):i=setTimeout(()=> {
r(!1,1e-7)
},1e3)
}N===1&&!Dt(a,t.getBoundingClientRect())&&r(),b=!1
}try {
n=new IntersectionObserver(x, {
...y,root:o.ownerDocument
})
}catch {
n=new IntersectionObserver(x,y)
}n.observe(t)
}return r(!0),s
}function ye(t,e,n,i) {
i===void 0&&(i= {
});
const {
ancestorScroll:o=!0,ancestorResize:s=!0,elementResize:r=typeof ResizeObserver=="function",layoutShift:l=typeof IntersectionObserver=="function",animationFrame:c=!1
}=i,a=ft(t),h=o||s?[...a?_(a):[],..._(e)]:[];
h.forEach(m=> {
o&&m.addEventListener("scroll",n, {
passive:!0
}),s&&m.addEventListener("resize",n)
});
const u=a&&l?ve(a,n):null;
let d=-1,f=null;
r&&(f=new ResizeObserver(m=> {
let[v]=m;
v&&v.target===a&&f&&(f.unobserve(e),cancelAnimationFrame(d),d=requestAnimationFrame(()=> {
var y;
(y=f)==null||y.observe(e)
})),n()
}),a&&!c&&f.observe(a),f.observe(e));
let p,g=c?M(t):null;
c&&w();
function w() {
const m=M(t);
g&&!Dt(g,m)&&n(),g=m,p=requestAnimationFrame(w)
}return n(),()=> {
var m;
h.forEach(v=> {
o&&v.removeEventListener("scroll",n),s&&v.removeEventListener("resize",n)
}),u==null||u(),(m=f)==null||m.disconnect(),f=null,c&&cancelAnimationFrame(p)
}
}const xe=te,be=ee,Ae=Qt,Ee=(t,e,n)=> {
const i=new Map,o= {
platform:we,...n
},s= {
...o.platform,_c:i
};
return Kt(t,e, {
...o,platform:s
})
},Oe=(t,e,n= {
})=>(n= {
placement:"bottom-start",offset:2,...n
},ye(t,e,()=> {
Ee(t,e, {
placement:n.placement,middleware:[xe(n.offset),Ae(),be( {
padding:5
})]
}).then(( {
x:o,y:s
})=> {
Object.assign(e.style, {
left:o+"px",top:s+"px"
})
})
})),Ft= {
alert:Wt,toast:It,modal:jt,empty:Vt,confirm:qt,floatingui:Oe,ajax:(t,e=null)=>new gt(t,e),json:t=>JSON.stringify(t,null,2),action:(t,e)=>new gt("/atom/action/"+t).post(e),random:()=>Math.random().toString(36).substring(2,15)+Math.random().toString(36).substring(2,15)
},Ce=t=>( {
name:t.name,scope:t.scope,dismissible:t.dismissible,closeable:t.closeable,showModal(e) {
this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)&&(this.$root.showModal(),this.$root.setAttribute("data-open",""),this.$dispatch("opened"))
},closeModal(e) {
(this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)||!e.detail.name&&this.$root.contains(e.target))&&(this.$root.close(),this.$root.removeAttribute("data-open"),this.$dispatch("closed"))
},backdropClick(e) {
if(!this.dismissible||e.target.tagName!=="DIALOG")return;
const n=e.target.getBoundingClientRect();
n.top<=e.clientY&&e.clientY<=n.top+n.height&&n.left<=e.clientX&&e.clientX<=n.left+n.width||this.closeModal(e)
}
}),Re=t=>( {
value:t.multiple?[]:null,options:[],callback:typeof t.options=="string"?t.options:null,multiple:t.multiple,text:null,loading:!1,visible:!1,get isEmpty() {
return!this.selected||Array.isArray(this.selected)&&!this.selected.length
},get selected() {
return Array.isArray(this.value)?this.value.map(e=>this.options.find(n=>n.value==e)):this.options.find(e=>e.value==this.value)
},get searchable() {
return t.searchable&&(this.text||Array.isArray(this.options)&&this.options.length>0||this.callback)
},init() {
this.wiresync(),this.$wire.$watch(t.wiremodel,()=>this.wiresync()),this.$watch("visible",()=>this.fetch()),this.$watch("text",()=>this.fetch())
},wiresync() {
this.value=this.$wire.get(t.wiremodel)
},show() {
this.$root.querySelector("[data-atom-dropdown] > button").click()
},clear() {
this.value=this.multiple?[]:"",this.$dispatch("input",this.value)
},fetch() {
this.callback?(this.loading=!0,atom.action("get-options", {
name:this.callback,filters: {
search:this.text,value:this.value,...t.filters
}
}).then(e=>this.options=[...e]).then(()=>this.loading=!1).then(()=>this.setWidth())):(this.options=this.text?t.options.filter(e=>e.label.toLowerCase().includes(this.text.toLowerCase())):[...t.options||[]],this.setWidth())
},setWidth() {
let e=this.$root,n=this.$root.querySelector("[data-atom-menu]");
e.clientWidth>n.clientWidth&&(n.style.width=e.clientWidth+"px")
},select(e) {
if(this.multiple) {
if(this.isSelected(e))return this.deselect(e);
this.value=[...this.value||[],e.value]
}else this.value=e.value;
this.text=null,this.loading=!1,this.$dispatch("input",this.value)
},deselect(e) {
let n=[...this.value],i=n.findIndex(o=>o==e.value);
i>-1&&(n.splice(i,1),this.value=[...n],this.text=null,this.loading=!1,this.$dispatch("input",this.value))
},moveTo(e,n=!0) {
if(n) {
let i=this.getFocusedElementIndex();
i>-1&&this.moveTo(this.getOptionsElements(i),!1),e.setAttribute("data-option-focus",""),e.focus()
}else e.removeAttribute("data-option-focus")
},getOptionHtml(e) {
return e.html?e.html:'<div class="flex items-center gap-2">'+(e.color?'<div style="background-color: '+e.color+'" class="shrink-0 w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center"></div>':"")+"<span>"+e.label+"</span></div>"
},getOptionsElements(e=-1) {
let n=Array.from(this.$root.querySelectorAll("[data-atom-option]"));
return e>-1?n[e]:n
},getFocusedElementIndex() {
return this.getOptionsElements().findIndex(e=>e.hasAttribute("data-option-focus"))
},keyUp() {
if(!this.visible)this.show();
else {
let e=this.getOptionsElements(),n=this.getFocusedElementIndex(),i=n<=0?e.length-1:n-1;
i>-1&&(this.moveTo(e[i]),this.scroll())
}
},keyDown() {
if(!this.visible)this.show();
else {
let e=this.getOptionsElements(),n=this.getFocusedElementIndex(),i=n>=e.length-1?0:n+1;
i>-1&&(this.moveTo(e[i]),this.scroll())
}
},isSelected(e) {
return this.multiple?(this.value||[]).includes(e.value):e.value===this.value
},scroll() {
let e=this.$root.querySelector("[data-atom-menu]"),n=this.getOptionsElements(),i=n.findIndex(s=>s.getAttribute("data-option-focus",!0)),o=i>-1?n[i]:null;
if(o)if(i===0)e.scrollTop=0;
else if(i===n.length-1)e.scrollTop=e.scrollHeight;
else {
let s=e.getBoundingClientRect().height,r=o.getBoundingClientRect().top-e.getBoundingClientRect().top,l=o.getBoundingClientRect().height;
r>s?e.scrollTop=e.scrollTop+l*2:r<0&&(e.scrollTop=e.scrollTop+r)
}
}
}),Se=t=>( {
cleanup:null,placement:t.placement,interactive:t.interactive,get popover() {
return this.$root.querySelector("[data-atom-tooltip-content]")
},init() {
this.$root.addEventListener("mouseenter",()=>this.show()),this.$root.addEventListener("mouseleave",()=>this.show(!1))
},show(e=!0) {
var n;
this.popover&&(e?(this.popover.showPopover(),this.cleanup=atom.floatingui(this.$root,this.popover, {
placement:this.placement
})):(this.popover.hidePopover(),(n=this.cleanup)==null||n.call(this)))
}
}),Te=t=>( {
cleanup:null,locked:t.locked,placement:t.placement,get trigger() {
return this.$root.querySelector("[data-atom-dropdown-trigger]")||this.$root.querySelector("button")
},get popover() {
return this.$root.querySelector(":scope >[popover]")||this.$root.querySelector("[data-atom-dropdown-popover]")||this.$root.querySelector("[data-atom-menu]")
},init() {
var e,n,i;
(e=this.trigger)==null||e.addEventListener("click",()=>this.show()),(n=this.popover)==null||n.addEventListener("toggle",o=> {
var s;
o.newState==="open"?this.$dispatch("open"):o.newState==="closed"&&(this.$dispatch("close"),(s=this.cleanup)==null||s.call(this))
}),this.locked||(i=this.popover)==null||i.addEventListener("click",()=>this.hide())
},show() {
this.popover.showPopover(),this.$root.setAttribute("data-open",""),this.cleanup=atom.floatingui(this.trigger,this.popover, {
placement:this.placement
})
},hide() {
this.popover.hidePopover(),this.$root.removeAttribute("data-open")
}
}),Le=t=>( {
trail:[],heading:t.heading,get breadcrumbs() {
let e=this.trail.slice().reverse().findIndex(i=>i.home);
if(e===-1)return[];
let n=this.trail.length-1-e;
return this.trail.slice(n)
},retrieve() {
var i;
let e=(i=document.body.querySelector("[data-atom-main] > *"))==null?void 0:i.getAttribute("wire:id");
if(!e)return;
let n=Livewire.find(e);
if(n)return n._breadcrumbs
},push(e) {
e.forEach(n=>this.trail.push( {
key:atom.random(),...n
}))
},back() {
let e=this.breadcrumbs.length-2;
e>-1&&Livewire.navigate(this.breadcrumbs[e].url)
},build() {
let e=this.retrieve(),i=[ {
...e.home,home:!0
},...e.items].filter(Boolean),o=e.replace;
if(!this.trail.length)this.push(i);
else if(i.length) {
let s=i[i.length-1],r=this.trail.findIndex(l=>l.url===s.url);
r===-1?this.push([s]):o?this.trail.splice(r,1,s):(this.trail.splice(r),this.push([s]))
}
}
});
document.addEventListener("alpine:init",()=> {
Alpine.data("modal",Ce),Alpine.data("select",Re),Alpine.data("tooltip",Se),Alpine.data("dropdown",Te),Alpine.data("breadcrumbs",Le)
});
window.dd=console.log.bind(console);
window.atom=Ft;
window.empty=Ft.empty;
