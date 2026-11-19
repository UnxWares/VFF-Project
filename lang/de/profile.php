<?php

// Abschnitt „Höhenprofil“ des Streckenblatts (App\Services\Profiles\LineProfiles): Höhen des IGN
// (BD TOPO, sonst RGE ALTI) entlang der Strecke.
return [
    'title' => 'Höhenprofil',
    'intro' => 'Höhe des Gleises entlang der Strecke, von :from bis :to. Die Entfernungen werden entlang der Trasse ab :from gemessen; den amtlichen Streckenkilometer jedes Bahnhofs finden Sie in der Tabelle.',
    'intro_plain' => 'Höhe des Gleises entlang der Strecke. Die Entfernungen werden entlang der Trasse ab einem ihrer Enden gemessen.',
    'stats_label' => 'Kennzahlen des Profils',
    'min' => 'Tiefster Punkt',
    'max' => 'Höchster Punkt',
    'ascent' => 'Anstieg insgesamt',
    'descent' => 'Gefälle insgesamt',
    'grade' => 'Größte Neigung',
    'grade_up' => 'steigend, von km :from bis km :to',
    'grade_down' => 'fallend, von km :from bis km :to',
    'chart_label' => 'Diagramm des Höhenprofils: Höhe von :min bis :max m auf :km km. Die Höhe jedes Bahnhofs steht auch in der folgenden Tabelle.',
    'axis_distance' => 'Entfernung (km)',
    'at_km' => 'km :km',
    'local_grade' => 'Neigung: :grade ‰',
    'in_tunnel' => 'im Tunnel',
    'tunnel_legend' => 'Tunnel: Höhe zwischen den beiden Portalen geschätzt',
    'break_note' => 'Die Kurve bricht ab, wo die Höhe nicht bekannt ist (Lücke in der Trasse, Abschnitt außerhalb Frankreichs).',
    'show_table' => 'Höhe der Bahnhöfe (:count)',
    'table_caption' => 'Höhe der Bahnhöfe im Höhenprofil',
    'station' => 'Bahnhof',
    'pk' => 'Amtlicher Streckenkilometer',
    'distance' => 'Entfernung',
    'altitude' => 'Höhe',
    'unknown' => 'unbekannt',
    'source' => 'Höhen des IGN, Licence Ouverte 2.0: Gleishöhe aus der BD TOPO, sonst Geländehöhe (RGE ALTI). In Tunneln und auf Brücken ohne Höhenangabe wird der Wert zwischen den beiden Enden interpoliert. Die Zahlen sind Richtwerte: Die Neigung kann um einige ‰ von den amtlichen Werten abweichen. Berechnet am :date.',
];
