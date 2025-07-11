const Mt=t=>new Promise((e,n)=> {
dispatchEvent(new CustomEvent("atom-alert-show", {
detail: {
...t,onDismissed:()=>e()
}
}))
}),Ft=t=> {
dispatchEvent(new CustomEvent("atom-toast-show", {
detail:t
}))
},Nt=(t=null)=>( {
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
}),Bt=t=>t==null?!0:(t=JSON.parse(JSON.stringify(t)),Array.isArray(t)&&!t.length||typeof t=="object"&&!Object.keys(t).length&&Object.getPrototypeOf(t)===Object.prototype||typeof t=="string"&&t.trim()===""),It=t=>new Promise((e,n)=> {
dispatchEvent(new CustomEvent("atom-confirm-show", {
detail: {
...t,onAccepted:(o=null,i=null)=>e( {
password:o,passphrase:i
}),onRejected:()=>n()
}
}))
}),Vt=(t=null)=> {
let e=()=>document.documentElement.classList.add("dark"),n=()=>document.documentElement.classList.remove("dark");
if(t=t||window.localStorage.getItem("darkmode")||"system",t==="system") {
let o=window.matchMedia("(prefers-color-scheme: dark)");
window.localStorage.removeItem("darkmode"),o.matches?e():n()
}else t==="dark"?(window.localStorage.setItem("darkmode","dark"),e()):t==="light"&&(window.localStorage.setItem("darkmode","light"),n())
},J=Math.min,M=Math.max,U=Math.round,X=Math.floor,C=t=>( {
x:t,y:t
}),Wt= {
left:"right",right:"left",bottom:"top",top:"bottom"
},jt= {
start:"end",end:"start"
};
function pt(t,e,n) {
return M(t,J(e,n))
}function Q(t,e) {
return typeof t=="function"?t(e):t
}function F(t) {
return t.split("-")[0]
}function Z(t) {
return t.split("-")[1]
}function vt(t) {
return t==="x"?"y":"x"
}function bt(t) {
return t==="y"?"height":"width"
}function T(t) {
return["top","bottom"].includes(F(t))?"y":"x"
}function At(t) {
return vt(T(t))
}function Ht(t,e,n) {
n===void 0&&(n=!1);
const o=Z(t),i=At(t),r=bt(i);
let s=i==="x"?o===(n?"end":"start")?"right":"left":o==="start"?"bottom":"top";
return e.reference[r]>e.floating[r]&&(s=G(s)),[s,G(s)]
}function _t(t) {
const e=G(t);
return[st(t),e,st(e)]
}function st(t) {
return t.replace(/start|end/g,e=>jt[e])
}function qt(t,e,n) {
const o=["left","right"],i=["right","left"],r=["top","bottom"],s=["bottom","top"];
switch(t) {
case"top":case"bottom":return n?e?i:o:e?o:i;
case"left":case"right":return e?r:s;
default:return[]
}
}function zt(t,e,n,o) {
const i=Z(t);
let r=qt(F(t),n==="start",o);
return i&&(r=r.map(s=>s+"-"+i),e&&(r=r.concat(r.map(st)))),r
}function G(t) {
return t.replace(/left|right|bottom|top/g,e=>Wt[e])
}function Yt(t) {
return {
top:0,right:0,bottom:0,left:0,...t
}
}function Xt(t) {
return typeof t!="number"?Yt(t): {
top:t,right:t,bottom:t,left:t
}
}function K(t) {
const {
x:e,y:n,width:o,height:i
}=t;
return {
width:o,height:i,top:n,left:e,right:e+o,bottom:n+i,x:e,y:n
}
}function gt(t,e,n) {
let {
reference:o,floating:i
}=t;
const r=T(e),s=At(e),l=bt(s),c=F(e),a=r==="y",d=o.x+o.width/2-i.width/2,f=o.y+o.height/2-i.height/2,h=o[l]/2-i[l]/2;
let u;
switch(c) {
case"top":u= {
x:d,y:o.y-i.height
};
break;
case"bottom":u= {
x:d,y:o.y+o.height
};
break;
case"right":u= {
x:o.x+o.width,y:f
};
break;
case"left":u= {
x:o.x-i.width,y:f
};
break;
default:u= {
x:o.x,y:o.y
}
}switch(Z(e)) {
case"start":u[s]-=h*(n&&a?-1:1);
break;
case"end":u[s]+=h*(n&&a?-1:1);
break
}return u
}const Jt=async(t,e,n)=> {
const {
placement:o="bottom",strategy:i="absolute",middleware:r=[],platform:s
}=n,l=r.filter(Boolean),c=await(s.isRTL==null?void 0:s.isRTL(e));
let a=await s.getElementRects( {
reference:t,floating:e,strategy:i
}), {
x:d,y:f
}=gt(a,o,c),h=o,u= {
},m=0;
for(let g=0;
g<l.length;
g++) {
const {
name:w,fn:p
}=l[g], {
x:y,y:x,data:b,reset:v
}=await p( {
x:d,y:f,initialPlacement:o,placement:h,strategy:i,middlewareData:u,rects:a,platform:s,elements: {
reference:t,floating:e
}
});
d=y??d,f=x??f,u= {
...u,[w]: {
...u[w],...b
}
},v&&m<=50&&(m++,typeof v=="object"&&(v.placement&&(h=v.placement),v.rects&&(a=v.rects===!0?await s.getElementRects( {
reference:t,floating:e,strategy:i
}):v.rects), {
x:d,y:f
}=gt(a,h,c)),g=-1)
}return {
x:d,y:f,placement:h,strategy:i,middlewareData:u
}
};
async function Et(t,e) {
var n;
e===void 0&&(e= {
});
const {
x:o,y:i,platform:r,rects:s,elements:l,strategy:c
}=t, {
boundary:a="clippingAncestors",rootBoundary:d="viewport",elementContext:f="floating",altBoundary:h=!1,padding:u=0
}=Q(e,t),m=Xt(u),w=l[h?f==="floating"?"reference":"floating":f],p=K(await r.getClippingRect( {
element:(n=await(r.isElement==null?void 0:r.isElement(w)))==null||n?w:w.contextElement||await(r.getDocumentElement==null?void 0:r.getDocumentElement(l.floating)),boundary:a,rootBoundary:d,strategy:c
})),y=f==="floating"? {
x:o,y:i,width:s.floating.width,height:s.floating.height
}:s.reference,x=await(r.getOffsetParent==null?void 0:r.getOffsetParent(l.floating)),b=await(r.isElement==null?void 0:r.isElement(x))?await(r.getScale==null?void 0:r.getScale(x))|| {
x:1,y:1
}: {
x:1,y:1
},v=K(r.convertOffsetParentRelativeRectToViewportRelativeRect?await r.convertOffsetParentRelativeRectToViewportRelativeRect( {
elements:l,rect:y,offsetParent:x,strategy:c
}):y);
return {
top:(p.top-v.top+m.top)/b.y,bottom:(v.bottom-p.bottom+m.bottom)/b.y,left:(p.left-v.left+m.left)/b.x,right:(v.right-p.right+m.right)/b.x
}
}const Ut=function(t) {
return t===void 0&&(t= {
}), {
name:"flip",options:t,async fn(e) {
var n,o;
const {
placement:i,middlewareData:r,rects:s,initialPlacement:l,platform:c,elements:a
}=e, {
mainAxis:d=!0,crossAxis:f=!0,fallbackPlacements:h,fallbackStrategy:u="bestFit",fallbackAxisSideDirection:m="none",flipAlignment:g=!0,...w
}=Q(t,e);
if((n=r.arrow)!=null&&n.alignmentOffset)return {
};
const p=F(i),y=T(l),x=F(l)===l,b=await(c.isRTL==null?void 0:c.isRTL(a.floating)),v=h||(x||!g?[G(l)]:_t(l)),H=m!=="none";
!h&&H&&v.push(...zt(l,g,m,b));
const B=[l,...v],ot=await Et(e,w),Y=[];
let I=((o=r.flip)==null?void 0:o.overflows)||[];
if(d&&Y.push(ot[p]),f) {
const D=Ht(i,s,b);
Y.push(ot[D[0]],ot[D[1]])
}if(I=[...I, {
placement:i,overflows:Y
}],!Y.every(D=>D<=0)) {
var dt,ht;
const D=(((dt=r.flip)==null?void 0:dt.index)||0)+1,it=B[D];
if(it&&(!(f==="alignment"?y!==T(it):!1)||I.every(E=>E.overflows[0]>0&&T(E.placement)===y)))return {
data: {
index:D,overflows:I
},reset: {
placement:it
}
};
let _=(ht=I.filter($=>$.overflows[0]<=0).sort(($,E)=>$.overflows[1]-E.overflows[1])[0])==null?void 0:ht.placement;
if(!_)switch(u) {
case"bestFit": {
var mt;
const $=(mt=I.filter(E=> {
if(H) {
const k=T(E.placement);
return k===y||k==="y"
}return!0
}).map(E=>[E.placement,E.overflows.filter(k=>k>0).reduce((k,$t)=>k+$t,0)]).sort((E,k)=>E[1]-k[1])[0])==null?void 0:mt[0];
$&&(_=$);
break
}case"initialPlacement":_=l;
break
}if(i!==_)return {
reset: {
placement:_
}
}
}return {
}
}
}
};
async function Gt(t,e) {
const {
placement:n,platform:o,elements:i
}=t,r=await(o.isRTL==null?void 0:o.isRTL(i.floating)),s=F(n),l=Z(n),c=T(n)==="y",a=["left","top"].includes(s)?-1:1,d=r&&c?-1:1,f=Q(e,t);
let {
mainAxis:h,crossAxis:u,alignmentAxis:m
}=typeof f=="number"? {
mainAxis:f,crossAxis:0,alignmentAxis:null
}: {
mainAxis:f.mainAxis||0,crossAxis:f.crossAxis||0,alignmentAxis:f.alignmentAxis
};
return l&&typeof m=="number"&&(u=l==="end"?m*-1:m),c? {
x:u*d,y:h*a
}: {
x:h*a,y:u*d
}
}const Kt=function(t) {
return t===void 0&&(t=0), {
name:"offset",options:t,async fn(e) {
var n,o;
const {
x:i,y:r,placement:s,middlewareData:l
}=e,c=await Gt(e,t);
return s===((n=l.offset)==null?void 0:n.placement)&&(o=l.arrow)!=null&&o.alignmentOffset? {
}: {
x:i+c.x,y:r+c.y,data: {
...c,placement:s
}
}
}
}
},Qt=function(t) {
return t===void 0&&(t= {
}), {
name:"shift",options:t,async fn(e) {
const {
x:n,y:o,placement:i
}=e, {
mainAxis:r=!0,crossAxis:s=!1,limiter:l= {
fn:w=> {
let {
x:p,y
}=w;
return {
x:p,y
}
}
},...c
}=Q(t,e),a= {
x:n,y:o
},d=await Et(e,c),f=T(F(i)),h=vt(f);
let u=a[h],m=a[f];
if(r) {
const w=h==="y"?"top":"left",p=h==="y"?"bottom":"right",y=u+d[w],x=u-d[p];
u=pt(y,u,x)
}if(s) {
const w=f==="y"?"top":"left",p=f==="y"?"bottom":"right",y=m+d[w],x=m-d[p];
m=pt(y,m,x)
}const g=l.fn( {
...e,[h]:u,[f]:m
});
return {
...g,data: {
x:g.x-n,y:g.y-o,enabled: {
[h]:r,[f]:s
}
}
}
}
}
};
function tt() {
return typeof window<"u"
}function j(t) {
return Ot(t)?(t.nodeName||"").toLowerCase():"#document"
}function A(t) {
var e;
return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window
}function S(t) {
var e;
return(e=(Ot(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement
}function Ot(t) {
return tt()?t instanceof Node||t instanceof A(t).Node:!1
}function O(t) {
return tt()?t instanceof Element||t instanceof A(t).Element:!1
}function L(t) {
return tt()?t instanceof HTMLElement||t instanceof A(t).HTMLElement:!1
}function wt(t) {
return!tt()||typeof ShadowRoot>"u"?!1:t instanceof ShadowRoot||t instanceof A(t).ShadowRoot
}function z(t) {
const {
overflow:e,overflowX:n,overflowY:o,display:i
}=R(t);
return/auto|scroll|overlay|hidden|clip/.test(e+o+n)&&!["inline","contents"].includes(i)
}function Zt(t) {
return["table","td","th"].includes(j(t))
}function et(t) {
return[":popover-open",":modal"].some(e=> {
try {
return t.matches(e)
}catch {
return!1
}
})
}function ct(t) {
const e=at(),n=O(t)?R(t):t;
return["transform","translate","scale","rotate","perspective"].some(o=>n[o]?n[o]!=="none":!1)||(n.containerType?n.containerType!=="normal":!1)||!e&&(n.backdropFilter?n.backdropFilter!=="none":!1)||!e&&(n.filter?n.filter!=="none":!1)||["transform","translate","scale","rotate","perspective","filter"].some(o=>(n.willChange||"").includes(o))||["paint","layout","strict","content"].some(o=>(n.contain||"").includes(o))
}function te(t) {
let e=P(t);
for(;
L(e)&&!W(e);
) {
if(ct(e))return e;
if(et(e))return null;
e=P(e)
}return null
}function at() {
return typeof CSS>"u"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")
}function W(t) {
return["html","body","#document"].includes(j(t))
}function R(t) {
return A(t).getComputedStyle(t)
}function nt(t) {
return O(t)? {
scrollLeft:t.scrollLeft,scrollTop:t.scrollTop
}: {
scrollLeft:t.scrollX,scrollTop:t.scrollY
}
}function P(t) {
if(j(t)==="html")return t;
const e=t.assignedSlot||t.parentNode||wt(t)&&t.host||S(t);
return wt(e)?e.host:e
}function Rt(t) {
const e=P(t);
return W(e)?t.ownerDocument?t.ownerDocument.body:t.body:L(e)&&z(e)?e:Rt(e)
}function q(t,e,n) {
var o;
e===void 0&&(e=[]),n===void 0&&(n=!0);
const i=Rt(t),r=i===((o=t.ownerDocument)==null?void 0:o.body),s=A(i);
if(r) {
const l=lt(s);
return e.concat(s,s.visualViewport||[],z(i)?i:[],l&&n?q(l):[])
}return e.concat(i,q(i,[],n))
}function lt(t) {
return t.parent&&Object.getPrototypeOf(t.parent)?t.frameElement:null
}function Ct(t) {
const e=R(t);
let n=parseFloat(e.width)||0,o=parseFloat(e.height)||0;
const i=L(t),r=i?t.offsetWidth:n,s=i?t.offsetHeight:o,l=U(n)!==r||U(o)!==s;
return l&&(n=r,o=s), {
width:n,height:o,$:l
}
}function ft(t) {
return O(t)?t:t.contextElement
}function V(t) {
const e=ft(t);
if(!L(e))return C(1);
const n=e.getBoundingClientRect(), {
width:o,height:i,$:r
}=Ct(e);
let s=(r?U(n.width):n.width)/o,l=(r?U(n.height):n.height)/i;
return(!s||!Number.isFinite(s))&&(s=1),(!l||!Number.isFinite(l))&&(l=1), {
x:s,y:l
}
}const ee=C(0);
function Lt(t) {
const e=A(t);
return!at()||!e.visualViewport?ee: {
x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop
}
}function ne(t,e,n) {
return e===void 0&&(e=!1),!n||e&&n!==A(t)?!1:e
}function N(t,e,n,o) {
e===void 0&&(e=!1),n===void 0&&(n=!1);
const i=t.getBoundingClientRect(),r=ft(t);
let s=C(1);
e&&(o?O(o)&&(s=V(o)):s=V(t));
const l=ne(r,n,o)?Lt(r):C(0);
let c=(i.left+l.x)/s.x,a=(i.top+l.y)/s.y,d=i.width/s.x,f=i.height/s.y;
if(r) {
const h=A(r),u=o&&O(o)?A(o):o;
let m=h,g=lt(m);
for(;
g&&o&&u!==m;
) {
const w=V(g),p=g.getBoundingClientRect(),y=R(g),x=p.left+(g.clientLeft+parseFloat(y.paddingLeft))*w.x,b=p.top+(g.clientTop+parseFloat(y.paddingTop))*w.y;
c*=w.x,a*=w.y,d*=w.x,f*=w.y,c+=x,a+=b,m=A(g),g=lt(m)
}
}return K( {
width:d,height:f,x:c,y:a
})
}function ut(t,e) {
const n=nt(t).scrollLeft;
return e?e.left+n:N(S(t)).left+n
}function St(t,e,n) {
n===void 0&&(n=!1);
const o=t.getBoundingClientRect(),i=o.left+e.scrollLeft-(n?0:ut(t,o)),r=o.top+e.scrollTop;
return {
x:i,y:r
}
}function oe(t) {
let {
elements:e,rect:n,offsetParent:o,strategy:i
}=t;
const r=i==="fixed",s=S(o),l=e?et(e.floating):!1;
if(o===s||l&&r)return n;
let c= {
scrollLeft:0,scrollTop:0
},a=C(1);
const d=C(0),f=L(o);
if((f||!f&&!r)&&((j(o)!=="body"||z(s))&&(c=nt(o)),L(o))) {
const u=N(o);
a=V(o),d.x=u.x+o.clientLeft,d.y=u.y+o.clientTop
}const h=s&&!f&&!r?St(s,c,!0):C(0);
return {
width:n.width*a.x,height:n.height*a.y,x:n.x*a.x-c.scrollLeft*a.x+d.x+h.x,y:n.y*a.y-c.scrollTop*a.y+d.y+h.y
}
}function ie(t) {
return Array.from(t.getClientRects())
}function re(t) {
const e=S(t),n=nt(t),o=t.ownerDocument.body,i=M(e.scrollWidth,e.clientWidth,o.scrollWidth,o.clientWidth),r=M(e.scrollHeight,e.clientHeight,o.scrollHeight,o.clientHeight);
let s=-n.scrollLeft+ut(t);
const l=-n.scrollTop;
return R(o).direction==="rtl"&&(s+=M(e.clientWidth,o.clientWidth)-i), {
width:i,height:r,x:s,y:l
}
}function se(t,e) {
const n=A(t),o=S(t),i=n.visualViewport;
let r=o.clientWidth,s=o.clientHeight,l=0,c=0;
if(i) {
r=i.width,s=i.height;
const a=at();
(!a||a&&e==="fixed")&&(l=i.offsetLeft,c=i.offsetTop)
}return {
width:r,height:s,x:l,y:c
}
}function le(t,e) {
const n=N(t,!0,e==="fixed"),o=n.top+t.clientTop,i=n.left+t.clientLeft,r=L(t)?V(t):C(1),s=t.clientWidth*r.x,l=t.clientHeight*r.y,c=i*r.x,a=o*r.y;
return {
width:s,height:l,x:c,y:a
}
}function yt(t,e,n) {
let o;
if(e==="viewport")o=se(t,n);
else if(e==="document")o=re(S(t));
else if(O(e))o=le(e,n);
else {
const i=Lt(t);
o= {
x:e.x-i.x,y:e.y-i.y,width:e.width,height:e.height
}
}return K(o)
}function kt(t,e) {
const n=P(t);
return n===e||!O(n)||W(n)?!1:R(n).position==="fixed"||kt(n,e)
}function ce(t,e) {
const n=e.get(t);
if(n)return n;
let o=q(t,[],!1).filter(l=>O(l)&&j(l)!=="body"),i=null;
const r=R(t).position==="fixed";
let s=r?P(t):t;
for(;
O(s)&&!W(s);
) {
const l=R(s),c=ct(s);
!c&&l.position==="fixed"&&(i=null),(r?!c&&!i:!c&&l.position==="static"&&!!i&&["absolute","fixed"].includes(i.position)||z(s)&&!c&&kt(t,s))?o=o.filter(d=>d!==s):i=l,s=P(s)
}return e.set(t,o),o
}function ae(t) {
let {
element:e,boundary:n,rootBoundary:o,strategy:i
}=t;
const s=[...n==="clippingAncestors"?et(e)?[]:ce(e,this._c):[].concat(n),o],l=s[0],c=s.reduce((a,d)=> {
const f=yt(e,d,i);
return a.top=M(f.top,a.top),a.right=J(f.right,a.right),a.bottom=J(f.bottom,a.bottom),a.left=M(f.left,a.left),a
},yt(e,l,i));
return {
width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top
}
}function fe(t) {
const {
width:e,height:n
}=Ct(t);
return {
width:e,height:n
}
}function ue(t,e,n) {
const o=L(e),i=S(e),r=n==="fixed",s=N(t,!0,r,e);
let l= {
scrollLeft:0,scrollTop:0
};
const c=C(0);
function a() {
c.x=ut(i)
}if(o||!o&&!r)if((j(e)!=="body"||z(i))&&(l=nt(e)),o) {
const u=N(e,!0,r,e);
c.x=u.x+e.clientLeft,c.y=u.y+e.clientTop
}else i&&a();
r&&!o&&i&&a();
const d=i&&!o&&!r?St(i,l):C(0),f=s.left+l.scrollLeft-c.x-d.x,h=s.top+l.scrollTop-c.y-d.y;
return {
x:f,y:h,width:s.width,height:s.height
}
}function rt(t) {
return R(t).position==="static"
}function xt(t,e) {
if(!L(t)||R(t).position==="fixed")return null;
if(e)return e(t);
let n=t.offsetParent;
return S(t)===n&&(n=n.ownerDocument.body),n
}function Tt(t,e) {
const n=A(t);
if(et(t))return n;
if(!L(t)) {
let i=P(t);
for(;
i&&!W(i);
) {
if(O(i)&&!rt(i))return i;
i=P(i)
}return n
}let o=xt(t,e);
for(;
o&&Zt(o)&&rt(o);
)o=xt(o,e);
return o&&W(o)&&rt(o)&&!ct(o)?n:o||te(t)||n
}const de=async function(t) {
const e=this.getOffsetParent||Tt,n=this.getDimensions,o=await n(t.floating);
return {
reference:ue(t.reference,await e(t.floating),t.strategy),floating: {
x:0,y:0,width:o.width,height:o.height
}
}
};
function he(t) {
return R(t).direction==="rtl"
}const me= {
convertOffsetParentRelativeRectToViewportRelativeRect:oe,getDocumentElement:S,getClippingRect:ae,getOffsetParent:Tt,getElementRects:de,getClientRects:ie,getDimensions:fe,getScale:V,isElement:O,isRTL:he
};
function Pt(t,e) {
return t.x===e.x&&t.y===e.y&&t.width===e.width&&t.height===e.height
}function pe(t,e) {
let n=null,o;
const i=S(t);
function r() {
var l;
clearTimeout(o),(l=n)==null||l.disconnect(),n=null
}function s(l,c) {
l===void 0&&(l=!1),c===void 0&&(c=1),r();
const a=t.getBoundingClientRect(), {
left:d,top:f,width:h,height:u
}=a;
if(l||e(),!h||!u)return;
const m=X(f),g=X(i.clientWidth-(d+h)),w=X(i.clientHeight-(f+u)),p=X(d),x= {
rootMargin:-m+"px "+-g+"px "+-w+"px "+-p+"px",threshold:M(0,J(1,c))||1
};
let b=!0;
function v(H) {
const B=H[0].intersectionRatio;
if(B!==c) {
if(!b)return s();
B?s(!1,B):o=setTimeout(()=> {
s(!1,1e-7)
},1e3)
}B===1&&!Pt(a,t.getBoundingClientRect())&&s(),b=!1
}try {
n=new IntersectionObserver(v, {
...x,root:i.ownerDocument
})
}catch {
n=new IntersectionObserver(v,x)
}n.observe(t)
}return s(!0),r
}function ge(t,e,n,o) {
o===void 0&&(o= {
});
const {
ancestorScroll:i=!0,ancestorResize:r=!0,elementResize:s=typeof ResizeObserver=="function",layoutShift:l=typeof IntersectionObserver=="function",animationFrame:c=!1
}=o,a=ft(t),d=i||r?[...a?q(a):[],...q(e)]:[];
d.forEach(p=> {
i&&p.addEventListener("scroll",n, {
passive:!0
}),r&&p.addEventListener("resize",n)
});
const f=a&&l?pe(a,n):null;
let h=-1,u=null;
s&&(u=new ResizeObserver(p=> {
let[y]=p;
y&&y.target===a&&u&&(u.unobserve(e),cancelAnimationFrame(h),h=requestAnimationFrame(()=> {
var x;
(x=u)==null||x.observe(e)
})),n()
}),a&&!c&&u.observe(a),u.observe(e));
let m,g=c?N(t):null;
c&&w();
function w() {
const p=N(t);
g&&!Pt(g,p)&&n(),g=p,m=requestAnimationFrame(w)
}return n(),()=> {
var p;
d.forEach(y=> {
i&&y.removeEventListener("scroll",n),r&&y.removeEventListener("resize",n)
}),f==null||f(),(p=u)==null||p.disconnect(),u=null,c&&cancelAnimationFrame(m)
}
}const we=Kt,ye=Qt,xe=Ut,ve=(t,e,n)=> {
const o=new Map,i= {
platform:me,...n
},r= {
...i.platform,_c:o
};
return Jt(t,e, {
...i,platform:r
})
},be=(t,e,n= {
})=>(n= {
placement:"bottom-start",offset:2,...n
},ge(t,e,()=> {
ve(t,e, {
placement:n.placement,middleware:[we(n.offset),xe(),ye( {
padding:5
})]
}).then(( {
x:i,y:r
})=> {
Object.assign(e.style, {
left:i+"px",top:r+"px"
})
})
})),Dt= {
alert:Mt,toast:Ft,modal:Nt,empty:Bt,confirm:It,darkmode:Vt,floatingui:be,json:t=>JSON.stringify(t,null,2),random:()=>Math.random().toString(36).substring(2,15)+Math.random().toString(36).substring(2,15)
},Ae=t=>( {
name:t.name,scope:t.scope,dismissible:t.dismissible,closeable:t.closeable,showModal(e) {
this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)&&(this.$root.showModal(),this.$root.setAttribute("data-open",""),this.$dispatch("opened"))
},closeModal(e) {
(this.name===e.detail.name&&(this.scope===e.detail.scope||!e.detail.scope)||!e.detail.name&&this.$root.contains(e.target))&&(this.$root.close(),this.$root.removeAttribute("data-open"),this.$dispatch("closed"))
},backdropClick(e) {
if(!this.dismissible||e.target.tagName!=="DIALOG")return;
const n=e.target.getBoundingClientRect();
n.top<=e.clientY&&e.clientY<=n.top+n.height&&n.left<=e.clientX&&e.clientX<=n.left+n.width||this.closeModal(e)
}
}),Ee=t=>( {
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
}),Oe=t=>( {
cleanup:null,locked:t.locked,placement:t.placement,get trigger() {
return this.$el.querySelector("[data-atom-dropdown-trigger]")||this.$el.querySelector("button")
},get popover() {
return this.$el.querySelector("[data-atom-dropdown-popover]")||this.$el.querySelector("[data-atom-menu]")
},init() {
var e;
(e=this.trigger)==null||e.addEventListener("click",()=>this.open()),this.locked||document.addEventListener("click",n=> {
this.trigger.contains(n.target)||this.open(!1)
}),this.popover.hasAttribute("popover")||(this.popover.setAttribute("popover",""),this.$root.classList.remove("[:where(&_[data-atom-menu])]:hidden"))
},open(e=!0) {
var n;
e?(this.popover.showPopover(),this.$root.setAttribute("data-open",""),this.cleanup=atom.floatingui(this.trigger,this.popover, {
placement:this.placement
})):(this.popover.hidePopover(),this.$root.removeAttribute("data-open"),(n=this.cleanup)==null||n.call(this))
}
}),Re=t=>( {
trail:[],heading:t.heading,get breadcrumbs() {
let e=this.trail.slice().reverse().findIndex(o=>o.home);
if(e===-1)return[];
let n=this.trail.length-1-e;
return this.trail.slice(n)
},retrieve() {
var o;
let e=(o=document.body.querySelector("[data-atom-main] > *"))==null?void 0:o.getAttribute("wire:id");
if(!e)return;
let n=Livewire.find(e);
if(n)return n._breadcrumbs
},push(e) {
e.forEach(n=>this.trail.push( {
key:atom.random(),...n
}))
},build() {
let e=this.retrieve(),o=[ {
...e.home,home:!0
},...e.items].filter(Boolean),i=e.replace;
if(!this.trail.length)this.push(o);
else if(o.length) {
let r=o[o.length-1],s=this.trail.findIndex(l=>l.title===r.title&&l.url===r.url);
s===-1?this.push([r]):i?this.trail.splice(s,1,r):this.trail.splice(s+1)
}
}
});
document.addEventListener("alpine:init",()=> {
Alpine.data("modal",Ae),Alpine.data("tooltip",Ee),Alpine.data("dropdown",Oe),Alpine.data("breadcrumbs",Re)
});
window.dd=console.log.bind(console);
window.atom=Dt;
window.empty=Dt.empty;
