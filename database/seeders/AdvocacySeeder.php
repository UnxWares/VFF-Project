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
