<?php

namespace App\Console\Commands;

use App\Services\NetworkFigures;
use Illuminate\Console\Command;

/**
 * Calcule « Le réseau en chiffres » (page /network, `GET /api/v1/stats/network`) et le range dans le cache,
 * sans expiration, avec sa date de calcul. Lecture seule en base (quelques secondes) ; à lancer à la main
 * après un rejeu de la carte historique ou un import d'événements : aucune planification.
 *
 *   php artisan vff:compute-network-figures
 *   php artisan vff:compute-network-figures --dry-run   (calcule et affiche, ne touche pas au cache)
 */
class ComputeNetworkFigures extends Command
{
    protected $signature = 'vff:compute-network-figures {--dry-run : calcule et affiche sans ranger dans le cache}';

    protected $description = 'Calcule les chiffres du réseau par décennie (longueurs, gares, électrification, fermetures, accidents) et les range dans le cache.';

    public function handle(NetworkFigures $figures): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $result = $dry ? $figures->compute() : $figures->refresh();

        $this->table(
            ['Ère', 'Ouvert km', 'Voyageurs', 'Fret seul', 'Non préc.', 'Fermé', 'Déposé', 'Électr. %', 'Retirés', 'Mis en serv.', 'Gares', 'Accidents', 'Morts'],
            array_map(fn (array $r): array => [
                $r['year'], $r['open_km'], $r['open_passenger_km'], $r['open_freight_km'], $r['open_unknown_km'], $r['closed_km'], $r['gone_km'],
                $r['electrified_share'] === null ? '-' : round($r['electrified_share'] * 100, 1),
                $r['removed_km'], $r['added_km'], $r['stations']['active'], $r['accidents']['count'], $r['accidents']['deaths'],
            ], $result['decades']),
        );
        $this->line($result['tracks'] === null ? 'Voies principales : jeu non importé.' : 'Voies principales : '.json_encode($result['tracks']));
        $this->info(sprintf('Chiffres du réseau calculés le %s en %.1f s%s.', $result['computed_at'], microtime(true) - $started, $dry ? ' [DRY-RUN, cache inchangé]' : ' et rangés dans le cache'));

        return self::SUCCESS;
    }
}
