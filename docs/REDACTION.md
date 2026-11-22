# Rédaction des textes : LanguageTool et relecture

Les textes visibles de VFF vivent dans `lang/{fr,en,de}/*.php` (français de référence, anglais britannique,
allemand en « Sie »). Ce document explique comment les relire avec LanguageTool, lancé en local, et rappelle les
règles de rédaction.

## Lancer LanguageTool en local

LanguageTool (licence LGPL 2.1) tourne dans un conteneur Podman, sans sudo et sans service externe : les textes ne
quittent pas la machine. L'image `docker.io/erikvl87/languagetool` est l'image communautaire la plus utilisée
(dépôt `Erikvl87/docker-languagetool`, LGPL 2.1) ; elle suit les versions de LanguageTool (6.8 en juin 2026).

```bash
podman pull docker.io/erikvl87/languagetool:6.8
podman run -d --name vff-languagetool --memory 1g \
    -e Java_Xms=256m -e Java_Xmx=768m \
    -p 127.0.0.1:8010:8010 docker.io/erikvl87/languagetool:6.8
```

Le serveur écoute seulement sur `127.0.0.1:8010`. Il met une vingtaine de secondes à démarrer. Pour vérifier :

```bash
curl -s -d "language=fr&text=Bonjour le monde." http://127.0.0.1:8010/v2/check
```

Une fois la relecture terminée, arrêtez le conteneur (sans supprimer l'image) :

```bash
podman stop vff-languagetool
```

Pour le relancer plus tard : `podman start vff-languagetool`.

## Lancer le rapport

```bash
node scripts/languagetool-check.mjs --out=storage/app/reports/langue.md
```

Le script extrait toutes les chaînes visibles de `lang/<langue>/*.php` (y compris le gabarit de `generated.php`, lu
sans jamais être modifié), masque les paramètres (`:nom`, `{{nombre}}`, `{year}`) et les balises HTML pour qu'ils ne
soient pas signalés, puis classe les remarques de LanguageTool en orthographe, grammaire et style.

Options :

| Option                        | Effet                                                                                                          |
| :---------------------------- | :------------------------------------------------------------------------------------------------------------- |
| `--lang=fr,de`                | limite aux langues données (par défaut `fr,en,de`)                                                             |
| `--file=home,lines`           | limite aux fichiers de `lang/<langue>/` donnés                                                                 |
| `--picky`                     | ajoute les règles de style du niveau « picky » (beaucoup de bruit, à lire avec le sens)                        |
| `--passive`                   | repère les tournures passives (indicatif : une forme passive naturelle et juste reste permise)                 |
| `--tics`                      | repère les tics de texte généré : contrastes « pas seulement X mais Y », ouvertures mises en scène, mots creux |
| `--out=fichier.md`            | écrit le rapport dans un fichier au lieu de la sortie standard                                                 |
| `--url=http://127.0.0.1:8010` | autre adresse du serveur LanguageTool                                                                          |
| `--concurrency=3`             | nombre de requêtes simultanées                                                                                 |

L'allemand est la langue la plus lente (une trentaine de minutes pour tout le site avec 1 Go de mémoire). Pour un contrôle
rapide, limitez-vous à un fichier : `--lang=fr --file=home`.

Les noms propres ou sigles que le dictionnaire ne connaît pas se déclarent dans `scripts/languagetool-mots.txt`
(un mot par ligne). Quelques faux positifs restent normaux : chaînes qui sont des fragments (libellés), noms de
gares, mots techniques. Décidez chaque remarque une par une.

## Règles de rédaction

- Du texte humain : phrases courtes, sujet concret, verbes plutôt que noms. La voix active n'est pas obligatoire :
  une tournure passive naturelle et juste reste correcte (« la contribution est publiée »).
- Pas de tics de texte généré : contrastes « non pas X mais Y », phrases de chute d'une ligne, ouvertures mises en
  scène (« Découvrez », « Plongez »), triades forcées, affirmations gonflées, langage commercial, mots creux.
- Vouvoiement en français, « Sie » en allemand, orthographe britannique en anglais.
- Jamais de tiret long, de tiret moyen ni de double trait d’union comme ponctuation (`tests/Unit/Static/TypographicDashTest.php`).
- Ne pas toucher aux paramètres (`:nom`, `{{nombre}}`), aux clés, aux gabarits de `lang/fr/generated.php` reconnus
  par `EventText` (voir le glossaire de traduction), ni au fond des textes légaux (`legal.php`, `privacy.php`,
  `use_conditions.php`, `accessibility.php` : fautes seulement).
- Vérifier après chaque série de corrections :
  `bin/php artisan vff:i18n-check --strict`, `bin/php vendor/bin/pint --test lang`, puis les tests PHPUnit des textes.

## Journal de bord : écrire un article

Un article est un fichier Markdown par langue dans `resources/content/journal/`, nommé `<slug>.<langue>.md`
(`les-hirondelles.fr.md`, `les-hirondelles.en.md`). Le français est obligatoire ; une langue absente est remplacée
par le texte français, signalé au lecteur. Le fichier commence par un en-tête entre deux lignes `---`, une clé par
ligne (`clé: valeur`, guillemets facultatifs ; une clé seule ouvre une liste `  - valeur` ou un dictionnaire
`  clé: valeur`) :

| Clé | Obligatoire | Contenu |
|---|---|---|
| `title` | oui | Titre de l'article (seul h1 de la page). |
| `date` | oui | Date de publication `AAAA-MM-JJ` ; un article daté du futur n'apparaît que ce jour-là. |
| `updated` | non | Date de la dernière mise à jour. |
| `summary` | oui | Résumé de 300 caractères au plus (liste, aperçus de partage, flux). |
| `lines` | non | Codes RFN des lignes dont parle l'article (lien depuis leur fiche). |
| `stations` | non | Slugs des gares citées. |
| `image` | non | Photo de Wikimedia Commons : `src` (vignette `upload.wikimedia.org`), `width`, `height`, `alt`, `caption`, `author`, `licence`, `licence_url`, `source_url` (page du fichier). |
| `disclosure` | non | Engagement, affiché en tête de l'article (obligatoire quand l'équipe soutient une cause dont parle l'article). |

Dans le texte : sections en `##` (le sommaire les reprend ; jamais de `#`), liens vers les fiches par
`[texte](ligne:878000)` ou `[texte](gare:morez)` (ancre possible : `ligne:878000#chronicle`), sources en notes
`[^nom]` définies en fin de texte avec leur lien. Aucun HTML : il est affiché tel quel. L'article est signé
« L'équipe VFF », jamais par une personne. Mêmes règles de rédaction que le reste du site (ci-dessus), relecture par une
deuxième personne, faits vérifiés et sources citées ; `tests/Feature/JournalContentTest.php` contrôle les
en-têtes, les notes, les liens et la typographie.

