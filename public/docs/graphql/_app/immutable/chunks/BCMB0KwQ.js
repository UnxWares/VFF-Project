import{$ as e,B as t,D as n,Dt as r,F as i,G as a,H as o,K as s,L as c,M as l,N as u,P as d,Q as f,R as p,S as m,Y as h,Z as g,_,at as v,bt as y,ct as b,f as x,it as S,j as C,k as ee,kt as te,l as w,o as ne,ot as re,st as ie,ut as ae,w as oe,wt as se,x as ce,yt as T,z as le}from"./WhYXVPzS.js";import{i as ue}from"./BTcGeA1D.js";import"./v_jBEYI6.js";import"./CL7zaV3M.js";var de=t(`<pre><code> </code></pre>`);function fe(t,n){y(n,!1);let r=w(n,`token`,8);var i={options:void 0,renderers:void 0};x();var o=de(),s=S(o),l=S(s,!0);return se(s),se(o),e(()=>{oe(s,1,(a(r()),h(()=>`lang-${r().lang}`))),c(l,(a(r()),h(()=>r().text)))}),p(t,o),_(n,`options`,void 0),_(n,`renderers`,void 0),T(i)}var pe=t(`<code> </code>`);function me(t,n){y(n,!1);let r=w(n,`token`,8);var i={options:void 0,renderers:void 0};x();var o=pe(),s=S(o,!0);return se(o),e(e=>c(s,e),[()=>(a(r()),h(()=>r().raw.slice(1,r().raw.length-1)))]),p(t,o),_(n,`options`,void 0),_(n,`renderers`,void 0),T(i)}function he(e,t){let n=w(t,`tokens`,8),r=w(t,`renderers`,8),a=w(t,`options`,8);var o=le(),c=v(o),l=e=>{var t=le();u(v(t),1,n,d,(e,t)=>{ge(e,{get token(){return s(t)},get renderers(){return r()},get options(){return a()}})}),p(e,t)};i(c,e=>{n()&&e(l)}),p(e,o)}function ge(t,n){y(n,!1);let r=w(n,`token`,8),s=w(n,`renderers`,8),l=w(n,`options`,8);x();var u=le(),d=v(u),f=t=>{var n=le();ee(v(n),()=>s()[r().type],(t,n)=>{n(t,{get token(){return r()},get options(){return l()},get renderers(){return s()},children:(t,n)=>{var u=le(),d=v(u),f=e=>{he(e,{get tokens(){return a(r()),h(()=>r().tokens)},get renderers(){return s()},get options(){return l()}})},m=t=>{var n=o();e(()=>c(n,(a(r()),h(()=>r().raw)))),p(t,n)};i(d,e=>{a(r()),h(()=>`tokens`in r()&&r().tokens)?e(f):e(m,-1)}),p(t,u)},$$slots:{default:!0}})}),p(t,n)};i(d,e=>{a(s()),a(r()),h(()=>s()[r().type])&&e(f)}),p(t,u),T()}function _e(e,t){y(t,!1);let r=w(t,`token`,8),i=w(t,`options`,8),o=w(t,`renderers`,8),c=ie();g(()=>a(r()),()=>{b(c,r().ordered?`ol`:`ul`)}),f(),x();var l=le();n(v(l),()=>s(c),!1,(e,t)=>{ce(e,()=>({start:(a(r()),h(()=>r().start||1))}));var n=le();u(v(n),1,()=>(a(r()),h(()=>r().items)),d,(e,t)=>{{let n=ae(()=>(s(t),h(()=>({...s(t)}))));ge(e,{get token(){return s(n)},get options(){return i()},get renderers(){return o()}})}}),p(t,n)}),p(e,l),T()}var ve=t(`<li><!></li>`);function ye(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=ve();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var be=t(`<blockquote><!></blockquote>`);function xe(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=be();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var Se=t(`<br/>`);function Ce(e,t){return y(t,!1),p(e,Se()),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T({token:void 0,options:void 0,renderers:void 0})}var we=t(`<del><!></del>`);function Te(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=we();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var Ee=t(`<dfn><!></dfn>`);function De(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=Ee();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var Oe=t(`<em><!></em>`);function ke(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=Oe();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}function Ae(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=le();return C(v(r),t,`default`,{},null),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}function je(...e){return`/${e.flatMap(e=>e.split(`/`)).filter(e=>!!e).join(`/`)}`}function Me(e){return e.startsWith(`/`)||e.startsWith(`#`)}function Ne(e,t){return t.slug(e).replace(/--+/g,`-`)}function Pe(e,t){y(t,!1);let r=w(t,`token`,8),i=w(t,`options`,8),o=ie();g(()=>(a(r()),a(i())),()=>{b(o,Ne(r().text,i().slugger))}),f();var c={renderers:void 0};x();var l=le();return n(v(l),()=>`h${r().depth}`,!1,(e,n)=>{ce(e,()=>({id:s(o)}));var r=le();C(v(r),t,`default`,{},null),p(n,r)}),p(e,l),_(t,`renderers`,void 0),T(c)}var Fe=t(`<hr/>`);function Ie(e,t){return y(t,!1),p(e,Fe()),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T({token:void 0,options:void 0,renderers:void 0})}function Le(e,t){y(t,!1);let n=w(t,`token`,8);var r={options:void 0,renderers:void 0};x();var i=le();return l(v(i),()=>(a(n()),h(()=>n().text))),p(e,i),_(t,`options`,void 0),_(t,`renderers`,void 0),T(r)}var Re=t(`<img class="markdown-image svelte-n6q0or"/>`);function ze(t,n){y(n,!1);let r=w(n,`token`,8),i=w(n,`options`,8);var o={renderers:void 0};x();var s=Re();return e(e=>{m(s,`src`,e),m(s,`title`,(a(r()),h(()=>r().title))),m(s,`alt`,(a(r()),h(()=>r().text)))},[()=>(a(Me),a(r()),a(je),a(i()),h(()=>Me(r().href)?je(i().baseUrl,r().href):r().href))]),p(t,s),_(n,`renderers`,void 0),T(o)}var Be=t(`<a><!></a>`);function Ve(t,n){y(n,!1);let r=w(n,`token`,8),i=w(n,`options`,8);var o={renderers:void 0};x();var s=Be();return C(S(s),n,`default`,{},null),se(s),e(e=>{m(s,`href`,e),m(s,`title`,(a(r()),h(()=>r().title)))},[()=>(a(Me),a(r()),a(je),a(i()),h(()=>Me(r().href)?je(i().baseUrl,r().href):r().href))]),p(t,s),_(n,`renderers`,void 0),T(o)}var He=t(`<div><!></div>`);function Ue(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=He();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}function We(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=le();return C(v(r),t,`default`,{},null),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var Ge=t(`<strong><!></strong>`);function Ke(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=Ge();return C(S(r),t,`default`,{},null),se(r),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}function qe(e,t){y(t,!1);var n={token:void 0,options:void 0,renderers:void 0},r=le();return C(v(r),t,`default`,{},null),p(e,r),_(t,`token`,void 0),_(t,`options`,void 0),_(t,`renderers`,void 0),T(n)}var Je=t(`<th scope="col"><!></th>`),Ye=t(`<td><!></td>`),Xe=t(`<tr></tr>`),Ze=t(`<table><thead><tr></tr></thead><tbody></tbody></table>`);function Qe(e,t){y(t,!1);let n=w(t,`token`,8),r=w(t,`options`,8),i=w(t,`renderers`,8);x();var o=Ze(),c=S(o),l=S(c);u(l,5,()=>(a(n()),h(()=>n().header)),d,(e,t)=>{var n=Je();he(S(n),{get tokens(){return s(t),h(()=>s(t).tokens)},get options(){return r()},get renderers(){return i()}}),se(n),p(e,n)}),se(l),se(c);var f=re(c);u(f,5,()=>(a(n()),h(()=>n().rows)),d,(e,t)=>{var n=Xe();u(n,5,()=>s(t),d,(e,t)=>{var n=Ye();he(S(n),{get tokens(){return s(t),h(()=>s(t).tokens)},get options(){return r()},get renderers(){return i()}}),se(n),p(e,n)}),se(n),p(e,n)}),se(f),se(o),p(e,o),T()}function $e(e){return{name:`container`,level:`block`,start(e){return e.match(/^:::[^:\n]/)?.index},tokenizer(t){let n=/^:::[\s]?(?<type>[a-z0-9-]+)(?<options>.*)?\n(?<content>(?:.|\n)*)\n:::(?:\n|$)/i,r=et(t);if(!r)return;let i=n.exec(r);if(!i?.groups)return;let a=i.groups.type.toLocaleLowerCase(),o=tt(i.groups.options||``),s=i.groups.content||``,c=e({type:a,content:s,raw:i[0],options:o,lexer:this.lexer});return c?.tokens&&this.lexer.blockTokens(s,c.tokens),c}}}function et(e){if(!e.startsWith(`:::`))return;let t=e.split(`
`),n=1,r=1;for(r=1;r<t.length;r++){let e=t[r];if(e.startsWith(`:::`)&&(/:::[^:\n\s]/.test(e)?n++:/^:::(\n|$)/.test(e)&&--n),n===0)break}return t.slice(0,r+1).join(`
`)}function tt(e){let t={},n=e;for(;;){let e=/(?<name>[a-z0-9]+)(?:="(?<value>(?:.*?))")?/i.exec(n);if(!e)break;let r=e?.groups?.name;r&&(t[r]=e?.groups?.value??!0),n=n.slice(e.index+e[0].length)}return t}var nt={containerExtension:$e},rt=/[\0-\x1F!-,\.\/:-@\[-\^`\{-\xA9\xAB-\xB4\xB6-\xB9\xBB-\xBF\xD7\xF7\u02C2-\u02C5\u02D2-\u02DF\u02E5-\u02EB\u02ED\u02EF-\u02FF\u0375\u0378\u0379\u037E\u0380-\u0385\u0387\u038B\u038D\u03A2\u03F6\u0482\u0530\u0557\u0558\u055A-\u055F\u0589-\u0590\u05BE\u05C0\u05C3\u05C6\u05C8-\u05CF\u05EB-\u05EE\u05F3-\u060F\u061B-\u061F\u066A-\u066D\u06D4\u06DD\u06DE\u06E9\u06FD\u06FE\u0700-\u070F\u074B\u074C\u07B2-\u07BF\u07F6-\u07F9\u07FB\u07FC\u07FE\u07FF\u082E-\u083F\u085C-\u085F\u086B-\u089F\u08B5\u08C8-\u08D2\u08E2\u0964\u0965\u0970\u0984\u098D\u098E\u0991\u0992\u09A9\u09B1\u09B3-\u09B5\u09BA\u09BB\u09C5\u09C6\u09C9\u09CA\u09CF-\u09D6\u09D8-\u09DB\u09DE\u09E4\u09E5\u09F2-\u09FB\u09FD\u09FF\u0A00\u0A04\u0A0B-\u0A0E\u0A11\u0A12\u0A29\u0A31\u0A34\u0A37\u0A3A\u0A3B\u0A3D\u0A43-\u0A46\u0A49\u0A4A\u0A4E-\u0A50\u0A52-\u0A58\u0A5D\u0A5F-\u0A65\u0A76-\u0A80\u0A84\u0A8E\u0A92\u0AA9\u0AB1\u0AB4\u0ABA\u0ABB\u0AC6\u0ACA\u0ACE\u0ACF\u0AD1-\u0ADF\u0AE4\u0AE5\u0AF0-\u0AF8\u0B00\u0B04\u0B0D\u0B0E\u0B11\u0B12\u0B29\u0B31\u0B34\u0B3A\u0B3B\u0B45\u0B46\u0B49\u0B4A\u0B4E-\u0B54\u0B58-\u0B5B\u0B5E\u0B64\u0B65\u0B70\u0B72-\u0B81\u0B84\u0B8B-\u0B8D\u0B91\u0B96-\u0B98\u0B9B\u0B9D\u0BA0-\u0BA2\u0BA5-\u0BA7\u0BAB-\u0BAD\u0BBA-\u0BBD\u0BC3-\u0BC5\u0BC9\u0BCE\u0BCF\u0BD1-\u0BD6\u0BD8-\u0BE5\u0BF0-\u0BFF\u0C0D\u0C11\u0C29\u0C3A-\u0C3C\u0C45\u0C49\u0C4E-\u0C54\u0C57\u0C5B-\u0C5F\u0C64\u0C65\u0C70-\u0C7F\u0C84\u0C8D\u0C91\u0CA9\u0CB4\u0CBA\u0CBB\u0CC5\u0CC9\u0CCE-\u0CD4\u0CD7-\u0CDD\u0CDF\u0CE4\u0CE5\u0CF0\u0CF3-\u0CFF\u0D0D\u0D11\u0D45\u0D49\u0D4F-\u0D53\u0D58-\u0D5E\u0D64\u0D65\u0D70-\u0D79\u0D80\u0D84\u0D97-\u0D99\u0DB2\u0DBC\u0DBE\u0DBF\u0DC7-\u0DC9\u0DCB-\u0DCE\u0DD5\u0DD7\u0DE0-\u0DE5\u0DF0\u0DF1\u0DF4-\u0E00\u0E3B-\u0E3F\u0E4F\u0E5A-\u0E80\u0E83\u0E85\u0E8B\u0EA4\u0EA6\u0EBE\u0EBF\u0EC5\u0EC7\u0ECE\u0ECF\u0EDA\u0EDB\u0EE0-\u0EFF\u0F01-\u0F17\u0F1A-\u0F1F\u0F2A-\u0F34\u0F36\u0F38\u0F3A-\u0F3D\u0F48\u0F6D-\u0F70\u0F85\u0F98\u0FBD-\u0FC5\u0FC7-\u0FFF\u104A-\u104F\u109E\u109F\u10C6\u10C8-\u10CC\u10CE\u10CF\u10FB\u1249\u124E\u124F\u1257\u1259\u125E\u125F\u1289\u128E\u128F\u12B1\u12B6\u12B7\u12BF\u12C1\u12C6\u12C7\u12D7\u1311\u1316\u1317\u135B\u135C\u1360-\u137F\u1390-\u139F\u13F6\u13F7\u13FE-\u1400\u166D\u166E\u1680\u169B-\u169F\u16EB-\u16ED\u16F9-\u16FF\u170D\u1715-\u171F\u1735-\u173F\u1754-\u175F\u176D\u1771\u1774-\u177F\u17D4-\u17D6\u17D8-\u17DB\u17DE\u17DF\u17EA-\u180A\u180E\u180F\u181A-\u181F\u1879-\u187F\u18AB-\u18AF\u18F6-\u18FF\u191F\u192C-\u192F\u193C-\u1945\u196E\u196F\u1975-\u197F\u19AC-\u19AF\u19CA-\u19CF\u19DA-\u19FF\u1A1C-\u1A1F\u1A5F\u1A7D\u1A7E\u1A8A-\u1A8F\u1A9A-\u1AA6\u1AA8-\u1AAF\u1AC1-\u1AFF\u1B4C-\u1B4F\u1B5A-\u1B6A\u1B74-\u1B7F\u1BF4-\u1BFF\u1C38-\u1C3F\u1C4A-\u1C4C\u1C7E\u1C7F\u1C89-\u1C8F\u1CBB\u1CBC\u1CC0-\u1CCF\u1CD3\u1CFB-\u1CFF\u1DFA\u1F16\u1F17\u1F1E\u1F1F\u1F46\u1F47\u1F4E\u1F4F\u1F58\u1F5A\u1F5C\u1F5E\u1F7E\u1F7F\u1FB5\u1FBD\u1FBF-\u1FC1\u1FC5\u1FCD-\u1FCF\u1FD4\u1FD5\u1FDC-\u1FDF\u1FED-\u1FF1\u1FF5\u1FFD-\u203E\u2041-\u2053\u2055-\u2070\u2072-\u207E\u2080-\u208F\u209D-\u20CF\u20F1-\u2101\u2103-\u2106\u2108\u2109\u2114\u2116-\u2118\u211E-\u2123\u2125\u2127\u2129\u212E\u213A\u213B\u2140-\u2144\u214A-\u214D\u214F-\u215F\u2189-\u24B5\u24EA-\u2BFF\u2C2F\u2C5F\u2CE5-\u2CEA\u2CF4-\u2CFF\u2D26\u2D28-\u2D2C\u2D2E\u2D2F\u2D68-\u2D6E\u2D70-\u2D7E\u2D97-\u2D9F\u2DA7\u2DAF\u2DB7\u2DBF\u2DC7\u2DCF\u2DD7\u2DDF\u2E00-\u2E2E\u2E30-\u3004\u3008-\u3020\u3030\u3036\u3037\u303D-\u3040\u3097\u3098\u309B\u309C\u30A0\u30FB\u3100-\u3104\u3130\u318F-\u319F\u31C0-\u31EF\u3200-\u33FF\u4DC0-\u4DFF\u9FFD-\u9FFF\uA48D-\uA4CF\uA4FE\uA4FF\uA60D-\uA60F\uA62C-\uA63F\uA673\uA67E\uA6F2-\uA716\uA720\uA721\uA789\uA78A\uA7C0\uA7C1\uA7CB-\uA7F4\uA828-\uA82B\uA82D-\uA83F\uA874-\uA87F\uA8C6-\uA8CF\uA8DA-\uA8DF\uA8F8-\uA8FA\uA8FC\uA92E\uA92F\uA954-\uA95F\uA97D-\uA97F\uA9C1-\uA9CE\uA9DA-\uA9DF\uA9FF\uAA37-\uAA3F\uAA4E\uAA4F\uAA5A-\uAA5F\uAA77-\uAA79\uAAC3-\uAADA\uAADE\uAADF\uAAF0\uAAF1\uAAF7-\uAB00\uAB07\uAB08\uAB0F\uAB10\uAB17-\uAB1F\uAB27\uAB2F\uAB5B\uAB6A-\uAB6F\uABEB\uABEE\uABEF\uABFA-\uABFF\uD7A4-\uD7AF\uD7C7-\uD7CA\uD7FC-\uD7FF\uE000-\uF8FF\uFA6E\uFA6F\uFADA-\uFAFF\uFB07-\uFB12\uFB18-\uFB1C\uFB29\uFB37\uFB3D\uFB3F\uFB42\uFB45\uFBB2-\uFBD2\uFD3E-\uFD4F\uFD90\uFD91\uFDC8-\uFDEF\uFDFC-\uFDFF\uFE10-\uFE1F\uFE30-\uFE32\uFE35-\uFE4C\uFE50-\uFE6F\uFE75\uFEFD-\uFF0F\uFF1A-\uFF20\uFF3B-\uFF3E\uFF40\uFF5B-\uFF65\uFFBF-\uFFC1\uFFC8\uFFC9\uFFD0\uFFD1\uFFD8\uFFD9\uFFDD-\uFFFF]|\uD800[\uDC0C\uDC27\uDC3B\uDC3E\uDC4E\uDC4F\uDC5E-\uDC7F\uDCFB-\uDD3F\uDD75-\uDDFC\uDDFE-\uDE7F\uDE9D-\uDE9F\uDED1-\uDEDF\uDEE1-\uDEFF\uDF20-\uDF2C\uDF4B-\uDF4F\uDF7B-\uDF7F\uDF9E\uDF9F\uDFC4-\uDFC7\uDFD0\uDFD6-\uDFFF]|\uD801[\uDC9E\uDC9F\uDCAA-\uDCAF\uDCD4-\uDCD7\uDCFC-\uDCFF\uDD28-\uDD2F\uDD64-\uDDFF\uDF37-\uDF3F\uDF56-\uDF5F\uDF68-\uDFFF]|\uD802[\uDC06\uDC07\uDC09\uDC36\uDC39-\uDC3B\uDC3D\uDC3E\uDC56-\uDC5F\uDC77-\uDC7F\uDC9F-\uDCDF\uDCF3\uDCF6-\uDCFF\uDD16-\uDD1F\uDD3A-\uDD7F\uDDB8-\uDDBD\uDDC0-\uDDFF\uDE04\uDE07-\uDE0B\uDE14\uDE18\uDE36\uDE37\uDE3B-\uDE3E\uDE40-\uDE5F\uDE7D-\uDE7F\uDE9D-\uDEBF\uDEC8\uDEE7-\uDEFF\uDF36-\uDF3F\uDF56-\uDF5F\uDF73-\uDF7F\uDF92-\uDFFF]|\uD803[\uDC49-\uDC7F\uDCB3-\uDCBF\uDCF3-\uDCFF\uDD28-\uDD2F\uDD3A-\uDE7F\uDEAA\uDEAD-\uDEAF\uDEB2-\uDEFF\uDF1D-\uDF26\uDF28-\uDF2F\uDF51-\uDFAF\uDFC5-\uDFDF\uDFF7-\uDFFF]|\uD804[\uDC47-\uDC65\uDC70-\uDC7E\uDCBB-\uDCCF\uDCE9-\uDCEF\uDCFA-\uDCFF\uDD35\uDD40-\uDD43\uDD48-\uDD4F\uDD74\uDD75\uDD77-\uDD7F\uDDC5-\uDDC8\uDDCD\uDDDB\uDDDD-\uDDFF\uDE12\uDE38-\uDE3D\uDE3F-\uDE7F\uDE87\uDE89\uDE8E\uDE9E\uDEA9-\uDEAF\uDEEB-\uDEEF\uDEFA-\uDEFF\uDF04\uDF0D\uDF0E\uDF11\uDF12\uDF29\uDF31\uDF34\uDF3A\uDF45\uDF46\uDF49\uDF4A\uDF4E\uDF4F\uDF51-\uDF56\uDF58-\uDF5C\uDF64\uDF65\uDF6D-\uDF6F\uDF75-\uDFFF]|\uD805[\uDC4B-\uDC4F\uDC5A-\uDC5D\uDC62-\uDC7F\uDCC6\uDCC8-\uDCCF\uDCDA-\uDD7F\uDDB6\uDDB7\uDDC1-\uDDD7\uDDDE-\uDDFF\uDE41-\uDE43\uDE45-\uDE4F\uDE5A-\uDE7F\uDEB9-\uDEBF\uDECA-\uDEFF\uDF1B\uDF1C\uDF2C-\uDF2F\uDF3A-\uDFFF]|\uD806[\uDC3B-\uDC9F\uDCEA-\uDCFE\uDD07\uDD08\uDD0A\uDD0B\uDD14\uDD17\uDD36\uDD39\uDD3A\uDD44-\uDD4F\uDD5A-\uDD9F\uDDA8\uDDA9\uDDD8\uDDD9\uDDE2\uDDE5-\uDDFF\uDE3F-\uDE46\uDE48-\uDE4F\uDE9A-\uDE9C\uDE9E-\uDEBF\uDEF9-\uDFFF]|\uD807[\uDC09\uDC37\uDC41-\uDC4F\uDC5A-\uDC71\uDC90\uDC91\uDCA8\uDCB7-\uDCFF\uDD07\uDD0A\uDD37-\uDD39\uDD3B\uDD3E\uDD48-\uDD4F\uDD5A-\uDD5F\uDD66\uDD69\uDD8F\uDD92\uDD99-\uDD9F\uDDAA-\uDEDF\uDEF7-\uDFAF\uDFB1-\uDFFF]|\uD808[\uDF9A-\uDFFF]|\uD809[\uDC6F-\uDC7F\uDD44-\uDFFF]|[\uD80A\uD80B\uD80E-\uD810\uD812-\uD819\uD824-\uD82B\uD82D\uD82E\uD830-\uD833\uD837\uD839\uD83D\uD83F\uD87B-\uD87D\uD87F\uD885-\uDB3F\uDB41-\uDBFF][\uDC00-\uDFFF]|\uD80D[\uDC2F-\uDFFF]|\uD811[\uDE47-\uDFFF]|\uD81A[\uDE39-\uDE3F\uDE5F\uDE6A-\uDECF\uDEEE\uDEEF\uDEF5-\uDEFF\uDF37-\uDF3F\uDF44-\uDF4F\uDF5A-\uDF62\uDF78-\uDF7C\uDF90-\uDFFF]|\uD81B[\uDC00-\uDE3F\uDE80-\uDEFF\uDF4B-\uDF4E\uDF88-\uDF8E\uDFA0-\uDFDF\uDFE2\uDFE5-\uDFEF\uDFF2-\uDFFF]|\uD821[\uDFF8-\uDFFF]|\uD823[\uDCD6-\uDCFF\uDD09-\uDFFF]|\uD82C[\uDD1F-\uDD4F\uDD53-\uDD63\uDD68-\uDD6F\uDEFC-\uDFFF]|\uD82F[\uDC6B-\uDC6F\uDC7D-\uDC7F\uDC89-\uDC8F\uDC9A-\uDC9C\uDC9F-\uDFFF]|\uD834[\uDC00-\uDD64\uDD6A-\uDD6C\uDD73-\uDD7A\uDD83\uDD84\uDD8C-\uDDA9\uDDAE-\uDE41\uDE45-\uDFFF]|\uD835[\uDC55\uDC9D\uDCA0\uDCA1\uDCA3\uDCA4\uDCA7\uDCA8\uDCAD\uDCBA\uDCBC\uDCC4\uDD06\uDD0B\uDD0C\uDD15\uDD1D\uDD3A\uDD3F\uDD45\uDD47-\uDD49\uDD51\uDEA6\uDEA7\uDEC1\uDEDB\uDEFB\uDF15\uDF35\uDF4F\uDF6F\uDF89\uDFA9\uDFC3\uDFCC\uDFCD]|\uD836[\uDC00-\uDDFF\uDE37-\uDE3A\uDE6D-\uDE74\uDE76-\uDE83\uDE85-\uDE9A\uDEA0\uDEB0-\uDFFF]|\uD838[\uDC07\uDC19\uDC1A\uDC22\uDC25\uDC2B-\uDCFF\uDD2D-\uDD2F\uDD3E\uDD3F\uDD4A-\uDD4D\uDD4F-\uDEBF\uDEFA-\uDFFF]|\uD83A[\uDCC5-\uDCCF\uDCD7-\uDCFF\uDD4C-\uDD4F\uDD5A-\uDFFF]|\uD83B[\uDC00-\uDDFF\uDE04\uDE20\uDE23\uDE25\uDE26\uDE28\uDE33\uDE38\uDE3A\uDE3C-\uDE41\uDE43-\uDE46\uDE48\uDE4A\uDE4C\uDE50\uDE53\uDE55\uDE56\uDE58\uDE5A\uDE5C\uDE5E\uDE60\uDE63\uDE65\uDE66\uDE6B\uDE73\uDE78\uDE7D\uDE7F\uDE8A\uDE9C-\uDEA0\uDEA4\uDEAA\uDEBC-\uDFFF]|\uD83C[\uDC00-\uDD2F\uDD4A-\uDD4F\uDD6A-\uDD6F\uDD8A-\uDFFF]|\uD83E[\uDC00-\uDFEF\uDFFA-\uDFFF]|\uD869[\uDEDE-\uDEFF]|\uD86D[\uDF35-\uDF3F]|\uD86E[\uDC1E\uDC1F]|\uD873[\uDEA2-\uDEAF]|\uD87A[\uDFE1-\uDFFF]|\uD87E[\uDE1E-\uDFFF]|\uD884[\uDF4B-\uDFFF]|\uDB40[\uDC00-\uDCFF\uDDF0-\uDFFF]/g,it=Object.hasOwnProperty,at=class{constructor(){this.occurrences,this.reset()}slug(e,t){let n=this,r=ot(e,t===!0),i=r;for(;it.call(n.occurrences,r);)n.occurrences[i]++,r=i+`-`+n.occurrences[i];return n.occurrences[r]=0,r}reset(){this.occurrences=Object.create(null)}};function ot(e,t){return typeof e==`string`?(t||(e=e.toLowerCase()),e.replace(rt,``).replace(/ /g,`-`)):``}function st(){return{async:!1,breaks:!1,extensions:null,gfm:!0,hooks:null,pedantic:!1,renderer:null,silent:!1,tokenizer:null,walkTokens:null}}var ct=st();function lt(e){ct=e}var ut={exec:()=>null};function E(e,t=``){let n=typeof e==`string`?e:e.source,r={replace:(e,t)=>{let i=typeof t==`string`?t:t.source;return i=i.replace(ft.caret,`$1`),n=n.replace(e,i),r},getRegex:()=>new RegExp(n,t)};return r}var dt=((e=``)=>{try{return!!RegExp(`(?<=1)(?<!1)`+e)}catch{return!1}})(),ft={codeRemoveIndent:/^(?: {1,4}| {0,3}\t)/gm,outputLinkReplace:/\\([\[\]])/g,indentCodeCompensation:/^(\s+)(?:```)/,beginningSpace:/^\s+/,endingHash:/#$/,startingSpaceChar:/^ /,endingSpaceChar:/ $/,nonSpaceChar:/[^ ]/,newLineCharGlobal:/\n/g,tabCharGlobal:/\t/g,multipleSpaceGlobal:/\s+/g,blankLine:/^[ \t]*$/,doubleBlankLine:/\n[ \t]*\n[ \t]*$/,blockquoteStart:/^ {0,3}>/,blockquoteSetextReplace:/\n {0,3}((?:=+|-+) *)(?=\n|$)/g,blockquoteSetextReplace2:/^ {0,3}>[ \t]?/gm,listReplaceNesting:/^ {1,4}(?=( {4})*[^ ])/g,listIsTask:/^\[[ xX]\] +\S/,listReplaceTask:/^\[[ xX]\] +/,listTaskCheckbox:/\[[ xX]\]/,anyLine:/\n.*\n/,hrefBrackets:/^<(.*)>$/,tableDelimiter:/[:|]/,tableAlignChars:/^\||\| *$/g,tableRowBlankLine:/\n[ \t]*$/,tableAlignRight:/^ *-+: *$/,tableAlignCenter:/^ *:-+: *$/,tableAlignLeft:/^ *:-+ *$/,startATag:/^<a /i,endATag:/^<\/a>/i,startPreScriptTag:/^<(pre|code|kbd|script)(\s|>)/i,endPreScriptTag:/^<\/(pre|code|kbd|script)(\s|>)/i,startAngleBracket:/^</,endAngleBracket:/>$/,pedanticHrefTitle:/^([^'"]*[^\s])\s+(['"])(.*)\2/,unicodeAlphaNumeric:/[\p{L}\p{N}]/u,escapeTest:/[&<>"']/,escapeReplace:/[&<>"']/g,escapeTestNoEncode:/[<>"']|&(?!(#\d{1,7}|#[Xx][a-fA-F0-9]{1,6}|\w+);)/,escapeReplaceNoEncode:/[<>"']|&(?!(#\d{1,7}|#[Xx][a-fA-F0-9]{1,6}|\w+);)/g,caret:/(^|[^\[])\^/g,percentDecode:/%25/g,findPipe:/\|/g,splitPipe:/ \|/,slashPipe:/\\\|/g,carriageReturn:/\r\n|\r/g,spaceLine:/^ +$/gm,notSpaceStart:/^\S*/,endingNewline:/\n$/,listItemRegex:e=>RegExp(`^( {0,3}${e})((?:[	 ][^\\n]*)?(?:\\n|$))`),nextBulletRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}(?:[*+-]|\\d{1,9}[.)])((?:[ 	][^\\n]*)?(?:\\n|$))`),hrRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}((?:- *){3,}|(?:_ *){3,}|(?:\\* *){3,})(?:\\n+|$)`),fencesBeginRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}(?:\`\`\`|~~~)`),headingBeginRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}#`),htmlBeginRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}<(?:[a-z].*>|!--)`,`i`),blockquoteBeginRegex:e=>RegExp(`^ {0,${Math.min(3,e-1)}}>`)},pt=/^(?:[ \t]*(?:\n|$))+/,mt=/^((?: {4}| {0,3}\t)[^\n]+(?:\n(?:[ \t]*(?:\n|$))*)?)+/,ht=/^ {0,3}(`{3,}(?=[^`\n]*(?:\n|$))|~{3,})([^\n]*)(?:\n|$)(?:|([\s\S]*?)(?:\n|$))(?: {0,3}\1[~`]* *(?=\n|$)|$)/,gt=/^ {0,3}((?:-[\t ]*){3,}|(?:_[ \t]*){3,}|(?:\*[ \t]*){3,})(?:\n+|$)/,_t=/^ {0,3}(#{1,6})(?=\s|$)(.*)(?:\n+|$)/,vt=/ {0,3}(?:[*+-]|\d{1,9}[.)])/,yt=/^(?!bull |blockCode|fences|blockquote|heading|html|table)((?:.|\n(?!\s*?\n|bull |blockCode|fences|blockquote|heading|html|table))+?)\n {0,3}(=+|-+) *(?:\n+|$)/,bt=E(yt).replace(/bull/g,vt).replace(/blockCode/g,/(?: {4}| {0,3}\t)/).replace(/fences/g,/ {0,3}(?:`{3,}|~{3,})/).replace(/blockquote/g,/ {0,3}>/).replace(/heading/g,/ {0,3}#{1,6}/).replace(/html/g,/ {0,3}<[^\n>]+>\n/).replace(/\|table/g,``).getRegex(),xt=E(yt).replace(/bull/g,vt).replace(/blockCode/g,/(?: {4}| {0,3}\t)/).replace(/fences/g,/ {0,3}(?:`{3,}|~{3,})/).replace(/blockquote/g,/ {0,3}>/).replace(/heading/g,/ {0,3}#{1,6}/).replace(/html/g,/ {0,3}<[^\n>]+>\n/).replace(/table/g,/ {0,3}\|?(?:[:\- ]*\|)+[\:\- ]*\n/).getRegex(),St=/^([^\n]+(?:\n(?!hr|heading|lheading|blockquote|fences|list|html|table| +\n)[^\n]+)*)/,Ct=/^[^\n]+/,wt=/(?!\s*\])(?:\\[\s\S]|[^\[\]\\])+/,Tt=E(/^ {0,3}\[(label)\]: *(?:\n[ \t]*)?([^<\s][^\s]*|<.*?>)(?:(?: +(?:\n[ \t]*)?| *\n[ \t]*)(title))? *(?:\n+|$)/).replace(`label`,wt).replace(`title`,/(?:"(?:\\"?|[^"\\])*"|'[^'\n]*(?:\n[^'\n]+)*\n?'|\([^()]*\))/).getRegex(),Et=E(/^(bull)([ \t][^\n]+?)?(?:\n|$)/).replace(/bull/g,vt).getRegex(),Dt=`address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|meta|nav|noframes|ol|optgroup|option|p|param|search|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul`,Ot=/<!--(?:-?>|[\s\S]*?(?:-->|$))/,kt=E(`^ {0,3}(?:<(script|pre|style|textarea)[\\s>][\\s\\S]*?(?:</\\1>[^\\n]*\\n+|$)|comment[^\\n]*(\\n+|$)|<\\?[\\s\\S]*?(?:\\?>\\n*|$)|<![A-Z][\\s\\S]*?(?:>\\n*|$)|<!\\[CDATA\\[[\\s\\S]*?(?:\\]\\]>\\n*|$)|</?(tag)(?: +|\\n|/?>)[\\s\\S]*?(?:(?:\\n[ 	]*)+\\n|$)|<(?!script|pre|style|textarea)([a-z][\\w-]*)(?:attribute)*? */?>(?=[ \\t]*(?:\\n|$))[\\s\\S]*?(?:(?:\\n[ 	]*)+\\n|$)|</(?!script|pre|style|textarea)[a-z][\\w-]*\\s*>(?=[ \\t]*(?:\\n|$))[\\s\\S]*?(?:(?:\\n[ 	]*)+\\n|$))`,`i`).replace(`comment`,Ot).replace(`tag`,Dt).replace(`attribute`,/ +[a-zA-Z:_][\w.:-]*(?: *= *"[^"\n]*"| *= *'[^'\n]*'| *= *[^\s"'=<>`]+)?/).getRegex(),D=E(St).replace(`hr`,gt).replace(`heading`,` {0,3}#{1,6}(?:\\s|$)`).replace(`|lheading`,``).replace(`|table`,``).replace(`blockquote`,` {0,3}>`).replace(`fences`," {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace(`list`,` {0,3}(?:[*+-]|1[.)])[ \\t]`).replace(`html`,`</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)`).replace(`tag`,Dt).getRegex(),At={blockquote:E(/^( {0,3}> ?(paragraph|[^\n]*)(?:\n|$))+/).replace(`paragraph`,D).getRegex(),code:mt,def:Tt,fences:ht,heading:_t,hr:gt,html:kt,lheading:bt,list:Et,newline:pt,paragraph:D,table:ut,text:Ct},jt=E(`^ *([^\\n ].*)\\n {0,3}((?:\\| *)?:?-+:? *(?:\\| *:?-+:? *)*(?:\\| *)?)(?:\\n((?:(?! *\\n|hr|heading|blockquote|code|fences|list|html).*(?:\\n|$))*)\\n*|$)`).replace(`hr`,gt).replace(`heading`,` {0,3}#{1,6}(?:\\s|$)`).replace(`blockquote`,` {0,3}>`).replace(`code`,`(?: {4}| {0,3}	)[^\\n]`).replace(`fences`," {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace(`list`,` {0,3}(?:[*+-]|1[.)])[ \\t]`).replace(`html`,`</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)`).replace(`tag`,Dt).getRegex(),Mt={...At,lheading:xt,table:jt,paragraph:E(St).replace(`hr`,gt).replace(`heading`,` {0,3}#{1,6}(?:\\s|$)`).replace(`|lheading`,``).replace(`table`,jt).replace(`blockquote`,` {0,3}>`).replace(`fences`," {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace(`list`,` {0,3}(?:[*+-]|1[.)])[ \\t]`).replace(`html`,`</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)`).replace(`tag`,Dt).getRegex()},Nt={...At,html:E(`^ *(?:comment *(?:\\n|\\s*$)|<(tag)[\\s\\S]+?</\\1> *(?:\\n{2,}|\\s*$)|<tag(?:"[^"]*"|'[^']*'|\\s[^'"/>\\s]*)*?/?> *(?:\\n{2,}|\\s*$))`).replace(`comment`,Ot).replace(/tag/g,`(?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\\b)\\w+(?!:|[^\\w\\s@]*@)\\b`).getRegex(),def:/^ *\[([^\]]+)\]: *<?([^\s>]+)>?(?: +(["(][^\n]+[")]))? *(?:\n+|$)/,heading:/^(#{1,6})(.*)(?:\n+|$)/,fences:ut,lheading:/^(.+?)\n {0,3}(=+|-+) *(?:\n+|$)/,paragraph:E(St).replace(`hr`,gt).replace(`heading`,` *#{1,6} *[^
]`).replace(`lheading`,bt).replace(`|table`,``).replace(`blockquote`,` {0,3}>`).replace(`|fences`,``).replace(`|list`,``).replace(`|html`,``).replace(`|tag`,``).getRegex()},Pt=/^\\([!"#$%&'()*+,\-./:;<=>?@\[\]\\^_`{|}~])/,Ft=/^(`+)([^`]|[^`][\s\S]*?[^`])\1(?!`)/,It=/^( {2,}|\\)\n(?!\s*$)/,Lt=/^(`+|[^`])(?:(?= {2,}\n)|[\s\S]*?(?:(?=[\\<!\[`*_]|\b_|$)|[^ ](?= {2,}\n)))/,O=/[\p{P}\p{S}]/u,Rt=/[\s\p{P}\p{S}]/u,zt=/[^\s\p{P}\p{S}]/u,Bt=E(/^((?![*_])punctSpace)/,`u`).replace(/punctSpace/g,Rt).getRegex(),Vt=/(?!~)[\p{P}\p{S}]/u,Ht=/(?!~)[\s\p{P}\p{S}]/u,Ut=/(?:[^\s\p{P}\p{S}]|~)/u,Wt=E(/link|precode-code|html/,`g`).replace(`link`,/\[(?:[^\[\]`]|(?<a>`+)[^`]+\k<a>(?!`))*?\]\((?:\\[\s\S]|[^\\\(\)]|\((?:\\[\s\S]|[^\\\(\)])*\))*\)/).replace(`precode-`,dt?"(?<!`)()":"(^^|[^`])").replace(`code`,/(?<b>`+)[^`]+\k<b>(?!`)/).replace(`html`,/<(?! )[^<>]*?>/).getRegex(),Gt=/^(?:\*+(?:((?!\*)punct)|([^\s*]))?)|^_+(?:((?!_)punct)|([^\s_]))?/,Kt=E(Gt,`u`).replace(/punct/g,O).getRegex(),qt=E(Gt,`u`).replace(/punct/g,Vt).getRegex(),Jt=`^[^_*]*?__[^_*]*?\\*[^_*]*?(?=__)|[^*]+(?=[^*])|(?!\\*)punct(\\*+)(?=[\\s]|$)|notPunctSpace(\\*+)(?!\\*)(?=punctSpace|$)|(?!\\*)punctSpace(\\*+)(?=notPunctSpace)|[\\s](\\*+)(?!\\*)(?=punct)|(?!\\*)punct(\\*+)(?!\\*)(?=punct)|notPunctSpace(\\*+)(?=notPunctSpace)`,Yt=E(Jt,`gu`).replace(/notPunctSpace/g,zt).replace(/punctSpace/g,Rt).replace(/punct/g,O).getRegex(),Xt=E(Jt,`gu`).replace(/notPunctSpace/g,Ut).replace(/punctSpace/g,Ht).replace(/punct/g,Vt).getRegex(),Zt=E(`^[^_*]*?\\*\\*[^_*]*?_[^_*]*?(?=\\*\\*)|[^_]+(?=[^_])|(?!_)punct(_+)(?=[\\s]|$)|notPunctSpace(_+)(?!_)(?=punctSpace|$)|(?!_)punctSpace(_+)(?=notPunctSpace)|[\\s](_+)(?!_)(?=punct)|(?!_)punct(_+)(?!_)(?=punct)`,`gu`).replace(/notPunctSpace/g,zt).replace(/punctSpace/g,Rt).replace(/punct/g,O).getRegex(),Qt=E(/^~~?(?:((?!~)punct)|[^\s~])/,`u`).replace(/punct/g,O).getRegex(),$t=E(`^[^~]+(?=[^~])|(?!~)punct(~~?)(?=[\\s]|$)|notPunctSpace(~~?)(?!~)(?=punctSpace|$)|(?!~)punctSpace(~~?)(?=notPunctSpace)|[\\s](~~?)(?!~)(?=punct)|(?!~)punct(~~?)(?!~)(?=punct)|notPunctSpace(~~?)(?=notPunctSpace)`,`gu`).replace(/notPunctSpace/g,zt).replace(/punctSpace/g,Rt).replace(/punct/g,O).getRegex(),en=E(/\\(punct)/,`gu`).replace(/punct/g,O).getRegex(),tn=E(/^<(scheme:[^\s\x00-\x1f<>]*|email)>/).replace(`scheme`,/[a-zA-Z][a-zA-Z0-9+.-]{1,31}/).replace(`email`,/[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+(@)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+(?![-_])/).getRegex(),nn=E(Ot).replace(`(?:-->|$)`,`-->`).getRegex(),rn=E(`^comment|^</[a-zA-Z][\\w:-]*\\s*>|^<[a-zA-Z][\\w-]*(?:attribute)*?\\s*/?>|^<\\?[\\s\\S]*?\\?>|^<![a-zA-Z]+\\s[\\s\\S]*?>|^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>`).replace(`comment`,nn).replace(`attribute`,/\s+[a-zA-Z:_][\w.:-]*(?:\s*=\s*"[^"]*"|\s*=\s*'[^']*'|\s*=\s*[^\s"'=<>`]+)?/).getRegex(),an=/(?:\[(?:\\[\s\S]|[^\[\]\\])*\]|\\[\s\S]|`+(?!`)[^`]*?`+(?!`)|``+(?=\])|[^\[\]\\`])*?/,on=E(/^!?\[(label)\]\(\s*(href)(?:(?:[ \t]+(?:\n[ \t]*)?|\n[ \t]*)(title))?\s*\)/).replace(`label`,an).replace(`href`,/<(?:\\.|[^\n<>\\])+>|[^ \t\n\x00-\x1f]*/).replace(`title`,/"(?:\\"?|[^"\\])*"|'(?:\\'?|[^'\\])*'|\((?:\\\)?|[^)\\])*\)/).getRegex(),sn=E(/^!?\[(label)\]\[(ref)\]/).replace(`label`,an).replace(`ref`,wt).getRegex(),cn=E(/^!?\[(ref)\](?:\[\])?/).replace(`ref`,wt).getRegex(),ln=E(`reflink|nolink(?!\\()`,`g`).replace(`reflink`,sn).replace(`nolink`,cn).getRegex(),un=/[hH][tT][tT][pP][sS]?|[fF][tT][pP]/,dn={_backpedal:ut,anyPunctuation:en,autolink:tn,blockSkip:Wt,br:It,code:Ft,del:ut,delLDelim:ut,delRDelim:ut,emStrongLDelim:Kt,emStrongRDelimAst:Yt,emStrongRDelimUnd:Zt,escape:Pt,link:on,nolink:cn,punctuation:Bt,reflink:sn,reflinkSearch:ln,tag:rn,text:Lt,url:ut},fn={...dn,link:E(/^!?\[(label)\]\((.*?)\)/).replace(`label`,an).getRegex(),reflink:E(/^!?\[(label)\]\s*\[([^\]]*)\]/).replace(`label`,an).getRegex()},pn={...dn,emStrongRDelimAst:Xt,emStrongLDelim:qt,delLDelim:Qt,delRDelim:$t,url:E(/^((?:protocol):\/\/|www\.)(?:[a-zA-Z0-9\-]+\.?)+[^\s<]*|^email/).replace(`protocol`,un).replace(`email`,/[A-Za-z0-9._+-]+(@)[a-zA-Z0-9-_]+(?:\.[a-zA-Z0-9-_]*[a-zA-Z0-9])+(?![-_])/).getRegex(),_backpedal:/(?:[^?!.,:;*_'"~()&]+|\([^)]*\)|&(?![a-zA-Z0-9]+;$)|[?!.,:;*_'"~)]+(?!$))+/,del:/^(~~?)(?=[^\s~])((?:\\[\s\S]|[^\\])*?(?:\\[\s\S]|[^\s~\\]))\1(?=[^~]|$)/,text:E(/^([`~]+|[^`~])(?:(?= {2,}\n)|(?=[a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-]+@)|[\s\S]*?(?:(?=[\\<!\[`*~_]|\b_|protocol:\/\/|www\.|$)|[^ ](?= {2,}\n)|[^a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-](?=[a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-]+@)))/).replace(`protocol`,un).getRegex()},mn={...pn,br:E(It).replace(`{2,}`,`*`).getRegex(),text:E(pn.text).replace(`\\b_`,`\\b_| {2,}\\n`).replace(/\{2,\}/g,`*`).getRegex()},hn={normal:At,gfm:Mt,pedantic:Nt},gn={normal:dn,gfm:pn,breaks:mn,pedantic:fn},_n={"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`},k=e=>_n[e];function A(e,t){if(t){if(ft.escapeTest.test(e))return e.replace(ft.escapeReplace,k)}else if(ft.escapeTestNoEncode.test(e))return e.replace(ft.escapeReplaceNoEncode,k);return e}function vn(e){try{e=encodeURI(e).replace(ft.percentDecode,`%`)}catch{return null}return e}function yn(e,t){let n=e.replace(ft.findPipe,(e,t,n)=>{let r=!1,i=t;for(;--i>=0&&n[i]===`\\`;)r=!r;return r?`|`:` |`}).split(ft.splitPipe),r=0;if(n[0].trim()||n.shift(),n.length>0&&!n.at(-1)?.trim()&&n.pop(),t)if(n.length>t)n.splice(t);else for(;n.length<t;)n.push(``);for(;r<n.length;r++)n[r]=n[r].trim().replace(ft.slashPipe,`|`);return n}function bn(e,t,n){let r=e.length;if(r===0)return``;let i=0;for(;i<r;){let a=e.charAt(r-i-1);if(a===t&&!n)i++;else if(a!==t&&n)i++;else break}return e.slice(0,r-i)}function xn(e){let t=e.split(`
`),n=t.length-1;for(;n>=0&&ft.blankLine.test(t[n]);)n--;return t.length-n<=2?e:t.slice(0,n+1).join(`
`)}function Sn(e,t){if(e.indexOf(t[1])===-1)return-1;let n=0;for(let r=0;r<e.length;r++)if(e[r]===`\\`)r++;else if(e[r]===t[0])n++;else if(e[r]===t[1]&&(n--,n<0))return r;return n>0?-2:-1}function Cn(e,t=0){let n=t,r=``;for(let t of e)if(t===`	`){let e=4-n%4;r+=` `.repeat(e),n+=e}else r+=t,n++;return r}function wn(e,t,n,r,i){let a=t.href,o=t.title||null,s=e[1].replace(i.other.outputLinkReplace,`$1`);r.state.inLink=!0;let c={type:e[0].charAt(0)===`!`?`image`:`link`,raw:n,href:a,title:o,text:s,tokens:r.inlineTokens(s)};return r.state.inLink=!1,c}function Tn(e,t,n){let r=e.match(n.other.indentCodeCompensation);if(r===null)return t;let i=r[1];return t.split(`
`).map(e=>{let t=e.match(n.other.beginningSpace);if(t===null)return e;let[r]=t;return r.length>=i.length?e.slice(i.length):e}).join(`
`)}var En=class{options;rules;lexer;constructor(e){this.options=e||ct}space(e){let t=this.rules.block.newline.exec(e);if(t&&t[0].length>0)return{type:`space`,raw:t[0]}}code(e){let t=this.rules.block.code.exec(e);if(t){let e=this.options.pedantic?t[0]:xn(t[0]);return{type:`code`,raw:e,codeBlockStyle:`indented`,text:e.replace(this.rules.other.codeRemoveIndent,``)}}}fences(e){let t=this.rules.block.fences.exec(e);if(t){let e=t[0],n=Tn(e,t[3]||``,this.rules);return{type:`code`,raw:e,lang:t[2]?t[2].trim().replace(this.rules.inline.anyPunctuation,`$1`):t[2],text:n}}}heading(e){let t=this.rules.block.heading.exec(e);if(t){let e=t[2].trim();if(this.rules.other.endingHash.test(e)){let t=bn(e,`#`);(this.options.pedantic||!t||this.rules.other.endingSpaceChar.test(t))&&(e=t.trim())}return{type:`heading`,raw:bn(t[0],`
`),depth:t[1].length,text:e,tokens:this.lexer.inline(e)}}}hr(e){let t=this.rules.block.hr.exec(e);if(t)return{type:`hr`,raw:bn(t[0],`
`)}}blockquote(e){let t=this.rules.block.blockquote.exec(e);if(t){let e=bn(t[0],`
`).split(`
`),n=``,r=``,i=[];for(;e.length>0;){let t=!1,a=[],o;for(o=0;o<e.length;o++)if(this.rules.other.blockquoteStart.test(e[o]))a.push(e[o]),t=!0;else if(!t)a.push(e[o]);else break;e=e.slice(o);let s=a.join(`
`),c=s.replace(this.rules.other.blockquoteSetextReplace,`
    $1`).replace(this.rules.other.blockquoteSetextReplace2,``);n=n?`${n}
${s}`:s,r=r?`${r}
${c}`:c;let l=this.lexer.state.top;if(this.lexer.state.top=!0,this.lexer.blockTokens(c,i,!0),this.lexer.state.top=l,e.length===0)break;let u=i.at(-1);if(u?.type===`code`)break;if(u?.type===`blockquote`){let t=u,a=t.raw+`
`+e.join(`
`),o=this.blockquote(a);i[i.length-1]=o,n=n.substring(0,n.length-t.raw.length)+o.raw,r=r.substring(0,r.length-t.text.length)+o.text;break}else if(u?.type===`list`){let t=u,a=t.raw+`
`+e.join(`
`),o=this.list(a);i[i.length-1]=o,n=n.substring(0,n.length-u.raw.length)+o.raw,r=r.substring(0,r.length-t.raw.length)+o.raw,e=a.substring(i.at(-1).raw.length).split(`
`);continue}}return{type:`blockquote`,raw:n,tokens:i,text:r}}}list(e){let t=this.rules.block.list.exec(e);if(t){let n=t[1].trim(),r=n.length>1,i={type:`list`,raw:``,ordered:r,start:r?+n.slice(0,-1):``,loose:!1,items:[]};n=r?`\\d{1,9}\\${n.slice(-1)}`:`\\${n}`,this.options.pedantic&&(n=r?n:`[*+-]`);let a=this.rules.other.listItemRegex(n),o=!1;for(;e;){let n=!1,r=``,s=``;if(!(t=a.exec(e))||this.rules.block.hr.test(e))break;r=t[0],e=e.substring(r.length);let c=Cn(t[2].split(`
`,1)[0],t[1].length),l=e.split(`
`,1)[0],u=!c.trim(),d=0;if(this.options.pedantic?(d=2,s=c.trimStart()):u?d=t[1].length+1:(d=c.search(this.rules.other.nonSpaceChar),d=d>4?1:d,s=c.slice(d),d+=t[1].length),u&&this.rules.other.blankLine.test(l)&&(r+=l+`
`,e=e.substring(l.length+1),n=!0),!n){let t=this.rules.other.nextBulletRegex(d),n=this.rules.other.hrRegex(d),i=this.rules.other.fencesBeginRegex(d),a=this.rules.other.headingBeginRegex(d),o=this.rules.other.htmlBeginRegex(d),f=this.rules.other.blockquoteBeginRegex(d);for(;e;){let p=e.split(`
`,1)[0],m;if(l=p,this.options.pedantic?(l=l.replace(this.rules.other.listReplaceNesting,`  `),m=l):m=l.replace(this.rules.other.tabCharGlobal,`    `),i.test(l)||a.test(l)||o.test(l)||f.test(l)||t.test(l)||n.test(l))break;if(m.search(this.rules.other.nonSpaceChar)>=d||!l.trim())s+=`
`+m.slice(d);else{if(u||c.replace(this.rules.other.tabCharGlobal,`    `).search(this.rules.other.nonSpaceChar)>=4||i.test(c)||a.test(c)||n.test(c))break;s+=`
`+l}u=!l.trim(),r+=p+`
`,e=e.substring(p.length+1),c=m.slice(d)}}i.loose||(o?i.loose=!0:this.rules.other.doubleBlankLine.test(r)&&(o=!0)),i.items.push({type:`list_item`,raw:r,task:!!this.options.gfm&&this.rules.other.listIsTask.test(s),loose:!1,text:s,tokens:[]}),i.raw+=r}let s=i.items.at(-1);if(s)s.raw=s.raw.trimEnd(),s.text=s.text.trimEnd();else return;i.raw=i.raw.trimEnd();for(let e of i.items){this.lexer.state.top=!1,e.tokens=this.lexer.blockTokens(e.text,[]);let t=e.tokens[0];if(e.task&&(t?.type===`text`||t?.type===`paragraph`)){e.text=e.text.replace(this.rules.other.listReplaceTask,``),t.raw=t.raw.replace(this.rules.other.listReplaceTask,``),t.text=t.text.replace(this.rules.other.listReplaceTask,``);for(let e=this.lexer.inlineQueue.length-1;e>=0;e--)if(this.rules.other.listIsTask.test(this.lexer.inlineQueue[e].src)){this.lexer.inlineQueue[e].src=this.lexer.inlineQueue[e].src.replace(this.rules.other.listReplaceTask,``);break}let n=this.rules.other.listTaskCheckbox.exec(e.raw);if(n){let t={type:`checkbox`,raw:n[0]+` `,checked:n[0]!==`[ ]`};e.checked=t.checked,i.loose?e.tokens[0]&&[`paragraph`,`text`].includes(e.tokens[0].type)&&`tokens`in e.tokens[0]&&e.tokens[0].tokens?(e.tokens[0].raw=t.raw+e.tokens[0].raw,e.tokens[0].text=t.raw+e.tokens[0].text,e.tokens[0].tokens.unshift(t)):e.tokens.unshift({type:`paragraph`,raw:t.raw,text:t.raw,tokens:[t]}):e.tokens.unshift(t)}}else e.task&&=!1;if(!i.loose){let t=e.tokens.filter(e=>e.type===`space`);i.loose=t.length>0&&t.some(e=>this.rules.other.anyLine.test(e.raw))}}if(i.loose)for(let e of i.items){e.loose=!0;for(let t of e.tokens)t.type===`text`&&(t.type=`paragraph`)}return i}}html(e){let t=this.rules.block.html.exec(e);if(t){let e=xn(t[0]);return{type:`html`,block:!0,raw:e,pre:t[1]===`pre`||t[1]===`script`||t[1]===`style`,text:e}}}def(e){let t=this.rules.block.def.exec(e);if(t){let e=t[1].toLowerCase().replace(this.rules.other.multipleSpaceGlobal,` `),n=t[2]?t[2].replace(this.rules.other.hrefBrackets,`$1`).replace(this.rules.inline.anyPunctuation,`$1`):``,r=t[3]?t[3].substring(1,t[3].length-1).replace(this.rules.inline.anyPunctuation,`$1`):t[3];return{type:`def`,tag:e,raw:bn(t[0],`
`),href:n,title:r}}}table(e){let t=this.rules.block.table.exec(e);if(!t||!this.rules.other.tableDelimiter.test(t[2]))return;let n=yn(t[1]),r=t[2].replace(this.rules.other.tableAlignChars,``).split(`|`),i=t[3]?.trim()?t[3].replace(this.rules.other.tableRowBlankLine,``).split(`
`):[],a={type:`table`,raw:bn(t[0],`
`),header:[],align:[],rows:[]};if(n.length===r.length){for(let e of r)this.rules.other.tableAlignRight.test(e)?a.align.push(`right`):this.rules.other.tableAlignCenter.test(e)?a.align.push(`center`):this.rules.other.tableAlignLeft.test(e)?a.align.push(`left`):a.align.push(null);for(let e=0;e<n.length;e++)a.header.push({text:n[e],tokens:this.lexer.inline(n[e]),header:!0,align:a.align[e]});for(let e of i)a.rows.push(yn(e,a.header.length).map((e,t)=>({text:e,tokens:this.lexer.inline(e),header:!1,align:a.align[t]})));return a}}lheading(e){let t=this.rules.block.lheading.exec(e);if(t){let e=t[1].trim();return{type:`heading`,raw:bn(t[0],`
`),depth:t[2].charAt(0)===`=`?1:2,text:e,tokens:this.lexer.inline(e)}}}paragraph(e){let t=this.rules.block.paragraph.exec(e);if(t){let e=t[1].charAt(t[1].length-1)===`
`?t[1].slice(0,-1):t[1];return{type:`paragraph`,raw:t[0],text:e,tokens:this.lexer.inline(e)}}}text(e){let t=this.rules.block.text.exec(e);if(t)return{type:`text`,raw:t[0],text:t[0],tokens:this.lexer.inline(t[0])}}escape(e){let t=this.rules.inline.escape.exec(e);if(t)return{type:`escape`,raw:t[0],text:t[1]}}tag(e){let t=this.rules.inline.tag.exec(e);if(t)return!this.lexer.state.inLink&&this.rules.other.startATag.test(t[0])?this.lexer.state.inLink=!0:this.lexer.state.inLink&&this.rules.other.endATag.test(t[0])&&(this.lexer.state.inLink=!1),!this.lexer.state.inRawBlock&&this.rules.other.startPreScriptTag.test(t[0])?this.lexer.state.inRawBlock=!0:this.lexer.state.inRawBlock&&this.rules.other.endPreScriptTag.test(t[0])&&(this.lexer.state.inRawBlock=!1),{type:`html`,raw:t[0],inLink:this.lexer.state.inLink,inRawBlock:this.lexer.state.inRawBlock,block:!1,text:t[0]}}link(e){let t=this.rules.inline.link.exec(e);if(t){let e=t[2].trim();if(!this.options.pedantic&&this.rules.other.startAngleBracket.test(e)){if(!this.rules.other.endAngleBracket.test(e))return;let t=bn(e.slice(0,-1),`\\`);if((e.length-t.length)%2==0)return}else{let e=Sn(t[2],`()`);if(e===-2)return;if(e>-1){let n=(t[0].indexOf(`!`)===0?5:4)+t[1].length+e;t[2]=t[2].substring(0,e),t[0]=t[0].substring(0,n).trim(),t[3]=``}}let n=t[2],r=``;if(this.options.pedantic){let e=this.rules.other.pedanticHrefTitle.exec(n);e&&(n=e[1],r=e[3])}else r=t[3]?t[3].slice(1,-1):``;return n=n.trim(),this.rules.other.startAngleBracket.test(n)&&(n=this.options.pedantic&&!this.rules.other.endAngleBracket.test(e)?n.slice(1):n.slice(1,-1)),wn(t,{href:n&&n.replace(this.rules.inline.anyPunctuation,`$1`),title:r&&r.replace(this.rules.inline.anyPunctuation,`$1`)},t[0],this.lexer,this.rules)}}reflink(e,t){let n;if((n=this.rules.inline.reflink.exec(e))||(n=this.rules.inline.nolink.exec(e))){let e=t[(n[2]||n[1]).replace(this.rules.other.multipleSpaceGlobal,` `).toLowerCase()];if(!e){let e=n[0].charAt(0);return{type:`text`,raw:e,text:e}}return wn(n,e,n[0],this.lexer,this.rules)}}emStrong(e,t,n=``){let r=this.rules.inline.emStrongLDelim.exec(e);if(!(!r||!r[1]&&!r[2]&&!r[3]&&!r[4]||r[4]&&n.match(this.rules.other.unicodeAlphaNumeric))&&(!(r[1]||r[3])||!n||this.rules.inline.punctuation.exec(n))){let n=[...r[0]].length-1,i,a,o=n,s=0,c=r[0][0]===`*`?this.rules.inline.emStrongRDelimAst:this.rules.inline.emStrongRDelimUnd;for(c.lastIndex=0,t=t.slice(-1*e.length+n);(r=c.exec(t))!==null;){if(i=r[1]||r[2]||r[3]||r[4]||r[5]||r[6],!i)continue;if(a=[...i].length,r[3]||r[4]){o+=a;continue}else if((r[5]||r[6])&&n%3&&!((n+a)%3)){s+=a;continue}if(o-=a,o>0)continue;a=Math.min(a,a+o+s);let t=[...r[0]][0].length,c=e.slice(0,n+r.index+t+a);if(Math.min(n,a)%2){let e=c.slice(1,-1);return{type:`em`,raw:c,text:e,tokens:this.lexer.inlineTokens(e)}}let l=c.slice(2,-2);return{type:`strong`,raw:c,text:l,tokens:this.lexer.inlineTokens(l)}}}}codespan(e){let t=this.rules.inline.code.exec(e);if(t){let e=t[2].replace(this.rules.other.newLineCharGlobal,` `),n=this.rules.other.nonSpaceChar.test(e),r=this.rules.other.startingSpaceChar.test(e)&&this.rules.other.endingSpaceChar.test(e);return n&&r&&(e=e.substring(1,e.length-1)),{type:`codespan`,raw:t[0],text:e}}}br(e){let t=this.rules.inline.br.exec(e);if(t)return{type:`br`,raw:t[0]}}del(e,t,n=``){let r=this.rules.inline.delLDelim.exec(e);if(r&&(!r[1]||!n||this.rules.inline.punctuation.exec(n))){let n=[...r[0]].length-1,i,a,o=n,s=this.rules.inline.delRDelim;for(s.lastIndex=0,t=t.slice(-1*e.length+n);(r=s.exec(t))!==null;){if(i=r[1]||r[2]||r[3]||r[4]||r[5]||r[6],!i||(a=[...i].length,a!==n))continue;if(r[3]||r[4]){o+=a;continue}if(o-=a,o>0)continue;a=Math.min(a,a+o);let t=[...r[0]][0].length,s=e.slice(0,n+r.index+t+a),c=s.slice(n,-n);return{type:`del`,raw:s,text:c,tokens:this.lexer.inlineTokens(c)}}}}autolink(e){let t=this.rules.inline.autolink.exec(e);if(t){let e,n;return t[2]===`@`?(e=t[1],n=`mailto:`+e):(e=t[1],n=e),{type:`link`,raw:t[0],text:e,href:n,tokens:[{type:`text`,raw:e,text:e}]}}}url(e){let t;if(t=this.rules.inline.url.exec(e)){let e,n;if(t[2]===`@`)e=t[0],n=`mailto:`+e;else{let r;do r=t[0],t[0]=this.rules.inline._backpedal.exec(t[0])?.[0]??``;while(r!==t[0]);e=t[0],n=t[1]===`www.`?`http://`+t[0]:t[0]}return{type:`link`,raw:t[0],text:e,href:n,tokens:[{type:`text`,raw:e,text:e}]}}}inlineText(e){let t=this.rules.inline.text.exec(e);if(t){let e=this.lexer.state.inRawBlock;return{type:`text`,raw:t[0],text:t[0],escaped:e}}}},Dn=class e{tokens;options;state;inlineQueue;tokenizer;constructor(e){this.tokens=[],this.tokens.links=Object.create(null),this.options=e||ct,this.options.tokenizer=this.options.tokenizer||new En,this.tokenizer=this.options.tokenizer,this.tokenizer.options=this.options,this.tokenizer.lexer=this,this.inlineQueue=[],this.state={inLink:!1,inRawBlock:!1,top:!0};let t={other:ft,block:hn.normal,inline:gn.normal};this.options.pedantic?(t.block=hn.pedantic,t.inline=gn.pedantic):this.options.gfm&&(t.block=hn.gfm,this.options.breaks?t.inline=gn.breaks:t.inline=gn.gfm),this.tokenizer.rules=t}static get rules(){return{block:hn,inline:gn}}static lex(t,n){return new e(n).lex(t)}static lexInline(t,n){return new e(n).inlineTokens(t)}lex(e){e=e.replace(ft.carriageReturn,`
`),this.blockTokens(e,this.tokens);for(let e=0;e<this.inlineQueue.length;e++){let t=this.inlineQueue[e];this.inlineTokens(t.src,t.tokens)}return this.inlineQueue=[],this.tokens}blockTokens(e,t=[],n=!1){this.tokenizer.lexer=this,this.options.pedantic&&(e=e.replace(ft.tabCharGlobal,`    `).replace(ft.spaceLine,``));let r=1/0;for(;e;){if(e.length<r)r=e.length;else{this.infiniteLoopError(e.charCodeAt(0));break}let i;if(this.options.extensions?.block?.some(n=>(i=n.call({lexer:this},e,t))?(e=e.substring(i.raw.length),t.push(i),!0):!1))continue;if(i=this.tokenizer.space(e)){e=e.substring(i.raw.length);let n=t.at(-1);i.raw.length===1&&n!==void 0?n.raw+=`
`:t.push(i);continue}if(i=this.tokenizer.code(e)){e=e.substring(i.raw.length);let n=t.at(-1);n?.type===`paragraph`||n?.type===`text`?(n.raw+=(n.raw.endsWith(`
`)?``:`
`)+i.raw,n.text+=`
`+i.text,this.inlineQueue.at(-1).src=n.text):t.push(i);continue}if(i=this.tokenizer.fences(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.heading(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.hr(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.blockquote(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.list(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.html(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.def(e)){e=e.substring(i.raw.length);let n=t.at(-1);n?.type===`paragraph`||n?.type===`text`?(n.raw+=(n.raw.endsWith(`
`)?``:`
`)+i.raw,n.text+=`
`+i.raw,this.inlineQueue.at(-1).src=n.text):this.tokens.links[i.tag]||(this.tokens.links[i.tag]={href:i.href,title:i.title},t.push(i));continue}if(i=this.tokenizer.table(e)){e=e.substring(i.raw.length),t.push(i);continue}if(i=this.tokenizer.lheading(e)){e=e.substring(i.raw.length),t.push(i);continue}let a=e;if(this.options.extensions?.startBlock){let t=1/0,n=e.slice(1),r;this.options.extensions.startBlock.forEach(e=>{r=e.call({lexer:this},n),typeof r==`number`&&r>=0&&(t=Math.min(t,r))}),t<1/0&&t>=0&&(a=e.substring(0,t+1))}if(this.state.top&&(i=this.tokenizer.paragraph(a))){let r=t.at(-1);n&&r?.type===`paragraph`?(r.raw+=(r.raw.endsWith(`
`)?``:`
`)+i.raw,r.text+=`
`+i.text,this.inlineQueue.pop(),this.inlineQueue.at(-1).src=r.text):t.push(i),n=a.length!==e.length,e=e.substring(i.raw.length);continue}if(i=this.tokenizer.text(e)){e=e.substring(i.raw.length);let n=t.at(-1);n?.type===`text`?(n.raw+=(n.raw.endsWith(`
`)?``:`
`)+i.raw,n.text+=`
`+i.text,this.inlineQueue.pop(),this.inlineQueue.at(-1).src=n.text):t.push(i);continue}if(e){this.infiniteLoopError(e.charCodeAt(0));break}}return this.state.top=!0,t}inline(e,t=[]){return this.inlineQueue.push({src:e,tokens:t}),t}inlineTokens(e,t=[]){this.tokenizer.lexer=this;let n=e,r=null;if(this.tokens.links){let e=Object.keys(this.tokens.links);if(e.length>0)for(;(r=this.tokenizer.rules.inline.reflinkSearch.exec(n))!==null;)e.includes(r[0].slice(r[0].lastIndexOf(`[`)+1,-1))&&(n=n.slice(0,r.index)+`[`+`a`.repeat(r[0].length-2)+`]`+n.slice(this.tokenizer.rules.inline.reflinkSearch.lastIndex))}for(;(r=this.tokenizer.rules.inline.anyPunctuation.exec(n))!==null;)n=n.slice(0,r.index)+`++`+n.slice(this.tokenizer.rules.inline.anyPunctuation.lastIndex);let i;for(;(r=this.tokenizer.rules.inline.blockSkip.exec(n))!==null;)i=r[2]?r[2].length:0,n=n.slice(0,r.index+i)+`[`+`a`.repeat(r[0].length-i-2)+`]`+n.slice(this.tokenizer.rules.inline.blockSkip.lastIndex);n=this.options.hooks?.emStrongMask?.call({lexer:this},n)??n;let a=!1,o=``,s=1/0;for(;e;){if(e.length<s)s=e.length;else{this.infiniteLoopError(e.charCodeAt(0));break}a||(o=``),a=!1;let r;if(this.options.extensions?.inline?.some(n=>(r=n.call({lexer:this},e,t))?(e=e.substring(r.raw.length),t.push(r),!0):!1))continue;if(r=this.tokenizer.escape(e)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.tag(e)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.link(e)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.reflink(e,this.tokens.links)){e=e.substring(r.raw.length);let n=t.at(-1);r.type===`text`&&n?.type===`text`?(n.raw+=r.raw,n.text+=r.text):t.push(r);continue}if(r=this.tokenizer.emStrong(e,n,o)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.codespan(e)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.br(e)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.del(e,n,o)){e=e.substring(r.raw.length),t.push(r);continue}if(r=this.tokenizer.autolink(e)){e=e.substring(r.raw.length),t.push(r);continue}if(!this.state.inLink&&(r=this.tokenizer.url(e))){e=e.substring(r.raw.length),t.push(r);continue}let i=e;if(this.options.extensions?.startInline){let t=1/0,n=e.slice(1),r;this.options.extensions.startInline.forEach(e=>{r=e.call({lexer:this},n),typeof r==`number`&&r>=0&&(t=Math.min(t,r))}),t<1/0&&t>=0&&(i=e.substring(0,t+1))}if(r=this.tokenizer.inlineText(i)){e=e.substring(r.raw.length),r.raw.slice(-1)!==`_`&&(o=r.raw.slice(-1)),a=!0;let n=t.at(-1);n?.type===`text`?(n.raw+=r.raw,n.text+=r.text):t.push(r);continue}if(e){this.infiniteLoopError(e.charCodeAt(0));break}}return t}infiniteLoopError(e){let t=`Infinite loop on byte: `+e;if(this.options.silent)console.error(t);else throw Error(t)}},On=class{options;parser;constructor(e){this.options=e||ct}space(e){return``}code({text:e,lang:t,escaped:n}){let r=(t||``).match(ft.notSpaceStart)?.[0],i=e.replace(ft.endingNewline,``)+`
`;return r?`<pre><code class="language-`+A(r)+`">`+(n?i:A(i,!0))+`</code></pre>
`:`<pre><code>`+(n?i:A(i,!0))+`</code></pre>
`}blockquote({tokens:e}){return`<blockquote>
${this.parser.parse(e)}</blockquote>
`}html({text:e}){return e}def(e){return``}heading({tokens:e,depth:t}){return`<h${t}>${this.parser.parseInline(e)}</h${t}>
`}hr(e){return`<hr>
`}list(e){let t=e.ordered,n=e.start,r=``;for(let t=0;t<e.items.length;t++){let n=e.items[t];r+=this.listitem(n)}let i=t?`ol`:`ul`,a=t&&n!==1?` start="`+n+`"`:``;return`<`+i+a+`>
`+r+`</`+i+`>
`}listitem(e){return`<li>${this.parser.parse(e.tokens)}</li>
`}checkbox({checked:e}){return`<input `+(e?`checked="" `:``)+`disabled="" type="checkbox"> `}paragraph({tokens:e}){return`<p>${this.parser.parseInline(e)}</p>
`}table(e){let t=``,n=``;for(let t=0;t<e.header.length;t++)n+=this.tablecell(e.header[t]);t+=this.tablerow({text:n});let r=``;for(let t=0;t<e.rows.length;t++){let i=e.rows[t];n=``;for(let e=0;e<i.length;e++)n+=this.tablecell(i[e]);r+=this.tablerow({text:n})}return r&&=`<tbody>${r}</tbody>`,`<table>
<thead>
`+t+`</thead>
`+r+`</table>
`}tablerow({text:e}){return`<tr>
${e}</tr>
`}tablecell(e){let t=this.parser.parseInline(e.tokens),n=e.header?`th`:`td`;return(e.align?`<${n} align="${e.align}">`:`<${n}>`)+t+`</${n}>
`}strong({tokens:e}){return`<strong>${this.parser.parseInline(e)}</strong>`}em({tokens:e}){return`<em>${this.parser.parseInline(e)}</em>`}codespan({text:e}){return`<code>${A(e,!0)}</code>`}br(e){return`<br>`}del({tokens:e}){return`<del>${this.parser.parseInline(e)}</del>`}link({href:e,title:t,tokens:n}){let r=this.parser.parseInline(n),i=vn(e);if(i===null)return r;e=i;let a=`<a href="`+e+`"`;return t&&(a+=` title="`+A(t)+`"`),a+=`>`+r+`</a>`,a}image({href:e,title:t,text:n,tokens:r}){r&&(n=this.parser.parseInline(r,this.parser.textRenderer));let i=vn(e);if(i===null)return A(n);e=i;let a=`<img src="${e}" alt="${A(n)}"`;return t&&(a+=` title="${A(t)}"`),a+=`>`,a}text(e){return`tokens`in e&&e.tokens?this.parser.parseInline(e.tokens):`escaped`in e&&e.escaped?e.text:A(e.text)}},kn=class{strong({text:e}){return e}em({text:e}){return e}codespan({text:e}){return e}del({text:e}){return e}html({text:e}){return e}text({text:e}){return e}link({text:e}){return``+e}image({text:e}){return``+e}br(){return``}checkbox({raw:e}){return e}},An=class e{options;renderer;textRenderer;constructor(e){this.options=e||ct,this.options.renderer=this.options.renderer||new On,this.renderer=this.options.renderer,this.renderer.options=this.options,this.renderer.parser=this,this.textRenderer=new kn}static parse(t,n){return new e(n).parse(t)}static parseInline(t,n){return new e(n).parseInline(t)}parse(e){this.renderer.parser=this;let t=``;for(let n=0;n<e.length;n++){let r=e[n];if(this.options.extensions?.renderers?.[r.type]){let e=r,n=this.options.extensions.renderers[e.type].call({parser:this},e);if(n!==!1||![`space`,`hr`,`heading`,`code`,`table`,`blockquote`,`list`,`html`,`def`,`paragraph`,`text`].includes(e.type)){t+=n||``;continue}}let i=r;switch(i.type){case`space`:t+=this.renderer.space(i);break;case`hr`:t+=this.renderer.hr(i);break;case`heading`:t+=this.renderer.heading(i);break;case`code`:t+=this.renderer.code(i);break;case`table`:t+=this.renderer.table(i);break;case`blockquote`:t+=this.renderer.blockquote(i);break;case`list`:t+=this.renderer.list(i);break;case`checkbox`:t+=this.renderer.checkbox(i);break;case`html`:t+=this.renderer.html(i);break;case`def`:t+=this.renderer.def(i);break;case`paragraph`:t+=this.renderer.paragraph(i);break;case`text`:t+=this.renderer.text(i);break;default:{let e=`Token with "`+i.type+`" type was not found.`;if(this.options.silent)return console.error(e),``;throw Error(e)}}}return t}parseInline(e,t=this.renderer){this.renderer.parser=this;let n=``;for(let r=0;r<e.length;r++){let i=e[r];if(this.options.extensions?.renderers?.[i.type]){let e=this.options.extensions.renderers[i.type].call({parser:this},i);if(e!==!1||![`escape`,`html`,`link`,`image`,`strong`,`em`,`codespan`,`br`,`del`,`text`].includes(i.type)){n+=e||``;continue}}let a=i;switch(a.type){case`escape`:n+=t.text(a);break;case`html`:n+=t.html(a);break;case`link`:n+=t.link(a);break;case`image`:n+=t.image(a);break;case`checkbox`:n+=t.checkbox(a);break;case`strong`:n+=t.strong(a);break;case`em`:n+=t.em(a);break;case`codespan`:n+=t.codespan(a);break;case`br`:n+=t.br(a);break;case`del`:n+=t.del(a);break;case`text`:n+=t.text(a);break;default:{let e=`Token with "`+a.type+`" type was not found.`;if(this.options.silent)return console.error(e),``;throw Error(e)}}}return n}},jn=class{options;block;constructor(e){this.options=e||ct}static passThroughHooks=new Set([`preprocess`,`postprocess`,`processAllTokens`,`emStrongMask`]);static passThroughHooksRespectAsync=new Set([`preprocess`,`postprocess`,`processAllTokens`]);preprocess(e){return e}postprocess(e){return e}processAllTokens(e){return e}emStrongMask(e){return e}provideLexer(e=this.block){return e?Dn.lex:Dn.lexInline}provideParser(e=this.block){return e?An.parse:An.parseInline}},Mn=new class{defaults=st();options=this.setOptions;parse=this.parseMarkdown(!0);parseInline=this.parseMarkdown(!1);Parser=An;Renderer=On;TextRenderer=kn;Lexer=Dn;Tokenizer=En;Hooks=jn;constructor(...e){this.use(...e)}walkTokens(e,t){let n=[];for(let r of e)switch(n=n.concat(t.call(this,r)),r.type){case`table`:{let e=r;for(let r of e.header)n=n.concat(this.walkTokens(r.tokens,t));for(let r of e.rows)for(let e of r)n=n.concat(this.walkTokens(e.tokens,t));break}case`list`:{let e=r;n=n.concat(this.walkTokens(e.items,t));break}default:{let e=r;this.defaults.extensions?.childTokens?.[e.type]?this.defaults.extensions.childTokens[e.type].forEach(r=>{let i=e[r].flat(1/0);n=n.concat(this.walkTokens(i,t))}):e.tokens&&(n=n.concat(this.walkTokens(e.tokens,t)))}}return n}use(...e){let t=this.defaults.extensions||{renderers:{},childTokens:{}};return e.forEach(e=>{let n={...e};if(n.async=this.defaults.async||n.async||!1,e.extensions&&(e.extensions.forEach(e=>{if(!e.name)throw Error(`extension name required`);if(`renderer`in e){let n=t.renderers[e.name];n?t.renderers[e.name]=function(...t){let r=e.renderer.apply(this,t);return r===!1&&(r=n.apply(this,t)),r}:t.renderers[e.name]=e.renderer}if(`tokenizer`in e){if(!e.level||e.level!==`block`&&e.level!==`inline`)throw Error(`extension level must be 'block' or 'inline'`);let n=t[e.level];n?n.unshift(e.tokenizer):t[e.level]=[e.tokenizer],e.start&&(e.level===`block`?t.startBlock?t.startBlock.push(e.start):t.startBlock=[e.start]:e.level===`inline`&&(t.startInline?t.startInline.push(e.start):t.startInline=[e.start]))}`childTokens`in e&&e.childTokens&&(t.childTokens[e.name]=e.childTokens)}),n.extensions=t),e.renderer){let t=this.defaults.renderer||new On(this.defaults);for(let n in e.renderer){if(!(n in t))throw Error(`renderer '${n}' does not exist`);if([`options`,`parser`].includes(n))continue;let r=n,i=e.renderer[r],a=t[r];t[r]=(...e)=>{let n=i.apply(t,e);return n===!1&&(n=a.apply(t,e)),n||``}}n.renderer=t}if(e.tokenizer){let t=this.defaults.tokenizer||new En(this.defaults);for(let n in e.tokenizer){if(!(n in t))throw Error(`tokenizer '${n}' does not exist`);if([`options`,`rules`,`lexer`].includes(n))continue;let r=n,i=e.tokenizer[r],a=t[r];t[r]=(...e)=>{let n=i.apply(t,e);return n===!1&&(n=a.apply(t,e)),n}}n.tokenizer=t}if(e.hooks){let t=this.defaults.hooks||new jn;for(let n in e.hooks){if(!(n in t))throw Error(`hook '${n}' does not exist`);if([`options`,`block`].includes(n))continue;let r=n,i=e.hooks[r],a=t[r];jn.passThroughHooks.has(n)?t[r]=e=>{if(this.defaults.async&&jn.passThroughHooksRespectAsync.has(n))return(async()=>{let n=await i.call(t,e);return a.call(t,n)})();let r=i.call(t,e);return a.call(t,r)}:t[r]=(...e)=>{if(this.defaults.async)return(async()=>{let n=await i.apply(t,e);return n===!1&&(n=await a.apply(t,e)),n})();let n=i.apply(t,e);return n===!1&&(n=a.apply(t,e)),n}}n.hooks=t}if(e.walkTokens){let t=this.defaults.walkTokens,r=e.walkTokens;n.walkTokens=function(e){let n=[];return n.push(r.call(this,e)),t&&(n=n.concat(t.call(this,e))),n}}this.defaults={...this.defaults,...n}}),this}setOptions(e){return this.defaults={...this.defaults,...e},this}lexer(e,t){return Dn.lex(e,t??this.defaults)}parser(e,t){return An.parse(e,t??this.defaults)}parseMarkdown(e){return(t,n)=>{let r={...n},i={...this.defaults,...r},a=this.onError(!!i.silent,!!i.async);if(this.defaults.async===!0&&r.async===!1)return a(Error(`marked(): The async option was set to true by an extension. Remove async: false from the parse options object to return a Promise.`));if(typeof t>`u`||t===null)return a(Error(`marked(): input parameter is undefined or null`));if(typeof t!=`string`)return a(Error(`marked(): input parameter is of type `+Object.prototype.toString.call(t)+`, string expected`));if(i.hooks&&(i.hooks.options=i,i.hooks.block=e),i.async)return(async()=>{let n=i.hooks?await i.hooks.preprocess(t):t,r=await(i.hooks?await i.hooks.provideLexer(e):e?Dn.lex:Dn.lexInline)(n,i),a=i.hooks?await i.hooks.processAllTokens(r):r;i.walkTokens&&await Promise.all(this.walkTokens(a,i.walkTokens));let o=await(i.hooks?await i.hooks.provideParser(e):e?An.parse:An.parseInline)(a,i);return i.hooks?await i.hooks.postprocess(o):o})().catch(a);try{i.hooks&&(t=i.hooks.preprocess(t));let n=(i.hooks?i.hooks.provideLexer(e):e?Dn.lex:Dn.lexInline)(t,i);i.hooks&&(n=i.hooks.processAllTokens(n)),i.walkTokens&&this.walkTokens(n,i.walkTokens);let r=(i.hooks?i.hooks.provideParser(e):e?An.parse:An.parseInline)(n,i);return i.hooks&&(r=i.hooks.postprocess(r)),r}catch(e){return a(e)}}}onError(e,t){return n=>{if(n.message+=`
Please report this to https://github.com/markedjs/marked.`,e){let e=`<p>An error occurred:</p><pre>`+A(n.message+``,!0)+`</pre>`;return t?Promise.resolve(e):e}if(t)return Promise.reject(n);throw n}}};function j(e,t){return Mn.parse(e,t)}j.options=j.setOptions=function(e){return Mn.setOptions(e),j.defaults=Mn.defaults,lt(j.defaults),j},j.getDefaults=st,j.defaults=ct,j.use=function(...e){return Mn.use(...e),j.defaults=Mn.defaults,lt(j.defaults),j},j.walkTokens=function(e,t){return Mn.walkTokens(e,t)},j.parseInline=Mn.parseInline,j.Parser=An,j.parser=An.parse,j.Renderer=On,j.TextRenderer=kn,j.Lexer=Dn,j.lexer=Dn.lex,j.Tokenizer=En,j.Hooks=jn,j.parse=j,j.options,j.setOptions,j.use,j.walkTokens,j.parseInline,An.parse,Dn.lex;function Nn(e){return new Dn().lex(e)}var Pn=()=>({heading:Pe,blockquote:xe,list:_e,list_item:ye,br:Ce,code:fe,codespan:me,table:Qe,html:Le,paragraph:Ue,link:Ve,text:qe,def:De,del:Te,em:ke,hr:Ie,strong:Ke,image:ze,space:We,escape:Ae}),Fn=()=>({baseUrl:`/`,slugger:new at});function In(){let e=console.warn;console.warn=t=>{t.includes(`unknown prop`)||t.includes(`unexpected slot`)||e(t)},ne(()=>{console.warn=e})}function Ln(e,t){y(t,!1),In();let n=w(t,`source`,8),r=w(t,`options`,24,()=>({})),i=w(t,`renderers`,24,()=>({})),o=ie(),c=ie(),l=ie();g(()=>(a(n()),a(i()),a(r())),()=>{b(o,Nn(n())),b(c,{...Pn(),...i()}),b(l,{...Fn(),...r()})}),f(),x(),he(e,{get tokens(){return s(o)},get renderers(){return s(c)},get options(){return s(l)}}),T()}var Rn=Ln,zn={joinUrlPaths:je,isRelative:Me,generatePathSegment:Ne};function Bn(){return{convert:e=>{if(e==null)return null;switch(typeof e){case`boolean`:return e;case`string`:{let t=e.toLowerCase().trim();return t===`true`||t===`t`||t===`1`}case`number`:return e!==0;default:return null}},asString:e=>String(e),type:e=>e.boolean().optional()}}function Vn(e,t){let n=n=>t.convert(n[e]);return{name:Hn(e),key:e,asEnv:n=>({[e]:t.asString(n)}),zod:{type:t.type},get:n,getOrDefault:(e,t)=>n(e)??t}}function Hn(e){return e.toLowerCase().replace(/([-_][a-z])/gi,e=>e.toUpperCase().replace(`-`,``).replace(`_`,``))}Vn(`MAGIDOC_GENERATE`,Bn());function Un(e){return{convert:e=>{if(Array.isArray(e))return e;if(typeof e==`string`){let t=JSON.parse(e);return Array.isArray(t)?t:null}return null},asString:e=>JSON.stringify(e),type:t=>t.array(e(t)).optional()}}function Wn(e){return{convert:e=>{if(typeof e==`object`)return e;if(typeof e==`string`){let t=JSON.parse(e);return typeof t==`object`?t:null}return null},asString:e=>JSON.stringify(e),type:t=>t.record(t.string(),e(t).optional()).optional()}}function M(){return{convert:e=>e==null||e===``?null:String(e),asString:e=>String(e),type:e=>e.string().optional()}}function Gn(e){return{convert:t=>t==null||!e.find(e=>e===t)?null:t,asString:e=>String(e),type:t=>t.enum(e).optional()}}var Kn={APP_LOGO:Vn(`APP_LOGO`,M()),APP_TITLE:Vn(`APP_TITLE`,M()),APP_FAVICON:Vn(`APP_FAVICON`,M()),SITE_ROOT:Vn(`SITE_ROOT`,M()),SITE_META:Vn(`SITE_META`,Wn(e=>e.string())),CUSTOM_STYLES:Vn(`CUSTOM_STYLES`,Un(e=>e.string())),FIELDS_SORTING:Vn(`FIELDS_SORTING`,Gn([`default`,`alphabetical`])),ARGUMENTS_SORTING:Vn(`ARGUMENTS_SORTING`,Gn([`default`,`alphabetical`])),QUERY_GENERATION_FACTORIES:Vn(`QUERY_GENERATION_FACTORIES`,Wn(e=>e.union([e.string(),e.boolean(),e.number(),e.null(),e.record(e.string(),e.unknown())]))),PAGES:Vn(`PAGES`,Un(e=>{let t=e.lazy(()=>e.object({title:e.string().min(1),content:e.union([e.array(t),e.string().min(1)])}));return t})),EXTERNAL_LINKS:Vn(`EXTERNAL_LINKS`,Un(e=>e.object({label:e.string().min(1),href:e.string().min(1),position:e.union([e.literal(`header`),e.literal(`navigation`)]).optional(),kind:e.string().min(1).optional(),group:e.string().min(1).optional()}))),DIRECTIVES:Vn(`DIRECTIVES`,Un(e=>e.object({name:e.string().min(1),args:e.array(e.string())})))},qn={APP_TITLE:`VFF - API GraphQL`,APP_LOGO:`/vff.png`,APP_FAVICON:`/docs/graphql/favicon.ico`,SITE_ROOT:`/docs/graphql`,SITE_META:`{"description":"Référence de l'API GraphQL de VFF - Voies Ferrées de France : lignes, gares, chronologies, signalisation, itinéraires historiques.","og:title":"VFF - API GraphQL","theme-color":"#ff6500"}`,CUSTOM_STYLES:`["/docs/graphql/vff.css"]`,FIELDS_SORTING:`default`,ARGUMENTS_SORTING:`default`,QUERY_GENERATION_FACTORIES:`{"Upload":null}`,PAGES:'[{"title":"Guide","content":[{"title":"Introduction","content":"\\n# API GraphQL de VFF\\n\\n**VFF - Voies Ferrées de France** est l\'encyclopédie collaborative du rail français. Son API\\nGraphQL donne accès aux lignes, gares, tronçons, chronologies, signalisation, matériels roulants\\net accidents, à la comparaison de deux ères, ainsi qu\'au calcul d\'itinéraires historiques.\\n\\n- Point d\'accès : `POST https://vff-project.org/graphql` (corps JSON `{\\"query\\": \\"…\\", \\"variables\\": {…}}`).\\n- Essais en direct : [GraphiQL](https://vff-project.org/graphiql).\\n- Lecture seule, sans clé obligatoire : 300 requêtes par minute et par adresse IP sans jeton,\\n  davantage avec un jeton personnel (voir « Authentification et quotas »).\\n- Schéma complet au format SDL : [/docs/schema.graphql](https://vff-project.org/docs/schema.graphql).\\n\\n## Authentification et quotas\\n\\nGraphQL s\'appelle sans jeton. Un **jeton personnel**, facultatif, relève la limite de débit : un\\nmembre le crée depuis son [profil](https://vff-project.org/profile) (section « Jetons d\'accès à l\'API »). Il ne\\nsert qu\'à lire, comme l\'accès anonyme, et n\'est affiché qu\'une fois. Il s\'envoie dans l\'en-tête\\n`Authorization: Bearer <jeton>`.\\n\\n| Profil | Limite | Compteur |\\n| --- | --- | --- |\\n| Sans jeton | 300 requêtes par minute | par adresse IP |\\n| Jeton d\'un membre | 1 200 requêtes par minute | par compte, tous ses jetons ensemble |\\n| Jeton d\'un compte partenaire | 6 000 requêtes par minute | par compte (profil accordé par l\'équipe de VFF) |\\n\\nLe compteur est commun à GraphQL, à l\'API REST et au serveur MCP. Chaque réponse porte\\n`X-RateLimit-Limit` (la limite de l\'appelant) et `X-RateLimit-Remaining` (requêtes restantes dans\\nla minute). Ces refus sont des réponses HTTP, avant toute exécution de la requête GraphQL :\\n\\n- **HTTP 429** avec `Retry-After` (attente en secondes) : limite atteinte ;\\n- **HTTP 401** (`{\\"error\\": \\"invalid_token\\"}`) : jeton inconnu, révoqué ou expiré ; l\'appel n\'est\\n  pas servi en anonyme, retirez l\'en-tête pour revenir à l\'accès sans jeton ;\\n- **HTTP 403** (`{\\"error\\": \\"account_suspended\\"}`) : jeton d\'un compte suspendu, refusé jusqu\'à la\\n  fin de la suspension.\\n\\n```bash\\n# Sans jeton\\ncurl -i -X POST https://vff-project.org/graphql \\\\\\n  -H \\"Content-Type: application/json\\" \\\\\\n  -d \'{\\"query\\": \\"{ stats { lines_count } }\\"}\'\\n# Avec un jeton\\ncurl -i -X POST https://vff-project.org/graphql \\\\\\n  -H \\"Authorization: Bearer $VFF_TOKEN\\" -H \\"Content-Type: application/json\\" \\\\\\n  -d \'{\\"query\\": \\"{ stats { lines_count } }\\"}\'\\n```\\n\\n```javascript\\nconst token = process.env.VFF_TOKEN;\\nconst response = await fetch(\\"https://vff-project.org/graphql\\", {\\n    method: \\"POST\\",\\n    headers: {\\n        \\"Content-Type\\": \\"application/json\\",\\n        ...(token ? { Authorization: `Bearer ${token}` } : {}),\\n    },\\n    body: JSON.stringify({ query: \\"{ stats { lines_count } }\\" }),\\n});\\nconsole.info(response.status, response.headers.get(\\"X-RateLimit-Remaining\\"));\\nconsole.info((await response.json()).data.stats.lines_count);\\n```\\n\\n```python\\nimport os\\n\\nimport requests\\n\\nheaders = {}\\nif os.environ.get(\\"VFF_TOKEN\\"):\\n    headers[\\"Authorization\\"] = f\\"Bearer {os.environ[\'VFF_TOKEN\']}\\"\\nresponse = requests.post(\\n    \\"https://vff-project.org/graphql\\",\\n    json={\\"query\\": \\"{ stats { lines_count } }\\"},\\n    headers=headers,\\n    timeout=30,\\n)\\nprint(response.status_code, response.headers[\\"X-RateLimit-Remaining\\"])\\nprint(response.json()[\\"data\\"][\\"stats\\"][\\"lines_count\\"])\\n```\\n\\n## Ères\\n\\nLe réseau est décrit par **ères décennales**, de 1830 à 2030. Chaque ère est un instantané au\\n**31/12** de son année : l\'ère 1930 montre le réseau au 31/12/1930. L\'ère **2030** désigne le\\nréseau **d\'aujourd\'hui**, recalculé au jour même. Les arguments `era` attendent cette année\\n(`era: 1930`) ; sans `era`, c\'est le réseau actuel.\\n\\nLes dates d\'événements restent exactes : `LineEvent.event_date` se lit avec\\n`date_precision` (`day` : jj/mm/aaaa, `month` : mm/aaaa, `year` : aaaa).\\n\\n## Unités\\n\\nDistances et points kilométriques (PK) en **mètres** (`pk_m: 373450` = PK 373+450), vitesses\\nen km/h, tensions en volts, écartements en millimètres, coordonnées WGS 84 en degrés décimaux.\\n\\n## Exemple\\n\\n```graphql\\n{\\n  line(slug: \\"rfn-420000\\") {\\n    name\\n    code\\n    stops(era: 1930) {\\n      position\\n      pk_m\\n      effective_status\\n      station { name city }\\n    }\\n    events { type event_date date_precision description source_url }\\n  }\\n}\\n```\\n\\n## Listes paginées\\n\\n`lines` et `stations` (recherche simple par nom) sont paginées par page : arguments `first` (20 par\\ndéfaut, 100 au plus) et `page` (à partir de 1) ; résultats dans `data`, compteurs dans\\n`paginatorInfo` (`total`, `lastPage`, `hasMorePages`…). Pour les annuaires complets, filtrés et\\ntriés comme sur le site, préférez `lineDirectory` et `stationDirectory` (par curseur, voir plus\\nbas). `materials` se lit par curseur, comme `lineEvents` et `media`. Les filtres textuels ignorent\\nla casse et acceptent le joker `%` :\\n\\n```graphql\\n{\\n  lines(name: \\"%brest%\\", first: 10) {\\n    data { slug name network }\\n    paginatorInfo { total lastPage }\\n  }\\n}\\n```\\n\\n## Événements, chronologie des gares et médias : pagination par curseur\\n\\n`lineEvents` (événements datés de toutes les lignes) et `media` (photos, cartes postales, plans,\\ndocuments) sont paginées **par curseur**, à la manière de Relay : arguments `first` (25 ou 20 par\\ndéfaut, 100 au plus) et `after`, résultats dans `edges { node }`, curseur de la page suivante dans\\n`pageInfo { hasNextPage endCursor }`. Passez `endCursor` dans `after` tant que `hasNextPage` est\\nvrai. Le tri est fixe (chronologique pour les événements, par identifiant pour les médias) et\\ns\'inverse avec `filter: { order: DESC }`. Les filtres se passent dans l\'argument `filter` ; une\\nvaleur invalide (type inconnu, date mal formée, ligne inconnue) donne une erreur de validation :\\n`errors[0].extensions.validation` détaille chaque champ fautif.\\n\\n```graphql\\n{\\n  lineEvents(first: 5, filter: { department: \\"29\\", from: \\"1930\\", to: \\"1939\\", type: \\"closure_passenger\\" }) {\\n    pageInfo { hasNextPage endCursor }\\n    edges {\\n      node {\\n        type event_date date_precision description source_quote\\n        provenance { name license license_status }\\n        source_url\\n        line { slug name }\\n      }\\n    }\\n  }\\n\\n  media(first: 3, filter: { station: \\"brest\\", rights_status: \\"public_domain\\" }) {\\n    edges { node { title thumb_url attribution license source_url depicted_year date_precision } }\\n  }\\n\\n  station(slug: \\"brest\\") {\\n    events { type event_date source is_inferred provenance { provider license } }\\n  }\\n}\\n```\\n\\nChaque événement garde sa source (`source`, `source_url`, `source_quote`) et la licence de cette\\nsource (`provenance`). Chaque média porte son auteur, sa licence, une ligne de crédit prête à\\nafficher (`attribution`) et le lien vers sa page d\'origine (`source_url`, sur Wikimedia Commons\\npour les médias Commons). `Line.media` et `Station.media` donnent les médias d\'une entité, aussi\\npar curseur.\\n\\n## Annuaires, fiches complètes, époques et sources\\n\\n`lineDirectory` et `stationDirectory` reprennent les annuaires du site (pages /lines et /stations) :\\nmêmes filtres, même tri, état de chaque ligne à l\'ère de référence. Ils se lisent par curseur :\\npassez `next_cursor` dans `after` (c\'est le même curseur que `meta.next_cursor` de l\'API REST).\\n`facets` donne les compteurs de la recherche, calculés seulement si vous les demandez.\\n\\n`Line.sheet` et `Station.sheet` rendent la fiche complète d\'une ligne ou d\'une gare, celle des pages\\ndu site : chiffres clés, électrification, signalisation, phases datées, chronique sourcée, gares,\\nsections nommées pour une ligne ; présence par décennie, lignes desservies, sources et gares\\nvoisines pour une gare. Les parties très imbriquées (phases, chronique, décennies) sont des objets\\n`JSON`, décrits champ par champ dans la référence REST. Le calcul d\'une fiche de ligne prend\\njusqu\'à quelques secondes : demandez-en une à la fois.\\n\\nDans la fiche d\'une ligne, `description` est un extrait de Wikipédia sous CC BY-SA 4.0 : `description_source`\\ndonne l\'auteur collectif, l\'article, le permalien de la version lue, la licence et une mention prête à\\ncopier. Dans celle d\'une gare, chaque entrée de `sources` garde sa provenance brute : identifiant dans\\nla source, date de lecture (`fetched_at`), attributs reçus tels quels (`raw`), méthode de rapprochement.\\n\\n`stats` donne les chiffres d\'ensemble du site (lignes, gares, matériels, kilomètres exploités) et la\\ncourbe du réseau à chaque ère ; `search(query: \\"brest\\")` cherche une ligne, une gare ou un matériel par\\nson nom ; `Line.pk(lat:, lng:)` convertit des coordonnées en point kilométrique sur la ligne.\\n\\n`Line.segments(era: 1930)` donne les tronçons d\'une ère avec leur provenance, `Era.stats` l\'état du\\nréseau à une époque (longueurs, lignes actives, gares par statut) et `sources` le registre des\\nfournisseurs de données avec leur licence et la mention à reproduire.\\n\\n`eraComparison(from: 1930, to: 1980)` résume ce qui a changé entre deux ères : kilomètres et nombre\\nde lignes ouvertes (`opened`), fermées voie en place (`closed`), déposées ou disparues\\n(`removed`) et restées en service (`kept`), avec la longueur en service à chaque date\\n(`open_km`). Mêmes chiffres et mêmes noms que `GET /api/v1/eras/{year}/compare/{other}`.\\n\\n`accidents` et `accident(slug:)` donnent les accidents et événements graves du rail (date et sa\\nprécision, lieu, ligne rattachée et portion, bilan humain et sa source, sources lues avec leur\\nlicence), avec les mêmes filtres que `GET /api/v1/accidents` ; `Line.accidents` liste ceux d\'une\\nligne. Une fiche dont `review_status` vaut `to_review` a un point incertain : à présenter comme\\ntel.\\n\\n```graphql\\n{\\n  lineDirectory(filter: { department: \\"29\\", status: \\"closed\\", sort: \\"code\\" }, first: 5) {\\n    data { slug name state traffic }\\n    next_cursor\\n    facets { total networks { code count } }\\n  }\\n\\n  line(slug: \\"rfn-447000\\") {\\n    sheet { key_figures { length_km station_count } today { state } electrification { state } }\\n    segments(era: 1930) { length_m status provenance { provider license } }\\n  }\\n\\n  era(year: 1930) { snapshot_date stats { open_km active_lines stations_by_status { code count } } }\\n\\n  stats { lines_count stations_count total_km profile { year km lines } }\\n\\n  search(query: \\"brest\\", limit: 3) { lines { slug name } stations { slug city } }\\n\\n  line(slug: \\"rfn-420000\\") {\\n    pk(lat: 48.10, lng: -1.67) { pk_label snap { distance_from_input_m } }\\n    sheet { description description_source { author permalink license attribution } }\\n  }\\n\\n  station(slug: \\"brest\\") { sheet { sources { source ref fetched_at match_method raw } } }\\n\\n  eraComparison(from: 1930, to: 1980) {\\n    changes { closed { km lines } removed { km lines } }\\n    open_km { from to }\\n  }\\n\\n  accidents(first: 3, filter: { kind: \\"derailment\\", min_deaths: 10, sort: \\"-deaths\\" }) {\\n    data { slug name date date_precision deaths review_status }\\n  }\\n\\n  source(provider: \\"osm\\") { name licence mention }\\n}\\n```\\n\\n## Erreurs\\n\\nUne requête invalide ou une valeur de filtre hors limites renvoie HTTP 200 avec un tableau `errors`\\n(convention GraphQL) : `message` et, pour une validation, `extensions.validation`. Au-delà de la\\nlimite de requêtes par minute, HTTP 429 (en-tête `Retry-After`) ; un jeton invalide donne HTTP 401\\n(voir « Authentification et quotas »). Un identifiant inconnu\\n(`lineEvent(id: …)`, `mediaItem(id: …)`, `line(slug: …)`) donne `null`, pas une erreur.\\n\\n## Itinéraires historiques\\n\\n```graphql\\n{\\n  route(from: \\"rennes\\", to: \\"brest\\", era: 1930) {\\n    era_year\\n    distance_m\\n    lines { line_name length_m }\\n    stations { name }\\n  }\\n}\\n```\\n\\n## Licences et sources\\n\\nDonnées ouvertes : citez « VFF - Voies Ferrées de France » et un lien vers\\n[vff-project.org](https://vff-project.org). Les données agrègent plusieurs sources dont les\\nlicences s\'appliquent : SNCF Open Data et OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0),\\nOpenHistoricalMap et Wikidata (CC0), Wikipédia (CC BY-SA). Les relevés tiers signalés par\\n`source` = `archeoferroviaire` sont hors ODbL : usage non commercial uniquement, crédit et lien\\nobligatoires. La\\nprovenance de chaque tracé (`LineSegment.source`) et de chaque événement\\n(`LineEvent.source`, `source_url`) est indiquée.\\n\\n## API REST\\n\\nLes mêmes données existent en REST, avec GeoJSON, tuiles vectorielles MVT et export GTFS :\\nréférence [Scalar](https://vff-project.org/docs/api), [Swagger UI](https://vff-project.org/docs/swagger) et spécification\\n[OpenAPI](https://vff-project.org/docs/api.json).\\n"}]}]',EXTERNAL_LINKS:`[{"label":"REST (Scalar)","href":"https://vff-project.org/docs/api","position":"navigation","group":"Voir aussi"},{"label":"REST (Swagger UI)","href":"https://vff-project.org/docs/swagger","position":"navigation","group":"Voir aussi"},{"label":"OpenAPI (JSON)","href":"https://vff-project.org/docs/api.json","position":"navigation","group":"Voir aussi"},{"label":"GraphiQL","href":"https://vff-project.org/graphiql","position":"navigation","group":"Voir aussi"},{"label":"Accès à l'API","href":"https://vff-project.org/api","position":"navigation","group":"Voir aussi"},{"label":"Site VFF","href":"https://vff-project.org/","position":"navigation","group":"Voir aussi"}]`,MAGIDOC_GENERATE:`true`};function Jn(e){return e.get(qn)}function Yn(e,t){return e.getOrDefault(qn,t)}function Xn(){return Yn(Kn.SITE_ROOT,ue)}function N(e,t){if(!e)throw Error(t)}function Zn(e){return typeof e==`object`&&!!e}function Qn(e,t){if(!e)throw Error(t??`Unexpected invariant triggered.`)}var $n=/\r\n|[\n\r]/g;function er(e,t){let n=0,r=1;for(let i of e.body.matchAll($n)){if(typeof i.index==`number`||Qn(!1),i.index>=t)break;n=i.index+i[0].length,r+=1}return{line:r,column:t+1-n}}function tr(e){return nr(e.source,er(e.source,e.start))}function nr(e,t){let n=e.locationOffset.column-1,r=``.padStart(n)+e.body,i=t.line-1,a=e.locationOffset.line-1,o=t.line+a,s=t.line===1?n:0,c=t.column+s,l=`${e.name}:${o}:${c}\n`,u=r.split(/\r\n|[\n\r]/g),d=u[i];if(d.length>120){let e=Math.floor(c/80),t=c%80,n=[];for(let e=0;e<d.length;e+=80)n.push(d.slice(e,e+80));return l+rr([[`${o} |`,n[0]],...n.slice(1,e+1).map(e=>[`|`,e]),[`|`,`^`.padStart(t)],[`|`,n[e+1]]])}return l+rr([[`${o-1} |`,u[i-1]],[`${o} |`,d],[`|`,`^`.padStart(c)],[`${o+1} |`,u[i+1]]])}function rr(e){let t=e.filter(([e,t])=>t!==void 0),n=Math.max(...t.map(([e])=>e.length));return t.map(([e,t])=>e.padStart(n)+(t?` `+t:``)).join(`
`)}function ir(e){let t=e[0];return t==null||`kind`in t||`length`in t?{nodes:t,source:e[1],positions:e[2],path:e[3],originalError:e[4],extensions:e[5]}:t}var P=class e extends Error{constructor(t,...n){let{nodes:r,source:i,positions:a,path:o,originalError:s,extensions:c}=ir(n);super(t),this.name=`GraphQLError`,this.path=o??void 0,this.originalError=s??void 0,this.nodes=ar(Array.isArray(r)?r:r?[r]:void 0);let l=ar(this.nodes?.map(e=>e.loc).filter(e=>e!=null));this.source=i??l?.[0]?.source,this.positions=a??l?.map(e=>e.start),this.locations=a&&i?a.map(e=>er(i,e)):l?.map(e=>er(e.source,e.start));let u=Zn(s?.extensions)?s?.extensions:void 0;this.extensions=c??u??Object.create(null),Object.defineProperties(this,{message:{writable:!0,enumerable:!0},name:{enumerable:!1},nodes:{enumerable:!1},source:{enumerable:!1},positions:{enumerable:!1},originalError:{enumerable:!1}}),s!=null&&s.stack?Object.defineProperty(this,`stack`,{value:s.stack,writable:!0,configurable:!0}):Error.captureStackTrace?Error.captureStackTrace(this,e):Object.defineProperty(this,`stack`,{value:Error().stack,writable:!0,configurable:!0})}get[Symbol.toStringTag](){return`GraphQLError`}toString(){let e=this.message;if(this.nodes)for(let t of this.nodes)t.loc&&(e+=`

`+tr(t.loc));else if(this.source&&this.locations)for(let t of this.locations)e+=`

`+nr(this.source,t);return e}toJSON(){let e={message:this.message};return this.locations!=null&&(e.locations=this.locations),this.path!=null&&(e.path=this.path),this.extensions!=null&&Object.keys(this.extensions).length>0&&(e.extensions=this.extensions),e}};function ar(e){return e===void 0||e.length===0?void 0:e}function or(e,t,n){return new P(`Syntax Error: ${n}`,{source:e,positions:[t]})}var sr=class{constructor(e,t,n){this.start=e.start,this.end=t.end,this.startToken=e,this.endToken=t,this.source=n}get[Symbol.toStringTag](){return`Location`}toJSON(){return{start:this.start,end:this.end}}},cr=class{constructor(e,t,n,r,i,a){this.kind=e,this.start=t,this.end=n,this.line=r,this.column=i,this.value=a,this.prev=null,this.next=null}get[Symbol.toStringTag](){return`Token`}toJSON(){return{kind:this.kind,value:this.value,line:this.line,column:this.column}}},lr={Name:[],Document:[`definitions`],OperationDefinition:[`description`,`name`,`variableDefinitions`,`directives`,`selectionSet`],VariableDefinition:[`description`,`variable`,`type`,`defaultValue`,`directives`],Variable:[`name`],SelectionSet:[`selections`],Field:[`alias`,`name`,`arguments`,`directives`,`selectionSet`],Argument:[`name`,`value`],FragmentSpread:[`name`,`directives`],InlineFragment:[`typeCondition`,`directives`,`selectionSet`],FragmentDefinition:[`description`,`name`,`variableDefinitions`,`typeCondition`,`directives`,`selectionSet`],IntValue:[],FloatValue:[],StringValue:[],BooleanValue:[],NullValue:[],EnumValue:[],ListValue:[`values`],ObjectValue:[`fields`],ObjectField:[`name`,`value`],Directive:[`name`,`arguments`],NamedType:[`name`],ListType:[`type`],NonNullType:[`type`],SchemaDefinition:[`description`,`directives`,`operationTypes`],OperationTypeDefinition:[`type`],ScalarTypeDefinition:[`description`,`name`,`directives`],ObjectTypeDefinition:[`description`,`name`,`interfaces`,`directives`,`fields`],FieldDefinition:[`description`,`name`,`arguments`,`type`,`directives`],InputValueDefinition:[`description`,`name`,`type`,`defaultValue`,`directives`],InterfaceTypeDefinition:[`description`,`name`,`interfaces`,`directives`,`fields`],UnionTypeDefinition:[`description`,`name`,`directives`,`types`],EnumTypeDefinition:[`description`,`name`,`directives`,`values`],EnumValueDefinition:[`description`,`name`,`directives`],InputObjectTypeDefinition:[`description`,`name`,`directives`,`fields`],DirectiveDefinition:[`description`,`name`,`arguments`,`directives`,`locations`],SchemaExtension:[`directives`,`operationTypes`],DirectiveExtension:[`name`,`directives`],ScalarTypeExtension:[`name`,`directives`],ObjectTypeExtension:[`name`,`interfaces`,`directives`,`fields`],InterfaceTypeExtension:[`name`,`interfaces`,`directives`,`fields`],UnionTypeExtension:[`name`,`directives`,`types`],EnumTypeExtension:[`name`,`directives`,`values`],InputObjectTypeExtension:[`name`,`directives`,`fields`],TypeCoordinate:[`name`],MemberCoordinate:[`name`,`memberName`],ArgumentCoordinate:[`name`,`fieldName`,`argumentName`],DirectiveCoordinate:[`name`],DirectiveArgumentCoordinate:[`name`,`argumentName`]},ur=new Set(Object.keys(lr));function dr(e){let t=e?.kind;return typeof t==`string`&&ur.has(t)}var fr;(function(e){e.QUERY=`query`,e.MUTATION=`mutation`,e.SUBSCRIPTION=`subscription`})(fr||={});var F;(function(e){e.QUERY=`QUERY`,e.MUTATION=`MUTATION`,e.SUBSCRIPTION=`SUBSCRIPTION`,e.FIELD=`FIELD`,e.FRAGMENT_DEFINITION=`FRAGMENT_DEFINITION`,e.FRAGMENT_SPREAD=`FRAGMENT_SPREAD`,e.INLINE_FRAGMENT=`INLINE_FRAGMENT`,e.VARIABLE_DEFINITION=`VARIABLE_DEFINITION`,e.SCHEMA=`SCHEMA`,e.SCALAR=`SCALAR`,e.OBJECT=`OBJECT`,e.FIELD_DEFINITION=`FIELD_DEFINITION`,e.ARGUMENT_DEFINITION=`ARGUMENT_DEFINITION`,e.INTERFACE=`INTERFACE`,e.UNION=`UNION`,e.ENUM=`ENUM`,e.ENUM_VALUE=`ENUM_VALUE`,e.INPUT_OBJECT=`INPUT_OBJECT`,e.INPUT_FIELD_DEFINITION=`INPUT_FIELD_DEFINITION`,e.DIRECTIVE_DEFINITION=`DIRECTIVE_DEFINITION`})(F||={});var I;(function(e){e.NAME=`Name`,e.DOCUMENT=`Document`,e.OPERATION_DEFINITION=`OperationDefinition`,e.VARIABLE_DEFINITION=`VariableDefinition`,e.SELECTION_SET=`SelectionSet`,e.FIELD=`Field`,e.ARGUMENT=`Argument`,e.FRAGMENT_SPREAD=`FragmentSpread`,e.INLINE_FRAGMENT=`InlineFragment`,e.FRAGMENT_DEFINITION=`FragmentDefinition`,e.VARIABLE=`Variable`,e.INT=`IntValue`,e.FLOAT=`FloatValue`,e.STRING=`StringValue`,e.BOOLEAN=`BooleanValue`,e.NULL=`NullValue`,e.ENUM=`EnumValue`,e.LIST=`ListValue`,e.OBJECT=`ObjectValue`,e.OBJECT_FIELD=`ObjectField`,e.DIRECTIVE=`Directive`,e.NAMED_TYPE=`NamedType`,e.LIST_TYPE=`ListType`,e.NON_NULL_TYPE=`NonNullType`,e.SCHEMA_DEFINITION=`SchemaDefinition`,e.OPERATION_TYPE_DEFINITION=`OperationTypeDefinition`,e.SCALAR_TYPE_DEFINITION=`ScalarTypeDefinition`,e.OBJECT_TYPE_DEFINITION=`ObjectTypeDefinition`,e.FIELD_DEFINITION=`FieldDefinition`,e.INPUT_VALUE_DEFINITION=`InputValueDefinition`,e.INTERFACE_TYPE_DEFINITION=`InterfaceTypeDefinition`,e.UNION_TYPE_DEFINITION=`UnionTypeDefinition`,e.ENUM_TYPE_DEFINITION=`EnumTypeDefinition`,e.ENUM_VALUE_DEFINITION=`EnumValueDefinition`,e.INPUT_OBJECT_TYPE_DEFINITION=`InputObjectTypeDefinition`,e.DIRECTIVE_DEFINITION=`DirectiveDefinition`,e.SCHEMA_EXTENSION=`SchemaExtension`,e.DIRECTIVE_EXTENSION=`DirectiveExtension`,e.SCALAR_TYPE_EXTENSION=`ScalarTypeExtension`,e.OBJECT_TYPE_EXTENSION=`ObjectTypeExtension`,e.INTERFACE_TYPE_EXTENSION=`InterfaceTypeExtension`,e.UNION_TYPE_EXTENSION=`UnionTypeExtension`,e.ENUM_TYPE_EXTENSION=`EnumTypeExtension`,e.INPUT_OBJECT_TYPE_EXTENSION=`InputObjectTypeExtension`,e.TYPE_COORDINATE=`TypeCoordinate`,e.MEMBER_COORDINATE=`MemberCoordinate`,e.ARGUMENT_COORDINATE=`ArgumentCoordinate`,e.DIRECTIVE_COORDINATE=`DirectiveCoordinate`,e.DIRECTIVE_ARGUMENT_COORDINATE=`DirectiveArgumentCoordinate`})(I||={});function pr(e){return e===9||e===32}function mr(e){return e>=48&&e<=57}function hr(e){return e>=97&&e<=122||e>=65&&e<=90}function gr(e){return hr(e)||e===95}function _r(e){return hr(e)||mr(e)||e===95}function vr(e){let t=2**53-1,n=null,r=-1;for(let i=0;i<e.length;++i){let a=e[i],o=yr(a);o!==a.length&&(n??=i,r=i,i!==0&&o<t&&(t=o))}return e.map((e,n)=>n===0?e:e.slice(t)).slice(n??0,r+1)}function yr(e){let t=0;for(;t<e.length&&pr(e.charCodeAt(t));)++t;return t}function br(e,t){let n=e.replace(/"""/g,`\\"""`),r=n.split(/\r\n|[\n\r]/g),i=r.length===1,a=r.length>1&&r.slice(1).every(e=>e.length===0||pr(e.charCodeAt(0))),o=n.endsWith(`\\"""`),s=e.endsWith(`"`)&&!o,c=e.endsWith(`\\`),l=s||c,u=!(t!=null&&t.minimize)&&(!i||e.length>70||l||a||o),d=``,f=i&&pr(e.charCodeAt(0));return(u&&!f||a)&&(d+=`
`),d+=n,(u||l)&&(d+=`
`),`"""`+d+`"""`}var L;(function(e){e.SOF=`<SOF>`,e.EOF=`<EOF>`,e.BANG=`!`,e.DOLLAR=`$`,e.AMP=`&`,e.PAREN_L=`(`,e.PAREN_R=`)`,e.DOT=`.`,e.SPREAD=`...`,e.COLON=`:`,e.EQUALS=`=`,e.AT=`@`,e.BRACKET_L=`[`,e.BRACKET_R=`]`,e.BRACE_L=`{`,e.PIPE=`|`,e.BRACE_R=`}`,e.NAME=`Name`,e.INT=`Int`,e.FLOAT=`Float`,e.STRING=`String`,e.BLOCK_STRING=`BlockString`,e.COMMENT=`Comment`})(L||={});var xr=class{constructor(e){let t=new cr(L.SOF,0,0,0,0);this.source=e,this.lastToken=t,this.token=t,this.line=1,this.lineStart=0}get[Symbol.toStringTag](){return`Lexer`}advance(){return this.lastToken=this.token,this.token=this.lookahead()}lookahead(){let e=this.token;if(e.kind!==L.EOF)do if(e.next)e=e.next;else{let t=Or(this,e.end);e.next=t,t.prev=e,e=t}while(e.kind===L.COMMENT);return e}};function Sr(e){return e===L.BANG||e===L.DOLLAR||e===L.AMP||e===L.PAREN_L||e===L.PAREN_R||e===L.DOT||e===L.SPREAD||e===L.COLON||e===L.EQUALS||e===L.AT||e===L.BRACKET_L||e===L.BRACKET_R||e===L.BRACE_L||e===L.PIPE||e===L.BRACE_R}function Cr(e){return e>=0&&e<=55295||e>=57344&&e<=1114111}function wr(e,t){return Tr(e.charCodeAt(t))&&Er(e.charCodeAt(t+1))}function Tr(e){return e>=55296&&e<=56319}function Er(e){return e>=56320&&e<=57343}function Dr(e,t){let n=e.source.body.codePointAt(t);if(n===void 0)return L.EOF;if(n>=32&&n<=126){let e=String.fromCodePoint(n);return e===`"`?`'"'`:`"${e}"`}return`U+`+n.toString(16).toUpperCase().padStart(4,`0`)}function R(e,t,n,r,i){let a=e.line;return new cr(t,n,r,a,1+n-e.lineStart,i)}function Or(e,t){let n=e.source.body,r=n.length,i=t;for(;i<r;){let t=n.charCodeAt(i);switch(t){case 65279:case 9:case 32:case 44:++i;continue;case 10:++i,++e.line,e.lineStart=i;continue;case 13:n.charCodeAt(i+1)===10?i+=2:++i,++e.line,e.lineStart=i;continue;case 35:return kr(e,i);case 33:return R(e,L.BANG,i,i+1);case 36:return R(e,L.DOLLAR,i,i+1);case 38:return R(e,L.AMP,i,i+1);case 40:return R(e,L.PAREN_L,i,i+1);case 41:return R(e,L.PAREN_R,i,i+1);case 46:if(n.charCodeAt(i+1)===46&&n.charCodeAt(i+2)===46)return R(e,L.SPREAD,i,i+3);break;case 58:return R(e,L.COLON,i,i+1);case 61:return R(e,L.EQUALS,i,i+1);case 64:return R(e,L.AT,i,i+1);case 91:return R(e,L.BRACKET_L,i,i+1);case 93:return R(e,L.BRACKET_R,i,i+1);case 123:return R(e,L.BRACE_L,i,i+1);case 124:return R(e,L.PIPE,i,i+1);case 125:return R(e,L.BRACE_R,i,i+1);case 34:return n.charCodeAt(i+1)===34&&n.charCodeAt(i+2)===34?Rr(e,i):Mr(e,i)}if(mr(t)||t===45)return Ar(e,i,t);if(gr(t))return z(e,i);throw or(e.source,i,t===39?`Unexpected single quote character ('), did you mean to use a double quote (")?`:Cr(t)||wr(n,i)?`Unexpected character: ${Dr(e,i)}.`:`Invalid character: ${Dr(e,i)}.`)}return R(e,L.EOF,r,r)}function kr(e,t){let n=e.source.body,r=n.length,i=t+1;for(;i<r;){let e=n.charCodeAt(i);if(e===10||e===13)break;if(Cr(e))++i;else if(wr(n,i))i+=2;else break}return R(e,L.COMMENT,t,i,n.slice(t+1,i))}function Ar(e,t,n){let r=e.source.body,i=t,a=n,o=!1;if(a===45&&(a=r.charCodeAt(++i)),a===48){if(a=r.charCodeAt(++i),mr(a))throw or(e.source,i,`Invalid number, unexpected digit after 0: ${Dr(e,i)}.`)}else i=jr(e,i,a),a=r.charCodeAt(i);if(a===46&&(o=!0,a=r.charCodeAt(++i),i=jr(e,i,a),a=r.charCodeAt(i)),(a===69||a===101)&&(o=!0,a=r.charCodeAt(++i),(a===43||a===45)&&(a=r.charCodeAt(++i)),i=jr(e,i,a),a=r.charCodeAt(i)),a===46||gr(a))throw or(e.source,i,`Invalid number, expected digit but got: ${Dr(e,i)}.`);return R(e,o?L.FLOAT:L.INT,t,i,r.slice(t,i))}function jr(e,t,n){if(!mr(n))throw or(e.source,t,`Invalid number, expected digit but got: ${Dr(e,t)}.`);let r=e.source.body,i=t+1;for(;mr(r.charCodeAt(i));)++i;return i}function Mr(e,t){let n=e.source.body,r=n.length,i=t+1,a=i,o=``;for(;i<r;){let r=n.charCodeAt(i);if(r===34)return o+=n.slice(a,i),R(e,L.STRING,t,i+1,o);if(r===92){o+=n.slice(a,i);let t=n.charCodeAt(i+1)===117?n.charCodeAt(i+2)===123?Nr(e,i):Pr(e,i):Lr(e,i);o+=t.value,i+=t.size,a=i;continue}if(r===10||r===13)break;if(Cr(r))++i;else if(wr(n,i))i+=2;else throw or(e.source,i,`Invalid character within String: ${Dr(e,i)}.`)}throw or(e.source,i,`Unterminated string.`)}function Nr(e,t){let n=e.source.body,r=0,i=3;for(;i<12;){let e=n.charCodeAt(t+ i++);if(e===125){if(i<5||!Cr(r))break;return{value:String.fromCodePoint(r),size:i}}if(r=r<<4|Ir(e),r<0)break}throw or(e.source,t,`Invalid Unicode escape sequence: "${n.slice(t,t+i)}".`)}function Pr(e,t){let n=e.source.body,r=Fr(n,t+2);if(Cr(r))return{value:String.fromCodePoint(r),size:6};if(Tr(r)&&n.charCodeAt(t+6)===92&&n.charCodeAt(t+7)===117){let e=Fr(n,t+8);if(Er(e))return{value:String.fromCodePoint(r,e),size:12}}throw or(e.source,t,`Invalid Unicode escape sequence: "${n.slice(t,t+6)}".`)}function Fr(e,t){return Ir(e.charCodeAt(t))<<12|Ir(e.charCodeAt(t+1))<<8|Ir(e.charCodeAt(t+2))<<4|Ir(e.charCodeAt(t+3))}function Ir(e){return e>=48&&e<=57?e-48:e>=65&&e<=70?e-55:e>=97&&e<=102?e-87:-1}function Lr(e,t){let n=e.source.body;switch(n.charCodeAt(t+1)){case 34:return{value:`"`,size:2};case 92:return{value:`\\`,size:2};case 47:return{value:`/`,size:2};case 98:return{value:`\b`,size:2};case 102:return{value:`\f`,size:2};case 110:return{value:`
`,size:2};case 114:return{value:`\r`,size:2};case 116:return{value:`	`,size:2}}throw or(e.source,t,`Invalid character escape sequence: "${n.slice(t,t+2)}".`)}function Rr(e,t){let n=e.source.body,r=n.length,i=e.lineStart,a=t+3,o=a,s=``,c=[];for(;a<r;){let r=n.charCodeAt(a);if(r===34&&n.charCodeAt(a+1)===34&&n.charCodeAt(a+2)===34){s+=n.slice(o,a),c.push(s);let r=R(e,L.BLOCK_STRING,t,a+3,vr(c).join(`
`));return e.line+=c.length-1,e.lineStart=i,r}if(r===92&&n.charCodeAt(a+1)===34&&n.charCodeAt(a+2)===34&&n.charCodeAt(a+3)===34){s+=n.slice(o,a),o=a+1,a+=4;continue}if(r===10||r===13){s+=n.slice(o,a),c.push(s),r===13&&n.charCodeAt(a+1)===10?a+=2:++a,s=``,o=a,i=a;continue}if(Cr(r))++a;else if(wr(n,a))a+=2;else throw or(e.source,a,`Invalid character within String: ${Dr(e,a)}.`)}throw or(e.source,a,`Unterminated string.`)}function z(e,t){let n=e.source.body,r=n.length,i=t+1;for(;i<r&&_r(n.charCodeAt(i));)++i;return R(e,L.NAME,t,i,n.slice(t,i))}var zr=10,Br=2;function B(e){return V(e,[])}function V(e,t){switch(typeof e){case`string`:return JSON.stringify(e);case`function`:return e.name?`[function ${e.name}]`:`[function]`;case`object`:return Vr(e,t);default:return String(e)}}function Vr(e,t){if(e===null)return`null`;if(t.includes(e))return`[Circular]`;let n=[...t,e];if(Hr(e)){let t=e.toJSON();if(t!==e)return typeof t==`string`?t:V(t,n)}else if(Array.isArray(e))return Wr(e,n);return Ur(e,n)}function Hr(e){return typeof e.toJSON==`function`}function Ur(e,t){let n=Object.entries(e);return n.length===0?`{}`:t.length>Br?`[`+Gr(e)+`]`:`{ `+n.map(([e,n])=>e+`: `+V(n,t)).join(`, `)+` }`}function Wr(e,t){if(e.length===0)return`[]`;if(t.length>Br)return`[Array]`;let n=Math.min(zr,e.length),r=e.length-n,i=[];for(let r=0;r<n;++r)i.push(V(e[r],t));return r===1?i.push(`... 1 more item`):r>1&&i.push(`... ${r} more items`),`[`+i.join(`, `)+`]`}function Gr(e){let t=Object.prototype.toString.call(e).replace(/^\[object /,``).replace(/]$/,``);if(t===`Object`&&typeof e.constructor==`function`){let t=e.constructor.name;if(typeof t==`string`&&t!==``)return t}return t}var Kr=globalThis.process?function(e,t){return e instanceof t}:function(e,t){if(e instanceof t)return!0;if(typeof e==`object`&&e){let n=t.prototype[Symbol.toStringTag];if(n===(Symbol.toStringTag in e?e[Symbol.toStringTag]:e.constructor?.name)){let t=B(e);throw Error(`Cannot use ${n} "${t}" from another module or realm.

Ensure that there is only one instance of "graphql" in the node_modules
directory. If different versions of "graphql" are the dependencies of other
relied on modules, use "resolutions" to ensure only one version is installed.

https://yarnpkg.com/en/docs/selective-version-resolutions

Duplicate "graphql" modules cannot be used at the same time since different
versions may have different capabilities and behavior. The data from one
version used in the function from another could produce confusing and
spurious results.`)}}return!1},qr=class{constructor(e,t=`GraphQL request`,n={line:1,column:1}){typeof e==`string`||N(!1,`Body must be a string. Received: ${B(e)}.`),this.body=e,this.name=t,this.locationOffset=n,this.locationOffset.line>0||N(!1,`line in locationOffset is 1-indexed and must be positive.`),this.locationOffset.column>0||N(!1,`column in locationOffset is 1-indexed and must be positive.`)}get[Symbol.toStringTag](){return`Source`}};function Jr(e){return Kr(e,qr)}function Yr(e,t){let n=new Xr(e,t),r=n.parseDocument();return Object.defineProperty(r,`tokenCount`,{enumerable:!1,value:n.tokenCount}),r}var Xr=class{constructor(e,t={}){let{lexer:n,...r}=t;if(n)this._lexer=n;else{let t=Jr(e)?e:new qr(e);this._lexer=new xr(t)}this._options=r,this._tokenCounter=0}get tokenCount(){return this._tokenCounter}parseName(){let e=this.expectToken(L.NAME);return this.node(e,{kind:I.NAME,value:e.value})}parseDocument(){return this.node(this._lexer.token,{kind:I.DOCUMENT,definitions:this.many(L.SOF,this.parseDefinition,L.EOF)})}parseDefinition(){if(this.peek(L.BRACE_L))return this.parseOperationDefinition();let e=this.peekDescription(),t=e?this._lexer.lookahead():this._lexer.token;if(e&&t.kind===L.BRACE_L)throw or(this._lexer.source,this._lexer.token.start,`Unexpected description, descriptions are not supported on shorthand queries.`);if(t.kind===L.NAME){switch(t.value){case`schema`:return this.parseSchemaDefinition();case`scalar`:return this.parseScalarTypeDefinition();case`type`:return this.parseObjectTypeDefinition();case`interface`:return this.parseInterfaceTypeDefinition();case`union`:return this.parseUnionTypeDefinition();case`enum`:return this.parseEnumTypeDefinition();case`input`:return this.parseInputObjectTypeDefinition();case`directive`:return this.parseDirectiveDefinition()}switch(t.value){case`query`:case`mutation`:case`subscription`:return this.parseOperationDefinition();case`fragment`:return this.parseFragmentDefinition()}if(e)throw or(this._lexer.source,this._lexer.token.start,`Unexpected description, only GraphQL definitions support descriptions.`);switch(t.value){case`extend`:return this.parseTypeSystemExtension()}}throw this.unexpected(t)}parseOperationDefinition(){let e=this._lexer.token;if(this.peek(L.BRACE_L))return this.node(e,{kind:I.OPERATION_DEFINITION,operation:fr.QUERY,description:void 0,name:void 0,variableDefinitions:[],directives:[],selectionSet:this.parseSelectionSet()});let t=this.parseDescription(),n=this.parseOperationType(),r;return this.peek(L.NAME)&&(r=this.parseName()),this.node(e,{kind:I.OPERATION_DEFINITION,operation:n,description:t,name:r,variableDefinitions:this.parseVariableDefinitions(),directives:this.parseDirectives(!1),selectionSet:this.parseSelectionSet()})}parseOperationType(){let e=this.expectToken(L.NAME);switch(e.value){case`query`:return fr.QUERY;case`mutation`:return fr.MUTATION;case`subscription`:return fr.SUBSCRIPTION}throw this.unexpected(e)}parseVariableDefinitions(){return this.optionalMany(L.PAREN_L,this.parseVariableDefinition,L.PAREN_R)}parseVariableDefinition(){return this.node(this._lexer.token,{kind:I.VARIABLE_DEFINITION,description:this.parseDescription(),variable:this.parseVariable(),type:(this.expectToken(L.COLON),this.parseTypeReference()),defaultValue:this.expectOptionalToken(L.EQUALS)?this.parseConstValueLiteral():void 0,directives:this.parseConstDirectives()})}parseVariable(){let e=this._lexer.token;return this.expectToken(L.DOLLAR),this.node(e,{kind:I.VARIABLE,name:this.parseName()})}parseSelectionSet(){return this.node(this._lexer.token,{kind:I.SELECTION_SET,selections:this.many(L.BRACE_L,this.parseSelection,L.BRACE_R)})}parseSelection(){return this.peek(L.SPREAD)?this.parseFragment():this.parseField()}parseField(){let e=this._lexer.token,t=this.parseName(),n,r;return this.expectOptionalToken(L.COLON)?(n=t,r=this.parseName()):r=t,this.node(e,{kind:I.FIELD,alias:n,name:r,arguments:this.parseArguments(!1),directives:this.parseDirectives(!1),selectionSet:this.peek(L.BRACE_L)?this.parseSelectionSet():void 0})}parseArguments(e){let t=e?this.parseConstArgument:this.parseArgument;return this.optionalMany(L.PAREN_L,t,L.PAREN_R)}parseArgument(e=!1){let t=this._lexer.token,n=this.parseName();return this.expectToken(L.COLON),this.node(t,{kind:I.ARGUMENT,name:n,value:this.parseValueLiteral(e)})}parseConstArgument(){return this.parseArgument(!0)}parseFragment(){let e=this._lexer.token;this.expectToken(L.SPREAD);let t=this.expectOptionalKeyword(`on`);return!t&&this.peek(L.NAME)?this.node(e,{kind:I.FRAGMENT_SPREAD,name:this.parseFragmentName(),directives:this.parseDirectives(!1)}):this.node(e,{kind:I.INLINE_FRAGMENT,typeCondition:t?this.parseNamedType():void 0,directives:this.parseDirectives(!1),selectionSet:this.parseSelectionSet()})}parseFragmentDefinition(){let e=this._lexer.token,t=this.parseDescription();return this.expectKeyword(`fragment`),this._options.allowLegacyFragmentVariables===!0?this.node(e,{kind:I.FRAGMENT_DEFINITION,description:t,name:this.parseFragmentName(),variableDefinitions:this.parseVariableDefinitions(),typeCondition:(this.expectKeyword(`on`),this.parseNamedType()),directives:this.parseDirectives(!1),selectionSet:this.parseSelectionSet()}):this.node(e,{kind:I.FRAGMENT_DEFINITION,description:t,name:this.parseFragmentName(),typeCondition:(this.expectKeyword(`on`),this.parseNamedType()),directives:this.parseDirectives(!1),selectionSet:this.parseSelectionSet()})}parseFragmentName(){if(this._lexer.token.value===`on`)throw this.unexpected();return this.parseName()}parseValueLiteral(e){let t=this._lexer.token;switch(t.kind){case L.BRACKET_L:return this.parseList(e);case L.BRACE_L:return this.parseObject(e);case L.INT:return this.advanceLexer(),this.node(t,{kind:I.INT,value:t.value});case L.FLOAT:return this.advanceLexer(),this.node(t,{kind:I.FLOAT,value:t.value});case L.STRING:case L.BLOCK_STRING:return this.parseStringLiteral();case L.NAME:switch(this.advanceLexer(),t.value){case`true`:return this.node(t,{kind:I.BOOLEAN,value:!0});case`false`:return this.node(t,{kind:I.BOOLEAN,value:!1});case`null`:return this.node(t,{kind:I.NULL});default:return this.node(t,{kind:I.ENUM,value:t.value})}case L.DOLLAR:if(e)if(this.expectToken(L.DOLLAR),this._lexer.token.kind===L.NAME){let e=this._lexer.token.value;throw or(this._lexer.source,t.start,`Unexpected variable "$${e}" in constant value.`)}else throw this.unexpected(t);return this.parseVariable();default:throw this.unexpected()}}parseConstValueLiteral(){return this.parseValueLiteral(!0)}parseStringLiteral(){let e=this._lexer.token;return this.advanceLexer(),this.node(e,{kind:I.STRING,value:e.value,block:e.kind===L.BLOCK_STRING})}parseList(e){return this.node(this._lexer.token,{kind:I.LIST,values:this.any(L.BRACKET_L,()=>this.parseValueLiteral(e),L.BRACKET_R)})}parseObject(e){return this.node(this._lexer.token,{kind:I.OBJECT,fields:this.any(L.BRACE_L,()=>this.parseObjectField(e),L.BRACE_R)})}parseObjectField(e){let t=this._lexer.token,n=this.parseName();return this.expectToken(L.COLON),this.node(t,{kind:I.OBJECT_FIELD,name:n,value:this.parseValueLiteral(e)})}parseDirectives(e){let t=[];for(;this.peek(L.AT);)t.push(this.parseDirective(e));return t}parseConstDirectives(){return this.parseDirectives(!0)}parseDirective(e){let t=this._lexer.token;return this.expectToken(L.AT),this.node(t,{kind:I.DIRECTIVE,name:this.parseName(),arguments:this.parseArguments(e)})}parseTypeReference(){let e=this._lexer.token,t;if(this.expectOptionalToken(L.BRACKET_L)){let n=this.parseTypeReference();this.expectToken(L.BRACKET_R),t=this.node(e,{kind:I.LIST_TYPE,type:n})}else t=this.parseNamedType();return this.expectOptionalToken(L.BANG)?this.node(e,{kind:I.NON_NULL_TYPE,type:t}):t}parseNamedType(){return this.node(this._lexer.token,{kind:I.NAMED_TYPE,name:this.parseName()})}peekDescription(){return this.peek(L.STRING)||this.peek(L.BLOCK_STRING)}parseDescription(){if(this.peekDescription())return this.parseStringLiteral()}parseSchemaDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`schema`);let n=this.parseConstDirectives(),r=this.many(L.BRACE_L,this.parseOperationTypeDefinition,L.BRACE_R);return this.node(e,{kind:I.SCHEMA_DEFINITION,description:t,directives:n,operationTypes:r})}parseOperationTypeDefinition(){let e=this._lexer.token,t=this.parseOperationType();this.expectToken(L.COLON);let n=this.parseNamedType();return this.node(e,{kind:I.OPERATION_TYPE_DEFINITION,operation:t,type:n})}parseScalarTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`scalar`);let n=this.parseName(),r=this.parseConstDirectives();return this.node(e,{kind:I.SCALAR_TYPE_DEFINITION,description:t,name:n,directives:r})}parseObjectTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`type`);let n=this.parseName(),r=this.parseImplementsInterfaces(),i=this.parseConstDirectives(),a=this.parseFieldsDefinition();return this.node(e,{kind:I.OBJECT_TYPE_DEFINITION,description:t,name:n,interfaces:r,directives:i,fields:a})}parseImplementsInterfaces(){return this.expectOptionalKeyword(`implements`)?this.delimitedMany(L.AMP,this.parseNamedType):[]}parseFieldsDefinition(){return this.optionalMany(L.BRACE_L,this.parseFieldDefinition,L.BRACE_R)}parseFieldDefinition(){let e=this._lexer.token,t=this.parseDescription(),n=this.parseName(),r=this.parseArgumentDefs();this.expectToken(L.COLON);let i=this.parseTypeReference(),a=this.parseConstDirectives();return this.node(e,{kind:I.FIELD_DEFINITION,description:t,name:n,arguments:r,type:i,directives:a})}parseArgumentDefs(){return this.optionalMany(L.PAREN_L,this.parseInputValueDef,L.PAREN_R)}parseInputValueDef(){let e=this._lexer.token,t=this.parseDescription(),n=this.parseName();this.expectToken(L.COLON);let r=this.parseTypeReference(),i;this.expectOptionalToken(L.EQUALS)&&(i=this.parseConstValueLiteral());let a=this.parseConstDirectives();return this.node(e,{kind:I.INPUT_VALUE_DEFINITION,description:t,name:n,type:r,defaultValue:i,directives:a})}parseInterfaceTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`interface`);let n=this.parseName(),r=this.parseImplementsInterfaces(),i=this.parseConstDirectives(),a=this.parseFieldsDefinition();return this.node(e,{kind:I.INTERFACE_TYPE_DEFINITION,description:t,name:n,interfaces:r,directives:i,fields:a})}parseUnionTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`union`);let n=this.parseName(),r=this.parseConstDirectives(),i=this.parseUnionMemberTypes();return this.node(e,{kind:I.UNION_TYPE_DEFINITION,description:t,name:n,directives:r,types:i})}parseUnionMemberTypes(){return this.expectOptionalToken(L.EQUALS)?this.delimitedMany(L.PIPE,this.parseNamedType):[]}parseEnumTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`enum`);let n=this.parseName(),r=this.parseConstDirectives(),i=this.parseEnumValuesDefinition();return this.node(e,{kind:I.ENUM_TYPE_DEFINITION,description:t,name:n,directives:r,values:i})}parseEnumValuesDefinition(){return this.optionalMany(L.BRACE_L,this.parseEnumValueDefinition,L.BRACE_R)}parseEnumValueDefinition(){let e=this._lexer.token,t=this.parseDescription(),n=this.parseEnumValueName(),r=this.parseConstDirectives();return this.node(e,{kind:I.ENUM_VALUE_DEFINITION,description:t,name:n,directives:r})}parseEnumValueName(){if(this._lexer.token.value===`true`||this._lexer.token.value===`false`||this._lexer.token.value===`null`)throw or(this._lexer.source,this._lexer.token.start,`${Zr(this._lexer.token)} is reserved and cannot be used for an enum value.`);return this.parseName()}parseInputObjectTypeDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`input`);let n=this.parseName(),r=this.parseConstDirectives(),i=this.parseInputFieldsDefinition();return this.node(e,{kind:I.INPUT_OBJECT_TYPE_DEFINITION,description:t,name:n,directives:r,fields:i})}parseInputFieldsDefinition(){return this.optionalMany(L.BRACE_L,this.parseInputValueDef,L.BRACE_R)}parseTypeSystemExtension(){let e=this._lexer.lookahead();if(e.kind===L.NAME)switch(e.value){case`schema`:return this.parseSchemaExtension();case`scalar`:return this.parseScalarTypeExtension();case`type`:return this.parseObjectTypeExtension();case`interface`:return this.parseInterfaceTypeExtension();case`union`:return this.parseUnionTypeExtension();case`enum`:return this.parseEnumTypeExtension();case`input`:return this.parseInputObjectTypeExtension();case`directive`:if(this._options.experimentalDirectivesOnDirectiveDefinitions)return this.parseDirectiveDefinitionExtension();break}throw this.unexpected(e)}parseSchemaExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`schema`);let t=this.parseConstDirectives(),n=this.optionalMany(L.BRACE_L,this.parseOperationTypeDefinition,L.BRACE_R);if(t.length===0&&n.length===0)throw this.unexpected();return this.node(e,{kind:I.SCHEMA_EXTENSION,directives:t,operationTypes:n})}parseScalarTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`scalar`);let t=this.parseName(),n=this.parseConstDirectives();if(n.length===0)throw this.unexpected();return this.node(e,{kind:I.SCALAR_TYPE_EXTENSION,name:t,directives:n})}parseObjectTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`type`);let t=this.parseName(),n=this.parseImplementsInterfaces(),r=this.parseConstDirectives(),i=this.parseFieldsDefinition();if(n.length===0&&r.length===0&&i.length===0)throw this.unexpected();return this.node(e,{kind:I.OBJECT_TYPE_EXTENSION,name:t,interfaces:n,directives:r,fields:i})}parseInterfaceTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`interface`);let t=this.parseName(),n=this.parseImplementsInterfaces(),r=this.parseConstDirectives(),i=this.parseFieldsDefinition();if(n.length===0&&r.length===0&&i.length===0)throw this.unexpected();return this.node(e,{kind:I.INTERFACE_TYPE_EXTENSION,name:t,interfaces:n,directives:r,fields:i})}parseUnionTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`union`);let t=this.parseName(),n=this.parseConstDirectives(),r=this.parseUnionMemberTypes();if(n.length===0&&r.length===0)throw this.unexpected();return this.node(e,{kind:I.UNION_TYPE_EXTENSION,name:t,directives:n,types:r})}parseEnumTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`enum`);let t=this.parseName(),n=this.parseConstDirectives(),r=this.parseEnumValuesDefinition();if(n.length===0&&r.length===0)throw this.unexpected();return this.node(e,{kind:I.ENUM_TYPE_EXTENSION,name:t,directives:n,values:r})}parseInputObjectTypeExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`input`);let t=this.parseName(),n=this.parseConstDirectives(),r=this.parseInputFieldsDefinition();if(n.length===0&&r.length===0)throw this.unexpected();return this.node(e,{kind:I.INPUT_OBJECT_TYPE_EXTENSION,name:t,directives:n,fields:r})}parseDirectiveDefinitionExtension(){let e=this._lexer.token;this.expectKeyword(`extend`),this.expectKeyword(`directive`),this.expectToken(L.AT);let t=this.parseName(),n=this.parseConstDirectives();if(n.length===0)throw this.unexpected();return this.node(e,{kind:I.DIRECTIVE_EXTENSION,name:t,directives:n})}parseDirectiveDefinition(){let e=this._lexer.token,t=this.parseDescription();this.expectKeyword(`directive`),this.expectToken(L.AT);let n=this.parseName(),r=this.parseArgumentDefs(),i=this._options.experimentalDirectivesOnDirectiveDefinitions?this.parseConstDirectives():[],a=this.expectOptionalKeyword(`repeatable`);this.expectKeyword(`on`);let o=this.parseDirectiveLocations();return this.node(e,{kind:I.DIRECTIVE_DEFINITION,description:t,name:n,arguments:r,directives:i,repeatable:a,locations:o})}parseDirectiveLocations(){return this.delimitedMany(L.PIPE,this.parseDirectiveLocation)}parseDirectiveLocation(){let e=this._lexer.token,t=this.parseName();if(Object.prototype.hasOwnProperty.call(F,t.value))return t;throw this.unexpected(e)}parseSchemaCoordinate(){let e=this._lexer.token,t=this.expectOptionalToken(L.AT),n=this.parseName(),r;!t&&this.expectOptionalToken(L.DOT)&&(r=this.parseName());let i;return(t||r)&&this.expectOptionalToken(L.PAREN_L)&&(i=this.parseName(),this.expectToken(L.COLON),this.expectToken(L.PAREN_R)),t?i?this.node(e,{kind:I.DIRECTIVE_ARGUMENT_COORDINATE,name:n,argumentName:i}):this.node(e,{kind:I.DIRECTIVE_COORDINATE,name:n}):r?i?this.node(e,{kind:I.ARGUMENT_COORDINATE,name:n,fieldName:r,argumentName:i}):this.node(e,{kind:I.MEMBER_COORDINATE,name:n,memberName:r}):this.node(e,{kind:I.TYPE_COORDINATE,name:n})}node(e,t){return this._options.noLocation!==!0&&(t.loc=new sr(e,this._lexer.lastToken,this._lexer.source)),t}peek(e){return this._lexer.token.kind===e}expectToken(e){let t=this._lexer.token;if(t.kind===e)return this.advanceLexer(),t;throw or(this._lexer.source,t.start,`Expected ${Qr(e)}, found ${Zr(t)}.`)}expectOptionalToken(e){return this._lexer.token.kind===e?(this.advanceLexer(),!0):!1}expectKeyword(e){let t=this._lexer.token;if(t.kind===L.NAME&&t.value===e)this.advanceLexer();else throw or(this._lexer.source,t.start,`Expected "${e}", found ${Zr(t)}.`)}expectOptionalKeyword(e){let t=this._lexer.token;return t.kind===L.NAME&&t.value===e?(this.advanceLexer(),!0):!1}unexpected(e){let t=e??this._lexer.token;return or(this._lexer.source,t.start,`Unexpected ${Zr(t)}.`)}any(e,t,n){this.expectToken(e);let r=[];for(;!this.expectOptionalToken(n);)r.push(t.call(this));return r}optionalMany(e,t,n){if(this.expectOptionalToken(e)){let e=[];do e.push(t.call(this));while(!this.expectOptionalToken(n));return e}return[]}many(e,t,n){this.expectToken(e);let r=[];do r.push(t.call(this));while(!this.expectOptionalToken(n));return r}delimitedMany(e,t){this.expectOptionalToken(e);let n=[];do n.push(t.call(this));while(this.expectOptionalToken(e));return n}advanceLexer(){let{maxTokens:e}=this._options,t=this._lexer.advance();if(t.kind!==L.EOF&&(++this._tokenCounter,e!==void 0&&this._tokenCounter>e))throw or(this._lexer.source,t.start,`Document contains more that ${e} tokens. Parsing aborted.`)}};function Zr(e){let t=e.value;return Qr(e.kind)+(t==null?``:` "${t}"`)}function Qr(e){return Sr(e)?`"${e}"`:e}var $r=5;function ei(e,t){let[n,r]=t?[e,t]:[void 0,e],i=` Did you mean `;n&&(i+=n+` `);let a=r.map(e=>`"${e}"`);switch(a.length){case 0:return``;case 1:return i+a[0]+`?`;case 2:return i+a[0]+` or `+a[1]+`?`}let o=a.slice(0,$r),s=o.pop();return i+o.join(`, `)+`, or `+s+`?`}function ti(e){return e}function ni(e,t){let n=Object.create(null);for(let r of e)n[t(r)]=r;return n}function ri(e,t,n){let r=Object.create(null);for(let i of e)r[t(i)]=n(i);return r}function ii(e,t){let n=Object.create(null);for(let r of Object.keys(e))n[r]=t(e[r],r);return n}function ai(e,t){let n=0,r=0;for(;n<e.length&&r<t.length;){let i=e.charCodeAt(n),a=t.charCodeAt(r);if(ci(i)&&ci(a)){let o=0;do++n,o=o*10+i-oi,i=e.charCodeAt(n);while(ci(i)&&o>0);let s=0;do++r,s=s*10+a-oi,a=t.charCodeAt(r);while(ci(a)&&s>0);if(o<s)return-1;if(o>s)return 1}else{if(i<a)return-1;if(i>a)return 1;++n,++r}}return e.length-t.length}var oi=48,si=57;function ci(e){return!isNaN(e)&&oi<=e&&e<=si}function li(e,t){let n=Object.create(null),r=new ui(e),i=Math.floor(e.length*.4)+1;for(let e of t){let t=r.measure(e,i);t!==void 0&&(n[e]=t)}return Object.keys(n).sort((e,t)=>{let r=n[e]-n[t];return r===0?ai(e,t):r})}var ui=class{constructor(e){this._input=e,this._inputLowerCase=e.toLowerCase(),this._inputArray=di(this._inputLowerCase),this._rows=[Array(e.length+1).fill(0),Array(e.length+1).fill(0),Array(e.length+1).fill(0)]}measure(e,t){if(this._input===e)return 0;let n=e.toLowerCase();if(this._inputLowerCase===n)return 1;let r=di(n),i=this._inputArray;if(r.length<i.length){let e=r;r=i,i=e}let a=r.length,o=i.length;if(a-o>t)return;let s=this._rows;for(let e=0;e<=o;e++)s[0][e]=e;for(let e=1;e<=a;e++){let n=s[(e-1)%3],a=s[e%3],c=a[0]=e;for(let t=1;t<=o;t++){let o=r[e-1]===i[t-1]?0:1,l=Math.min(n[t]+1,a[t-1]+1,n[t-1]+o);if(e>1&&t>1&&r[e-1]===i[t-2]&&r[e-2]===i[t-1]){let n=s[(e-2)%3][t-2];l=Math.min(l,n+1)}l<c&&(c=l),a[t]=l}if(c>t)return}let c=s[a%3][o];return c<=t?c:void 0}};function di(e){let t=e.length,n=Array(t);for(let r=0;r<t;++r)n[r]=e.charCodeAt(r);return n}function fi(e){if(e==null)return Object.create(null);if(Object.getPrototypeOf(e)===null)return e;let t=Object.create(null);for(let[n,r]of Object.entries(e))t[n]=r;return t}function pi(e){return`"${e.replace(mi,hi)}"`}var mi=/[\x00-\x1f\x22\x5c\x7f-\x9f]/g;function hi(e){return gi[e.charCodeAt(0)]}var gi=`\\u0000.\\u0001.\\u0002.\\u0003.\\u0004.\\u0005.\\u0006.\\u0007.\\b.\\t.\\n.\\u000B.\\f.\\r.\\u000E.\\u000F.\\u0010.\\u0011.\\u0012.\\u0013.\\u0014.\\u0015.\\u0016.\\u0017.\\u0018.\\u0019.\\u001A.\\u001B.\\u001C.\\u001D.\\u001E.\\u001F...\\"..........................................................\\\\...................................\\u007F.\\u0080.\\u0081.\\u0082.\\u0083.\\u0084.\\u0085.\\u0086.\\u0087.\\u0088.\\u0089.\\u008A.\\u008B.\\u008C.\\u008D.\\u008E.\\u008F.\\u0090.\\u0091.\\u0092.\\u0093.\\u0094.\\u0095.\\u0096.\\u0097.\\u0098.\\u0099.\\u009A.\\u009B.\\u009C.\\u009D.\\u009E.\\u009F`.split(`.`),_i=Object.freeze({});function vi(e,t,n=lr){let r=new Map;for(let e of Object.values(I))r.set(e,bi(t,e));let i,a=Array.isArray(e),o=[e],s=-1,c=[],l=e,u,d,f=[],p=[];do{s++;let e=s===o.length,m=e&&c.length!==0;if(e){if(u=p.length===0?void 0:f[f.length-1],l=d,d=p.pop(),m)if(a){l=l.slice();let e=0;for(let[t,n]of c){let r=t-e;n===null?(l.splice(r,1),e++):l[r]=n}}else{l={...l};for(let[e,t]of c)l[e]=t}s=i.index,o=i.keys,c=i.edits,a=i.inArray,i=i.prev}else if(d){if(u=a?s:o[s],l=d[u],l==null)continue;f.push(u)}let h;if(!Array.isArray(l)){if(dr(l)||N(!1,`Invalid AST Node: ${B(l)}.`),h=(e?r.get(l.kind)?.leave:r.get(l.kind)?.enter)?.call(t,l,u,d,f,p),h===_i)break;if(h===!1){if(!e){f.pop();continue}}else if(h!==void 0&&(c.push([u,h]),!e))if(dr(h))l=h;else{f.pop();continue}}h===void 0&&m&&c.push([u,l]),e?f.pop():(i={inArray:a,index:s,keys:o,edits:c,prev:i},a=Array.isArray(l),o=a?l:n[l.kind]??[],s=-1,c=[],d&&p.push(d),d=l)}while(i!==void 0);return c.length===0?e:c[c.length-1][1]}function yi(e){let t=Array(e.length).fill(null),n=Object.create(null);for(let r of Object.values(I)){let i=!1,a=Array(e.length).fill(void 0),o=Array(e.length).fill(void 0);for(let t=0;t<e.length;++t){let{enter:n,leave:s}=bi(e[t],r);i||=n!=null||s!=null,a[t]=n,o[t]=s}i&&(n[r]={enter(...n){let r=n[0];for(let i=0;i<e.length;i++)if(t[i]===null){let o=a[i]?.apply(e[i],n);if(o===!1)t[i]=r;else if(o===_i)t[i]=_i;else if(o!==void 0)return o}},leave(...n){let r=n[0];for(let i=0;i<e.length;i++)if(t[i]===null){let r=o[i]?.apply(e[i],n);if(r===_i)t[i]=_i;else if(r!==void 0&&r!==!1)return r}else t[i]===r&&(t[i]=null)}})}return n}function bi(e,t){let n=e[t];return typeof n==`object`?n:typeof n==`function`?{enter:n,leave:void 0}:{enter:e.enter,leave:e.leave}}function xi(e){return vi(e,Ci)}var Si=80,Ci={Name:{leave:e=>e.value},Variable:{leave:e=>`$`+e.name},Document:{leave:e=>H(e.definitions,`

`)},OperationDefinition:{leave(e){let t=Ei(e.variableDefinitions)?U(`(
`,H(e.variableDefinitions,`
`),`
)`):U(`(`,H(e.variableDefinitions,`, `),`)`),n=U(``,e.description,`
`)+H([e.operation,H([e.name,t]),H(e.directives,` `)],` `);return(n===`query`?``:n+` `)+e.selectionSet}},VariableDefinition:{leave:({variable:e,type:t,defaultValue:n,directives:r,description:i})=>U(``,i,`
`)+e+`: `+t+U(` = `,n)+U(` `,H(r,` `))},SelectionSet:{leave:({selections:e})=>wi(e)},Field:{leave({alias:e,name:t,arguments:n,directives:r,selectionSet:i}){let a=U(``,e,`: `)+t,o=a+U(`(`,H(n,`, `),`)`);return o.length>Si&&(o=a+U(`(
`,Ti(H(n,`
`)),`
)`)),H([o,H(r,` `),i],` `)}},Argument:{leave:({name:e,value:t})=>e+`: `+t},FragmentSpread:{leave:({name:e,directives:t})=>`...`+e+U(` `,H(t,` `))},InlineFragment:{leave:({typeCondition:e,directives:t,selectionSet:n})=>H([`...`,U(`on `,e),H(t,` `),n],` `)},FragmentDefinition:{leave:({name:e,typeCondition:t,variableDefinitions:n,directives:r,selectionSet:i,description:a})=>U(``,a,`
`)+`fragment ${e}${U(`(`,H(n,`, `),`)`)} on ${t} ${U(``,H(r,` `),` `)}`+i},IntValue:{leave:({value:e})=>e},FloatValue:{leave:({value:e})=>e},StringValue:{leave:({value:e,block:t})=>t?br(e):pi(e)},BooleanValue:{leave:({value:e})=>e?`true`:`false`},NullValue:{leave:()=>`null`},EnumValue:{leave:({value:e})=>e},ListValue:{leave:({values:e})=>`[`+H(e,`, `)+`]`},ObjectValue:{leave:({fields:e})=>`{`+H(e,`, `)+`}`},ObjectField:{leave:({name:e,value:t})=>e+`: `+t},Directive:{leave:({name:e,arguments:t})=>`@`+e+U(`(`,H(t,`, `),`)`)},NamedType:{leave:({name:e})=>e},ListType:{leave:({type:e})=>`[`+e+`]`},NonNullType:{leave:({type:e})=>e+`!`},SchemaDefinition:{leave:({description:e,directives:t,operationTypes:n})=>U(``,e,`
`)+H([`schema`,H(t,` `),wi(n)],` `)},OperationTypeDefinition:{leave:({operation:e,type:t})=>e+`: `+t},ScalarTypeDefinition:{leave:({description:e,name:t,directives:n})=>U(``,e,`
`)+H([`scalar`,t,H(n,` `)],` `)},ObjectTypeDefinition:{leave:({description:e,name:t,interfaces:n,directives:r,fields:i})=>U(``,e,`
`)+H([`type`,t,U(`implements `,H(n,` & `)),H(r,` `),wi(i)],` `)},FieldDefinition:{leave:({description:e,name:t,arguments:n,type:r,directives:i})=>U(``,e,`
`)+t+(Ei(n)?U(`(
`,Ti(H(n,`
`)),`
)`):U(`(`,H(n,`, `),`)`))+`: `+r+U(` `,H(i,` `))},InputValueDefinition:{leave:({description:e,name:t,type:n,defaultValue:r,directives:i})=>U(``,e,`
`)+H([t+`: `+n,U(`= `,r),H(i,` `)],` `)},InterfaceTypeDefinition:{leave:({description:e,name:t,interfaces:n,directives:r,fields:i})=>U(``,e,`
`)+H([`interface`,t,U(`implements `,H(n,` & `)),H(r,` `),wi(i)],` `)},UnionTypeDefinition:{leave:({description:e,name:t,directives:n,types:r})=>U(``,e,`
`)+H([`union`,t,H(n,` `),U(`= `,H(r,` | `))],` `)},EnumTypeDefinition:{leave:({description:e,name:t,directives:n,values:r})=>U(``,e,`
`)+H([`enum`,t,H(n,` `),wi(r)],` `)},EnumValueDefinition:{leave:({description:e,name:t,directives:n})=>U(``,e,`
`)+H([t,H(n,` `)],` `)},InputObjectTypeDefinition:{leave:({description:e,name:t,directives:n,fields:r})=>U(``,e,`
`)+H([`input`,t,H(n,` `),wi(r)],` `)},DirectiveDefinition:{leave:({description:e,name:t,arguments:n,directives:r,repeatable:i,locations:a})=>U(``,e,`
`)+`directive @`+t+(Ei(n)?U(`(
`,Ti(H(n,`
`)),`
)`):U(`(`,H(n,`, `),`)`))+U(` `,H(r,` `))+(i?` repeatable`:``)+` on `+H(a,` | `)},SchemaExtension:{leave:({directives:e,operationTypes:t})=>H([`extend schema`,H(e,` `),wi(t)],` `)},ScalarTypeExtension:{leave:({name:e,directives:t})=>H([`extend scalar`,e,H(t,` `)],` `)},ObjectTypeExtension:{leave:({name:e,interfaces:t,directives:n,fields:r})=>H([`extend type`,e,U(`implements `,H(t,` & `)),H(n,` `),wi(r)],` `)},InterfaceTypeExtension:{leave:({name:e,interfaces:t,directives:n,fields:r})=>H([`extend interface`,e,U(`implements `,H(t,` & `)),H(n,` `),wi(r)],` `)},UnionTypeExtension:{leave:({name:e,directives:t,types:n})=>H([`extend union`,e,H(t,` `),U(`= `,H(n,` | `))],` `)},EnumTypeExtension:{leave:({name:e,directives:t,values:n})=>H([`extend enum`,e,H(t,` `),wi(n)],` `)},InputObjectTypeExtension:{leave:({name:e,directives:t,fields:n})=>H([`extend input`,e,H(t,` `),wi(n)],` `)},DirectiveExtension:{leave:({name:e,directives:t})=>H([`extend directive @`+e,H(t,` `)],` `)},TypeCoordinate:{leave:({name:e})=>e},MemberCoordinate:{leave:({name:e,memberName:t})=>H([e,U(`.`,t)])},ArgumentCoordinate:{leave:({name:e,fieldName:t,argumentName:n})=>H([e,U(`.`,t),U(`(`,n,`:)`)])},DirectiveCoordinate:{leave:({name:e})=>H([`@`,e])},DirectiveArgumentCoordinate:{leave:({name:e,argumentName:t})=>H([`@`,e,U(`(`,t,`:)`)])}};function H(e,t=``){return e?.filter(e=>e).join(t)??``}function wi(e){return U(`{
`,Ti(H(e,`
`)),`
}`)}function U(e,t,n=``){return t!=null&&t!==``?e+t+n:``}function Ti(e){return U(`  `,e.replace(/\n/g,`
  `))}function Ei(e){return e?.some(e=>e.includes(`
`))??!1}function Di(e,t){switch(e.kind){case I.NULL:return null;case I.INT:return parseInt(e.value,10);case I.FLOAT:return parseFloat(e.value);case I.STRING:case I.ENUM:case I.BOOLEAN:return e.value;case I.LIST:return e.values.map(e=>Di(e,t));case I.OBJECT:return ri(e.fields,e=>e.name.value,e=>Di(e.value,t));case I.VARIABLE:return t?.[e.name.value]}}function Oi(e){if(e??N(!1,`Must provide name.`),typeof e==`string`||N(!1,`Expected name to be a string.`),e.length===0)throw new P(`Expected name to be a non-empty string.`);for(let t=1;t<e.length;++t)if(!_r(e.charCodeAt(t)))throw new P(`Names must only contain [_a-zA-Z0-9] but "${e}" does not.`);if(!gr(e.charCodeAt(0)))throw new P(`Names must start with [_a-zA-Z] but "${e}" does not.`);return e}function ki(e){if(e===`true`||e===`false`||e===`null`)throw new P(`Enum values cannot be named: ${e}`);return Oi(e)}function Ai(e){return ji(e)||W(e)||G(e)||Mi(e)||Ni(e)||Pi(e)||Fi(e)||K(e)}function ji(e){return Kr(e,Yi)}function W(e){return Kr(e,Xi)}function G(e){return Kr(e,ia)}function Mi(e){return Kr(e,aa)}function Ni(e){return Kr(e,sa)}function Pi(e){return Kr(e,ua)}function Fi(e){return Kr(e,Vi)}function K(e){return Kr(e,q)}function Ii(e){return ji(e)||Ni(e)||Pi(e)||Hi(e)&&Ii(e.ofType)}function Li(e){return ji(e)||W(e)||G(e)||Mi(e)||Ni(e)||Hi(e)&&Li(e.ofType)}function Ri(e){return ji(e)||Ni(e)}function zi(e){return W(e)||G(e)||Mi(e)}function Bi(e){return G(e)||Mi(e)}var Vi=class{constructor(e){Ai(e)||N(!1,`Expected ${B(e)} to be a GraphQL type.`),this.ofType=e}get[Symbol.toStringTag](){return`GraphQLList`}toString(){return`[`+String(this.ofType)+`]`}toJSON(){return this.toString()}},q=class{constructor(e){Ui(e)||N(!1,`Expected ${B(e)} to be a GraphQL nullable type.`),this.ofType=e}get[Symbol.toStringTag](){return`GraphQLNonNull`}toString(){return String(this.ofType)+`!`}toJSON(){return this.toString()}};function Hi(e){return Fi(e)||K(e)}function Ui(e){return Ai(e)&&!K(e)}function Wi(e){if(e)return K(e)?e.ofType:e}function Gi(e){return ji(e)||W(e)||G(e)||Mi(e)||Ni(e)||Pi(e)}function Ki(e){if(e){let t=e;for(;Hi(t);)t=t.ofType;return t}}function qi(e){return typeof e==`function`?e():e}function Ji(e){return typeof e==`function`?e():e}var Yi=class{constructor(e){let t=e.parseValue??ti;this.name=Oi(e.name),this.description=e.description,this.specifiedByURL=e.specifiedByURL,this.serialize=e.serialize??ti,this.parseValue=t,this.parseLiteral=e.parseLiteral??((e,n)=>t(Di(e,n))),this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],e.specifiedByURL==null||typeof e.specifiedByURL==`string`||N(!1,`${this.name} must provide "specifiedByURL" as a string, but got: ${B(e.specifiedByURL)}.`),e.serialize==null||typeof e.serialize==`function`||N(!1,`${this.name} must provide "serialize" function. If this custom Scalar is also used as an input type, ensure "parseValue" and "parseLiteral" functions are also provided.`),e.parseLiteral&&(typeof e.parseValue==`function`&&typeof e.parseLiteral==`function`||N(!1,`${this.name} must provide both "parseValue" and "parseLiteral" functions.`))}get[Symbol.toStringTag](){return`GraphQLScalarType`}toConfig(){return{name:this.name,description:this.description,specifiedByURL:this.specifiedByURL,serialize:this.serialize,parseValue:this.parseValue,parseLiteral:this.parseLiteral,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return this.name}toJSON(){return this.toString()}},Xi=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.isTypeOf=e.isTypeOf,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this._fields=()=>Qi(e),this._interfaces=()=>Zi(e),e.isTypeOf==null||typeof e.isTypeOf==`function`||N(!1,`${this.name} must provide "isTypeOf" as a function, but got: ${B(e.isTypeOf)}.`)}get[Symbol.toStringTag](){return`GraphQLObjectType`}getFields(){return typeof this._fields==`function`&&(this._fields=this._fields()),this._fields}getInterfaces(){return typeof this._interfaces==`function`&&(this._interfaces=this._interfaces()),this._interfaces}toConfig(){return{name:this.name,description:this.description,interfaces:this.getInterfaces(),fields:ta(this.getFields()),isTypeOf:this.isTypeOf,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return this.name}toJSON(){return this.toString()}};function Zi(e){let t=qi(e.interfaces??[]);return Array.isArray(t)||N(!1,`${e.name} interfaces must be an Array or a function which returns an Array.`),t}function Qi(e){let t=Ji(e.fields);return ea(t)||N(!1,`${e.name} fields must be an object with field names as keys or a function which returns such an object.`),ii(t,(t,n)=>{ea(t)||N(!1,`${e.name}.${n} field config must be an object.`),t.resolve==null||typeof t.resolve==`function`||N(!1,`${e.name}.${n} field resolver must be a function if provided, but got: ${B(t.resolve)}.`);let r=t.args??{};return ea(r)||N(!1,`${e.name}.${n} args must be an object with argument names as keys.`),{name:Oi(n),description:t.description,type:t.type,args:$i(r),resolve:t.resolve,subscribe:t.subscribe,deprecationReason:t.deprecationReason,extensions:fi(t.extensions),astNode:t.astNode}})}function $i(e){return Object.entries(e).map(([e,t])=>({name:Oi(e),description:t.description,type:t.type,defaultValue:t.defaultValue,deprecationReason:t.deprecationReason,extensions:fi(t.extensions),astNode:t.astNode}))}function ea(e){return Zn(e)&&!Array.isArray(e)}function ta(e){return ii(e,e=>({description:e.description,type:e.type,args:na(e.args),resolve:e.resolve,subscribe:e.subscribe,deprecationReason:e.deprecationReason,extensions:e.extensions,astNode:e.astNode}))}function na(e){return ri(e,e=>e.name,e=>({description:e.description,type:e.type,defaultValue:e.defaultValue,deprecationReason:e.deprecationReason,extensions:e.extensions,astNode:e.astNode}))}function ra(e){return K(e.type)&&e.defaultValue===void 0}var ia=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.resolveType=e.resolveType,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this._fields=Qi.bind(void 0,e),this._interfaces=Zi.bind(void 0,e),e.resolveType==null||typeof e.resolveType==`function`||N(!1,`${this.name} must provide "resolveType" as a function, but got: ${B(e.resolveType)}.`)}get[Symbol.toStringTag](){return`GraphQLInterfaceType`}getFields(){return typeof this._fields==`function`&&(this._fields=this._fields()),this._fields}getInterfaces(){return typeof this._interfaces==`function`&&(this._interfaces=this._interfaces()),this._interfaces}toConfig(){return{name:this.name,description:this.description,interfaces:this.getInterfaces(),fields:ta(this.getFields()),resolveType:this.resolveType,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return this.name}toJSON(){return this.toString()}},aa=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.resolveType=e.resolveType,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this._types=oa.bind(void 0,e),e.resolveType==null||typeof e.resolveType==`function`||N(!1,`${this.name} must provide "resolveType" as a function, but got: ${B(e.resolveType)}.`)}get[Symbol.toStringTag](){return`GraphQLUnionType`}getTypes(){return typeof this._types==`function`&&(this._types=this._types()),this._types}toConfig(){return{name:this.name,description:this.description,types:this.getTypes(),resolveType:this.resolveType,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return this.name}toJSON(){return this.toString()}};function oa(e){let t=qi(e.types);return Array.isArray(t)||N(!1,`Must provide Array of types or a function which returns such an array for Union ${e.name}.`),t}var sa=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this._values=typeof e.values==`function`?e.values:la(this.name,e.values),this._valueLookup=null,this._nameLookup=null}get[Symbol.toStringTag](){return`GraphQLEnumType`}getValues(){return typeof this._values==`function`&&(this._values=la(this.name,this._values())),this._values}getValue(e){return this._nameLookup===null&&(this._nameLookup=ni(this.getValues(),e=>e.name)),this._nameLookup[e]}serialize(e){this._valueLookup===null&&(this._valueLookup=new Map(this.getValues().map(e=>[e.value,e])));let t=this._valueLookup.get(e);if(t===void 0)throw new P(`Enum "${this.name}" cannot represent value: ${B(e)}`);return t.name}parseValue(e){if(typeof e!=`string`){let t=B(e);throw new P(`Enum "${this.name}" cannot represent non-string value: ${t}.`+ca(this,t))}let t=this.getValue(e);if(t==null)throw new P(`Value "${e}" does not exist in "${this.name}" enum.`+ca(this,e));return t.value}parseLiteral(e,t){if(e.kind!==I.ENUM){let t=xi(e);throw new P(`Enum "${this.name}" cannot represent non-enum value: ${t}.`+ca(this,t),{nodes:e})}let n=this.getValue(e.value);if(n==null){let t=xi(e);throw new P(`Value "${t}" does not exist in "${this.name}" enum.`+ca(this,t),{nodes:e})}return n.value}toConfig(){let e=ri(this.getValues(),e=>e.name,e=>({description:e.description,value:e.value,deprecationReason:e.deprecationReason,extensions:e.extensions,astNode:e.astNode}));return{name:this.name,description:this.description,values:e,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return this.name}toJSON(){return this.toString()}};function ca(e,t){return ei(`the enum value`,li(t,e.getValues().map(e=>e.name)))}function la(e,t){return ea(t)||N(!1,`${e} values must be an object with value names as keys.`),Object.entries(t).map(([t,n])=>(ea(n)||N(!1,`${e}.${t} must refer to an object with a "value" key representing an internal value but got: ${B(n)}.`),{name:ki(t),description:n.description,value:n.value===void 0?t:n.value,deprecationReason:n.deprecationReason,extensions:fi(n.extensions),astNode:n.astNode}))}var ua=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this.isOneOf=e.isOneOf??!1,this._fields=da.bind(void 0,e)}get[Symbol.toStringTag](){return`GraphQLInputObjectType`}getFields(){return typeof this._fields==`function`&&(this._fields=this._fields()),this._fields}toConfig(){let e=ii(this.getFields(),e=>({description:e.description,type:e.type,defaultValue:e.defaultValue,deprecationReason:e.deprecationReason,extensions:e.extensions,astNode:e.astNode}));return{name:this.name,description:this.description,fields:e,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes,isOneOf:this.isOneOf}}toString(){return this.name}toJSON(){return this.toString()}};function da(e){let t=Ji(e.fields);return ea(t)||N(!1,`${e.name} fields must be an object with field names as keys or a function which returns such an object.`),ii(t,(t,n)=>(!(`resolve`in t)||N(!1,`${e.name}.${n} field has a resolve property, but Input Types cannot define resolvers.`),{name:Oi(n),description:t.description,type:t.type,defaultValue:t.defaultValue,deprecationReason:t.deprecationReason,extensions:fi(t.extensions),astNode:t.astNode}))}function fa(e){return K(e.type)&&e.defaultValue===void 0}function pa(e,t,n){return t===n?!0:K(n)?K(t)?pa(e,t.ofType,n.ofType):!1:K(t)?pa(e,t.ofType,n):Fi(n)?Fi(t)?pa(e,t.ofType,n.ofType):!1:Fi(t)?!1:Bi(n)&&(G(t)||W(t))&&e.isSubType(n,t)}function ma(e,t,n){return t===n?!0:Bi(t)?Bi(n)?e.getPossibleTypes(t).some(t=>e.isSubType(n,t)):e.isSubType(t,n):Bi(n)?e.isSubType(n,t):!1}var ha=new Yi({name:`Int`,description:"The `Int` scalar type represents non-fractional signed whole numeric values. Int can represent values between -(2^31) and 2^31 - 1.",serialize(e){let t=Sa(e);if(typeof t==`boolean`)return+!!t;let n=t;if(typeof t==`string`&&t!==``&&(n=Number(t)),typeof n!=`number`||!Number.isInteger(n))throw new P(`Int cannot represent non-integer value: ${B(t)}`);if(n>2147483647||n<-2147483648)throw new P(`Int cannot represent non 32-bit signed integer value: `+B(t));return n},parseValue(e){if(typeof e!=`number`||!Number.isInteger(e))throw new P(`Int cannot represent non-integer value: ${B(e)}`);if(e>2147483647||e<-2147483648)throw new P(`Int cannot represent non 32-bit signed integer value: ${e}`);return e},parseLiteral(e){if(e.kind!==I.INT)throw new P(`Int cannot represent non-integer value: ${xi(e)}`,{nodes:e});let t=parseInt(e.value,10);if(t>2147483647||t<-2147483648)throw new P(`Int cannot represent non 32-bit signed integer value: ${e.value}`,{nodes:e});return t}}),ga=new Yi({name:`Float`,description:"The `Float` scalar type represents signed double-precision fractional values as specified by [IEEE 754](https://en.wikipedia.org/wiki/IEEE_floating_point).",serialize(e){let t=Sa(e);if(typeof t==`boolean`)return+!!t;let n=t;if(typeof t==`string`&&t!==``&&(n=Number(t)),typeof n!=`number`||!Number.isFinite(n))throw new P(`Float cannot represent non numeric value: ${B(t)}`);return n},parseValue(e){if(typeof e!=`number`||!Number.isFinite(e))throw new P(`Float cannot represent non numeric value: ${B(e)}`);return e},parseLiteral(e){if(e.kind!==I.FLOAT&&e.kind!==I.INT)throw new P(`Float cannot represent non numeric value: ${xi(e)}`,e);return parseFloat(e.value)}}),_a=new Yi({name:`String`,description:"The `String` scalar type represents textual data, represented as UTF-8 character sequences. The String type is most often used by GraphQL to represent free-form human-readable text.",serialize(e){let t=Sa(e);if(typeof t==`string`)return t;if(typeof t==`boolean`)return t?`true`:`false`;if(typeof t==`number`&&Number.isFinite(t))return t.toString();throw new P(`String cannot represent value: ${B(e)}`)},parseValue(e){if(typeof e!=`string`)throw new P(`String cannot represent a non string value: ${B(e)}`);return e},parseLiteral(e){if(e.kind!==I.STRING)throw new P(`String cannot represent a non string value: ${xi(e)}`,{nodes:e});return e.value}}),va=new Yi({name:`Boolean`,description:"The `Boolean` scalar type represents `true` or `false`.",serialize(e){let t=Sa(e);if(typeof t==`boolean`)return t;if(Number.isFinite(t))return t!==0;throw new P(`Boolean cannot represent a non boolean value: ${B(t)}`)},parseValue(e){if(typeof e!=`boolean`)throw new P(`Boolean cannot represent a non boolean value: ${B(e)}`);return e},parseLiteral(e){if(e.kind!==I.BOOLEAN)throw new P(`Boolean cannot represent a non boolean value: ${xi(e)}`,{nodes:e});return e.value}}),ya=new Yi({name:`ID`,description:'The `ID` scalar type represents a unique identifier, often used to refetch an object or as key for a cache. The ID type appears in a JSON response as a String; however, it is not intended to be human-readable. When expected as an input type, any string (such as `"4"`) or integer (such as `4`) input value will be accepted as an ID.',serialize(e){let t=Sa(e);if(typeof t==`string`)return t;if(Number.isInteger(t))return String(t);throw new P(`ID cannot represent value: ${B(e)}`)},parseValue(e){if(typeof e==`string`)return e;if(typeof e==`number`&&Number.isInteger(e))return e.toString();throw new P(`ID cannot represent value: ${B(e)}`)},parseLiteral(e){if(e.kind!==I.STRING&&e.kind!==I.INT)throw new P(`ID cannot represent a non-string and non-integer value: `+xi(e),{nodes:e});return e.value}}),ba=Object.freeze([_a,ha,ga,va,ya]);function xa(e){return ba.some(({name:t})=>e.name===t)}function Sa(e){if(Zn(e)){if(typeof e.valueOf==`function`){let t=e.valueOf();if(!Zn(t))return t}if(typeof e.toJSON==`function`)return e.toJSON()}return e}function Ca(e){return Kr(e,wa)}var wa=class{constructor(e){this.name=Oi(e.name),this.description=e.description,this.locations=e.locations,this.isRepeatable=e.isRepeatable??!1,this.deprecationReason=e.deprecationReason,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],Array.isArray(e.locations)||N(!1,`@${e.name} locations must be an Array.`);let t=e.args??{};Zn(t)&&!Array.isArray(t)||N(!1,`@${e.name} args must be an object with argument names as keys.`),this.args=$i(t)}get[Symbol.toStringTag](){return`GraphQLDirective`}toConfig(){return{name:this.name,description:this.description,locations:this.locations,args:na(this.args),isRepeatable:this.isRepeatable,deprecationReason:this.deprecationReason,extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes}}toString(){return`@`+this.name}toJSON(){return this.toString()}},Ta=new wa({name:`include`,description:"Directs the executor to include this field or fragment only when the `if` argument is true.",locations:[F.FIELD,F.FRAGMENT_SPREAD,F.INLINE_FRAGMENT],args:{if:{type:new q(va),description:`Included when true.`}}}),Ea=new wa({name:`skip`,description:"Directs the executor to skip this field or fragment when the `if` argument is true.",locations:[F.FIELD,F.FRAGMENT_SPREAD,F.INLINE_FRAGMENT],args:{if:{type:new q(va),description:`Skipped when true.`}}}),J=new wa({name:`deprecated`,description:`Marks an element of a GraphQL schema as no longer supported.`,locations:[F.FIELD_DEFINITION,F.ARGUMENT_DEFINITION,F.INPUT_FIELD_DEFINITION,F.ENUM_VALUE,F.DIRECTIVE_DEFINITION],args:{reason:{type:_a,description:`Explains why this element was deprecated, usually also including a suggestion for how to access supported similar data. Formatted using the Markdown syntax, as specified by [CommonMark](https://commonmark.org/).`,defaultValue:`No longer supported`}}}),Da=new wa({name:`specifiedBy`,description:`Exposes a URL that specifies the behavior of this scalar.`,locations:[F.SCALAR],args:{url:{type:new q(_a),description:`The URL that specifies the behavior of this scalar.`}}}),Oa=new wa({name:`oneOf`,description:"Indicates exactly one field must be supplied and this field must not be `null`.",locations:[F.INPUT_OBJECT],args:{}}),ka=Object.freeze([Ta,Ea,J,Da,Oa]);function Aa(e){return typeof e==`object`&&typeof e?.[Symbol.iterator]==`function`}function ja(e,t){if(K(t)){let n=ja(e,t.ofType);return n?.kind===I.NULL?null:n}if(e===null)return{kind:I.NULL};if(e===void 0)return null;if(Fi(t)){let n=t.ofType;if(Aa(e)){let t=[];for(let r of e){let e=ja(r,n);e!=null&&t.push(e)}return{kind:I.LIST,values:t}}return ja(e,n)}if(Pi(t)){if(!Zn(e))return null;let n=[];for(let r of Object.values(t.getFields())){let t=ja(e[r.name],r.type);t&&n.push({kind:I.OBJECT_FIELD,name:{kind:I.NAME,value:r.name},value:t})}return{kind:I.OBJECT,fields:n}}if(Ri(t)){let n=t.serialize(e);if(n==null)return null;if(typeof n==`boolean`)return{kind:I.BOOLEAN,value:n};if(typeof n==`number`&&Number.isFinite(n)){let e=String(n);return Ma.test(e)?{kind:I.INT,value:e}:{kind:I.FLOAT,value:e}}if(typeof n==`string`)return Ni(t)?{kind:I.ENUM,value:n}:t===ya&&Ma.test(n)?{kind:I.INT,value:n}:{kind:I.STRING,value:n};throw TypeError(`Cannot convert value to AST: ${B(n)}.`)}Qn(!1,`Unexpected input type: `+B(t))}var Ma=/^-?(?:0|[1-9][0-9]*)$/,Na=new Xi({name:`__Schema`,description:`A GraphQL Schema defines the capabilities of a GraphQL server. It exposes all available types and directives on the server, as well as the entry points for query, mutation, and subscription operations.`,fields:()=>({description:{type:_a,resolve:e=>e.description},types:{description:`A list of all types supported by this server.`,type:new q(new Vi(new q(Ia))),resolve(e){return Object.values(e.getTypeMap())}},queryType:{description:`The type that query operations will be rooted at.`,type:new q(Ia),resolve:e=>e.getQueryType()},mutationType:{description:`If this server supports mutation, the type that mutation operations will be rooted at.`,type:Ia,resolve:e=>e.getMutationType()},subscriptionType:{description:`If this server support subscription, the type that subscription operations will be rooted at.`,type:Ia,resolve:e=>e.getSubscriptionType()},directives:{description:`A list of all directives supported by this server.`,type:new q(new Vi(new q(Pa))),args:{includeDeprecated:{type:new q(va),defaultValue:!1}},resolve:(e,{includeDeprecated:t})=>t?e.getDirectives():e.getDirectives().filter(e=>e.deprecationReason==null)}})}),Pa=new Xi({name:`__Directive`,description:`A Directive provides a way to describe alternate runtime execution and type validation behavior in a GraphQL document.

In some cases, you need to provide options to alter GraphQL's execution behavior in ways field arguments will not suffice, such as conditionally including or skipping a field. Directives provide this by describing additional information to the executor.`,fields:()=>({name:{type:new q(_a),resolve:e=>e.name},description:{type:_a,resolve:e=>e.description},isRepeatable:{type:new q(va),resolve:e=>e.isRepeatable},locations:{type:new q(new Vi(new q(Fa))),resolve:e=>e.locations},args:{type:new q(new Vi(new q(Ra))),args:{includeDeprecated:{type:va,defaultValue:!1}},resolve(e,{includeDeprecated:t}){return t?e.args:e.args.filter(e=>e.deprecationReason==null)}},isDeprecated:{type:new q(va),resolve:e=>e.deprecationReason!=null},deprecationReason:{type:_a,resolve:e=>e.deprecationReason}})}),Fa=new sa({name:`__DirectiveLocation`,description:`A Directive can be adjacent to many parts of the GraphQL language, a __DirectiveLocation describes one such possible adjacencies.`,values:{QUERY:{value:F.QUERY,description:`Location adjacent to a query operation.`},MUTATION:{value:F.MUTATION,description:`Location adjacent to a mutation operation.`},SUBSCRIPTION:{value:F.SUBSCRIPTION,description:`Location adjacent to a subscription operation.`},FIELD:{value:F.FIELD,description:`Location adjacent to a field.`},FRAGMENT_DEFINITION:{value:F.FRAGMENT_DEFINITION,description:`Location adjacent to a fragment definition.`},FRAGMENT_SPREAD:{value:F.FRAGMENT_SPREAD,description:`Location adjacent to a fragment spread.`},INLINE_FRAGMENT:{value:F.INLINE_FRAGMENT,description:`Location adjacent to an inline fragment.`},VARIABLE_DEFINITION:{value:F.VARIABLE_DEFINITION,description:`Location adjacent to a variable definition.`},SCHEMA:{value:F.SCHEMA,description:`Location adjacent to a schema definition.`},SCALAR:{value:F.SCALAR,description:`Location adjacent to a scalar definition.`},OBJECT:{value:F.OBJECT,description:`Location adjacent to an object type definition.`},FIELD_DEFINITION:{value:F.FIELD_DEFINITION,description:`Location adjacent to a field definition.`},ARGUMENT_DEFINITION:{value:F.ARGUMENT_DEFINITION,description:`Location adjacent to an argument definition.`},INTERFACE:{value:F.INTERFACE,description:`Location adjacent to an interface definition.`},UNION:{value:F.UNION,description:`Location adjacent to a union definition.`},ENUM:{value:F.ENUM,description:`Location adjacent to an enum definition.`},ENUM_VALUE:{value:F.ENUM_VALUE,description:`Location adjacent to an enum value definition.`},INPUT_OBJECT:{value:F.INPUT_OBJECT,description:`Location adjacent to an input object type definition.`},INPUT_FIELD_DEFINITION:{value:F.INPUT_FIELD_DEFINITION,description:`Location adjacent to an input object field definition.`},DIRECTIVE_DEFINITION:{value:F.DIRECTIVE_DEFINITION,description:`Location adjacent to a directive definition.`}}}),Ia=new Xi({name:`__Type`,description:"The fundamental unit of any GraphQL Schema is the type. There are many kinds of types in GraphQL as represented by the `__TypeKind` enum.\n\nDepending on the kind of a type, certain fields describe information about that type. Scalar types provide no information beyond a name, description and optional `specifiedByURL`, while Enum types provide their values. Object and Interface types provide the fields they describe. Abstract types, Union and Interface, provide the Object types possible at runtime. List and NonNull types compose other types.",fields:()=>({kind:{type:new q(Ba),resolve(e){if(ji(e))return Y.SCALAR;if(W(e))return Y.OBJECT;if(G(e))return Y.INTERFACE;if(Mi(e))return Y.UNION;if(Ni(e))return Y.ENUM;if(Pi(e))return Y.INPUT_OBJECT;if(Fi(e))return Y.LIST;if(K(e))return Y.NON_NULL;Qn(!1,`Unexpected type: "${B(e)}".`)}},name:{type:_a,resolve:e=>`name`in e?e.name:void 0},description:{type:_a,resolve:e=>`description`in e?e.description:void 0},specifiedByURL:{type:_a,resolve:e=>`specifiedByURL`in e?e.specifiedByURL:void 0},fields:{type:new Vi(new q(La)),args:{includeDeprecated:{type:va,defaultValue:!1}},resolve(e,{includeDeprecated:t}){if(W(e)||G(e)){let n=Object.values(e.getFields());return t?n:n.filter(e=>e.deprecationReason==null)}}},interfaces:{type:new Vi(new q(Ia)),resolve(e){if(W(e)||G(e))return e.getInterfaces()}},possibleTypes:{type:new Vi(new q(Ia)),resolve(e,t,n,{schema:r}){if(Bi(e))return r.getPossibleTypes(e)}},enumValues:{type:new Vi(new q(za)),args:{includeDeprecated:{type:va,defaultValue:!1}},resolve(e,{includeDeprecated:t}){if(Ni(e)){let n=e.getValues();return t?n:n.filter(e=>e.deprecationReason==null)}}},inputFields:{type:new Vi(new q(Ra)),args:{includeDeprecated:{type:va,defaultValue:!1}},resolve(e,{includeDeprecated:t}){if(Pi(e)){let n=Object.values(e.getFields());return t?n:n.filter(e=>e.deprecationReason==null)}}},ofType:{type:Ia,resolve:e=>`ofType`in e?e.ofType:void 0},isOneOf:{type:va,resolve:e=>{if(Pi(e))return e.isOneOf}}})}),La=new Xi({name:`__Field`,description:`Object and Interface types are described by a list of Fields, each of which has a name, potentially a list of arguments, and a return type.`,fields:()=>({name:{type:new q(_a),resolve:e=>e.name},description:{type:_a,resolve:e=>e.description},args:{type:new q(new Vi(new q(Ra))),args:{includeDeprecated:{type:va,defaultValue:!1}},resolve(e,{includeDeprecated:t}){return t?e.args:e.args.filter(e=>e.deprecationReason==null)}},type:{type:new q(Ia),resolve:e=>e.type},isDeprecated:{type:new q(va),resolve:e=>e.deprecationReason!=null},deprecationReason:{type:_a,resolve:e=>e.deprecationReason}})}),Ra=new Xi({name:`__InputValue`,description:`Arguments provided to Fields or Directives and the input fields of an InputObject are represented as Input Values which describe their type and optionally a default value.`,fields:()=>({name:{type:new q(_a),resolve:e=>e.name},description:{type:_a,resolve:e=>e.description},type:{type:new q(Ia),resolve:e=>e.type},defaultValue:{type:_a,description:`A GraphQL-formatted string representing the default value for this input value.`,resolve(e){let{type:t,defaultValue:n}=e,r=ja(n,t);return r?xi(r):null}},isDeprecated:{type:new q(va),resolve:e=>e.deprecationReason!=null},deprecationReason:{type:_a,resolve:e=>e.deprecationReason}})}),za=new Xi({name:`__EnumValue`,description:`One possible value for a given Enum. Enum values are unique values, not a placeholder for a string or numeric value. However an Enum value is returned in a JSON response as a string.`,fields:()=>({name:{type:new q(_a),resolve:e=>e.name},description:{type:_a,resolve:e=>e.description},isDeprecated:{type:new q(va),resolve:e=>e.deprecationReason!=null},deprecationReason:{type:_a,resolve:e=>e.deprecationReason}})}),Y;(function(e){e.SCALAR=`SCALAR`,e.OBJECT=`OBJECT`,e.INTERFACE=`INTERFACE`,e.UNION=`UNION`,e.ENUM=`ENUM`,e.INPUT_OBJECT=`INPUT_OBJECT`,e.LIST=`LIST`,e.NON_NULL=`NON_NULL`})(Y||={});var Ba=new sa({name:`__TypeKind`,description:"An enum describing what kind of type a given `__Type` is.",values:{SCALAR:{value:Y.SCALAR,description:`Indicates this type is a scalar.`},OBJECT:{value:Y.OBJECT,description:"Indicates this type is an object. `fields` and `interfaces` are valid fields."},INTERFACE:{value:Y.INTERFACE,description:"Indicates this type is an interface. `fields`, `interfaces`, and `possibleTypes` are valid fields."},UNION:{value:Y.UNION,description:"Indicates this type is a union. `possibleTypes` is a valid field."},ENUM:{value:Y.ENUM,description:"Indicates this type is an enum. `enumValues` is a valid field."},INPUT_OBJECT:{value:Y.INPUT_OBJECT,description:"Indicates this type is an input object. `inputFields` is a valid field."},LIST:{value:Y.LIST,description:"Indicates this type is a list. `ofType` is a valid field."},NON_NULL:{value:Y.NON_NULL,description:"Indicates this type is a non-null. `ofType` is a valid field."}}});new q(Na),new q(_a),new q(_a);var Va=Object.freeze([Na,Pa,Fa,Ia,La,Ra,za,Ba]);function Ha(e){return Va.some(({name:t})=>e.name===t)}var Ua=class{constructor(e){this.__validationErrors=e.assumeValid===!0?[]:void 0,Zn(e)||N(!1,`Must provide configuration object.`),!e.types||Array.isArray(e.types)||N(!1,`"types" must be Array if provided but got: ${B(e.types)}.`),!e.directives||Array.isArray(e.directives)||N(!1,`"directives" must be Array if provided but got: ${B(e.directives)}.`),this.description=e.description,this.extensions=fi(e.extensions),this.astNode=e.astNode,this.extensionASTNodes=e.extensionASTNodes??[],this._queryType=e.query,this._mutationType=e.mutation,this._subscriptionType=e.subscription,this._directives=e.directives??ka;let t=new Set(e.types);if(e.types!=null)for(let n of e.types)t.delete(n),Wa(n,t);this._queryType!=null&&Wa(this._queryType,t),this._mutationType!=null&&Wa(this._mutationType,t),this._subscriptionType!=null&&Wa(this._subscriptionType,t);for(let e of this._directives)if(Ca(e))for(let n of e.args)Wa(n.type,t);Wa(Na,t),this._typeMap=Object.create(null),this._subTypeMap=Object.create(null),this._implementationsMap=Object.create(null);for(let e of t){if(e==null)continue;let t=e.name;if(t||N(!1,`One of the provided types for building the Schema is missing a name.`),this._typeMap[t]!==void 0)throw Error(`Schema must contain uniquely named types but contains multiple types named "${t}".`);if(this._typeMap[t]=e,G(e)){for(let t of e.getInterfaces())if(G(t)){let n=this._implementationsMap[t.name];n===void 0&&(n=this._implementationsMap[t.name]={objects:[],interfaces:[]}),n.interfaces.push(e)}}else if(W(e)){for(let t of e.getInterfaces())if(G(t)){let n=this._implementationsMap[t.name];n===void 0&&(n=this._implementationsMap[t.name]={objects:[],interfaces:[]}),n.objects.push(e)}}}}get[Symbol.toStringTag](){return`GraphQLSchema`}getQueryType(){return this._queryType}getMutationType(){return this._mutationType}getSubscriptionType(){return this._subscriptionType}getRootType(e){switch(e){case fr.QUERY:return this.getQueryType();case fr.MUTATION:return this.getMutationType();case fr.SUBSCRIPTION:return this.getSubscriptionType()}}getTypeMap(){return this._typeMap}getType(e){return this.getTypeMap()[e]}getPossibleTypes(e){return Mi(e)?e.getTypes():this.getImplementations(e).objects}getImplementations(e){return this._implementationsMap[e.name]??{objects:[],interfaces:[]}}isSubType(e,t){let n=this._subTypeMap[e.name];if(n===void 0){if(n=Object.create(null),Mi(e))for(let t of e.getTypes())n[t.name]=!0;else{let t=this.getImplementations(e);for(let e of t.objects)n[e.name]=!0;for(let e of t.interfaces)n[e.name]=!0}this._subTypeMap[e.name]=n}return n[t.name]!==void 0}getDirectives(){return this._directives}getDirective(e){return this.getDirectives().find(t=>t.name===e)}toConfig(){return{description:this.description,query:this.getQueryType(),mutation:this.getMutationType(),subscription:this.getSubscriptionType(),types:Object.values(this.getTypeMap()),directives:this.getDirectives(),extensions:this.extensions,astNode:this.astNode,extensionASTNodes:this.extensionASTNodes,assumeValid:this.__validationErrors!==void 0}}};function Wa(e,t){let n=Ki(e);if(!t.has(n)){if(t.add(n),Mi(n))for(let e of n.getTypes())Wa(e,t);else if(W(n)||G(n)){for(let e of n.getInterfaces())Wa(e,t);for(let e of Object.values(n.getFields())){Wa(e.type,t);for(let n of e.args)Wa(n.type,t)}}else if(Pi(n))for(let e of Object.values(n.getFields()))Wa(e.type,t)}return t}function Ga(e,t){switch(t.kind){case I.LIST_TYPE:{let n=Ga(e,t.type);return n&&new Vi(n)}case I.NON_NULL_TYPE:{let n=Ga(e,t.type);return n&&new q(n)}case I.NAMED_TYPE:return e.getType(t.name.value)}}function Ka(e){return e.kind===I.OPERATION_DEFINITION||e.kind===I.FRAGMENT_DEFINITION}function qa(e){return e.kind===I.SCHEMA_DEFINITION||Ja(e)||e.kind===I.DIRECTIVE_DEFINITION}function Ja(e){return e.kind===I.SCALAR_TYPE_DEFINITION||e.kind===I.OBJECT_TYPE_DEFINITION||e.kind===I.INTERFACE_TYPE_DEFINITION||e.kind===I.UNION_TYPE_DEFINITION||e.kind===I.ENUM_TYPE_DEFINITION||e.kind===I.INPUT_OBJECT_TYPE_DEFINITION}function Ya(e){return e.kind===I.SCHEMA_EXTENSION||e.kind===I.DIRECTIVE_EXTENSION||Xa(e)}function Xa(e){return e.kind===I.SCALAR_TYPE_EXTENSION||e.kind===I.OBJECT_TYPE_EXTENSION||e.kind===I.INTERFACE_TYPE_EXTENSION||e.kind===I.UNION_TYPE_EXTENSION||e.kind===I.ENUM_TYPE_EXTENSION||e.kind===I.INPUT_OBJECT_TYPE_EXTENSION}function Za(e){return{Document(t){for(let n of t.definitions)if(!Ka(n)){let t=n.kind===I.SCHEMA_DEFINITION||n.kind===I.SCHEMA_EXTENSION?`schema`:`"`+n.name.value+`"`;e.reportError(new P(`The ${t} definition is not executable.`,{nodes:n}))}return!1}}}function Qa(e){return{Field(t){let n=e.getParentType();if(n&&!e.getFieldDef()){let r=e.getSchema(),i=t.name.value,a=ei(`to use an inline fragment on`,$a(r,n,i));a===``&&(a=ei(eo(n,i))),e.reportError(new P(`Cannot query field "${i}" on type "${n.name}".`+a,{nodes:t}))}}}}function $a(e,t,n){if(!Bi(t))return[];let r=new Set,i=Object.create(null);for(let a of e.getPossibleTypes(t))if(a.getFields()[n]){r.add(a),i[a.name]=1;for(let e of a.getInterfaces())e.getFields()[n]&&(r.add(e),i[e.name]=(i[e.name]??0)+1)}return[...r].sort((t,n)=>{let r=i[n.name]-i[t.name];return r===0?G(t)&&e.isSubType(t,n)?-1:G(n)&&e.isSubType(n,t)?1:ai(t.name,n.name):r}).map(e=>e.name)}function eo(e,t){return W(e)||G(e)?li(t,Object.keys(e.getFields())):[]}function to(e){return{InlineFragment(t){let n=t.typeCondition;if(n){let t=Ga(e.getSchema(),n);if(t&&!zi(t)){let t=xi(n);e.reportError(new P(`Fragment cannot condition on non composite type "${t}".`,{nodes:n}))}}},FragmentDefinition(t){let n=Ga(e.getSchema(),t.typeCondition);if(n&&!zi(n)){let n=xi(t.typeCondition);e.reportError(new P(`Fragment "${t.name.value}" cannot condition on non composite type "${n}".`,{nodes:t.typeCondition}))}}}}function no(e){return{...ro(e),Argument(t){let n=e.getArgument(),r=e.getFieldDef(),i=e.getParentType();if(!n&&r&&i){let n=t.name.value,a=li(n,r.args.map(e=>e.name));e.reportError(new P(`Unknown argument "${n}" on field "${i.name}.${r.name}".`+ei(a),{nodes:t}))}}}}function ro(e){let t=Object.create(null),n=e.getSchema(),r=n?n.getDirectives():ka;for(let e of r)t[e.name]=e.args.map(e=>e.name);let i=e.getDocument().definitions;for(let e of i)if(e.kind===I.DIRECTIVE_DEFINITION){let n=e.arguments??[];t[e.name.value]=n.map(e=>e.name.value)}return{Directive(n){let r=n.name.value,i=t[r];if(n.arguments&&i)for(let t of n.arguments){let n=t.name.value;if(!i.includes(n)){let a=li(n,i);e.reportError(new P(`Unknown argument "${n}" on directive "@${r}".`+ei(a),{nodes:t}))}}return!1}}}function io(e){let t=Object.create(null),n=e.getSchema(),r=n?n.getDirectives():ka;for(let e of r)t[e.name]=e.locations;let i=e.getDocument().definitions;for(let e of i)e.kind===I.DIRECTIVE_DEFINITION&&(t[e.name.value]=e.locations.map(e=>e.value));return{Directive(n,r,i,a,o){let s=n.name.value,c=t[s];if(!c){e.reportError(new P(`Unknown directive "@${s}".`,{nodes:n}));return}let l=ao(o);l&&!c.includes(l)&&e.reportError(new P(`Directive "@${s}" may not be used on ${l}.`,{nodes:n}))}}}function ao(e){let t=e[e.length-1];switch(`kind`in t||Qn(!1),t.kind){case I.OPERATION_DEFINITION:return oo(t.operation);case I.FIELD:return F.FIELD;case I.FRAGMENT_SPREAD:return F.FRAGMENT_SPREAD;case I.INLINE_FRAGMENT:return F.INLINE_FRAGMENT;case I.FRAGMENT_DEFINITION:return F.FRAGMENT_DEFINITION;case I.VARIABLE_DEFINITION:return F.VARIABLE_DEFINITION;case I.SCHEMA_DEFINITION:case I.SCHEMA_EXTENSION:return F.SCHEMA;case I.SCALAR_TYPE_DEFINITION:case I.SCALAR_TYPE_EXTENSION:return F.SCALAR;case I.OBJECT_TYPE_DEFINITION:case I.OBJECT_TYPE_EXTENSION:return F.OBJECT;case I.FIELD_DEFINITION:return F.FIELD_DEFINITION;case I.INTERFACE_TYPE_DEFINITION:case I.INTERFACE_TYPE_EXTENSION:return F.INTERFACE;case I.UNION_TYPE_DEFINITION:case I.UNION_TYPE_EXTENSION:return F.UNION;case I.ENUM_TYPE_DEFINITION:case I.ENUM_TYPE_EXTENSION:return F.ENUM;case I.ENUM_VALUE_DEFINITION:return F.ENUM_VALUE;case I.INPUT_OBJECT_TYPE_DEFINITION:case I.INPUT_OBJECT_TYPE_EXTENSION:return F.INPUT_OBJECT;case I.INPUT_VALUE_DEFINITION:{let t=e[e.length-3];return`kind`in t||Qn(!1),t.kind===I.INPUT_OBJECT_TYPE_DEFINITION?F.INPUT_FIELD_DEFINITION:F.ARGUMENT_DEFINITION}case I.DIRECTIVE_DEFINITION:case I.DIRECTIVE_EXTENSION:return F.DIRECTIVE_DEFINITION;default:Qn(!1,`Unexpected kind: `+B(t.kind))}}function oo(e){switch(e){case fr.QUERY:return F.QUERY;case fr.MUTATION:return F.MUTATION;case fr.SUBSCRIPTION:return F.SUBSCRIPTION}}function so(e){return{FragmentSpread(t){let n=t.name.value;e.getFragment(n)||e.reportError(new P(`Unknown fragment "${n}".`,{nodes:t.name}))}}}function co(e){let t=e.getSchema(),n=t?t.getTypeMap():Object.create(null),r=Object.create(null);for(let t of e.getDocument().definitions)Ja(t)&&(r[t.name.value]=!0);let i=[...Object.keys(n),...Object.keys(r)];return{NamedType(t,a,o,s,c){let l=t.name.value;if(!n[l]&&!r[l]){let n=c[2]??o,r=n!=null&&uo(n);if(r&&lo.includes(l))return;let a=li(l,r?lo.concat(i):i);e.reportError(new P(`Unknown type "${l}".`+ei(a),{nodes:t}))}}}}var lo=[...ba,...Va].map(e=>e.name);function uo(e){return`kind`in e&&(qa(e)||Ya(e))}function fo(e){let t=0;return{Document(e){t=e.definitions.filter(e=>e.kind===I.OPERATION_DEFINITION).length},OperationDefinition(n){!n.name&&t>1&&e.reportError(new P(`This anonymous operation must be the only defined operation.`,{nodes:n}))}}}function po(e){let t=e.getSchema(),n=t?.astNode??t?.getQueryType()??t?.getMutationType()??t?.getSubscriptionType(),r=0;return{SchemaDefinition(t){if(n){e.reportError(new P(`Cannot define a new schema within a schema extension.`,{nodes:t}));return}r>0&&e.reportError(new P(`Must provide only one schema definition.`,{nodes:t})),++r}}}var mo=3;function ho(e){function t(n,r=Object.create(null),i=0){if(n.kind===I.FRAGMENT_SPREAD){let a=n.name.value;if(r[a]===!0)return!1;let o=e.getFragment(a);if(!o)return!1;try{return r[a]=!0,t(o,r,i)}finally{r[a]=void 0}}if(n.kind===I.FIELD&&(n.name.value===`fields`||n.name.value===`interfaces`||n.name.value===`possibleTypes`||n.name.value===`inputFields`)&&(i++,i>=mo))return!0;if(`selectionSet`in n&&n.selectionSet){for(let e of n.selectionSet.selections)if(t(e,r,i))return!0}return!1}return{Field(n){if((n.name.value===`__schema`||n.name.value===`__type`)&&t(n))return e.reportError(new P(`Maximum introspection depth exceeded`,{nodes:[n]})),!1}}}function go(e){let t=Object.create(null),n=[],r=Object.create(null);return{OperationDefinition:()=>!1,FragmentDefinition(e){return i(e),!1}};function i(a){if(t[a.name.value])return;let o=a.name.value;t[o]=!0;let s=e.getFragmentSpreads(a.selectionSet);if(s.length!==0){r[o]=n.length;for(let t of s){let a=t.name.value,o=r[a];if(n.push(t),o===void 0){let t=e.getFragment(a);t&&i(t)}else{let t=n.slice(o),r=t.slice(0,-1).map(e=>`"`+e.name.value+`"`).join(`, `);e.reportError(new P(`Cannot spread fragment "${a}" within itself`+(r===``?`.`:` via ${r}.`),{nodes:t}))}n.pop()}r[o]=void 0}}}function _o(e){let t=Object.create(null);return{OperationDefinition:{enter(){t=Object.create(null)},leave(n){let r=e.getRecursiveVariableUsages(n);for(let{node:i}of r){let r=i.name.value;t[r]!==!0&&e.reportError(new P(n.name?`Variable "$${r}" is not defined by operation "${n.name.value}".`:`Variable "$${r}" is not defined.`,{nodes:[i,n]}))}}},VariableDefinition(e){t[e.variable.name.value]=!0}}}function vo(e){let t=[],n=[];return{OperationDefinition(e){return t.push(e),!1},FragmentDefinition(e){return n.push(e),!1},Document:{leave(){let r=Object.create(null);for(let n of t)for(let t of e.getRecursivelyReferencedFragments(n))r[t.name.value]=!0;for(let t of n){let n=t.name.value;r[n]!==!0&&e.reportError(new P(`Fragment "${n}" is never used.`,{nodes:t}))}}}}}function yo(e){let t=[];return{OperationDefinition:{enter(){t=[]},leave(n){let r=Object.create(null),i=e.getRecursiveVariableUsages(n);for(let{node:e}of i)r[e.name.value]=!0;for(let i of t){let t=i.variable.name.value;r[t]!==!0&&e.reportError(new P(n.name?`Variable "$${t}" is never used in operation "${n.name.value}".`:`Variable "$${t}" is never used.`,{nodes:i}))}}},VariableDefinition(e){t.push(e)}}}function bo(e){switch(e.kind){case I.OBJECT:return{...e,fields:xo(e.fields)};case I.LIST:return{...e,values:e.values.map(bo)};case I.INT:case I.FLOAT:case I.STRING:case I.BOOLEAN:case I.NULL:case I.ENUM:case I.VARIABLE:return e}}function xo(e){return e.map(e=>({...e,value:bo(e.value)})).sort((e,t)=>ai(e.name.value,t.name.value))}function So(e){return Array.isArray(e)?e.map(([e,t])=>`subfields "${e}" conflict because `+So(t)).join(` and `):e}function Co(e){let t=new Ro,n=new zo,r=new Map;return{SelectionSet(i){let a=wo(e,r,t,n,e.getParentType(),i);for(let[[t,n],r,i]of a){let a=So(n);e.reportError(new P(`Fields "${t}" conflict because ${a}. Use different aliases on the fields to fetch both if this was intentional.`,{nodes:r.concat(i)}))}}}}function wo(e,t,n,r,i,a){let o=[],[s,c]=Po(e,t,i,a);if(Oo(e,o,t,n,r,s),c.length!==0)for(let i=0;i<c.length;i++){To(e,o,t,n,r,!1,s,c[i]);for(let a=i+1;a<c.length;a++)Eo(e,o,t,n,r,!1,c[i],c[a])}return o}function To(e,t,n,r,i,a,o,s){if(r.has(o,s,a))return;r.add(o,s,a);let c=e.getFragment(s);if(!c)return;let[l,u]=Fo(e,n,c);if(o!==l){ko(e,t,n,r,i,a,o,l);for(let s of u)To(e,t,n,r,i,a,o,s)}}function Eo(e,t,n,r,i,a,o,s){if(o===s||i.has(o,s,a))return;i.add(o,s,a);let c=e.getFragment(o),l=e.getFragment(s);if(!c||!l)return;let[u,d]=Fo(e,n,c),[f,p]=Fo(e,n,l);ko(e,t,n,r,i,a,u,f);for(let s of p)Eo(e,t,n,r,i,a,o,s);for(let o of d)Eo(e,t,n,r,i,a,o,s)}function Do(e,t,n,r,i,a,o,s,c){let l=[],[u,d]=Po(e,t,a,o),[f,p]=Po(e,t,s,c);ko(e,l,t,n,r,i,u,f);for(let a of p)To(e,l,t,n,r,i,u,a);for(let a of d)To(e,l,t,n,r,i,f,a);for(let a of d)for(let o of p)Eo(e,l,t,n,r,i,a,o);return l}function Oo(e,t,n,r,i,a){for(let[o,s]of Object.entries(a))if(s.length>1)for(let a=0;a<s.length;a++)for(let c=a+1;c<s.length;c++){let l=Ao(e,n,r,i,!1,o,s[a],s[c]);l&&t.push(l)}}function ko(e,t,n,r,i,a,o,s){for(let[c,l]of Object.entries(o)){let o=s[c];if(o)for(let s of l)for(let l of o){let o=Ao(e,n,r,i,a,c,s,l);o&&t.push(o)}}}function Ao(e,t,n,r,i,a,o,s){let[c,l,u]=o,[d,f,p]=s,m=i||c!==d&&W(c)&&W(d);if(!m){let e=l.name.value,t=f.name.value;if(e!==t)return[[a,`"${e}" and "${t}" are different fields`],[l],[f]];if(!jo(l,f))return[[a,`they have differing arguments`],[l],[f]]}let h=u?.type,g=p?.type;if(h&&g&&No(h,g))return[[a,`they return conflicting types "${B(h)}" and "${B(g)}"`],[l],[f]];let _=l.selectionSet,v=f.selectionSet;if(_&&v)return Lo(Do(e,t,n,r,m,Ki(h),_,Ki(g),v),a,l,f)}function jo(e,t){let n=e.arguments,r=t.arguments;if(n===void 0||n.length===0)return r===void 0||r.length===0;if(r===void 0||r.length===0||n.length!==r.length)return!1;let i=new Map(r.map(({name:e,value:t})=>[e.value,t]));return n.every(e=>{let t=e.value,n=i.get(e.name.value);return n===void 0?!1:Mo(t)===Mo(n)})}function Mo(e){return xi(bo(e))}function No(e,t){return Fi(e)?Fi(t)?No(e.ofType,t.ofType):!0:Fi(t)?!0:K(e)?K(t)?No(e.ofType,t.ofType):!0:K(t)?!0:Ri(e)||Ri(t)?e!==t:!1}function Po(e,t,n,r){let i=t.get(r);if(i)return i;let a=Object.create(null),o=Object.create(null);Io(e,n,r,a,o);let s=[a,Object.keys(o)];return t.set(r,s),s}function Fo(e,t,n){return t.get(n.selectionSet)||Po(e,t,Ga(e.getSchema(),n.typeCondition),n.selectionSet)}function Io(e,t,n,r,i){for(let a of n.selections)switch(a.kind){case I.FIELD:{let e=a.name.value,n;(W(t)||G(t))&&(n=t.getFields()[e]);let i=a.alias?a.alias.value:e;r[i]||(r[i]=[]),r[i].push([t,a,n]);break}case I.FRAGMENT_SPREAD:i[a.name.value]=!0;break;case I.INLINE_FRAGMENT:{let n=a.typeCondition;Io(e,n?Ga(e.getSchema(),n):t,a.selectionSet,r,i);break}}}function Lo(e,t,n,r){if(e.length>0)return[[t,e.map(([e])=>e)],[n,...e.map(([,e])=>e).flat()],[r,...e.map(([,,e])=>e).flat()]]}var Ro=class{constructor(){this._data=new Map}has(e,t,n){let r=this._data.get(e)?.get(t);return r===void 0?!1:n?!0:n===r}add(e,t,n){let r=this._data.get(e);r===void 0?this._data.set(e,new Map([[t,n]])):r.set(t,n)}},zo=class{constructor(){this._orderedPairSet=new Ro}has(e,t,n){return e<t?this._orderedPairSet.has(e,t,n):this._orderedPairSet.has(t,e,n)}add(e,t,n){e<t?this._orderedPairSet.add(e,t,n):this._orderedPairSet.add(t,e,n)}};function Bo(e){return{InlineFragment(t){let n=e.getType(),r=e.getParentType();if(zi(n)&&zi(r)&&!ma(e.getSchema(),n,r)){let i=B(r),a=B(n);e.reportError(new P(`Fragment cannot be spread here as objects of type "${i}" can never be of type "${a}".`,{nodes:t}))}},FragmentSpread(t){let n=t.name.value,r=Vo(e,n),i=e.getParentType();if(r&&i&&!ma(e.getSchema(),r,i)){let a=B(i),o=B(r);e.reportError(new P(`Fragment "${n}" cannot be spread here as objects of type "${a}" can never be of type "${o}".`,{nodes:t}))}}}}function Vo(e,t){let n=e.getFragment(t);if(n){let t=Ga(e.getSchema(),n.typeCondition);if(zi(t))return t}}function Ho(e){let t=e.getSchema(),n=Object.create(null);for(let t of e.getDocument().definitions)Ja(t)&&(n[t.name.value]=t);return{ScalarTypeExtension:r,ObjectTypeExtension:r,InterfaceTypeExtension:r,UnionTypeExtension:r,EnumTypeExtension:r,InputObjectTypeExtension:r};function r(r){let i=r.name.value,a=n[i],o=t?.getType(i),s;if(a?s=Uo[a.kind]:o&&(s=Wo(o)),s){if(s!==r.kind){let t=Go(r.kind);e.reportError(new P(`Cannot extend non-${t} type "${i}".`,{nodes:a?[a,r]:r}))}}else{let a=li(i,Object.keys({...n,...t?.getTypeMap()}));e.reportError(new P(`Cannot extend type "${i}" because it is not defined.`+ei(a),{nodes:r.name}))}}}var Uo={[I.SCALAR_TYPE_DEFINITION]:I.SCALAR_TYPE_EXTENSION,[I.OBJECT_TYPE_DEFINITION]:I.OBJECT_TYPE_EXTENSION,[I.INTERFACE_TYPE_DEFINITION]:I.INTERFACE_TYPE_EXTENSION,[I.UNION_TYPE_DEFINITION]:I.UNION_TYPE_EXTENSION,[I.ENUM_TYPE_DEFINITION]:I.ENUM_TYPE_EXTENSION,[I.INPUT_OBJECT_TYPE_DEFINITION]:I.INPUT_OBJECT_TYPE_EXTENSION};function Wo(e){if(ji(e))return I.SCALAR_TYPE_EXTENSION;if(W(e))return I.OBJECT_TYPE_EXTENSION;if(G(e))return I.INTERFACE_TYPE_EXTENSION;if(Mi(e))return I.UNION_TYPE_EXTENSION;if(Ni(e))return I.ENUM_TYPE_EXTENSION;if(Pi(e))return I.INPUT_OBJECT_TYPE_EXTENSION;Qn(!1,`Unexpected type: `+B(e))}function Go(e){switch(e){case I.SCALAR_TYPE_EXTENSION:return`scalar`;case I.OBJECT_TYPE_EXTENSION:return`object`;case I.INTERFACE_TYPE_EXTENSION:return`interface`;case I.UNION_TYPE_EXTENSION:return`union`;case I.ENUM_TYPE_EXTENSION:return`enum`;case I.INPUT_OBJECT_TYPE_EXTENSION:return`input object`;default:Qn(!1,`Unexpected kind: `+B(e))}}function Ko(e){return{...qo(e),Field:{leave(t){let n=e.getFieldDef();if(!n)return!1;let r=new Set(t.arguments?.map(e=>e.name.value));for(let i of n.args)if(!r.has(i.name)&&ra(i)){let r=B(i.type);e.reportError(new P(`Field "${n.name}" argument "${i.name}" of type "${r}" is required, but it was not provided.`,{nodes:t}))}}}}}function qo(e){let t=Object.create(null),n=e.getSchema()?.getDirectives()??ka;for(let e of n)t[e.name]=ni(e.args.filter(ra),e=>e.name);let r=e.getDocument().definitions;for(let e of r)if(e.kind===I.DIRECTIVE_DEFINITION){let n=e.arguments??[];t[e.name.value]=ni(n.filter(Jo),e=>e.name.value)}return{Directive:{leave(n){let r=n.name.value,i=t[r];if(i){let t=n.arguments??[],a=new Set(t.map(e=>e.name.value));for(let[t,o]of Object.entries(i))if(!a.has(t)){let i=Ai(o.type)?B(o.type):xi(o.type);e.reportError(new P(`Directive "@${r}" argument "${t}" of type "${i}" is required, but it was not provided.`,{nodes:n}))}}}}}}function Jo(e){return e.type.kind===I.NON_NULL_TYPE&&e.defaultValue==null}function X(e){return{Field(t){let n=e.getType(),r=t.selectionSet;if(n){if(Ri(Ki(n))){if(r){let i=t.name.value,a=B(n);e.reportError(new P(`Field "${i}" must not have a selection since type "${a}" has no subfields.`,{nodes:r}))}}else if(!r){let r=t.name.value,i=B(n);e.reportError(new P(`Field "${r}" of type "${i}" must have a selection of subfields. Did you mean "${r} { ... }"?`,{nodes:t}))}else if(r.selections.length===0){let r=t.name.value,i=B(n);e.reportError(new P(`Field "${r}" of type "${i}" must have at least one field selected.`,{nodes:t}))}}}}}function Yo(e,t,n){if(e){if(e.kind===I.VARIABLE){let r=e.name.value;if(n==null||n[r]===void 0||!Zo(n,r))return;let i=n[r];return i===null&&K(t)?void 0:i}if(K(t))return e.kind===I.NULL?void 0:Yo(e,t.ofType,n);if(e.kind===I.NULL)return null;if(Fi(t)){let r=t.ofType;if(e.kind===I.LIST){let t=[];for(let i of e.values)if(Xo(i,n)){if(K(r))return;t.push(null)}else{let e=Yo(i,r,n);if(e===void 0)return;t.push(e)}return t}let i=Yo(e,r,n);return i===void 0?void 0:[i]}if(Pi(t)){if(e.kind!==I.OBJECT)return;let r=Object.create(null),i=ni(e.fields,e=>e.name.value);for(let e of Object.values(t.getFields())){let t=i[e.name];if(!t||Xo(t.value,n)){if(e.defaultValue!==void 0)r[e.name]=e.defaultValue;else if(K(e.type))return;continue}let a=Yo(t.value,e.type,n);if(a===void 0)return;r[e.name]=a}if(t.isOneOf){let e=Object.keys(r);if(e.length!==1||r[e[0]]===null)return}return r}if(Ri(t)){let r;try{r=t.parseLiteral(e,n)}catch{return}return r===void 0?void 0:r}Qn(!1,`Unexpected input type: `+B(t))}}function Xo(e,t){return e.kind===I.VARIABLE&&(t==null||t[e.name.value]===void 0||!Zo(t,e.name.value))}function Zo(e,t){return Object.prototype.hasOwnProperty.call(e,t)}function Qo(e,t,n){let r=Object.create(null),i=ni(t.arguments??[],e=>e.name.value);for(let a of e.args){let e=a.name,o=a.type,s=i[e];if(!s){if(a.defaultValue!==void 0)r[e]=a.defaultValue;else if(K(o))throw new P(`Argument "${e}" of required type "${B(o)}" was not provided.`,{nodes:t});continue}let c=s.value,l=c.kind===I.NULL;if(c.kind===I.VARIABLE){let t=c.name.value;if(n==null||!es(n,t)){if(a.defaultValue!==void 0)r[e]=a.defaultValue;else if(K(o))throw new P(`Argument "${e}" of required type "${B(o)}" was provided the variable "$${t}" which was not provided a runtime value.`,{nodes:c});continue}l=n[t]==null}if(l&&K(o))throw new P(`Argument "${e}" of non-null type "${B(o)}" must not be null.`,{nodes:c});let u=Yo(c,o,n);if(u===void 0)throw new P(`Argument "${e}" has invalid value ${xi(c)}.`,{nodes:c});r[e]=u}return{...r}}function $o(e,t,n){let r=t.directives?.find(t=>t.name.value===e.name);if(r)return Qo(e,r,n)}function es(e,t){return Object.prototype.hasOwnProperty.call(e,t)}function ts(e,t,n,r,i){let a=new Map;return ns(e,t,n,r,i,a,new Set),a}function ns(e,t,n,r,i,a,o){for(let s of i.selections)switch(s.kind){case I.FIELD:{if(!rs(n,s))continue;let e=as(s),t=a.get(e);t===void 0?a.set(e,[s]):t.push(s);break}case I.INLINE_FRAGMENT:if(!rs(n,s)||!is(e,s,r))continue;ns(e,t,n,r,s.selectionSet,a,o);break;case I.FRAGMENT_SPREAD:{let i=s.name.value;if(o.has(i)||!rs(n,s))continue;o.add(i);let c=t[i];if(!c||!is(e,c,r))continue;ns(e,t,n,r,c.selectionSet,a,o);break}}}function rs(e,t){return $o(Ea,t,e)?.if===!0?!1:$o(Ta,t,e)?.if!==!1}function is(e,t,n){let r=t.typeCondition;if(!r)return!0;let i=Ga(e,r);return i===n?!0:Bi(i)?e.isSubType(i,n):!1}function as(e){return e.alias?e.alias.value:e.name.value}function os(e){return{OperationDefinition(t){if(t.operation===`subscription`){let n=e.getSchema(),r=n.getSubscriptionType();if(r){let i=t.name?t.name.value:null,a=Object.create(null),o=e.getDocument(),s=Object.create(null);for(let e of o.definitions)e.kind===I.FRAGMENT_DEFINITION&&(s[e.name.value]=e);let c=ts(n,s,a,r,t.selectionSet);if(c.size>1){let t=[...c.values()].slice(1).flat();e.reportError(new P(i==null?`Anonymous Subscription must select only one top level field.`:`Subscription "${i}" must select only one top level field.`,{nodes:t}))}for(let t of c.values())t[0].name.value.startsWith(`__`)&&e.reportError(new P(i==null?`Anonymous Subscription must not select an introspection top level field.`:`Subscription "${i}" must not select an introspection top level field.`,{nodes:t}))}}}}}function ss(e,t){let n=new Map;for(let r of e){let e=t(r),i=n.get(e);i===void 0?n.set(e,[r]):i.push(r)}return n}function cs(e){return{DirectiveDefinition(e){let t=e.arguments??[];return n(`@${e.name.value}`,t)},InterfaceTypeDefinition:t,InterfaceTypeExtension:t,ObjectTypeDefinition:t,ObjectTypeExtension:t};function t(e){let t=e.name.value,r=e.fields??[];for(let e of r){let r=e.name.value,i=e.arguments??[];n(`${t}.${r}`,i)}return!1}function n(t,n){let r=ss(n,e=>e.name.value);for(let[n,i]of r)i.length>1&&e.reportError(new P(`Argument "${t}(${n}:)" can only be defined once.`,{nodes:i.map(e=>e.name)}));return!1}}function ls(e){return{Field:t,Directive:t};function t(t){let n=ss(t.arguments??[],e=>e.name.value);for(let[t,r]of n)r.length>1&&e.reportError(new P(`There can be only one argument named "${t}".`,{nodes:r.map(e=>e.name)}))}}function us(e){let t=Object.create(null),n=e.getSchema();return{DirectiveDefinition(r){let i=r.name.value;if(n!=null&&n.getDirective(i)){e.reportError(new P(`Directive "@${i}" already exists in the schema. It cannot be redefined.`,{nodes:r.name}));return}return t[i]?e.reportError(new P(`There can be only one directive named "@${i}".`,{nodes:[t[i],r.name]})):t[i]=r.name,!1}}}function ds(e){let t=Object.create(null),n=e.getSchema(),r=n?n.getDirectives():ka;for(let e of r)t[e.name]=!e.isRepeatable;let i=e.getDocument().definitions;for(let e of i)e.kind===I.DIRECTIVE_DEFINITION&&(t[e.name.value]=!e.repeatable);let a=Object.create(null),o=Object.create(null),s=Object.create(null);return{enter(n){if(!(`directives`in n)||!n.directives)return;let r;if(n.kind===I.SCHEMA_DEFINITION||n.kind===I.SCHEMA_EXTENSION)r=a;else if(Ja(n)||Xa(n)){let e=n.name.value;r=o[e],r===void 0&&(o[e]=r=Object.create(null))}else if(n.kind===I.DIRECTIVE_DEFINITION||n.kind===I.DIRECTIVE_EXTENSION){let e=n.name.value;r=s[e],r===void 0&&(s[e]=r=Object.create(null))}else r=Object.create(null);for(let i of n.directives){let n=i.name.value;t[n]&&(r[n]?e.reportError(new P(`The directive "@${n}" can only be used once at this location.`,{nodes:[r[n],i]})):r[n]=i)}}}}function fs(e){let t=e.getSchema(),n=t?t.getTypeMap():Object.create(null),r=Object.create(null);return{EnumTypeDefinition:i,EnumTypeExtension:i};function i(t){let i=t.name.value;r[i]||(r[i]=Object.create(null));let a=t.values??[],o=r[i];for(let t of a){let r=t.name.value,a=n[i];Ni(a)&&a.getValue(r)?e.reportError(new P(`Enum value "${i}.${r}" already exists in the schema. It cannot also be defined in this type extension.`,{nodes:t.name})):o[r]?e.reportError(new P(`Enum value "${i}.${r}" can only be defined once.`,{nodes:[o[r],t.name]})):o[r]=t.name}return!1}}function ps(e){let t=e.getSchema(),n=t?t.getTypeMap():Object.create(null),r=Object.create(null);return{InputObjectTypeDefinition:i,InputObjectTypeExtension:i,InterfaceTypeDefinition:i,InterfaceTypeExtension:i,ObjectTypeDefinition:i,ObjectTypeExtension:i};function i(t){let i=t.name.value;r[i]||(r[i]=Object.create(null));let a=t.fields??[],o=r[i];for(let t of a){let r=t.name.value;ms(n[i],r)?e.reportError(new P(`Field "${i}.${r}" already exists in the schema. It cannot also be defined in this type extension.`,{nodes:t.name})):o[r]?e.reportError(new P(`Field "${i}.${r}" can only be defined once.`,{nodes:[o[r],t.name]})):o[r]=t.name}return!1}}function ms(e,t){return W(e)||G(e)||Pi(e)?e.getFields()[t]!=null:!1}function hs(e){let t=Object.create(null);return{OperationDefinition:()=>!1,FragmentDefinition(n){let r=n.name.value;return t[r]?e.reportError(new P(`There can be only one fragment named "${r}".`,{nodes:[t[r],n.name]})):t[r]=n.name,!1}}}function gs(e){let t=[],n=Object.create(null);return{ObjectValue:{enter(){t.push(n),n=Object.create(null)},leave(){let e=t.pop();e||Qn(!1),n=e}},ObjectField(t){let r=t.name.value;n[r]?e.reportError(new P(`There can be only one input field named "${r}".`,{nodes:[n[r],t.name]})):n[r]=t.name}}}function _s(e){let t=Object.create(null);return{OperationDefinition(n){let r=n.name;return r&&(t[r.value]?e.reportError(new P(`There can be only one operation named "${r.value}".`,{nodes:[t[r.value],r]})):t[r.value]=r),!1},FragmentDefinition:()=>!1}}function vs(e){let t=e.getSchema(),n=Object.create(null),r=t?{query:t.getQueryType(),mutation:t.getMutationType(),subscription:t.getSubscriptionType()}:{};return{SchemaDefinition:i,SchemaExtension:i};function i(t){let i=t.operationTypes??[];for(let t of i){let i=t.operation,a=n[i];r[i]?e.reportError(new P(`Type for ${i} already defined in the schema. It cannot be redefined.`,{nodes:t})):a?e.reportError(new P(`There can be only one ${i} type in schema.`,{nodes:[a,t]})):n[i]=t}return!1}}function ys(e){let t=Object.create(null),n=e.getSchema();return{ScalarTypeDefinition:r,ObjectTypeDefinition:r,InterfaceTypeDefinition:r,UnionTypeDefinition:r,EnumTypeDefinition:r,InputObjectTypeDefinition:r};function r(r){let i=r.name.value;if(n!=null&&n.getType(i)){e.reportError(new P(`Type "${i}" already exists in the schema. It cannot also be defined in this type definition.`,{nodes:r.name}));return}return t[i]?e.reportError(new P(`There can be only one type named "${i}".`,{nodes:[t[i],r.name]})):t[i]=r.name,!1}}function bs(e){return{OperationDefinition(t){let n=ss(t.variableDefinitions??[],e=>e.variable.name.value);for(let[t,r]of n)r.length>1&&e.reportError(new P(`There can be only one variable named "$${t}".`,{nodes:r.map(e=>e.variable.name)}))}}}function xs(e){return{ListValue(t){if(!Fi(Wi(e.getParentInputType())))return Ss(e,t),!1},ObjectValue(t){let n=Ki(e.getInputType());if(!Pi(n))return Ss(e,t),!1;let r=ni(t.fields,e=>e.name.value);for(let i of Object.values(n.getFields()))if(!r[i.name]&&fa(i)){let r=B(i.type);e.reportError(new P(`Field "${n.name}.${i.name}" of required type "${r}" was not provided.`,{nodes:t}))}n.isOneOf&&Cs(e,t,n,r)},ObjectField(t){let n=Ki(e.getParentInputType());if(!e.getInputType()&&Pi(n)){let r=li(t.name.value,Object.keys(n.getFields()));e.reportError(new P(`Field "${t.name.value}" is not defined by type "${n.name}".`+ei(r),{nodes:t}))}},NullValue(t){let n=e.getInputType();K(n)&&e.reportError(new P(`Expected value of type "${B(n)}", found ${xi(t)}.`,{nodes:t}))},EnumValue:t=>Ss(e,t),IntValue:t=>Ss(e,t),FloatValue:t=>Ss(e,t),StringValue:t=>Ss(e,t),BooleanValue:t=>Ss(e,t)}}function Ss(e,t){let n=e.getInputType();if(!n)return;let r=Ki(n);if(!Ri(r)){let r=B(n);e.reportError(new P(`Expected value of type "${r}", found ${xi(t)}.`,{nodes:t}));return}try{if(r.parseLiteral(t,void 0)===void 0){let r=B(n);e.reportError(new P(`Expected value of type "${r}", found ${xi(t)}.`,{nodes:t}))}}catch(r){let i=B(n);r instanceof P?e.reportError(r):e.reportError(new P(`Expected value of type "${i}", found ${xi(t)}; `+r.message,{nodes:t,originalError:r}))}}function Cs(e,t,n,r){let i=Object.keys(r);if(i.length!==1){e.reportError(new P(`OneOf Input Object "${n.name}" must specify exactly one key.`,{nodes:[t]}));return}let a=r[i[0]]?.value;(!a||a.kind===I.NULL)&&e.reportError(new P(`Field "${n.name}.${i[0]}" must be non-null.`,{nodes:[t]}))}function ws(e){return{VariableDefinition(t){let n=Ga(e.getSchema(),t.type);if(n!==void 0&&!Ii(n)){let n=t.variable.name.value,r=xi(t.type);e.reportError(new P(`Variable "$${n}" cannot be non-input type "${r}".`,{nodes:t.type}))}}}}function Ts(e){let t=Object.create(null);return{OperationDefinition:{enter(){t=Object.create(null)},leave(n){let r=e.getRecursiveVariableUsages(n);for(let{node:n,type:i,defaultValue:a,parentType:o}of r){let r=n.name.value,s=t[r];if(s&&i){let t=e.getSchema(),c=Ga(t,s.type);if(c&&!Es(t,c,s.defaultValue,i,a)){let t=B(c),a=B(i);e.reportError(new P(`Variable "$${r}" of type "${t}" used in position expecting type "${a}".`,{nodes:[s,n]}))}Pi(o)&&o.isOneOf&&Ui(c)&&e.reportError(new P(`Variable "$${r}" is of type "${c}" but must be non-nullable to be used for OneOf Input Object "${o}".`,{nodes:[s,n]}))}}}},VariableDefinition(e){t[e.variable.name.value]=e}}}function Es(e,t,n,r,i){if(K(r)&&!K(t)){if(!(n!=null&&n.kind!==I.NULL)&&i===void 0)return!1;let a=r.ofType;return pa(e,t,a)}return pa(e,t,r)}var Ds=Object.freeze([ho]);Object.freeze([Za,_s,fo,os,co,to,ws,X,Qa,hs,so,vo,Bo,go,bs,_o,yo,io,ds,no,ls,xs,Ko,Ts,Co,gs,...Ds]);var Os=Object.freeze([po,vs,ys,fs,ps,cs,us,co,io,ds,Ho,ro,ls,gs,qo]),ks=class{constructor(e,t){this._ast=e,this._fragments=void 0,this._fragmentSpreads=new Map,this._recursivelyReferencedFragments=new Map,this._onError=t}get[Symbol.toStringTag](){return`ASTValidationContext`}reportError(e){this._onError(e)}getDocument(){return this._ast}getFragment(e){let t;if(this._fragments)t=this._fragments;else{t=Object.create(null);for(let e of this.getDocument().definitions)e.kind===I.FRAGMENT_DEFINITION&&(t[e.name.value]=e);this._fragments=t}return t[e]}getFragmentSpreads(e){let t=this._fragmentSpreads.get(e);if(!t){t=[];let n=[e],r;for(;r=n.pop();)for(let e of r.selections)e.kind===I.FRAGMENT_SPREAD?t.push(e):e.selectionSet&&n.push(e.selectionSet);this._fragmentSpreads.set(e,t)}return t}getRecursivelyReferencedFragments(e){let t=this._recursivelyReferencedFragments.get(e);if(!t){t=[];let n=Object.create(null),r=[e.selectionSet],i;for(;i=r.pop();)for(let e of this.getFragmentSpreads(i)){let i=e.name.value;if(n[i]!==!0){n[i]=!0;let e=this.getFragment(i);e&&(t.push(e),r.push(e.selectionSet))}}this._recursivelyReferencedFragments.set(e,t)}return t}},As=class extends ks{constructor(e,t,n){super(e,n),this._schema=t}get[Symbol.toStringTag](){return`SDLValidationContext`}getSchema(){return this._schema}};ii(lr,e=>e.filter(e=>e!==`description`));function js(e,t,n=Os){let r=[],i=new As(e,t,e=>{r.push(e)});return vi(e,yi(n.map(e=>e(i)))),r}function Ms(e){let t=js(e);if(t.length!==0)throw Error(t.map(e=>e.message).join(`

`))}function Ns(e,t,n){let r=[],i=Object.create(null),a=Object.create(null),o=[],s,c=[];for(let e of t.definitions)if(e.kind===I.SCHEMA_DEFINITION)s=e;else if(e.kind===I.SCHEMA_EXTENSION)c.push(e);else if(Ja(e))r.push(e);else if(Xa(e)){let t=e.name.value,n=i[t];i[t]=n?n.concat([e]):[e]}else if(e.kind===I.DIRECTIVE_DEFINITION)o.push(e);else if(e.kind===I.DIRECTIVE_EXTENSION){let t=e.name.value,n=a[t];a[t]=n?n.concat([e]):[e]}if(Object.keys(i).length===0&&r.length===0&&Object.keys(a).length===0&&o.length===0&&c.length===0&&s==null)return e;let l=Object.create(null);for(let t of e.types)l[t.name]=g(t);for(let e of r){let t=e.name.value;l[t]=Ps[t]??ue(e)}let u=Object.create(null);for(let t of e.directives)u[t.name]=w(t);let d={query:e.query&&m(e.query),mutation:e.mutation&&m(e.mutation),subscription:e.subscription&&m(e.subscription),...s&&te([s]),...te(c)},f=Object.values(u);return{description:s?.description?.value,...d,types:Object.values(l),directives:[...f.map(h),...o.map(ie)],extensions:Object.create(null),astNode:s??e.astNode,extensionASTNodes:e.extensionASTNodes.concat(c),assumeValid:n?.assumeValid??!1};function p(e){return Fi(e)?new Vi(p(e.ofType)):K(e)?new q(p(e.ofType)):m(e)}function m(e){return l[e.name]}function h(e){let t=e.toConfig();return new wa({...t,args:ii(t.args,ee)})}function g(e){if(Ha(e)||xa(e))return e;if(ji(e))return y(e);if(W(e))return b(e);if(G(e))return x(e);if(Mi(e))return S(e);if(Ni(e))return v(e);if(Pi(e))return _(e);Qn(!1,`Unexpected type: `+B(e))}function _(e){let t=e.toConfig(),n=i[t.name]??[];return new ua({...t,fields:()=>({...ii(t.fields,e=>({...e,type:p(e.type)})),...se(n)}),extensionASTNodes:t.extensionASTNodes.concat(n)})}function v(e){let t=e.toConfig(),n=i[e.name]??[];return new sa({...t,values:{...t.values,...ce(n)},extensionASTNodes:t.extensionASTNodes.concat(n)})}function y(e){let t=e.toConfig(),n=i[t.name]??[],r=t.specifiedByURL;for(let e of n)r=Is(e)??r;return new Yi({...t,specifiedByURL:r,extensionASTNodes:t.extensionASTNodes.concat(n)})}function b(e){let t=e.toConfig(),n=i[t.name]??[];return new Xi({...t,interfaces:()=>[...e.getInterfaces().map(m),...T(n)],fields:()=>({...ii(t.fields,C),...ae(n)}),extensionASTNodes:t.extensionASTNodes.concat(n)})}function x(e){let t=e.toConfig(),n=i[t.name]??[];return new ia({...t,interfaces:()=>[...e.getInterfaces().map(m),...T(n)],fields:()=>({...ii(t.fields,C),...ae(n)}),extensionASTNodes:t.extensionASTNodes.concat(n)})}function S(e){let t=e.toConfig(),n=i[t.name]??[];return new aa({...t,types:()=>[...e.getTypes().map(m),...le(n)],extensionASTNodes:t.extensionASTNodes.concat(n)})}function C(e){return{...e,type:p(e.type),args:e.args&&ii(e.args,ee)}}function ee(e){return{...e,type:p(e.type)}}function te(e){let t={};for(let n of e){let e=n.operationTypes??[];for(let n of e)t[n.operation]=ne(n.type)}return t}function w(e){let t=e.toConfig(),n=a[t.name]??[],r=t.deprecationReason??n.map(e=>Fs(e)).find(e=>e!=null);return new wa({...t,deprecationReason:r,extensionASTNodes:t.extensionASTNodes.concat(n)})}function ne(e){let t=e.name.value,n=Ps[t]??l[t];if(n===void 0)throw Error(`Unknown type: "${t}".`);return n}function re(e){return e.kind===I.LIST_TYPE?new Vi(re(e.type)):e.kind===I.NON_NULL_TYPE?new q(re(e.type)):ne(e)}function ie(e){let t=a[e.name.value]??[],n=Fs(e)??t.map(e=>Fs(e)).find(e=>e!=null);return new wa({name:e.name.value,description:e.description?.value,locations:e.locations.map(({value:e})=>e),isRepeatable:e.repeatable,args:oe(e.arguments),deprecationReason:n,astNode:e,extensionASTNodes:t})}function ae(e){let t=Object.create(null);for(let n of e){let e=n.fields??[];for(let n of e)t[n.name.value]={type:re(n.type),description:n.description?.value,args:oe(n.arguments),deprecationReason:Fs(n),astNode:n}}return t}function oe(e){let t=e??[],n=Object.create(null);for(let e of t){let t=re(e.type);n[e.name.value]={type:t,description:e.description?.value,defaultValue:Yo(e.defaultValue,t),deprecationReason:Fs(e),astNode:e}}return n}function se(e){let t=Object.create(null);for(let n of e){let e=n.fields??[];for(let n of e){let e=re(n.type);t[n.name.value]={type:e,description:n.description?.value,defaultValue:Yo(n.defaultValue,e),deprecationReason:Fs(n),astNode:n}}}return t}function ce(e){let t=Object.create(null);for(let n of e){let e=n.values??[];for(let n of e)t[n.name.value]={description:n.description?.value,deprecationReason:Fs(n),astNode:n}}return t}function T(e){return e.flatMap(e=>e.interfaces?.map(ne)??[])}function le(e){return e.flatMap(e=>e.types?.map(ne)??[])}function ue(e){let t=e.name.value,n=i[t]??[];switch(e.kind){case I.OBJECT_TYPE_DEFINITION:{let r=[e,...n];return new Xi({name:t,description:e.description?.value,interfaces:()=>T(r),fields:()=>ae(r),astNode:e,extensionASTNodes:n})}case I.INTERFACE_TYPE_DEFINITION:{let r=[e,...n];return new ia({name:t,description:e.description?.value,interfaces:()=>T(r),fields:()=>ae(r),astNode:e,extensionASTNodes:n})}case I.ENUM_TYPE_DEFINITION:{let r=[e,...n];return new sa({name:t,description:e.description?.value,values:ce(r),astNode:e,extensionASTNodes:n})}case I.UNION_TYPE_DEFINITION:{let r=[e,...n];return new aa({name:t,description:e.description?.value,types:()=>le(r),astNode:e,extensionASTNodes:n})}case I.SCALAR_TYPE_DEFINITION:return new Yi({name:t,description:e.description?.value,specifiedByURL:Is(e),astNode:e,extensionASTNodes:n});case I.INPUT_OBJECT_TYPE_DEFINITION:{let r=[e,...n];return new ua({name:t,description:e.description?.value,fields:()=>se(r),astNode:e,extensionASTNodes:n,isOneOf:Ls(e)})}}}}var Ps=ni([...ba,...Va],e=>e.name);function Fs(e){return $o(J,e)?.reason}function Is(e){return $o(Da,e)?.url}function Ls(e){return!!$o(Oa,e)}function Rs(e,t){e!=null&&e.kind===I.DOCUMENT||N(!1,`Must provide valid Document AST.`),t?.assumeValid!==!0&&t?.assumeValidSDL!==!0&&Ms(e);let n=Ns({description:void 0,types:[],directives:[],extensions:Object.create(null),extensionASTNodes:[],assumeValid:!1},e,t);if(n.astNode==null)for(let e of n.types)switch(e.name){case`Query`:n.query=e;break;case`Mutation`:n.mutation=e;break;case`Subscription`:n.subscription=e;break}let r=[...n.directives,...ka.filter(e=>n.directives.every(t=>t.name!==e.name))];return new Ua({...n,directives:r})}function zs(e,t){return Rs(Yr(e,{noLocation:t?.noLocation,allowLegacyFragmentVariables:t?.allowLegacyFragmentVariables,experimentalDirectivesOnDirectiveDefinitions:t?.experimentalDirectivesOnDirectiveDefinitions}),{assumeValidSDL:t?.assumeValidSDL,assumeValid:t?.assumeValid})}var Bs=class{mapping;constructor(e){this.mapping=e}getFor(e){return this.mapping.get(e.name)}},Vs;(function(e){e.UNION=`union`,e.ARGUMENT=`argument`,e.FIELD=`field`})(Vs||={});function Hs(e){let t=new Map;return Us(e,e=>{W(e)&&Ws(e,n=>{Ks(t,qs(n.type)).references.push({kind:Vs.FIELD,parent:e,by:n}),Gs(n,r=>{Ks(t,qs(r.type)).references.push({kind:Vs.ARGUMENT,field:n,type:e,by:r})})}),Mi(e)&&e.getTypes().forEach(n=>{Ks(t,n).references.push({kind:Vs.UNION,by:e})})}),new Bs(t)}function Us(e,t){Object.entries(e.getTypeMap()).forEach(([,e])=>{e.name.startsWith(`__`)||t(e)})}function Ws(e,t){Object.entries(e.getFields()).forEach(([,e])=>{t(e)})}function Gs(e,t){Object.entries(e.args).forEach(([,e])=>{t(e)})}function Ks(e,t){let n=e.get(t.name);return n||(n={references:[]},e.set(t.name,n)),n}function qs(e){return K(e)||Fi(e)?qs(e.ofType):e}var Js=r(((e,t)=>{(function(){var n,r=`4.18.1`,i=200,a=`Unsupported core-js use. Try https://npms.io/search?q=ponyfill.`,o=`Expected a function`,s="Invalid `variable` option passed into `_.template`",c="Invalid `imports` option passed into `_.template`",l=`__lodash_hash_undefined__`,u=500,d=`__lodash_placeholder__`,f=1,p=2,m=4,h=1,g=2,_=1,v=2,y=4,b=8,x=16,S=32,C=64,ee=128,te=256,w=512,ne=30,re=`...`,ie=800,ae=16,oe=1,se=2,ce=3,T=1/0,le=9007199254740991,ue=17976931348623157e292,de=NaN,fe=4294967295,pe=fe-1,me=fe>>>1,he=[[`ary`,ee],[`bind`,_],[`bindKey`,v],[`curry`,b],[`curryRight`,x],[`flip`,w],[`partial`,S],[`partialRight`,C],[`rearg`,te]],ge=`[object Arguments]`,_e=`[object Array]`,ve=`[object AsyncFunction]`,ye=`[object Boolean]`,be=`[object Date]`,xe=`[object DOMException]`,Se=`[object Error]`,Ce=`[object Function]`,we=`[object GeneratorFunction]`,Te=`[object Map]`,Ee=`[object Number]`,De=`[object Null]`,Oe=`[object Object]`,ke=`[object Promise]`,Ae=`[object Proxy]`,je=`[object RegExp]`,Me=`[object Set]`,Ne=`[object String]`,Pe=`[object Symbol]`,Fe=`[object Undefined]`,Ie=`[object WeakMap]`,Le=`[object WeakSet]`,Re=`[object ArrayBuffer]`,ze=`[object DataView]`,Be=`[object Float32Array]`,Ve=`[object Float64Array]`,He=`[object Int8Array]`,Ue=`[object Int16Array]`,We=`[object Int32Array]`,Ge=`[object Uint8Array]`,Ke=`[object Uint8ClampedArray]`,qe=`[object Uint16Array]`,Je=`[object Uint32Array]`,Ye=/\b__p \+= '';/g,Xe=/\b(__p \+=) '' \+/g,Ze=/(__e\(.*?\)|\b__t\)) \+\n'';/g,Qe=/&(?:amp|lt|gt|quot|#39);/g,$e=/[&<>"']/g,et=RegExp(Qe.source),tt=RegExp($e.source),nt=/<%-([\s\S]+?)%>/g,rt=/<%([\s\S]+?)%>/g,it=/<%=([\s\S]+?)%>/g,at=/\.|\[(?:[^[\]]*|(["'])(?:(?!\1)[^\\]|\\.)*?\1)\]/,ot=/^\w*$/,st=/[^.[\]]+|\[(?:(-?\d+(?:\.\d+)?)|(["'])((?:(?!\2)[^\\]|\\.)*?)\2)\]|(?=(?:\.|\[\])(?:\.|\[\]|$))/g,ct=/[\\^$.*+?()[\]{}|]/g,lt=RegExp(ct.source),ut=/^\s+/,E=/\s/,dt=/\{(?:\n\/\* \[wrapped with .+\] \*\/)?\n?/,ft=/\{\n\/\* \[wrapped with (.+)\] \*/,pt=/,? & /,mt=/[^\x00-\x2f\x3a-\x40\x5b-\x60\x7b-\x7f]+/g,ht=/[()=,{}\[\]\/\s]/,gt=/\\(\\)?/g,_t=/\$\{([^\\}]*(?:\\.[^\\}]*)*)\}/g,vt=/\w*$/,yt=/^[-+]0x[0-9a-f]+$/i,bt=/^0b[01]+$/i,xt=/^\[object .+?Constructor\]$/,St=/^0o[0-7]+$/i,Ct=/^(?:0|[1-9]\d*)$/,wt=/[\xc0-\xd6\xd8-\xf6\xf8-\xff\u0100-\u017f]/g,Tt=/($^)/,Et=/['\n\r\u2028\u2029\\]/g,Dt=`\\ud800-\\udfff`,Ot=`\\u0300-\\u036f\\ufe20-\\ufe2f\\u20d0-\\u20ff`,kt=`\\u2700-\\u27bf`,D=`a-z\\xdf-\\xf6\\xf8-\\xff`,At=`\\xac\\xb1\\xd7\\xf7`,jt=`\\x00-\\x2f\\x3a-\\x40\\x5b-\\x60\\x7b-\\xbf`,Mt=`\\u2000-\\u206f`,Nt=` \\t\\x0b\\f\\xa0\\ufeff\\n\\r\\u2028\\u2029\\u1680\\u180e\\u2000\\u2001\\u2002\\u2003\\u2004\\u2005\\u2006\\u2007\\u2008\\u2009\\u200a\\u202f\\u205f\\u3000`,Pt=`A-Z\\xc0-\\xd6\\xd8-\\xde`,Ft=`\\ufe0e\\ufe0f`,It=At+jt+Mt+Nt,Lt=`['’]`,O=`[`+Dt+`]`,Rt=`[`+It+`]`,zt=`[`+Ot+`]`,Bt=`\\d+`,Vt=`[`+kt+`]`,Ht=`[`+D+`]`,Ut=`[^`+Dt+It+Bt+kt+D+Pt+`]`,Wt=`\\ud83c[\\udffb-\\udfff]`,Gt=`(?:`+zt+`|`+Wt+`)`,Kt=`[^`+Dt+`]`,qt=`(?:\\ud83c[\\udde6-\\uddff]){2}`,Jt=`[\\ud800-\\udbff][\\udc00-\\udfff]`,Yt=`[`+Pt+`]`,Xt=`\\u200d`,Zt=`(?:`+Ht+`|`+Ut+`)`,Qt=`(?:`+Yt+`|`+Ut+`)`,$t=`(?:`+Lt+`(?:d|ll|m|re|s|t|ve))?`,en=`(?:`+Lt+`(?:D|LL|M|RE|S|T|VE))?`,tn=Gt+`?`,nn=`[`+Ft+`]?`,rn=`(?:`+Xt+`(?:`+[Kt,qt,Jt].join(`|`)+`)`+nn+tn+`)*`,an=`\\d*(?:1st|2nd|3rd|(?![123])\\dth)(?=\\b|[A-Z_])`,on=`\\d*(?:1ST|2ND|3RD|(?![123])\\dTH)(?=\\b|[a-z_])`,sn=nn+tn+rn,cn=`(?:`+[Vt,qt,Jt].join(`|`)+`)`+sn,ln=`(?:`+[Kt+zt+`?`,zt,qt,Jt,O].join(`|`)+`)`,un=RegExp(Lt,`g`),dn=RegExp(zt,`g`),fn=RegExp(Wt+`(?=`+Wt+`)|`+ln+sn,`g`),pn=RegExp([Yt+`?`+Ht+`+`+$t+`(?=`+[Rt,Yt,`$`].join(`|`)+`)`,Qt+`+`+en+`(?=`+[Rt,Yt+Zt,`$`].join(`|`)+`)`,Yt+`?`+Zt+`+`+$t,Yt+`+`+en,on,an,Bt,cn].join(`|`),`g`),mn=RegExp(`[`+Xt+Dt+Ot+Ft+`]`),hn=/[a-z][A-Z]|[A-Z]{2}[a-z]|[0-9][a-zA-Z]|[a-zA-Z][0-9]|[^a-zA-Z0-9 ]/,gn=`Array.Buffer.DataView.Date.Error.Float32Array.Float64Array.Function.Int8Array.Int16Array.Int32Array.Map.Math.Object.Promise.RegExp.Set.String.Symbol.TypeError.Uint8Array.Uint8ClampedArray.Uint16Array.Uint32Array.WeakMap._.clearTimeout.isFinite.parseInt.setTimeout`.split(`.`),_n=-1,k={};k[Be]=k[Ve]=k[He]=k[Ue]=k[We]=k[Ge]=k[Ke]=k[qe]=k[Je]=!0,k[ge]=k[_e]=k[Re]=k[ye]=k[ze]=k[be]=k[Se]=k[Ce]=k[Te]=k[Ee]=k[Oe]=k[je]=k[Me]=k[Ne]=k[Ie]=!1;var A={};A[ge]=A[_e]=A[Re]=A[ze]=A[ye]=A[be]=A[Be]=A[Ve]=A[He]=A[Ue]=A[We]=A[Te]=A[Ee]=A[Oe]=A[je]=A[Me]=A[Ne]=A[Pe]=A[Ge]=A[Ke]=A[qe]=A[Je]=!0,A[Se]=A[Ce]=A[Ie]=!1;var vn={À:`A`,Á:`A`,Â:`A`,Ã:`A`,Ä:`A`,Å:`A`,à:`a`,á:`a`,â:`a`,ã:`a`,ä:`a`,å:`a`,Ç:`C`,ç:`c`,Ð:`D`,ð:`d`,È:`E`,É:`E`,Ê:`E`,Ë:`E`,è:`e`,é:`e`,ê:`e`,ë:`e`,Ì:`I`,Í:`I`,Î:`I`,Ï:`I`,ì:`i`,í:`i`,î:`i`,ï:`i`,Ñ:`N`,ñ:`n`,Ò:`O`,Ó:`O`,Ô:`O`,Õ:`O`,Ö:`O`,Ø:`O`,ò:`o`,ó:`o`,ô:`o`,õ:`o`,ö:`o`,ø:`o`,Ù:`U`,Ú:`U`,Û:`U`,Ü:`U`,ù:`u`,ú:`u`,û:`u`,ü:`u`,Ý:`Y`,ý:`y`,ÿ:`y`,Æ:`Ae`,æ:`ae`,Þ:`Th`,þ:`th`,ß:`ss`,Ā:`A`,Ă:`A`,Ą:`A`,ā:`a`,ă:`a`,ą:`a`,Ć:`C`,Ĉ:`C`,Ċ:`C`,Č:`C`,ć:`c`,ĉ:`c`,ċ:`c`,č:`c`,Ď:`D`,Đ:`D`,ď:`d`,đ:`d`,Ē:`E`,Ĕ:`E`,Ė:`E`,Ę:`E`,Ě:`E`,ē:`e`,ĕ:`e`,ė:`e`,ę:`e`,ě:`e`,Ĝ:`G`,Ğ:`G`,Ġ:`G`,Ģ:`G`,ĝ:`g`,ğ:`g`,ġ:`g`,ģ:`g`,Ĥ:`H`,Ħ:`H`,ĥ:`h`,ħ:`h`,Ĩ:`I`,Ī:`I`,Ĭ:`I`,Į:`I`,İ:`I`,ĩ:`i`,ī:`i`,ĭ:`i`,į:`i`,ı:`i`,Ĵ:`J`,ĵ:`j`,Ķ:`K`,ķ:`k`,ĸ:`k`,Ĺ:`L`,Ļ:`L`,Ľ:`L`,Ŀ:`L`,Ł:`L`,ĺ:`l`,ļ:`l`,ľ:`l`,ŀ:`l`,ł:`l`,Ń:`N`,Ņ:`N`,Ň:`N`,Ŋ:`N`,ń:`n`,ņ:`n`,ň:`n`,ŋ:`n`,Ō:`O`,Ŏ:`O`,Ő:`O`,ō:`o`,ŏ:`o`,ő:`o`,Ŕ:`R`,Ŗ:`R`,Ř:`R`,ŕ:`r`,ŗ:`r`,ř:`r`,Ś:`S`,Ŝ:`S`,Ş:`S`,Š:`S`,ś:`s`,ŝ:`s`,ş:`s`,š:`s`,Ţ:`T`,Ť:`T`,Ŧ:`T`,ţ:`t`,ť:`t`,ŧ:`t`,Ũ:`U`,Ū:`U`,Ŭ:`U`,Ů:`U`,Ű:`U`,Ų:`U`,ũ:`u`,ū:`u`,ŭ:`u`,ů:`u`,ű:`u`,ų:`u`,Ŵ:`W`,ŵ:`w`,Ŷ:`Y`,ŷ:`y`,Ÿ:`Y`,Ź:`Z`,Ż:`Z`,Ž:`Z`,ź:`z`,ż:`z`,ž:`z`,Ĳ:`IJ`,ĳ:`ij`,Œ:`Oe`,œ:`oe`,ŉ:`'n`,ſ:`s`},yn={"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`},bn={"&amp;":`&`,"&lt;":`<`,"&gt;":`>`,"&quot;":`"`,"&#39;":`'`},xn={"\\":`\\`,"'":`'`,"\n":`n`,"\r":`r`,"\u2028":`u2028`,"\u2029":`u2029`},Sn=parseFloat,Cn=parseInt,wn=typeof global==`object`&&global&&global.Object===Object&&global,Tn=typeof self==`object`&&self&&self.Object===Object&&self,En=wn||Tn||Function(`return this`)(),Dn=typeof e==`object`&&e&&!e.nodeType&&e,On=Dn&&typeof t==`object`&&t&&!t.nodeType&&t,kn=On&&On.exports===Dn,An=kn&&wn.process,jn=function(){try{return On&&On.require&&On.require(`util`).types||An&&An.binding&&An.binding(`util`)}catch{}}(),Mn=jn&&jn.isArrayBuffer,j=jn&&jn.isDate,Nn=jn&&jn.isMap,Pn=jn&&jn.isRegExp,Fn=jn&&jn.isSet,In=jn&&jn.isTypedArray;function Ln(e,t,n){switch(n.length){case 0:return e.call(t);case 1:return e.call(t,n[0]);case 2:return e.call(t,n[0],n[1]);case 3:return e.call(t,n[0],n[1],n[2])}return e.apply(t,n)}function Rn(e,t,n,r){for(var i=-1,a=e==null?0:e.length;++i<a;){var o=e[i];t(r,o,n(o),e)}return r}function zn(e,t){for(var n=-1,r=e==null?0:e.length;++n<r&&t(e[n],n,e)!==!1;);return e}function Bn(e,t){for(var n=e==null?0:e.length;n--&&t(e[n],n,e)!==!1;);return e}function Vn(e,t){for(var n=-1,r=e==null?0:e.length;++n<r;)if(!t(e[n],n,e))return!1;return!0}function Hn(e,t){for(var n=-1,r=e==null?0:e.length,i=0,a=[];++n<r;){var o=e[n];t(o,n,e)&&(a[i++]=o)}return a}function Un(e,t){return!!(e!=null&&e.length)&&$n(e,t,0)>-1}function Wn(e,t,n){for(var r=-1,i=e==null?0:e.length;++r<i;)if(n(t,e[r]))return!0;return!1}function M(e,t){for(var n=-1,r=e==null?0:e.length,i=Array(r);++n<r;)i[n]=t(e[n],n,e);return i}function Gn(e,t){for(var n=-1,r=t.length,i=e.length;++n<r;)e[i+n]=t[n];return e}function Kn(e,t,n,r){var i=-1,a=e==null?0:e.length;for(r&&a&&(n=e[++i]);++i<a;)n=t(n,e[i],i,e);return n}function qn(e,t,n,r){var i=e==null?0:e.length;for(r&&i&&(n=e[--i]);i--;)n=t(n,e[i],i,e);return n}function Jn(e,t){for(var n=-1,r=e==null?0:e.length;++n<r;)if(t(e[n],n,e))return!0;return!1}var Yn=rr(`length`);function Xn(e){return e.split(``)}function N(e){return e.match(mt)||[]}function Zn(e,t,n){var r;return n(e,function(e,n,i){if(t(e,n,i))return r=n,!1}),r}function Qn(e,t,n,r){for(var i=e.length,a=n+(r?1:-1);r?a--:++a<i;)if(t(e[a],a,e))return a;return-1}function $n(e,t,n){return t===t?Tr(e,t,n):Qn(e,tr,n)}function er(e,t,n,r){for(var i=n-1,a=e.length;++i<a;)if(r(e[i],t))return i;return-1}function tr(e){return e!==e}function nr(e,t){var n=e==null?0:e.length;return n?or(e,t)/n:de}function rr(e){return function(t){return t==null?n:t[e]}}function ir(e){return function(t){return e==null?n:e[t]}}function P(e,t,n,r,i){return i(e,function(e,i,a){n=r?(r=!1,e):t(n,e,i,a)}),n}function ar(e,t){var n=e.length;for(e.sort(t);n--;)e[n]=e[n].value;return e}function or(e,t){for(var r,i=-1,a=e.length;++i<a;){var o=t(e[i]);o!==n&&(r=r===n?o:r+o)}return r}function sr(e,t){for(var n=-1,r=Array(e);++n<e;)r[n]=t(n);return r}function cr(e,t){return M(t,function(t){return[t,e[t]]})}function lr(e){return e&&e.slice(0,Or(e)+1).replace(ut,``)}function ur(e){return function(t){return e(t)}}function dr(e,t){return M(t,function(t){return e[t]})}function fr(e,t){return e.has(t)}function F(e,t){for(var n=-1,r=e.length;++n<r&&$n(t,e[n],0)>-1;);return n}function I(e,t){for(var n=e.length;n--&&$n(t,e[n],0)>-1;);return n}function pr(e,t){for(var n=e.length,r=0;n--;)e[n]===t&&++r;return r}var mr=ir(vn),hr=ir(yn);function gr(e){return`\\`+xn[e]}function _r(e,t){return e==null?n:e[t]}function vr(e){return mn.test(e)}function yr(e){return hn.test(e)}function br(e){for(var t,n=[];!(t=e.next()).done;)n.push(t.value);return n}function L(e){var t=-1,n=Array(e.size);return e.forEach(function(e,r){n[++t]=[r,e]}),n}function xr(e,t){return function(n){return e(t(n))}}function Sr(e,t){for(var n=-1,r=e.length,i=0,a=[];++n<r;){var o=e[n];(o===t||o===d)&&(e[n]=d,a[i++]=n)}return a}function Cr(e){var t=-1,n=Array(e.size);return e.forEach(function(e){n[++t]=e}),n}function wr(e){var t=-1,n=Array(e.size);return e.forEach(function(e){n[++t]=[e,e]}),n}function Tr(e,t,n){for(var r=n-1,i=e.length;++r<i;)if(e[r]===t)return r;return-1}function Er(e,t,n){for(var r=n+1;r--;)if(e[r]===t)return r;return r}function Dr(e){return vr(e)?Ar(e):Yn(e)}function R(e){return vr(e)?jr(e):Xn(e)}function Or(e){for(var t=e.length;t--&&E.test(e.charAt(t)););return t}var kr=ir(bn);function Ar(e){for(var t=fn.lastIndex=0;fn.test(e);)++t;return t}function jr(e){return e.match(fn)||[]}function Mr(e){return e.match(pn)||[]}var Nr=(function e(t){t=t==null?En:Nr.defaults(En.Object(),t,Nr.pick(En,gn));var E=t.Array,mt=t.Date,Dt=t.Error,Ot=t.Function,kt=t.Math,D=t.Object,At=t.RegExp,jt=t.String,Mt=t.TypeError,Nt=E.prototype,Pt=Ot.prototype,Ft=D.prototype,It=t[`__core-js_shared__`],Lt=Pt.toString,O=Ft.hasOwnProperty,Rt=0,zt=function(){var e=/[^.]+$/.exec(It&&It.keys&&It.keys.IE_PROTO||``);return e?`Symbol(src)_1.`+e:``}(),Bt=Ft.toString,Vt=Lt.call(D),Ht=En._,Ut=At(`^`+Lt.call(O).replace(ct,`\\$&`).replace(/hasOwnProperty|(function).*?(?=\\\()| for .+?(?=\\\])/g,`$1.*?`)+`$`),Wt=kn?t.Buffer:n,Gt=t.Symbol,Kt=t.Uint8Array,qt=Wt?Wt.allocUnsafe:n,Jt=xr(D.getPrototypeOf,D),Yt=D.create,Xt=Ft.propertyIsEnumerable,Zt=Nt.splice,Qt=Gt?Gt.isConcatSpreadable:n,$t=Gt?Gt.iterator:n,en=Gt?Gt.toStringTag:n,tn=function(){try{var e=Zo(D,`defineProperty`);return e({},``,{}),e}catch{}}(),nn=t.clearTimeout!==En.clearTimeout&&t.clearTimeout,rn=mt&&mt.now!==En.Date.now&&mt.now,an=t.setTimeout!==En.setTimeout&&t.setTimeout,on=kt.ceil,sn=kt.floor,cn=D.getOwnPropertySymbols,ln=Wt?Wt.isBuffer:n,fn=t.isFinite,pn=Nt.join,mn=xr(D.keys,D),hn=kt.max,vn=kt.min,yn=mt.now,bn=t.parseInt,xn=kt.random,wn=Nt.reverse,Tn=Zo(t,`DataView`),Dn=Zo(t,`Map`),On=Zo(t,`Promise`),An=Zo(t,`Set`),jn=Zo(t,`WeakMap`),Yn=Zo(D,`create`),Xn=jn&&new jn,ir={},Tr=Is(Tn),Ar=Is(Dn),jr=Is(On),Pr=Is(An),Fr=Is(jn),Ir=Gt?Gt.prototype:n,Lr=Ir?Ir.valueOf:n,Rr=Ir?Ir.toString:n;function z(e){if(Ou(e)&&!Z(e)&&!(e instanceof V)){if(e instanceof B)return e;if(O.call(e,`__wrapped__`))return Rs(e)}return new B(e)}var zr=function(){function e(){}return function(t){if(!Du(t))return{};if(Yt)return Yt(t);e.prototype=t;var r=new e;return e.prototype=n,r}}();function Br(){}function B(e,t){this.__wrapped__=e,this.__actions__=[],this.__chain__=!!t,this.__index__=0,this.__values__=n}z.templateSettings={escape:nt,evaluate:rt,interpolate:it,variable:``,imports:{_:z}},z.prototype=Br.prototype,z.prototype.constructor=z,B.prototype=zr(Br.prototype),B.prototype.constructor=B;function V(e){this.__wrapped__=e,this.__actions__=[],this.__dir__=1,this.__filtered__=!1,this.__iteratees__=[],this.__takeCount__=fe,this.__views__=[]}function Vr(){var e=new V(this.__wrapped__);return e.__actions__=co(this.__actions__),e.__dir__=this.__dir__,e.__filtered__=this.__filtered__,e.__iteratees__=co(this.__iteratees__),e.__takeCount__=this.__takeCount__,e.__views__=co(this.__views__),e}function Hr(){if(this.__filtered__){var e=new V(this);e.__dir__=-1,e.__filtered__=!0}else e=this.clone(),e.__dir__*=-1;return e}function Ur(){var e=this.__wrapped__.value(),t=this.__dir__,n=Z(e),r=t<0,i=n?e.length:0,a=ns(0,i,this.__views__),o=a.start,s=a.end,c=s-o,l=r?s:o-1,u=this.__iteratees__,d=u.length,f=0,p=vn(c,this.__takeCount__);if(!n||!r&&i==c&&p==c)return Ua(e,this.__actions__);var m=[];outer:for(;c--&&f<p;){l+=t;for(var h=-1,g=e[l];++h<d;){var _=u[h],v=_.iteratee,y=_.type,b=v(g);if(y==se)g=b;else if(!b){if(y==oe)continue outer;break outer}}m[f++]=g}return m}V.prototype=zr(Br.prototype),V.prototype.constructor=V;function Wr(e){var t=-1,n=e==null?0:e.length;for(this.clear();++t<n;){var r=e[t];this.set(r[0],r[1])}}function Gr(){this.__data__=Yn?Yn(null):{},this.size=0}function Kr(e){var t=this.has(e)&&delete this.__data__[e];return this.size-=+!!t,t}function qr(e){var t=this.__data__;if(Yn){var r=t[e];return r===l?n:r}return O.call(t,e)?t[e]:n}function Jr(e){var t=this.__data__;return Yn?t[e]!==n:O.call(t,e)}function Yr(e,t){var r=this.__data__;return this.size+=+!this.has(e),r[e]=Yn&&t===n?l:t,this}Wr.prototype.clear=Gr,Wr.prototype.delete=Kr,Wr.prototype.get=qr,Wr.prototype.has=Jr,Wr.prototype.set=Yr;function Xr(e){var t=-1,n=e==null?0:e.length;for(this.clear();++t<n;){var r=e[t];this.set(r[0],r[1])}}function Zr(){this.__data__=[],this.size=0}function Qr(e){var t=this.__data__,n=Ci(t,e);return n<0?!1:(n==t.length-1?t.pop():Zt.call(t,n,1),--this.size,!0)}function $r(e){var t=this.__data__,r=Ci(t,e);return r<0?n:t[r][1]}function ei(e){return Ci(this.__data__,e)>-1}function ti(e,t){var n=this.__data__,r=Ci(n,e);return r<0?(++this.size,n.push([e,t])):n[r][1]=t,this}Xr.prototype.clear=Zr,Xr.prototype.delete=Qr,Xr.prototype.get=$r,Xr.prototype.has=ei,Xr.prototype.set=ti;function ni(e){var t=-1,n=e==null?0:e.length;for(this.clear();++t<n;){var r=e[t];this.set(r[0],r[1])}}function ri(){this.size=0,this.__data__={hash:new Wr,map:new(Dn||Xr),string:new Wr}}function ii(e){var t=Yo(this,e).delete(e);return this.size-=+!!t,t}function ai(e){return Yo(this,e).get(e)}function oi(e){return Yo(this,e).has(e)}function si(e,t){var n=Yo(this,e),r=n.size;return n.set(e,t),this.size+=n.size==r?0:1,this}ni.prototype.clear=ri,ni.prototype.delete=ii,ni.prototype.get=ai,ni.prototype.has=oi,ni.prototype.set=si;function ci(e){var t=-1,n=e==null?0:e.length;for(this.__data__=new ni;++t<n;)this.add(e[t])}function li(e){return this.__data__.set(e,l),this}function ui(e){return this.__data__.has(e)}ci.prototype.add=ci.prototype.push=li,ci.prototype.has=ui;function di(e){var t=this.__data__=new Xr(e);this.size=t.size}function fi(){this.__data__=new Xr,this.size=0}function pi(e){var t=this.__data__,n=t.delete(e);return this.size=t.size,n}function mi(e){return this.__data__.get(e)}function hi(e){return this.__data__.has(e)}function gi(e,t){var n=this.__data__;if(n instanceof Xr){var r=n.__data__;if(!Dn||r.length<i-1)return r.push([e,t]),this.size=++n.size,this;n=this.__data__=new ni(r)}return n.set(e,t),this.size=n.size,this}di.prototype.clear=fi,di.prototype.delete=pi,di.prototype.get=mi,di.prototype.has=hi,di.prototype.set=gi;function _i(e,t){var n=Z(e),r=!n&&du(e),i=!n&&!r&&gu(e),a=!n&&!r&&!i&&Uu(e),o=n||r||i||a,s=o?sr(e.length,jt):[],c=s.length;for(var l in e)(t||O.call(e,l))&&!(o&&(l==`length`||i&&(l==`offset`||l==`parent`)||a&&(l==`buffer`||l==`byteLength`||l==`byteOffset`)||us(l,c)))&&s.push(l);return s}function vi(e){var t=e.length;return t?e[wa(0,t-1)]:n}function yi(e,t){return Ns(co(e),Di(t,0,e.length))}function bi(e){return Ns(co(e))}function xi(e,t,r){(r!==n&&!cu(e[t],r)||r===n&&!(t in e))&&Ti(e,t,r)}function Si(e,t,r){var i=e[t];(!(O.call(e,t)&&cu(i,r))||r===n&&!(t in e))&&Ti(e,t,r)}function Ci(e,t){for(var n=e.length;n--;)if(cu(e[n][0],t))return n;return-1}function H(e,t,n,r){return G(e,function(e,i,a){t(r,e,n(e),a)}),r}function wi(e,t){return e&&lo(t,Cd(t),e)}function U(e,t){return e&&lo(t,wd(t),e)}function Ti(e,t,n){t==`__proto__`&&tn?tn(e,t,{configurable:!0,enumerable:!0,value:n,writable:!0}):e[t]=n}function Ei(e,t){for(var r=-1,i=t.length,a=E(i),o=e==null;++r<i;)a[r]=o?n:_d(e,t[r]);return a}function Di(e,t,r){return e===e&&(r!==n&&(e=e<=r?e:r),t!==n&&(e=e>=t?e:t)),e}function Oi(e,t,r,i,a,o){var s,c=t&f,l=t&p,u=t&m;if(r&&(s=a?r(e,i,a,o):r(e)),s!==n)return s;if(!Du(e))return e;var d=Z(e);if(d){if(s=as(e),!c)return co(e,s)}else{var h=ts(e),g=h==Ce||h==we;if(gu(e))return Qa(e,c);if(h==Oe||h==ge||g&&!a){if(s=l||g?{}:os(e),!c)return l?fo(e,U(s,e)):uo(e,wi(s,e))}else{if(!A[h])return a?e:{};s=ss(e,h,c)}}o||=new di;var _=o.get(e);if(_)return _;o.set(e,s),Bu(e)?e.forEach(function(n){s.add(Oi(n,t,r,n,e,o))}):ku(e)&&e.forEach(function(n,i){s.set(i,Oi(n,t,r,i,e,o))});var v=d?n:(u?l?Go:Wo:l?wd:Cd)(e);return zn(v||e,function(n,i){v&&(i=n,n=e[i]),Si(s,i,Oi(n,t,r,i,e,o))}),s}function ki(e){var t=Cd(e);return function(n){return Ai(n,e,t)}}function Ai(e,t,r){var i=r.length;if(e==null)return!i;for(e=D(e);i--;){var a=r[i],o=t[a],s=e[a];if(s===n&&!(a in e)||!o(s))return!1}return!0}function ji(e,t,r){if(typeof e!=`function`)throw new Mt(o);return ks(function(){e.apply(n,r)},t)}function W(e,t,n,r){var a=-1,o=Un,s=!0,c=e.length,l=[],u=t.length;if(!c)return l;n&&(t=M(t,ur(n))),r?(o=Wn,s=!1):t.length>=i&&(o=fr,s=!1,t=new ci(t));outer:for(;++a<c;){var d=e[a],f=n==null?d:n(d);if(d=r||d!==0?d:0,s&&f===f){for(var p=u;p--;)if(t[p]===f)continue outer;l.push(d)}else o(t,f,r)||l.push(d)}return l}var G=ho(zi),Mi=ho(Bi,!0);function Ni(e,t){var n=!0;return G(e,function(e,r,i){return n=!!t(e,r,i),n}),n}function Pi(e,t,r){for(var i=-1,a=e.length;++i<a;){var o=e[i],s=t(o);if(s!=null&&(c===n?s===s&&!Hu(s):r(s,c)))var c=s,l=o}return l}function Fi(e,t,r,i){var a=e.length;for(r=Q(r),r<0&&(r=-r>a?0:a+r),i=i===n||i>a?a:Q(i),i<0&&(i+=a),i=r>i?0:Zu(i);r<i;)e[r++]=t;return e}function K(e,t){var n=[];return G(e,function(e,r,i){t(e,r,i)&&n.push(e)}),n}function Ii(e,t,n,r,i){var a=-1,o=e.length;for(n||=ls,i||=[];++a<o;){var s=e[a];t>0&&n(s)?t>1?Ii(s,t-1,n,r,i):Gn(i,s):r||(i[i.length]=s)}return i}var Li=go(),Ri=go(!0);function zi(e,t){return e&&Li(e,t,Cd)}function Bi(e,t){return e&&Ri(e,t,Cd)}function Vi(e,t){return Hn(t,function(t){return wu(e[t])})}function q(e,t){t=Ja(t,e);for(var r=0,i=t.length;e!=null&&r<i;)e=e[Fs(t[r++])];return r&&r==i?e:n}function Hi(e,t,n){var r=t(e);return Z(e)?r:Gn(r,n(e))}function Ui(e){return e==null?e===n?Fe:De:en&&en in D(e)?Qo(e):Cs(e)}function Wi(e,t){return e>t}function Gi(e,t){return e!=null&&O.call(e,t)}function Ki(e,t){return e!=null&&t in D(e)}function qi(e,t,n){return e>=vn(t,n)&&e<hn(t,n)}function Ji(e,t,r){for(var i=r?Wn:Un,a=e[0].length,o=e.length,s=o,c=E(o),l=1/0,u=[];s--;){var d=e[s];s&&t&&(d=M(d,ur(t))),l=vn(d.length,l),c[s]=!r&&(t||a>=120&&d.length>=120)?new ci(s&&d):n}d=e[0];var f=-1,p=c[0];outer:for(;++f<a&&u.length<l;){var m=d[f],h=t?t(m):m;if(m=r||m!==0?m:0,!(p?fr(p,h):i(u,h,r))){for(s=o;--s;){var g=c[s];if(!(g?fr(g,h):i(e[s],h,r)))continue outer}p&&p.push(h),u.push(m)}}return u}function Yi(e,t,n,r){return zi(e,function(e,i,a){t(r,n(e),i,a)}),r}function Xi(e,t,r){t=Ja(t,e),e=Ts(e,t);var i=e==null?e:e[Fs(lc(t))];return i==null?n:Ln(i,e,r)}function Zi(e){return Ou(e)&&Ui(e)==ge}function Qi(e){return Ou(e)&&Ui(e)==Re}function $i(e){return Ou(e)&&Ui(e)==be}function ea(e,t,n,r,i){return e===t?!0:e==null||t==null||!Ou(e)&&!Ou(t)?e!==e&&t!==t:ta(e,t,n,r,ea,i)}function ta(e,t,n,r,i,a){var o=Z(e),s=Z(t),c=o?_e:ts(e),l=s?_e:ts(t);c=c==ge?Oe:c,l=l==ge?Oe:l;var u=c==Oe,d=l==Oe,f=c==l;if(f&&gu(e)){if(!gu(t))return!1;o=!0,u=!1}if(f&&!u)return a||=new di,o||Uu(e)?Bo(e,t,n,r,i,a):Vo(e,t,c,n,r,i,a);if(!(n&h)){var p=u&&O.call(e,`__wrapped__`),m=d&&O.call(t,`__wrapped__`);if(p||m){var g=p?e.value():e,_=m?t.value():t;return a||=new di,i(g,_,n,r,a)}}return f?(a||=new di,Ho(e,t,n,r,i,a)):!1}function na(e){return Ou(e)&&ts(e)==Te}function ra(e,t,r,i){var a=r.length,o=a,s=!i;if(e==null)return!o;for(e=D(e);a--;){var c=r[a];if(s&&c[2]?c[1]!==e[c[0]]:!(c[0]in e))return!1}for(;++a<o;){c=r[a];var l=c[0],u=e[l],d=c[1];if(s&&c[2]){if(u===n&&!(l in e))return!1}else{var f=new di;if(i)var p=i(u,d,l,e,t,f);if(!(p===n?ea(d,u,h|g,i,f):p))return!1}}return!0}function ia(e){return!Du(e)||hs(e)?!1:(wu(e)?Ut:xt).test(Is(e))}function aa(e){return Ou(e)&&Ui(e)==je}function oa(e){return Ou(e)&&ts(e)==Me}function sa(e){return Ou(e)&&Eu(e.length)&&!!k[Ui(e)]}function ca(e){return typeof e==`function`?e:e==null?Mf:typeof e==`object`?Z(e)?ma(e[0],e[1]):pa(e):Gf(e)}function la(e){if(!_s(e))return mn(e);var t=[];for(var n in D(e))O.call(e,n)&&n!=`constructor`&&t.push(n);return t}function ua(e){if(!Du(e))return Ss(e);var t=_s(e),n=[];for(var r in e)r==`constructor`&&(t||!O.call(e,r))||n.push(r);return n}function da(e,t){return e<t}function fa(e,t){var n=-1,r=pu(e)?E(e.length):[];return G(e,function(e,i,a){r[++n]=t(e,i,a)}),r}function pa(e){var t=Xo(e);return t.length==1&&t[0][2]?ys(t[0][0],t[0][1]):function(n){return n===e||ra(n,e,t)}}function ma(e,t){return fs(e)&&vs(t)?ys(Fs(e),t):function(r){var i=_d(r,e);return i===n&&i===t?yd(r,e):ea(t,i,h|g)}}function ha(e,t,r,i,a){e!==t&&Li(t,function(o,s){if(a||=new di,Du(o))ga(e,t,s,r,ha,i,a);else{var c=i?i(Ds(e,s),o,s+``,e,t,a):n;c===n&&(c=o),xi(e,s,c)}},wd)}function ga(e,t,r,i,a,o,s){var c=Ds(e,r),l=Ds(t,r),u=s.get(l);if(u){xi(e,r,u);return}var d=o?o(c,l,r+``,e,t,s):n,f=d===n;if(f){var p=Z(l),m=!p&&gu(l),h=!p&&!m&&Uu(l);d=l,p||m||h?Z(c)?d=c:mu(c)?d=co(c):m?(f=!1,d=Qa(l,!0)):h?(f=!1,d=ro(l,!0)):d=[]:Lu(l)||du(l)?(d=c,du(c)?d=$u(c):(!Du(c)||wu(c))&&(d=os(l))):f=!1}f&&(s.set(l,d),a(d,l,i,o,s),s.delete(l)),xi(e,r,d)}function _a(e,t){var r=e.length;if(r)return t+=t<0?r:0,us(t,r)?e[t]:n}function va(e,t,n){t=t.length?M(t,function(e){return Z(e)?function(t){return q(t,e.length===1?e[0]:e)}:e}):[Mf];var r=-1;return t=M(t,ur(X())),ar(fa(e,function(e,n,i){return{criteria:M(t,function(t){return t(e)}),index:++r,value:e}}),function(e,t){return ao(e,t,n)})}function ya(e,t){return ba(e,t,function(t,n){return yd(e,n)})}function ba(e,t,n){for(var r=-1,i=t.length,a={};++r<i;){var o=t[r],s=q(e,o);n(s,o)&&ka(a,Ja(o,e),s)}return a}function xa(e){return function(t){return q(t,e)}}function Sa(e,t,n,r){var i=r?er:$n,a=-1,o=t.length,s=e;for(e===t&&(t=co(t)),n&&(s=M(e,ur(n)));++a<o;)for(var c=0,l=t[a],u=n?n(l):l;(c=i(s,u,c,r))>-1;)s!==e&&Zt.call(s,c,1),Zt.call(e,c,1);return e}function Ca(e,t){for(var n=e?t.length:0,r=n-1;n--;){var i=t[n];if(n==r||i!==a){var a=i;us(i)?Zt.call(e,i,1):Ba(e,i)}}return e}function wa(e,t){return e+sn(xn()*(t-e+1))}function Ta(e,t,n,r){for(var i=-1,a=hn(on((t-e)/(n||1)),0),o=E(a);a--;)o[r?a:++i]=e,e+=n;return o}function Ea(e,t){var n=``;if(!e||t<1||t>le)return n;do t%2&&(n+=e),t=sn(t/2),t&&(e+=e);while(t);return n}function J(e,t){return As(ws(e,t,Mf),e+``)}function Da(e){return vi(Hd(e))}function Oa(e,t){var n=Hd(e);return Ns(n,Di(t,0,n.length))}function ka(e,t,r,i){if(!Du(e))return e;t=Ja(t,e);for(var a=-1,o=t.length,s=o-1,c=e;c!=null&&++a<o;){var l=Fs(t[a]),u=r;if(l===`__proto__`||l===`constructor`||l===`prototype`)return e;if(a!=s){var d=c[l];u=i?i(d,l,c):n,u===n&&(u=Du(d)?d:us(t[a+1])?[]:{})}Si(c,l,u),c=c[l]}return e}var Aa=Xn?function(e,t){return Xn.set(e,t),e}:Mf,ja=tn?function(e,t){return tn(e,`toString`,{configurable:!0,enumerable:!1,value:Of(t),writable:!0})}:Mf;function Ma(e){return Ns(Hd(e))}function Na(e,t,n){var r=-1,i=e.length;t<0&&(t=-t>i?0:i+t),n=n>i?i:n,n<0&&(n+=i),i=t>n?0:n-t>>>0,t>>>=0;for(var a=E(i);++r<i;)a[r]=e[r+t];return a}function Pa(e,t){var n;return G(e,function(e,r,i){return n=t(e,r,i),!n}),!!n}function Fa(e,t,n){var r=0,i=e==null?r:e.length;if(typeof t==`number`&&t===t&&i<=me){for(;r<i;){var a=r+i>>>1,o=e[a];o!==null&&!Hu(o)&&(n?o<=t:o<t)?r=a+1:i=a}return i}return Ia(e,t,Mf,n)}function Ia(e,t,r,i){var a=0,o=e==null?0:e.length;if(o===0)return 0;t=r(t);for(var s=t!==t,c=t===null,l=Hu(t),u=t===n;a<o;){var d=sn((a+o)/2),f=r(e[d]),p=f!==n,m=f===null,h=f===f,g=Hu(f);if(s)var _=i||h;else _=u?h&&(i||p):c?h&&p&&(i||!m):l?h&&p&&!m&&(i||!g):m||g?!1:i?f<=t:f<t;_?a=d+1:o=d}return vn(o,pe)}function La(e,t){for(var n=-1,r=e.length,i=0,a=[];++n<r;){var o=e[n],s=t?t(o):o;if(!n||!cu(s,c)){var c=s;a[i++]=o===0?0:o}}return a}function Ra(e){return typeof e==`number`?e:Hu(e)?de:+e}function za(e){if(typeof e==`string`)return e;if(Z(e))return M(e,za)+``;if(Hu(e))return Rr?Rr.call(e):``;var t=e+``;return t==`0`&&1/e==-T?`-0`:t}function Y(e,t,n){var r=-1,a=Un,o=e.length,s=!0,c=[],l=c;if(n)s=!1,a=Wn;else if(o>=i){var u=t?null:Po(e);if(u)return Cr(u);s=!1,a=fr,l=new ci}else l=t?[]:c;outer:for(;++r<o;){var d=e[r],f=t?t(d):d;if(d=n||d!==0?d:0,s&&f===f){for(var p=l.length;p--;)if(l[p]===f)continue outer;t&&l.push(f),c.push(d)}else a(l,f,n)||(l!==c&&l.push(f),c.push(d))}return c}function Ba(e,t){t=Ja(t,e);var n=-1,r=t.length;if(!r)return!0;for(;++n<r;){var i=Fs(t[n]);if(i===`__proto__`&&!O.call(e,`__proto__`)||(i===`constructor`||i===`prototype`)&&n<r-1)return!1}var a=Ts(e,t);return a==null||delete a[Fs(lc(t))]}function Va(e,t,n,r){return ka(e,t,n(q(e,t)),r)}function Ha(e,t,n,r){for(var i=e.length,a=r?i:-1;(r?a--:++a<i)&&t(e[a],a,e););return n?Na(e,r?0:a,r?a+1:i):Na(e,r?a+1:0,r?i:a)}function Ua(e,t){var n=e;return n instanceof V&&(n=n.value()),Kn(t,function(e,t){return t.func.apply(t.thisArg,Gn([e],t.args))},n)}function Wa(e,t,n){var r=e.length;if(r<2)return r?Y(e[0]):[];for(var i=-1,a=E(r);++i<r;)for(var o=e[i],s=-1;++s<r;)s!=i&&(a[i]=W(a[i]||o,e[s],t,n));return Y(Ii(a,1),t,n)}function Ga(e,t,r){for(var i=-1,a=e.length,o=t.length,s={};++i<a;){var c=i<o?t[i]:n;r(s,e[i],c)}return s}function Ka(e){return mu(e)?e:[]}function qa(e){return typeof e==`function`?e:Mf}function Ja(e,t){return Z(e)?e:fs(e,t)?[e]:Ps($(e))}var Ya=J;function Xa(e,t,r){var i=e.length;return r=r===n?i:r,!t&&r>=i?e:Na(e,t,r)}var Za=nn||function(e){return En.clearTimeout(e)};function Qa(e,t){if(t)return e.slice();var n=e.length,r=qt?qt(n):new e.constructor(n);return e.copy(r),r}function $a(e){var t=new e.constructor(e.byteLength);return new Kt(t).set(new Kt(e)),t}function eo(e,t){var n=t?$a(e.buffer):e.buffer;return new e.constructor(n,e.byteOffset,e.byteLength)}function to(e){var t=new e.constructor(e.source,vt.exec(e));return t.lastIndex=e.lastIndex,t}function no(e){return Lr?D(Lr.call(e)):{}}function ro(e,t){var n=t?$a(e.buffer):e.buffer;return new e.constructor(n,e.byteOffset,e.length)}function io(e,t){if(e!==t){var r=e!==n,i=e===null,a=e===e,o=Hu(e),s=t!==n,c=t===null,l=t===t,u=Hu(t);if(!c&&!u&&!o&&e>t||o&&s&&l&&!c&&!u||i&&s&&l||!r&&l||!a)return 1;if(!i&&!o&&!u&&e<t||u&&r&&a&&!i&&!o||c&&r&&a||!s&&a||!l)return-1}return 0}function ao(e,t,n){for(var r=-1,i=e.criteria,a=t.criteria,o=i.length,s=n.length;++r<o;){var c=io(i[r],a[r]);if(c)return r>=s?c:c*(n[r]==`desc`?-1:1)}return e.index-t.index}function oo(e,t,n,r){for(var i=-1,a=e.length,o=n.length,s=-1,c=t.length,l=hn(a-o,0),u=E(c+l),d=!r;++s<c;)u[s]=t[s];for(;++i<o;)(d||i<a)&&(u[n[i]]=e[i]);for(;l--;)u[s++]=e[i++];return u}function so(e,t,n,r){for(var i=-1,a=e.length,o=-1,s=n.length,c=-1,l=t.length,u=hn(a-s,0),d=E(u+l),f=!r;++i<u;)d[i]=e[i];for(var p=i;++c<l;)d[p+c]=t[c];for(;++o<s;)(f||i<a)&&(d[p+n[o]]=e[i++]);return d}function co(e,t){var n=-1,r=e.length;for(t||=E(r);++n<r;)t[n]=e[n];return t}function lo(e,t,r,i){var a=!r;r||={};for(var o=-1,s=t.length;++o<s;){var c=t[o],l=i?i(r[c],e[c],c,r,e):n;l===n&&(l=e[c]),a?Ti(r,c,l):Si(r,c,l)}return r}function uo(e,t){return lo(e,$o(e),t)}function fo(e,t){return lo(e,es(e),t)}function po(e,t){return function(n,r){var i=Z(n)?Rn:H,a=t?t():{};return i(n,e,X(r,2),a)}}function mo(e){return J(function(t,r){var i=-1,a=r.length,o=a>1?r[a-1]:n,s=a>2?r[2]:n;for(o=e.length>3&&typeof o==`function`?(a--,o):n,s&&ds(r[0],r[1],s)&&(o=a<3?n:o,a=1),t=D(t);++i<a;){var c=r[i];c&&e(t,c,i,o)}return t})}function ho(e,t){return function(n,r){if(n==null)return n;if(!pu(n))return e(n,r);for(var i=n.length,a=t?i:-1,o=D(n);(t?a--:++a<i)&&r(o[a],a,o)!==!1;);return n}}function go(e){return function(t,n,r){for(var i=-1,a=D(t),o=r(t),s=o.length;s--;){var c=o[e?s:++i];if(n(a[c],c,a)===!1)break}return t}}function _o(e,t,n){var r=t&_,i=bo(e);function a(){return(this&&this!==En&&this instanceof a?i:e).apply(r?n:this,arguments)}return a}function vo(e){return function(t){t=$(t);var r=vr(t)?R(t):n,i=r?r[0]:t.charAt(0),a=r?Xa(r,1).join(``):t.slice(1);return i[e]()+a}}function yo(e){return function(t){return Kn(Cf(Yd(t).replace(un,``)),e,``)}}function bo(e){return function(){var t=arguments;switch(t.length){case 0:return new e;case 1:return new e(t[0]);case 2:return new e(t[0],t[1]);case 3:return new e(t[0],t[1],t[2]);case 4:return new e(t[0],t[1],t[2],t[3]);case 5:return new e(t[0],t[1],t[2],t[3],t[4]);case 6:return new e(t[0],t[1],t[2],t[3],t[4],t[5]);case 7:return new e(t[0],t[1],t[2],t[3],t[4],t[5],t[6])}var n=zr(e.prototype),r=e.apply(n,t);return Du(r)?r:n}}function xo(e,t,r){var i=bo(e);function a(){for(var o=arguments.length,s=E(o),c=o,l=Jo(a);c--;)s[c]=arguments[c];var u=o<3&&s[0]!==l&&s[o-1]!==l?[]:Sr(s,l);return o-=u.length,o<r?Mo(e,t,wo,a.placeholder,n,s,u,n,n,r-o):Ln(this&&this!==En&&this instanceof a?i:e,this,s)}return a}function So(e){return function(t,r,i){var a=D(t);if(!pu(t)){var o=X(r,3);t=Cd(t),r=function(e){return o(a[e],e,a)}}var s=e(t,r,i);return s>-1?a[o?t[s]:s]:n}}function Co(e){return Uo(function(t){var r=t.length,i=r,a=B.prototype.thru;for(e&&t.reverse();i--;){var s=t[i];if(typeof s!=`function`)throw new Mt(o);if(a&&!c&&qo(s)==`wrapper`)var c=new B([],!0)}for(i=c?i:r;++i<r;){s=t[i];var l=qo(s),u=l==`wrapper`?Ko(s):n;c=u&&ms(u[0])&&u[1]==(ee|b|S|te)&&!u[4].length&&u[9]==1?c[qo(u[0])].apply(c,u[3]):s.length==1&&ms(s)?c[l]():c.thru(s)}return function(){var e=arguments,n=e[0];if(c&&e.length==1&&Z(n))return c.plant(n).value();for(var i=0,a=r?t[i].apply(this,e):n;++i<r;)a=t[i].call(this,a);return a}})}function wo(e,t,r,i,a,o,s,c,l,u){var d=t&ee,f=t&_,p=t&v,m=t&(b|x),h=t&w,g=p?n:bo(e);function y(){for(var n=arguments.length,_=E(n),v=n;v--;)_[v]=arguments[v];if(m)var b=Jo(y),x=pr(_,b);if(i&&(_=oo(_,i,a,m)),o&&(_=so(_,o,s,m)),n-=x,m&&n<u){var S=Sr(_,b);return Mo(e,t,wo,y.placeholder,r,_,S,c,l,u-n)}var C=f?r:this,ee=p?C[e]:e;return n=_.length,c?_=Es(_,c):h&&n>1&&_.reverse(),d&&l<n&&(_.length=l),this&&this!==En&&this instanceof y&&(ee=g||bo(ee)),ee.apply(C,_)}return y}function To(e,t){return function(n,r){return Yi(n,e,t(r),{})}}function Eo(e,t){return function(r,i){var a;if(r===n&&i===n)return t;if(r!==n&&(a=r),i!==n){if(a===n)return i;typeof r==`string`||typeof i==`string`?(r=za(r),i=za(i)):(r=Ra(r),i=Ra(i)),a=e(r,i)}return a}}function Do(e){return Uo(function(t){return t=M(t,ur(X())),J(function(n){var r=this;return e(t,function(e){return Ln(e,r,n)})})})}function Oo(e,t){t=t===n?` `:za(t);var r=t.length;if(r<2)return r?Ea(t,e):t;var i=Ea(t,on(e/Dr(t)));return vr(t)?Xa(R(i),0,e).join(``):i.slice(0,e)}function ko(e,t,n,r){var i=t&_,a=bo(e);function o(){for(var t=-1,s=arguments.length,c=-1,l=r.length,u=E(l+s),d=this&&this!==En&&this instanceof o?a:e;++c<l;)u[c]=r[c];for(;s--;)u[c++]=arguments[++t];return Ln(d,i?n:this,u)}return o}function Ao(e){return function(t,r,i){return i&&typeof i!=`number`&&ds(t,r,i)&&(r=i=n),t=Xu(t),r===n?(r=t,t=0):r=Xu(r),i=i===n?t<r?1:-1:Xu(i),Ta(t,r,i,e)}}function jo(e){return function(t,n){return typeof t==`string`&&typeof n==`string`||(t=Qu(t),n=Qu(n)),e(t,n)}}function Mo(e,t,r,i,a,o,s,c,l,u){var d=t&b,f=d?s:n,p=d?n:s,m=d?o:n,h=d?n:o;t|=d?S:C,t&=~(d?C:S),t&y||(t&=~(_|v));var g=[e,t,a,m,f,h,p,c,l,u],x=r.apply(n,g);return ms(e)&&Os(x,g),x.placeholder=i,js(x,e,t)}function No(e){var t=kt[e];return function(e,n){if(e=Qu(e),n=n==null?0:vn(Q(n),292),n&&fn(e)){var r=($(e)+`e`).split(`e`);return r=($(t(r[0]+`e`+(+r[1]+n)))+`e`).split(`e`),+(r[0]+`e`+(+r[1]-n))}return t(e)}}var Po=An&&1/Cr(new An([,-0]))[1]==T?function(e){return new An(e)}:Bf;function Fo(e){return function(t){var n=ts(t);return n==Te?L(t):n==Me?wr(t):cr(t,e(t))}}function Io(e,t,r,i,a,s,c,l){var u=t&v;if(!u&&typeof e!=`function`)throw new Mt(o);var d=i?i.length:0;if(d||(t&=~(S|C),i=a=n),c=c===n?c:hn(Q(c),0),l=l===n?l:Q(l),d-=a?a.length:0,t&C){var f=i,p=a;i=a=n}var m=u?n:Ko(e),h=[e,t,r,i,a,f,p,s,c,l];if(m&&xs(h,m),e=h[0],t=h[1],r=h[2],i=h[3],a=h[4],l=h[9]=h[9]===n?u?0:e.length:hn(h[9]-d,0),!l&&t&(b|x)&&(t&=~(b|x)),!t||t==_)var g=_o(e,t,r);else g=t==b||t==x?xo(e,t,l):(t==S||t==(_|S))&&!a.length?ko(e,t,r,i):wo.apply(n,h);return js((m?Aa:Os)(g,h),e,t)}function Lo(e,t,r,i){return e===n||cu(e,Ft[r])&&!O.call(i,r)?t:e}function Ro(e,t,r,i,a,o){return Du(e)&&Du(t)&&(o.set(t,e),ha(e,t,n,Ro,o),o.delete(t)),e}function zo(e){return Lu(e)?n:e}function Bo(e,t,r,i,a,o){var s=r&h,c=e.length,l=t.length;if(c!=l&&!(s&&l>c))return!1;var u=o.get(e),d=o.get(t);if(u&&d)return u==t&&d==e;var f=-1,p=!0,m=r&g?new ci:n;for(o.set(e,t),o.set(t,e);++f<c;){var _=e[f],v=t[f];if(i)var y=s?i(v,_,f,t,e,o):i(_,v,f,e,t,o);if(y!==n){if(y)continue;p=!1;break}if(m){if(!Jn(t,function(e,t){if(!fr(m,t)&&(_===e||a(_,e,r,i,o)))return m.push(t)})){p=!1;break}}else if(!(_===v||a(_,v,r,i,o))){p=!1;break}}return o.delete(e),o.delete(t),p}function Vo(e,t,n,r,i,a,o){switch(n){case ze:if(e.byteLength!=t.byteLength||e.byteOffset!=t.byteOffset)return!1;e=e.buffer,t=t.buffer;case Re:return!(e.byteLength!=t.byteLength||!a(new Kt(e),new Kt(t)));case ye:case be:case Ee:return cu(+e,+t);case Se:return e.name==t.name&&e.message==t.message;case je:case Ne:return e==t+``;case Te:var s=L;case Me:var c=r&h;if(s||=Cr,e.size!=t.size&&!c)return!1;var l=o.get(e);if(l)return l==t;r|=g,o.set(e,t);var u=Bo(s(e),s(t),r,i,a,o);return o.delete(e),u;case Pe:if(Lr)return Lr.call(e)==Lr.call(t)}return!1}function Ho(e,t,r,i,a,o){var s=r&h,c=Wo(e),l=c.length;if(l!=Wo(t).length&&!s)return!1;for(var u=l;u--;){var d=c[u];if(!(s?d in t:O.call(t,d)))return!1}var f=o.get(e),p=o.get(t);if(f&&p)return f==t&&p==e;var m=!0;o.set(e,t),o.set(t,e);for(var g=s;++u<l;){d=c[u];var _=e[d],v=t[d];if(i)var y=s?i(v,_,d,t,e,o):i(_,v,d,e,t,o);if(!(y===n?_===v||a(_,v,r,i,o):y)){m=!1;break}g||=d==`constructor`}if(m&&!g){var b=e.constructor,x=t.constructor;b!=x&&`constructor`in e&&`constructor`in t&&!(typeof b==`function`&&b instanceof b&&typeof x==`function`&&x instanceof x)&&(m=!1)}return o.delete(e),o.delete(t),m}function Uo(e){return As(ws(e,n,Qs),e+``)}function Wo(e){return Hi(e,Cd,$o)}function Go(e){return Hi(e,wd,es)}var Ko=Xn?function(e){return Xn.get(e)}:Bf;function qo(e){for(var t=e.name+``,n=ir[t],r=O.call(ir,t)?n.length:0;r--;){var i=n[r],a=i.func;if(a==null||a==e)return i.name}return t}function Jo(e){return(O.call(z,`placeholder`)?z:e).placeholder}function X(){var e=z.iteratee||Nf;return e=e===Nf?ca:e,arguments.length?e(arguments[0],arguments[1]):e}function Yo(e,t){var n=e.__data__;return ps(t)?n[typeof t==`string`?`string`:`hash`]:n.map}function Xo(e){for(var t=Cd(e),n=t.length;n--;){var r=t[n],i=e[r];t[n]=[r,i,vs(i)]}return t}function Zo(e,t){var r=_r(e,t);return ia(r)?r:n}function Qo(e){var t=O.call(e,en),r=e[en];try{e[en]=n;var i=!0}catch{}var a=Bt.call(e);return i&&(t?e[en]=r:delete e[en]),a}var $o=cn?function(e){return e==null?[]:(e=D(e),Hn(cn(e),function(t){return Xt.call(e,t)}))}:Yf,es=cn?function(e){for(var t=[];e;)Gn(t,$o(e)),e=Jt(e);return t}:Yf,ts=Ui;(Tn&&ts(new Tn(new ArrayBuffer(1)))!=ze||Dn&&ts(new Dn)!=Te||On&&ts(On.resolve())!=ke||An&&ts(new An)!=Me||jn&&ts(new jn)!=Ie)&&(ts=function(e){var t=Ui(e),r=t==Oe?e.constructor:n,i=r?Is(r):``;if(i)switch(i){case Tr:return ze;case Ar:return Te;case jr:return ke;case Pr:return Me;case Fr:return Ie}return t});function ns(e,t,n){for(var r=-1,i=n.length;++r<i;){var a=n[r],o=a.size;switch(a.type){case`drop`:e+=o;break;case`dropRight`:t-=o;break;case`take`:t=vn(t,e+o);break;case`takeRight`:e=hn(e,t-o);break}}return{start:e,end:t}}function rs(e){var t=e.match(ft);return t?t[1].split(pt):[]}function is(e,t,n){t=Ja(t,e);for(var r=-1,i=t.length,a=!1;++r<i;){var o=Fs(t[r]);if(!(a=e!=null&&n(e,o)))break;e=e[o]}return a||++r!=i?a:(i=e==null?0:e.length,!!i&&Eu(i)&&us(o,i)&&(Z(e)||du(e)))}function as(e){var t=e.length,n=new e.constructor(t);return t&&typeof e[0]==`string`&&O.call(e,`index`)&&(n.index=e.index,n.input=e.input),n}function os(e){return typeof e.constructor==`function`&&!_s(e)?zr(Jt(e)):{}}function ss(e,t,n){var r=e.constructor;switch(t){case Re:return $a(e);case ye:case be:return new r(+e);case ze:return eo(e,n);case Be:case Ve:case He:case Ue:case We:case Ge:case Ke:case qe:case Je:return ro(e,n);case Te:return new r;case Ee:case Ne:return new r(e);case je:return to(e);case Me:return new r;case Pe:return no(e)}}function cs(e,t){var n=t.length;if(!n)return e;var r=n-1;return t[r]=(n>1?`& `:``)+t[r],t=t.join(n>2?`, `:` `),e.replace(dt,`{
/* [wrapped with `+t+`] */
`)}function ls(e){return Z(e)||du(e)||!!(Qt&&e&&e[Qt])}function us(e,t){var n=typeof e;return t??=le,!!t&&(n==`number`||n!=`symbol`&&Ct.test(e))&&e>-1&&e%1==0&&e<t}function ds(e,t,n){if(!Du(n))return!1;var r=typeof t;return(r==`number`?pu(n)&&us(t,n.length):r==`string`&&t in n)?cu(n[t],e):!1}function fs(e,t){if(Z(e))return!1;var n=typeof e;return n==`number`||n==`symbol`||n==`boolean`||e==null||Hu(e)?!0:ot.test(e)||!at.test(e)||t!=null&&e in D(t)}function ps(e){var t=typeof e;return t==`string`||t==`number`||t==`symbol`||t==`boolean`?e!==`__proto__`:e===null}function ms(e){var t=qo(e),n=z[t];if(typeof n!=`function`||!(t in V.prototype))return!1;if(e===n)return!0;var r=Ko(n);return!!r&&e===r[0]}function hs(e){return!!zt&&zt in e}var gs=It?wu:Xf;function _s(e){var t=e&&e.constructor;return e===(typeof t==`function`&&t.prototype||Ft)}function vs(e){return e===e&&!Du(e)}function ys(e,t){return function(r){return r==null?!1:r[e]===t&&(t!==n||e in D(r))}}function bs(e){var t=Wl(e,function(e){return n.size===u&&n.clear(),e}),n=t.cache;return t}function xs(e,t){var n=e[1],r=t[1],i=n|r,a=i<(_|v|ee),o=r==ee&&n==b||r==ee&&n==te&&e[7].length<=t[8]||r==(ee|te)&&t[7].length<=t[8]&&n==b;if(!(a||o))return e;r&_&&(e[2]=t[2],i|=n&_?0:y);var s=t[3];if(s){var c=e[3];e[3]=c?oo(c,s,t[4]):s,e[4]=c?Sr(e[3],d):t[4]}return s=t[5],s&&(c=e[5],e[5]=c?so(c,s,t[6]):s,e[6]=c?Sr(e[5],d):t[6]),s=t[7],s&&(e[7]=s),r&ee&&(e[8]=e[8]==null?t[8]:vn(e[8],t[8])),e[9]??=t[9],e[0]=t[0],e[1]=i,e}function Ss(e){var t=[];if(e!=null)for(var n in D(e))t.push(n);return t}function Cs(e){return Bt.call(e)}function ws(e,t,r){return t=hn(t===n?e.length-1:t,0),function(){for(var n=arguments,i=-1,a=hn(n.length-t,0),o=E(a);++i<a;)o[i]=n[t+i];i=-1;for(var s=E(t+1);++i<t;)s[i]=n[i];return s[t]=r(o),Ln(e,this,s)}}function Ts(e,t){return t.length<2?e:q(e,Na(t,0,-1))}function Es(e,t){for(var r=e.length,i=vn(t.length,r),a=co(e);i--;){var o=t[i];e[i]=us(o,r)?a[o]:n}return e}function Ds(e,t){if(!(t===`constructor`&&typeof e[t]==`function`)&&t!=`__proto__`)return e[t]}var Os=Ms(Aa),ks=an||function(e,t){return En.setTimeout(e,t)},As=Ms(ja);function js(e,t,n){var r=t+``;return As(e,cs(r,Ls(rs(r),n)))}function Ms(e){var t=0,r=0;return function(){var i=yn(),a=ae-(i-r);if(r=i,a>0){if(++t>=ie)return arguments[0]}else t=0;return e.apply(n,arguments)}}function Ns(e,t){var r=-1,i=e.length,a=i-1;for(t=t===n?i:t;++r<t;){var o=wa(r,a),s=e[o];e[o]=e[r],e[r]=s}return e.length=t,e}var Ps=bs(function(e){var t=[];return e.charCodeAt(0)===46&&t.push(``),e.replace(st,function(e,n,r,i){t.push(r?i.replace(gt,`$1`):n||e)}),t});function Fs(e){if(typeof e==`string`||Hu(e))return e;var t=e+``;return t==`0`&&1/e==-T?`-0`:t}function Is(e){if(e!=null){try{return Lt.call(e)}catch{}try{return e+``}catch{}}return``}function Ls(e,t){return zn(he,function(n){var r=`_.`+n[0];t&n[1]&&!Un(e,r)&&e.push(r)}),e.sort()}function Rs(e){if(e instanceof V)return e.clone();var t=new B(e.__wrapped__,e.__chain__);return t.__actions__=co(e.__actions__),t.__index__=e.__index__,t.__values__=e.__values__,t}function zs(e,t,r){t=(r?ds(e,t,r):t===n)?1:hn(Q(t),0);var i=e==null?0:e.length;if(!i||t<1)return[];for(var a=0,o=0,s=E(on(i/t));a<i;)s[o++]=Na(e,a,a+=t);return s}function Bs(e){for(var t=-1,n=e==null?0:e.length,r=0,i=[];++t<n;){var a=e[t];a&&(i[r++]=a)}return i}function Vs(){var e=arguments.length;if(!e)return[];for(var t=E(e-1),n=arguments[0],r=e;r--;)t[r-1]=arguments[r];return Gn(Z(n)?co(n):[n],Ii(t,1))}var Hs=J(function(e,t){return mu(e)?W(e,Ii(t,1,mu,!0)):[]}),Us=J(function(e,t){var r=lc(t);return mu(r)&&(r=n),mu(e)?W(e,Ii(t,1,mu,!0),X(r,2)):[]}),Ws=J(function(e,t){var r=lc(t);return mu(r)&&(r=n),mu(e)?W(e,Ii(t,1,mu,!0),n,r):[]});function Gs(e,t,r){var i=e==null?0:e.length;return i?(t=r||t===n?1:Q(t),Na(e,t<0?0:t,i)):[]}function Ks(e,t,r){var i=e==null?0:e.length;return i?(t=r||t===n?1:Q(t),t=i-t,Na(e,0,t<0?0:t)):[]}function qs(e,t){return e&&e.length?Ha(e,X(t,3),!0,!0):[]}function Js(e,t){return e&&e.length?Ha(e,X(t,3),!0):[]}function Ys(e,t,n,r){var i=e==null?0:e.length;return i?(n&&typeof n!=`number`&&ds(e,t,n)&&(n=0,r=i),Fi(e,t,n,r)):[]}function Xs(e,t,n){var r=e==null?0:e.length;if(!r)return-1;var i=n==null?0:Q(n);return i<0&&(i=hn(r+i,0)),Qn(e,X(t,3),i)}function Zs(e,t,r){var i=e==null?0:e.length;if(!i)return-1;var a=i-1;return r!==n&&(a=Q(r),a=r<0?hn(i+a,0):vn(a,i-1)),Qn(e,X(t,3),a,!0)}function Qs(e){return e!=null&&e.length?Ii(e,1):[]}function $s(e){return e!=null&&e.length?Ii(e,T):[]}function ec(e,t){return e!=null&&e.length?(t=t===n?1:Q(t),Ii(e,t)):[]}function tc(e){for(var t=-1,n=e==null?0:e.length,r={};++t<n;){var i=e[t];Ti(r,i[0],i[1])}return r}function nc(e){return e&&e.length?e[0]:n}function rc(e,t,n){var r=e==null?0:e.length;if(!r)return-1;var i=n==null?0:Q(n);return i<0&&(i=hn(r+i,0)),$n(e,t,i)}function ic(e){return e!=null&&e.length?Na(e,0,-1):[]}var ac=J(function(e){var t=M(e,Ka);return t.length&&t[0]===e[0]?Ji(t):[]}),oc=J(function(e){var t=lc(e),r=M(e,Ka);return t===lc(r)?t=n:r.pop(),r.length&&r[0]===e[0]?Ji(r,X(t,2)):[]}),sc=J(function(e){var t=lc(e),r=M(e,Ka);return t=typeof t==`function`?t:n,t&&r.pop(),r.length&&r[0]===e[0]?Ji(r,n,t):[]});function cc(e,t){return e==null?``:pn.call(e,t)}function lc(e){var t=e==null?0:e.length;return t?e[t-1]:n}function uc(e,t,r){var i=e==null?0:e.length;if(!i)return-1;var a=i;return r!==n&&(a=Q(r),a=a<0?hn(i+a,0):vn(a,i-1)),t===t?Er(e,t,a):Qn(e,tr,a,!0)}function dc(e,t){return e&&e.length?_a(e,Q(t)):n}var fc=J(pc);function pc(e,t){return e&&e.length&&t&&t.length?Sa(e,t):e}function mc(e,t,n){return e&&e.length&&t&&t.length?Sa(e,t,X(n,2)):e}function hc(e,t,r){return e&&e.length&&t&&t.length?Sa(e,t,n,r):e}var gc=Uo(function(e,t){var n=e==null?0:e.length,r=Ei(e,t);return Ca(e,M(t,function(e){return us(e,n)?+e:e}).sort(io)),r});function _c(e,t){var n=[];if(!(e&&e.length))return n;var r=-1,i=[],a=e.length;for(t=X(t,3);++r<a;){var o=e[r];t(o,r,e)&&(n.push(o),i.push(r))}return Ca(e,i),n}function vc(e){return e==null?e:wn.call(e)}function yc(e,t,r){var i=e==null?0:e.length;return i?(r&&typeof r!=`number`&&ds(e,t,r)?(t=0,r=i):(t=t==null?0:Q(t),r=r===n?i:Q(r)),Na(e,t,r)):[]}function bc(e,t){return Fa(e,t)}function xc(e,t,n){return Ia(e,t,X(n,2))}function Sc(e,t){var n=e==null?0:e.length;if(n){var r=Fa(e,t);if(r<n&&cu(e[r],t))return r}return-1}function Cc(e,t){return Fa(e,t,!0)}function wc(e,t,n){return Ia(e,t,X(n,2),!0)}function Tc(e,t){if(e!=null&&e.length){var n=Fa(e,t,!0)-1;if(cu(e[n],t))return n}return-1}function Ec(e){return e&&e.length?La(e):[]}function Dc(e,t){return e&&e.length?La(e,X(t,2)):[]}function Oc(e){var t=e==null?0:e.length;return t?Na(e,1,t):[]}function kc(e,t,r){return e&&e.length?(t=r||t===n?1:Q(t),Na(e,0,t<0?0:t)):[]}function Ac(e,t,r){var i=e==null?0:e.length;return i?(t=r||t===n?1:Q(t),t=i-t,Na(e,t<0?0:t,i)):[]}function jc(e,t){return e&&e.length?Ha(e,X(t,3),!1,!0):[]}function Mc(e,t){return e&&e.length?Ha(e,X(t,3)):[]}var Nc=J(function(e){return Y(Ii(e,1,mu,!0))}),Pc=J(function(e){var t=lc(e);return mu(t)&&(t=n),Y(Ii(e,1,mu,!0),X(t,2))}),Fc=J(function(e){var t=lc(e);return t=typeof t==`function`?t:n,Y(Ii(e,1,mu,!0),n,t)});function Ic(e){return e&&e.length?Y(e):[]}function Lc(e,t){return e&&e.length?Y(e,X(t,2)):[]}function Rc(e,t){return t=typeof t==`function`?t:n,e&&e.length?Y(e,n,t):[]}function zc(e){if(!(e&&e.length))return[];var t=0;return e=Hn(e,function(e){if(mu(e))return t=hn(e.length,t),!0}),sr(t,function(t){return M(e,rr(t))})}function Bc(e,t){if(!(e&&e.length))return[];var r=zc(e);return t==null?r:M(r,function(e){return Ln(t,n,e)})}var Vc=J(function(e,t){return mu(e)?W(e,t):[]}),Hc=J(function(e){return Wa(Hn(e,mu))}),Uc=J(function(e){var t=lc(e);return mu(t)&&(t=n),Wa(Hn(e,mu),X(t,2))}),Wc=J(function(e){var t=lc(e);return t=typeof t==`function`?t:n,Wa(Hn(e,mu),n,t)}),Gc=J(zc);function Kc(e,t){return Ga(e||[],t||[],Si)}function qc(e,t){return Ga(e||[],t||[],ka)}var Jc=J(function(e){var t=e.length,r=t>1?e[t-1]:n;return r=typeof r==`function`?(e.pop(),r):n,Bc(e,r)});function Yc(e){var t=z(e);return t.__chain__=!0,t}function Xc(e,t){return t(e),e}function Zc(e,t){return t(e)}var Qc=Uo(function(e){var t=e.length,r=t?e[0]:0,i=this.__wrapped__,a=function(t){return Ei(t,e)};return t>1||this.__actions__.length||!(i instanceof V)||!us(r)?this.thru(a):(i=i.slice(r,+r+ +!!t),i.__actions__.push({func:Zc,args:[a],thisArg:n}),new B(i,this.__chain__).thru(function(e){return t&&!e.length&&e.push(n),e}))});function $c(){return Yc(this)}function el(){return new B(this.value(),this.__chain__)}function tl(){this.__values__===n&&(this.__values__=Yu(this.value()));var e=this.__index__>=this.__values__.length;return{done:e,value:e?n:this.__values__[this.__index__++]}}function nl(){return this}function rl(e){for(var t,r=this;r instanceof Br;){var i=Rs(r);i.__index__=0,i.__values__=n,t?a.__wrapped__=i:t=i;var a=i;r=r.__wrapped__}return a.__wrapped__=e,t}function il(){var e=this.__wrapped__;if(e instanceof V){var t=e;return this.__actions__.length&&(t=new V(this)),t=t.reverse(),t.__actions__.push({func:Zc,args:[vc],thisArg:n}),new B(t,this.__chain__)}return this.thru(vc)}function al(){return Ua(this.__wrapped__,this.__actions__)}var ol=po(function(e,t,n){O.call(e,n)?++e[n]:Ti(e,n,1)});function sl(e,t,r){var i=Z(e)?Vn:Ni;return r&&ds(e,t,r)&&(t=n),i(e,X(t,3))}function cl(e,t){return(Z(e)?Hn:K)(e,X(t,3))}var ll=So(Xs),ul=So(Zs);function dl(e,t){return Ii(bl(e,t),1)}function fl(e,t){return Ii(bl(e,t),T)}function pl(e,t,r){return r=r===n?1:Q(r),Ii(bl(e,t),r)}function ml(e,t){return(Z(e)?zn:G)(e,X(t,3))}function hl(e,t){return(Z(e)?Bn:Mi)(e,X(t,3))}var gl=po(function(e,t,n){O.call(e,n)?e[n].push(t):Ti(e,n,[t])});function _l(e,t,n,r){e=pu(e)?e:Hd(e),n=n&&!r?Q(n):0;var i=e.length;return n<0&&(n=hn(i+n,0)),Vu(e)?n<=i&&e.indexOf(t,n)>-1:!!i&&$n(e,t,n)>-1}var vl=J(function(e,t,n){var r=-1,i=typeof t==`function`,a=pu(e)?E(e.length):[];return G(e,function(e){a[++r]=i?Ln(t,e,n):Xi(e,t,n)}),a}),yl=po(function(e,t,n){Ti(e,n,t)});function bl(e,t){return(Z(e)?M:fa)(e,X(t,3))}function xl(e,t,r,i){return e==null?[]:(Z(t)||(t=t==null?[]:[t]),r=i?n:r,Z(r)||(r=r==null?[]:[r]),va(e,t,r))}var Sl=po(function(e,t,n){e[+!n].push(t)},function(){return[[],[]]});function Cl(e,t,n){var r=Z(e)?Kn:P,i=arguments.length<3;return r(e,X(t,4),n,i,G)}function wl(e,t,n){var r=Z(e)?qn:P,i=arguments.length<3;return r(e,X(t,4),n,i,Mi)}function Tl(e,t){return(Z(e)?Hn:K)(e,Gl(X(t,3)))}function El(e){return(Z(e)?vi:Da)(e)}function Dl(e,t,r){return t=(r?ds(e,t,r):t===n)?1:Q(t),(Z(e)?yi:Oa)(e,t)}function Ol(e){return(Z(e)?bi:Ma)(e)}function kl(e){if(e==null)return 0;if(pu(e))return Vu(e)?Dr(e):e.length;var t=ts(e);return t==Te||t==Me?e.size:la(e).length}function Al(e,t,r){var i=Z(e)?Jn:Pa;return r&&ds(e,t,r)&&(t=n),i(e,X(t,3))}var jl=J(function(e,t){if(e==null)return[];var n=t.length;return n>1&&ds(e,t[0],t[1])?t=[]:n>2&&ds(t[0],t[1],t[2])&&(t=[t[0]]),va(e,Ii(t,1),[])}),Ml=rn||function(){return En.Date.now()};function Nl(e,t){if(typeof t!=`function`)throw new Mt(o);return e=Q(e),function(){if(--e<1)return t.apply(this,arguments)}}function Pl(e,t,r){return t=r?n:t,t=e&&t==null?e.length:t,Io(e,ee,n,n,n,n,t)}function Fl(e,t){var r;if(typeof t!=`function`)throw new Mt(o);return e=Q(e),function(){return--e>0&&(r=t.apply(this,arguments)),e<=1&&(t=n),r}}var Il=J(function(e,t,n){var r=_;if(n.length){var i=Sr(n,Jo(Il));r|=S}return Io(e,r,t,n,i)}),Ll=J(function(e,t,n){var r=_|v;if(n.length){var i=Sr(n,Jo(Ll));r|=S}return Io(t,r,e,n,i)});function Rl(e,t,r){t=r?n:t;var i=Io(e,b,n,n,n,n,n,t);return i.placeholder=Rl.placeholder,i}function zl(e,t,r){t=r?n:t;var i=Io(e,x,n,n,n,n,n,t);return i.placeholder=zl.placeholder,i}function Bl(e,t,r){var i,a,s,c,l,u,d=0,f=!1,p=!1,m=!0;if(typeof e!=`function`)throw new Mt(o);t=Qu(t)||0,Du(r)&&(f=!!r.leading,p=`maxWait`in r,s=p?hn(Qu(r.maxWait)||0,t):s,m=`trailing`in r?!!r.trailing:m);function h(t){var r=i,o=a;return i=a=n,d=t,c=e.apply(o,r),c}function g(e){return d=e,l=ks(y,t),f?h(e):c}function _(e){var n=e-u,r=e-d,i=t-n;return p?vn(i,s-r):i}function v(e){var r=e-u,i=e-d;return u===n||r>=t||r<0||p&&i>=s}function y(){var e=Ml();if(v(e))return b(e);l=ks(y,_(e))}function b(e){return l=n,m&&i?h(e):(i=a=n,c)}function x(){l!==n&&Za(l),d=0,i=u=a=l=n}function S(){return l===n?c:b(Ml())}function C(){var e=Ml(),r=v(e);if(i=arguments,a=this,u=e,r){if(l===n)return g(u);if(p)return Za(l),l=ks(y,t),h(u)}return l===n&&(l=ks(y,t)),c}return C.cancel=x,C.flush=S,C}var Vl=J(function(e,t){return ji(e,1,t)}),Hl=J(function(e,t,n){return ji(e,Qu(t)||0,n)});function Ul(e){return Io(e,w)}function Wl(e,t){if(typeof e!=`function`||t!=null&&typeof t!=`function`)throw new Mt(o);var n=function(){var r=arguments,i=t?t.apply(this,r):r[0],a=n.cache;if(a.has(i))return a.get(i);var o=e.apply(this,r);return n.cache=a.set(i,o)||a,o};return n.cache=new(Wl.Cache||ni),n}Wl.Cache=ni;function Gl(e){if(typeof e!=`function`)throw new Mt(o);return function(){var t=arguments;switch(t.length){case 0:return!e.call(this);case 1:return!e.call(this,t[0]);case 2:return!e.call(this,t[0],t[1]);case 3:return!e.call(this,t[0],t[1],t[2])}return!e.apply(this,t)}}function Kl(e){return Fl(2,e)}var ql=Ya(function(e,t){t=t.length==1&&Z(t[0])?M(t[0],ur(X())):M(Ii(t,1),ur(X()));var n=t.length;return J(function(r){for(var i=-1,a=vn(r.length,n);++i<a;)r[i]=t[i].call(this,r[i]);return Ln(e,this,r)})}),Jl=J(function(e,t){return Io(e,S,n,t,Sr(t,Jo(Jl)))}),Yl=J(function(e,t){return Io(e,C,n,t,Sr(t,Jo(Yl)))}),Xl=Uo(function(e,t){return Io(e,te,n,n,n,t)});function Zl(e,t){if(typeof e!=`function`)throw new Mt(o);return t=t===n?t:Q(t),J(e,t)}function Ql(e,t){if(typeof e!=`function`)throw new Mt(o);return t=t==null?0:hn(Q(t),0),J(function(n){var r=n[t],i=Xa(n,0,t);return r&&Gn(i,r),Ln(e,this,i)})}function $l(e,t,n){var r=!0,i=!0;if(typeof e!=`function`)throw new Mt(o);return Du(n)&&(r=`leading`in n?!!n.leading:r,i=`trailing`in n?!!n.trailing:i),Bl(e,t,{leading:r,maxWait:t,trailing:i})}function eu(e){return Pl(e,1)}function tu(e,t){return Jl(qa(t),e)}function nu(){if(!arguments.length)return[];var e=arguments[0];return Z(e)?e:[e]}function ru(e){return Oi(e,m)}function iu(e,t){return t=typeof t==`function`?t:n,Oi(e,m,t)}function au(e){return Oi(e,f|m)}function ou(e,t){return t=typeof t==`function`?t:n,Oi(e,f|m,t)}function su(e,t){return t==null||Ai(e,t,Cd(t))}function cu(e,t){return e===t||e!==e&&t!==t}var lu=jo(Wi),uu=jo(function(e,t){return e>=t}),du=Zi(function(){return arguments}())?Zi:function(e){return Ou(e)&&O.call(e,`callee`)&&!Xt.call(e,`callee`)},Z=E.isArray,fu=Mn?ur(Mn):Qi;function pu(e){return e!=null&&Eu(e.length)&&!wu(e)}function mu(e){return Ou(e)&&pu(e)}function hu(e){return e===!0||e===!1||Ou(e)&&Ui(e)==ye}var gu=ln||Xf,_u=j?ur(j):$i;function vu(e){return Ou(e)&&e.nodeType===1&&!Lu(e)}function yu(e){if(e==null)return!0;if(pu(e)&&(Z(e)||typeof e==`string`||typeof e.splice==`function`||gu(e)||Uu(e)||du(e)))return!e.length;var t=ts(e);if(t==Te||t==Me)return!e.size;if(_s(e))return!la(e).length;for(var n in e)if(O.call(e,n))return!1;return!0}function bu(e,t){return ea(e,t)}function xu(e,t,r){r=typeof r==`function`?r:n;var i=r?r(e,t):n;return i===n?ea(e,t,n,r):!!i}function Su(e){if(!Ou(e))return!1;var t=Ui(e);return t==Se||t==xe||typeof e.message==`string`&&typeof e.name==`string`&&!Lu(e)}function Cu(e){return typeof e==`number`&&fn(e)}function wu(e){if(!Du(e))return!1;var t=Ui(e);return t==Ce||t==we||t==ve||t==Ae}function Tu(e){return typeof e==`number`&&e==Q(e)}function Eu(e){return typeof e==`number`&&e>-1&&e%1==0&&e<=le}function Du(e){var t=typeof e;return e!=null&&(t==`object`||t==`function`)}function Ou(e){return typeof e==`object`&&!!e}var ku=Nn?ur(Nn):na;function Au(e,t){return e===t||ra(e,t,Xo(t))}function ju(e,t,r){return r=typeof r==`function`?r:n,ra(e,t,Xo(t),r)}function Mu(e){return Iu(e)&&e!=+e}function Nu(e){if(gs(e))throw new Dt(a);return ia(e)}function Pu(e){return e===null}function Fu(e){return e==null}function Iu(e){return typeof e==`number`||Ou(e)&&Ui(e)==Ee}function Lu(e){if(!Ou(e)||Ui(e)!=Oe)return!1;var t=Jt(e);if(t===null)return!0;var n=O.call(t,`constructor`)&&t.constructor;return typeof n==`function`&&n instanceof n&&Lt.call(n)==Vt}var Ru=Pn?ur(Pn):aa;function zu(e){return Tu(e)&&e>=-le&&e<=le}var Bu=Fn?ur(Fn):oa;function Vu(e){return typeof e==`string`||!Z(e)&&Ou(e)&&Ui(e)==Ne}function Hu(e){return typeof e==`symbol`||Ou(e)&&Ui(e)==Pe}var Uu=In?ur(In):sa;function Wu(e){return e===n}function Gu(e){return Ou(e)&&ts(e)==Ie}function Ku(e){return Ou(e)&&Ui(e)==Le}var qu=jo(da),Ju=jo(function(e,t){return e<=t});function Yu(e){if(!e)return[];if(pu(e))return Vu(e)?R(e):co(e);if($t&&e[$t])return br(e[$t]());var t=ts(e);return(t==Te?L:t==Me?Cr:Hd)(e)}function Xu(e){return e?(e=Qu(e),e===T||e===-T?(e<0?-1:1)*ue:e===e?e:0):e===0?e:0}function Q(e){var t=Xu(e),n=t%1;return t===t?n?t-n:t:0}function Zu(e){return e?Di(Q(e),0,fe):0}function Qu(e){if(typeof e==`number`)return e;if(Hu(e))return de;if(Du(e)){var t=typeof e.valueOf==`function`?e.valueOf():e;e=Du(t)?t+``:t}if(typeof e!=`string`)return e===0?e:+e;e=lr(e);var n=bt.test(e);return n||St.test(e)?Cn(e.slice(2),n?2:8):yt.test(e)?de:+e}function $u(e){return lo(e,wd(e))}function ed(e){return e?Di(Q(e),-le,le):e===0?e:0}function $(e){return e==null?``:za(e)}var td=mo(function(e,t){if(_s(t)||pu(t)){lo(t,Cd(t),e);return}for(var n in t)O.call(t,n)&&Si(e,n,t[n])}),nd=mo(function(e,t){lo(t,wd(t),e)}),rd=mo(function(e,t,n,r){lo(t,wd(t),e,r)}),id=mo(function(e,t,n,r){lo(t,Cd(t),e,r)}),ad=Uo(Ei);function od(e,t){var n=zr(e);return t==null?n:wi(n,t)}var sd=J(function(e,t){e=D(e);var r=-1,i=t.length,a=i>2?t[2]:n;for(a&&ds(t[0],t[1],a)&&(i=1);++r<i;)for(var o=t[r],s=wd(o),c=-1,l=s.length;++c<l;){var u=s[c],d=e[u];(d===n||cu(d,Ft[u])&&!O.call(e,u))&&(e[u]=o[u])}return e}),cd=J(function(e){return e.push(n,Ro),Ln(Od,n,e)});function ld(e,t){return Zn(e,X(t,3),zi)}function ud(e,t){return Zn(e,X(t,3),Bi)}function dd(e,t){return e==null?e:Li(e,X(t,3),wd)}function fd(e,t){return e==null?e:Ri(e,X(t,3),wd)}function pd(e,t){return e&&zi(e,X(t,3))}function md(e,t){return e&&Bi(e,X(t,3))}function hd(e){return e==null?[]:Vi(e,Cd(e))}function gd(e){return e==null?[]:Vi(e,wd(e))}function _d(e,t,r){var i=e==null?n:q(e,t);return i===n?r:i}function vd(e,t){return e!=null&&is(e,t,Gi)}function yd(e,t){return e!=null&&is(e,t,Ki)}var bd=To(function(e,t,n){t!=null&&typeof t.toString!=`function`&&(t=Bt.call(t)),e[t]=n},Of(Mf)),xd=To(function(e,t,n){t!=null&&typeof t.toString!=`function`&&(t=Bt.call(t)),O.call(e,t)?e[t].push(n):e[t]=[n]},X),Sd=J(Xi);function Cd(e){return pu(e)?_i(e):la(e)}function wd(e){return pu(e)?_i(e,!0):ua(e)}function Td(e,t){var n={};return t=X(t,3),zi(e,function(e,r,i){Ti(n,t(e,r,i),e)}),n}function Ed(e,t){var n={};return t=X(t,3),zi(e,function(e,r,i){Ti(n,r,t(e,r,i))}),n}var Dd=mo(function(e,t,n){ha(e,t,n)}),Od=mo(function(e,t,n,r){ha(e,t,n,r)}),kd=Uo(function(e,t){var n={};if(e==null)return n;var r=!1;t=M(t,function(t){return t=Ja(t,e),r||=t.length>1,t}),lo(e,Go(e),n),r&&(n=Oi(n,f|p|m,zo));for(var i=t.length;i--;)Ba(n,t[i]);return n});function Ad(e,t){return Md(e,Gl(X(t)))}var jd=Uo(function(e,t){return e==null?{}:ya(e,t)});function Md(e,t){if(e==null)return{};var n=M(Go(e),function(e){return[e]});return t=X(t),ba(e,n,function(e,n){return t(e,n[0])})}function Nd(e,t,r){t=Ja(t,e);var i=-1,a=t.length;for(a||(a=1,e=n);++i<a;){var o=e==null?n:e[Fs(t[i])];o===n&&(i=a,o=r),e=wu(o)?o.call(e):o}return e}function Pd(e,t,n){return e==null?e:ka(e,t,n)}function Fd(e,t,r,i){return i=typeof i==`function`?i:n,e==null?e:ka(e,t,r,i)}var Id=Fo(Cd),Ld=Fo(wd);function Rd(e,t,n){var r=Z(e),i=r||gu(e)||Uu(e);if(t=X(t,4),n==null){var a=e&&e.constructor;n=i?r?new a:[]:Du(e)&&wu(a)?zr(Jt(e)):{}}return(i?zn:zi)(e,function(e,r,i){return t(n,e,r,i)}),n}function zd(e,t){return e==null?!0:Ba(e,t)}function Bd(e,t,n){return e==null?e:Va(e,t,qa(n))}function Vd(e,t,r,i){return i=typeof i==`function`?i:n,e==null?e:Va(e,t,qa(r),i)}function Hd(e){return e==null?[]:dr(e,Cd(e))}function Ud(e){return e==null?[]:dr(e,wd(e))}function Wd(e,t,r){return r===n&&(r=t,t=n),r!==n&&(r=Qu(r),r=r===r?r:0),t!==n&&(t=Qu(t),t=t===t?t:0),Di(Qu(e),t,r)}function Gd(e,t,r){return t=Xu(t),r===n?(r=t,t=0):r=Xu(r),e=Qu(e),qi(e,t,r)}function Kd(e,t,r){if(r&&typeof r!=`boolean`&&ds(e,t,r)&&(t=r=n),r===n&&(typeof t==`boolean`?(r=t,t=n):typeof e==`boolean`&&(r=e,e=n)),e===n&&t===n?(e=0,t=1):(e=Xu(e),t===n?(t=e,e=0):t=Xu(t)),e>t){var i=e;e=t,t=i}if(r||e%1||t%1){var a=xn();return vn(e+a*(t-e+Sn(`1e-`+((a+``).length-1))),t)}return wa(e,t)}var qd=yo(function(e,t,n){return t=t.toLowerCase(),e+(n?Jd(t):t)});function Jd(e){return Sf($(e).toLowerCase())}function Yd(e){return e=$(e),e&&e.replace(wt,mr).replace(dn,``)}function Xd(e,t,r){e=$(e),t=za(t);var i=e.length;r=r===n?i:Di(Q(r),0,i);var a=r;return r-=t.length,r>=0&&e.slice(r,a)==t}function Zd(e){return e=$(e),e&&tt.test(e)?e.replace($e,hr):e}function Qd(e){return e=$(e),e&&lt.test(e)?e.replace(ct,`\\$&`):e}var $d=yo(function(e,t,n){return e+(n?`-`:``)+t.toLowerCase()}),ef=yo(function(e,t,n){return e+(n?` `:``)+t.toLowerCase()}),tf=vo(`toLowerCase`);function nf(e,t,n){e=$(e),t=Q(t);var r=t?Dr(e):0;if(!t||r>=t)return e;var i=(t-r)/2;return Oo(sn(i),n)+e+Oo(on(i),n)}function rf(e,t,n){e=$(e),t=Q(t);var r=t?Dr(e):0;return t&&r<t?e+Oo(t-r,n):e}function af(e,t,n){e=$(e),t=Q(t);var r=t?Dr(e):0;return t&&r<t?Oo(t-r,n)+e:e}function of(e,t,n){return n||t==null?t=0:t&&=+t,bn($(e).replace(ut,``),t||0)}function sf(e,t,r){return t=(r?ds(e,t,r):t===n)?1:Q(t),Ea($(e),t)}function cf(){var e=arguments,t=$(e[0]);return e.length<3?t:t.replace(e[1],e[2])}var lf=yo(function(e,t,n){return e+(n?`_`:``)+t.toLowerCase()});function uf(e,t,r){return r&&typeof r!=`number`&&ds(e,t,r)&&(t=r=n),r=r===n?fe:r>>>0,r?(e=$(e),e&&(typeof t==`string`||t!=null&&!Ru(t))&&(t=za(t),!t&&vr(e))?Xa(R(e),0,r):e.split(t,r)):[]}var df=yo(function(e,t,n){return e+(n?` `:``)+Sf(t)});function ff(e,t,n){return e=$(e),n=n==null?0:Di(Q(n),0,e.length),t=za(t),e.slice(n,n+t.length)==t}function pf(e,t,r){var i=z.templateSettings;r&&ds(e,t,r)&&(t=n),e=$(e),t=id({},t,i,Lo);var a=id({},t.imports,i.imports,Lo),o=Cd(a),l=dr(a,o);zn(o,function(e){if(ht.test(e))throw new Dt(c)});var u,d,f=0,p=t.interpolate||Tt,m=`__p += '`,h=At((t.escape||Tt).source+`|`+p.source+`|`+(p===it?_t:Tt).source+`|`+(t.evaluate||Tt).source+`|$`,`g`),g=`//# sourceURL=`+(O.call(t,`sourceURL`)?(t.sourceURL+``).replace(/\s/g,` `):`lodash.templateSources[`+ ++_n+`]`)+`
`;e.replace(h,function(t,n,r,i,a,o){return r||=i,m+=e.slice(f,o).replace(Et,gr),n&&(u=!0,m+=`' +
__e(`+n+`) +
'`),a&&(d=!0,m+=`';
`+a+`;
__p += '`),r&&(m+=`' +
((__t = (`+r+`)) == null ? '' : __t) +
'`),f=o+t.length,t}),m+=`';
`;var _=O.call(t,`variable`)&&t.variable;if(!_)m=`with (obj) {
`+m+`
}
`;else if(ht.test(_))throw new Dt(s);m=(d?m.replace(Ye,``):m).replace(Xe,`$1`).replace(Ze,`$1;`),m=`function(`+(_||`obj`)+`) {
`+(_?``:`obj || (obj = {});
`)+`var __t, __p = ''`+(u?`, __e = _.escape`:``)+(d?`, __j = Array.prototype.join;
function print() { __p += __j.call(arguments, '') }
`:`;
`)+m+`return __p
}`;var v=wf(function(){return Ot(o,g+`return `+m).apply(n,l)});if(v.source=m,Su(v))throw v;return v}function mf(e){return $(e).toLowerCase()}function hf(e){return $(e).toUpperCase()}function gf(e,t,r){if(e=$(e),e&&(r||t===n))return lr(e);if(!e||!(t=za(t)))return e;var i=R(e),a=R(t);return Xa(i,F(i,a),I(i,a)+1).join(``)}function _f(e,t,r){if(e=$(e),e&&(r||t===n))return e.slice(0,Or(e)+1);if(!e||!(t=za(t)))return e;var i=R(e);return Xa(i,0,I(i,R(t))+1).join(``)}function vf(e,t,r){if(e=$(e),e&&(r||t===n))return e.replace(ut,``);if(!e||!(t=za(t)))return e;var i=R(e);return Xa(i,F(i,R(t))).join(``)}function yf(e,t){var r=ne,i=re;if(Du(t)){var a=`separator`in t?t.separator:a;r=`length`in t?Q(t.length):r,i=`omission`in t?za(t.omission):i}e=$(e);var o=e.length;if(vr(e)){var s=R(e);o=s.length}if(r>=o)return e;var c=r-Dr(i);if(c<1)return i;var l=s?Xa(s,0,c).join(``):e.slice(0,c);if(a===n)return l+i;if(s&&(c+=l.length-c),Ru(a)){if(e.slice(c).search(a)){var u,d=l;for(a.global||(a=At(a.source,$(vt.exec(a))+`g`)),a.lastIndex=0;u=a.exec(d);)var f=u.index;l=l.slice(0,f===n?c:f)}}else if(e.indexOf(za(a),c)!=c){var p=l.lastIndexOf(a);p>-1&&(l=l.slice(0,p))}return l+i}function bf(e){return e=$(e),e&&et.test(e)?e.replace(Qe,kr):e}var xf=yo(function(e,t,n){return e+(n?` `:``)+t.toUpperCase()}),Sf=vo(`toUpperCase`);function Cf(e,t,r){return e=$(e),t=r?n:t,t===n?yr(e)?Mr(e):N(e):e.match(t)||[]}var wf=J(function(e,t){try{return Ln(e,n,t)}catch(e){return Su(e)?e:new Dt(e)}}),Tf=Uo(function(e,t){return zn(t,function(t){t=Fs(t),Ti(e,t,Il(e[t],e))}),e});function Ef(e){var t=e==null?0:e.length,n=X();return e=t?M(e,function(e){if(typeof e[1]!=`function`)throw new Mt(o);return[n(e[0]),e[1]]}):[],J(function(n){for(var r=-1;++r<t;){var i=e[r];if(Ln(i[0],this,n))return Ln(i[1],this,n)}})}function Df(e){return ki(Oi(e,f))}function Of(e){return function(){return e}}function kf(e,t){return e==null||e!==e?t:e}var Af=Co(),jf=Co(!0);function Mf(e){return e}function Nf(e){return ca(typeof e==`function`?e:Oi(e,f))}function Pf(e){return pa(Oi(e,f))}function Ff(e,t){return ma(e,Oi(t,f))}var If=J(function(e,t){return function(n){return Xi(n,e,t)}}),Lf=J(function(e,t){return function(n){return Xi(e,n,t)}});function Rf(e,t,n){var r=Cd(t),i=Vi(t,r);n==null&&!(Du(t)&&(i.length||!r.length))&&(n=t,t=e,e=this,i=Vi(t,Cd(t)));var a=!(Du(n)&&`chain`in n)||!!n.chain,o=wu(e);return zn(i,function(n){var r=t[n];e[n]=r,o&&(e.prototype[n]=function(){var t=this.__chain__;if(a||t){var n=e(this.__wrapped__);return(n.__actions__=co(this.__actions__)).push({func:r,args:arguments,thisArg:e}),n.__chain__=t,n}return r.apply(e,Gn([this.value()],arguments))})}),e}function zf(){return En._===this&&(En._=Ht),this}function Bf(){}function Vf(e){return e=Q(e),J(function(t){return _a(t,e)})}var Hf=Do(M),Uf=Do(Vn),Wf=Do(Jn);function Gf(e){return fs(e)?rr(Fs(e)):xa(e)}function Kf(e){return function(t){return e==null?n:q(e,t)}}var qf=Ao(),Jf=Ao(!0);function Yf(){return[]}function Xf(){return!1}function Zf(){return{}}function Qf(){return``}function $f(){return!0}function ep(e,t){if(e=Q(e),e<1||e>le)return[];var n=fe,r=vn(e,fe);t=X(t),e-=fe;for(var i=sr(r,t);++n<e;)t(n);return i}function tp(e){return Z(e)?M(e,Fs):Hu(e)?[e]:co(Ps($(e)))}function np(e){var t=++Rt;return $(e)+t}var rp=Eo(function(e,t){return e+t},0),ip=No(`ceil`),ap=Eo(function(e,t){return e/t},1),op=No(`floor`);function sp(e){return e&&e.length?Pi(e,Mf,Wi):n}function cp(e,t){return e&&e.length?Pi(e,X(t,2),Wi):n}function lp(e){return nr(e,Mf)}function up(e,t){return nr(e,X(t,2))}function dp(e){return e&&e.length?Pi(e,Mf,da):n}function fp(e,t){return e&&e.length?Pi(e,X(t,2),da):n}var pp=Eo(function(e,t){return e*t},1),mp=No(`round`),hp=Eo(function(e,t){return e-t},0);function gp(e){return e&&e.length?or(e,Mf):0}function _p(e,t){return e&&e.length?or(e,X(t,2)):0}return z.after=Nl,z.ary=Pl,z.assign=td,z.assignIn=nd,z.assignInWith=rd,z.assignWith=id,z.at=ad,z.before=Fl,z.bind=Il,z.bindAll=Tf,z.bindKey=Ll,z.castArray=nu,z.chain=Yc,z.chunk=zs,z.compact=Bs,z.concat=Vs,z.cond=Ef,z.conforms=Df,z.constant=Of,z.countBy=ol,z.create=od,z.curry=Rl,z.curryRight=zl,z.debounce=Bl,z.defaults=sd,z.defaultsDeep=cd,z.defer=Vl,z.delay=Hl,z.difference=Hs,z.differenceBy=Us,z.differenceWith=Ws,z.drop=Gs,z.dropRight=Ks,z.dropRightWhile=qs,z.dropWhile=Js,z.fill=Ys,z.filter=cl,z.flatMap=dl,z.flatMapDeep=fl,z.flatMapDepth=pl,z.flatten=Qs,z.flattenDeep=$s,z.flattenDepth=ec,z.flip=Ul,z.flow=Af,z.flowRight=jf,z.fromPairs=tc,z.functions=hd,z.functionsIn=gd,z.groupBy=gl,z.initial=ic,z.intersection=ac,z.intersectionBy=oc,z.intersectionWith=sc,z.invert=bd,z.invertBy=xd,z.invokeMap=vl,z.iteratee=Nf,z.keyBy=yl,z.keys=Cd,z.keysIn=wd,z.map=bl,z.mapKeys=Td,z.mapValues=Ed,z.matches=Pf,z.matchesProperty=Ff,z.memoize=Wl,z.merge=Dd,z.mergeWith=Od,z.method=If,z.methodOf=Lf,z.mixin=Rf,z.negate=Gl,z.nthArg=Vf,z.omit=kd,z.omitBy=Ad,z.once=Kl,z.orderBy=xl,z.over=Hf,z.overArgs=ql,z.overEvery=Uf,z.overSome=Wf,z.partial=Jl,z.partialRight=Yl,z.partition=Sl,z.pick=jd,z.pickBy=Md,z.property=Gf,z.propertyOf=Kf,z.pull=fc,z.pullAll=pc,z.pullAllBy=mc,z.pullAllWith=hc,z.pullAt=gc,z.range=qf,z.rangeRight=Jf,z.rearg=Xl,z.reject=Tl,z.remove=_c,z.rest=Zl,z.reverse=vc,z.sampleSize=Dl,z.set=Pd,z.setWith=Fd,z.shuffle=Ol,z.slice=yc,z.sortBy=jl,z.sortedUniq=Ec,z.sortedUniqBy=Dc,z.split=uf,z.spread=Ql,z.tail=Oc,z.take=kc,z.takeRight=Ac,z.takeRightWhile=jc,z.takeWhile=Mc,z.tap=Xc,z.throttle=$l,z.thru=Zc,z.toArray=Yu,z.toPairs=Id,z.toPairsIn=Ld,z.toPath=tp,z.toPlainObject=$u,z.transform=Rd,z.unary=eu,z.union=Nc,z.unionBy=Pc,z.unionWith=Fc,z.uniq=Ic,z.uniqBy=Lc,z.uniqWith=Rc,z.unset=zd,z.unzip=zc,z.unzipWith=Bc,z.update=Bd,z.updateWith=Vd,z.values=Hd,z.valuesIn=Ud,z.without=Vc,z.words=Cf,z.wrap=tu,z.xor=Hc,z.xorBy=Uc,z.xorWith=Wc,z.zip=Gc,z.zipObject=Kc,z.zipObjectDeep=qc,z.zipWith=Jc,z.entries=Id,z.entriesIn=Ld,z.extend=nd,z.extendWith=rd,Rf(z,z),z.add=rp,z.attempt=wf,z.camelCase=qd,z.capitalize=Jd,z.ceil=ip,z.clamp=Wd,z.clone=ru,z.cloneDeep=au,z.cloneDeepWith=ou,z.cloneWith=iu,z.conformsTo=su,z.deburr=Yd,z.defaultTo=kf,z.divide=ap,z.endsWith=Xd,z.eq=cu,z.escape=Zd,z.escapeRegExp=Qd,z.every=sl,z.find=ll,z.findIndex=Xs,z.findKey=ld,z.findLast=ul,z.findLastIndex=Zs,z.findLastKey=ud,z.floor=op,z.forEach=ml,z.forEachRight=hl,z.forIn=dd,z.forInRight=fd,z.forOwn=pd,z.forOwnRight=md,z.get=_d,z.gt=lu,z.gte=uu,z.has=vd,z.hasIn=yd,z.head=nc,z.identity=Mf,z.includes=_l,z.indexOf=rc,z.inRange=Gd,z.invoke=Sd,z.isArguments=du,z.isArray=Z,z.isArrayBuffer=fu,z.isArrayLike=pu,z.isArrayLikeObject=mu,z.isBoolean=hu,z.isBuffer=gu,z.isDate=_u,z.isElement=vu,z.isEmpty=yu,z.isEqual=bu,z.isEqualWith=xu,z.isError=Su,z.isFinite=Cu,z.isFunction=wu,z.isInteger=Tu,z.isLength=Eu,z.isMap=ku,z.isMatch=Au,z.isMatchWith=ju,z.isNaN=Mu,z.isNative=Nu,z.isNil=Fu,z.isNull=Pu,z.isNumber=Iu,z.isObject=Du,z.isObjectLike=Ou,z.isPlainObject=Lu,z.isRegExp=Ru,z.isSafeInteger=zu,z.isSet=Bu,z.isString=Vu,z.isSymbol=Hu,z.isTypedArray=Uu,z.isUndefined=Wu,z.isWeakMap=Gu,z.isWeakSet=Ku,z.join=cc,z.kebabCase=$d,z.last=lc,z.lastIndexOf=uc,z.lowerCase=ef,z.lowerFirst=tf,z.lt=qu,z.lte=Ju,z.max=sp,z.maxBy=cp,z.mean=lp,z.meanBy=up,z.min=dp,z.minBy=fp,z.stubArray=Yf,z.stubFalse=Xf,z.stubObject=Zf,z.stubString=Qf,z.stubTrue=$f,z.multiply=pp,z.nth=dc,z.noConflict=zf,z.noop=Bf,z.now=Ml,z.pad=nf,z.padEnd=rf,z.padStart=af,z.parseInt=of,z.random=Kd,z.reduce=Cl,z.reduceRight=wl,z.repeat=sf,z.replace=cf,z.result=Nd,z.round=mp,z.runInContext=e,z.sample=El,z.size=kl,z.snakeCase=lf,z.some=Al,z.sortedIndex=bc,z.sortedIndexBy=xc,z.sortedIndexOf=Sc,z.sortedLastIndex=Cc,z.sortedLastIndexBy=wc,z.sortedLastIndexOf=Tc,z.startCase=df,z.startsWith=ff,z.subtract=hp,z.sum=gp,z.sumBy=_p,z.template=pf,z.times=ep,z.toFinite=Xu,z.toInteger=Q,z.toLength=Zu,z.toLower=mf,z.toNumber=Qu,z.toSafeInteger=ed,z.toString=$,z.toUpper=hf,z.trim=gf,z.trimEnd=_f,z.trimStart=vf,z.truncate=yf,z.unescape=bf,z.uniqueId=np,z.upperCase=xf,z.upperFirst=Sf,z.each=ml,z.eachRight=hl,z.first=nc,Rf(z,function(){var e={};return zi(z,function(t,n){O.call(z.prototype,n)||(e[n]=t)}),e}(),{chain:!1}),z.VERSION=r,zn([`bind`,`bindKey`,`curry`,`curryRight`,`partial`,`partialRight`],function(e){z[e].placeholder=z}),zn([`drop`,`take`],function(e,t){V.prototype[e]=function(r){r=r===n?1:hn(Q(r),0);var i=this.__filtered__&&!t?new V(this):this.clone();return i.__filtered__?i.__takeCount__=vn(r,i.__takeCount__):i.__views__.push({size:vn(r,fe),type:e+(i.__dir__<0?`Right`:``)}),i},V.prototype[e+`Right`]=function(t){return this.reverse()[e](t).reverse()}}),zn([`filter`,`map`,`takeWhile`],function(e,t){var n=t+1,r=n==oe||n==ce;V.prototype[e]=function(e){var t=this.clone();return t.__iteratees__.push({iteratee:X(e,3),type:n}),t.__filtered__=t.__filtered__||r,t}}),zn([`head`,`last`],function(e,t){var n=`take`+(t?`Right`:``);V.prototype[e]=function(){return this[n](1).value()[0]}}),zn([`initial`,`tail`],function(e,t){var n=`drop`+(t?``:`Right`);V.prototype[e]=function(){return this.__filtered__?new V(this):this[n](1)}}),V.prototype.compact=function(){return this.filter(Mf)},V.prototype.find=function(e){return this.filter(e).head()},V.prototype.findLast=function(e){return this.reverse().find(e)},V.prototype.invokeMap=J(function(e,t){return typeof e==`function`?new V(this):this.map(function(n){return Xi(n,e,t)})}),V.prototype.reject=function(e){return this.filter(Gl(X(e)))},V.prototype.slice=function(e,t){e=Q(e);var r=this;return r.__filtered__&&(e>0||t<0)?new V(r):(e<0?r=r.takeRight(-e):e&&(r=r.drop(e)),t!==n&&(t=Q(t),r=t<0?r.dropRight(-t):r.take(t-e)),r)},V.prototype.takeRightWhile=function(e){return this.reverse().takeWhile(e).reverse()},V.prototype.toArray=function(){return this.take(fe)},zi(V.prototype,function(e,t){var r=/^(?:filter|find|map|reject)|While$/.test(t),i=/^(?:head|last)$/.test(t),a=z[i?`take`+(t==`last`?`Right`:``):t],o=i||/^find/.test(t);a&&(z.prototype[t]=function(){var t=this.__wrapped__,s=i?[1]:arguments,c=t instanceof V,l=s[0],u=c||Z(t),d=function(e){var t=a.apply(z,Gn([e],s));return i&&f?t[0]:t};u&&r&&typeof l==`function`&&l.length!=1&&(c=u=!1);var f=this.__chain__,p=!!this.__actions__.length,m=o&&!f,h=c&&!p;if(!o&&u){t=h?t:new V(this);var g=e.apply(t,s);return g.__actions__.push({func:Zc,args:[d],thisArg:n}),new B(g,f)}return m&&h?e.apply(this,s):(g=this.thru(d),m?i?g.value()[0]:g.value():g)})}),zn([`pop`,`push`,`shift`,`sort`,`splice`,`unshift`],function(e){var t=Nt[e],n=/^(?:push|sort|unshift)$/.test(e)?`tap`:`thru`,r=/^(?:pop|shift)$/.test(e);z.prototype[e]=function(){var e=arguments;if(r&&!this.__chain__){var i=this.value();return t.apply(Z(i)?i:[],e)}return this[n](function(n){return t.apply(Z(n)?n:[],e)})}}),zi(V.prototype,function(e,t){var n=z[t];if(n){var r=n.name+``;O.call(ir,r)||(ir[r]=[]),ir[r].push({name:t,func:n})}}),ir[wo(n,v).name]=[{name:`wrapper`,func:n}],V.prototype.clone=Vr,V.prototype.reverse=Hr,V.prototype.value=Ur,z.prototype.at=Qc,z.prototype.chain=$c,z.prototype.commit=el,z.prototype.next=tl,z.prototype.plant=rl,z.prototype.reverse=il,z.prototype.toJSON=z.prototype.valueOf=z.prototype.value=al,z.prototype.first=z.prototype.head,$t&&(z.prototype[$t]=nl),z})();typeof define==`function`&&typeof define.amd==`object`&&define.amd?(En._=Nr,define(function(){return Nr})):On?((On.exports=Nr)._=Nr,Dn._=Nr):En._=Nr}).call(e)})),Ys=te(Js(),1),Xs=`
"""
Requêtes publiques de l'API GraphQL de VFF : lecture seule, anonyme, limitée à 300 requêtes
par minute et par adresse IP. Point d'accès : \`POST /graphql\`.

Données ouvertes : citez « VFF - Voies Ferrées de France » (https://vff-project.org) et
respectez les licences des sources (SNCF Open Data et OpenStreetMap : ODbL ; IGN BD TOPO :
Licence Ouverte 2.0 ; OpenHistoricalMap et Wikidata : CC0 ; Wikipédia : CC BY-SA).
"""
type Query {
  "Toutes les ères disponibles, de 1830 à 2030 (ordre non garanti : triez sur \`year\`)."
  eras: [Era!]!
  "Une ère, par son année."
  era(
    "Année de l'ère : une décennie de 1830 à 2030."
    year: Int!
  ): Era
  """
  Recherche par nom, comme \`GET /api/v1/search\` et l'outil MCP \`search\` : lignes (nom ou code à 6
  chiffres), gares, matériels roulants et accidents (par nom dans l'une des trois langues, commune ou
  date : \`1891\`, \`07/2013\`, \`2013-07-12\`), triés par pertinence (nom exact, début du nom, mot du nom,
  reste), sans tenir compte de la casse ni des accents. Seuls les accidents publiés sont renvoyés.
  Moins de 2 caractères : listes vides.
  """
  search(
    "Texte cherché, 2 caractères au moins."
    query: String!
    "Résultats par catégorie, de 1 à 20 (8 par défaut)."
    limit: Int = 8
  ): SearchResults!
  """
  Chiffres d'ensemble du site : lignes, gares, matériels, ères cartographiées, kilomètres exploités
  aujourd'hui et profil du réseau à chaque ère. Mêmes valeurs que la page d'accueil et que l'API
  REST \`GET /api/v1/stats\`, mêmes noms de champs.
  """
  stats: SiteStats!
  """
  Ce qui a changé sur le réseau entre deux ères de la carte : kilomètres et nombre de lignes
  ouvertes, fermées, déposées ou restées en service. Même résumé que l'API REST
  \`GET /api/v1/eras/{year}/compare/{other}\`, mêmes noms de champs. Les deux années peuvent venir
  dans n'importe quel ordre. Erreur de requête si une année n'est pas une décennie de la carte
  ou si les deux années sont identiques.
  """
  eraComparison(
    "Première année comparée : une décennie de 1830 à 2030 (ex. \`1930\`)."
    from: Int!
    "Seconde année comparée : une autre décennie de 1830 à 2030 (ex. \`1980\`)."
    to: Int!
  ): EraComparison!
  "Une ligne, par son slug (ex. \`rfn-420000\`)."
  line(
    "Slug de la ligne : \`rfn-\` suivi du code à 6 chiffres pour le réseau ferré national."
    slug: String!
  ): Line
  "Une gare, par son slug (ex. \`rennes\`)."
  station(
    "Slug de la gare."
    slug: String!
  ): Station
  "Une série de matériel roulant, par son slug (ex. \`bb-15000\`)."
  material(
    "Slug de la série."
    slug: String!
  ): Material
  "Un accident, par son slug (ex. \`2013-07-12-bretigny-sur-orge\`)."
  accident(
    "Slug de l'accident : date puis commune."
    slug: String!
  ): Accident
  """
  Itinéraire historique entre deux gares, sur le réseau en service à l'époque demandée
  (ex. Rennes - Brest en 1930). Renvoie \`null\` si une gare est inconnue ou ambiguë, ou si
  aucun chemin n'existe à cette époque : l'API REST \`GET /api/v1/route\` en donne le motif
  (code \`error\`) et les gares candidates.
  """
  route(
    "Gare de départ : slug (ex. \`rennes\`), nom exact ou nom de ville (« Paris » = toutes les gares parisiennes ouvertes à l'époque)."
    from: String!
    "Gare d'arrivée, mêmes formes que \`from\`."
    to: String!
    """
    Année quelconque : on retient l'ère de cette année, sinon la décennie précédente
    (1937 donne l'ère 1930). À partir de l'année en cours, et par défaut : réseau actuel.
    """
    era: Int
  ): Route
  """
  Un même trajet à deux époques (ex. Angoulême - Limoges en 1930 et aujourd'hui) : l'itinéraire
  de chacune, ou la raison de son absence, et les différences (longueur, lignes qui ne sont plus
  empruntées ou nouvelles, trajet devenu impossible). Renvoie \`null\` si une gare est inconnue ou
  ambiguë, si les deux années désignent la même ère ou si un graphe manque : l'API REST
  \`GET /api/v1/route/compare\` en donne le motif (code \`error\`).
  """
  routeComparison(
    "Gare de départ : slug (ex. \`angouleme\`), nom exact ou nom de ville."
    from: String!
    "Gare d'arrivée, mêmes formes que \`from\`."
    to: String!
    "Première année (par défaut : réseau actuel), mêmes règles que \`route(era)\`."
    era: Int
    "Seconde année, mêmes règles ; elle doit désigner une autre ère que \`era\`."
    compare: Int!
  ): RouteComparison
  "Un événement de ligne, par son identifiant."
  lineEvent(
    "Identifiant de l'événement."
    id: ID!
  ): LineEvent
  "Un média, par son identifiant."
  mediaItem(
    "Identifiant du média."
    id: ID!
  ): Media
  """
  Annuaire des lignes (filtres de la page \`/lines\`), trié et paginé par curseur, comme
  \`GET /api/v1/lines\`. Passez \`next_cursor\` dans \`after\` pour la page suivante.
  """
  lineDirectory(
    "Filtres facultatifs, combinés (ET)."
    filter: LineDirectoryFilter = {}
    "Nombre de lignes par page, de 1 à 100."
    first: Int = 25
    "Curseur \`next_cursor\` de la page précédente."
    after: String
  ): LineDirectoryPage!
  """
  Annuaire des gares (filtres de la page \`/stations\`), trié et paginé par curseur, comme
  \`GET /api/v1/stations\`.
  """
  stationDirectory(
    "Filtres facultatifs, combinés (ET)."
    filter: StationDirectoryFilter = {}
    "Nombre de gares par page, de 1 à 100."
    first: Int = 25
    "Curseur \`next_cursor\` de la page précédente."
    after: String
  ): StationDirectoryPage!
  "Registre des fournisseurs de données et de leurs licences, comme \`GET /api/v1/sources\`."
  sources: [SourceProvider!]!
  "Un fournisseur, par sa clé (celle de \`Provenance.provider\`) ; nul s'il est inconnu."
  source(
    "Clé du fournisseur (ex. \`osm\`, \`sncf\`, \`archeoferroviaire\`)."
    provider: String!
  ): SourceProvider
  """
  Départements : les valeurs acceptées par les filtres \`department\`, avec leur nom, leur région et ce
  que VFF y recense, comme \`GET /api/v1/departments\`. Ordre du code officiel (\`01\` à \`95\`, \`2A\`, \`2B\`,
  puis \`971\` à \`976\`).
  """
  departments(
    "Code INSEE d'une région : seulement ses départements. Code inconnu : erreur de validation."
    region: String
  ): [Department!]!
  "Un département, par son code INSEE (\`29\`, \`2A\`, \`971\` ; minuscules acceptées) ; nul s'il est inconnu."
  department(
    "Code INSEE du département."
    code: String!
  ): Department
  "Régions (valeurs acceptées par les filtres \`region\`), avec leurs départements, comme \`GET /api/v1/regions\`. Ordre alphabétique."
  regions: [Region!]!
  "Une région, par son code INSEE (\`53\`, \`84\`, \`01\`) ; nul si elle est inconnue."
  region(
    "Code INSEE de la région."
    code: String!
  ): Region
  """
  Lignes, page par page (20 par défaut, 100 au plus), filtrables par nom, code et trafic.
  Les filtres \`name\` et \`code\` ignorent la casse et acceptent le joker \`%\` :
  \`name: "%brest%"\` trouve toutes les lignes dont le nom contient « brest ».
  """
  lines(
    "Nom de la ligne, joker \`%\` accepté, casse ignorée (ex. \`%brest%\`)."
    name: String
    "Code SNCF Réseau à 6 chiffres, joker \`%\` accepté (ex. \`420000\` ou \`42%\`)."
    code: String
    "Trafic déclaré de la ligne : \`passenger\`, \`freight\` ou \`mixed\`."
    type: String
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "The offset from which items are returned."
    page: Int
  ): LinePaginator!
  """
  Gares, page par page (20 par défaut, 100 au plus), filtrables par nom, nature et état
  actuel. Le filtre \`name\` ignore la casse et accepte le joker \`%\` (ex. \`"saint-%"\`).
  """
  stations(
    "Nom de la gare, joker \`%\` accepté, casse ignorée (ex. \`%brest%\`)."
    name: String
    "Nature : \`gare\`, \`halte\`, \`point_arret\`, \`passage_niveau\` ou \`embranchement\`."
    type: String
    "État actuel : \`active\`, \`tourist\`, \`no_commercial\` ou \`declassified\`."
    status: String
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "The offset from which items are returned."
    page: Int
  ): StationPaginator!
  """
  Séries de matériel roulant (20 par défaut, 100 au plus), triées par nom sauf \`filter.sort\`.
  Pagination par curseur, comme \`lineEvents\` et \`media\` : lisez \`pageInfo { hasNextPage endCursor }\`
  et repassez \`endCursor\` dans \`after\`. Mêmes filtres et même tri que l'API REST
  \`GET /api/v1/materials\` (argument \`filter\`) ; \`type\` et \`builder\` restent acceptés directement.
  """
  materials(
    "Catégorie : \`locomotive_vapeur\`, \`locomotive_diesel\`, \`locomotive_electrique\`, \`automotrice\`, \`bimode\`, \`autorail\`, \`rame_tgv\`, \`voiture\`, \`wagon\`, \`tramway\` ou \`engin_service\`."
    type: String
    "Constructeur, joker \`%\` accepté, casse ignorée (ex. \`%alstom%\`)."
    builder: String
    "Filtres et tri (tous facultatifs, combinés par ET). Un filtre invalide est une erreur de validation."
    filter: MaterialFilter = {}
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MaterialConnection!
  """
  Accidents et événements graves du rail français, page par page (20 par défaut, 100 au plus),
  du plus ancien au plus récent sauf \`filter.sort\`. Mêmes filtres et même tri que l'API REST
  \`GET /api/v1/accidents\`.
  """
  accidents(
    "Filtres et tri (tous facultatifs, combinés par ET). Un filtre invalide est une erreur de validation."
    filter: AccidentFilter = {}
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "The offset from which items are returned."
    page: Int
  ): AccidentPaginator!
  """
  Passages à niveau du réseau ferré national (SNCF Réseau, ODbL), page par page (50 par défaut,
  200 au plus), dans l'ordre des lignes puis des points kilométriques. Mêmes filtres que l'API
  REST \`GET /api/v1/level-crossings\`.
  """
  levelCrossings(
    "Filtres (tous facultatifs, combinés par ET). Un filtre invalide est une erreur de validation."
    filter: LevelCrossingFilter = {}
    "Limits number of fetched items. Maximum allowed value: 200."
    first: Int! = 50
    "The offset from which items are returned."
    page: Int
  ): LevelCrossingPaginator!
  """
  Événements datés de toutes les lignes (ouvertures, fermetures, électrifications, déposes…),
  page par page (25 par défaut, 100 au plus), du plus ancien au plus récent, filtrables par
  ligne, type, source, département et période. Pagination par curseur : lisez
  \`pageInfo { hasNextPage endCursor }\` et repassez \`endCursor\` dans \`after\`. Chaque événement
  garde sa source, sa citation et la licence de sa source (\`provenance\`).
  """
  lineEvents(
    "Filtres facultatifs, combinés (ET)."
    filter: LineEventFilter = {}
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 25
    "A cursor after which elements are returned."
    after: String
  ): LineEventConnection!
  """
  Médias (photos, cartes postales, plans, cartes, documents) de toutes les lignes et gares,
  page par page (20 par défaut, 100 au plus), dans l'ordre de leurs identifiants, filtrables
  par ligne, gare, série de matériel, nature, fournisseur, licence, droits et époque. Chaque média porte son auteur,
  sa licence et le lien vers sa source : affichez \`attribution\` avec l'image.
  """
  media(
    "Filtres facultatifs, combinés (ET)."
    filter: MediaFilter = {}
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MediaConnection!
}


"""
Mutations de contribution : proposer une modification, un événement daté, une image, le matériel
d'une ligne ou une série de matériel, puis la relire, avec les mêmes règles que le site
(\`/contribute\`, éditeur de tracé, formulaires \`/contribute/event\`, \`/contribute/media\`,
\`/contribute/material\` et \`/contribute/series\`, espace de validation \`/validate\`).

**Authentification**, l'une ou l'autre :
- jeton personnel dans l'en-tête \`Authorization: Bearer <jeton>\`, créé dans le profil avec la case
  « Autoriser les contributions » cochée (capacité \`contribute\`) ;
- session du site (cookie), depuis une page de VFF : la requête porte l'en-tête \`X-XSRF-TOKEN\`
  (ou \`X-CSRF-TOKEN\`), ou le navigateur la déclare de même origine (\`Sec-Fetch-Site: same-origin\`).

Les mutations partagent la limite de débit du reste de l'API (REST, GraphQL, MCP), et **chaque
mutation compte pour une requête** : une opération qui en porte plusieurs (alias, comme
\`a: submitContribution(…) b: submitContribution(…)\`, ou lot de plusieurs opérations) consomme une
requête par mutation, et \`X-RateLimit-Remaining\` le montre. Une mutation au-delà de la limite est
refusée sans rien écrire (\`too_many_requests\`). Les mutations s'exécutent dans l'ordre : celles qui la
précèdent dans la même opération restent faites, les suivantes ne sont pas exécutées, et, les champs
de mutation étant non nuls, \`data\` vaut alors \`null\` en entier. Envoyez une mutation par requête pour
lire chaque résultat.
Les requêtes de lecture restent ouvertes sans compte.

**Erreurs.** Une erreur arrive dans \`errors[]\`, avec \`data.<mutation>\` à \`null\` et un code stable
dans \`extensions.code\` (messages en anglais) :
- \`post_required\` : mutation envoyée en GET (seul POST est accepté) ;
- \`unauthenticated\` : ni jeton ni session ;
- \`insufficient_scope\` : jeton sans la capacité \`contribute\` ;
- \`csrf_token_mismatch\` : session du site sans jeton CSRF valide ;
- \`account_suspended\` : compte suspendu (\`extensions.suspended_until\`, ISO 8601, nul si sans fin) ;
- \`too_many_requests\` : limite de débit atteinte par les mutations de la requête
  (\`extensions.retry_after\` : secondes à attendre) ;
- \`forbidden\` : relecture demandée par un compte qui n'est ni validateur ni administrateur ;
- \`not_found\` : contribution inconnue, ou retirée par un administrateur ;
- \`own_contribution\` : relecture de sa propre contribution ;
- \`already_closed\` : contribution déjà refusée ou publiée ;
- \`already_approved\` : le même validateur approuve une deuxième fois ;
- \`application_failed\` : la publication a échoué, rien n'est écrit et l'approbation est annulée ;
  \`extensions.reason\` donne la raison stable : \`no_geometry\` (la ligne n'a pas de tracé à l'ère de la
  contribution), \`line_not_found\` (la ligne que corrige le tracé n'existe plus), \`target_not_found\`
  (la ligne, la gare, l'événement ou la série visée n'existe plus), \`already_present\` (le fichier
  Commons a été rattaché entre-temps à la même fiche, ou une série du même nom ou du même identifiant
  Wikidata a été ajoutée entre-temps), \`file_missing\` (le fichier déposé n'est plus dans la réserve
  privée), \`incompatible\` (le lien entre la série et la ligne contredit maintenant la règle
  d'écartement ou d'électrification, et la phrase citée ne suffit plus ; ou la correction d'une série
  rendrait des liens de cette série contraires à ces règles), \`stale\` (la série corrigée a changé
  depuis l'envoi), \`inconsistent\` (avec la correction, le retrait viendrait avant la mise en service)
  ou \`internal_error\` (erreur inattendue, consignée dans le journal du site ; son détail n'est jamais
  renvoyé). \`extensions.details\` donne les précisions utiles au validateur (champs changés, liens
  concernés), vide sinon.

Une saisie refusée donne une erreur de validation : \`extensions.validation\` associe le chemin du
champ (par exemple \`input.segment.pk_end_m\`) à ses messages.

Un jeton inconnu, révoqué ou expiré reçoit HTTP 401, et le jeton d'un compte suspendu HTTP 403,
avant toute exécution (comme pour les requêtes de lecture).
"""
type Mutation {
  """
  Propose une contribution, enregistrée « en attente » (\`PENDING\`) puis annoncée aux validateurs.
  Elle est publiée après deux approbations indépendantes (\`validateContribution\`).
  
  Renseignez exactement un des six champs de \`input\` : \`segment\` (modification d'une portion de
  ligne entre deux PK), \`trace\` (tracé dessiné : ligne disparue ou correction d'une ligne), \`event\`
  (événement daté d'une ligne), \`media\` (image de Wikimedia Commons ou fichier déposé, en requête
  multipart), \`line_material\` (série de matériel qui a circulé sur une ligne) ou \`series\` (nouvelle
  série de matériel ou correction d'une série). Le compte doit être connecté et non suspendu.
  """
  submitContribution(
    "La proposition : un seul de ses six champs."
    input: ContributionInput!
  ): Contribution!
  """
  Relit une contribution en attente : approbation ou refus. Réservé aux validateurs et aux
  administrateurs, connectés et non suspendus.
  
  Deux approbations de validateurs différents publient la contribution (\`APPLIED\`) : la carte et
  la fiche de la ligne changent, l'auteur est prévenu et les webhooks \`contribution.applied\`
  partent. Un refus demande un motif, transmis à l'auteur. On ne relit pas sa propre
  contribution. Chaque décision recalcule la réputation de l'auteur et du validateur et entre au
  journal d'audit.
  """
  validateContribution(
    "Identifiant de la contribution (renvoyé par \`submitContribution\`)."
    id: ID!
    "Décision : \`APPROVE\` ou \`REJECT\`."
    decision: ContributionDecision!
    """
    Motif du refus, de 5 à 1 000 caractères (espaces de début et de fin retirés), obligatoire avec
    \`REJECT\` et transmis à l'auteur. Ignoré avec \`APPROVE\`.
    """
    reason: String
  ): ContributionReview!
}


"Date et heure au format \`AAAA-MM-JJ HH:MM:SS\` (heure de Paris), par exemple \`2026-10-20 14:05:00\`."
scalar DateTime


"""
Date au format ISO \`AAAA-MM-JJ\`, par exemple \`1981-09-27\`. Quand la date n'est connue
qu'au mois ou à l'année, le jour (et le mois) valent \`01\` : voir le champ de précision
associé quand il existe (\`LineEvent.date_precision\`).
"""
scalar Date


"""
Structure JSON (objet ou liste) renvoyée telle quelle, en sortie seulement : sa forme est décrite
par le champ qui la porte et, en détail, dans la documentation REST (\`/docs/api\`).
"""
scalar JSON


"""
Fichier envoyé avec une mutation, en entrée seulement (\`MediaContributionInput.file\`). La requête
est alors en \`multipart/form-data\`, selon la spécification GraphQL multipart request : un champ
\`operations\` (la requête et ses variables, le fichier à \`null\`), un champ \`map\` (quel envoi remplit
quelle variable, ex. \`{"0": ["variables.input.media.file"]}\`) et le fichier lui-même (champ \`0\`).
"""
scalar Upload


"""
Une ère : un instantané du réseau au **31/12** d'une année ronde.

VFF décrit le réseau par décennies, de 1830 à 2030 : l'ère 1930 montre le réseau tel qu'il
était le 31/12/1930. L'ère **2030** désigne le réseau **d'aujourd'hui**, recalculé au jour
même ; c'est l'ère courante (\`is_current\`). Les dates d'événements, elles, restent exactes
au jour près (voir \`LineEvent\`).
"""
type Era {
  "Identifiant interne de l'ère."
  id: ID!
  "Année de l'instantané (1830, 1840… 2030). C'est cette valeur qu'attendent les arguments \`era\` et \`year\`."
  year: Int!
  "Titre court de la période, en français (ex. « Les débuts du TGV »)."
  label: String
  "Vrai pour l'ère qui représente le réseau actuel (2030)."
  is_current: Boolean!
  "Présentation de la période, en français : faits marquants du réseau à cette époque."
  description: String
  "Date de l'instantané (\`AAAA-12-31\`) ; nulle pour l'ère courante, recalculée au jour même."
  snapshot_date: String
  "Faux si la carte de cette ère n'a encore aucun tronçon."
  has_data: Boolean!
  "État du réseau à cette ère (longueurs, lignes actives, gares par statut), comme \`GET /api/v1/eras/{year}\`."
  stats: EraStats!
}


"""
Une ligne ferroviaire : un itinéraire nommé, décrit ère par ère par ses tronçons.

Les lignes du réseau ferré national portent un code SNCF Réseau à 6 chiffres
(ex. \`420000\`, Paris-Montparnasse à Brest) et un slug \`rfn-<code>\`. Les lignes disparues,
secondaires ou particulières ont d'autres slugs (ex. \`archeo-…\` pour les relevés
d'archeoferroviaire).
"""
type Line {
  "Identifiant interne de la ligne."
  id: ID!
  "Identifiant stable et lisible, utilisé dans les URL du site et de l'API REST (ex. \`rfn-420000\`)."
  slug: String!
  "Code de ligne SNCF Réseau à 6 chiffres, sans espace (ex. \`420000\`). Nul pour une ligne sans code officiel."
  code: String
  "Nom usuel de la ligne (ex. « Ligne de Paris-Montparnasse à Brest »)."
  name: String!
  """
  Trafic déclaré pour la ligne entière : \`passenger\` (voyageurs), \`freight\` (fret seul) ou
  \`mixed\` (voyageurs et fret). Souvent nul : le trafic fiable est celui des tronçons, ère par
  ère (\`LineSegment.traffic_type\`).
  """
  type: String
  """
  Catégorie de réseau : \`principal\` (grandes compagnies puis SNCF), \`secondaire\` (réseaux
  secondaires d'intérêt local, souvent à voie étroite, tramways), \`militaire\`,
  \`particulier\` (réseaux particuliers, industriels).
  """
  network: String
  "Compagnie qui a construit ou exploité la ligne à l'origine (ex. « Cie de l'Ouest », « État »)."
  historical_operator: String
  "Présentation de la ligne, en français."
  description: String
  "Autres noms de la ligne, anciens ou usuels."
  alt_names: [String!]
  """
  Tronçons de la ligne : ceux d'une ère avec \`era\` (dans l'ordre des points kilométriques, comme
  \`GET /api/v1/lines/{line}/segments\`), sinon **toutes ères confondues**. Liste longue pour les
  grandes lignes : une ligne compte des dizaines de tronçons par ère.
  """
  segments(
    "Année de l'ère (décennie de 1830 à 2030). Par défaut : toutes les ères."
    era: Int
  ): [LineSegment!]!
  "Chronologie de la ligne : ouvertures, fermetures, électrifications, déposes… (ordre non garanti : triez sur \`event_date\`)."
  events: [LineEvent!]!
  "Matériels roulants associés à la ligne (ayant circulé ou circulant dessus), sans la preuve : voir \`material_links\`."
  materials: [Material!]!
  "Matériel roulant de la ligne, avec la preuve de chaque lien (source, citation, fiabilité)."
  material_links: [LineMaterialLink!]!
  """
  Accidents et événements graves rattachés à la ligne (lieu sur son tracé de l'époque, ou ligne
  citée par une source), rattachement principal ou secondaire : le détail est dans
  \`Accident.lines\`. Ordre non garanti : triez sur \`date\`.
  """
  accidents: [Accident!]!
  """
  Gares de la ligne à une ère, dans l'ordre des points kilométriques, sur le modèle de
  GTFS \`stop_times.txt\` (sans horaires). Les gares fermées à cette ère sont incluses, avec
  \`effective_status\` = \`declassified\`.
  """
  stops(
    "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire aujourd'hui."
    era: Int
  ): [LineStop!]!
  """
  Signalisation de la ligne, par tranches de points kilométriques homogènes. Données
  SNCF Réseau Open Data (ODbL) ; aujourd'hui seule l'ère courante (2030) est
  renseignée.
  """
  signalling(
    "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030."
    era: Int
  ): [LineSignallingRange!]!
  """
  Passages à niveau de la ligne aujourd'hui (SNCF Réseau, ODbL), dans l'ordre des points
  kilométriques, PK inconnus en dernier. Rattachement par le code de ligne : liste vide pour une
  ligne sans code SNCF Réseau.
  """
  level_crossings: [LevelCrossing!]!
  "Nombre de passages à niveau de la ligne par groupe (\`LevelCrossing.group\`), groupes absents omis."
  level_crossing_summary: [LevelCrossingCount!]!
  """
  Voies vertes OpenStreetMap installées sur l'emprise de la ligne (ligne disparue ou fermée), la
  plus longue sur l'emprise d'abord. Le rattachement est mesuré : un morceau de voie verte compte
  quand il est à 8 m au plus de l'axe de la ligne et qu'aucune voie n'est en service ou en place à
  moins de 30 m ; une voie verte qui longe seulement une ligne en service n'est jamais rattachée.
  Liste vide si la ligne n'en a pas. Données © contributeurs OpenStreetMap (ODbL 1.0) : reprenez la
  mention de \`LineGreenway.attribution\`.
  """
  greenways: [LineGreenway!]!
  "Longueur totale, en mètres, des voies vertes qui suivent l'ancienne emprise de la ligne (0 s'il n'y en a pas)."
  greenway_length_m: Int!
  """
  Documents d'archives numérisés dans Gallica (Bibliothèque nationale de France) dont le titre parle
  de la ligne, du plus ancien au plus récent (sans date en dernier), comme
  \`GET /api/v1/lines/{line}/archives\` : projets, plans et cartes du tracé, mémoires, guides,
  photographies. Rattachement par un score explicable calculé sur le seul titre de la notice
  (\`LineArchiveDocument.score_reasons\`), retenu à partir de 9 points sur 11 ; liste vide si aucun
  document n'est sûr. Métadonnées des notices de la BnF sous Licence Ouverte : reprenez la mention
  \`LineArchiveDocument.attribution\`. Aucune image ni vignette n'est servie : suivez \`gallica_url\`.
  """
  archives(
    "Ne garder qu'un type de document. Par défaut : tous."
    type: ArchiveDocumentType
  ): [LineArchiveDocument!]!
  """
  Mises en service de signalisation datées (BAL, BAPR, block manuel, cantonnement téléphonique, TVM,
  ETCS, KVB), relevées dans l'article Wikipédia de la ligne (texte, listes, tableaux, infobox), du plus
  ancien au plus récent. C'est un historique à part : l'état actuel de la ligne (SNCF Réseau) est donné
  par \`GET /api/v1/lines/{line}/signalling\` de l'API REST. Seuls les faits sûrs sont donnés (jour ou mois
  précis, ou année), et un fait absent ne veut pas dire qu'aucun système n'a été mis en service. Liste vide
  si l'article ne date rien. Avec \`era\`, seuls les faits encore en vigueur à la fin de l'ère (ceux que
  dessine la carte de l'époque). Texte de Wikipédia sous CC BY-SA 4.0 : reprenez la mention de
  \`SignallingEvent.attribution\`.
  """
  signalling_history(
    "Année d'une ère (décennie de 1830 à 2030) : seulement les faits en vigueur au 31 décembre de cette année (aujourd'hui pour 2030). Un fait plus récent du même groupe (cantonnement, TVM, ETCS, KVB) qui porte sur toute la ligne ou recouvre sa portion le remplace. Ère inconnue : liste vide."
    era: Int
  ): [SignallingEvent!]!
  """
  Profil en long de la ligne (altitude le long du tracé, gares placées dessus), comme
  \`GET /api/v1/lines/{line}/profile\`. Altitudes de l'IGN (BD TOPO, à défaut RGE ALTI ; Licence
  Ouverte 2.0). Nul tant que le profil de la ligne n'est pas calculé.
  """
  profile(
    "Nombre de points au plus (10 à 5 000) : profil allégé qui garde les deux bouts, le point le plus bas et le plus haut. Par défaut : tous les points."
    max_points: Int
  ): LineProfile
  """
  Point kilométrique d'un lieu sur la ligne, comme \`GET /api/v1/lines/{line}/pk\` : le point est projeté
  sur le tracé de la ligne à l'ère choisie, puis la distance est mesurée le long du tracé depuis son
  origine. Ce PK peut différer du PK officiel de SNCF Réseau (origine, sauts de numérotation). Nul si
  la ligne n'a pas de tracé à cette ère ; erreur de requête si l'ère n'existe pas. Sur un tracé dont la
  source n'autorise pas la redistribution, le PK est arrondi et le point projeté n'est pas donné
  (\`geometry_withheld\`).
  """
  pk(
    "Latitude WGS 84 du point, en degrés décimaux."
    lat: Float!
    "Longitude WGS 84 du point, en degrés décimaux."
    lng: Float!
    "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, le réseau d'aujourd'hui."
    era: Int
  ): LinePk
  """
  Fiche complète de la ligne, comme la page \`/lines/{slug}\` et \`GET /api/v1/lines/{line}\` :
  chiffres clés, électrification, signalisation, phases, chronique, gares, sections. Calcul
  lourd (jusqu'à quelques secondes) : demandez-la pour une ligne à la fois.
  """
  sheet(
    "Joindre le tracé GeoJSON de chaque portion des phases (\`portions[].geometry\`). Nul pour une portion dont une source n'autorise pas la redistribution (\`geometry_withheld\`, \`geometry_withheld_reason\`)."
    geometry: Boolean = false
  ): LineSheet!
  "Date de création de la fiche dans VFF (pas une date historique)."
  created_at: DateTime!
  "Date de dernière modification de la fiche dans VFF."
  updated_at: DateTime!
  """
  Médias (photos, cartes postales, plans…) rattachés à la ligne, page par page (20 par défaut,
  100 au plus), dans l'ordre de leurs identifiants. Chaque média porte ses droits.
  """
  media(
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MediaConnection!
}


"""
Une gare sur une ligne, à une ère : sa position, son point kilométrique et son état à cette
époque.
"""
type LineStop {
  "Rang de la gare sur la ligne, à partir de 0, dans l'ordre des points kilométriques."
  position: Int!
  """
  Point kilométrique de la gare, en **mètres** (ex. \`373450\` = PK 373+450). PK officiel de
  SNCF Réseau quand il est connu, sinon estimé sur le tracé et calé sur les PK voisins.
  Équivaut au \`shape_dist_traveled\` de GTFS.
  """
  pk_m: Int!
  """
  État de la gare à l'ère demandée : \`active\` (desservie par des trains de voyageurs),
  \`tourist\` (desservie par un train touristique), \`no_commercial\` (plus de voyageurs, fret
  seul), \`declassified\` (fermée). Une gare est fermée si toutes ses lignes sont fermées à
  cette ère, ou si elle n'est plus desservie.
  """
  effective_status: String!
  "Vrai pour une gare d'extrémité de la ligne."
  is_terminal: Boolean!
  "La gare elle-même."
  station: Station!
}


"""
Une tranche de ligne homogène en signalisation, entre deux points kilométriques.

Deux notions distinctes : le **cantonnement** (\`signalling\`), qui espace les trains, et le
**contrôle de vitesse** (\`train_protection\`), qui surveille le conducteur. Le KVB est un
contrôle de vitesse, pas un mode de cantonnement.
"""
type LineSignallingRange {
  "Point kilométrique de début de la tranche, en mètres (numérotation SNCF Réseau)."
  pk_start_m: Int!
  "Point kilométrique de fin de la tranche, en mètres."
  pk_end_m: Int!
  "Longueur de la tranche, en mètres (\`pk_end_m - pk_start_m\`)."
  length_m: Int!
  """
  Mode de cantonnement : \`BAL\` (block automatique lumineux), \`BAPR\` (block automatique à
  permissivité restreinte), \`BM\` (block manuel), \`BMVU\` (block manuel de voie unique),
  \`CAPI\` (cantonnement assisté par informatique), \`CT\` (cantonnement téléphonique de double voie),
  \`TVM-300\` / \`TVM-430\` (signalisation en cabine des LGV, sans signaux latéraux),
  \`ETCS_L1\` / \`ETCS_L2\`. Suffixes : \`-VB\` voie banalisée (circulation dans les deux sens
  sur chaque voie), \`-VU\` voie unique, \`-Tram\` section tram-train. Quand deux systèmes sont
  installés ensemble, c'est le premier du libellé de SNCF Réseau : voir \`signalling_systems\`.
  """
  signalling: String!
  """
  Tous les systèmes de cantonnement installés sur la tranche, code principal (\`signalling\`)
  en premier. En général un seul ; deux sur les LGV équipées à la fois de l'ETCS niveau 2 et de
  la TVM : \`["ETCS_L2", "TVM-300"]\` (LGV Bretagne - Pays de la Loire, LGV SEA),
  \`["ETCS_L2", "TVM-430"]\` (LGV Est).
  """
  signalling_systems: [String!]!
  "Libellé de SNCF Réseau tel quel (ex. « European train control system 2/Transmission voie-machine 300 »), null pour une tranche d'une autre source."
  source_label: String
  """
  Systèmes de contrôle de vitesse installés sur la tranche : \`KVB\`, \`KVBP\`, \`TVM-300\`,
  \`TVM-430\`, \`ERTMS_L1\`, \`ERTMS_L2\`. Liste vide si aucun n'est connu.
  """
  train_protection: [String!]!
  "Raccourci : vrai si la tranche est équipée du KVB ou du KVBP."
  kvb_equipped: Boolean!
  "Remarque sur la tranche, en général la source (ex. « Source : SNCF Open Data, mode-de-cantonnement-des-lignes »)."
  note: String
}


"""
Un tronçon de ligne à une ère : une portion de voie géolocalisée, avec son état et ses
caractéristiques à cette époque. Une ligne compte en général plusieurs tronçons par ère.
"""
type LineSegment {
  "Identifiant interne du tronçon."
  id: ID!
  "Ère décrite par ce tronçon (instantané au 31/12 de l'année ; 2030 = aujourd'hui)."
  era: Era!
  """
  État de la voie à cette ère :
  \`in_service\` (exploitée), \`single_track\` (exploitée, à voie unique), \`service_track\`
  (voie de service ou portuaire, hors ligne du réseau ferré national), \`construction\`
  (en travaux), \`abandoned\` (fermée ou neutralisée, voie encore en place), \`dismantled\`
  (voie déposée, emprise encore visible), \`razed\` (emprise disparue). Un déclassement
  juridique seul ne vaut pas dépose : il donne \`abandoned\`.
  """
  status: String!
  """
  Trafic à cette ère : \`passenger\` (voyageurs seuls), \`freight\` (fret seul), \`mixed\`
  (voyageurs et fret ; une portion ouverte aux voyageurs est réputée mixte, sauf sur LGV),
  \`unknown\` (non renseigné).
  """
  traffic_type: String
  """
  Provenance du tracé : \`sncf\` (SNCF Réseau Open Data, ODbL), \`bdtopo\` (IGN BD TOPO,
  Licence Ouverte 2.0), \`osm\` (OpenStreetMap, ODbL), \`ohm\` (OpenHistoricalMap, CC0),
  \`archeoferroviaire\` (relevés tiers hors ODbL : usage non commercial uniquement, crédit et
  lien vers la source obligatoires).
  """
  source: String
  "Vrai si la portion est électrifiée à cette ère."
  electrified: Boolean!
  "Tension d'alimentation en volts (ex. \`1500\` en continu, \`25000\` en alternatif 50 Hz). Nul si non électrifiée ou inconnue."
  voltage_v: Int
  "Vitesse maximale autorisée, en km/h. Nul si inconnue."
  max_speed_kmh: Int
  "Écartement des rails en millimètres : \`1435\` voie normale, \`1000\` voie métrique, \`600\` voie étroite… Nul si inconnu."
  gauge_mm: Int
  "Vrai si la portion est exploitée par une association à cette ère (train touristique, vélorail, musée)."
  is_heritage: Boolean!
  "Cantonnement du tronçon, quand il est connu à ce niveau (voir plutôt \`Line.signalling\`)."
  signalling: String
  "Contrôle de vitesse du tronçon, quand il est connu à ce niveau (voir plutôt \`Line.signalling\`)."
  train_protection: String
  "Exploitant de la portion à cette ère, s'il diffère de l'exploitant habituel (ex. une association)."
  operator: String
  "Point kilométrique de début du tronçon, en mètres, quand il est connu."
  pk_start_m: Int
  "Point kilométrique de fin du tronçon, en mètres, quand il est connu."
  pk_end_m: Int
  "Date de dépose de la voie, si elle est connue."
  dismantled_at: Date
  """
  Géométrie du tronçon : une chaîne JSON contenant une géométrie GeoJSON (\`LineString\` ou
  \`MultiLineString\`, coordonnées WGS 84 dans l'ordre longitude, latitude). À décoder côté
  client avec \`JSON.parse\`. Vaut \`null\` quand la source du tronçon n'autorise pas la
  redistribution (licence non ouverte, non commerciale ou statut incertain, ou source absente du
  registre des droits : voir \`source\`). Les tuiles vectorielles de la carte servent à l'affichage
  et ne sont pas un export de données.
  """
  geojson: String
  "Longueur du tronçon, en mètres."
  length_m: Int
  "Nature de l'exploitation associative : \`tourist_train\`, \`velorail\`, \`museum\`."
  heritage_kind: String
  "Année d'électrification, quand elle est connue."
  electrified_year: Int
  "Source de l'électrification (\`sncf\`, \`osm\`, \`wikipedia\`…)."
  electrification_source: String
  "Source de la vitesse maximale."
  max_speed_source: String
  "Classe d'écartement : \`standard\`, \`metre\`, \`narrow\`…"
  gauge_class: String
  "Reconversion de l'emprise après dépose : \`greenway\` (voie verte), \`road\`, \`path\`, \`urban\`, \`other\`."
  reuse: String
  "Fournisseur du tracé et sa licence (registre des droits)."
  provenance: Provenance!
}


"""
Un événement daté de la vie d'une ligne : ouverture, fermeture, électrification, dépose…

La date est exacte au jour près quand on la connaît : lisez \`event_date\` avec
\`date_precision\` pour n'afficher que la partie connue (jj/mm/aaaa, mm/aaaa ou aaaa).
"""
type LineEvent {
  "Identifiant interne de l'événement."
  id: ID!
  """
  Nature de l'événement.
  - Trafic, tous services : \`opening\` (ouverture), \`closure\` (fermeture), \`reopening\` (réouverture).
  - Un seul service : \`opening_passenger\`, \`closure_passenger\`, \`reopening_passenger\`
    (voyageurs) ; \`opening_freight\`, \`closure_freight\`, \`reopening_freight\` (fret). Une ligne
    fermée aux voyageurs mais ouverte au fret devient une ligne fret.
  - Infrastructure : \`electrification\`, \`modernization\` (modernisation), \`dismantling\`
    (dépose de la voie), \`conversion\` (reconversion de l'emprise en voie verte, route… ;
    voie déposée).
  - Administration : \`concession\`, \`concession_end\` (fin de concession), \`declassification\`
    (retrait juridique du réseau, qui ne prouve pas la dépose).
  - Associations : \`heritage_start\`, \`heritage_end\` (train touristique, vélorail, musée).
  - Autres : \`accident\`, \`other\`.
  """
  type: String!
  "Date de l'événement (\`AAAA-MM-JJ\`), à lire avec \`date_precision\`."
  event_date: Date!
  """
  Précision de \`event_date\` : \`day\` (date exacte, à afficher jj/mm/aaaa), \`month\` (mois et
  année connus, jour fixé à 01), \`year\` (année seule, mois et jour fixés à 01). Nul : \`day\`.
  """
  date_precision: String
  "Début de la portion concernée (gare ou lieu), si l'événement ne touche qu'une section de la ligne."
  section_from: String
  "Fin de la portion concernée (gare ou lieu)."
  section_to: String
  "Description de l'événement, en français."
  description: String
  """
  Provenance de l'information : \`wikipedia\`, \`wikipedia-reseaux\`, \`wikidata\`, \`ohm\`
  (OpenHistoricalMap), \`lignes-oubliees\`, \`archeoferroviaire\`, \`osm\`, \`heritage\`,
  \`estimation-reseau\` (date déduite du réseau voisin), \`manual\` (saisie VFF)…
  """
  source: String
  "Lien vers le document source consulté. Citez-le en réutilisant la donnée."
  source_url: String
  """
  Fournisseur de la donnée et licence de sa source, d'après le registre des droits de VFF.
  À citer en réutilisant l'événement.
  """
  provenance: Provenance!
  "Fiabilité de la date : \`high\`, \`medium\` ou \`low\`."
  confidence: String
  "Citation de la source : l'extrait du texte d'où l'événement est tiré. Sous la licence de la source."
  source_quote: String
  "Point kilométrique de début de la portion concernée, en mètres, quand elle a pu être située sur le tracé."
  section_pk_start_m: Int
  "Point kilométrique de fin de la portion concernée, en mètres."
  section_pk_end_m: Int
  "Longueur de la portion concernée, en mètres."
  section_length_m: Int
  "Comment la portion a été située sur le tracé (\`pk\`, \`whole_line\`, \`unresolved\`…)."
  section_resolution: String
  "Reconversion de l'emprise après la dépose (voie verte, route…), pour un événement \`conversion\`."
  reuse: String
  "Nature de l'exploitation associative (train touristique, vélorail, musée…), pour \`heritage_start\` et \`heritage_end\`."
  heritage_kind: String
  "La ligne concernée."
  line: Line!
  """
  Images rattachées à cet événement (photo du jour de l'ouverture, affiche, avis de fermeture…),
  publiées après validation d'une contribution, page par page (20 par défaut, 100 au plus), dans
  l'ordre de leurs identifiants. Chaque média porte ses droits. Liste vide le plus souvent.
  """
  media(
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MediaConnection!
}


"""
Provenance et licence d'une donnée, d'après le registre des droits de VFF. Citez le
fournisseur en réutilisant la donnée.
"""
type Provenance {
  "Clé du fournisseur (\`wikipedia\`, \`wikidata\`, \`ohm\`, \`osm\`, \`sncf\`, \`vff\`…). Nulle si la source n'est pas déclarée."
  provider: String
  "Nom lisible du fournisseur."
  name: String
  "Licence ou conditions de réutilisation, telles que vérifiées par VFF."
  license: String
  """
  Solidité de la licence : \`verified\` (licence ouverte établie), \`uncertain\` (autorisation
  particulière ou conditions à lire : citez la source), \`forbidden\`.
  """
  license_status: String
}


"""
Un événement daté de la vie d'une gare : ouverture, fermeture, démolition…

Comme pour \`LineEvent\`, lisez \`event_date\` avec \`date_precision\`.
"""
type StationEvent {
  "Identifiant interne de l'événement."
  id: ID!
  """
  Nature de l'événement : \`opening\` (ouverture), \`closure\` (fermeture totale),
  \`closure_passenger\` (fermeture aux voyageurs), \`closure_freight\` (fermeture au fret),
  \`reopening\` (réouverture), \`demolition\` (démolition).
  """
  type: String!
  "Date de l'événement (\`AAAA-MM-JJ\`), à lire avec \`date_precision\`."
  event_date: Date!
  "Précision de \`event_date\` : \`day\`, \`month\` ou \`year\` (mois et jour fixés à 01)."
  date_precision: String
  "Source de l'événement : \`wikidata\`, \`wikipedia\`, \`ohm\`, \`osm\`, \`correction\`, \`inferred:line_event\`, \`inferred:trace\`…"
  source: String!
  "Référence dans la source (identifiant Wikidata et propriété, page…)."
  source_ref: String
  "Lien vers la source, quand il existe."
  source_url: String
  "Vrai si VFF a déduit l'événement des lignes qui desservent la gare ou de l'état des tracés : ce n'est pas un relevé direct."
  is_inferred: Boolean!
  "Précision sur le contexte de l'événement."
  note: String
  "Fournisseur de la donnée et licence de sa source."
  provenance: Provenance!
  "La gare concernée."
  station: Station!
}


"""
Un média : photo, carte postale, plan, carte ou document d'archive lié à une ligne, à une gare, à
un événement daté d'une ligne ou à une série de matériel roulant.

Les médias externes (Wikimedia Commons…) ne sont pas hébergés par VFF : les adresses d'images
pointent chez le fournisseur. Chaque média porte ses propres droits, à afficher avec l'image :
\`attribution\` est une ligne de crédit prête à l'emploi.
"""
type Media {
  "Identifiant interne du média."
  id: ID!
  "Fournisseur du média : \`commons\` (Wikimedia Commons), \`wikidata\`, \`gallica\`, \`upload\` (hébergé par VFF)…"
  provider: String!
  "Nom lisible du fournisseur (ex. « Wikimedia Commons »)."
  provider_label: String!
  "Nature du média : \`photo\`, \`postcard\` (carte postale), \`map\` (carte), \`plan\` ou \`document\`."
  type: String!
  "Titre du média."
  title: String
  "Légende du média."
  caption: String
  "Adresse de la vignette (environ 500 px), chez le fournisseur pour un média externe."
  thumb_url: String
  "Adresse de l'image en grande taille."
  display_url: String
  "Page d'origine du média : la page Wikimedia Commons du fichier pour un média Commons. À citer."
  source_url: String
  "Largeur de l'image, en pixels."
  width: Int
  "Hauteur de l'image, en pixels."
  height: Int
  "Année représentée par le média (pas celle de sa mise en ligne). Nulle si inconnue."
  depicted_year: Int
  "Fin de la fourchette d'années représentées, quand \`date_precision\` vaut \`range\`."
  depicted_year_to: Int
  """
  Ce qu'on sait de la date représentée : \`day\`, \`month\`, \`year\`, \`decade\`, \`circa\` (vers),
  \`range\` (fourchette), \`before\` ou \`after\`.
  """
  date_precision: String
  "Date de prise de vue, quand elle est connue."
  captured_at: Date
  "Auteur du média."
  author: String
  "Ligne de crédit prête à l'emploi, à afficher avec l'image (auteur, licence, fournisseur)."
  attribution: String
  "Licence telle que déclarée par la source (ex. \`CC BY-SA 4.0\`, \`Public domain\`). Nulle si inconnue : aucune licence n'est présumée."
  license: String
  "Lien vers le texte de la licence."
  license_url: String
  "Statut des droits : \`public_domain\`, \`open_license\` (licence libre), \`permission\` (autorisation) ou \`unknown\`."
  rights_status: String!
  "Vrai si l'usage commercial est permis, faux s'il ne l'est pas, nul si inconnu."
  commercial_ok: Boolean
  "Vrai si le partage dans les mêmes conditions est exigé."
  share_alike: Boolean
  "Vrai si la mention de l'auteur est obligatoire, nul si inconnu."
  attribution_required: Boolean
  "Vrai si le média est mis en avant sur la fiche de son entité."
  featured: Boolean!
  """
  Numéro de la contribution validée qui a publié ce média (image proposée par un contributeur,
  fichier de Wikimedia Commons ou fichier déposé). Nul pour un média importé par VFF.
  """
  contribution_id: Int
  "Latitude WGS 84 du lieu de prise de vue, en degrés décimaux, quand elle est connue."
  lat: Float
  "Longitude WGS 84 du lieu de prise de vue, en degrés décimaux, quand elle est connue."
  lng: Float
  "La ligne à laquelle le média est rattaché, si c'est une ligne."
  line: Line
  "La gare à laquelle le média est rattaché, si c'est une gare."
  station: Station
  "La série de matériel roulant à laquelle le média est rattaché, si c'en est une."
  material: Material
  "L'événement daté d'une ligne auquel le média est rattaché, si c'en est un."
  event: LineEvent
}


"Directions for ordering a list of records."
enum SortOrder {
  "Sort records in ascending order."
  ASC
  "Sort records in descending order."
  DESC
}


"Filtres de la requête \`lineEvents\`. Tous sont facultatifs et se combinent (ET)."
input LineEventFilter {
  "Slug de la ligne (ex. \`rfn-420000\`). Une ligne inconnue est une erreur de validation."
  line: String
  "Type d'événement (voir \`LineEvent.type\`), ex. \`closure_passenger\`."
  type: String
  "Provenance de l'événement (voir \`LineEvent.source\`), ex. \`wikipedia\`."
  source: String
  "Code INSEE du département (\`35\`, \`2A\`, \`971\`…) : lignes citant ce département ou y desservant une gare. Liste : requête \`departments\`."
  department: String
  "Code INSEE de la région (\`53\` Bretagne, \`11\` Île-de-France…, liste : requête \`regions\`) : lignes citant l'un de ses départements ou y desservant une gare."
  region: String
  "Début de la période, bornes incluses : une année (\`1930\`, soit le 1er janvier) ou une date (\`1930-06-15\`)."
  from: String
  "Fin de la période, bornes incluses : une année (\`1939\`, soit le 31 décembre) ou une date (\`1939-12-31\`)."
  to: String
  "Ordre chronologique. Par défaut : du plus ancien (\`ASC\`)."
  order: SortOrder
}


"Filtres de la requête \`materials\`. Tous sont facultatifs et se combinent (ET)."
input MaterialFilter {
  "Recherche sans accents ni capitales dans le nom, les autres noms, les constructeurs et les numéros d'engins (100 caractères au plus)."
  q: String
  "Catégorie (voir \`Material.type\`)."
  type: String
  "Constructeur, recherche partielle sans accents ni capitales (ex. \`alsthom\`)."
  builder: String
  "Exploitant : identifiant Wikidata exact (\`Q13646\` = SNCF) ou nom partiel sans accents ni capitales."
  operator: String
  "Slug d'une ligne : séries qui l'ont parcourue d'après une source. Ligne inconnue : erreur de validation."
  line: String
  "Année (1800 à 2100) : séries en service cette année-là (mises en service avant, pas retirées avant ; sans date de mise en service : exclues)."
  in_service_at: Int
  "Mise en service à partir de cette année, incluse (1800 à 2100)."
  from_year: Int
  "Mise en service jusqu'à cette année, incluse (1800 à 2100, au moins égale à \`from_year\`)."
  to_year: Int
  "Écartement en millimètres (500 à 2000), ex. \`1000\` pour la voie métrique."
  gauge_mm: Int
  "État d'utilisation déclaré par une source : \`in_service\` (en service) ou \`withdrawn\` (retirée). Une série dont aucune source ne dit l'état n'est dans aucun des deux cas."
  service_status: String
  "Périmètre : \`france\` (série rattachée à la France dans Wikidata) ou \`attested\` (série non rattachée, par exemple un ICE ou une locomotive belge, dont une source atteste la circulation sur une ligne française)."
  scope: String
  "Vrai : séries illustrées seulement ; faux : séries sans image."
  has_image: Boolean
  "Vrai : séries reliées à au moins une ligne ; faux : séries sans ligne connue."
  has_lines: Boolean
  """
  Tri : \`name\` (par défaut), \`in_service_from\`, \`max_speed_kmh\`, \`units_built\`, \`lines_count\`,
  préfixe \`-\` pour l'ordre décroissant (ex. \`-max_speed_kmh\`). Valeurs inconnues en dernier.
  """
  sort: String
}


"Filtres de la requête \`media\`. Tous sont facultatifs et se combinent (ET)."
input MediaFilter {
  "Slug d'une ligne : ses médias seulement. Exclusif avec \`station\`, \`material\` et \`event\`."
  line: String
  "Slug d'une gare : ses médias seulement. Exclusif avec \`line\`, \`material\` et \`event\`."
  station: String
  "Slug d'une série de matériel roulant : ses médias seulement (images proposées pour sa fiche). Exclusif avec \`line\`, \`station\` et \`event\`."
  material: String
  "Identifiant d'un événement daté d'une ligne (\`LineEvent.id\`) : ses images seulement. Exclusif avec \`line\`, \`station\` et \`material\`."
  event: Int
  "Nature du média : \`photo\`, \`postcard\`, \`map\`, \`plan\` ou \`document\`."
  type: String
  "Fournisseur du média : \`commons\`, \`upload\`…"
  provider: String
  "Licence telle que déclarée par la source, casse ignorée (ex. \`CC BY-SA 4.0\`)."
  license: String
  "Statut des droits : \`public_domain\`, \`open_license\`, \`permission\` ou \`unknown\`."
  rights_status: String
  "Vrai : seulement les médias d'usage commercial permis ; faux : les autres."
  commercial_ok: Boolean
  "Époque représentée : \`pre1900\`, \`1900_1944\`, \`1945_1969\`, \`1970_1999\`, \`since2000\` ou \`undated\`."
  period: String
  "Année représentée minimale, incluse (exclut les médias non datés)."
  from_year: Int
  "Année représentée maximale, incluse, au moins égale à \`from_year\`."
  to_year: Int
  "Vrai : seulement les médias mis en avant (images de l'accueil) ; faux : les autres."
  featured: Boolean
  "Ordre des identifiants. Par défaut : croissant (\`ASC\`)."
  order: SortOrder
}


"""
Une gare, une halte ou un point d'arrêt ferroviaire. Les champs décrivent la gare
**aujourd'hui** ; son état à une époque donnée se lit sur une ligne (\`Line.stops\`) ou dans
l'API REST (\`/api/v1/eras/{year}/stations.geojson\`).
"""
type Station {
  "Identifiant interne de la gare."
  id: ID!
  "Identifiant stable et lisible (ex. \`rennes\`), utilisé dans les URL et par \`route(from, to)\`."
  slug: String!
  "Nom de la gare (ex. « Paris-Montparnasse-Pasteur »)."
  name: String!
  "Nature : \`gare\`, \`halte\`, \`point_arret\` (point d'arrêt non géré), \`passage_niveau\`, \`embranchement\`."
  type: String!
  """
  État actuel : \`active\` (desservie par des trains de voyageurs), \`tourist\` (desservie par
  un train touristique), \`no_commercial\` (plus de voyageurs, fret seul), \`declassified\`
  (fermée).
  """
  status: String!
  "Commune où se trouve la gare."
  city: String
  "Code du département (INSEE, ex. \`35\`, \`2A\`)."
  department_code: String
  "Date d'ouverture (\`AAAA-MM-JJ\`) ; quand seule l'année est connue, jour et mois valent 01."
  opened_at: Date
  "Date de fermeture (\`AAAA-MM-JJ\`), nulle si la gare est ouverte ou la date inconnue ; quand seule l'année est connue, jour et mois valent 01."
  closed_at: Date
  "Présentation de la gare, en français."
  description: String
  "Latitude WGS 84, en degrés décimaux."
  lat: Float
  "Longitude WGS 84, en degrés décimaux."
  lng: Float
  "Chronologie de la gare : ouverture, fermeture, démolition…, du plus ancien au plus récent, avec la source de chaque événement."
  events: [StationEvent!]!
  """
  Fiche complète de la gare, comme la page \`/stations/{slug}\` et \`GET /api/v1/stations/{station}\` :
  présence par décennie, lignes desservies, sources, gares voisines.
  """
  sheet: StationSheet!
  """
  Médias (photos, cartes postales, plans…) rattachés à la gare, page par page (20 par défaut,
  100 au plus), dans l'ordre de leurs identifiants. Chaque média porte ses droits.
  """
  media(
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MediaConnection!
}


"""
Une série de matériel roulant ayant circulé en France : locomotives (vapeur, diesel,
électriques), automotrices, autorails, rames TGV, voitures, wagons, tramways, engins de service.

Fiches importées de **Wikidata** (CC0, aucune obligation) ; image et droits lus dans
**Wikimedia Commons** (licence propre à chaque fichier, à afficher avec l'image). Un champ nul
veut dire que la source ne le renseigne pas : VFF n'estime aucune valeur. La provenance de
chaque champ est dans \`field_sources\`.
"""
type Material {
  "Identifiant interne du matériel."
  id: ID!
  "Identifiant stable et lisible, dérivé du nom (ex. \`bb-15000\`), utilisé dans les URL du site et de l'API REST."
  slug: String!
  "Nom d'usage de la série : titre de l'article Wikipédia en français, sinon libellé Wikidata (ex. « BB 15000 »)."
  name: String!
  """
  Catégorie : \`locomotive_vapeur\`, \`locomotive_diesel\`, \`locomotive_electrique\`,
  \`automotrice\` (rame automotrice électrique), \`bimode\` (engin qui circule sous caténaire et à
  l'énergie thermique : Régiolis, AGC bimodes ; rangé là seulement quand Wikidata ou l'infobox de
  l'article Wikipédia le dit, sinon l'engin reste \`automotrice\` ou \`autorail\`), \`autorail\` (rame
  thermique), \`rame_tgv\` (grande vitesse), \`voiture\` (voiture voyageurs), \`wagon\` (marchandises),
  \`tramway\`, \`engin_service\` (engin de l'infrastructure : auscultation, chasse-neige, draisine).
  """
  type: String!
  "Libellé français de la catégorie (ex. « Locomotive électrique »)."
  type_label: String!
  "Autres noms : libellés et alias Wikidata (français, anglais), surnoms. Liste vide si aucun."
  designations: [String!]!
  "Courte description en français (description Wikidata, CC0), ex. « série de 65 locomotives électriques de la SNCF »."
  description: String
  "Constructeurs en une ligne lisible, noms séparés par des virgules (ex. « Alstom, Matériel de Traction Électrique »)."
  builder: String
  "Constructeurs, un par entrée, avec le nombre d'engins construits par chacun quand il est connu."
  builders: [MaterialBuilder!]!
  "Exploitants de la série, avec leur période d'exploitation et le nombre d'engins quand ils sont connus."
  operators: [MaterialOperator!]!
  "Année de mise en service de la série (la plus ancienne déclarée). Nulle si inconnue."
  in_service_from: Int
  """
  Année de retrait du service (la plus récente déclarée). Nulle si la série circule encore OU si
  la date de retrait n'est pas renseignée : ne concluez pas qu'elle est en service (voir \`service\`
  pour l'état d'utilisation, quand une source le dit).
  """
  in_service_to: Int
  "État d'utilisation, seulement quand une source le dit (Wikidata P5817, effectif de l'infobox Wikipédia) ; tous les champs sont nuls sinon."
  service: MaterialService!
  """
  Périmètre de la fiche : \`france\` (série rattachée à la France dans Wikidata) ou \`attested\` (série
  non rattachée, par exemple un ICE, un AVE ou une locomotive belge, ajoutée parce qu'une source atteste
  sa circulation sur une ligne française : voir \`field_sources\` (champ \`scope\`) et \`line_links\`).
  """
  scope: String!
  "Nombre d'engins construits, s'il est univoque dans la source."
  units_built: Int
  "Vitesse maximale en km/h (la plus grande déclarée, convertie si la source l'exprime en mi/h)."
  max_speed_kmh: Int
  """
  Puissance nominale en kilowatts (la plus grande déclarée ; convertie depuis les
  chevaux-vapeur métriques si besoin, valeur d'origine dans \`field_sources\`). Rarement renseignée.
  """
  power_kw: Int
  "Alimentations électriques (ex. « électrification ferroviaire 25 kV 50 Hz »). Liste vide pour un engin thermique ou si non renseignée."
  electrification: [MaterialElectrification!]!
  "Écartement en millimètres quand la série n'en a qu'un (\`1435\` voie normale, \`1000\` voie métrique). Nul si inconnu ou multiple."
  gauge_mm: Int
  "Écartements de la série."
  gauges: [MaterialGauge!]!
  "Disposition des essieux telle que libellée par Wikidata (ex. « B'B' », « 141 »)."
  wheel_arrangement: String
  "Numéros des engins de la série, tels que déclarés (ex. « 15001 - 15065 » ; plusieurs plages séparées par « ; »)."
  fleet_numbers: String
  "Image principale, hébergée par Wikimedia Commons, avec ses droits. Nulle si aucune."
  image: MaterialImage
  "Catégorie Wikimedia Commons de la série (autres photos), ex. « SNCF Class BB 15000 »."
  commons_category: String
  "Adresse de la catégorie Commons."
  commons_category_url: String
  "Élément Wikidata de la série (ex. \`Q746572\`). Nul pour une fiche saisie dans VFF."
  wikidata_id: String
  "Adresse de l'élément Wikidata."
  wikidata_url: String
  "Titre de l'article Wikipédia en français."
  wikipedia_fr: String
  "Adresse de l'article Wikipédia en français."
  wikipedia_fr_url: String
  "Titre de l'article Wikipédia en anglais."
  wikipedia_en: String
  "Adresse de l'article Wikipédia en anglais."
  wikipedia_en_url: String
  "Nombre de lignes de VFF que la série a parcourues d'après une source (voir \`line_links\`)."
  lines_count: Int!
  "Provenance de la fiche : \`wikidata\`, \`contrib\` (série proposée par un contributeur et validée), ou nulle pour une fiche saisie dans VFF."
  source: String
  "Fournisseur de la fiche et sa licence (Wikidata : CC0). L'image a sa propre licence (\`image\`)."
  provenance: Provenance!
  "Provenance de chaque champ renseigné : source, référence précise, adresse, licence."
  field_sources: [MaterialFieldSource!]!
  "Lignes sur lesquelles ce matériel a circulé ou circule (sans la preuve : voir \`line_links\`)."
  lines: [Line!]!
  "Lignes parcourues, avec la preuve de chaque lien (source, citation, fiabilité)."
  line_links: [LineMaterialLink!]!
  """
  Médias (photos, plans, documents) rattachés à la série, publiés après validation d'une
  contribution, page par page (20 par défaut, 100 au plus), dans l'ordre de leurs identifiants.
  Chaque média porte ses droits. L'image principale de la fiche reste dans \`image\`.
  """
  media(
    "Limits number of fetched items. Maximum allowed value: 100."
    first: Int! = 20
    "A cursor after which elements are returned."
    after: String
  ): MediaConnection!
}


"""
État d'utilisation d'une série, tel que le dit une source. Jamais déduit : une date de retrait absente
ne prouve pas qu'une série circule encore.
"""
type MaterialService {
  "\`in_service\` (en service) ou \`withdrawn\` (retirée) ; nul si aucune source ne le dit."
  status: String
  "Effectif cité par la source (nombre d'engins en service à la date \`as_of\`), nul si elle n'en donne pas."
  units_in_service: Int
  "Date à laquelle la source donne cet état : \`AAAA-MM-JJ\`, \`AAAA-MM\` ou \`AAAA\` selon sa précision. Nulle si elle n'en donne pas."
  as_of: String
  "Date à laquelle VFF a lu la source (\`AAAA-MM-JJ\`)."
  checked_at: String
  "Source : \`wikidata\` (propriété P5817) ou \`wikipedia\` (effectif de l'infobox de l'article)."
  source: String
  "Page de la source : élément Wikidata ou version citée de l'article Wikipédia."
  url: String
}


"Un constructeur d'une série de matériel (Wikidata, propriété P176)."
type MaterialBuilder {
  "Nom du constructeur (libellé Wikidata en français, sinon en anglais). Un élément sans libellé garde son identifiant (ex. \`Q529531\`), que le site n'affiche pas comme un nom."
  name: String!
  "Élément Wikidata du constructeur (ex. \`Q309084\` pour Alstom)."
  wikidata_id: String
  "Nombre d'engins construits par ce constructeur, nul si non précisé."
  units: Int
}


"Un exploitant d'une série de matériel (Wikidata, propriété P137)."
type MaterialOperator {
  "Nom de l'exploitant (libellé Wikidata en français, ex. « SNCF »)."
  name: String!
  "Élément Wikidata de l'exploitant (ex. \`Q13646\` pour la SNCF)."
  wikidata_id: String
  "Première année d'exploitation par lui, nulle si non précisée."
  from_year: Int
  "Dernière année d'exploitation par lui, nulle si non précisée ou en cours."
  to_year: Int
  "Nombre d'engins exploités par lui, nul si non précisé."
  units: Int
}


"Une alimentation électrique d'une série (Wikidata, propriété P930)."
type MaterialElectrification {
  "Libellé Wikidata en français (ex. « électrification ferroviaire 1 500 V continu »)."
  label: String!
  "Élément Wikidata du système d'électrification."
  wikidata_id: String
}


"Un écartement de voie d'une série (Wikidata, propriété P1064)."
type MaterialGauge {
  "Libellé Wikidata en français (ex. « écartement standard », « écartement métrique »)."
  label: String!
  "Écartement en millimètres (largeur déclarée par l'élément d'écartement), nul si inconnu."
  mm: Int
  "Élément Wikidata de l'écartement (ex. \`Q1999572\`)."
  wikidata_id: String
}


"""
Image d'une série, hébergée par Wikimedia Commons. Ses droits sont ceux du fichier : à afficher
avec l'image (\`attribution\` est une ligne de crédit prête à l'emploi).
"""
type MaterialImage {
  "Fournisseur : \`commons\`."
  provider: String!
  "Nom du fichier Commons (« File:… »)."
  file: String!
  "Adresse de la vignette (500 px de large)."
  thumb_url: String
  "Adresse de l'image en grande taille."
  display_url: String
  "Page du fichier sur Commons : à citer."
  source_url: String!
  "Largeur de l'original, en pixels."
  width: Int
  "Hauteur de l'original, en pixels."
  height: Int
  "Auteur, nul si inconnu ou anonyme."
  author: String
  "Licence telle que déclarée par Commons (ex. \`CC BY-SA 4.0\`), nulle si inconnue : aucune licence n'est présumée."
  license: String
  "Lien vers le texte de la licence."
  license_url: String
  "Statut des droits : \`public_domain\`, \`open_license\` (licence libre) ou \`unknown\`."
  rights_status: String!
  "Vrai si l'usage commercial est permis, nul si inconnu."
  commercial_ok: Boolean
  "Vrai si le partage dans les mêmes conditions est exigé."
  share_alike: Boolean
  "Vrai si la mention de l'auteur est obligatoire, nul si inconnu."
  attribution_required: Boolean
  "Ligne de crédit prête à l'emploi (auteur, licence, « via Wikimedia Commons »)."
  attribution: String!
}


"Provenance d'un champ d'une fiche de matériel."
type MaterialFieldSource {
  "Nom du champ (ex. \`in_service_from\`, \`max_speed_kmh\`, \`image\`)."
  field: String!
  "Source : \`wikidata\`, \`commons\` (image), \`wikipedia\` (catégorie ou infobox de l'article : catégorie « bimode », effectif en service ; ou circulation en France d'une série non rattachée) ou \`contrib\` (valeur proposée par un contributeur et validée, référence \`contribution:<id>\`, \`url\` : source qu'il cite)."
  source: String!
  "Référence précise : élément et propriété Wikidata (ex. \`Q746572#P729\`), fichier Commons, catégorie Wikipédia, ou article et paramètre de l'infobox (ex. \`Régiolis#infobox:Type\`)."
  reference: String
  "Page de la source."
  url: String
  "Valeur d'origine quand VFF a converti l'unité (ex. \`2000\` pour 2000 ch), sinon nulle."
  original_amount: Float
  "Unité d'origine (élément Wikidata, ex. \`Q160857\` cheval-vapeur métrique), sinon nulle."
  original_unit: String
  "Clé du fournisseur dans le registre des droits de VFF."
  provider: String
  "Nom lisible du fournisseur."
  name: String
  "Licence de la source, telle que vérifiée par VFF."
  license: String
  "Solidité de la licence : \`verified\`, \`uncertain\` ou \`forbidden\`."
  license_status: String
}


"""
Lien entre une ligne et une série de matériel : la série a circulé (ou circule) sur la ligne
d'après une source citée. Jamais déduit d'un nom approchant : déclaration Wikidata (P3438), ou
lien posé dans une section « Matériel roulant », « Exploitation », « Desserte » de l'article
Wikipédia de la ligne, ou « Utilisation », « Services », « Carrière » de l'article de la série.
"""
type LineMaterialLink {
  "La ligne."
  line: Line!
  "La série de matériel."
  material: Material!
  """
  Provenance du lien : \`wikidata\`, \`wikipedia\`, \`contrib\` (proposé par des contributeurs avec leur
  source et validé par deux relecteurs), ou nulle pour un lien saisi dans VFF. Un même couple ligne ×
  série peut avoir un lien par provenance.
  """
  source: String
  "Permalien de la source la plus forte (révision Wikipédia \`oldid\` ou élément Wikidata) : à citer."
  source_url: String
  """
  Fiabilité : \`high\` (Wikidata, ou section « Matériel roulant » de l'article de la ligne),
  \`medium\` (autre section), \`low\` (à confirmer : article qui décrit plusieurs lignes de VFF à la
  fois, ou preuves qui portent toutes un doute, voir \`LineMaterialEvidence.doubt\`). Lien \`contrib\` :
  \`high\` dès qu'une contribution déclare un service régulier, \`low\` si toutes parlent d'une
  circulation exceptionnelle, d'essais ou d'un train de musée.
  """
  confidence: String
  "Ère VFF à laquelle le lien est rattaché, quand la source la date. Souvent nulle."
  era: Era
  "Début de la circulation saisi dans VFF : nul pour les liens importés, dont les dates lues dans les sources sont dans \`periods\`."
  from_date: Date
  "Fin de la circulation saisie dans VFF : nulle pour les liens importés (voir \`periods\`)."
  to_date: Date
  """
  Périodes de circulation de la série sur la ligne, datées par une source, de la plus ancienne à la
  plus récente. Seules les périodes sûres (\`high\`, \`medium\`) sont servies ; liste vide quand aucune
  source ne date la circulation.
  """
  periods: [LineMaterialPeriod!]!
  "Note libre (liens saisis dans VFF)."
  notes: String
  "Preuves du lien, les plus fortes d'abord (cinq au plus)."
  evidence: [LineMaterialEvidence!]!
  "Fournisseur et licence de la source du lien."
  provenance: Provenance!
}


"""
Période de circulation d'une série sur une ligne, lue dans une source datée : phrase d'un article
Wikipédia qui relie la série et la ligne (faits seulement, CC BY-SA 4.0, la phrase n'est jamais
servie) ou qualificatifs de date d'une déclaration Wikidata P3438 (CC0). Une date à l'année s'écrit
au 1er janvier, une date au mois au 1er du mois : lisez toujours la précision.
"""
type LineMaterialPeriod {
  "Début de la circulation (\`AAAA-MM-JJ\`), nul si la source ne donne que la fin (« jusqu'en 1990 »)."
  start_date: Date
  "Précision du début : \`day\`, \`month\` ou \`year\`. Nulle sans début."
  start_precision: String
  "Fin de la circulation (\`AAAA-MM-JJ\`), nulle si la source ne la donne pas : une fin nulle ne veut pas dire que la série y circule encore."
  end_date: Date
  "Précision de la fin : \`day\`, \`month\` ou \`year\`. Nulle sans fin."
  end_precision: String
  "Début de la portion parcourue, quand le titre de section de l'article la nomme (lieu tel qu'écrit)."
  section_from: String
  "Fin de la portion parcourue (voir \`section_from\`)."
  section_to: String
  "Fiabilité : \`high\` (chaque date au jour ou au mois) ou \`medium\` (une date à l'année). Les périodes \`low\` ne sont jamais servies."
  confidence: String!
  "Où le fait a été lu : \`line_article\` (article de la ligne), \`material_article\` (article de la série), \`wikidata\` ou \`contribution\` (proposée par un contributeur avec sa source, validée par deux relecteurs)."
  origin: String!
  "Fournisseur de la source : \`wikipedia\`, \`wikidata\` ou \`contrib\` (contribution validée)."
  source: String!
  "Titre de l'article Wikipédia lu, élément Wikidata de la ligne (\`Q…\`), ou titre et licence de la source d'une contribution."
  source_title: String
  "Révision de l'article lue (\`oldid\`), nulle pour Wikidata et les contributions."
  source_revision: Int
  "Permalien de la source (révision Wikipédia, élément Wikidata, adresse citée par le contributeur) : à citer. Nul seulement pour une contribution qui cite un ouvrage ou une archive papier par sa référence (\`source_title\`)."
  source_url: String
  "Fournisseur et licence de la source."
  provenance: Provenance!
}


"""
Une preuve d'un lien ligne × matériel. Les phrases citées sont des extraits de Wikipédia
(CC BY-SA 4.0) : citez l'article avec le permalien \`url\`.
"""
type LineMaterialEvidence {
  "\`wikipedia_line_article\` (lien vers la série dans l'article de la ligne), \`wikipedia_material_article\` (lien vers la ligne dans l'article de la série), \`wikidata\` ou \`contribution\` (contribution validée)."
  kind: String!
  "Provenance d'une preuve \`contribution\` : \`contribution:<id>\`."
  contribution: String
  "Titre ou référence de la source citée par une contribution (ex. « Livret Chaix d'été 1938, p. 112 »)."
  title: String
  "Nature de la source citée par une contribution : \`web\`, \`document\` (document numérisé) ou \`print\` (ouvrage ou archive papier)."
  source_kind: String
  "Licence du document cité par une contribution (\`public_domain\`, \`cc-by-sa-4.0\`, \`all_rights_reserved\`…), nulle pour une page web ou un ouvrage papier."
  license: String
  "Titre de l'article Wikipédia lu."
  article: String
  "Révision de l'article lue (identifiant \`oldid\`)."
  revid: Int
  "Titre de la section (« Section > Sous-section »), qui précise parfois la portion parcourue."
  section: String
  "Phrase (ou ligne de liste, de tableau) qui porte le lien."
  quote: String
  "Propriété Wikidata (\`P3438\`, véhicule habituellement utilisé)."
  property: String
  "Élément Wikidata de la ligne qui porte la déclaration."
  subject: String
  "Première année déclarée par Wikidata (qualificatif P580)."
  from_year: Int
  "Dernière année déclarée par Wikidata (qualificatif P582)."
  to_year: Int
  "Vrai si l'article décrit plusieurs lignes de VFF à la fois."
  shared_article: Boolean
  """
  Raison de douter que la série ait roulé en service sur la ligne d'après cette phrase, ou nul :
  \`order\` (commande, contrat), \`trial\` (essai, record), \`one_off\` (circulation exceptionnelle,
  spéciale, présentation), \`preserved\` (musée, préservation), \`possible\` (simple possibilité),
  \`positioning\` (acheminement vers un atelier), \`diverted\` (coupure, détournement), \`restricted\`
  (matériel réservé à une autre ligne), \`component\` (pièce reprise de la série), \`footnote\` (lien
  posé dans une note ou un passage sans source), \`section\` (section d'essais, de records ou de
  préservation). Un lien dont toutes les preuves portent un doute est en fiabilité \`low\`. Pour une
  contribution, la circulation déclarée : \`one_off\` (exceptionnelle), \`trial\` (essais), \`preserved\`
  (train de musée ou touristique), nul pour un service régulier.
  """
  doubt: String
  "Permalien de la preuve."
  url: String
}


"""
Un accident ou un événement grave du rail français : date et précision, nature, lieu, lignes
rattachées, bilan humain et sa source, résumé, sources ouvertes avec leur licence.

Faits recoupés entre Wikidata (CC0), Wikipédia (faits seulement, CC BY-SA 4.0 pour toute
citation) et le bilan général des enquêtes du BEA-TT (Licence Ouverte 2.0), par
\`vff:import-accidents\`. Un champ nul veut dire qu'aucune source consultée ne le donne : VFF
n'estime aucune valeur. Aucune victime n'est nommée.
"""
type Accident {
  "Identifiant interne de l'accident."
  id: ID!
  "Identifiant stable et lisible : date (selon sa précision) puis commune (ex. \`2013-07-12-bretigny-sur-orge\`)."
  slug: String!
  "Élément Wikidata (\`Q…\`), nul si l'accident n'y figure pas (accident connu par le seul BEA-TT)."
  wikidata_id: String
  "Nom usuel : libellé Wikidata, sinon nom construit par VFF (nature et commune)."
  name(
    "Langue : \`fr\` (par défaut), \`en\` ou \`de\`."
    lang: String = "fr"
  ): String!
  """
  Nature : \`collision\` (entre trains ou avec un obstacle sur la voie), \`derailment\`
  (déraillement), \`level_crossing\` (collision à un passage à niveau), \`fire\` (incendie),
  \`explosion\`, \`structure_failure\` (rupture d'ouvrage : pont, tunnel, mur, rail), \`attack\`
  (attentat), \`person_struck\` (personnes heurtées hors passage à niveau), \`runaway\` (dérive),
  \`incident\` (incident grave sans accident : quasi-collision, dépassement de vitesse,
  immobilisation), \`other\`.
  """
  kind: String!
  "Date de l'accident (\`AAAA-MM-JJ\`), à lire avec \`date_precision\` (au 1er du mois ou au 1er janvier si moins précise)."
  date: Date!
  "Précision de \`date\` : \`day\` (date exacte), \`month\` (mois et année), \`year\` (année seule)."
  date_precision: String!
  "Heure locale (\`HH:MM\`) quand une source la donne."
  time: String
  "Commune actuelle (contours IGN ADMIN EXPRESS) où se trouve le lieu."
  commune: String
  "Code INSEE de la commune."
  commune_insee: String
  "Code INSEE du département actuel (\`2A\`, \`2B\` pour la Corse)."
  department_code: String
  "Latitude WGS 84 du lieu, en degrés décimaux ; nulle si le lieu est inconnu."
  lat: Float
  "Longitude WGS 84 du lieu, en degrés décimaux ; nulle si le lieu est inconnu."
  lng: Float
  """
  D'où vient la position : \`exact\` (coordonnées données par la source), \`level_crossing\`
  (passage à niveau cité, jeu SNCF Réseau), \`station\` (gare ou lieu cité), \`commune\` (centre
  de la commune : lieu exact inconnu). Nulle si le lieu est inconnu.
  """
  location_precision: String
  "Nombre de morts, selon \`toll_source\` ; nul si inconnu (0 : aucun mort)."
  deaths: Int
  "Nombre de blessés ; nul si inconnu. Les sources comptent parfois différemment : voir \`review_status\`."
  injuries: Int
  "Fournisseur du bilan retenu : \`bea-tt\` (enquête officielle, préférée), \`wikidata\`, \`wikipedia\`."
  toll_source: String
  "Lien qui appuie le bilan retenu (référence Wikidata, permalien Wikipédia, rapport du BEA-TT)."
  toll_source_url: String
  "Résumé rédigé par VFF à partir des faits retenus, jamais recopié d'une source."
  summary(
    "Langue : \`fr\` (par défaut), \`en\` ou \`de\`."
    lang: String = "fr"
  ): String!
  "Article de Wikipédia sur l'accident dans la langue demandée, nul s'il n'existe pas."
  wikipedia_url(
    "Langue : \`fr\` (par défaut), \`en\` ou \`de\`."
    lang: String = "fr"
  ): String
  "\`ok\` : date et lieu confirmés par une source primaire (enquête du BEA-TT) ou par deux sources indépendantes (voir \`verification\`) ; \`to_review\` quand un point reste incertain (voir \`review_notes\`). Le rattachement à une ligne porte sa propre confiance."
  review_status: String!
  """
  Vérification de la date et du lieu (\`vff:verify-accidents\`) : \`rule\` (\`primary_source\`, \`two_sources\`,
  \`wikidata_wikipedia\`, \`wikipedia_editions\` ou absente), \`date\` et \`place\` (valeur retenue ; sources qui concordent \`confirmed_by\`,
  qui divergent \`disagreeing\`, communes voisines \`nearby\`, chacune avec \`provider\`, \`role\`, \`url\` et la date de
  lecture \`retrieved_at\`), \`families\`, \`kind\` (nature lue dans une source), \`aliases\` (autres éléments Wikidata du
  même accident, gardés avec leurs sources). Nul si la fiche n'a pas été vérifiée.
  """
  verification: JSON
  "Date de la dernière vérification, nulle si la fiche n'a pas été vérifiée."
  verified_at: DateTime
  "Points incertains : bilans ou dates qui diffèrent selon les sources, lieu vague, ligne incertaine."
  review_notes: [AccidentReviewNote!]!
  "Trains et matériel impliqués, quand les sources les nomment."
  trains: [AccidentTrain!]!
  "Lignes rattachées, avec la portion, le PK estimé, la méthode et la confiance (ordre non garanti : la principale a \`is_primary\`)."
  lines: [AccidentLineLink!]!
  "Gare VFF la plus proche du lieu, ouverte à la date quand ses dates sont connues (10 km au plus)."
  nearest_station: Station
  "Distance du lieu à \`nearest_station\`, en mètres."
  nearest_station_distance_m: Int
  "Sources consultées, avec leur licence et les faits lus dans chacune."
  sources: [AccidentSource!]!
}


"Rattachement d'un accident à une ligne VFF, sur le tracé de l'époque de l'accident."
type AccidentLineLink {
  "Ligne VFF (nulle si elle a disparu de VFF depuis l'import)."
  line: Line
  "Vrai pour la ligne retenue comme lieu de l'accident ; faux pour une ligne au même endroit (tronc commun, jonction) ou citée par une source."
  is_primary: Boolean!
  "Ère dont le tracé a servi au rattachement (décennie qui encadre la date)."
  era_year: Int
  "Distance du lieu au tracé, en mètres."
  distance_m: Int
  "Point kilométrique estimé, en mètres : PK du passage à niveau cité, sinon interpolé entre les deux gares qui encadrent le lieu."
  pk_m: Int
  "Le même PK en notation ferroviaire \`km+mmm\`."
  pk_label: String
  "Portion : gare d'un côté du lieu. Seule renseignée (\`section_to\` nulle) : accident en gare. Nulle si le lieu n'est connu qu'à la commune près."
  section_from: Station
  "Portion : gare de l'autre côté du lieu."
  section_to: Station
  "Méthodes : \`proximity\` (tracé de l'époque), \`wikidata\` (ligne citée par Wikidata), \`wikipedia\` (article de ligne cité), \`level_crossing\` (ligne du passage à niveau)."
  method: [String!]!
  "Confiance : \`high\`, \`medium\` ou \`low\` (commune seule, ligne la plus proche : à vérifier)."
  confidence: String!
  "Score de 0 à 1 : proximité du tracé et concordance des sources."
  score: Float!
  "Titre de l'article de ligne de Wikipédia cité par la source (ex. \`Ligne de Paris-Lyon à Marseille-Saint-Charles\`). Nul si aucune source ne cite cette ligne."
  cited_as: String
  "Permalien de la source qui cite la ligne (révision lue de la page de Wikipédia). Nul si aucune source ne cite cette ligne."
  cited_url: String
}


"Une source consultée pour un accident."
type AccidentSource {
  "Fournisseur : \`wikidata\`, \`wikipedia\`, \`bea-tt\` (clé du registre des droits de VFF)."
  provider: String!
  "\`data\` (données structurées), \`article\` (encyclopédie), \`report\` (rapport d'enquête), \`inventory\` (bilan des enquêtes)."
  role: String!
  "Titre de la source (intitulé de l'enquête, titre de l'article, libellé de l'élément)."
  title: String!
  "Lien vers la source (permalien de la révision lue pour Wikipédia)."
  url: String!
  "Licence de la source (CC0 1.0, CC BY-SA 4.0, Licence Ouverte 2.0)."
  license: String!
  "Lien vers le texte de la licence."
  license_url: String
  "Révision lue (oldid Wikipédia, lastrevid Wikidata), en texte."
  revision: String
  "Date de consultation."
  retrieved_at: DateTime!
  "Faits lus dans cette source (date, morts, blessés, coordonnées…), objet JSON sérialisé."
  facts: String
}


"Un train ou un matériel impliqué dans un accident."
type AccidentTrain {
  "Nom de service (\`TER\`, \`TGV\`, \`Intercités\`, \`RER C\`…), invariable."
  label: String
  "Catégorie quand le service n'est pas nommé : \`passenger\`, \`freight\`, \`works\` (travaux), \`test\` (essai)."
  category: String
  "Numéro de train, quand il est cité."
  number: String
  "Matériel roulant (en français), quand il est cité."
  material: String
  "Exploitant."
  operator: String
  "Fournisseur qui cite ce train : \`wikidata\`, \`wikipedia\` ou \`bea-tt\`."
  source: String
}


"Un point incertain d'une fiche d'accident."
type AccidentReviewNote {
  "Code stable : \`toll_mismatch\`, \`toll_ambiguous\`, \`date_mismatch\`, \`location_commune_only\`, \`commune_not_found\`, \`line_uncertain\`, \`no_line\`…"
  code: String!
  "Explication en français."
  label: String!
  "Valeurs en cause (ex. « blessés : Wikidata 14, Wikipédia 18 »)."
  detail: String!
}


"""
Passage à niveau du réseau ferré national, tel que publié par SNCF Réseau (jeu
\`liste-des-passages-a-niveau\`, ODbL : mention « SNCF Réseau », partage à l'identique des bases
dérivées). État à la date de publication, sans date d'ouverture ni de suppression.
"""
type LevelCrossing {
  "Identifiant de l'installation fixe dans les bases de SNCF Réseau (ex. \`30000010436\`)."
  id: ID!
  "Libellé SNCF Réseau, en général « PN » et le numéro du passage sur la ligne (ex. \`PN 32\`)."
  label: String
  """
  Classe SNCF Réseau : 10 à 16 gardés, 17 à 19 barrières automatiques (SAL 2, SAL 2B, SAL 4),
  20 sans barrières ni signalisation lumineuse, 21 sans barrières avec SAL 0, 31 et 32 piétons,
  41 à 46 privés, 0 PN secondaire. Nulle si la source ne la donne pas.
  """
  class: Int
  "Définition de la classe, rédigée par VFF d'après la description du jeu. Nulle si la classe est absente ou hors de la liste officielle."
  class_label(
    "Langue : \`fr\` (par défaut), \`en\` ou \`de\`."
    lang: String = "fr"
  ): String
  """
  Groupe : \`automatic\` (17 à 19), \`guarded\` (10 à 16), \`no_barrier\` (20, 21), \`pedestrian\`
  (31, 32), \`private\` (41 à 46), \`secondary\` (0 : passage compté sur une autre ligne).
  """
  group: String
  "Point kilométrique officiel, en mètres (ex. \`112866\`), nul s'il est inconnu."
  pk_m: Int
  "Point kilométrique en notation \`km+mmm\` (ex. \`112+866\`)."
  pk_label: String
  "Commune, telle que l'écrit SNCF Réseau."
  commune: String
  """
  Voie franchie, libellé brut de SNCF Réseau (\`VC\` voie communale, \`CD 106-0\` route
  départementale, \`CR\` chemin rural…). Pour un PN secondaire : le PN qui porte la classe.
  """
  crossed_way: String
  "Code de ligne SNCF Réseau à 6 chiffres (ex. \`070000\`)."
  line_code: String!
  "Ligne VFF qui porte ce code, nulle si le code n'a pas de fiche."
  line: Line
  "Latitude WGS 84, au centre du croisement (à moins d'un mètre pour plus de 90 % des PN selon SNCF Réseau)."
  lat: Float!
  "Longitude WGS 84."
  lng: Float!
  "Fournisseur et licence de la donnée (SNCF Réseau, ODbL)."
  provenance: Provenance!
}


"""
Profil en long d'une ligne : altitude le long du parcours, de la première gare (plus petit PK)
vers la dernière. Les distances sont mesurées le long du parcours depuis son origine, en mètres :
ce ne sont pas des PK officiels (voir \`LineProfileStation.pk_m\`). Altitudes en mètres (NGF-IGN69).
"""
type LineProfile {
  "Année de l'ère dont le tracé a été échantillonné (2030 = aujourd'hui)."
  era_year: Int!
  "Pas d'échantillonnage le long du parcours, en mètres (100 à 250 selon la longueur)."
  step_m: Int!
  "Longueur du parcours profilé, lacunes comprises, en mètres."
  length_m: Int!
  "Longueur totale du tracé de la ligne à cette ère, en mètres (épis et raccordements compris)."
  trace_length_m: Int!
  "Altitudes extrêmes, dénivelés cumulés et déclivité la plus forte."
  summary: LineProfileSummary!
  "Points du profil dans l'ordre du parcours."
  points: [LineProfilePoint!]!
  "Tunnels et ponts de la BD TOPO sous le parcours."
  structures: [LineProfileStructure!]!
  "Nombre de points de chaque origine d'altitude."
  altitude_sources: LineProfileAltitudeSources!
  "Lacunes du tracé (aucune altitude entre leurs bornes)."
  gaps: [LineProfileGap!]!
  "Gares de la ligne placées sur le profil (à moins de 500 m du parcours)."
  stations: [LineProfileStation!]!
  "Date du calcul (ISO 8601)."
  computed_at: String!
  "Provenance et licence des altitudes."
  provenance: Provenance!
}


"Chiffres d'un profil en long. Valeurs indicatives : quelques ‰ d'écart avec la déclivité officielle sont courants."
type LineProfileSummary {
  "Altitude la plus basse du parcours, en mètres."
  min_altitude_m: Float!
  "Altitude la plus haute du parcours, en mètres."
  max_altitude_m: Float!
  "Dénivelé positif cumulé dans le sens du parcours, en mètres (variations de moins de 3 m ignorées)."
  ascent_m: Float!
  "Dénivelé négatif cumulé dans le sens du parcours, en mètres (variations de moins de 3 m ignorées)."
  descent_m: Float!
  "Déclivité la plus forte, en ‰ (mètres par kilomètre) : pente moyenne par moindres carrés sur 1 km."
  max_grade_permil: Float!
  "Début de la fenêtre de la déclivité la plus forte, en mètres le long du parcours."
  max_grade_from_m: Int!
  "Fin de la fenêtre de la déclivité la plus forte, en mètres le long du parcours."
  max_grade_to_m: Int!
  "\`up\` : rampe dans le sens du parcours ; \`down\` : pente."
  max_grade_direction: String!
}


"Un point du profil."
type LineProfilePoint {
  "Distance le long du parcours depuis son origine, en mètres."
  distance_m: Int!
  "Altitude en mètres, arrondie au décimètre ; nulle sans valeur (hors de France, lacune)."
  altitude_m: Float
}


"Un ouvrage d'art sous le parcours (BD TOPO)."
type LineProfileStructure {
  "Début, en mètres le long du parcours."
  from_m: Int!
  "Fin, en mètres le long du parcours."
  to_m: Int!
  "\`tunnel\` ou \`bridge\` (pont, viaduc)."
  kind: String!
}


"Origine des altitudes d'un profil, en nombre de points."
type LineProfileAltitudeSources {
  "Altitude de la voie dans la BD TOPO (géométrie 3D)."
  bdtopo: Int!
  "Altitude du terrain (RGE ALTI), faute de voie dans la BD TOPO."
  rge_alti: Int!
  "Interpolée (tunnel ou pont sans altitude, point aberrant, petit trou)."
  interpolated: Int!
  "Sans altitude (hors de France, trou trop long)."
  missing: Int!
}


"Une lacune du tracé dans un profil en long."
type LineProfileGap {
  "Début, en mètres le long du parcours."
  from_m: Int!
  "Fin, en mètres le long du parcours."
  to_m: Int!
}


"Une gare placée sur un profil en long."
type LineProfileStation {
  "Identifiant (slug) de la gare."
  slug: String!
  "Nom de la gare."
  name: String!
  "Position le long du parcours, en mètres."
  distance_m: Int!
  "Altitude du profil à la gare, en mètres ; nulle sans valeur."
  altitude_m: Float
  "Point kilométrique officiel de la gare sur la ligne, en mètres ; nul s'il est inconnu."
  pk_m: Int
}


"""
Voie verte nommée sur l'ancienne emprise d'une ligne : les ways OpenStreetMap de la ligne qui portent le
même nom (celui de leur itinéraire cyclable OSM s'il existe, sinon celui du way ; les ways sans nom forment
un groupe au nom nul). Données © contributeurs OpenStreetMap, ODbL 1.0.
"""
type LineGreenway {
  "Nom de la voie verte (itinéraire cyclable OSM, ou nom du way). Nul pour les ways sans nom."
  name: String
  "Longueur en mètres qui suit l'ancienne emprise de la ligne, tous les ways du groupe ensemble : ce n'est pas la longueur de la voie verte entière."
  shared_length_m: Int!
  "Nombre de ways OSM du groupe."
  way_count: Int!
  "Année d'ouverture si OpenStreetMap la donne (\`start_date\` du way ou de l'itinéraire, 1970 ou après), sinon nulle : elle n'est jamais devinée."
  opened_year: Int
  "Identifiant de l'itinéraire cyclable OSM (relation \`route=bicycle\` ou \`route=mtb\`), nul si les ways n'en sont membres d'aucun."
  osm_relation_id: Int
  "Lien vers l'itinéraire OSM (relation) s'il existe, sinon vers le way qui suit le plus longtemps l'emprise."
  osm_url: String!
  "Ways OSM de la voie verte sur cette ligne, le plus long sur l'emprise d'abord."
  ways: [LineGreenwayWay!]!
  "Mention à reproduire en réutilisant ces données : « © contributeurs OpenStreetMap, ODbL 1.0 » avec le lien de la licence."
  attribution: String!
  "Fournisseur et licence de la donnée (OpenStreetMap, ODbL)."
  provenance: Provenance!
}


"Type d'un document d'archives, d'après la notice de la BnF."
enum ArchiveDocumentType {
  "Texte imprimé : livre, brochure, mémoire, rapport, guide."
  MONOGRAPH
  "Document cartographique : carte, plan, profil en long."
  MAP
  "Image fixe : photographie, estampe, affiche, carte postale."
  IMAGE
  "Manuscrit."
  MANUSCRIPT
}


"""
Document d'archives numérisé en accès libre dans Gallica, rattaché à une ligne d'après le titre de sa
notice au catalogue général de la BnF (\`vff:import-gallica\`). Seules les métadonnées de la notice
(Licence Ouverte) sont gardées par VFF, avec les identifiants ark ; le document lui-même se consulte
dans Gallica, et sa réutilisation suit les conditions d'utilisation de Gallica (non commerciale libre
avec la mention « Source gallica.bnf.fr / BnF », commerciale soumise à redevance).
"""
type LineArchiveDocument {
  "Identifiant ark du document numérisé dans Gallica (ex. \`ark:/12148/bpt6k1192344q\`)."
  gallica_ark: String!
  "Lien vers le document dans Gallica."
  gallica_url: String!
  "Identifiant ark de la notice au catalogue général de la BnF (ex. \`ark:/12148/cb315821610\`), nul s'il manque."
  catalogue_ark: String
  "Lien vers la notice au catalogue général de la BnF."
  catalogue_url: String
  "Titre propre de la notice, suivi de son premier complément après « : »."
  title: String!
  "Type de document : \`monograph\` (texte imprimé), \`map\` (carte, plan), \`image\` (image fixe) ou \`manuscript\`."
  type: String!
  "Auteurs, illustrateurs, éditeurs scientifiques ou collectivités, tels que la notice les écrit (« Villemandy, Alfred de ») ; quatre au plus."
  authors: [String!]!
  "Date telle que la notice l'imprime (« 1860 », « [ca 1850] », « 1er novembre 1873 »), nulle si la notice n'en donne pas."
  date_label: String
  "Année du document (première année d'un intervalle), nulle si elle est inconnue."
  year: Int
  "Dernière année d'un intervalle (\`date_precision\` = \`range\`), nulle sinon."
  year_to: Int
  "Jour exact (précision \`day\`) ou premier jour du mois (précision \`month\`), quand la notice le donne."
  exact_date: Date
  "Précision de la date : \`day\`, \`month\`, \`year\`, \`circa\` (date approchée), \`range\` (entre \`year\` et \`year_to\`), \`before\` (au plus tard \`year\`, « Avant 1885 »), \`after\` (au plus tôt \`year\`) ou \`unknown\`."
  date_precision: String!
  "Cote de l'original à la BnF (ex. \`V-54984\`, \`GE C-4520\`), nulle si la notice n'en donne pas."
  shelfmark: String
  "Score de rattachement à la ligne, de 9 (seuil) à 11."
  score: Int!
  "Raisons du score, point par point : \`rail_route\` (4), \`route\` (3) ou \`two_places\` (1, plus \`only_line_places\` (1) si le titre ne nomme aucun lieu hors de la ligne), \`ends\` (2) ou \`secondary_mention\` (0), \`rail_word\` (2) ou \`rail_hint\` (1), \`date_in_life\` (2), \`date_project\` (1), \`date_after_closure\` (1), \`date_unknown\` (0) ou \`date_doubtful\` (0, année imprimée dans le titre à plus de 40 ans de la date de la notice), \`doc_type\` (1)."
  score_reasons: [ArchiveMatchReason!]!
  "Lieux de la ligne reconnus dans le titre."
  matched_places: [String!]!
  "Dernière mise à jour de la notice à la BnF."
  record_updated_on: Date
  "Date de récupération de la notice par VFF (à citer avec la source)."
  fetched_at: DateTime!
  "Mention de source à reproduire : « Source : gallica.bnf.fr / BnF », Licence Ouverte pour les métadonnées."
  attribution: String!
  "Fournisseur et licence de la donnée (Gallica, BnF)."
  provenance: Provenance!
}


"Une raison du score de rattachement d'un document d'archives."
type ArchiveMatchReason {
  "Code de la raison : \`rail_route\` (voie ferrée « de X à Y »), \`route\` (trajet « de X à Y »), \`two_places\`, \`only_line_places\`, \`ends\`, \`secondary_mention\`, \`rail_word\`, \`rail_hint\`, \`date_in_life\`, \`date_project\`, \`date_after_closure\`, \`date_unknown\`, \`date_doubtful\` ou \`doc_type\`."
  code: String!
  "Points apportés au score."
  points: Int!
}


"Un way OpenStreetMap d'une voie verte, avec sa part sur l'ancienne emprise de la ligne."
type LineGreenwayWay {
  "Identifiant du way OSM (https://www.openstreetmap.org/way/<id>), rendu en chaîne : les identifiants OSM approchent la limite d'un entier GraphQL."
  osm_way_id: ID!
  "Lien vers le way sur openstreetmap.org."
  osm_url: String!
  "Nom du way, nul s'il n'en a pas."
  name: String
  "Type de chemin OSM : \`cycleway\`, \`path\`, \`footway\`, \`track\`, \`bridleway\` ou \`pedestrian\`."
  highway: String
  "Longueur du way entier, en mètres."
  way_length_m: Int!
  "Longueur du way qui suit l'ancienne emprise de la ligne, en mètres."
  shared_length_m: Int!
  "Part du way qui suit l'ancienne emprise, en pourcent (50 à 100 : un way moins présent n'est pas rattaché)."
  shared_percent: Int!
  "Année d'ouverture donnée par OpenStreetMap (1970 ou après), nulle sinon."
  opened_year: Int
}


"""
Mise en service d'un système de signalisation, datée par l'article Wikipédia de la ligne : un fait par
système, date et portion. Le KVB y est un contrôle de vitesse (\`role: speed_control\`), jamais un cantonnement.
"""
type SignallingEvent {
  "Système : \`BAL\`, \`BAPR\`, \`BM\` (block manuel), \`CT\` (cantonnement téléphonique), \`TVM-300\`, \`TVM-430\`, \`TVM\`, \`ETCS_L1\`, \`ETCS_L2\`, \`ETCS\` (niveau non précisé) ou \`KVB\`."
  system: String!
  "Rôle : \`block\` (cantonnement), \`cab\` (signalisation en cabine, TVM et ETCS : cantonnement et contrôle à la fois) ou \`speed_control\` (contrôle de vitesse : KVB)."
  role: String!
  "Date de la mise en service. Lisez-la avec \`date_precision\` : le jour et le mois valent \`01\` quand l'article ne les donne pas."
  date: Date!
  "Précision de \`date\` : \`day\`, \`month\` ou \`year\`."
  date_precision: String!
  "Fiabilité : \`high\` (jour ou mois donné) ou \`medium\` (année seule, date reprise d'une phrase voisine comme « la même année » ou « à cette occasion », ou date déduite de l'ouverture : \`origin\` vaut \`opening\`). Lisez la précision dans \`date_precision\`. Les faits douteux ne sont pas exposés."
  reliability: String!
  "Où l'article donne le fait : \`text\` (phrase), \`list\` (liste à puces), \`table\` (ligne d'un tableau), \`infobox\` (champ « signalisation » daté) ou \`opening\` (TVM d'une ligne à grande vitesse, en service dès l'ouverture : date de la première mise en service de l'infobox)."
  origin: String!
  "Portée : \`whole_line\` (l'article dit toute la ligne), \`section\` (portion nommée) ou \`unspecified\` (l'article ne dit pas)."
  scope: String!
  "Portion concernée, nulle quand \`scope\` n'est pas \`section\`."
  section: SignallingEventSection
  "Article Wikipédia et révision d'où vient le fait."
  source: SignallingEventSource!
  "Mention à reproduire en réutilisant ces données (CC BY-SA 4.0)."
  attribution: String!
  "Fournisseur et licence de la donnée (Wikipédia)."
  provenance: Provenance!
}


"Portion d'une mise en service de signalisation, telle que l'article la donne, et sa place sur la ligne quand elle est connue."
type SignallingEventSection {
  "Lieu de départ tel qu'écrit dans l'article, nul s'il n'est pas cité."
  from: String
  "Lieu d'arrivée tel qu'écrit dans l'article, nul s'il n'est pas cité."
  to: String
  "Placement : \`stations\` (les deux gares d'extrémité trouvées sur la ligne, avec leur PK calé), \`article_pk\` (PK donnés par l'article) ou \`unlocated\` (lieux introuvables ou ambigus : aucun PK n'est inventé)."
  location: String!
  "PK de début de la portion placée, en mètres (numérotation de SNCF Réseau), nul si elle n'est pas placée."
  pk_start_m: Int
  "PK de fin de la portion placée, en mètres, nul si elle n'est pas placée."
  pk_end_m: Int
  "Gare retenue pour le lieu de départ (\`location: stations\`), nulle sinon."
  from_station: SignallingEventStation
  "Gare retenue pour le lieu d'arrivée (\`location: stations\`), nulle sinon."
  to_station: SignallingEventStation
}


"Gare d'extrémité d'une portion de signalisation placée sur la ligne."
type SignallingEventStation {
  "Identifiant (slug) de la gare, comme dans \`station(slug:)\`."
  slug: String!
  "Nom de la gare."
  name: String!
  "PK de la gare sur cette ligne, en mètres."
  pk_m: Int
}


"Source d'une mise en service de signalisation."
type SignallingEventSource {
  "Fournisseur : \`wikipedia\`."
  provider: String!
  "Titre de l'article."
  title: String!
  "Permalien de la révision lue."
  url: String!
  "Numéro de la révision lue."
  revision: Int!
  "Courte phrase de l'article (200 caractères au plus), pour relire le fait."
  excerpt: String!
}


"Nombre de passages à niveau d'un groupe."
type LevelCrossingCount {
  "Groupe (voir \`LevelCrossing.group\`), ou \`unknown\` pour une classe que la source ne donne pas."
  group: String!
  "Nombre de passages à niveau."
  count: Int!
}


"Filtres de la requête \`levelCrossings\`. Tous sont facultatifs et se combinent (ET)."
input LevelCrossingFilter {
  "Slug d'une ligne (ex. \`rfn-70000\`). Ligne inconnue : erreur de validation."
  line: String
  "Groupe : \`automatic\`, \`guarded\`, \`no_barrier\`, \`pedestrian\`, \`private\`, \`secondary\`."
  group: String
  "Classe exacte : 0, 10 à 21, 31, 32, 41 à 46."
  class: Int
  "Partie du nom de la commune, sans accents ni capitales (2 à 100 caractères)."
  commune: String
  "Emprise \`ouest,sud,est,nord\` en degrés WGS 84 (ex. \`3.5,49.0,3.7,49.1\`)."
  bbox: String
}


"Filtres de la requête \`accidents\`. Tous sont facultatifs et se combinent (ET)."
input AccidentFilter {
  "Recherche sans accents ni capitales dans le nom (toutes langues) et la commune (100 caractères au plus)."
  q: String
  "À partir de cette année, incluse (1800 à 2100)."
  from: Int
  "Jusqu'à cette année, incluse (1800 à 2100, au moins égale à \`from\`)."
  to: Int
  "Slug d'une ligne : accidents rattachés à cette ligne. Ligne inconnue : erreur de validation."
  line: String
  "Code INSEE du département actuel du lieu (ex. \`91\`, \`2A\`)."
  department: String
  "Code INSEE de la région (\`53\` Bretagne, \`11\` Île-de-France…, liste : requête \`regions\`) : accidents dont le lieu est dans l'un de ses départements."
  region: String
  "Slug d'une gare : accidents dont elle est la gare la plus proche du lieu (10 km au plus). Gare inconnue : erreur de validation."
  station: String
  "Nature (voir \`Accident.kind\`)."
  kind: String
  "\`ok\` ou \`to_review\`."
  review: String
  "Bilan d'au moins ce nombre de morts (exclut les bilans inconnus)."
  min_deaths: Int
  "Vrai : accidents rattachés à une ligne ; faux : sans ligne."
  has_line: Boolean
  "Tri : \`date\` (par défaut), \`-date\`, \`deaths\`, \`-deaths\`. Bilan inconnu en dernier."
  sort: String
}


"Une section d'itinéraire entre deux gares consécutives du trajet."
type RouteEdge {
  "Slug de la gare de départ de la section."
  from_slug: String!
  "Slug de la gare d'arrivée de la section."
  to_slug: String!
  "Identifiant interne de la ligne principalement empruntée sur la section."
  line_id: Int!
  "Longueur de la section, en mètres."
  length_m: Int!
  "Nombre de gares fermées (à l'ère de calcul) traversées sans arrêt entre \`from\` et \`to\`."
  skipped_count: Int!
}


"Une gare traversée par l'itinéraire, dans l'ordre du trajet."
type RouteStop {
  "Slug de la gare."
  slug: String!
  "Nom de la gare."
  name: String!
  "Latitude WGS 84, en degrés décimaux."
  lat: Float!
  "Longitude WGS 84, en degrés décimaux."
  lng: Float!
}


"Une ligne empruntée par l'itinéraire, dans l'ordre du trajet."
type RouteLine {
  "Slug de la ligne (nul pour un raccordement sans fiche)."
  line_slug: String
  "Nom de la ligne."
  line_name: String
  "Code SNCF Réseau de la ligne, à 6 chiffres."
  line_code: String
  "Longueur parcourue sur cette ligne, en mètres."
  length_m: Int!
  "Gare où le trajet prend cette ligne (nul : à un raccordement, hors gare)."
  from_slug: String
  "Gare où le trajet quitte cette ligne (nul : à un raccordement, hors gare)."
  to_slug: String
}


"""
Itinéraire ferroviaire entre deux gares, calculé sur le réseau **en service à l'ère retenue**
(gares ouvertes et voies exploitées au 31/12 de son année) : le plus court chemin, les voies
sans service voyageurs étant pénalisées.
"""
type Route {
  "Année de l'ère dont le réseau a servi au calcul (2030 = réseau actuel)."
  era_year: Int
  "Longueur totale du trajet, en mètres."
  distance_m: Int!
  "Durée **indicative** en heures, calculée à 100 km/h de moyenne : ce n'est pas un horaire."
  duration_h: Float!
  "Nombre de sections gare à gare."
  hops: Int!
  "Gares traversées, dans l'ordre, de la gare de départ à la gare d'arrivée."
  stations: [RouteStop!]!
  "Lignes empruntées, dans l'ordre du trajet."
  lines: [RouteLine!]!
  "Détail de chaque section gare à gare."
  edges: [RouteEdge!]!
}


"Gare de départ ou d'arrivée d'une comparaison de trajets."
type RouteEnd {
  "Slug de la gare."
  slug: String!
  "Nom de la gare."
  name: String!
}


"Le trajet à l'une des deux époques comparées : l'itinéraire, ou la raison de son absence."
type RouteAtEra {
  "Année demandée (nulle : réseau actuel)."
  requested_year: Int
  "Année de l'ère dont le réseau a servi au calcul (2030 = réseau actuel)."
  era_year: Int!
  "Vrai si l'ère est le réseau d'aujourd'hui."
  era_is_current: Boolean!
  "Itinéraire à cette époque, ou \`null\` si le trajet y est impossible."
  route: Route
  """
  Raison de l'absence de trajet, ou \`null\` : \`no_path\` (gares non reliées par le réseau en
  service) ou \`station_not_in_network\` (gare fermée ou hors du réseau exploité).
  """
  unavailable_reason: String
  "Message en français qui explique l'absence de trajet, ou \`null\`."
  unavailable_message: String
}


"Une ligne empruntée par l'un des deux trajets comparés, longueurs parcourues additionnées."
type RouteLineChange {
  "Slug de la ligne (nul pour un raccordement sans fiche)."
  line_slug: String
  "Nom de la ligne."
  line_name: String
  "Code SNCF Réseau de la ligne, à 6 chiffres."
  line_code: String
  "Longueur parcourue sur cette ligne, en mètres."
  length_m: Int!
  """
  Pour une ligne d'un seul des deux trajets : ce qu'elle était à l'autre époque. \`open\` (en
  service sur au moins 90 % de son tracé), \`partly_open\` (en partie), \`closed\` (fermée, voie en
  place) ou \`none\` (déposée, pas encore ouverte ou absente de la carte). Nul pour une ligne
  commune aux deux trajets ou sans slug.
  """
  other_era: String
}


"Ce qui change entre les deux trajets."
type RouteDifferences {
  """
  \`both\` (trajet possible aux deux époques), \`lost\` (devenu impossible), \`gained\` (devenu
  possible) ou \`neither\` (impossible aux deux).
  """
  outcome: String!
  "Écart de longueur en mètres, seconde époque moins première (nul si un trajet manque)."
  distance_change_m: Int
  "Même écart, en pourcentage de la longueur à la première époque."
  distance_change_percent: Float
  "Vrai si les deux trajets passent par les mêmes gares."
  same_path: Boolean!
  "Lignes empruntées aux deux époques (longueurs de la seconde)."
  shared_lines: [RouteLineChange!]!
  "Lignes empruntées seulement à la première époque."
  lines_only_earlier: [RouteLineChange!]!
  "Lignes empruntées seulement à la seconde époque."
  lines_only_later: [RouteLineChange!]!
  "Nombre de gares communes aux deux trajets."
  shared_stations: Int!
}


"""
Un même trajet (gare A → gare B) à deux époques, rangées dans l'ordre chronologique, et le
résumé de leurs différences.
"""
type RouteComparison {
  "Gare de départ (nulle si aucun des deux trajets n'existe et que \`from\` n'est pas un slug)."
  from: RouteEnd
  "Gare d'arrivée (mêmes règles)."
  to: RouteEnd
  "Le trajet à la plus ancienne des deux époques."
  earlier: RouteAtEra!
  "Le trajet à la plus récente des deux époques."
  later: RouteAtEra!
  "Résumé des différences."
  differences: RouteDifferences!
}


"Longueur (en km) rattachée à un code : mode de cantonnement, système de contrôle, trafic, tension…"
type KmByCode {
  "Code (ex. \`BAL\`, \`KVB\`, \`mixed\`)."
  code: String!
  "Longueur en kilomètres (une décimale dans les fiches, arrondie à l'unité dans les statistiques d'ère)."
  km: Float!
}


"""
Comparaison de deux ères de la carte : ce qui a changé sur le réseau entre deux décennies.
Les longueurs additionnent les tronçons de la carte (voies parallèles d'une même ligne
comprises, arrondies au kilomètre) : un ordre de grandeur, pas le kilométrage officiel. Les
tronçons sont appariés d'une ère à l'autre par ligne et par géométrie identique. Chaque ère est
un instantané au 31/12 de son année ; 2030 est le réseau d'aujourd'hui.
"""
type EraComparison {
  "Année de la plus ancienne des deux ères comparées."
  from: Int!
  "Année de la plus récente des deux ères comparées."
  to: Int!
  "Changements entre \`from\` et \`to\`, par catégorie."
  changes: EraChanges!
  "Longueur en service à chacune des deux ères, en km arrondis."
  open_km: EraOpenKm!
  "Liens utiles : cette comparaison en REST, sur la carte, tuiles vectorielles, sources."
  links: EraComparisonLinks!
}


"Les quatre catégories de changement entre deux ères."
type EraChanges {
  "Ouvert ou rouvert entre les deux ères : en service à \`to\`, pas à \`from\`."
  opened: EraChangeTotal!
  "Fermé à \`to\` voie en place (voie de service comprise), alors qu'il ne l'était pas à \`from\`."
  closed: EraChangeTotal!
  "En service ou fermé à \`from\`, déposé ou disparu à \`to\`."
  removed: EraChangeTotal!
  "En service aux deux ères."
  kept: EraChangeTotal!
}


"Longueur et nombre de lignes d'une catégorie de changement."
type EraChangeTotal {
  "Somme des tronçons concernés, en kilomètres arrondis (voies parallèles d'une même ligne comprises)."
  km: Int!
  "Nombre de lignes dont au moins un tronçon est concerné (une ligne fermée en partie compte dans \`closed\` et dans \`kept\`)."
  lines: Int!
}


"Longueur en service à chacune des deux ères comparées."
type EraOpenKm {
  "Longueur en service à l'ère \`from\`, en km arrondis."
  from: Int!
  "Longueur en service à l'ère \`to\`, en km arrondis."
  to: Int!
}


"Liens associés à une comparaison d'ères."
type EraComparisonLinks {
  "Adresse de cette comparaison dans l'API REST."
  self: String!
  "La comparaison sur la carte du site."
  map: String!
  "Gabarit des tuiles vectorielles MVT de la comparaison (\`{z}/{x}/{y}\` à remplacer, MapLibre)."
  tiles: String!
  "Registre des sources et de leurs licences."
  sources: String!
}


"Nombre d'objets rattachés à un code (ex. gares par statut)."
type CountByCode {
  "Code (ex. \`active\`, \`declassified\`)."
  code: String!
  "Nombre d'objets."
  count: Int!
}


"""
État du réseau à une ère, d'après la carte VFF (mêmes chiffres que la carte et l'accueil). Les
longueurs sont la somme des tronçons de la carte (voies parallèles d'une même ligne comprises) :
un ordre de grandeur, pas le kilométrage officiel.
"""
type EraStats {
  "Longueur en service (voyageurs, fret ou associatif), en km arrondis."
  open_km: Int!
  "Longueur fermée, voie encore en place, en km."
  closed_km: Int!
  "Longueur déposée ou effacée, en km."
  gone_km: Int!
  "Longueur électrifiée et en service, en km."
  electrified_km: Int!
  "Nombre de lignes portant du trafic à cette ère."
  active_lines: Int!
  "Longueur en service par trafic : \`mixed\`, \`passenger\`, \`freight\`, \`heritage\` (associatif), \`unknown\`."
  open_km_by_traffic: [KmByCode!]!
  "Nombre de gares par statut à cette ère : \`active\`, \`tourist\`, \`no_commercial\`, \`declassified\`."
  stations_by_status: [CountByCode!]!
}


"Un autre nom d'une ligne, avec sa source."
type LineName {
  "Le nom."
  name: String!
  "Nature : \`name\` (nom successif ou usuel), \`section\` (section nommée), \`merged\` (fiche fusionnée), \`wikipedia\` (titre de l'article), \`lignes-oubliees\`, \`archeo\`…"
  kind: String!
  "Source du nom (\`wikidata\`, \`wikipedia\`, \`lignes-oubliees\`, \`archeoferroviaire\`…)."
  source: String
  "Lien vers la source."
  url: String
  "Première année d'usage du nom, si elle est connue."
  from: Int
  "Dernière année d'usage du nom, si elle est connue."
  to: Int
}


"Une compagnie ou un gestionnaire de la ligne, avec ses années."
type LineOperator {
  "Nom de la compagnie ou du gestionnaire."
  operator: String!
  "Rôle, en français : \`exploitant\`, \`gestionnaire\`, \`propriétaire\`, ou plusieurs séparés par une virgule."
  role: String
  "Élément Wikidata de la compagnie (ex. \`Q13646\`)."
  wikidata: String
  "Première année dans ce rôle sur la ligne."
  from_year: Int
  "Dernière année dans ce rôle (nulle si inconnue ou toujours en fonction)."
  to_year: Int
  "Vrai si la compagnie est toujours en fonction."
  ongoing: Boolean
  "Source de l'information (\`wikidata\`…)."
  source: String
}


"""
Source de la description d'une ligne. Pour un extrait Wikipédia (\`type\` = \`wikipedia\`) : de quoi respecter la licence CC BY-SA 4.0
(auteur collectif, article, permalien de la version lue, licence). Pour une ligne décrite à la main (\`type\` = \`curated\`) : les sources citées.
"""
type LineDescriptionSource {
  "\`wikipedia\` (extrait de l'article, sous CC BY-SA 4.0) ou \`curated\` (texte rédigé à la main, sources citées dans \`urls\`)."
  type: String!
  "Titre de l'article Wikipédia (\`wikipedia\`)."
  title: String
  "Lien exact de la version lue de l'article (\`wikipedia\`)."
  url: String
  "Lien vers l'article, dans sa version courante (\`wikipedia\`)."
  article_url: String
  "Numéro de la révision lue (\`oldid\` de Wikipédia), ou nul s'il n'est pas connu."
  revision_id: Int
  "Permalien de la révision lue, ou nul si elle n'est pas connue."
  permalink: String
  "Historique de l'article, où figurent les auteurs."
  history_url: String
  "Auteur collectif à citer : « Contributeurs de Wikipédia »."
  author: String
  "Mention d'attribution prête à copier (article, auteur, licence)."
  attribution: String
  "Date de lecture de l'article (AAAA-MM-JJ)."
  fetched_at: String
  "Licence de l'extrait : \`CC BY-SA 4.0\`."
  license: String
  "Lien vers le texte de la licence."
  license_url: String
  "Sources citées pour une description rédigée à la main (\`curated\`)."
  urls: [String!]
}


"L'état de la ligne aujourd'hui (ou à sa dernière décennie connue)."
type LineToday {
  "Ère affichée : l'ère courante (2030) si la ligne y a un tracé, sinon sa dernière décennie."
  era_year: Int
  "État : \`open\` (au moins une portion en service), \`closed\` (fermée, voie en place), \`gone\` (déposée)."
  state: String
  "Trafic dominant : \`passenger\`, \`mixed\`, \`freight\`, \`heritage\` (associatif), \`unknown\`."
  traffic: String
}


"Les chiffres clés d'une ligne, ceux de l'en-tête de sa page."
type LineKeyFigures {
  "Longueur du tracé VFF à son plus grand développement, toutes portions comprises (en service, fermées, déposées), en km."
  length_km: Float
  "Origine de \`length_km\` : \`trace\` (tracé VFF), \`sncf\` (formes SNCF), \`segments\` (somme des tronçons)."
  length_definition: String
  "Longueur en service aujourd'hui, en km, de la même mesure."
  active_length_km: Float
  "Longueur des formes SNCF Réseau (généralisées), en km."
  length_sncf_km: Float
  "Longueur publiée (Wikidata), en mètres."
  length_official_m: Int
  "Vitesse maximale des tronçons, en km/h."
  max_speed_kmh: Int
  "Vitesse maximale publiée (Wikidata), en km/h."
  max_speed_kmh_official: Int
  "Écartement des rails, en mm (\`1435\` voie normale, \`1000\` voie métrique…)."
  gauge_mm: Int
  "Nombre de voies (Wikidata)."
  tracks: Int
  "Nombre de gares de la ligne, toutes ères, fermées comprises."
  station_count: Int!
  "Nombre de ces gares ouvertes aujourd'hui."
  station_open_count: Int!
  "Nombre d'événements datés."
  event_count: Int!
  "Nombre de tronçons de l'ère affichée."
  segment_count: Int!
  "Première date de dépose connue (\`AAAA-MM-JJ\`)."
  dismantled_from: String
  "Dernière date de dépose connue (\`AAAA-MM-JJ\`)."
  dismantled_to: String
}


"Électrification d'une ligne à l'ère affichée."
type LineElectrification {
  "\`full\` (toute la ligne), \`partial\`, \`marginal\` (quelques voies seulement : gare, raccordement), \`none\`."
  state: String!
  "Longueur électrifiée, en km."
  km: Float
  "Part électrifiée, en pourcentage."
  pct: Float
  "Longueur par tension (\`code\` = tension en volts, ex. \`1500\`, \`25000\`)."
  voltages: [LineVoltage!]!
}


"Longueur électrifiée sous une tension."
type LineVoltage {
  "Tension en volts (ex. \`1500\` continu, \`25000\` alternatif 50 Hz)."
  voltage_v: Int!
  "Longueur en km."
  km: Float!
}


"Signalisation de la ligne à l'ère affichée (SNCF Réseau Open Data, ODbL)."
type LineSheetSignalling {
  "Longueur par mode de cantonnement (\`BAL\`, \`BAPR-VB\`, \`CT-VU\`…) ; une tranche à deux systèmes (ETCS niveau 2 et TVM) compte pour chacun."
  block_systems_km: [KmByCode!]!
  "Longueur par système de contrôle de vitesse (\`KVB\`, \`TVM 430\`, \`ETCS 2\`…)."
  train_protection_km: [KmByCode!]!
  "Vrai si une partie de la ligne est équipée du KVB."
  kvb_equipped: Boolean!
  "Tranches de points kilométriques homogènes."
  ranges: [LineSignallingRange!]!
  "Mises en service datées relevées dans l'article Wikipédia de la ligne (historique à part de l'état actuel ci-dessus), comme \`Line.signalling_history\`."
  history: [SignallingEvent!]!
}


"L'état de la ligne au 31/12 d'une décennie (frise « évolution »)."
type LineEraSummary {
  "Année de l'ère."
  year: Int!
  "Vrai pour l'ère courante (aujourd'hui)."
  is_current: Boolean!
  "Longueur en service, en km."
  active_km: Float!
  "Longueur fermée, voie en place, en km."
  closed_km: Float!
  "Longueur déposée ou effacée, en km."
  gone_km: Float!
  "Longueur exploitée par une association, en km."
  heritage_km: Float!
  "Trafic dominant : \`passenger\`, \`mixed\`, \`freight\`, \`heritage\`, \`unknown\`."
  traffic: String
  "\`open\`, \`closed\` ou \`gone\`."
  state: String!
}


"Une gare d'une ligne, toutes ères confondues, dans l'ordre du tracé."
type LineSheetStation {
  "Slug de la gare."
  slug: String!
  "Nom de la gare."
  name: String!
  "Nature : \`gare\`, \`halte\`, \`point_arret\`…"
  type: String
  "Commune."
  city: String
  "Code INSEE du département."
  department_code: String
  "Latitude WGS 84."
  lat: Float
  "Longitude WGS 84."
  lng: Float
  "Point kilométrique sur la ligne, en mètres."
  pk_m: Int
  "Point kilométrique en notation \`km+mmm\`."
  pk_label: String
  "Vrai pour une gare d'extrémité."
  is_terminal: Boolean!
  "Comment la gare est rattachée à la ligne (\`sncf_pk\`, \`wikidata_p81\`, \`segment\`…)."
  link_method: String
  "Première année de desserte par la ligne."
  served_from: Int
  "Dernière année de desserte (nulle : toujours desservie ou inconnue)."
  served_until: Int
  "Nature de \`served_until\` : \`year\` (année tirée d'une date), \`upper_bound\` (desservie au plus tard cette année : la gare n'a qu'une fermeture en intervalle, ex. Frenelle-la-Grande - Puzieux sur 040000, 1989), \`undated\` (fin non datée : l'année n'est que celle du calcul, ex. Jarménil sur 062000, 2026), nul sans fin."
  served_until_kind: String
  "Origine des années de desserte : \`line_events\` (événements datés de la ligne au PK de la gare), \`traces\` (tracés datés), \`station\` (dates de la gare elle-même, faute d'événement daté de la ligne, ex. Morlaix sur 447000), nul si inconnues."
  years_source: String
  "Date sûre du début de desserte par la ligne, avec l'événement de ligne qui la donne ; nulle quand seule l'année est sûre (voir \`StationServiceDate\`)."
  service_start: StationServiceDate
  "Date sûre de la fin de desserte par la ligne, avec l'événement de ligne qui la donne ; nulle quand seule l'année est sûre, et toujours quand \`served_until_kind\` n'est pas \`year\`."
  service_end: StationServiceDate
  "Date d'ouverture (\`AAAA-MM-JJ\`)."
  opened_at: String
  "Précision de \`opened_at\` : \`day\`, \`month\`, \`year\`."
  opened_precision: String
  "Date de fermeture (\`AAAA-MM-JJ\`)."
  closed_at: String
  "Précision de \`closed_at\` : \`day\`, \`month\`, \`year\`, \`range\`."
  closed_precision: String
  "Gare fermée après cette année, date exacte inconnue."
  closed_after: Int
  "Année de fermeture retenue."
  closed_year: Int
  "Statut aujourd'hui (\`active\`, \`tourist\`, \`no_commercial\`, \`declassified\`) ; nul si la gare a disparu."
  status_today: String
  "Statut à chaque décennie : objet \`{ \\"1930\\": \\"active\\", … }\`."
  eras: JSON
}


"Une section nommée d'une ligne (ex. « LGV Rhône-Alpes »)."
type LineSection {
  "Nom de la section."
  name: String!
  "Autres noms."
  alt_names: [String!]!
  "Point kilométrique de début, en mètres."
  pk_start_m: Int!
  "Point kilométrique de fin, en mètres."
  pk_end_m: Int!
  "PK de début en notation \`km+mmm\`."
  pk_start_label: String!
  "PK de fin en notation \`km+mmm\`."
  pk_end_label: String!
  "Longueur, en km."
  length_km: Float!
  "Première année d'ouverture."
  opening_year_from: Int
  "Dernière année d'ouverture (section ouverte en plusieurs fois)."
  opening_year_to: Int
  "Présentation, en français (Wikipédia, CC BY-SA)."
  description: String
  "Lien vers la source."
  source_url: String
  """
  Mises en service datées de la section : les phases de la ligne qui ouvrent une partie du tracé
  pendant ses années d'ouverture, dans l'ordre des dates. Une phase dont l'année tombe dans les
  années de deux sections n'est rattachée à aucune.
  """
  stages: [LineSectionStage!]!
}


"Une mise en service datée d'une section nommée (ex. LGV Paris - Lyon, 27 septembre 1981)."
type LineSectionStage {
  "Indice de la phase dans \`LineSheet.phases\`."
  phase: Int!
  "Date de la mise en service (AAAA-MM-JJ), à lire avec \`precision\`."
  date: String!
  "Précision de la date : \`day\`, \`month\` ou \`year\`."
  precision: String!
  "Longueur mise en service, en mètres."
  length_m: Int!
  "Événements qui datent cette mise en service (voir \`Query.lineEvent\` et \`LineSheet.chronicle\`)."
  event_ids: [Int!]!
}


"Un matériel roulant ayant circulé sur la ligne (résumé)."
type LineSheetMaterial {
  "Slug du matériel."
  slug: String!
  "Nom de la série."
  name: String!
  "Catégorie (voir \`Material.type\`)."
  type: String
  "Constructeurs joints par des virgules, coupés à 255 caractères (« … ») quand ils sont nombreux. Liste complète : \`builders\`."
  builder: String
  "Tous les constructeurs de la série (Wikidata P176), sans doublon, dans l'ordre de la source, sans ceux qui n'ont pas de libellé Wikidata ; liste vide si aucun n'est connu."
  builders: [String!]!
  "Année de mise en service."
  in_service_from: Int
  "Année de retrait."
  in_service_to: Int
  "Périodes de circulation sur cette ligne datées par une source (sûres seulement), les plus anciennes d'abord. Détail et fiabilité : \`Line.material_links.periods\`."
  periods: [LineSheetMaterialPeriod!]!
}


"Période de circulation d'une série sur la ligne (forme courte de \`LineMaterialPeriod\`)."
type LineSheetMaterialPeriod {
  "Début (\`AAAA-MM-JJ\`), nul si seule la fin est connue."
  start_date: String
  "Précision du début : \`day\`, \`month\` ou \`year\`."
  start_precision: String
  "Fin (\`AAAA-MM-JJ\`), nulle si la source ne la donne pas."
  end_date: String
  "Précision de la fin : \`day\`, \`month\` ou \`year\`."
  end_precision: String
  "Début de la portion parcourue, si l'article la nomme."
  section_from: String
  "Fin de la portion parcourue."
  section_to: String
  "Fournisseur de la source : \`wikipedia\` ou \`wikidata\`."
  source: String!
  "Permalien de la source."
  source_url: String!
}


"""
Fiche complète d'une ligne : tout ce que montre la page \`/lines/{slug}\`, au même contrat que
\`GET /api/v1/lines/{line}\`. Les parties très imbriquées (faits, phases, décennies, chronique,
exploitations associatives, sources) sont des objets \`JSON\` dont chaque champ est décrit dans la
documentation REST (\`/docs/api\`, « Fiche complète d'une ligne »).
"""
type LineSheet {
  "Slug de la ligne."
  slug: String!
  "Code SNCF Réseau à 6 chiffres."
  code: String
  "Nom usuel."
  name: String!
  "Autres noms, chacun avec sa source."
  names: [LineName!]!
  "Élément Wikidata de la ligne."
  wikidata: String
  "Réseau : \`principal\`, \`secondaire\`, \`militaire\`, \`particulier\`."
  network: String
  "Ligne à grande vitesse (signalisation en cabine)."
  is_lgv: Boolean!
  "Ligne menacée de fermeture."
  is_threatened: Boolean!
  "Extrait de l'article Wikipédia, en français, sous CC BY-SA 4.0 (voir \`description_source\`)."
  description: String
  "Source de la description : auteur collectif, article, permalien de la révision lue et licence à citer."
  description_source: LineDescriptionSource
  "Compagnie d'origine."
  historical_operator: String
  "Compagnies et gestionnaires successifs."
  historical_operators: [LineOperator!]!
  "Année de concession."
  concession_year: Int
  "Régions traversées (noms)."
  regions: [String!]!
  "Départements traversés (noms)."
  departements: [String!]!
  "Titre de l'article Wikipédia."
  wikipedia_title: String
  "Article d'archeoferroviaire (usage non commercial, crédit et lien)."
  archeo_url: String
  "État aujourd'hui."
  today: LineToday!
  "Chiffres clés."
  key_figures: LineKeyFigures!
  "Électrification à l'ère affichée, ou nul."
  electrification: LineElectrification
  "Signalisation à l'ère affichée."
  signalling: LineSheetSignalling!
  "Faits de la fiche, chacun avec \`key\`, sa valeur (\`km\`, \`mm\`, \`kmh\`…), \`source\`, \`scope\` (\`line\` ou \`part\`), \`item\`, \`as_of\`, \`label\` et \`note\` en français."
  facts: JSON
  "État décennie par décennie."
  era_summary: [LineEraSummary!]!
  """
  Tronçons élémentaires des phases : \`index\`, \`length_m\` et, avec \`geometry: true\`, \`geometry\`
  GeoJSON, \`geometry_withheld\` et \`geometry_withheld_reason\`. La géométrie d'une portion n'est
  jointe que si toutes ses sources autorisent la redistribution ; sinon elle vaut \`null\`,
  \`geometry_withheld\` vaut \`true\` et \`geometry_withheld_reason\` donne la raison
  (\`licence_not_open\` : source dont la licence n'autorise pas la redistribution ;
  \`unknown_source\` : source absente du registre des droits).
  """
  portions: JSON
  "Phases datées : \`date\`, \`precision\`, \`kind\`, \`changes\` (\`from\`, \`to\`, \`length_m\`, \`event_id\`), \`state\` par portion, \`length_by_category\`, \`active_m\`."
  phases: JSON
  "Indice de la phase où la ligne était la plus étendue."
  best_phase: Int
  "Écarts entre la dernière décennie et les sources d'aujourd'hui qu'aucun événement ne date."
  phases_unexplained: JSON
  "Décennies : \`year\`, \`length_by_category\` (m), \`active_m\`, \`phases\`, \`stations\` (ouvertes, incertaines, fermées, fermetures)."
  decades: JSON
  "Chronique sourcée : \`events\`, \`groups\` (par portion), \`notes\`, \`conflicts\`, \`statuses\`."
  chronicle: JSON
  "Gares de la ligne, toutes ères, dans l'ordre du tracé."
  stations: [LineSheetStation!]!
  "Sections nommées."
  sections: [LineSection!]!
  "Exploitations associatives : \`name\`, \`operator\`, \`kind\`, \`from\`, \`to\`, \`length_km\`, \`periods\`, \`source_url\`."
  heritage: JSON
  "Matériels roulants associés."
  materials: [LineSheetMaterial!]!
  "Jeux de données utilisés pour la fiche : \`type\`, \`url\`, \`fetched_at\`, \`fields\`."
  sources: JSON
  "Liens : page web et points d'accès REST liés (événements, tronçons, gares, médias…)."
  links: JSON
}


"Position d'une gare et sa fiabilité."
type StationPosition {
  "Source retenue pour la position (\`osm\`, \`sncf\`, \`wikidata\`…)."
  source: String
  "Écart entre les sources, en mètres."
  spread_m: Int
  "Vrai si la position est à vérifier."
  flagged: Boolean!
}


"Statut d'une gare à une décennie."
type StationEra {
  "Année de l'ère."
  year: Int!
  "Vrai pour l'ère courante (aujourd'hui)."
  is_current: Boolean!
  "Statut : \`active\`, \`tourist\`, \`no_commercial\`, \`declassified\`, \`uncertain\` (ouverte selon ses sources, non confirmée par les voies) ; nul si la gare n'existe pas encore ou plus."
  status: String
  "Note sur ce statut, en français."
  note: String
  "Vrai si l'ère est incertaine faute de date de fermeture."
  undated: Boolean!
}


"""
Début ou fin de desserte d'une gare par une ligne, daté par un événement de la ligne. Présent
seulement quand cette date vaut pour la gare : son année est celle de la période de desserte, la
gare n'a pas ouvert (fermé) la même année à une date qui pourrait la contredire, la fin est une vraie
date (ni borne haute ni fin non datée) et la ligne a desservi la gare. Exemple réel : Saint-Calais
sur la ligne de Bessé-sur-Braye à Saint-Calais (\`lo-507000\`), début
\`{date: "1879-03-31", precision: "day", event_id: 416}\`, fin
\`{date: "1938-05-15", precision: "day", event_id: 417}\` (fermeture aux voyageurs).
"""
type StationServiceDate {
  "Date \`AAAA-MM-JJ\`, à lire avec \`precision\` : au mois, le jour vaut 01 et ne compte pas ; à l'année, seule l'année compte."
  date: String!
  "Précision de \`date\` : \`day\`, \`month\` ou \`year\`."
  precision: String!
  "Événement de ligne qui donne la date (requête \`lineEvent(id:)\`, REST \`/api/v1/events/{id}\`) ; nul s'il n'a pas été retrouvé."
  event_id: Int
}


"Une ligne desservant une gare."
type StationLine {
  "Slug de la ligne."
  slug: String!
  "Code SNCF Réseau."
  code: String
  "Nom de la ligne."
  name: String!
  "Réseau de la ligne."
  network: String
  "Comment la gare est rattachée (\`sncf_pk\`, \`wikidata_p81\`, \`segment\`…)."
  method: String
  "Point kilométrique de la gare sur la ligne, en mètres."
  pk_m: Int
  "Source du PK (\`sncf\`, \`projected_raw\`…)."
  pk_source: String
  "Première année de desserte."
  first_year: Int
  "Dernière année de desserte (nulle : toujours desservie)."
  last_year: Int
  "Nature de \`last_year\` : \`year\` (année tirée d'une date), \`upper_bound\` (desservie au plus tard cette année : la gare n'a qu'une fermeture en intervalle, ex. Frenelle-la-Grande - Puzieux sur 040000, 1989), \`undated\` (fin non datée : l'année n'est que celle du calcul, ex. Jarménil sur 062000, 2026), nul sans fin."
  last_year_kind: String
  "Origine des années de desserte : \`line_events\` (événements datés de la ligne au PK de la gare), \`traces\` (tracés datés), \`station\` (dates de la gare elle-même, faute d'événement daté de la ligne, ex. Morlaix sur 447000), \`never_served\`, nul si inconnues."
  years_source: String
  "Date sûre du début de desserte par cette ligne, avec l'événement de ligne qui la donne ; nulle quand seule l'année est sûre (voir \`StationServiceDate\`)."
  service_start: StationServiceDate
  "Date sûre de la fin de desserte par cette ligne, avec l'événement de ligne qui la donne ; nulle quand seule l'année est sûre, et toujours quand \`last_year_kind\` n'est pas \`year\`."
  service_end: StationServiceDate
  "\`line_closed\` (ligne fermée avant l'ouverture de la gare), \`station_closed\`, ou nul."
  never_served: String
  "Vrai si une portion en service aujourd'hui dessert la gare."
  active_current: Boolean!
  "Décennies où la gare est un arrêt de la ligne."
  era_years: [Int!]!
}


"Une source rattachée à une gare."
type StationSource {
  "Source : \`sncf\`, \`sncf_voyageurs\`, \`osm\`, \`wikidata\`, \`ohm\`, \`lignes-oubliees\`…"
  source: String!
  "Identifiant dans la source (code UIC, objet OSM \`n123\`, élément Wikidata…)."
  ref: String
  "Nom dans la source."
  name: String
  "Cycle de vie selon la source."
  lifecycle: String
  "Code UIC selon la source."
  uic_code: String
  "Date d'ouverture selon la source."
  opened_on: String
  "Date de fermeture selon la source."
  closed_on: String
  "Méthode d'appariement avec la gare (\`uic\`, \`seed\`, \`name\`…)."
  match_method: String
  "Écart à la position retenue, en mètres."
  distance_m: Int
  "Desserte voyageurs déclarée."
  passenger: Boolean
  "Desserte fret déclarée."
  freight: Boolean
  "Point kilométrique donné par la source, en mètres (si elle en donne un)."
  pk_m: Int
  "Comment la source a été placée sur la carte : \`pk\` (calée sur le point kilométrique), \`commune\`, \`coords\`…"
  geocoding: String
  "Précision estimée de la position donnée par la source, en mètres (nul : position exacte ou inconnue)."
  precision_m: Int
  "Lien vers l'objet de la source."
  url: String
  "Date à laquelle VFF a lu l'objet dans la source (AAAA-MM-JJ)."
  fetched_at: String
  "Attributs bruts tels que reçus de la source (étiquettes OSM, champs SNCF, éléments Wikidata, calage), en lecture seule. Leur forme dépend de la source. Nul quand la source n'est pas sous licence ouverte vérifiée (voir \`raw_withheld\`)."
  raw: JSON
  "Vrai si les attributs bruts ne sont pas republiés : comme pour les tracés, seule une source sous licence ouverte vérifiée (\`provenance.license_status\` = \`verified\`) les voit republiés."
  raw_withheld: Boolean!
  "Fournisseur et licence de la source."
  provenance: Provenance!
}


"Une gare voisine (moins de 10 km)."
type StationNeighbor {
  "Slug."
  slug: String!
  "Nom."
  name: String!
  "Nature."
  type: String
  "Commune."
  city: String
  "Statut actuel."
  status: String
  "Latitude WGS 84."
  lat: Float!
  "Longitude WGS 84."
  lng: Float!
  "Distance, en mètres."
  distance_m: Int!
}


"""
Fiche complète d'une gare : tout ce que montre la page \`/stations/{slug}\`, au même contrat que
\`GET /api/v1/stations/{station}\`. La chronologie est sur \`Station.events\`.
"""
type StationSheet {
  "Statut affiché (voir l'annuaire ; \`technical\` point technique, \`excluded\` fiche retirée du réseau ferré)."
  status: String!
  "Statut selon les sources."
  source_status: String
  "Autres noms."
  alt_names: JSON
  "Précision de la date d'ouverture : \`day\`, \`month\`, \`year\`."
  opened_precision: String
  "Précision de la date de fermeture."
  closed_precision: String
  "Fermée après cette année, date exacte inconnue."
  closed_after: Int
  "Code UIC."
  uic_code: String
  "Élément Wikidata."
  wikidata_id: String
  "Position et fiabilité."
  position: StationPosition!
  "Source de chaque attribut (\`name\`, \`opened_at\`, \`geom\`…) et détail du recalage."
  attribute_sources: JSON
  "Statut à chaque décennie."
  eras: [StationEra!]!
  "Lignes desservies."
  lines: [StationLine!]!
  "Sources rattachées."
  sources: [StationSource!]!
  "Gares à moins de 10 km (12 au plus), de la plus proche à la plus lointaine."
  neighbors: [StationNeighbor!]!
  """
  Liens : page web (\`web\`) et points d'accès REST liés : chronologie (\`events\`), médias (\`media\`),
  accidents dont la gare est la gare la plus proche du lieu (\`accidents\`), département (\`department\`,
  nul si inconnu).
  """
  links: JSON
}


"Une ligne de l'annuaire, avec son état à l'ère de référence."
type LineDirectoryItem {
  "Slug."
  slug: String!
  "Code SNCF Réseau."
  code: String
  "Nom."
  name: String!
  "Réseau : \`principal\`, \`secondaire\`, \`militaire\`, \`particulier\` (nul : non renseigné)."
  network: String
  "Compagnie d'origine."
  historical_operator: String
  "Régions (noms)."
  regions: [String!]!
  "Départements (noms)."
  departements: [String!]!
  "État à l'ère de référence : \`open\`, \`closed\`, \`unknown\` (aucun tracé à cette ère)."
  state: String!
  "Trafic dominant à l'ère de référence : \`passenger\`, \`mixed\`, \`freight\`, \`unknown\` ; nul si rien n'est en service."
  traffic: String
  "Vrai si une portion est exploitée par une association à l'ère de référence."
  heritage: Boolean!
}


"Compteurs de l'annuaire des lignes (chaque facette est comptée sans son propre filtre)."
type LineDirectoryFacets {
  "Nombre de lignes trouvées."
  total: Int!
  "Lignes ouvertes à l'ère de référence."
  open: Int!
  "Lignes fermées à l'ère de référence."
  closed: Int!
  "Lignes sans tracé à l'ère de référence."
  unknown: Int!
  "Lignes par réseau (\`none\` : non renseigné)."
  networks: [CountByCode!]!
  "Lignes par trafic de leurs portions en service."
  traffics: [CountByCode!]!
  "Lignes avec une portion associative."
  heritage: Int!
  "Lignes par département (code INSEE), sans le filtre \`department\` ; une ligne compte dans chacun de ses départements. Départements sans ligne absents."
  departments: [CountByCode!]!
}


"Une page de l'annuaire des lignes."
type LineDirectoryPage {
  "Les lignes de la page."
  data: [LineDirectoryItem!]!
  "Curseur de la page suivante, à passer dans \`after\` ; nul à la dernière page. Le même que \`meta.next_cursor\` de l'API REST."
  next_cursor: String
  "Ère de référence de \`state\`, \`traffic\` et des filtres \`status\` et \`traffic\`."
  era_year: Int
  "Compteurs de la recherche (calculés seulement s'ils sont demandés)."
  facets: LineDirectoryFacets!
}


"Une gare de l'annuaire."
type StationDirectoryItem {
  "Slug."
  slug: String!
  "Nom."
  name: String!
  "Nature : \`gare\`, \`halte\`, \`point_arret\`, \`passage_niveau\`, \`embranchement\`."
  type: String
  "Statut affiché, ou statut à l'ère \`era\` : \`active\`, \`tourist\`, \`no_commercial\`, \`declassified\`, \`gone\`, \`planned\`."
  status: String
  "Commune."
  city: String
  "Code INSEE du département."
  department_code: String
  "Code UIC."
  uic_code: String
  "Élément Wikidata."
  wikidata_id: String
  "Date d'ouverture (\`AAAA-MM-JJ\`)."
  opened_at: String
  "Précision de la date d'ouverture : \`day\`, \`month\`, \`year\`."
  opened_precision: String
  "Date de fermeture (\`AAAA-MM-JJ\`)."
  closed_at: String
  "Précision de la date de fermeture."
  closed_precision: String
  "Fermée après cette année, date exacte inconnue."
  closed_after: Int
  "Latitude WGS 84."
  lat: Float!
  "Longitude WGS 84."
  lng: Float!
  "Vrai si la position est à vérifier."
  position_flagged: Boolean!
  "Nombre de sources rattachées."
  sources_count: Int!
  "Lignes desservant la gare (10 au plus, codes d'abord)."
  lines: [StationDirectoryLine!]!
  "Nombre d'autres lignes, au-delà des 10 données."
  lines_more: Int!
}


"Une ligne desservant une gare de l'annuaire."
type StationDirectoryLine {
  "Slug de la ligne."
  slug: String!
  "Code SNCF Réseau."
  code: String
  "Nom de la ligne."
  name: String!
}


"Une page de l'annuaire des gares."
type StationDirectoryPage {
  "Les gares de la page."
  data: [StationDirectoryItem!]!
  "Curseur de la page suivante, à passer dans \`after\` ; nul à la dernière page."
  next_cursor: String
  "Ère du filtre \`era\`, ou nul."
  era_year: Int
  "Nombre de gares par statut pour la recherche, sans le filtre \`status\` (calculé seulement s'il est demandé)."
  facets: [CountByCode!]!
  "Nombre de gares par département (code INSEE) pour la recherche, sans le filtre \`department\` ; départements sans gare absents (calculé seulement s'il est demandé)."
  department_facets: [CountByCode!]!
}


"Filtres de \`lineDirectory\`, ceux de la page \`/lines\` (combinés : ET)."
input LineDirectoryFilter {
  "Recherche littérale dans le nom, le code ou la compagnie d'origine, 100 caractères au plus."
  q: String
  "Réseau : \`principal\`, \`secondaire\`, \`militaire\`, \`particulier\` ou \`none\`."
  network: String
  "Trafic d'au moins une portion en service à l'ère : \`passenger\`, \`mixed\`, \`freight\`, \`unknown\`."
  traffic: String
  "État à l'ère de référence : \`open\` ou \`closed\`."
  status: String
  "Seulement les lignes avec une portion associative."
  heritage: Boolean
  "Code INSEE du département (lignes citant ce département ou y desservant une gare). Liste : requête \`departments\`."
  department: String
  "Code INSEE de la région (\`53\` Bretagne, \`11\` Île-de-France…, liste : requête \`regions\`) : lignes citant l'un de ses départements ou y desservant une gare."
  region: String
  "Année de l'ère de référence (décennie de 1830 à 2030). Par défaut : aujourd'hui."
  era: Int
  "Tri : \`name\` (alphabétique français, par défaut), \`code\` (lignes sans code en dernier), \`slug\`."
  sort: String
  "Sens du tri (\`ASC\` par défaut)."
  order: SortOrder
}


"Filtres de \`stationDirectory\`, ceux de la page \`/stations\` (combinés : ET)."
input StationDirectoryFilter {
  "Recherche littérale dans le nom, la commune, les autres noms, ou début du code UIC."
  q: String
  "Statut : \`active\`, \`tourist\`, \`no_commercial\`, \`declassified\`, \`gone\`, \`planned\`."
  status: String
  "Code INSEE du département de la gare. Liste : requête \`departments\`."
  department: String
  "Code INSEE de la région (\`53\` Bretagne, \`11\` Île-de-France…, liste : requête \`regions\`) : gares de ses départements."
  region: String
  "Année d'une ère : statut à cette ère, gares absentes exclues."
  era: Int
  "Seulement les gares dont la position est à vérifier."
  flagged: Boolean
  "Tri : \`name\` (par défaut), \`city\`, \`opened\`, \`closed\` (sans valeur : en dernier)."
  sort: String
  "Sens du tri (\`ASC\` par défaut)."
  order: SortOrder
}


"Les valeurs de provenance d'une table qui désignent un fournisseur."
type SourceValues {
  "Table (\`line_events\`, \`line_segments\`, \`station_sources\`, \`station_events\`, \`media\`…)."
  table: String!
  "Valeurs du champ \`source\` de cette table."
  values: [String!]!
}


"Un fournisseur de données de VFF et sa licence, tel que VFF l'a vérifiée."
type SourceProvider {
  "Clé du fournisseur : celle de \`Provenance.provider\`."
  provider: String!
  "Nom du fournisseur."
  name: String!
  "Licence ou conditions de réutilisation, en français."
  licence: String!
  "\`verified\` (licence ouverte prouvée), \`uncertain\` (autorisation particulière, conditions à lire), \`forbidden\`."
  license_status: String
  "Vrai si la licence est ouverte et prouvée."
  open_licence: Boolean!
  "Mention à reproduire en réutilisant les données."
  mention: String
  "Phrase exacte de la licence ou des conditions."
  citation: String
  "Pages qui établissent la licence."
  proof: [String!]!
  "Date de lecture des pages (\`AAAA-MM-JJ\`)."
  checked_on: String
  "Domaines des liens qui désignent ce fournisseur."
  domains: [String!]!
  "Valeurs de provenance, par table, qui renvoient à ce fournisseur."
  source_values: [SourceValues!]!
}


"Chiffres d'ensemble du site (page d'accueil, \`GET /api/v1/stats\`). Recalculés dès que les données du réseau changent."
type SiteStats {
  "Lignes recensées, en service ou disparues."
  lines_count: Int!
  "Gares et haltes recensées, ouvertes ou fermées."
  stations_count: Int!
  "Séries de matériel roulant."
  materials_count: Int!
  "Ères dont la carte a au moins un tronçon."
  eras_count: Int!
  "Kilomètres de lignes exploitées aujourd'hui, d'après les formes de SNCF Réseau (l'axe de chaque ligne, une fois)."
  total_km: Int!
  "Le réseau à chaque ère : kilomètres en service et lignes actives."
  profile: [SiteStatsEra!]!
}


"Le réseau à une ère, dans \`SiteStats.profile\`."
type SiteStatsEra {
  "Année de l'ère (1830, 1840… 2030)."
  year: Int!
  "Longueur des tronçons en service, en km arrondis. Voies parallèles comprises : un ordre de grandeur de l'étendue du réseau."
  km: Int!
  "Nombre de lignes qui portent du trafic."
  lines: Int!
  "Vrai pour l'ère d'aujourd'hui (2030)."
  current: Boolean!
}


"Résultats d'une recherche par nom."
type SearchResults {
  "Lignes dont le nom ou le code correspond."
  lines: [SearchLine!]!
  "Gares dont le nom correspond."
  stations: [SearchStation!]!
  "Matériels roulants dont le nom ou la désignation correspond."
  materials: [SearchMaterial!]!
  "Accidents publiés dont le nom (dans l'une des trois langues), la commune ou la date correspond."
  accidents: [SearchAccident!]!
}


"Une ligne trouvée par la recherche."
type SearchLine {
  "Identifiant de la ligne (\`rfn-420000\`), à passer à \`line(slug:)\`."
  slug: String!
  "Nom de la ligne."
  name: String!
  "Code SNCF Réseau à 6 chiffres, nul pour une ligne hors réseau ferré national."
  code: String
}


"Une gare trouvée par la recherche."
type SearchStation {
  "Identifiant de la gare, à passer à \`station(slug:)\`."
  slug: String!
  "Nom de la gare."
  name: String!
  "Commune, pour distinguer les homonymes."
  city: String
  "Code du département."
  department_code: String
  "Latitude WGS 84."
  lat: Float!
  "Longitude WGS 84."
  lng: Float!
}


"Un matériel roulant trouvé par la recherche."
type SearchMaterial {
  "Identifiant de la série, à passer à \`material(slug:)\`."
  slug: String!
  "Nom de la série."
  name: String!
  "Catégorie (\`locomotive_electrique\`, \`automotrice\`…)."
  kind: String!
  "Constructeur, nul s'il n'est pas connu."
  builder: String
}


"Un accident trouvé par la recherche."
type SearchAccident {
  "Identifiant de l'accident (\`2013-07-12-bretigny-sur-orge\`), à passer à \`accident(slug:)\`."
  slug: String!
  "Nom usuel."
  name(
    "Langue : \`fr\` (par défaut), \`en\` ou \`de\`."
    lang: String = "fr"
  ): String!
  "Date de l'accident (\`AAAA-MM-JJ\`), à lire avec \`date_precision\`."
  date: Date!
  "Précision de \`date\` : \`day\` (date exacte), \`month\` (mois et année), \`year\` (année seule)."
  date_precision: String!
  "Nature (\`derailment\`, \`collision\`, \`level_crossing\`…)."
  kind: String!
  "Commune actuelle du lieu."
  commune: String
  "Code du département."
  department_code: String
  "Latitude WGS 84, nulle si le lieu n'est pas localisé."
  lat: Float
  "Longitude WGS 84, nulle si le lieu n'est pas localisé."
  lng: Float
}


"Point kilométrique d'un lieu sur une ligne (\`Line.pk\`)."
type LinePk {
  "PK en mètres, mesuré le long du tracé VFF depuis son origine."
  pk_m: Int!
  "PK en notation ferroviaire \`km+mmm\` (\`249+204\` = 249,204 km)."
  pk_label: String!
  "Longueur totale du tracé de la ligne à cette ère, en mètres."
  total_m: Int!
  """
  Vrai quand le point projeté longe un tronçon dont la source n'autorise pas la redistribution
  (voir « Droits des tracés » dans la documentation de l'API REST). \`snap\` vaut alors \`null\` : ni le
  point projeté ni la distance au tracé ne sont donnés, car une grille de requêtes suffirait à
  reconstituer le tracé, même avec une distance arrondie. Le lieu est ramené au centre de sa maille
  de 0,001 degré (environ 110 m sur 75 m en France) et \`pk_m\` est arrondi à 100 m : il est juste à
  150 m près environ.
  """
  geometry_withheld: Boolean!
  "Raison du retrait : \`licence_not_open\` (licence non ouverte, non commerciale ou incertaine) ou \`unknown_source\` (source absente du registre des droits) ; nulle quand \`geometry_withheld\` est faux."
  geometry_withheld_reason: String
  "Point du tracé le plus proche du lieu demandé ; nul quand \`geometry_withheld\` est vrai."
  snap: LinePkSnap
}


"Point du tracé retenu pour calculer un PK."
type LinePkSnap {
  "Latitude WGS 84 du point du tracé."
  lat: Float!
  "Longitude WGS 84 du point du tracé."
  lng: Float!
  "Distance entre le lieu demandé et le tracé, en mètres. Au-delà de quelques dizaines de mètres, le lieu n'est probablement pas sur cette ligne."
  distance_from_input_m: Float!
}


"""
Un département français. Code et nom tirés du Code officiel géographique de l'INSEE (Licence Ouverte
2.0), région depuis le 1er janvier 2016. Les compteurs suivent les règles des filtres \`department\`.
"""
type Department {
  "Code INSEE : \`01\` à \`95\`, \`2A\`, \`2B\`, \`971\` à \`976\` (valeur des filtres \`department\`)."
  code: String!
  "Nom officiel (ex. « Finistère »)."
  name: String!
  "Région du département."
  region: RegionRef!
  "Ce que VFF recense dans le département."
  counts: TerritoryCounts!
}


"Une région française (Code officiel géographique de l'INSEE, Licence Ouverte 2.0)."
type Region {
  "Code INSEE : \`11\` à \`94\`, outre-mer \`01\` à \`06\` (valeur des filtres \`region\`)."
  code: String!
  "Nom officiel (ex. « Bretagne »)."
  name: String!
  "Départements de la région, dans l'ordre du code officiel."
  departments: [DepartmentRef!]!
  "Ce que VFF recense dans la région (une ligne qui traverse plusieurs de ses départements compte une fois)."
  counts: TerritoryCounts!
}


"Une région, par son code et son nom."
type RegionRef {
  "Code INSEE de la région."
  code: String!
  "Nom de la région."
  name: String!
}


"Un département, par son code et son nom."
type DepartmentRef {
  "Code INSEE du département."
  code: String!
  "Nom du département."
  name: String!
}


"Ce que VFF recense dans un département ou une région."
type TerritoryCounts {
  "Lignes dont la fiche cite le territoire ou qui y desservent une gare (résultat du filtre \`department\` ou \`region\` de \`lineDirectory\`)."
  lines: Int!
  "Gares de l'annuaire situées dans le territoire (points techniques exclus)."
  stations: Int!
  "Accidents publiés dont le lieu est dans le territoire (département actuel)."
  accidents: Int!
}


"Une proposition de contribution : renseignez exactement un des six champs."
input ContributionInput {
  "Modification d'attributs d'une portion de ligne entre deux PK, à une ère."
  segment: SegmentContributionInput
  "Tracé dessiné : ligne disparue (\`new_line\`) ou correction d'une ligne existante (\`correction\`)."
  trace: TraceContributionInput
  "Événement daté d'une ligne existante (ouverture, fermeture, électrification…), avec sa source."
  event: EventContributionInput
  "Image rattachée à une ligne, une gare, un événement ou une série de matériel : fichier de Wikimedia Commons ou fichier déposé."
  media: MediaContributionInput
  "Série de matériel qui a circulé sur une ligne, avec sa source et sa période de service."
  line_material: LineMaterialContributionInput
  "Nouvelle série de matériel, ou correction d'une série de VFF, avec sa source."
  series: MaterialSeriesContributionInput
}


"""
Série de matériel qui a circulé sur une ligne, comme dans le formulaire « Proposer un matériel sur
une ligne » du site (\`/contribute/material\`).

À l'envoi, les règles d'écartement et d'électrification de l'import s'appliquent : une série dont
l'écartement couvre moins du quart de la ligne, ou une série électrique dont aucun système n'a
couvert 5 % de la ligne pendant son service, est refusée (erreur sur \`quote\`, qui explique le
conflit). Le lien n'est accepté que si \`quote\` cite une phrase de la source qui nomme l'écartement
(« voie normale », « 1 435 mm ») ou la tension de la série (« 750 V »), comme les automotrices
Z 1200 sur la ligne des Moulineaux (troisième rail en 750 V). L'exception est alors signalée aux
validateurs. Un lien déjà sûr dans VFF n'est accepté qu'avec une période de service.

À la publication, le lien entre dans \`LineMaterialLink\` (source \`contrib\`, preuve \`contribution\`,
provenance \`contribution:<id>\`) et la période dans \`LineMaterialPeriod\` (origine \`contribution\`).
Aucun import n'est relancé.
"""
input LineMaterialContributionInput {
  "Identifiant d'URL de la ligne (ex. \`rfn-972000\`)."
  line_slug: String!
  "Identifiant d'URL de la série (ex. \`z-1200\`, voir \`Material.slug\`)."
  material_slug: String!
  """
  Nature de la circulation d'après la source : \`regular\` (service régulier), \`occasional\`
  (circulation exceptionnelle ou spéciale), \`trial\` (essais) ou \`heritage\` (train de musée ou
  touristique). Hors service régulier, le lien est publié en fiabilité \`low\` (à confirmer).
  """
  circulation: String!
  "Précision du début de la période de service : \`day\`, \`month\` ou \`year\` ; à donner avec \`start_date\`."
  start_precision: String
  "Début de la période selon sa précision : \`AAAA-MM-JJ\`, \`AAAA-MM\` ou \`AAAA\` (années 1750 et suivantes)."
  start_date: String
  "Précision de la fin de la période : \`day\`, \`month\` ou \`year\` ; à donner avec \`end_date\`."
  end_precision: String
  "Fin de la période selon sa précision, au plus tôt le début. Sans début ni fin, aucune période n'est publiée."
  end_date: String
  "Début de la portion parcourue, quand la source la précise (gare, commune), 250 caractères au plus ; à donner avec \`section_to\` et une date."
  section_from: String
  "Fin de la portion parcourue, différente de \`section_from\`."
  section_to: String
  "Nature de la source : \`web\` (page web), \`document\` (document numérisé) ou \`print\` (ouvrage ou archive papier)."
  source_kind: String!
  "Titre ou référence de la source, de 3 à 300 caractères (ex. « Livret Chaix d'été 1938, p. 112 »)."
  source_title: String!
  "Adresse de la source, en http ou https, 500 caractères au plus : obligatoire sauf avec \`print\`."
  source_url: String
  """
  Licence du document, obligatoire avec \`document\` et ignorée sinon : \`public_domain\`, \`cc0-1.0\`,
  \`cc-by-4.0\`, \`cc-by-sa-4.0\`, \`etalab-2.0\`, \`all_rights_reserved\` ou \`unknown\`.
  """
  source_license: String
  """
  Phrase de la source qui montre la circulation, de 10 à 600 caractères (comptés après la réduction
  des espaces). Facultative, sauf quand le lien contredit la règle d'écartement ou d'électrification :
  elle doit alors nommer la série (nom ou désignation) et son écartement ou sa tension, en français,
  en anglais ou en allemand, sans négation autour (« jamais », « pas », « sans », « aucun », « never »,
  « not », « nicht », « kein »…). Publiée avec le lien (\`LineMaterialEvidence.quote\`). Un couple
  écarté après relecture (exclusion relue de VFF) est refusé, comme une période déjà publiée pour
  le couple par une contribution.
  """
  quote: String
  "Note pour les validateurs, non publiée, 2 000 caractères au plus."
  notes: String
}


"""
Nouvelle série de matériel (\`new\`) ou correction d'une série de VFF (\`correction\`), comme dans le
formulaire « Proposer une série » du site (\`/contribute/series\`).

\`wikidata_id\` est vérifié auprès de Wikidata, seulement s'il change : un élément qui n'existe pas, ou
qui n'est pas une série de matériel roulant, est refusé. Une nouvelle série prend l'identifiant final
d'un élément fusionné ; une correction refuse un élément fusionné en donnant son identifiant final, à
saisir s'il convient (l'identifiant d'une série ne change jamais sans qu'on le demande). Une nouvelle
série déjà présente dans VFF (même identifiant Wikidata, même nom) est refusée : proposez la
correction de sa fiche. Pour une correction, un champ absent ou nul ne change pas, \`clear\` efface
une valeur fausse, et seuls les champs qui changent sont gardés (\`payload.changes\`, \`after\` nul pour
un effacement). \`payload.impact\` liste les liens de la série qui deviendraient contraires à la règle
d'écartement ou d'électrification (\`line_slug\`, \`line_code\`, \`line_name\`, \`source\`, \`rule\`, \`reason\`).

À la publication, la série est créée ou corrigée ; chaque champ écrit a la provenance \`contrib\`
(\`Material.field_sources\`), que l'import de Wikidata ne remplace pas (ni ne remplit de nouveau après
un effacement). Une correction échoue (\`application_failed\`) si un champ a changé depuis l'envoi
(\`stale\`), si le retrait viendrait avant la mise en service (\`inconsistent\`) ou si elle rendrait des
liens de la série contraires aux règles (\`incompatible\`, liens dans \`extensions.details\`).
Limite propre : 10 propositions de série par minute et par compte, communes avec le site
(\`too_many_requests\` au-delà).
"""
input MaterialSeriesContributionInput {
  "\`new\` (nouvelle série) ou \`correction\` (série de VFF désignée par \`material_slug\`)."
  mode: String!
  "Avec \`correction\` : identifiant d'URL de la série à corriger (ex. \`z-1200\`)."
  material_slug: String
  "Nom d'usage de la série, de 2 à 120 caractères (ex. « Z 1200 ») ; obligatoire avec \`new\`."
  name: String
  """
  Catégorie, qui dit aussi la traction : \`locomotive_electrique\`, \`locomotive_diesel\`,
  \`locomotive_vapeur\`, \`automotrice\`, \`bimode\`, \`autorail\`, \`rame_tgv\`, \`voiture\`, \`wagon\`,
  \`tramway\` ou \`engin_service\` ; obligatoire avec \`new\`.
  """
  type: String
  """
  Systèmes d'électrification de la série, sans doublon : \`dc_600\`, \`dc_750\`, \`dc_850\`, \`dc_1500\`,
  \`dc_3000\` (courant continu, en volts), \`ac_15k\` (15 kV 16,7 Hz), \`ac_20k\` (20 kV 50 Hz) ou
  \`ac_25k\` (25 kV 50 Hz). Vide ou absent : inchangé pour une correction.
  """
  electrification: [String!]
  """
  Écartements de la série en millimètres, sans doublon, quatre au plus (ex. \`[1435, 1668]\` pour une
  série à deux écartements) : \`1435\`, \`1000\`, \`600\`, \`1668\`, \`1520\`, ou un écartement que porte déjà
  une série de VFF (\`891\`, \`1588\`…). Vide ou absent : inchangé pour une correction.
  """
  gauges: [Int!]
  "Forme courte d'un seul écartement (\`gauges\` = \`[gauge_mm]\`), mêmes valeurs ; jamais avec \`gauges\`."
  gauge_mm: Int
  "Constructeurs, séparés par des virgules (ex. « Alsthom, Francorail-MTE »), de 2 à 255 caractères."
  builder: String
  "Année de mise en service, de 1800 à l'année en cours plus 30."
  in_service_from: Int
  "Année de retrait du service, au plus tôt celle de la mise en service."
  in_service_to: Int
  "Identifiant Wikidata de la série (\`Q3574398\`) ou adresse de l'élément, facultatif, vérifié auprès de Wikidata s'il change."
  wikidata_id: String
  """
  Avec \`correction\` : champs à effacer, sans valeur donnée à côté : \`builder\`, \`in_service_from\`,
  \`in_service_to\`, \`gauges\` ou \`electrification\`.
  """
  clear: [String!]
  "Nature de la source : \`web\` (page web), \`document\` (document numérisé) ou \`print\` (ouvrage ou archive papier)."
  source_kind: String!
  "Titre ou référence de la source, de 3 à 300 caractères."
  source_title: String!
  "Adresse de la source, en http ou https, 500 caractères au plus : obligatoire sauf avec \`print\`."
  source_url: String
  "Licence du document, obligatoire avec \`document\` et ignorée sinon (mêmes valeurs que \`EventContributionInput.source_license\`)."
  source_license: String
  "Note pour les validateurs, non publiée, 2 000 caractères au plus."
  notes: String
}


"""
Événement daté d'une ligne existante, comme dans le formulaire « Proposer un événement daté » du
site (\`/contribute/event\`). À la publication, il entre dans l'historique de la ligne (\`LineEvent\`,
source \`contrib\`, provenance \`contribution:<id>\`). Sa portion est résolue ensuite par une tâche à
part : la carte par décennie n'est pas recalculée à la publication.
"""
input EventContributionInput {
  "Identifiant d'URL de la ligne (ex. \`rfn-610000\`)."
  line_slug: String!
  """
  Type : \`opening\`, \`opening_passenger\`, \`opening_freight\`, \`closure_passenger\`, \`closure_freight\`,
  \`closure\`, \`reopening\`, \`reopening_passenger\`, \`reopening_freight\`, \`dismantling\`,
  \`electrification\`, \`declassification\`, \`modernization\`, \`conversion\`, \`heritage_start\`,
  \`heritage_end\`, \`concession\`, \`concession_end\` ou \`other\`. \`accident\` n'est pas proposé : les
  accidents ont leur propre fiche vérifiée.
  """
  type: String!
  "Précision de la date, celle que donne la source : \`day\`, \`month\` ou \`year\`."
  precision: String!
  "Date selon la précision : \`AAAA-MM-JJ\`, \`AAAA-MM\` ou \`AAAA\` (années 1750 et suivantes)."
  date: String!
  """
  Portion concernée : \`whole\` (toute la ligne), \`places\` (entre \`from\` et \`to\`) ou \`pk\` (entre
  \`pk_start_m\` et \`pk_end_m\`). Les champs de l'autre forme de portion sont ignorés.
  """
  portion: String!
  "Début de la portion, avec \`places\` : gare, halte ou commune écrite comme dans la source, 250 caractères au plus."
  from: String
  "Fin de la portion, avec \`places\` : différente de \`from\`, 250 caractères au plus."
  to: String
  "Début de la portion, avec \`pk\` : point kilométrique en mètres (PK 12+345 = 12345), de 0 à 9 999 999."
  pk_start_m: Int
  "Fin de la portion, avec \`pk\` : en mètres, strictement supérieure à \`pk_start_m\`."
  pk_end_m: Int
  "Nouvel usage de l'emprise, avec le type \`conversion\` seulement : \`greenway\`, \`road\`, \`path\`, \`urban\` ou \`other\`."
  reuse: String
  "Exploitation associative, avec \`heritage_start\` ou \`heritage_end\` seulement : \`tourist_train\`, \`velorail\` ou \`museum\`."
  heritage_kind: String
  "Précision publiée avec l'événement, une phrase de 300 caractères au plus ; obligatoire avec le type \`other\`."
  description: String
  "Nature de la source : \`web\` (page web), \`document\` (document numérisé) ou \`print\` (ouvrage ou archive papier)."
  source_kind: String!
  "Titre ou référence de la source, de 3 à 300 caractères (ex. « Journal officiel du 16 juin 1890, p. 2 »)."
  source_title: String!
  "Adresse de la source, en http ou https, 500 caractères au plus : obligatoire sauf avec \`print\`."
  source_url: String
  """
  Licence du document, obligatoire avec \`document\` et ignorée sinon : \`public_domain\`, \`cc0-1.0\`,
  \`cc-by-4.0\`, \`cc-by-sa-4.0\`, \`etalab-2.0\`, \`all_rights_reserved\` ou \`unknown\`. VFF ne reprend que
  le fait daté et le lien : un document sous droits peut être cité.
  """
  source_license: String
  "Note pour les validateurs, non publiée, 2 000 caractères au plus."
  notes: String
}


"""
Image rattachée à une ligne, une gare, un événement daté ou une série de matériel, comme dans le formulaire « Proposer une
image » du site (\`/contribute/media\`). Deux origines :
- \`commons\` : un fichier de Wikimedia Commons (\`commons_url\`). Auteur, licence et miniature sont lus
  sur Commons ; une licence non libre ou qui interdit l'usage commercial est refusée. L'image reste
  hébergée par Wikimedia ;
- \`upload\` : un fichier déposé (\`file\`, voir le scalaire \`Upload\`) avec son auteur, sa licence, sa
  date et son lieu. Type réel, taille et dimensions sont contrôlés ; les métadonnées du fichier
  (position GPS, appareil, dates) sont retirées avant l'enregistrement, seule l'orientation reste.
  Avant la publication, le fichier n'est montré qu'à son auteur et aux validateurs.

À la publication, le média entre dans \`Media\` avec sa provenance (\`contribution:<id>\`).
"""
input MediaContributionInput {
  "Origine : \`commons\` (fichier de Wikimedia Commons) ou \`upload\` (fichier déposé)."
  source: String!
  "Rattachement : \`line\` (une ligne), \`station\` (une gare), \`event\` (un événement daté d'une ligne) ou \`material\` (une série de matériel roulant)."
  target_type: String!
  "Identifiant d'URL de la ligne, avec \`line\` et \`event\` (ex. \`rfn-610000\`)."
  line_slug: String
  "Identifiant d'URL de la gare, avec \`station\` (ex. \`nontron\`)."
  station_slug: String
  "Identifiant d'URL de la série de matériel, avec \`material\` (ex. \`x-73500\`)."
  material_slug: String
  "Identifiant de l'événement (\`LineEvent.id\`), avec \`event\` : il doit appartenir à la ligne \`line_slug\`."
  event_id: ID
  """
  Avec \`commons\` : adresse de la page du fichier (\`https://commons.wikimedia.org/wiki/File:…\`),
  du fichier sur \`upload.wikimedia.org\`, ou son titre \`File:…\`, 500 caractères au plus.
  """
  commons_url: String
  "Avec \`upload\` : l'image, JPEG, PNG ou WebP (type lu dans le fichier), 10 Mo au plus, chaque côté de 400 à 12 000 pixels."
  file: Upload
  "Avec \`upload\` : nature de l'image, \`photo\`, \`postcard\`, \`plan\`, \`map\` ou \`document\`."
  media_type: String
  "Avec \`upload\` : titre, ce que montre l'image, de 3 à 250 caractères."
  title: String
  "Avec \`upload\` : légende facultative, 800 caractères au plus."
  caption: String
  "Avec \`upload\` : auteur de la photo ou du document, de 2 à 200 caractères, publié avec l'image."
  author: String
  "Avec \`upload\` : licence, \`public_domain\`, \`cc0-1.0\`, \`cc-by-4.0\`, \`cc-by-sa-4.0\` ou \`etalab-2.0\`."
  license: String
  "Avec \`upload\` : précision de la date de prise de vue, \`day\`, \`month\` ou \`year\` ; à donner avec \`captured_date\`."
  captured_precision: String
  "Avec \`upload\` : date de prise de vue selon la précision, \`AAAA-MM-JJ\`, \`AAAA-MM\` ou \`AAAA\`."
  captured_date: String
  "Avec \`upload\` : lieu de la prise de vue (gare, commune, lieu-dit), 250 caractères au plus."
  place: String
  "Avec \`upload\` : latitude WGS 84 en degrés décimaux (de -90 à 90), à donner avec \`lng\` ; publiée avec l'image."
  lat: Float
  "Avec \`upload\` : longitude WGS 84 en degrés décimaux (de -180 à 180), à donner avec \`lat\`."
  lng: Float
  "Avec \`upload\` : \`true\` pour attester le droit de partager ce fichier sous cette licence (obligatoire)."
  rights_confirmed: Boolean
  "Note pour les validateurs, non publiée, 2 000 caractères au plus."
  notes: String
}


"""
Modification d'une portion de ligne entre deux points kilométriques, à une ère : les mêmes champs
que le formulaire « Modifier une portion » du site (\`/contribute/edit-segment\`). Les tronçons de
la ligne qui rencontrent la portion reçoivent les nouvelles valeurs à la publication.
"""
input SegmentContributionInput {
  "Code de la ligne (6 chiffres pour le réseau ferré national, espaces ignorés : \`405000\` ou \`405 000\`)."
  line_code: String!
  "Ère visée : une décennie de 1830 à 2030 (2030 désigne le réseau d'aujourd'hui)."
  era_year: Int!
  "Début de la portion, en mètres depuis l'origine du tracé de la ligne à cette ère (0 à 9 999 999)."
  pk_start_m: Int!
  "Fin de la portion, en mètres, strictement supérieure à \`pk_start_m\` (au plus 9 999 999)."
  pk_end_m: Int!
  "Nouvelles valeurs : au moins une non nulle ; un champ absent ou nul reste inchangé."
  changes: SegmentChangesInput!
  """
  Sources de la modification, au moins une (adresse web ou référence : arrêté, ouvrage, archive),
  500 caractères au plus chacune. Les validateurs ne relisent pas une affirmation sans pièce.
  """
  sources: [String!]!
  "Remarques pour les validateurs, 2 000 caractères au plus."
  notes: String
}


"Nouvelles valeurs d'une portion ; un champ absent ou nul reste inchangé, \`false\` et \`0\` sont de vraies valeurs."
input SegmentChangesInput {
  """
  État de la voie, mêmes valeurs que \`LineSegment.status\` : \`in_service\` (exploitée),
  \`single_track\` (exploitée, à voie unique), \`service_track\` (voie de service ou portuaire),
  \`construction\` (en travaux), \`abandoned\` (fermée ou neutralisée, voie encore en place),
  \`dismantled\` (voie déposée, emprise encore visible), \`razed\` (emprise disparue).
  """
  status: String
  "Trafic, mêmes valeurs que \`LineSegment.traffic_type\` : \`passenger\`, \`freight\`, \`mixed\` ou \`unknown\`."
  traffic_type: String
  "Vrai si la portion est électrifiée."
  electrified: Boolean
  "Tension d'électrification en volts, de 0 à 50 000 (ex. 1500, 25000)."
  voltage_v: Int
  "Vitesse maximale en km/h, de 0 à 600."
  max_speed_kmh: Int
  "Vrai si la portion est exploitée par une association (train touristique, vélorail, musée)."
  is_heritage: Boolean
}


"""
Tracé dessiné, comme dans l'éditeur de tracé du site (\`/contribute/trace\`) : une ou plusieurs
portions, chacune avec ses événements datés et sourcés. À la publication, le tracé entre dans les
sources de VFF et les événements dans l'historique de la ligne (créée si \`kind\` vaut \`new_line\`).
"""
input TraceContributionInput {
  "\`new_line\` (ligne disparue absente de VFF) ou \`correction\` (ligne existante, voir \`line_slug\`)."
  kind: String!
  "Identifiant d'URL de la ligne corrigée (ex. \`rfn-405000\`) : obligatoire avec \`correction\`."
  line_slug: String
  "Nom de la nouvelle ligne, de 3 à 250 caractères : obligatoire avec \`new_line\`."
  new_line_name: String
  "Réseau : \`principal\`, \`secondaire\`, \`militaire\` ou \`particulier\`."
  network: String!
  "Fond de carte utilisé pour dessiner (ex. \`ign-etat-major\`), 60 caractères au plus."
  basemap: String
  "Source du tracé (carte, plan, ouvrage), de 3 à 500 caractères."
  trace_source: String!
  "Remarques pour les validateurs, 2 000 caractères au plus."
  notes: String
  "Portions du tracé, de 1 à 60."
  portions: [TracePortionInput!]!
  "Éléments existants que ce tracé remplace (facultatif) : les validateurs les voient en « avant »."
  replaces: TraceReplacesInput
}


"Une portion du tracé : une ligne brisée et ses événements."
input TracePortionInput {
  "Clé de la portion, unique dans la proposition (lettres, chiffres, \`_\` et \`-\`, 24 caractères au plus)."
  key: String!
  "Nom de la portion (ex. « Section Nontron - Thiviers »), 250 caractères au plus."
  label: String
  "Lieu de départ, 250 caractères au plus."
  from: String
  "Lieu d'arrivée, 250 caractères au plus."
  to: String
  """
  Points de la portion, de 2 à 8 000, chacun \`[longitude, latitude]\` en degrés WGS 84
  (EPSG:4326). La portion doit mesurer au moins 10 m.
  """
  coordinates: [[Float!]!]!
  "Événements datés de la portion, 30 au plus."
  events: [TraceEventInput!]
}


"Un événement daté d'une portion (ouverture, fermeture, dépose…)."
input TraceEventInput {
  """
  Type : \`opening\`, \`opening_passenger\`, \`opening_freight\`, \`closure_passenger\`,
  \`closure_freight\`, \`closure\`, \`reopening\`, \`reopening_passenger\`, \`reopening_freight\`,
  \`dismantling\`, \`electrification\`, \`declassification\` ou \`other\`.
  """
  type: String!
  "Précision de la date : \`day\`, \`month\` ou \`year\`."
  precision: String!
  "Date selon la précision : \`AAAA-MM-JJ\`, \`AAAA-MM\` ou \`AAAA\` (années 1750 et suivantes)."
  date: String!
  "Source de l'événement (ouvrage, archive, journal officiel), de 3 à 500 caractères."
  source: String!
  "Adresse de la source, en http ou https, 500 caractères au plus."
  source_url: String
  "Description de l'événement, 1 000 caractères au plus."
  description: String
}


"Éléments existants remplacés par un tracé (identifiants internes, ceux de l'éditeur du site)."
input TraceReplacesInput {
  "Tracés de source remplacés, 200 au plus."
  source_trace_ids: [Int!]
  "Tronçons de ligne remplacés, 2 000 au plus."
  line_segment_ids: [Int!]
  "Lignes remplacées, 5 au plus."
  line_ids: [Int!]
}


"Décision d'un validateur."
enum ContributionDecision {
  "Approuver : à la deuxième approbation indépendante, la contribution est publiée."
  APPROVE
  "Refuser, avec un motif (\`reason\`) transmis à l'auteur."
  REJECT
}


"Type d'une contribution."
enum ContributionType {
  "Modification d'une portion de ligne entre deux PK."
  LINE_SEGMENT
  "Tracé dessiné (ligne disparue ou correction)."
  LINE_TRACE
  "Événement daté d'une ligne."
  LINE_EVENT
  "Image (fichier de Wikimedia Commons ou fichier déposé)."
  MEDIA
  "Série de matériel qui a circulé sur une ligne."
  LINE_MATERIAL
  "Nouvelle série de matériel ou correction d'une série."
  MATERIAL_SERIES
}


"Nature d'une contribution."
enum ContributionAction {
  "Création (nouvelle ligne, événement daté, image, lien de matériel, nouvelle série)."
  CREATE
  "Modification d'une ligne ou d'une série existante."
  UPDATE
  "Suppression."
  DELETE
}


"État d'une contribution."
enum ContributionStatus {
  "En attente d'une première approbation."
  PENDING
  "Approuvée une fois, en attente d'un deuxième validateur."
  APPROVED
  "Refusée (motif dans \`review_notes\`)."
  REJECTED
  "Publiée : appliquée à l'encyclopédie."
  APPLIED
}


"Issue d'une relecture enregistrée."
enum ContributionReviewOutcome {
  "Approbation enregistrée, il en manque encore pour publier."
  APPROVED
  "Dernière approbation requise : la contribution est publiée."
  APPLIED
  "Contribution refusée."
  REJECTED
}


"Une contribution, telle que la voient son auteur et les validateurs."
type Contribution {
  "Identifiant de la contribution."
  id: ID!
  "Type de la contribution."
  type: ContributionType!
  "Création d'une ligne ou modification d'une ligne existante."
  action: ContributionAction!
  "État de la contribution."
  status: ContributionStatus!
  "Approbations reçues."
  approvals: Int!
  "Approbations indépendantes nécessaires pour publier (2)."
  approvals_required: Int!
  "Ligne visée (nulle pour une nouvelle ligne pas encore publiée et pour une image de gare)."
  line: Line
  "Gare visée par une image rattachée à une gare ; nulle sinon."
  station: Station
  "Série visée : série corrigée, série créée une fois publiée, série d'un lien \`LINE_MATERIAL\`, ou série à laquelle une image \`MEDIA\` est rattachée ; nulle sinon."
  material: Material
  """
  Proposition enregistrée, normalisée par le serveur (mêmes clés que sur le site) : pour
  \`LINE_SEGMENT\`, \`line_code\`, \`line_name\`, \`era_year\`, \`pk_start_m\`, \`pk_end_m\`, \`changes\`,
  \`sources\` et \`notes\` ; pour \`LINE_TRACE\`, les portions en GeoJSON (\`geometry\`), leurs
  événements aux dates normalisées, \`length_m\`, \`warnings\` et, une fois publiée, \`applied\` ; pour
  \`LINE_EVENT\`, \`line_slug\`, \`event_type\`, \`date\` (\`AAAA-MM-JJ\`) et \`precision\`, \`portion\`
  (\`kind\`, \`from\`, \`to\`, \`pk_start_m\`, \`pk_end_m\`), \`source\` (\`kind\`, \`title\`, \`url\`, \`license\`),
  \`description\`, \`notes\` et, une fois publiée, \`applied.line_event_id\` ; pour \`MEDIA\`, \`source\`,
  \`target_type\` et la fiche, puis \`commons\` (métadonnées lues sur Commons) ou, pour un fichier
  déposé, \`title\`, \`author\`, \`license\`, \`captured\`, \`place\`, \`location\` et \`file\` (type,
  dimensions, empreinte SHA-256, métadonnées retirées, jamais d'adresse publique avant la
  publication) et, une fois publiée, \`applied.media_id\` ; pour \`LINE_MATERIAL\`, \`line_slug\`,
  \`material_id\`, \`material_slug\`, \`material_name\`, \`circulation\`, \`period\` (\`start\` et \`end\` avec
  \`date\` et \`precision\`, \`from\`, \`to\`, ou nulle), \`source\`, \`quote\`, \`exception\` (règle levée par
  la phrase citée : \`rule\` \`gauge\` ou \`traction\`, \`reason\`), \`excluded\` (lien écarté à la relecture
  de l'import, motif), \`existing_link\`, \`notes\` et, une fois publiée, \`applied.line_material_id\` et
  \`applied.period_id\` ; pour \`MATERIAL_SERIES\`, \`mode\`, \`material_name\`, \`values\` (nouvelle série)
  ou \`changes\` (correction : \`before\`, \`after\` par champ, \`after\` nul pour une valeur effacée),
  \`impact\` (correction : liens que la correction rendrait contraires aux règles), \`wikidata\`
  (élément vérifié : \`id\`, \`label\`, \`description\`), \`source\`, \`notes\` et, une fois publiée,
  \`applied.material_id\`.
  """
  payload: JSON!
  "Motif du refus, transmis à l'auteur ; nul sinon."
  review_notes: String
  "Date d'envoi."
  created_at: DateTime!
  "Date de la dernière décision (refus ou publication) ; nulle sinon."
  reviewed_at: DateTime
}


"Résultat d'une relecture enregistrée."
type ContributionReview {
  "Ce que la décision a produit."
  outcome: ContributionReviewOutcome!
  "La contribution après la décision."
  contribution: Contribution!
}


"Information about pagination using a Relay style cursor connection."
type PageInfo {
  "When paginating forwards, are there more items?"
  hasNextPage: Boolean!
  "When paginating backwards, are there more items?"
  hasPreviousPage: Boolean!
  "The cursor to continue paginating backwards."
  startCursor: String
  "The cursor to continue paginating forwards."
  endCursor: String
  "Total number of nodes in the paginated connection."
  total: Int!
  "Number of nodes in the current page."
  count: Int!
  "Index of the current page."
  currentPage: Int!
  "Index of the last available page."
  lastPage: Int!
}


"A paginated list of Media edges."
type MediaConnection {
  "Pagination information about the list of edges."
  pageInfo: PageInfo!
  "A list of Media edges."
  edges: [MediaEdge!]!
}


"An edge that contains a node of type Media and a cursor."
type MediaEdge {
  "The Media node."
  node: Media!
  "A unique cursor that can be used for pagination."
  cursor: String!
}


"Information about pagination using a fully featured paginator."
type PaginatorInfo {
  "Number of items in the current page."
  count: Int!
  "Index of the current page."
  currentPage: Int!
  "Index of the first item in the current page."
  firstItem: Int
  "Are there more pages after this one?"
  hasMorePages: Boolean!
  "Index of the last item in the current page."
  lastItem: Int
  "Index of the last available page."
  lastPage: Int!
  "Number of items per page."
  perPage: Int!
  "Number of total available items."
  total: Int!
}


"A paginated list of Line items."
type LinePaginator {
  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!
  "A list of Line items."
  data: [Line!]!
}


"A paginated list of Station items."
type StationPaginator {
  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!
  "A list of Station items."
  data: [Station!]!
}


"A paginated list of Material edges."
type MaterialConnection {
  "Pagination information about the list of edges."
  pageInfo: PageInfo!
  "A list of Material edges."
  edges: [MaterialEdge!]!
}


"An edge that contains a node of type Material and a cursor."
type MaterialEdge {
  "The Material node."
  node: Material!
  "A unique cursor that can be used for pagination."
  cursor: String!
}


"A paginated list of Accident items."
type AccidentPaginator {
  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!
  "A list of Accident items."
  data: [Accident!]!
}


"A paginated list of LevelCrossing items."
type LevelCrossingPaginator {
  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!
  "A list of LevelCrossing items."
  data: [LevelCrossing!]!
}


"A paginated list of LineEvent edges."
type LineEventConnection {
  "Pagination information about the list of edges."
  pageInfo: PageInfo!
  "A list of LineEvent edges."
  edges: [LineEventEdge!]!
}


"An edge that contains a node of type LineEvent and a cursor."
type LineEventEdge {
  "The LineEvent node."
  node: LineEvent!
  "A unique cursor that can be used for pagination."
  cursor: String!
}


"Aggregate functions when ordering by a relation without specifying a column."
enum OrderByRelationAggregateFunction {
  "Amount of items."
  COUNT
}


"Aggregate functions when ordering by a relation that may specify a column."
enum OrderByRelationWithColumnAggregateFunction {
  "Average."
  AVG
  "Minimum."
  MIN
  "Maximum."
  MAX
  "Sum."
  SUM
  "Amount of items."
  COUNT
}


"Allows ordering a list of records."
input OrderByClause {
  "The column that is used for ordering."
  column: String!
  "The direction that is used for ordering."
  order: SortOrder!
}


"Specify if you want to include or exclude trashed results from a query."
enum Trashed {
  "Only return trashed results."
  ONLY
  "Return both trashed and non-trashed results."
  WITH
  "Only return non-trashed results."
  WITHOUT
}


`,Zs=Oc(),Qs=Yn(Kn.DIRECTIVES,[]),$s=oc(Zs.getQueryType()?.getFields()),ec=oc(Zs.getMutationType()?.getFields()),tc=oc(Zs.getSubscriptionType()?.getFields()),nc=Ys.default.keyBy(Dc(),e=>e.name.toLocaleLowerCase()),rc=Ys.default.mapValues(nc,e=>{let t=Qs.find(t=>t?.name===e.name||t?.name===`*`);return t?t.args.some(e=>e===`*`)?e.args:t.args.map(t=>e.args.find(e=>e.name===t)).filter(e=>!!e):[]}),ic=oc(Zs.getTypeMap()),ac=Hs(Zs);function oc(e){return Ys.default.mapKeys(e||{},(e,t)=>t.toLocaleLowerCase())}function sc(){return Ys.default.size(Zs.getTypeMap())<=10}function cc(){return[lc(`Queries`,Zs.getQueryType()),lc(`Mutations`,Zs.getMutationType()),lc(`Subscriptions`,Zs.getSubscriptionType()),kc(),fc()].filter(e=>!!e)}function lc(e,t){return dc(e,uc(t))}function uc(e){return Ys.default.sortBy(e?.getFields()||{},e=>e.name)}function dc(e,t){return t.length===0?null:{type:`menu`,title:e,children:t.map(t=>({type:`page`,title:t.name,section:e,deprecated:!!t.deprecationReason,href:zn.joinUrlPaths(Xn(),e.toLocaleLowerCase(),t.name)}))}}function fc(){return sc()?null:{type:`menu`,title:`Types`,children:Ys.default.sortBy(Ys.default.map(Zs.getTypeMap()),e=>e.name).filter(e=>!e.name.startsWith(`__`)).map(e=>({type:`page`,title:e.name,section:`Types`,href:zn.joinUrlPaths(Xn(),`types`,e.name)}))}}function pc(){return!!Zs.getQueryType()}function mc(e){return $s[e.toLocaleLowerCase()]}function hc(){return!!Zs.getMutationType()}function gc(e){return ec[e.toLocaleLowerCase()]}function _c(){return!!Zs.getSubscriptionType()}function vc(e){return tc[e.toLocaleLowerCase()]}function yc(e){return ic[e.toLocaleLowerCase()]}function bc(e){return nc[e.toLocaleLowerCase()]}function xc(e){return bc(e.name)!==void 0}function Sc(){return Ys.default.size(nc)>0}function Cc(e){return rc[e.name.toLocaleLowerCase()]||[]}function wc(e){if(e)return ac.getFor(e)}function Tc(e){return Ys.default.flatMap(e.getFields(),t=>({field:t,possibleDescriptions:Ec(t,e)}))}function Ec(e,t){return e?e.description?[{description:e.description,from:t}]:W(t)?t.getInterfaces().flatMap(t=>Ec(t.getFields()[e.name],t)):[]:[]}function Dc(){return Qs.some(e=>e?.name===`*`)?Zs.getDirectives().filter(e=>![`include`,`skip`,`deprecated`,`specifiedBy`].includes(e.name)):Qs.filter(e=>!!e?.name).map(({name:e})=>e?Zs.getDirective(e):void 0).filter(e=>!!e)}function Oc(){return zs(Xs)}function kc(){let e=Dc();return e.length===0?null:{type:`menu`,title:`Directives`,children:e.map(e=>({type:`page`,title:e.name,href:zn.joinUrlPaths(Xn(),`directives`,e.name),section:`Directives`}))}}var Ac=Yn(Kn.APP_TITLE,`GraphQL Documentation`),jc=Pc().concat(cc());Ic(jc);var Mc=Object.freeze(jc),Nc=Fc();function Pc(){return Yn(Kn.PAGES,Hc()).filter(e=>!!e).map(e=>Bc([],e))}function Fc(){let e=Lc();if(e)return e.href;throw Error(`No custom pages or query available to use as the root application URL. You need to provide at least one custom page or your schema should contain at least one query/mutation/subscription.`)}function Ic(e){function t(e,n){for(let r of e){if(r.type===`page`){n(r);continue}r.type===`menu`&&t(r.children,n)}}let n;t(e,e=>{n&&(n.next={title:e.title,section:e.section,href:e.href},e.previous={title:n.title,section:n.section,href:n.href}),n=e})}function Lc(){return zc(()=>!0)}function Rc(e){return zc(t=>t.href.toLocaleLowerCase()===e.toLocaleLowerCase())}function zc(e){function t(n){for(let r of n){if(r.type===`page`&&e(r))return r;if(r.type===`menu`){let e=t(r.children);if(e)return e}}return null}return t(Mc)}function Bc(e,t){if(typeof t.content==`string`)return{type:`page`,title:t.title,content:t.content,href:zn.joinUrlPaths(Xn(),...e,Vc(t.title))};let n=e.concat([Vc(t.title)]);return{type:`menu`,title:t.title,children:t.content.map(e=>({...Bc(n,e),section:t.title}))}}function Vc(e){return zn.generatePathSegment(e,new at)}function Hc(){return[{title:`Introduction`,content:[{title:`Welcome`,content:`
        # Welcome 🎉

        [Congratulations!](https://www.youtube.com/watch?v=1Bix44C1EzY) You have successfully created your first Magidoc website.

        Now that you are up and running, you can customize this website even further by providing some configuration inside your [magidoc.mjs ⚙️](https://magidoc.js.org/cli/magidoc-configuration). 
        If you wish to remove or modify this page, have a look at the *customPages* configuration.
        `.split(`
`).map(e=>e.trim()).join(`
`)}]}]}export{Ui as A,zn as B,Ni as C,Fi as D,Ri as E,Jn as F,ge as G,Dn as H,Yn as I,he as K,Xn as L,ji as M,Mi as N,Gi as O,I as P,Kn as R,Ki as S,G as T,at as U,j as V,nt as W,xc as _,Cc as a,Js as b,gc as c,yc as d,wc as f,_c as g,pc as h,Mc as i,W as j,K as k,mc as l,hc as m,Rc as n,bc as o,Sc as p,Nc as r,Tc as s,Ac as t,vc as u,sc as v,Pi as w,Vs as x,Zs as y,Rn as z};