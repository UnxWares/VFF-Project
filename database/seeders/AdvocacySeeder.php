<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Lignes soutenues par VFF : rejoue les messages de soutien après une
 * reconstruction de la base (ils étaient posés à la main et ont déjà été
 * perdus une fois). Les lignes doivent exister (import SNCF fait avant).
 *
 * Faits sources :
 *   - Angoulême - Limoges (610000) : section Angoulême - Saillat-Chassenon
 *     fermée le 13 mars 2018, annoncée comme temporaire, jamais rouverte
 *     (questions écrites AN 16e lég. n° 15914) ; « Le 14 mars 2018, la SNCF
 *     annonçait la fermeture temporaire pour 3 semaines de la ligne
 *     Limoges-Angoulême » (question écrite AN 16e lég. n° 8999, Caroline
 *     Colombier, JO du 13 juin 2023, p. 5303) ; association AngouLim fondée
 *     le 15 octobre 2023 pour sa réouverture complète.
 *
 * Lien d'intérêts (déclaré le 2026-10-19) : le gestionnaire et créateur de
 * VFF, président d'UnxWares (VFF est un projet open source de la future
 * association UnxWares OpenSource), est adhérent d'AngouLim. Mention publique
 * choisie par l'utilisateur le 2026-10-20 : « Nous adhérons à AngouLim car nous
 * soutenons cette initiative. »
 *
 * Langues : le français est la référence ; les traductions anglaise et allemande
 * lui sont fidèles (mêmes faits, même date, même durée annoncée, même mention du
 * lien d'intérêts) et sont affichées selon la langue de la page (App\Support\Advocacy).
 */
class AdvocacySeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            [
                'code' => '878000',
                '--kind' => 'threatened',
                '--title' => 'La ligne des Hirondelles est en sursis',
                '--message' => 'Viaducs, tunnels et lacets du Haut-Jura : la ligne des Hirondelles, d\'Andelot à La Cluse par Champagnole, Morez et Saint-Claude, est l\'une des plus belles lignes de France. Son avenir dépend des travaux de régénération : VFF soutient son maintien.',
                '--title-en' => 'The Ligne des Hirondelles is on borrowed time',
                '--message-en' => 'Viaducts, tunnels and hairpin bends of the Haut-Jura: the Ligne des Hirondelles, from Andelot to La Cluse via Champagnole, Morez and Saint-Claude, is one of the most beautiful lines in France. Its future depends on renewal works: VFF supports keeping it open.',
                '--cta-en' => 'Take action for the line',
                '--title-de' => 'Die Ligne des Hirondelles hat nur eine Gnadenfrist',
                '--message-de' => 'Viadukte, Tunnel und Kehren im Hochjura: Die Ligne des Hirondelles von Andelot nach La Cluse über Champagnole, Morez und Saint-Claude ist eine der schönsten Strecken Frankreichs. Ihre Zukunft hängt von den Erneuerungsarbeiten ab: VFF setzt sich für ihren Erhalt ein.',
                '--cta-de' => 'Für die Strecke aktiv werden',
            ],
            // Argentan - Granville : courrier du président de la Région Normandie au Premier ministre
            // (9 juillet 2026, France 3 Normandie du 23 juillet 2026) ; fréquentation des quatre gares
            // desservies par cette seule ligne : database/data/frequentation_gares_405000.php (SNCF,
            // jeu 2015-2024), chiffres vérifiés par ArgentanGranvilleAdvocacyTest ; hausse 2025 :
            // bilan Région et SNCF Voyageurs (ICI Normandie, 19 janvier 2026) ; B100 depuis avril 2021 :
            // communiqué Région Normandie, SNCF Voyageurs et Oleo100 du 17 janvier 2023.
            // La Région ne demande pas la fermeture : elle annonce un risque si l'État ne paie pas.
            [
                'code' => '405000',
                '--kind' => 'threatened',
                '--title' => 'La ligne Argentan - Granville doit rester ouverte',
                '--message' => 'Le 9 juillet 2026, le président de la Région Normandie a écrit au Premier ministre que la ligne Paris - Granville pourrait fermer d\'ici trois ans si l\'État ne finance pas sa remise en état, estimée à près d\'un milliard d\'euros d\'ici 2040. La Région demande à l\'État de reprendre ces coûts. Fermer la ligne serait une absurdité : elle gagne des voyageurs depuis le Covid, ce que la Région écrit elle-même dans ce courrier. Les gares de Briouze, Flers, Vire et Villedieu-les-Poêles, que seule cette ligne dessert, ont compté 20 % de voyageurs de plus en 2024 qu\'en 2019 et 63 % de plus qu\'en 2021, malgré un recul de 4 % entre 2023 et 2024. Pour 2025, la Région et SNCF Voyageurs annoncent encore 6 % de hausse sur Paris - Granville. La ligne permet aussi d\'aller au Mont-Saint-Michel. Depuis avril 2021, ses trains Régiolis roulent au B100, un carburant tiré du colza. VFF soutient le maintien de la ligne.',
                '--title-en' => 'The Argentan - Granville line must stay open',
                '--message-en' => 'On 9 July 2026, the president of the Normandy Region wrote to the Prime Minister that the Paris - Granville line could close within three years if the State does not fund its renewal, estimated at nearly one billion euros by 2040. The Region is asking the State to take over these costs. Closing the line would be absurd: it has been gaining passengers since Covid, as the Region itself writes in that letter. At Briouze, Flers, Vire and Villedieu-les-Poêles, stations served only by this line, passenger numbers were 20% higher in 2024 than in 2019 and 63% higher than in 2021, despite a 4% fall between 2023 and 2024. For 2025, the Region and SNCF Voyageurs report a further 6% rise on Paris - Granville. The line also serves travellers heading to Mont-Saint-Michel. Since April 2021, its Régiolis trains have run on B100, a fuel made from rapeseed. VFF supports keeping the line open.',
                '--title-de' => 'Die Strecke Argentan - Granville muss offen bleiben',
                '--message-de' => 'Am 9. Juli 2026 schrieb der Präsident der Region Normandie dem Premierminister, die Strecke Paris - Granville könne innerhalb von drei Jahren geschlossen werden, wenn der Staat ihre Erneuerung nicht finanziert, deren Kosten bis 2040 auf fast eine Milliarde Euro geschätzt werden. Die Region fordert, dass der Staat diese Kosten übernimmt. Die Strecke zu schließen wäre absurd: Seit Corona gewinnt sie Fahrgäste hinzu, wie die Region in diesem Schreiben selbst festhält. Die Bahnhöfe Briouze, Flers, Vire und Villedieu-les-Poêles, die nur diese Strecke bedient, zählten 2024 20 % mehr Fahrgäste als 2019 und 63 % mehr als 2021, trotz eines Rückgangs um 4 % von 2023 auf 2024. Für 2025 melden die Region und SNCF Voyageurs auf Paris - Granville ein weiteres Plus von 6 %. Die Strecke führt Reisende auch zum Mont-Saint-Michel. Seit April 2021 fahren ihre Régiolis-Züge mit B100, einem Kraftstoff aus Raps. VFF setzt sich für den Erhalt der Strecke ein.',
                '--source' => [
                    'France 3 Normandie|2026-07-23|https://france3-regions.franceinfo.fr/normandie/1-milliard-d-euros-de-travaux-trop-vetuste-la-ligne-sncf-paris-granville-pourrait-elle-vraiment-disparaitre-3390607.html',
                    'ICI Normandie|2026-01-19|https://www.ici.fr/normandie/frequentation-ponctualite-chantiers-quel-bilan-pour-les-trains-en-normandie-en-2025-8580180',
                    'SNCF Gares & Connexions, Fréquentation en gares 2015-2024|2025-07-03|https://ressources.data.sncf.com/explore/dataset/frequentation-gares-archive/',
                    'Région Normandie, SNCF Voyageurs, Oleo100|2023-01-17|https://oleo100.com/oleo-assets/uploads/2023/01/2023-cp-bilan-des-18-mois-dutilisation-du-b100-vdef.pdf',
                ],
            ],
            [
                'code' => '610000',
                '--kind' => 'reopening',
                '--title' => 'Rouvrons la ligne Angoulême - Limoges',
                '--message' => 'Fermée le 13 mars 2018 entre Angoulême et Saillat-Chassenon pour une interruption annoncée de trois semaines, la ligne n\'a jamais rouvert. Sa réouverture désenclaverait tout un territoire entre Charente et Haute-Vienne. VFF soutient l\'association AngouLim, qui milite pour sa réouverture complète. Nous adhérons à AngouLim car nous soutenons cette initiative.',
                '--cta' => 'Soutenir AngouLim',
                '--title-en' => 'Let\'s reopen the Angoulême - Limoges line',
                '--message-en' => 'Closed on 13 March 2018 between Angoulême and Saillat-Chassenon for an interruption announced to last three weeks, the line has never reopened. Its reopening would end the isolation of a whole area between Charente and Haute-Vienne. VFF supports the AngouLim association, which campaigns for its full reopening. We are members of AngouLim because we support this initiative.',
                '--cta-en' => 'Support AngouLim',
                '--title-de' => 'Öffnen wir die Strecke Angoulême - Limoges wieder',
                '--message-de' => 'Am 13. März 2018 zwischen Angoulême und Saillat-Chassenon für eine angekündigte Unterbrechung von drei Wochen geschlossen, wurde die Strecke nie wieder eröffnet. Ihre Wiedereröffnung würde ein ganzes Gebiet zwischen Charente und Haute-Vienne aus seiner Abgeschiedenheit holen. VFF unterstützt den Verein AngouLim, der sich für ihre vollständige Wiedereröffnung einsetzt. Wir sind Mitglied von AngouLim, weil wir diese Initiative unterstützen.',
                '--cta-de' => 'AngouLim unterstützen',
                '--url' => 'https://www.helloasso.com/associations/angoulim/adhesions/bulletin-d-adhesion-a-angoulim',
            ],
        ];

        foreach ($campaigns as $args) {
            Artisan::call('vff:flag-threatened', $args);
            $this->command?->line(trim(Artisan::output()));
        }
    }
}
