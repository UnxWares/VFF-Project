import json,re,collections
from parse1 import nrm,fold
import final as F
cur=json.load(open('cur.json'))
ex={}; exnum=set()
for s,us in cur['units'].items():
    for u in us: ex[(s,nrm(u['number']))]=u; exnum.add(nrm(u['number']))
f=json.load(open('final_raw.json'))
ALIAS={'amicale-des-anciens-et-amis-de-la-traction-vapeur-centre-val-de-loire-aaatv-cvl':'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv'}
for u in f['units']:
    if u['venue'] in ALIAS: u['venue']=ALIAS[u['venue']]
FIX={'CC 1110':'Garée aux ateliers de Béziers','BB 66014':'Stationnée sur les voies du dépôt de Nevers (propriété SNCF)','Z 103':None}
for u in f['units']:
    if u['number'] in FIX and u['where'] is not None: u['where']=FIX[u['number']]
    if u['where']: u['where']=u['where'].replace(' au Pays-Bas',' aux Pays-Bas')
out=collections.OrderedDict()
n_new=n_comp=0
for u in f['units']:
    k=(u['slug'],nrm(u['number']))
    if k in ex and ex[k]['venue'] is not None: u['venue']=None; u['where']=None
    if k in ex:
        o=ex[k]; add=[x for x in ('venue','where','state') if o[x] is None and u[x] is not None]
        if o['mh'] is None and u['mh'] and u['mh'].get('kind'): add.append('mh')
        if not add and all(s in o['src'] for s in u['src']): continue
        if not add: continue
        n_comp+=1
    else:
        if nrm(u['number']) in exnum: continue
        n_new+=1
    if k in ex and ex[k]['venue'] is not None: u['venue']=None   # le lieu connu reste
    if u['mh'] and not u['mh'].get('kind'): u['mh']=None; u['src']=[s for s in u['src'] if s[0]!='palissy']
    out.setdefault(u['slug'],[]).append(u)
print('new',n_new,'completed',n_comp)
used=set(u['venue'] for us in out.values() for u in us if u['venue'])
newv={k:v for k,v in f['newv'].items() if k in used and k not in cur['venues']}
for k in used:
    assert k in cur['venues'] or k in newv,k
# liens touristiques pour les nouveaux lieux
for k,v in newv.items():
    if v['type']=='tourist_railway': v['tourist']=F.tour_link(v['name'])
def cl(s): return s.replace('—','-').replace('–','-')
def q(s):
    s=cl(s); return "'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
def val(v):
    if v is None: return 'null'
    if isinstance(v,int): return str(v)
    return q(v)
def src(s):
    if s[0]=='wikipedia': return f"['wikipedia', {q(s[1])}, {s[2]}]"
    if s[0]=='palissy': return f"['palissy', {q(s[1])}]"
    return f"['wikidata', {q(s[1])}]"
def mh(m):
    if not m: return 'null'
    return "['kind' => %s, 'date' => %s, 'year' => %s, 'ref' => %s]"%(val(m.get('kind')),val(m.get('date')),val(m.get('year')),val(m.get('ref')))
L=[]
L.append("""<?php

/**
 * Complément du matériel roulant préservé (même forme que preserved_rolling_stock.php, qu'il complète sans le modifier) :
 * lu et fusionné par App\\Services\\Materials\\PreservedRollingStock. Un exemplaire déjà connu (même série, même numéro) ne reçoit
 * que ce qui lui manquait (lieu, état, protection, sources), un nouveau est ajouté.
 *
 * Sources, faits seulement (numéro, lieu, état dit par la source, protection ; jamais d'état deviné) :
 *   - Wikipédia en français (CC BY-SA 4.0), articles « Matériel moteur préservé de la SNCF » (version 241069362) et « Cité du train »
 *     (version 241565103), lus par l'API MediaWiki le 23/11/2026 (User-Agent du projet, 5 s entre deux requêtes) ; un exemplaire que
 *     la liste dit ferraillé, démantelé ou vendu n'est pas repris ; la série est celle de la section ou du lien de l'article ;
 *   - Palissy (POP, ministère de la Culture, Licence Ouverte 2.0) : date et nature de la protection des notices que Wikipédia cite
 *     (référence PM…), lues dans le fichier palissy.csv du 13/11/2026.
 *
 * @return array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>}
 */
return [
    'venues' => [""")
for k in sorted(newv):
    v=newv[k]
    L.append("        %s => ['name' => %s, 'type' => %s, 'commune' => %s, 'tourist_railway' => %s],"%(q(k),q(v['name']),q(v['type']),val(v['commune']),val(v['tourist'])))
L.append("    ],\n    'units' => [")
for s in sorted(out):
    L.append("        %s => ["%q(s))
    for u in sorted(out[s],key=lambda u:[int(x) if x.isdigit() else x for x in re.split(r'(\d+)',u['number'])]):
        L.append("            ['number' => %s, 'venue' => %s, 'where' => %s, 'state' => %s, 'mh' => %s, 'src' => [%s]],"%(q(u['number']),val(u['venue']),val(u['where']),val(u['state']),mh(u['mh']),', '.join(src(x) for x in u['src'])))
    L.append("        ],")
L.append("    ],\n    'others' => [],\n];\n")
open('complement.php','w').write('\n'.join(L))
print(len(out),'séries',len(newv),'nouveaux lieux')
