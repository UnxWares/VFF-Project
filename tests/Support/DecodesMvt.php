<?php

namespace Tests\Support;

/**
 * Décodeur minimal de tuiles Mapbox Vector Tile (protobuf, spec 2.1) pour les
 * tests : couches, et propriétés de chaque entité (la géométrie n'est pas
 * décodée). Évite de tester les tuiles par simple recherche de sous-chaîne
 * (« threatened » est aussi dans la clé « is_threatened »).
 */
trait DecodesMvt
{
    /**
     * @return array<string, list<array<string, mixed>>> nom de couche → propriétés des entités
     */
    protected function decodeMvt(string $tile): array
    {
        $layers = [];
        foreach ($this->pbFields($tile) as [$field, $value]) {
            if ($field !== 3) {
                continue;
            }
            $name = '';
            $keys = [];
            $values = [];
            $features = [];
            foreach ($this->pbFields($value) as [$f, $v]) {
                match ($f) {
                    1 => $name = $v,
                    2 => $features[] = $v,
                    3 => $keys[] = $v,
                    4 => $values[] = $this->mvtValue($v),
                    default => null,
                };
            }
            foreach ($features as $feature) {
                $props = [];
                foreach ($this->pbFields($feature) as [$f, $v]) {
                    if ($f === 2) {
                        $tags = $this->pbPacked($v);
                        for ($i = 0; $i + 1 < count($tags); $i += 2) {
                            $props[$keys[$tags[$i]]] = $values[$tags[$i + 1]];
                        }
                    }
                }
                $layers[$name][] = $props;
            }
            $layers[$name] ??= [];
        }

        return $layers;
    }

    private function mvtValue(string $buf): mixed
    {
        foreach ($this->pbFields($buf) as [$f, $v]) {
            return match ($f) {
                1 => $v,
                2 => unpack('g', $v)[1],
                3 => unpack('e', $v)[1],
                4, 5 => $v,
                6 => ($v >> 1) ^ -($v & 1),
                7 => (bool) $v,
                default => null,
            };
        }

        return null;
    }

    /** @return list<array{0: int, 1: int|string}> (numéro de champ, valeur) */
    private function pbFields(string $buf): array
    {
        $out = [];
        $pos = 0;
        $len = strlen($buf);
        while ($pos < $len) {
            $key = $this->pbVarint($buf, $pos);
            $field = $key >> 3;
            switch ($key & 7) {
                case 0:
                    $out[] = [$field, $this->pbVarint($buf, $pos)];
                    break;
                case 1:
                    $out[] = [$field, substr($buf, $pos, 8)];
                    $pos += 8;
                    break;
                case 2:
                    $n = $this->pbVarint($buf, $pos);
                    $out[] = [$field, substr($buf, $pos, $n)];
                    $pos += $n;
                    break;
                case 5:
                    $out[] = [$field, substr($buf, $pos, 4)];
                    $pos += 4;
                    break;
                default:
                    throw new \RuntimeException('Type protobuf non géré : '.($key & 7));
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function pbPacked(string $buf): array
    {
        $out = [];
        $pos = 0;
        while ($pos < strlen($buf)) {
            $out[] = $this->pbVarint($buf, $pos);
        }

        return $out;
    }

    private function pbVarint(string $buf, int &$pos): int
    {
        $result = 0;
        $shift = 0;
        do {
            $byte = ord($buf[$pos++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $result;
    }
}
