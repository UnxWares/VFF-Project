import re,json,collections
from parse1 import *
from venues1 import toks,acr_of
res=json.load(open('wp_sncf_res.json'))
REV=241069362; TITLE='Matériel moteur préservé de la SNCF'
cur=json.load(open('cur.json'))
CV=cur['venues']
matsby={m['slug']:m for m in mats}
byname={fold(m['name']):m['slug'] for m in mats}
FOREIGN=r"\s+(?:en|au|aux|dans le|à)\s+(?:Suisse|Belgique|Allemagne|Pays-Bas|Turquie|Canada|Guadeloupe|Italie|Espagne|Autriche|Luxembourg|Royaume-Uni|Angleterre|Portugal|Algérie|Tunisie|Maroc)\b.*$"
def heading_slug(h):
    h=re.sub(r'^\d-','',h).strip()
    return byname.get(fold(h))
def venue_text(rest):
    t=rest.strip()
    t=re.sub(r"^(?:pr[ée]serv[ée]e?|conserv[ée]e?|expos[ée]e?|gar[ée]e?|sauvegard[ée]e?|serait pr[ée]serv[ée]e?)\s+(?:en état de (?:marche|présentation)\s+|en restauration\s+(?:\(\)\s*)?|hors service\s+|en attente de restauration\s+)?",'',t,flags=re.I)
    m=re.match(r"(?:par|à|au|chez|dans|aux)\s+(?:(?:les|le|la|l['’])\s*)?(.+)$",t,flags=re.I)
    if not m: return None
    t=m.group(1)
    t=re.split(r"\.\s|\.$|;|,|\s+Locomotive\s|\s+Autorail\s",t)[0]
    return t.strip(' .;,')
def state_of(t):
    f=fold(t)
    if re.search(r'restauration|chantier',f): return 'restoration'
    if re.search(r'etat de marche',f): return 'running'
    if re.search(r'expos',f): return 'exposed'
    if re.search(r'hors service|\bgare|\ben reserve|\breserves?\b|attente de convention',f): return 'stored'
    return None
# existing venues index
vidx=[(k,toks(v['name']),acr_of(v['name']),v) for k,v in CV.items()]
def find_venue(name):
    n=re.sub(FOREIGN,'',name)
    base=re.split(r"\s+(?:au|à|en)\s+(?=[A-ZÉ])",n)[0].strip()
    a={fold(x) for x in re.findall(r'\(([A-Za-z0-9\.\- ]{2,14})\)',base)}
    ta=toks(base)
    best=None
    for k,tk,ak,v in vidx:
        sc=0
        if a and (a&ak or any(x in {fold(y) for y in ak} for x in a)): sc=1.0
        elif ta and tk:
            inter=ta&tk
            if inter:
                sc=len(inter)/max(len(ta),len(tk))
                if len(ta)==1 and len(tk)>1 and (sc<1 and not (len(list(ta)[0])>=5 and list(ta)[0] in tk)): sc=0
                if len(ta)==1 and len(list(ta)[0])>=5 and list(ta)[0] in tk: sc=max(sc,0.8)
        if sc>=0.6 and (not best or sc>best[0]): best=(sc,k)
    return best[1] if best else None
def slugv(s): return re.sub(r'-+','-',re.sub(r'[^a-z0-9]+','-',fold(s))).strip('-')[:80]
def vtype(name):
    f=fold(name)
    if re.search(r'musee|ecomusee|conservatoire|rotonde|musee',f): return 'museum'
    if re.search(r'association|amicale|club|cercle|groupement|\baaatv|compagnie internationale|stichting',f): return 'association'
    if re.search(r'chemin|train|tramway|velorail|vapeur|autorail|draisine|tacot|velay express',f): return 'tourist_railway'
    return None
newv={}
items=[]; skipped=[]
for x in res:
    if x['k']=='past': continue
    if x['k']=='ok': slugs=x['hits'][:1]
    else:
        # repli : intitulé de la section
        hs=heading_slug(x['sec'][1]); 
        if not hs or not re.search(r'\d',x.get('num','')): skipped.append(x); continue
        # le numéro doit avoir le code de la série de la section
        code=re.sub(r'\s*[\d\"«»][\d\s\-à,.et]*$','',matsby[hs]['name']).strip()
        if not nrm(code) or not nrm(x['num']).startswith(nrm(code)): skipped.append(x); continue
        slugs=[hs]
    num=re.sub(r'\s+',' ',x['num']).strip()
    if '*' in num or len(num)>28 or not re.search(r'\d',num): skipped.append(x); continue
    rest=x.get('rest','')
    st=state_of(rest)
    vt=venue_text(rest)
    venue=None; where=None
    if vt:
        k=find_venue(vt)
        if k: venue=k
        else:
            nm=re.sub(FOREIGN,'',vt); nm=re.split(r"\s+(?:au|à|en)\s+(?=[A-ZÉ])",nm)[0].strip()
            nm=re.sub(r"\s+en version.*$|\s+\(sans remorque\)$","",nm)
            if re.match(r'(?i)^cit[ée] du train',nm): venue='cite-du-train'; nm=None
            ty=vtype(nm) if nm else None
            foreign=re.search(FOREIGN,vt)
            if venue: pass
            elif ty and not foreign and len(nm)>12 and not re.search(r'(?i)^(association|muséeotrain|appf \()',nm):
                key=slugv(nm); newv.setdefault(key,{'name':nm[:1].upper()+nm[1:],'type':ty,'commune':None,'tourist':None}); venue=key
            else:
                where=rest.strip()
    elif rest:
        where=rest.strip()
    if where:
        where=re.sub(r"\.\s+(?:Locomotive|Autorail|Automotrice).*$",'',where).strip(' .;,')
        where=where[:1].upper()+where[1:]
        if len(where)>150: where=where[:147].rsplit(' ',1)[0]+'...'
    mh=None
    if x['pal'] or x['mh']:
        kind={'Classé':'classified','Inscrit':'listed'}[x['mh'][0]] if x['mh'] else None
        mh={'kind':kind,'date':None,'year':x['mh'][1] if x['mh'] else None,'ref':x['pal'][0] if x['pal'] else None}
    src=[['wikipedia',TITLE,REV]]
    if x['pal']: src.append(['palissy',x['pal'][0]])
    items.append({'slug':slugs[0],'number':num,'venue':venue,'where':where,'state':st,'mh':mh,'src':src})
print(len(items),'items;',len(skipped),'skipped;',len(newv),'new venues')
json.dump({'items':items,'newv':newv},open('b1.json','w'),ensure_ascii=False,indent=1)
n=0
for i in items:
    k=nrm(i['number'])
    tag='HAVE' if k in have else 'NEW'
    if tag=='NEW': n+=1
print('new',n)
for k,v in newv.items(): print('NV',k,v['type'])
for s in skipped: print('SK',s['sec'][1],'|',s.get('num','')[:40])
