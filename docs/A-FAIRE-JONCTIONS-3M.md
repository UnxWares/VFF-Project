# Jonctions : tracés propres à moins de 3 m du réel (à faire)

Demande de l'utilisateur, 2026-10-20 (~10 h 20) : « je veux que ça soit parfait les jonctions, donc encore une fois
ce qu'on fait avec le GPU est à allier aussi avec un algo qui nous fait vraiment des tracés propres à - de 3 m d'écart
du réel ». Accord pour écrire en base les tracés GPU, mais seulement une fois cette exigence tenue.

## Exigence précisée par l'utilisateur (2026-10-20, ~10 h 25), elle prime sur les « 3 m »

« Normalement on doit être précis au mètre près, mais j'accepte une tolérance de 5 m au niveau du tracé ; par contre
je n'accepte pas que les 2 lignes qui font une jonction aient un écart. On peut avoir 5 m de décalage avec la réalité,
mais sur la carte ça doit se rejoindre correctement. »
- Précision absolue : visée ~1 m, acceptation p90 ≤ 5 m, 0 faux au-delà de 10 m.
- Continuité aux jonctions : 0 m, exigence absolue, sur la géométrie dessinée à toutes les ères (sommet partagé,
  arrivée tangente, ni trou ni escalier ni chevauchement décalé).

## Point de départ (mesuré)

- Passe GPU aux jonctions (service vff-gpu-jonctions, run national `jonctions-fr-ortho-1950`, 5 396 corridors) :
  contrôle sur 511 corridors à vérité connue (`jonctions-eval-gpu`, recalage à 15 m) : écart médian 2,7 m,
  p90 12,7 m, 5,1 % de faux à plus de 20 m ; 75 km gardés sur 251 km bruts. Rien n'est écrit en base.
- Modèle `ortho-v1` (orthos IGN 1950-1965, 1,6 m/px au z16) : écart médian 2,2 m, p90 6,5 m hors jonctions.
- Moteur : raccord tangent lissé (« raccord-lisse ») de la phase 3, marqué comme estimé.
- Plancher physique : précision de géoréférencement des orthos historiques IGN et des sources de vérité
  (BD TOPO ~2,5 m, OSM ~2 m) : à mesurer, et à dire honnêtement s'il empêche les 3 m quelque part.

## Ce qu'il faut construire (sans GPU : les probabilités sont déjà calculées sur disque)

1. **Axe sous-pixel** : extraire l'axe de la voie des cartes de probabilité par ajustement local (crête,
   profil gaussien transversal) plutôt qu'au pixel, pour viser moins de 1 m sur l'axe dans l'image.
2. **Modèle d'aiguillage à la jonction** : près de la voie précise, les deux voies se confondent dans l'image.
   La branche part TANGENTE à la voie précise au point d'aiguillage, avec une géométrie d'appareil de voie
   (tangente 1/9 à 1/12 selon le réseau, puis courbe de rayon ferroviaire), raccordée à l'axe extrait là où les
   voies sont séparées (au moins 6 à 10 m). Le point d'aiguillage est cherché le long de la voie précise
   (OSM, BD TOPO) par ajustement à l'axe extrait.
3. **Géométrie ferroviaire** : lissage par alignements, arcs et clothoïdes (rayons plausibles selon le réseau),
   jamais de coude ; contrôle de courbure.
4. **Fusion multi-dates** quand le GPU sera de nouveau autorisé : orthos 1950-1965 et 1965-1980 pour moyenner
   les erreurs de géoréférencement (inférence à relancer seulement sur accord de l'utilisateur).
5. **Erreur estimée par morceau** (écart au modèle, accord entre dates et sources, confiance) et **filtre strict** :
   n'écrire comme « précis » que les morceaux dont l'erreur estimée est sous 3 m ; les autres restent « estimés »
   (raccord lissé), jamais présentés comme précis.
6. **Validation** sur les 511 corridors à vérité connue, plus un échantillon indépendant : objectif p90 ≤ 3 m
   sur les morceaux gardés, et 0 faux à plus de 10 m. Captures avant/après sur le vrai site.
7. **Écriture** (`ml/rail-extraction/run.sh write`, verrou `.lancement-autorise`), puis passe nationale
   (build-line-references, build-era-segments, build-shared-track), puis détecteur des défauts visibles.

## Contraintes

- GPU : seulement sur accord explicite de l'utilisateur (verrou dans run.sh). Ce chantier n'en a pas besoin
  tant qu'il travaille sur les probabilités déjà calculées.
- RAM : au moins 3 000 Mo libres avant une commande lourde ; un seul Chrome à la fois (README des agents).
