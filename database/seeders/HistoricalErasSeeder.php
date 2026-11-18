<?php

namespace Database\Seeders;

use App\Models\Era;
use Illuminate\Database\Seeder;

/**
 * Ères historiques 1830-1980.
 *
 * La carte d'une ère montre l'état du réseau au 31 décembre de son année :
 * chaque texte résume donc la décennie qui MÈNE à cette année (ère 1880 =
 * années 1871-1880). Les segments de chaque ère sont générés par
 * vff:build-era-segments à partir des événements datés.
 */
class HistoricalErasSeeder extends Seeder
{
    public function run(): void
    {
        $eras = [
            ['year' => 1830, 'label' => 'Les pionniers',
                'description' => "Les premières voies ferrées françaises desservent le bassin houiller de la Loire : Saint-Étienne - Andrézieux en 1827, puis Saint-Étienne - Lyon, ouverte par étapes à partir de 1830, d'abord avec des chevaux."],
            ['year' => 1840, 'label' => 'Les premières lignes de voyageurs',
                'description' => "Paris - Saint-Germain (1837), les deux lignes de Paris à Versailles (1839-1840) et Montpellier - Sète (1839) ouvrent l'ère du voyage en train. Le réseau ne compte encore que quelques centaines de kilomètres."],
            ['year' => 1850, 'label' => "L'étoile de Legrand",
                'description' => "La loi du 11 juin 1842 organise un réseau en étoile autour de Paris, construit avec l'État et exploité par des compagnies. Paris est relié à Orléans et à Rouen (1843), puis à Lille (1846)."],
            ['year' => 1860, 'label' => 'Les grandes compagnies',
                'description' => 'Les fusions des années 1850 donnent naissance aux six grandes compagnies (Nord, Est, Ouest, Paris-Orléans, PLM, Midi), qui achèvent les grandes lignes rayonnant depuis Paris.'],
            ['year' => 1870, 'label' => 'Les transversales',
                'description' => 'Les grandes radiales sont achevées et les lignes transversales se multiplient. La guerre franco-allemande éclate en juillet 1870.'],
            ['year' => 1880, 'label' => 'Le plan Freycinet',
                'description' => "L'Alsace-Moselle annexée en 1871 forme un réseau allemand. Le plan Freycinet (1879) lance des milliers de kilomètres de lignes pour desservir chaque sous-préfecture, et la loi du 11 juin 1880 encadre les chemins de fer d'intérêt local."],
            ['year' => 1890, 'label' => "L'essor de l'intérêt local",
                'description' => 'Les conventions de 1883 confient la plupart des lignes Freycinet aux grandes compagnies. Dans les départements, les réseaux secondaires à voie métrique et les tramways se développent.'],
            ['year' => 1900, 'label' => 'La Belle Époque',
                'description' => "Les grandes compagnies exploitent un réseau dense, complété par des réseaux départementaux à voie métrique en pleine extension. Paris inaugure ses premières lignes électriques (gare d'Orsay, 1900)."],
            ['year' => 1910, 'label' => "Vers l'apogée",
                'description' => "Le réseau d'intérêt général approche de son extension maximale et les réseaux secondaires se densifient. Premières électrifications en montagne (ligne de Cerdagne) et en banlieue parisienne."],
            ['year' => 1920, 'label' => "L'après-guerre",
                'description' => "La Première Guerre mondiale a détruit une partie des lignes du Nord et de l'Est et multiplié les voies militaires. L'Alsace-Moselle revient à la France ; les autocars commencent à concurrencer les petites lignes."],
            ['year' => 1930, 'label' => "L'apogée du réseau",
                'description' => "Le réseau atteint son extension maximale : plus de 40 000 km de lignes d'intérêt général et près de 20 000 km de réseaux secondaires. Les premières lignes secondaires ferment face à la route."],
            ['year' => 1940, 'label' => 'La SNCF et la guerre',
                'description' => 'La SNCF, créée le 1er janvier 1938, ferme de nombreuses lignes au trafic voyageurs dans le cadre de la coordination rail-route. La Seconde Guerre mondiale commence en 1939.'],
            ['year' => 1950, 'label' => 'La reconstruction',
                'description' => "Le réseau est reconstruit après les destructions de la guerre. La plupart des réseaux secondaires ont disparu ou déclinent, et l'électrification des grandes artères reprend."],
            ['year' => 1960, 'label' => 'La modernisation',
                'description' => 'Électrification en 25 kV alternatif (première ligne en 1954), autorails et locomotives diesel remplacent la vapeur. Les fermetures de lignes rurales se poursuivent.'],
            ['year' => 1970, 'label' => 'Le resserrement',
                'description' => 'De nombreuses lignes rurales sont fermées aux voyageurs et les derniers réseaux secondaires à voie métrique disparaissent. La vapeur quitte le service commercial.'],
            ['year' => 1980, 'label' => 'Avant la grande vitesse',
                'description' => 'Le TGV Paris - Lyon est décidé en 1974 et la ligne nouvelle Sud-Est se construit. Les voitures Corail modernisent les trains classiques.'],
        ];

        foreach ($eras as $era) {
            Era::updateOrCreate(
                ['year' => $era['year']],
                array_merge($era, ['is_current' => false, 'editable_until' => null]),
            );
        }

        $this->command?->info('✓ '.count($eras).' ères historiques (1830-1980) seedées.');
    }
}
