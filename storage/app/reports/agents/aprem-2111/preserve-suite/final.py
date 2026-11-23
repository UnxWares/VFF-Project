import re,json,collections
from parse1 import *
import build1 as B   # réutilise find_venue, state_of, venue_text...
from build1 import find_venue,venue_text,state_of,vtype,slugv,FOREIGN,CV,matsby,byname,newv,items as WP
pal={r['Reference']:r for r in json.load(open('/tmp/claude-1000/-mnt-Unified-Datas-BaptGosse-Pro-UnxWares-Projects-VFF-VFF-Project/d701a02d-189a-43a2-99f0-19f5c77f21ae/scratchpad/mp/palissy_rail.json'))}
tour=json.load(open('tour_raw.json'))
def palmh(ref,fallback):
    r=pal.get(ref)
    if r:
        t=r['Date_et_typologie_de_la_protection']
        m=re.match(r'(\d{4})/(\d\d)/(\d\d)\s*:\s*(classé|inscrit|déclassé)',t)
        if m:
            kind={'classé':'classified','inscrit':'listed','déclassé':'declassified'}[m.group(4)]
            return {'kind':kind,'date':f'{m.group(1)}-{m.group(2)}-{m.group(3)}','year':int(m.group(1)),'ref':ref}
    return fallback
TOK=lambda s:{w for w in re.findall(r'[a-z0-9]{3,}',fold(s)) if w not in {'chemin','fer','chemins','train','touristique','vapeur','association','amicale','des','les','pour','sud','vallee','pays','tourist','musee','ecomusee','amis','conservation','preservation','autorail','autorails','patrimoine'}}
def tour_link(name):
    t=TOK(name); best=None
    acr={fold(a) for a in re.findall(r'\(([A-Za-z0-9\.\- ]{2,12})\)',name)}
    for e in tour:
        et=TOK(e['name']); ea={fold(a) for a in re.findall(r'\(([A-Za-z0-9\.\- ]{2,12})\)',e['name'])}
        if not t or not et: continue
        inter=t&et
        sc=len(inter)/len(t|et) if inter else 0
        if acr&ea: sc=max(sc,0.9)
        if sc>=0.5 and len(inter)>=2 or sc>=0.9:
            if not best or sc>best[0]: best=(sc,e['name'])
    return best[1] if best else None
units=collections.OrderedDict()   # (slug,nrm)->entry
def put(e):
    k=(e['slug'],nrm(e['number']))
    if k in units:
        o=units[k]
        for f in ('venue','where','state','mh'):
            if o[f] is None and e[f] is not None: o[f]=e[f]
        for s in e['src']:
            if s not in o['src']: o['src'].append(s)
    else: units[k]=e
for i in WP:
    num=re.sub(r'\s*\+.*$','',i['number']); num=re.sub(r'\s*\(.*\)$','',num).strip()
    num=re.sub(r'-R[C]?$','',num) if False else num
    i=dict(i,number=num)
    if i['mh'] and i['mh'].get('ref'): i['mh']=palmh(i['mh']['ref'],i['mh'])
    if i['where']:
        w=i['where']
        if re.search(r"'\s|\s'|\(\s*\)",w):
            c=re.search(r'(Pays-Bas|Suisse|Belgique|Allemagne)',w); w='Conservée à l’étranger' + (' ('+c.group(1)+')' if c else '')
        i['where']=w
    put(i)
# --- Cité du Train
rows=json.load(open('cdt_rows.json'))
SK=[]
CDT_REV=241565103
for sec,r in rows:
    if sec in ('Fréquentation','Voitures à voyageurs','Fourgons','Wagons de marchandises',"Engins d'infrastructure",'Fourgon/Tombereau automoteurs','Motrice à crémaillère','Tender',"Remorque d'autorail"): continue
    cells=[c.strip() for c in r.split('||')]
    if len(cells)<4: continue
    name=cells[0]; site=re.sub(r'\[\[Fichier[^\]]*\]\]','',cells[3])
    if not site.strip(): continue
    m=re.search(r'\[\[([^|\]]+)\|([^\]]*)\]\]',name); 
    if not m or re.match(r'(?i)fichier',m.group(1)):
        SK.append(('nolink',name[:60])); continue
    target=m.group(1).strip(); disp=m.group(2)
    slug=byname.get(fold(target))
    if not slug: SK.append(('notarget',target)); continue
    base=re.sub(r'<ref.*','',name)
    d=re.sub(r'\{\{nobr\|([^}]*)\}\}',r'\1',disp)
    d=re.sub(r'\{\{n°\|([\d.]+)\}\}',r'\1',d)
    tail=re.sub(r'<br>.*$','',re.sub(r'^\[\[[^\]]*\]\]','',name)).strip()
    WORDS=r'^(?:American|Ten Wheel|Pacific|Mikado|Consolidation|Atlantic|Baltic|Mountain|Decapod|Prairie|Northern|Hudson|Bourbonnais|Crampton|Engerth|Sprague|Buddicom)\s+'
    d2=re.sub(WORDS,'',d.strip())
    cands=[d2]
    for p in re.findall(r'\(([^)]*\d[^)]*)\)',re.sub(r'\{\{n°\|([\d.]+)\}\}',r'\1',tail)):
        cands.append(re.sub(r'^ex\s*','',p,flags=re.I).strip())
    sname=matsby[slug]['name']
    code=re.sub(r'\s*[\d"«»][\d\s\-à,.et]*$','',sname).strip()
    code=re.sub(r'\s+(Est|Nord|Ouest|Midi|PO|PLM|État|Etat|T|AT)$','',code)
    num=None
    for c in cands:
        c=re.sub(r'\s*\(.*$','',c).strip()
        if re.search(r'\d',c) and len(c)<24 and nrm(c).startswith(nrm(code)):
            num=c; break
    if not num: SK.append(('nonum',disp[:50],cands)); continue
    # numéro de compagnie seul (231 4546) : ajouter la compagnie lue dans le nom de la série
    if re.fullmatch(r'\d{3}\s\d+(?:\.\d+)?',num):
        co=re.search(r'\b(Est|Nord|Ouest|Midi|PO|PLM|État)\b',sname)
        if co: num=f"{num.split()[0]} {co.group(1)} {num.split()[1]}"
    s=site; venue=None; where=None; st=None
    if re.search(r'réserve',s) and re.search(r'Châlons',s):
        venue='cite-du-train-reserve-de-chalons-en-champagne'; B.newv.setdefault(venue,{'name':'Cité du Train, réserve de Châlons-en-Champagne','type':'site','commune':'Châlons-en-Champagne','tourist':None}); st='stored'
    elif re.search(r'Cité du Train',s):
        venue='cite-du-train'
        if re.search(r'réserve',s): st='stored'
    elif re.search(r'Mohon',s):
        venue='annexe-de-la-cite-du-train-remise-de-mohon'; B.newv.setdefault(venue,{'name':'Annexe de la Cité du Train (remise de Mohon)','type':'site','commune':'Charleville-Mézières','tourist':None})
    elif re.match(r'(?i)confi',s) or re.search(r'Circule|Mulhouse',s):
        ss=re.sub(r'\[\[(?:[^|\]]*\|)?([^\]]*)\]\]',r'\1',s).replace('"','')
        ss=re.sub(r'<ref.*','',ss)
        t=re.sub(r"(?i)^confiée?\s+(?:à|au|aux)\s+(?:(?:les|le|la|l['’])\s*)?",'',ss).strip()
        t=re.split(r'\s*\(depuis|\s+en\s+(?:20\d\d)|\s+\(anciennement|,',t)[0].strip()
        k=find_venue(t) if re.match(r'(?i)confi',s) else None
        if k: venue=k
        else:
            where='Confiée : '+re.sub(r'\s+',' ',ss)[:130] if False else None
            SK.append(('venue',num,s[:90])); continue
    else: SK.append(('site',num,s[:60])); continue
    src=[['wikipedia','Cité du train',CDT_REV]]
    put({'slug':slug,'number':num,'venue':venue,'where':where,'state':st,'mh':None,'src':src})
print(len(units),'entrées',len(SK),'écartées')
for s in SK: print('SKIP',s)
json.dump({'units':[v for v in units.values()],'newv':B.newv},open('final_raw.json','w'),ensure_ascii=False,indent=1)
