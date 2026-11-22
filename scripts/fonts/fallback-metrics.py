#!/usr/bin/env python3
"""Calcule les @font-face des polices de repli ajustées de resources/css/general.css.

Pour chaque police web (@fontsource, fichier .woff du sous-ensemble latin) et chaque graisse utilisée, la
police de repli est Arial (ou son équivalent métrique Liberation Sans / Arimo) réglée avec :
  unicode-range     = caractères de la police web (le reste garde Helvetica Neue / Arial inchangés) ;
  size-adjust       = largeur moyenne des lettres de la police web / celle d'Arial, pondérée par la fréquence
                      des lettres du français (les espaces comptent), puis corrigée par la mesure sur textes
                      réels (CORRECTION) ;
  ascent-override, descent-override, line-gap-override = métriques hhea de la police web / size-adjust.

Colle le résultat dans resources/css/general.css puis passe Prettier (il ne change que la mise en forme).
Usage : python3 scripts/fonts/fallback-metrics.py [dossier des .ttf de Liberation Sans]
Dépendance : fonttools (pip install fonttools, dans un environnement virtuel).
"""
import sys

from fontTools.ttLib import TTFont

LIBERATION = (sys.argv[1] if len(sys.argv) > 1 else '/usr/share/fonts/liberation-sans-fonts') + '/LiberationSans-%s.ttf'
FREQUENCES = {
    'e': 14.7, 'a': 7.6, 'i': 7.5, 's': 7.9, 'n': 7.1, 'r': 6.6, 't': 7.2, 'o': 5.4, 'l': 5.5, 'u': 6.3,
    'd': 3.7, 'c': 3.2, 'm': 3.0, 'p': 3.0, 'é': 1.9, 'v': 1.6, 'q': 1.4, 'f': 1.1, 'b': 0.9, 'g': 1.0,
    'h': 0.8, 'j': 0.5, 'à': 0.5, 'x': 0.4, 'y': 0.3, 'z': 0.3, ' ': 17.0,
}
SOURCES = {
    'Regular': "local('Arial'), local('ArialMT'), local('Liberation Sans'), local('Arimo')",
    'Bold': "local('Arial Bold'), local('Arial-BoldMT'), local('Liberation Sans Bold'), local('Arimo Bold')",
    'Italic': "local('Arial Italic'), local('Arial-ItalicMT'), local('Liberation Sans Italic'), local('Arimo Italic')",
}
# Correction mesurée dans le navigateur (canvas, textes réels des pages : article du journal, /api, accueil, fiche de
# ligne, accidents, journal) : largeur du texte en police web / largeur en repli. Absente (1,0) pour les graisses non
# mesurées (police pas chargée sur ces pages).
CORRECTION = {
    ('Lato', '400'): 0.9875,
    ('Lato', '700'): 0.9708,
    ('Gabarito', '700'): 0.9871,
    ('Inter', '400'): 0.9819,
    ('Inter', '700'): 0.998,
}
POIDS = {'300': '300 400', '400': '400', '700': '600 700'}
POLICES = [
    ('Lato', 'lato', [('400', 'normal', 'Regular'), ('700', 'normal', 'Bold'), ('300', 'italic', 'Italic')]),
    ('Gabarito', 'gabarito', [('400', 'normal', 'Regular'), ('700', 'normal', 'Bold')]),
    ('Inter', 'inter', [('400', 'normal', 'Regular'), ('700', 'normal', 'Bold')]),
    ('Montserrat', 'montserrat', [('400', 'normal', 'Regular'), ('700', 'normal', 'Bold')]),
]


def largeur_moyenne(police):
    cmap, hmtx, upm = police.getBestCmap(), police['hmtx'], police['head'].unitsPerEm
    total = pondere = 0.0
    for lettre, frequence in FREQUENCES.items():
        glyphe = cmap.get(ord(lettre))
        if glyphe:
            total += frequence
            pondere += frequence * hmtx[glyphe][0] / upm
    return pondere / total


def plages(police):
    """Caractères réellement présents dans la police web, en plages unicode-range : les autres (flèches, symboles)
    continuent d'aller à Helvetica Neue / Arial sans réglage, comme avant l'ajout des polices de repli."""
    codes = sorted(c for c in police.getBestCmap() if c >= 0x20 and c != 0x7F)
    morceaux, debut, fin = [], codes[0], codes[0]
    for c in codes[1:]:
        if c == fin + 1:
            fin = c
            continue
        morceaux.append((debut, fin))
        debut = fin = c
    morceaux.append((debut, fin))
    return ','.join('U+%04X' % a if a == b else 'U+%04X-%04X' % (a, b) for a, b in morceaux)


def metriques(police):
    upm, hhea = police['head'].unitsPerEm, police['hhea']
    return hhea.ascent / upm, -hhea.descent / upm, hhea.lineGap / upm


for famille, dossier, graisses in POLICES:
    for graisse, style, repli in graisses:
        web = TTFont('node_modules/@fontsource/%s/files/%s-latin-%s-%s.woff' % (dossier, dossier, graisse, style))
        arial = TTFont(LIBERATION % repli)
        ajust = largeur_moyenne(web) / largeur_moyenne(arial) * CORRECTION.get((famille, graisse), 1.0)
        asc, desc, ecart = metriques(web)
        print("@font-face {\n    font-family: '%s Fallback';\n    font-style: %s;\n    font-weight: %s;" % (famille, style, POIDS[graisse]))
        print('    src: %s;\n    size-adjust: %.2f%%;\n    ascent-override: %.2f%%;' % (SOURCES[repli], ajust * 100, asc / ajust * 100))
        print('    descent-override: %.2f%%;\n    line-gap-override: %.2f%%;' % (desc / ajust * 100, ecart / ajust * 100))
        print('    unicode-range: %s;\n}' % plages(web))
