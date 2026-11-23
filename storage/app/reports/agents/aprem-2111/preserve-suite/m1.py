import re,json
from parse1 import *
o=json.load(open('wp_sncf_items.json'))
PAST=re.compile(r'anciennement|ferraill|démoli|détruit|dépec|vendu|revendu|<s>|retourn|disparu|incendi',re.I)
res=[]
for x in o:
    t=x['text']
    if '<s>' in x['raw'] or PAST.search(t): x['k']='past'; res.append(x); continue
    m=re.match(r'^(.*?)\s*(?::|,|;| – | - |\.)\s+(.*)$',t)
    num,rest=(m.group(1),m.group(2)) if m else (t,'')
    x['num']=num.strip(); x['rest']=rest
    hits=mt(x['num'])
    x['hits']=[h['slug'] for h in hits]
    x['k']='ok' if hits else 'nomatch'
    res.append(x)
import collections
print(collections.Counter(x['k'] for x in res))
new=[x for x in res if x['k']=='ok' and nrm(x['num']) not in have]
print('new',len(new),'already',sum(1 for x in res if x['k']=='ok' and nrm(x['num']) in have))
json.dump(res,open('wp_sncf_res.json','w'),ensure_ascii=False)
for x in res:
    if x['k']=='nomatch': print('NM',x['sec'][1],'|',x['num'][:50],'|',x['rest'][:60])
