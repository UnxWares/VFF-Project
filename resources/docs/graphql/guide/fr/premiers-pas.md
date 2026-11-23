# Premiers pas

*Français · [English]({{siteRoot}}/guide-en/getting-started) · [Deutsch]({{siteRoot}}/guide-de/erste-schritte)*

Dix requêtes complètes, à coller dans [GraphiQL]({{origin}}/graphiql) ou à envoyer en `POST` sur `{{origin}}/graphql`. Chacune est vérifiée contre le schéma à chaque modification. Les identifiants (`rfn-420000`, `brest`) sont les `slug` des fiches du site : celui d'une ligne ou d'une gare se lit dans l'adresse de sa page.

Les requêtes sont nommées et prennent leurs valeurs dans des variables quand elles en ont : c'est la forme à préférer dans un programme, parce que le texte de la requête ne change pas d'un appel à l'autre.

## 1. Une ligne et ses sections à une date

Les tronçons (`segments`) d'une ligne à une ère : état, trafic, électrification, vitesse, bornes kilométriques et provenance de chaque tronçon. Sans `era`, la requête rend toutes les ères.

{{query:01-line-at-date}}

Variables :

{{variables:01-line-at-date}}

`status` dit si le tronçon est en service, fermé voie en place ou déposé à la fin de cette année. La provenance (`provenance`) donne le fournisseur du tracé et sa licence.

## 2. Les gares d'une ligne à une ère

`stops` liste les gares dans l'ordre du parcours, avec leur point kilométrique (`pk_label`) et leur état à l'ère demandée (`effective_status`). Sans `era`, c'est aujourd'hui.

{{query:02-line-stations}}

## 3. Une gare et son histoire par ère

La fiche d'une gare donne son état à chaque ère (`eras`), les lignes qui la desservent et ses voisines.

{{query:03-station-eras}}

`opened_at` se lit avec la précision de la date : une gare datée de l'année seule n'a pas de jour.

## 4. Le profil en long d'une ligne

Altitude le long du tracé, avec les extrêmes, les dénivelés cumulés et la pente maximale en pour mille. `max_points` allège la réponse : le profil garde les deux bouts, le point le plus bas et le plus haut. `profile` est nul tant que le profil de la ligne n'est pas calculé.

{{query:04-line-profile}}

## 5. Les accidents d'une ligne

Date et sa précision, nature, bilan humain et sources lues avec leur licence. Une fiche dont `review_status` vaut `to_review` a un point incertain : présentez-la comme telle. Pour chercher dans tous les accidents, voir `accidents(filter: …)`.

{{query:05-line-accidents}}

## 6. Le matériel d'une ligne

`material_links` garde la preuve de chaque lien entre une ligne et une série de matériel : source, fiabilité (`confidence`) et période. `materials` donne la liste seule, sans la preuve.

{{query:06-line-materials}}

## 7. La recherche

Une ligne, une gare, un matériel ou un accident par son nom, 2 caractères au moins. `limit` borne chaque catégorie (8 par défaut, 20 au plus). Le champ coûte plus cher qu'un champ simple : voir les limites de l'introduction.

{{query:07-search}}

Variables :

{{variables:07-search}}

## 8. Les médias et leurs droits

Chaque média porte son auteur, sa licence, une ligne de crédit prête à afficher (`attribution`) et le lien vers sa page d'origine. `rights_status` permet de ne garder que les images du domaine public ; `derivatives_ok` à `false` signale une licence sans modification (affichez l'image entière, jamais recadrée) ; `commercial_ok` et `attribution_required` complètent le tableau. Ne republiez jamais une image sans reprendre ces champs.

{{query:08-media-rights}}

## 9. Ce qui a changé entre deux ères

Kilomètres et nombre de lignes ouverts, fermés voie en place, déposés ou restés en service entre deux ères, avec la longueur en service à chaque date. Mêmes chiffres que `GET /api/v1/eras/{year}/compare/{other}`. C'est un champ lourd (250 points) : une seule comparaison par requête.

{{query:09-era-comparison}}

## 10. Parcourir une liste par curseur

Les fermetures au trafic voyageurs dans le Finistère entre 1930 et 1969, vingt à la fois. Pour la page suivante, renvoyez la même requête avec `after` valant la valeur de `pageInfo.endCursor`, tant que `hasNextPage` est vrai.

{{query:10-paginated-events}}

Variables de la première page :

{{variables:10-paginated-events}}

Chaque événement garde sa source (`source_url`) et la licence de celle-ci (`provenance`).

## Pour aller plus loin

- La fiche complète d'une ligne ou d'une gare, comme sur le site, est dans `Line.sheet` et `Station.sheet` ; son calcul prend jusqu'à quelques secondes, demandez-en une à la fois.
- `Line.pk(lat:, lng:)` convertit des coordonnées en point kilométrique sur la ligne ; `route(from:, to:, era:)` calcule un itinéraire à une date.
- Les annuaires `lineDirectory` et `stationDirectory` reprennent les filtres et le tri des pages /lines et /stations.
- Chaque requête et chaque type ont leur page dans la référence, avec les arguments, les types imbriqués et les champs dépréciés.
