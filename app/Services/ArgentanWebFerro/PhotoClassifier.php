<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanPhotoClass;

/**
 * Classe de droits d'une photo de la galerie, par ordre de priorité :
 *   1. relecture qui exclut la photo (carte postale, archive, créditée, non établie) ;
 *   2. mot-clé nommant un autre photographe → créditée à un tiers (même relue comme œuvre propre) ;
 *   3. mention d'un tiers dans le nom, la description ou le fichier : « Coll. », « Collection »,
 *      « Photo: X » (autre que l'auteur) → créditée ; archives départementales (« AD50 »),
 *      fonds militaires (« Fold3 »), « Archives » → archive ;
 *   4. relecture « œuvre propre » (curation, database/data/argentanwebferro.php : « photos ») ;
 *   5. sinon : nature non établie (jamais republiée sans relecture).
 */
final readonly class PhotoClassifier
{
    /**
     * @param  array<string, array<int, string>>  $reviewed  classe → [identifiant Piwigo => raison]
     * @param  array<int, string>  $creditedByTag  identifiant Piwigo → photographe
     */
    public function __construct(private array $reviewed = [], private array $creditedByTag = []) {}

    /**
     * @param  array<string, mixed>  $image  élément de pwg.categories.getImages
     * @return array{0: ArgentanPhotoClass, 1: string}
     */
    public function classify(array $image): array
    {
        $id = (int) $image['id'];
        $reviewed = null;
        foreach ($this->reviewed as $class => $ids) {
            if (isset($ids[$id])) {
                $reviewed = [ArgentanPhotoClass::from($class), 'relecture : '.$ids[$id]];
            }
        }
        // Une relecture qui EXCLUT la photo l'emporte toujours ; une relecture « œuvre propre » cède devant un crédit à un tiers.
        if ($reviewed !== null && $reviewed[0] !== ArgentanPhotoClass::OwnWork) {
            return $reviewed;
        }
        if (isset($this->creditedByTag[$id])) {
            return [ArgentanPhotoClass::Credited, 'mot-clé du photographe : '.$this->creditedByTag[$id]];
        }
        $text = implode(' ', array_map(fn ($v) => html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            [$image['name'] ?? '', $image['comment'] ?? '', $image['file'] ?? '']));
        if (preg_match('/\b(?:Coll(?:ection)?\.?\s+\p{Lu}|Photo\s*:\s*(?!T\.?\s*Danglos|Thierry Danglos)\p{Lu})/u', $text, $m)) {
            return [ArgentanPhotoClass::Credited, 'mention d\'un tiers : « '.trim($m[0]).' »'];
        }
        if (preg_match('/(?<![\p{L}\d])(?:AD\s?\d{2}(?!\d)|Fold3|Archives?(?!\p{L}))/u', $text, $m)) {
            return [ArgentanPhotoClass::Archive, 'fonds d\'archives : « '.$m[0].' »'];
        }

        return $reviewed ?? [ArgentanPhotoClass::Unknown, 'non relue'];
    }
}
