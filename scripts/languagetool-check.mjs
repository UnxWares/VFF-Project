#!/usr/bin/env node
// Relecture des textes de l'interface avec LanguageTool, lancé en local (voir docs/REDACTION.md).
//
//   node scripts/languagetool-check.mjs                      toutes les langues, rapport Markdown sur la sortie
//   node scripts/languagetool-check.mjs --lang=fr,de         certaines langues seulement
//   node scripts/languagetool-check.mjs --file=home,lines    certains fichiers de lang/<langue>/ seulement
//   node scripts/languagetool-check.mjs --picky              règles de style supplémentaires (niveau « picky »)
//   node scripts/languagetool-check.mjs --passive            ajoute le repérage des tournures passives (indicatif : le passif reste permis quand il est naturel)
//   node scripts/languagetool-check.mjs --tics               ajoute le repérage des tics de texte généré (contrastes, ouvertures mises en scène, mots creux)
//   node scripts/languagetool-check.mjs --out=fichier.md     écrit le rapport dans un fichier
//   node scripts/languagetool-check.mjs --url=http://127.0.0.1:8010   adresse du serveur LanguageTool
//
// Le script extrait toutes les chaînes visibles de lang/<langue>/*.php (le gabarit de generated.php est lu, jamais
// modifié), masque les paramètres (:nom, {{nombre}}, {ère}) et les balises pour que LanguageTool ne les signale pas,
// puis classe ses remarques en orthographe, grammaire et style. Les noms propres inconnus du dictionnaire se
// déclarent dans scripts/languagetool-mots.txt (un mot par ligne, `#` pour commenter).
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const racine = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const options = Object.fromEntries(
    process.argv
        .slice(2)
        .filter((argument) => argument.startsWith('--'))
        .map((argument) => {
            const [cle, ...valeur] = argument.slice(2).split('=');
            return [cle, valeur.length > 0 ? valeur.join('=') : 'true'];
        }),
);

const langues = (options.lang ?? 'fr,en,de').split(',');
const fichiers = options.file ? options.file.split(',') : null;
const url = options.url ?? 'http://127.0.0.1:8010';
const picky = options.picky === 'true';
const passif = options.passive === 'true';
const tics = options.tics === 'true';
const simultanes = Number(options.concurrency ?? 3);

// Code de langue envoyé à LanguageTool : l'anglais est britannique, l'allemand est celui d'Allemagne.
const CODES = { fr: 'fr', en: 'en-GB', de: 'de-DE' };

const motsConnus = new Set(
    existsSync(join(racine, 'scripts/languagetool-mots.txt'))
        ? readFileSync(join(racine, 'scripts/languagetool-mots.txt'), 'utf8')
              .split('\n')
              .map((ligne) => ligne.trim())
              .filter((ligne) => ligne !== '' && !ligne.startsWith('#'))
        : [],
);

/** Aplatit lang/<langue>/*.php en { 'fichier.cle.sous_cle': 'texte' } grâce au PHP du conteneur de développement. */
function chargerChaines(langue) {
    const programme = `
        $out = [];
        $walk = function ($valeur, string $chemin) use (&$walk, &$out) {
            if (is_array($valeur)) {
                foreach ($valeur as $k => $v) { $walk($v, $chemin . '.' . $k); }
            } elseif (is_string($valeur)) {
                $out[ltrim($chemin, '.')] = $valeur;
            }
        };
        foreach (glob('lang/${langue}/*.php') as $f) {
            $walk(require $f, basename($f, '.php'));
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    `;
    const sortie = execFileSync(join(racine, 'bin/php'), ['-r', programme], {
        cwd: racine,
        maxBuffer: 256 * 1024 * 1024,
    }).toString();
    const chaines = JSON.parse(sortie);
    if (!fichiers) {
        return chaines;
    }
    return Object.fromEntries(
        Object.entries(chaines).filter(([cle]) => fichiers.includes(cle.split('.')[0])),
    );
}

const MASQUES = /(\{\{\s*\w+\s*\}\}|\{[\w.]+\}|:[a-z_]+\b|<[^>]+>|&[a-z]+;)/giu;

/** Texte annoté pour LanguageTool : les parties masquées sont lues comme « 12 » et ne sont jamais corrigées. */
function annoter(texte, valeur = '12') {
    const annotation = [];
    let reste = texte;
    let correspondance;
    MASQUES.lastIndex = 0;
    let dernier = 0;
    while ((correspondance = MASQUES.exec(reste)) !== null) {
        // « :nom » n'est un paramètre que précédé d'une espace, d'une parenthèse ou du début de chaîne (évite « http: »).
        const avant = correspondance.index === 0 ? ' ' : reste[correspondance.index - 1];
        if (correspondance[0].startsWith(':') && !/[\s(«"'’\u00a0]/u.test(avant)) {
            continue;
        }
        if (correspondance.index > dernier) {
            annotation.push({ text: reste.slice(dernier, correspondance.index) });
        }
        annotation.push({
            markup: correspondance[0],
            interpretAs: correspondance[0].startsWith('<') ? '' : valeur,
        });
        dernier = correspondance.index + correspondance[0].length;
    }
    if (dernier < reste.length) {
        annotation.push({ text: reste.slice(dernier) });
    }
    return annotation;
}

/** Longueur, dans le texte envoyé à LanguageTool, d'une annotation (les décalages comptent le texte et les balises). */
function longueurAnnotee(annotation) {
    return annotation.reduce((somme, partie) => somme + (partie.text ?? partie.markup).length, 0);
}

/**
 * Vérifie plusieurs chaînes en une requête : elles sont séparées par des paragraphes vides, puis les remarques sont
 * rattachées à leur chaîne d'après leur décalage.
 */
async function verifierLot(langue, lot) {
    const annotation = [];
    const bornes = [];
    let position = 0;
    for (const [cle, texte] of lot) {
        const partie = annoter(texte, cle.endsWith('_one') ? '1' : '12');
        bornes.push({ cle, texte, debut: position });
        annotation.push(...partie);
        position += longueurAnnotee(partie);
        annotation.push({ text: '\n\n' });
        position += 2;
    }
    const corps = new URLSearchParams({
        language: CODES[langue],
        data: JSON.stringify({ annotation }),
        level: picky ? 'picky' : 'default',
    });
    let matches = null;
    for (let essai = 0; essai < 3 && matches === null; essai++) {
        try {
            const reponse = await fetch(`${url}/v2/check`, { method: 'POST', body: corps });
            if (reponse.ok) {
                matches = (await reponse.json()).matches;
            } else if (reponse.status === 400) {
                matches = [];
            }
        } catch {
            // Serveur occupé : nouvel essai après une courte pause.
        }
        if (matches === null) {
            await new Promise((suite) => setTimeout(suite, 1000 * (essai + 1)));
        }
    }
    if (matches === null) {
        throw new Error(`LanguageTool ne répond pas sur ${url} (voir docs/REDACTION.md)`);
    }
    return bornes.map((borne, index) => {
        const fin = index + 1 < bornes.length ? bornes[index + 1].debut : Infinity;
        return {
            cle: borne.cle,
            texte: borne.texte,
            remarques: matches
                .filter((m) => m.offset >= borne.debut && m.offset < fin)
                .map((m) => ({ ...m, offset: m.offset - borne.debut })),
        };
    });
}

/** Catégorie d'une remarque : orthographe, grammaire, style (typographie comprise). */
function categorie(remarque) {
    const id = remarque.rule.id;
    const type = remarque.rule.issueType;
    if (/MORFOLOGIK|SPELLER|HUNSPELL|SPELLING/u.test(id) || type === 'misspelling') {
        return 'orthographe';
    }
    if (
        type === 'style' ||
        type === 'typographical' ||
        type === 'register' ||
        type === 'locale-violation'
    ) {
        return 'style';
    }
    return 'grammaire';
}

// Repérage des tournures passives : être conjugué suivi d'un participe passé (heuristique, à lire avec le sens).
const PASSIFS = {
    fr: /\b(est|sont|était|étaient|sera|seront|serait|seraient|soit|soient|été|être|étant)\s+(?:(?:déjà|aussi|ensuite|ainsi|toujours|jamais|pas|plus|bien|alors|automatiquement|ensuite)\s+)*([a-zéèêàâîôûç]+(?:é|ée|és|ées|i|is|ie|ies|it|its|ite|ites|u|us|ue|ues|s|te|tes|ts))\b/giu,
    en: /\b(is|are|was|were|be|been|being)\s+(?:(?:also|then|already|always|never|not|automatically|further|currently)\s+)*([a-z]+(?:ed|en|wn|ne|lt|nt|ft|ld|id|ut|et|ad))\b/giu,
    de: /\b(wird|werden|wurde|wurden|worden|würde|würden)\b[^.;:!?]*?\b(ge\w+(?:t|en)|\w+iert)\b/giu,
};
const FAUX_PASSIFS = {
    fr: /\b(est|sont|était|soit|sera|seront)\s+(?:une?|des?|le|la|les|en|à|de|du|ce|cet|cette|ces|là|ici|plus|aussi|très|pas|si|donc|souvent|toujours|encore|libre|vrai|prêt|prêts|gratuit|gratuite|gratuits|gratuites|ouvert|ouverte|ouverts|ouvertes|fermé|fermée|fermés|fermées|situé|située|situés|situées|inconnu|inconnue|inconnus|inconnues|lié|liée|liés|liées|concerné|concernée|concernés|concernées|disponible|disponibles|public|publique|publics|publiques|possible|actuel|visible|visibles|utile)\b/giu,
};

// Tics de texte généré : contrastes « pas X mais Y », ouvertures mises en scène, mots creux, langage commercial.
const TICS = {
    fr: /\b(?:non (?:pas )?seulement|pas seulement|ne se (?:limite|contente) pas|il ne s'agit pas|il ne s’agit pas|plongez|découvrez|explorez|en somme|en résumé|au cœur de|véritable|incontournable|riche patrimoine|il est important de noter|n'hésitez pas|n’hésitez pas|tout simplement|véritablement|à la croisée)\b/giu,
    en: /\b(?:not (?:just|only|merely)|it(?:'|’)s not|delve|dive into|discover|explore|in a nutshell|in summary|at the heart of|rich (?:heritage|history)|tapestry|testament|pivotal|crucial|vibrant|seamless(?:ly)?|robust|showcase|don(?:'|’)t hesitate|simply)\b/giu,
    de: /\b(?:nicht nur|nicht bloß|nicht einfach|tauchen sie|entdecken sie|erkunden sie|zusammenfassend|im herzen|reiches erbe|nahtlos|zögern sie nicht|einfach nur|wahrhaft|unverzichtbar)\b/giu,
};

function reperagesTics(langue, texte) {
    const motif = TICS[langue];
    const trouvailles = [];
    motif.lastIndex = 0;
    let m;
    while ((m = motif.exec(texte)) !== null) {
        trouvailles.push({ debut: m.index, extrait: m[0] });
    }
    return trouvailles;
}

function reperagesPassifs(langue, texte) {
    const motif = PASSIFS[langue];
    const trouvailles = [];
    motif.lastIndex = 0;
    let m;
    while ((m = motif.exec(texte)) !== null) {
        trouvailles.push({ debut: m.index, extrait: m[0] });
    }
    return trouvailles;
}

function extrait(texte, debut, longueur) {
    const a = Math.max(0, debut - 30);
    const b = Math.min(texte.length, debut + longueur + 30);
    return (
        (a > 0 ? '…' : '') +
        texte.slice(a, debut) +
        '[' +
        texte.slice(debut, debut + longueur) +
        ']' +
        texte.slice(debut + longueur, b) +
        (b < texte.length ? '…' : '')
    );
}

async function parLots(entrees, traitement) {
    const resultats = new Array(entrees.length);
    let prochain = 0;
    const travailleurs = Array.from({ length: simultanes }, async () => {
        while (prochain < entrees.length) {
            const index = prochain++;
            resultats[index] = await traitement(entrees[index]);
        }
    });
    await Promise.all(travailleurs);
    return resultats;
}

const rapport = [];
const totaux = {};

for (const langue of langues) {
    const chaines = Object.entries(chargerChaines(langue));
    process.stderr.write(`${langue} : ${chaines.length} chaînes\n`);
    const lots = [];
    let courant = [];
    let taille = 0;
    for (const entree of chaines) {
        courant.push(entree);
        taille += entree[1].length;
        if (courant.length >= 25 || taille > 5000) {
            lots.push(courant);
            courant = [];
            taille = 0;
        }
    }
    if (courant.length > 0) {
        lots.push(courant);
    }
    const resultats = (await parLots(lots, (lot) => verifierLot(langue, lot))).flat();

    const lignes = [];
    const compte = { orthographe: 0, grammaire: 0, style: 0, passif: 0, tic: 0 };
    for (const { cle, texte, remarques } of resultats) {
        for (const remarque of remarques) {
            // Les chaînes sont souvent des fragments (libellés, début de phrase en minuscule) : règle sans objet.
            if (remarque.rule.id === 'UPPERCASE_SENTENCE_START' && remarque.offset === 0) {
                continue;
            }
            const mot = texte.slice(remarque.offset, remarque.offset + remarque.length);
            if (motsConnus.has(mot)) {
                continue;
            }
            const genre = categorie(remarque);
            compte[genre]++;
            const propositions = remarque.replacements
                .slice(0, 3)
                .map((remplacement) => remplacement.value)
                .join(' | ');
            lignes.push(
                `- [${genre}] \`${cle}\` (${remarque.rule.id}) ${remarque.message.replace(/\s+/gu, ' ')}\n  ${extrait(texte, remarque.offset, remarque.length)}${propositions ? `  ->  ${propositions}` : ''}`,
            );
        }
        if (tics) {
            for (const tic of reperagesTics(langue, texte)) {
                compte.tic++;
                lignes.push(`- [tic] \`${cle}\` ${extrait(texte, tic.debut, tic.extrait.length)}`);
            }
        }
        if (passif) {
            for (const passe of reperagesPassifs(langue, texte)) {
                if (FAUX_PASSIFS[langue]?.test(passe.extrait)) {
                    FAUX_PASSIFS[langue].lastIndex = 0;
                    continue;
                }
                compte.passif++;
                lignes.push(
                    `- [passif] \`${cle}\` ${extrait(texte, passe.debut, passe.extrait.length)}`,
                );
            }
        }
    }
    totaux[langue] = compte;
    rapport.push(
        `## ${langue.toUpperCase()}\n\n${chaines.length} chaînes, ${Object.entries(compte)
            .map(([genre, nombre]) => `${nombre} ${genre}`)
            .join(', ')}\n\n${lignes.join('\n') || 'Rien à signaler.'}\n`,
    );
}

const sortie = `# Rapport LanguageTool (${new Date().toISOString().slice(0, 10)})\n\nNiveau : ${picky ? 'picky' : 'par défaut'}${passif ? ', repérage du passif' : ''}${tics ? ', repérage des tics' : ''}\n\n${rapport.join('\n')}`;

if (options.out) {
    writeFileSync(resolve(racine, options.out), sortie);
    process.stderr.write(`Rapport écrit dans ${options.out}\n`);
} else {
    process.stdout.write(sortie);
}
