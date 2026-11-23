import re,json
from parse1 import *
txt=open('wp_Cit_du_train.txt').read().split('\n')
sec=None; rows=[]; hdr={}
i=0
buf=None
for line in txt:
    m=re.match(r'^(=+)\s*(.*?)\s*=+$',line)
    if m: sec=m.group(2); continue
    if sec is None or not (81<=txt.index(line) if False else True): pass
    if line.startswith('|-'):
        if buf: rows.append((sec,buf))
        buf=''
        continue
    if buf is not None and (line.startswith('|') or line.startswith('!') ):
        if line.startswith('|}'): rows.append((sec,buf)) if buf else None; buf=None; continue
        if line.startswith('!') : continue
        buf+=(' ' if buf else '')+line[1:]
    elif buf is not None and line.strip(): buf+=' '+line
json.dump(rows,open('cdt_rows.json','w'),ensure_ascii=False)
secs=set(r[0] for r in rows); print(len(rows),secs)
for s,r in rows:
    if s in ('Locomotives à vapeur','Autorails','Locomotives diesel','Locomotives électriques','Turbotrains','Automotrices (diesel et électrique)','Trains à Grande Vitesse','Locotracteurs','Tender','Remorque d\'autorail'):
        cells=r.split('||')
        print(s[:8],'|',re.sub(r'\s+',' ',cells[0])[:110],'|',[re.sub(r'\[\[Fichier[^\]]*\]\]','',c)[:60] for c in cells[3:5]])
