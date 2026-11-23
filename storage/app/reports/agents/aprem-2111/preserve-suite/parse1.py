import re,json,sys
sys.path.insert(0,'/mnt/Unified_Datas/BaptGosse/Pro/UnxWares/Projects/VFF/VFF-Project/storage/app/reports/agents/aprem-2111/materiel-preserve')
from lib import *
mats=[]
for l in open('materials.tsv'):
    p=l.rstrip('\n').split('\t'); mats.append({'slug':p[0],'name':p[1],'type':p[2],'wp':p[3],'wd':p[4]})
mt=build_matcher(mats)
cur=json.load(open('cur.json'))
have={}
for s,us in cur['units'].items():
    for u in us: have.setdefault(nrm(u['number']),set()).add(s)
def clean(t):
    t=re.sub(r'<ref[^>]*/>','',t); t=re.sub(r'<ref.*?</ref>','',t,flags=re.S)
    return t
def parse(path):
    sec=None; out=[]
    for line in open(path):
        line=line.rstrip('\n')
        m=re.match(r'^(=+)\s*(.*?)\s*=+$',line)
        if m: sec=(len(m.group(1)),re.sub(r'\[\[(?:[^|\]]*\|)?([^\]]*)\]\]',r'\1',m.group(2))); 
        if not line.startswith('*'): continue
        raw=line
        pal=re.findall(r'Base Palissy\|(P[AM]\d+|IM\d+)',raw)
        mh=re.search(r'\{\{(Classé|Inscrit) MH\|(\d{4})\}\}',raw)
        t=clean(raw[1:].strip())
        t=re.sub(r'\{\{Numéro avec majuscule\|(\d+)\}\}',r'\1',t)
        t=re.sub(r'\{\{(?:Classé|Inscrit) MH[^}]*\}\}','',t)
        t=re.sub(r'\{\{[Aa]nglais\|([^}]*)\}\}',r'\1',t)
        t=re.sub(r'\{\{[^}]*\}\}','',t)
        links=re.findall(r'\[\[([^\]|]+)(?:\|([^\]]*))?\]\]',t)
        t=re.sub(r'\[\[(?:[^|\]]*\|)?([^\]]*)\]\]',r'\1',t).replace("'''",'').replace("''",'')
        out.append({'sec':sec,'raw':raw,'text':t.strip(),'pal':pal,'mh':(mh.group(1),int(mh.group(2))) if mh else None,'links':links})
    return out
if __name__=='__main__':
    o=parse('wp_Matriel_moteur_prserv_de_la_SNCF.txt')
    print(len(o))
    json.dump(o,open('wp_sncf_items.json','w'),ensure_ascii=False)
    for x in o[:5]+o[300:305]: print(x['sec'],x['text'])
