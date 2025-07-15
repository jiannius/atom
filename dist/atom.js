var Mt=Object.defineProperty;
var Ht=(t,e,o)=>e in t?Mt(t,e, {
enumerable:!0,configurable:!0,writable:!0,value:o
}):t[e]=o;
var lt=(t,e,o)=>Ht(t,typeof e!="symbol"?e+"":e,o);
Number.prototype.currency=function(t=null,e=!1) {
const o= {
minimumFractionDigits:2
};
let n,i=Number(this);
return e?(i=i+Number.EPSILON,n=(Math.round(i*2*10)/10/2).toLocaleString("en-US",o)):n=i.toLocaleString("en-US",o),t?t+" "+n:n
};
class yt {
constructor(e,o= {
}) {
lt(this,"url");
lt(this,"headers");
this.url=e,this.headers= {
...o,"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")
}
}get(e) {
let o=empty(e)?this.url:this.url+"?"+new URLSearchParams(e).toString();
return fetch(o, {
method:"GET",headers: {
"Content-Type":"application/json",...this.headers
}
}).then(n=>n.json())
}post(e) {
return fetch(this.url, {
method:"POST",body:e instanceof FormData?e:JSON.stringify(e),headers:e instanceof FormData?this.headers: {
"Content-Type":"application/json",...this.headers
}
}).then(o=>o.json())
}
}const It=t=>new Promise((e,o)=> {
dispatchEvent(new CustomEvent("atom-alert-show", {
detail: {
...t,onDismissed:()=>e()
}
}))
}),jt=t=> {
dispatchEvent(new CustomEvent("atom-toast-show", {
detail:t
}))
},Vt=(t=null)=>( {
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
}),qt=t=>t==null?!0:(t=JSON.parse(JSON.stringify(t)),Array.isArray(t)&&!t.length||typeof t=="object"&&!Object.keys(t).length&&Object.getPrototypeOf(t)===Object.prototype||typeof t=="string"&&t.trim()===""),_t=t=>new Promise((e,o)=> {
dispatchEvent(new CustomEvent("atom-confirm-show", {
detail: {
...t,onAccepted:(n=null,i=null)=>e( {
password:n,passphrase:i
}),onRejected:()=>o()
}
}))
}),K=Math.min,z=Math.max,Q=Math.round,G=Math.floor,S=t=>( {
x:t,y:t
}),Yt= {
left:"right",right:"left",bottom:"top",top:"bottom"
},Xt= {
start:"end",end:"start"
};
function xt(t,e,o) {
return z(t,K(e,o))
}function et(t,e) {
return typeof t=="function"?t(e):t
}function W(t) {
return t.split("-")[0]
}function nt(t) {
return t.split("-")[1]
}function Rt(t) {
return t==="x"?"y":"x"
}function Tt(t) {
return t==="y"?"height":"width"
}function F(t) {
return["top","bottom"].includes(W(t))?"y":"x"
}function St(t) {
return Rt(F(t))
}function Ut(t,e,o) {
o===void 0&&(o=!1);
const n=nt(t),i=St(t),r=Tt(i);
let s=i==="x"?n===(o?"end":"start")?"right":"left":n==="start"?"bottom":"top";
return e.reference[r]>e.floating[r]&&(s=Z(s)),[s,Z(s)]
}function Jt(t) {
const e=Z(t);
return[ut(t),e,ut(e)]
}function ut(t) {
return t.replace(/start|end/g,e=>Xt[e])
}function Gt(t,e,o) {
const n=["left","right"],i=["right","left"],r=["top","bottom"],s=["bottom","top"];
switch(t) {
case"top":case"bottom":return o?e?i:n:e?n:i;
case"left":case"right":return e?r:s;
default:return[]
}
}function Kt(t,e,o,n) {
const i=nt(t);
let r=Gt(W(t),o==="start",n);
return i&&(r=r.map(s=>s+"-"+i),e&&(r=r.concat(r.map(ut)))),r
}function Z(t) {
return t.replace(/left|right|bottom|top/g,e=>Yt[e])
}function Qt(t) {
return {
top:0,right:0,bottom:0,left:0,...t
}
}function Zt(t) {
return typeof t!="number"?Qt(t): {
top:t,right:t,bottom:t,left:t
}
}function tt(t) {
const {
x:e,y:o,width:n,height:i
}=t;
return {
width:n,height:i,top:o,left:e,right:e+n,bottom:o+i,x:e,y:o
}
}function bt(t,e,o) {
let {
reference:n,floating:i
}=t;
const r=F(e),s=St(e),l=Tt(s),c=W(e),a=r==="y",d=n.x+n.width/2-i.width/2,f=n.y+n.height/2-i.height/2,h=n[l]/2-i[l]/2;
let u;
switch(c) {
case"top":u= {
x:d,y:n.y-i.height
};
break;
case"bottom":u= {
x:d,y:n.y+n.height
};
break;
case"right":u= {
x:n.x+n.width,y:f
};
break;
case"left":u= {
x:n.x-i.width,y:f
};
break;
default:u= {
x:n.x,y:n.y
}
}switch(nt(e)) {
case"start":u[s]-=h*(o&&a?-1:1);
break;
case"end":u[s]+=h*(o&&a?-1:1);
break
}return u
}const te=async(t,e,o)=> {
const {
placement:n="bottom",strategy:i="absolute",middleware:r=[],platform:s
}=o,l=r.filter(Boolean),c=await(s.isRTL==null?void 0:s.isRTL(e));
let a=await s.getElementRects( {
reference:t,floating:e,strategy:i
}), {
x:d,y:f
}=bt(a,n,c),h=n,u= {
},p=0;
for(let g=0;
g<l.length;
g++) {
const {
name:w,fn:m
}=l[g], {
x:v,y,data:x,reset:b
}=await m( {
x:d,y:f,initialPlacement:n,placement:h,strategy:i,middlewareData:u,rects:a,platform:s,elements: {
reference:t,floating:e
}
});
d=v??d,f=y??f,u= {
...u,[w]: {
...u[w],...x
}
},b&&p<=50&&(p++,typeof b=="object"&&(b.placement&&(h=b.placement),b.rects&&(a=b.rects===!0?await s.getElementRects( {
reference:t,floating:e,strategy:i
}):b.rects), {
x:d,y:f
}=bt(a,h,c)),g=-1)
}return {
x:d,y:f,placement:h,strategy:i,middlewareData:u
}
};
async function Ct(t,e) {
var o;
e===void 0&&(e= {
});
const {
x:n,y:i,platform:r,rects:s,elements:l,strategy:c
}=t, {
boundary:a="clippingAncestors",rootBoundary:d="viewport",elementContext:f="floating",altBoundary:h=!1,padding:u=0
}=et(e,t),p=Zt(u),w=l[h?f==="floating"?"reference":"floating":f],m=tt(await r.getClippingRect( {
element:(o=await(r.isElement==null?void 0:r.isElement(w)))==null||o?w:w.contextElement||await(r.getDocumentElement==null?void 0:r.getDocumentElement(l.floating)),boundary:a,rootBoundary:d,strategy:c
})),v=f==="floating"? {
x:n,y:i,width:s.floating.width,height:s.floating.height
}:s.reference,y=await(r.getOffsetParent==null?void 0:r.getOffsetParent(l.floating)),x=await(r.isElement==null?void 0:r.isElement(y))?await(r.getScale==null?void 0:r.getScale(y))|| {
x:1,y:1
}: {
x:1,y:1
},b=tt(r.convertOffsetParentRelativeRectToViewportRelativeRect?await r.convertOffsetParentRelativeRectToViewportRelativeRect( {
elements:l,rect:v,offsetParent:y,strategy:c
}):v);
return {
top:(m.top-b.top+p.top)/x.y,bottom:(b.bottom-m.bottom+p.bottom)/x.y,left:(m.left-b.left+p.left)/x.x,right:(b.right-m.right+p.right)/x.x
}
}const ee=function(t) {
return t===void 0&&(t= {
}), {
name:"flip",options:t,async fn(e) {
var o,n;
const {
placement:i,middlewareData:r,rects:s,initialPlacement:l,platform:c,elements:a
}=e, {
mainAxis:d=!0,crossAxis:f=!0,fallbackPlacements:h,fallbackStrategy:u="bestFit",fallbackAxisSideDirection:p="none",flipAlignment:g=!0,...w
}=et(t,e);
if((o=r.arrow)!=null&&o.alignmentOffset)return {
};
const m=W(i),v=F(l),y=W(l)===l,x=await(c.isRTL==null?void 0:c.isRTL(a.floating)),b=h||(y||!g?[Z(l)]:Jt(l)),k=p!=="none";
!h&&k&&b.push(...Kt(l,g,p,x));
const T=[l,...b],V=await Ct(e,w),J=[];
let M=((n=r.flip)==null?void 0:n.overflows)||[];
if(d&&J.push(V[m]),f) {
const P=Ut(i,s,x);
J.push(V[P[0]],V[P[1]])
}if(M=[...M, {
placement:i,overflows:J
}],!J.every(P=>P<=0)) {
var gt,wt;
const P=(((gt=r.flip)==null?void 0:gt.index)||0)+1,rt=T[P];
if(rt&&(!(f==="alignment"?v!==F(rt):!1)||M.every(E=>E.overflows[0]>0&&F(E.placement)===v)))return {
data: {
index:P,overflows:M
},reset: {
placement:rt
}
};
let q=(wt=M.filter(D=>D.overflows[0]<=0).sort((D,E)=>D.overflows[1]-E.overflows[1])[0])==null?void 0:wt.placement;
if(!q)switch(u) {
case"bestFit": {
var vt;
const D=(vt=M.filter(E=> {
if(k) {
const $=F(E.placement);
return $===v||$==="y"
}return!0
}).map(E=>[E.placement,E.overflows.filter($=>$>0).reduce(($,Bt)=>$+Bt,0)]).sort((E,$)=>E[1]-$[1])[0])==null?void 0:vt[0];
D&&(q=D);
break
}case"initialPlacement":q=l;
break
}if(i!==q)return {
reset: {
placement:q
}
}
}return {
}
}
}
};
async function ne(t,e) {
const {
placement:o,platform:n,elements:i
}=t,r=await(n.isRTL==null?void 0:n.isRTL(i.floating)),s=W(o),l=nt(o),c=F(o)==="y",a=["left","top"].includes(s)?-1:1,d=r&&c?-1:1,f=et(e,t);
let {
mainAxis:h,crossAxis:u,alignmentAxis:p
}=typeof f=="number"? {
mainAxis:f,crossAxis:0,alignmentAxis:null
}: {
mainAxis:f.mainAxis||0,crossAxis:f.crossAxis||0,alignmentAxis:f.alignmentAxis
};
return l&&typeof p=="number"&&(u=l==="end"?p*-1:p),c? {
x:u*d,y:h*a
}: {
x:h*a,y:u*d
}
}const oe=function(t) {
return t===void 0&&(t=0), {
name:"offset",options:t,async fn(e) {
var o,n;
const {
x:i,y:r,placement:s,middlewareData:l
}=e,c=await ne(e,t);
return s===((o=l.offset)==null?void 0:o.placement)&&(n=l.arrow)!=null&&n.alignmentOffset? {
}: {
x:i+c.x,y:r+c.y,data: {
...c,placement:s
}
}
}
}
},ie=function(t) {
return t===void 0&&(t= {
}), {
name:"shift",options:t,async fn(e) {
const {
x:o,y:n,placement:i
}=e, {
mainAxis:r=!0,crossAxis:s=!1,limiter:l= {
fn:w=> {
let {
x:m,y:v
}=w;
return {
x:m,y:v
}
}
},...c
}=et(t,e),a= {
x:o,y:n
},d=await Ct(e,c),f=F(W(i)),h=Rt(f);
let u=a[h],p=a[f];
if(r) {
const w=h==="y"?"top":"left",m=h==="y"?"bottom":"right",v=u+d[w],y=u-d[m];
u=xt(v,u,y)
}if(s) {
const w=f==="y"?"top":"left",m=f==="y"?"bottom":"right",v=p+d[w],y=p-d[m];
p=xt(v,p,y)
}const g=l.fn( {
...e,[h]:u,[f]:p
});
return {
...g,data: {
x:g.x-o,y:g.y-n,enabled: {
[h]:r,[f]:s
}
}
}
}
}
};
function ot() {
return typeof window<"u"
}function j(t) {
return Lt(t)?(t.nodeName||"").toLowerCase():"#document"
}function A(t) {
var e;
return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window
}function L(t) {
var e;
return(e=(Lt(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement
}function Lt(t) {
return ot()?t instanceof Node||t instanceof A(t).Node:!1
}function O(t) {
return ot()?t instanceof Element||t instanceof A(t).Element:!1
}function C(t) {
return ot()?t instanceof HTMLElement||t instanceof A(t).HTMLElement:!1
}function At(t) {
return!ot()||typeof ShadowRoot>"u"?!1:t instanceof ShadowRoot||t instanceof A(t).ShadowRoot
}function U(t) {
const {
overflow:e,overflowX:o,overflowY:n,display:i
}=R(t);
return/auto|scroll|overlay|hidden|clip/.test(e+n+o)&&!["inline","contents"].includes(i)
}function se(t) {
return["table","td","th"].includes(j(t))
}function it(t) {
return[":popover-open",":modal"].some(e=> {
try {
return t.matches(e)
}catch {
return!1
}
})
}function dt(t) {
const e=ht(),o=O(t)?R(t):t;
return["transform","translate","scale","rotate","perspective"].some(n=>o[n]?o[n]!=="none":!1)||(o.containerType?o.containerType!=="normal":!1)||!e&&(o.backdropFilter?o.backdropFilter!=="none":!1)||!e&&(o.filter?o.filter!=="none":!1)||["transform","translate","scale","rotate","perspective","filter"].some(n=>(o.willChange||"").includes(n))||["paint","layout","strict","content"].some(n=>(o.contain||"").includes(n))
}function re(t) {
let e=N(t);
for(;
C(e)&&!I(e);
) {
if(dt(e))return e;
if(it(e))return null;
e=N(e)
}return null
}function ht() {
return typeof CSS>"u"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")
}function I(t) {
return["html","body","#document"].includes(j(t))
}function R(t) {
return A(t).getComputedStyle(t)
}function st(t) {
return O(t)? {
scrollLeft:t.scrollLeft,scrollTop:t.scrollTop
}: {
scrollLeft:t.scrollX,scrollTop:t.scrollY
}
}function N(t) {
if(j(t)==="html")return t;
const e=t.assignedSlot||t.parentNode||At(t)&&t.host||L(t);
return At(e)?e.host:e
}function kt(t) {
const e=N(t);
return I(e)?t.ownerDocument?t.ownerDocument.body:t.body:C(e)&&U(e)?e:kt(e)
}function X(t,e,o) {
var n;
e===void 0&&(e=[]),o===void 0&&(o=!0);
const i=kt(t),r=i===((n=t.ownerDocument)==null?void 0:n.body),s=A(i);
if(r) {
const l=ft(s);
return e.concat(s,s.visualViewport||[],U(i)?i:[],l&&o?X(l):[])
}return e.concat(i,X(i,[],o))
}function ft(t) {
return t.parent&&Object.getPrototypeOf(t.parent)?t.frameElement:null
}function $t(t) {
const e=R(t);
let o=parseFloat(e.width)||0,n=parseFloat(e.height)||0;
const i=C(t),r=i?t.offsetWidth:o,s=i?t.offsetHeight:n,l=Q(o)!==r||Q(n)!==s;
return l&&(o=r,n=s), {
width:o,height:n,$:l
}
}function pt(t) {
return O(t)?t:t.contextElement
}function H(t) {
const e=pt(t);
if(!C(e))return S(1);
const o=e.getBoundingClientRect(), {
width:n,height:i,$:r
}=$t(e);
let s=(r?Q(o.width):o.width)/n,l=(r?Q(o.height):o.height)/i;
return(!s||!Number.isFinite(s))&&(s=1),(!l||!Number.isFinite(l))&&(l=1), {
x:s,y:l
}
}const le=S(0);
function Ft(t) {
const e=A(t);
return!ht()||!e.visualViewport?le: {
x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop
}
}function ce(t,e,o) {
return e===void 0&&(e=!1),!o||e&&o!==A(t)?!1:e
}function B(t,e,o,n) {
e===void 0&&(e=!1),o===void 0&&(o=!1);
const i=t.getBoundingClientRect(),r=pt(t);
let s=S(1);
e&&(n?O(n)&&(s=H(n)):s=H(t));
const l=ce(r,o,n)?Ft(r):S(0);
let c=(i.left+l.x)/s.x,a=(i.top+l.y)/s.y,d=i.width/s.x,f=i.height/s.y;
if(r) {
const h=A(r),u=n&&O(n)?A(n):n;
let p=h,g=ft(p);
for(;
g&&n&&u!==p;
) {
const w=H(g),m=g.getBoundingClientRect(),v=R(g),y=m.left+(g.clientLeft+parseFloat(v.paddingLeft))*w.x,x=m.top+(g.clientTop+parseFloat(v.paddingTop))*w.y;
c*=w.x,a*=w.y,d*=w.x,f*=w.y,c+=y,a+=x,p=A(g),g=ft(p)
}
}return tt( {
width:d,height:f,x:c,y:a
})
}function mt(t,e) {
const o=st(t).scrollLeft;
return e?e.left+o:B(L(t)).left+o
}function Nt(t,e,o) {
o===void 0&&(o=!1);
const n=t.getBoundingClientRect(),i=n.left+e.scrollLeft-(o?0:mt(t,n)),r=n.top+e.scrollTop;
return {
x:i,y:r
}
}function ae(t) {
let {
elements:e,rect:o,offsetParent:n,strategy:i
}=t;
const r=i==="fixed",s=L(n),l=e?it(e.floating):!1;
if(n===s||l&&r)return o;
let c= {
scrollLeft:0,scrollTop:0
},a=S(1);
const d=S(0),f=C(n);
if((f||!f&&!r)&&((j(n)!=="body"||U(s))&&(c=st(n)),C(n))) {
const u=B(n);
a=H(n),d.x=u.x+n.clientLeft,d.y=u.y+n.clientTop
}const h=s&&!f&&!r?Nt(s,c,!0):S(0);
return {
width:o.width*a.x,height:o.height*a.y,x:o.x*a.x-c.scrollLeft*a.x+d.x+h.x,y:o.y*a.y-c.scrollTop*a.y+d.y+h.y
}
}function ue(t) {
return Array.from(t.getClientRects())
}function fe(t) {
const e=L(t),o=st(t),n=t.ownerDocument.body,i=z(e.scrollWidth,e.clientWidth,n.scrollWidth,n.clientWidth),r=z(e.scrollHeight,e.clientHeight,n.scrollHeight,n.clientHeight);
let s=-o.scrollLeft+mt(t);
const l=-o.scrollTop;
return R(n).direction==="rtl"&&(s+=z(e.clientWidth,n.clientWidth)-i), {
width:i,height:r,x:s,y:l
}
}function de(t,e) {
const o=A(t),n=L(t),i=o.visualViewport;
let r=n.clientWidth,s=n.clientHeight,l=0,c=0;
if(i) {
r=i.width,s=i.height;
const a=ht();
(!a||a&&e==="fixed")&&(l=i.offsetLeft,c=i.offsetTop)
}return {
width:r,height:s,x:l,y:c
}
}function he(t,e) {
const o=B(t,!0,e==="fixed"),n=o.top+t.clientTop,i=o.left+t.clientLeft,r=C(t)?H(t):S(1),s=t.clientWidth*r.x,l=t.clientHeight*r.y,c=i*r.x,a=n*r.y;
return {
width:s,height:l,x:c,y:a
}
}function Et(t,e,o) {
let n;
if(e==="viewport")n=de(t,o);
else if(e==="document")n=fe(L(t));
else if(O(e))n=he(e,o);
else {
const i=Ft(t);
n= {
x:e.x-i.x,y:e.y-i.y,width:e.width,height:e.height
}
}return tt(n)
}function Pt(t,e) {
const o=N(t);
return o===e||!O(o)||I(o)?!1:R(o).position==="fixed"||Pt(o,e)
}function pe(t,e) {
const o=e.get(t);
if(o)return o;
let n=X(t,[],!1).filter(l=>O(l)&&j(l)!=="body"),i=null;
const r=R(t).position==="fixed";
let s=r?N(t):t;
for(;
O(s)&&!I(s);
) {
const l=R(s),c=dt(s);
!c&&l.position==="fixed"&&(i=null),(r?!c&&!i:!c&&l.position==="static"&&!!i&&["absolute","fixed"].includes(i.position)||U(s)&&!c&&Pt(t,s))?n=n.filter(d=>d!==s):i=l,s=N(s)
}return e.set(t,n),n
}function me(t) {
let {
element:e,boundary:o,rootBoundary:n,strategy:i
}=t;
const s=[...o==="clippingAncestors"?it(e)?[]:pe(e,this._c):[].concat(o),n],l=s[0],c=s.reduce((a,d)=> {
const f=Et(e,d,i);
return a.top=z(f.top,a.top),a.right=K(f.right,a.right),a.bottom=K(f.bottom,a.bottom),a.left=z(f.left,a.left),a
},Et(e,l,i));
return {
width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top
}
}function ge(t) {
const {
width:e,height:o
}=$t(t);
return {
width:e,height:o
}
}function we(t,e,o) {
const n=C(e),i=L(e),r=o==="fixed",s=B(t,!0,r,e);
let l= {
scrollLeft:0,scrollTop:0
};
const c=S(0);
function a() {
c.x=mt(i)
}if(n||!n&&!r)if((j(e)!=="body"||U(i))&&(l=st(e)),n) {
const u=B(e,!0,r,e);
c.x=u.x+e.clientLeft,c.y=u.y+e.clientTop
}else i&&a();
r&&!n&&i&&a();
const d=i&&!n&&!r?Nt(i,l):S(0),f=s.left+l.scrollLeft-c.x-d.x,h=s.top+l.scrollTop-c.y-d.y;
return {
x:f,y:h,width:s.width,height:s.height
}
}function ct(t) {
return R(t).position==="static"
}function Ot(t,e) {
if(!C(t)||R(t).position==="fixed")return null;
if(e)return e(t);
let o=t.offsetParent;
return L(t)===o&&(o=o.ownerDocument.body),o
}function Dt(t,e) {
const o=A(t);
if(it(t))return o;
if(!C(t)) {
let i=N(t);
for(;
i&&!I(i);
) {
if(O(i)&&!ct(i))return i;
i=N(i)
}return o
}let n=Ot(t,e);
for(;
n&&se(n)&&ct(n);
)n=Ot(n,e);
return n&&I(n)&&ct(n)&&!dt(n)?o:n||re(t)||o
}const ve=async function(t) {
const e=this.getOffsetParent||Dt,o=this.getDimensions,n=await o(t.floating);
return {
reference:we(t.reference,await e(t.floating),t.strategy),floating: {
x:0,y:0,width:n.width,height:n.height
}
}
};
function ye(t) {
return R(t).direction==="rtl"
}const xe= {
convertOffsetParentRelativeRectToViewportRelativeRect:ae,getDocumentElement:L,getClippingRect:me,getOffsetParent:Dt,getElementRects:ve,getClientRects:ue,getDimensions:ge,getScale:H,isElement:O,isRTL:ye
};
function zt(t,e) {
return t.x===e.x&&t.y===e.y&&t.width===e.width&&t.height===e.height
}function be(t,e) {
let o=null,n;
const i=L(t);
function r() {
var l;
clearTimeout(n),(l=o)==null||l.disconnect(),o=null
}function s(l,c) {
l===void 0&&(l=!1),c===void 0&&(c=1),r();
const a=t.getBoundingClientRect(), {
left:d,top:f,width:h,height:u
}=a;
if(l||e(),!h||!u)return;
const p=G(f),g=G(i.clientWidth-(d+h)),w=G(i.clientHeight-(f+u)),m=G(d),y= {
rootMargin:-p+"px "+-g+"px "+-w+"px "+-m+"px",threshold:z(0,K(1,c))||1
};
let x=!0;
function b(k) {
const T=k[0].intersectionRatio;
if(T!==c) {
if(!x)return s();
T?s(!1,T):n=setTimeout(()=> {
s(!1,1e-7)
},1e3)
}T===1&&!zt(a,t.getBoundingClientRect())&&s(),x=!1
}try {
o=new IntersectionObserver(b, {
...y,root:i.ownerDocument
})
}catch {
o=new IntersectionObserver(b,y)
}o.observe(t)
}return s(!0),r
}function Ae(t,e,o,n) {
n===void 0&&(n= {
});
const {
ancestorScroll:i=!0,ancestorResize:r=!0,elementResize:s=typeof ResizeObserver=="function",layoutShift:l=typeof IntersectionObserver=="function",animationFrame:c=!1
}=n,a=pt(t),d=i||r?[...a?X(a):[],...X(e)]:[];
d.forEach(m=> {
i&&m.addEventListener("scroll",o, {
passive:!0
}),r&&m.addEventListener("resize",o)
});
const f=a&&l?be(a,o):null;
let h=-1,u=null;
s&&(u=new ResizeObserver(m=> {
let[v]=m;
v&&v.target===a&&u&&(u.unobserve(e),cancelAnimationFrame(h),h=requestAnimationFrame(()=> {
var y;
(y=u)==null||y.observe(e)
})),o()
}),a&&!c&&u.observe(a),u.observe(e));
let p,g=c?B(t):null;
c&&w();
function w() {
const m=B(t);
g&&!zt(g,m)&&o(),g=m,p=requestAnimationFrame(w)
}return o(),()=> {
var m;
d.forEach(v=> {
i&&v.removeEventListener("scroll",o),r&&v.removeEventListener("resize",o)
}),f==null||f(),(m=u)==null||m.disconnect(),u=null,c&&cancelAnimationFrame(p)
}
}const Ee=oe,Oe=ie,Re=ee,Te=(t,e,o)=> {
const n=new Map,i= {
platform:xe,...o
},r= {
...i.platform,_c:n
};
return te(t,e, {
...i,platform:r
})
},Se=(t,e,o= {
})=>(o= {
placement:"bottom-start",offset:2,...o
},Ae(t,e,()=> {
Te(t,e, {
placement:o.placement,middleware:[Ee(o.offset),Re(),Oe( {
padding:5
})]
}).then(( {
x:i,y:r
})=> {
Object.assign(e.style, {
left:i+"px",top:r+"px"
})
})
})),Wt= {
alert:It,toast:jt,modal:Vt,empty:qt,confirm:_t,floatingui:Se,ajax:(t,e=null)=>new yt(t,e),json:t=>JSON.stringify(t,null,2),action:(t,e)=>new yt("/atom/action/"+t).post(e),random:()=>Math.random().toString(36).substring(2,15)+Math.random().toString(36).substring(2,15)
},Ce=t=>( {
name:t.name,scope:t.scope,dismissible:t.dismissible,closeable:t.closeable,showModal(e) {
this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)&&(this.$root.showModal(),this.$root.setAttribute("data-open",""),this.$dispatch("opened"))
},closeModal(e) {
(this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)||!e.detail.name&&this.$root.contains(e.target))&&(this.$root.close(),this.$root.removeAttribute("data-open"),this.$dispatch("closed"))
},backdropClick(e) {
if(!this.dismissible||e.target.tagName!=="DIALOG")return;
const o=e.target.getBoundingClientRect();
o.top<=e.clientY&&e.clientY<=o.top+o.height&&o.left<=e.clientX&&e.clientX<=o.left+o.width||this.closeModal(e)
}
}),Le=t=>( {
value:t.multiple?[]:null,options:[],callback:typeof t.options=="string"?t.options:null,multiple:t.multiple,text:null,loading:!1,visible:!1,get isEmpty() {
return!this.selected||Array.isArray(this.selected)&&!this.selected.length
},get selected() {
return Array.isArray(this.value)?this.value.map(e=>this.options.find(o=>o.value==e)):this.options.find(e=>e.value==this.value)
},get searchable() {
return t.searchable&&(this.text||Array.isArray(this.options)&&this.options.length>0||this.callback)
},init() {
var e;
this.wiresync(),this.$wire.$watch(t.wiremodel,()=>this.wiresync()),this.$watch("visible",()=>this.fetch()),this.$watch("text",()=>this.fetch()),(this.value||(e=this.value)!=null&&e.length)&&this.fetch()
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
}).then(e=>this.options=[...e]).then(()=>this.loading=!1).then(()=> {
this.setWidth(),this.$nextTick(()=>this.$root.querySelector("[data-atom-select-search]").focus())
})):(this.options=this.text?t.options.filter(e=>e.label.toLowerCase().includes(this.text.toLowerCase())):[...t.options||[]],this.setWidth(),this.$nextTick(()=>this.$root.querySelector("[data-atom-select-search]").focus()))
},setWidth() {
let e=this.$root,o=this.$root.querySelector("[data-atom-menu]");
e.clientWidth>o.clientWidth&&(o.style.width=e.clientWidth+"px")
},select(e) {
if(this.multiple) {
if(this.isSelected(e))return this.deselect(e);
this.value=[...this.value||[],e.value]
}else this.value=e.value;
this.text=null,this.loading=!1,this.$dispatch("input",this.value)
},deselect(e) {
let o=[...this.value],n=o.findIndex(i=>i==e.value);
n>-1&&(o.splice(n,1),this.value=[...o],this.text=null,this.loading=!1,this.$dispatch("input",this.value))
},moveTo(e,o=!0) {
if(o) {
let n=this.getFocusedElementIndex();
n>-1&&this.moveTo(this.getOptionsElements(n),!1),e.setAttribute("data-option-focus",""),e.focus()
}else e.removeAttribute("data-option-focus")
},getOptionHtml(e) {
return e.html?e.html:'<div class="flex items-center gap-2">'+(e.color?'<div style="background-color: '+e.color+'" class="shrink-0 w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center"></div>':"")+"<span>"+e.label+"</span></div>"
},getOptionsElements(e=-1) {
let o=Array.from(this.$root.querySelectorAll("[data-atom-option]"));
return e>-1?o[e]:o
},getFocusedElementIndex() {
return this.getOptionsElements().findIndex(e=>e.hasAttribute("data-option-focus"))
},keyUp() {
if(!this.visible)this.show();
else {
let e=this.getOptionsElements(),o=this.getFocusedElementIndex(),n=o<=0?e.length-1:o-1;
n>-1&&(this.moveTo(e[n]),this.scroll())
}
},keyDown() {
if(!this.visible)this.show();
else {
let e=this.getOptionsElements(),o=this.getFocusedElementIndex(),n=o>=e.length-1?0:o+1;
n>-1&&(this.moveTo(e[n]),this.scroll())
}
},isSelected(e) {
return this.multiple?(this.value||[]).includes(e.value):e.value===this.value
},scroll() {
let e=this.$root.querySelector("[data-atom-menu]"),o=this.getOptionsElements(),n=o.findIndex(r=>r.getAttribute("data-option-focus",!0)),i=n>-1?o[n]:null;
if(i)if(n===0)e.scrollTop=0;
else if(n===o.length-1)e.scrollTop=e.scrollHeight;
else {
let r=e.getBoundingClientRect().height,s=i.getBoundingClientRect().top-e.getBoundingClientRect().top,l=i.getBoundingClientRect().height;
s>r?e.scrollTop=e.scrollTop+l*2:s<0&&(e.scrollTop=e.scrollTop+s)
}
}
}),ke=t=>( {
cleanup:null,placement:t.placement,interactive:t.interactive,get popover() {
return this.$root.querySelector("[data-atom-tooltip-content]")
},init() {
this.$root.addEventListener("mouseenter",()=>this.show()),this.$root.addEventListener("mouseleave",()=>this.show(!1))
},show(e=!0) {
var o;
this.popover&&(e?(this.popover.showPopover(),this.cleanup=atom.floatingui(this.$root,this.popover, {
placement:this.placement
})):(this.popover.hidePopover(),(o=this.cleanup)==null||o.call(this)))
}
}),$e=t=>( {
cleanup:null,locked:t.locked,placement:t.placement,get trigger() {
return this.$root.querySelector("[data-atom-dropdown-trigger]")||this.$root.querySelector("button")
},get popover() {
return this.$root.querySelector(":scope >[popover]")||this.$root.querySelector("[data-atom-dropdown-popover]")||this.$root.querySelector("[data-atom-menu]")
},init() {
var e,o,n;
(e=this.trigger)==null||e.addEventListener("click",()=>this.show()),(o=this.popover)==null||o.addEventListener("toggle",i=> {
var r;
i.newState==="open"?this.$dispatch("open"):i.newState==="closed"&&(this.$dispatch("close"),(r=this.cleanup)==null||r.call(this))
}),this.locked||(n=this.popover)==null||n.addEventListener("click",()=>this.hide())
},show() {
this.popover.showPopover(),this.$root.setAttribute("data-open",""),this.cleanup=atom.floatingui(this.trigger,this.popover, {
placement:this.placement
})
},hide() {
this.popover.hidePopover(),this.$root.removeAttribute("data-open")
}
}),Fe=t=>( {
trail:[],heading:t.heading,get breadcrumbs() {
let e=this.trail.slice().reverse().findIndex(n=>n.home);
if(e===-1)return[];
let o=this.trail.length-1-e;
return this.trail.slice(o)
},retrieve() {
var n;
let e=(n=document.body.querySelector("[data-atom-main] > *"))==null?void 0:n.getAttribute("wire:id");
if(!e)return;
let o=Livewire.find(e);
if(o)return o._breadcrumbs
},push(e) {
e.forEach(o=>this.trail.push( {
key:atom.random(),...o
}))
},back() {
let e=this.breadcrumbs.length-2;
e>-1&&Livewire.navigate(this.breadcrumbs[e].url)
},build() {
let e=this.retrieve(),n=[ {
...e.home,home:!0
},...e.items].filter(Boolean),i=e.replace;
if(!this.trail.length)this.push(n);
else if(n.length) {
let r=n[n.length-1],s=this.trail.findIndex(l=>l.url===r.url);
s===-1?this.push([r]):i?this.trail.splice(s,1,r):(this.trail.splice(s),this.push([r]))
}
}
});
var Y=new Map;
function Ne(t) {
var e=Y.get(t);
e&&e.destroy()
}function Pe(t) {
var e=Y.get(t);
e&&e.update()
}var _=null;
typeof window>"u"?((_=function(t) {
return t
}).destroy=function(t) {
return t
},_.update=function(t) {
return t
}):((_=function(t,e) {
return t&&Array.prototype.forEach.call(t.length?t:[t],function(o) {
return function(n) {
if(n&&n.nodeName&&n.nodeName==="TEXTAREA"&&!Y.has(n)) {
var i,r=null,s=window.getComputedStyle(n),l=(i=n.value,function() {
a( {
testForHeightReduction:i===""||!n.value.startsWith(i),restoreTextAlign:null
}),i=n.value
}),c=(function(f) {
n.removeEventListener("autosize:destroy",c),n.removeEventListener("autosize:update",d),n.removeEventListener("input",l),window.removeEventListener("resize",d),Object.keys(f).forEach(function(h) {
return n.style[h]=f[h]
}),Y.delete(n)
}).bind(n, {
height:n.style.height,resize:n.style.resize,textAlign:n.style.textAlign,overflowY:n.style.overflowY,overflowX:n.style.overflowX,wordWrap:n.style.wordWrap
});
n.addEventListener("autosize:destroy",c),n.addEventListener("autosize:update",d),n.addEventListener("input",l),window.addEventListener("resize",d),n.style.overflowX="hidden",n.style.wordWrap="break-word",Y.set(n, {
destroy:c,update:d
}),d()
}function a(f) {
var h,u,p=f.restoreTextAlign,g=p===void 0?null:p,w=f.testForHeightReduction,m=w===void 0||w,v=s.overflowY;
if(n.scrollHeight!==0&&(s.resize==="vertical"?n.style.resize="none":s.resize==="both"&&(n.style.resize="horizontal"),m&&(h=function(x) {
for(var b=[];
x&&x.parentNode&&x.parentNode instanceof Element;
)x.parentNode.scrollTop&&b.push([x.parentNode,x.parentNode.scrollTop]),x=x.parentNode;
return function() {
return b.forEach(function(k) {
var T=k[0],V=k[1];
T.style.scrollBehavior="auto",T.scrollTop=V,T.style.scrollBehavior=null
})
}
}(n),n.style.height=""),u=s.boxSizing==="content-box"?n.scrollHeight-(parseFloat(s.paddingTop)+parseFloat(s.paddingBottom)):n.scrollHeight+parseFloat(s.borderTopWidth)+parseFloat(s.borderBottomWidth),s.maxHeight!=="none"&&u>parseFloat(s.maxHeight)?(s.overflowY==="hidden"&&(n.style.overflow="scroll"),u=parseFloat(s.maxHeight)):s.overflowY!=="hidden"&&(n.style.overflow="hidden"),n.style.height=u+"px",g&&(n.style.textAlign=g),h&&h(),r!==u&&(n.dispatchEvent(new Event("autosize:resized", {
bubbles:!0
})),r=u),v!==s.overflow&&!g)) {
var y=s.textAlign;
s.overflow==="hidden"&&(n.style.textAlign=y==="start"?"end":"start"),a( {
restoreTextAlign:y,testForHeightReduction:!0
})
}
}function d() {
a( {
testForHeightReduction:!0,restoreTextAlign:null
})
}
}(o)
}),t
}).destroy=function(t) {
return t&&Array.prototype.forEach.call(t.length?t:[t],Ne),t
},_.update=function(t) {
return t&&Array.prototype.forEach.call(t.length?t:[t],Pe),t
});
var at=_;
function De(t) {
t.directive("autosize",(e, {
modifiers:o
}, {
cleanup:n
})=> {
at(e);
const i=Array.from(e.attributes);
let r=!1;
for(let {
nodeName:l
}of i)if(l==="wire:model"||l.startsWith("wire:model.")) {
r=!0;
break
}!e.hasAttribute("wire:ignore")&&r&&e.setAttribute("wire:ignore","");
const s=()=> {
at.update(e)
};
e.addEventListener("autosize",s),n(()=> {
at.destroy(e),e.removeEventListener("autosize",s)
})
}),t.magic("autosize",e=>o=> {
(o||e).dispatchEvent(new Event("autosize"))
})
}document.addEventListener("alpine:init",()=> {
Alpine.data("modal",Ce),Alpine.data("select",Le),Alpine.data("tooltip",ke),Alpine.data("dropdown",$e),Alpine.data("breadcrumbs",Fe),Alpine.plugin(De)
});
window.dd=console.log.bind(console);
window.atom=Wt;
window.empty=Wt.empty;
