<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\WikipediaListReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Filtre « transport urbain » des puces de la liste d'accidents ferroviaires : un accident où un train de
 * chemin de fer est impliqué (express, train de marchandises, machine haut-le-pied) reste dans le périmètre
 * même contre un tramway ; un accident de tramway ou de métro seul est écarté. Extraits réels de la liste.
 */
class WikipediaListUrbanTransitTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function bullets(): iterable
    {
        yield 'Lyon 1901 : express contre un train-tramway' => ["* {{date|20|décembre|1901}} - À 19 heures 20, à Lyon, l'express Lyon-Genève, parti de la [[Gare de Lyon-Perrache|gare de Perrache]], percute un train-tramway se dirigeant vers le [[Montchat|quartier de Montchat]] sur le passage à niveau de la [[Rue Paul-Bert (Lyon)|rue Paul-Bert]], que ses deux garde-barrières ont négligé de fermer. La collision fera quatre morts et une quarantaine de blessés.", false];
        yield 'Dijon 1932 : express contre un tramway' => ["* {{date|1er|novembre|1932}}. À Dijon, les barrières du passage à niveau de la rue d'Auxonne sur la [[Ligne de Dijon-Ville à Is-sur-Tille|ligne Dijon-Is-sur-Tille]] sont restées ouvertes lorsque survient l'express Nancy-Saincaize, qui écrase un piéton et percute la plateforme arrière d'un tramway, tuant trois de ses passagers et en blessant trois autres.", false];
        yield 'Thionville 1928 : wagons de minerai contre un tramway' => ['* {{date|9|février|1928}} - À environ deux kilomètres au sud de [[Thionville]], à un passage à niveau de la [[Ligne Lille - Thionville|ligne Valenciennes-Thionville]], vers 18 heures 15, une rame de wagons de minerai en dérive dans la pente percute un tramway allant de [[Hayange]] vers Thionville et le coupe en deux, faisant dix-sept morts et huit blessés.', false];
        yield 'Le Havre 1915 : train de marchandises contre un tramway' => ['* {{date|16|janvier|1915}} - [[Le Havre|Au Havre]], le soir, à un passage à niveau du Boulevard de Harfleur, un [[tramway]] bondé est percuté par un train de marchandises manœuvrant en marche arrière du port à la gare de triage. La collision fait trois morts et treize blessés.', false];
        yield 'Longwy 1907 : machine haut-le-pied contre un tramway' => ["* {{date|13|mai|1907}} \u{2013} À [[Longwy]], à 20 heures 30, un tramway électrique de la ligne Longwy-Mont-Saint-Martin s'engage sur le passage à niveau du quartier de Gouraincourt resté ouvert, lorsqu'il est heurté par une machine haut-le-pied rentrant au dépôt. La collision fait quatre morts et trois blessés graves.", false];
        yield 'Cherbourg 1920 : wagons de marchandises contre un tramway' => ['* {{date|8|novembre|1920}} - Sur le réseau du [[Tramway de Cherbourg]], à un passage à niveau de la ligne de desserte du port, un tramway électrique remorquant une baladeuse est percuté par une rame de wagons de marchandises. Une voyageuse est tuée, vingt autres personnes sont blessées.', false];
        yield 'Passy 1900 : tramway seul' => ["* {{date|16|juin|1900}} - À Paris, vers 17 heures, [[avenue du Trocadéro]], un [[tramway à impériale]] de la ligne [[Passy (Seine)|Passy]]-Hôtel-de-Ville s'emballe dans la descente, quitte les rails, accroche un premier [[Fiacre (hippomobile)|fiacre]], en pulvérise un second. L'accident fait un mort et quinze blessés.", true];
        yield 'Couronnes 1903 : métro seul' => ["* {{date|10|août|1903}} - [[Paris]] : Incendie à la suite d'un court-circuit d'une rame du [[métro de Paris]] entre les stations Ménilmontant et Couronnes : {{nombre|84|morts}}.", true];
        yield 'Nice 1940 : tramway seul' => ["* {{date|24|novembre|1940}} - À [[Nice]], un tramway patine sur des feuilles, quitte les rails, et s'écrase contre un platane du Boulevard de Cimiez, faisant quatre morts et huit blessés.", true];
        yield 'Aubagne 1906 : deux tramways' => ["* {{date|20|août|1906}} \u{2013} À Camp-Major, faubourg d'[[Aubagne]], sur le réseau de Marseille de la Compagnie générale française de tramways, deux tramways rapides de sens contraire se rencontrent dans une courbe. Un [[wattman]] est tué, et une vingtaine de personnes sont blessées.", true];
        yield 'Vaugneray 1900 : locomotive de tramway contre un train électrique' => ["* {{date|31|mai|1900}} - Sur le tronc commun aux lignes de [[Vaugneray]] et [[Mornant]] du réseau à voie métrique du Fourvière-Ouest-Lyonnais, près du viaduc d'Alaï, une locomotive du tramway à vapeur de retour d'essais heurte de face un train électrique régulier allant de Lyon à Vaugneray. Deux morts.", true];
        yield 'Rue de Rennes 1910 : fiacre entre deux tramways' => ["* {{date|25|septembre|1910}} - À Paris, vers 20 heures, rue de Rennes, un fiacre tentant de traverser la ligne de tramways est coincé dans l'entrevoie et laminé entre deux convois. Deux de ses occupants sont tués.", true];
        yield 'Escautpont 1882 : tramway seul' => ["* {{date|16|juillet|1882}} - À [[Escautpont]], un tramway ramenant à [[Valenciennes]] le public d'un hippodrome déraille, faisant deux morts et de nombreux blessés.", true];
        yield 'train ordinaire sans tramway' => ['* {{date|12|août|1881}} - À son entrée en gare de [[Belfort]], un express contre un rapide pour Paris, un mort.', false];
    }

    #[DataProvider('bullets')]
    public function test_urban_transit_filter_keeps_rail_trains_and_drops_tram_only(string $line, bool $urban): void
    {
        $entry = (new WikipediaListReader(new AccidentKindClassifier))->read($line)[0] ?? null;

        $this->assertNotNull($entry);
        $this->assertSame($urban, $entry['urban']);
    }
}
