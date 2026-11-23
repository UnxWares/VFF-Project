<?php

namespace App\Enums;

/**
 * Sujets des questions du jeu « Culture du rail » (QuizGame::Facts). Chaque sujet a un degré de difficulté
 * (1 facile, 2 moyen, 3 difficile) : le niveau facile du quiz ne pose que des questions de degré 1 ou 2.
 */
enum QuizTopic: string
{
    case OpeningYear = 'opening_year';
    case ElectrificationYear = 'electrification_year';
    case Company = 'company';
    case Milestone = 'milestone';
    case LongestTunnel = 'longest_tunnel';
    case LongestViaduct = 'longest_viaduct';
    case Architect = 'architect';
    case HeritageYear = 'heritage_year';
    case Attendance = 'attendance';
    case FirstSeries = 'first_series';
    case StationOpeningYear = 'station_opening_year';
    case CabSignalling = 'cab_signalling';
    case ElectrificationDate = 'electrification_date';
    case LineMaterial = 'line_material';
    case AccidentYear = 'accident_year';

    /** Degré de difficulté de la question : 1 facile, 2 moyen, 3 difficile. */
    public function difficulty(): int
    {
        return match ($this) {
            self::Attendance, self::LongestTunnel, self::LongestViaduct => 1,
            self::OpeningYear, self::Company, self::Milestone, self::FirstSeries, self::StationOpeningYear, self::CabSignalling, self::LineMaterial, self::AccidentYear => 2,
            self::ElectrificationYear, self::Architect, self::HeritageYear, self::ElectrificationDate => 3,
        };
    }

    /** La réponse est une année (propositions tirées autour d'elle). */
    public function isYear(): bool
    {
        return in_array($this, [self::OpeningYear, self::ElectrificationYear, self::Milestone, self::HeritageYear, self::StationOpeningYear, self::AccidentYear], true);
    }

    /** La réponse est une date au jour (propositions : d'autres dates, écrites dans la langue de la page). */
    public function isDate(): bool
    {
        return $this === self::ElectrificationDate;
    }
}
