<?php
function flat($a, $p = '') { $o = []; foreach ($a as $k => $v) { if (is_array($v)) $o += flat($v, "$p$k."); else $o["$p$k"] = $v; } return $o; }
foreach (glob('lang/fr/*.php') as $f) {
  $n = basename($f);
  $fr = flat(require $f);
  foreach (['en','de'] as $l) {
    $p = "lang/$l/$n";
    $o = file_exists($p) ? flat(require $p) : [];
    $miss = array_diff(array_keys($fr), array_keys($o));
    if ($miss) echo "$l/$n manque: ".implode(', ', array_slice($miss,0,30)).(count($miss)>30?' …(+'.(count($miss)-30).')':'')."\n";
  }
}
