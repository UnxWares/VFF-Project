import re,json
from parse1 import fold
STOP={'chemin','fer','chemins','train','touristique','vapeur','association','amicale','des','les','pour','sud','vallee','pays','tourist','musee','ecomusee','amis','conservation','preservation','autorail','autorails','patrimoine','de','du','la','le','l','et','au','aux','en','par','d','dit'}
def toks(s): return {w for w in re.findall(r'[a-z0-9]{2,}',fold(s)) if w not in STOP}
def acr_of(s):
    return {fold(a) for a in re.findall(r'\(([A-Za-z0-9\.\- ]{2,14})\)',s)}
def extract_name(rest,links):
    """nom du lieu de conservation dans la phrase"""
    t=rest.strip()
    t=re.sub(r"^(?:pr[ée]serv[ée]e?|conserv[ée]e?|expos[ée]e?|gar[ée]e?|sauvegard[ée]e?)\s+(?:en état de (?:marche|présentation)\s+|en restauration\s+(?:\(\)\s*)?|hors service\s+)?","",t,flags=re.I)
    m=re.match(r"(?:par|à|au|chez|dans|aux)\s+(?:(?:le|la|les|l['’])\s*)?(.+)$",t,flags=re.I)
    if not m: return None
    t=m.group(1)
    # coupe à la fin du nom
    t=re.split(r"\.\s|;|,\s+(?:ex|loco|machin|sans|vid|prop|trans|ren|pr[ée]sent|actuel)|\s+Locomotive\s|\s+Autorail\s",t)[0]
    t=re.sub(r"\s+(?:à|en|au|dans|près)\s+[A-ZÉ].*$",lambda mm: '' if re.search(r'(?:Mus[ée]e|Mine)',t[:mm.start()]) is None and False else mm.group(0),t)
    t=t.strip(' .;,')
    return t or None
