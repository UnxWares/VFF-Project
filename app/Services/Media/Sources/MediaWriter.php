<?php

namespace App\Services\Media\Sources;

use Illuminate\Support\Facades\DB;

/**
 * Écrit dans `media` les MediaRecord d'une source, ou les compte seulement (passe à blanc). Commun à toutes les
 * sources (MediaSource) : clé `(provider, external_id, mediable_type, mediable_id)`, donc relancer ne crée aucun
 * doublon ; une ligne déjà présente voit ses droits et adresses remis à jour (la source fait foi), jamais son
 * `is_featured` baissé.
 *
 * Compteurs (MediaWriteReport) : écrits, mis à jour, refusés par motif, sujets touchés, et sujets « gagnés »
 * (aucun média avant, au moins un après) : la mesure demandée pour chaque source.
 */
final class MediaWriter
{
    private const CHUNK = 500;

    /** @param  iterable<MediaRecord>  $records */
    public function write(iterable $records, bool $dryRun = false): MediaWriteReport
    {
        $report = new MediaWriteReport;
        $buffer = [];
        foreach ($records as $record) {
            $reason = $record->invalidReason();
            if ($reason !== null) {
                $report->refuse($reason);

                continue;
            }
            $buffer[] = $record;
            if (count($buffer) >= self::CHUNK) {
                $this->flush($buffer, $dryRun, $report);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $this->flush($buffer, $dryRun, $report);
        }

        return $report;
    }

    /** @param  list<MediaRecord>  $records */
    private function flush(array $records, bool $dryRun, MediaWriteReport $report): void
    {
        // Doublons dans le même lot : le premier l'emporte (même clé que la contrainte unique).
        $byKey = [];
        foreach ($records as $r) {
            $byKey[$r->provider."\0".$r->externalId."\0".$r->mediableType."\0".$r->mediableId] ??= $r;
        }
        $records = array_values($byKey);

        $existing = $this->existing($records);
        $subjectsWithMedia = $this->subjectsWithMedia($records);

        foreach ($records as $r) {
            $subject = $r->mediableType."\0".$r->mediableId;
            $report->touch($subject, $r->mediableType, isset($subjectsWithMedia[$subject]));
            if (isset($existing[$r->provider."\0".$r->externalId."\0".$subject])) {
                $report->updated++;
            } else {
                $report->inserted++;
            }
        }
        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($records, $existing): void {
            $now = now();
            foreach ($records as $r) {
                $row = $r->toRow();
                $key = $r->provider."\0".$r->externalId."\0".$r->mediableType."\0".$r->mediableId;
                if (isset($existing[$key])) {
                    unset($row['is_featured']);
                    DB::table('media')->where('id', $existing[$key])->update([...$row, 'updated_at' => $now]);
                    if ($r->featured) {
                        DB::table('media')->where('id', $existing[$key])->update(['is_featured' => true]);
                    }
                } else {
                    DB::table('media')->insert([...$row, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }

    /**
     * Identifiant des lignes déjà en base, par clé.
     *
     * @param  list<MediaRecord>  $records
     * @return array<string, int>
     */
    private function existing(array $records): array
    {
        $out = [];
        foreach ($this->groupByProvider($records) as $provider => $ids) {
            $rows = DB::table('media')->where('provider', $provider)->whereIn('external_id', array_keys($ids))
                ->get(['id', 'provider', 'external_id', 'mediable_type', 'mediable_id']);
            foreach ($rows as $row) {
                $out[$row->provider."\0".$row->external_id."\0".$row->mediable_type."\0".$row->mediable_id] = (int) $row->id;
            }
        }

        return $out;
    }

    /**
     * Sujets qui ont déjà au moins un média, toutes sources confondues.
     *
     * @param  list<MediaRecord>  $records
     * @return array<string, true>
     */
    private function subjectsWithMedia(array $records): array
    {
        $byType = [];
        foreach ($records as $r) {
            $byType[$r->mediableType][$r->mediableId] = true;
        }
        $out = [];
        foreach ($byType as $type => $ids) {
            foreach (DB::table('media')->where('mediable_type', $type)->whereIn('mediable_id', array_keys($ids))->distinct()->pluck('mediable_id') as $id) {
                $out[$type."\0".$id] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<MediaRecord>  $records
     * @return array<string, array<string, true>>
     */
    private function groupByProvider(array $records): array
    {
        $out = [];
        foreach ($records as $r) {
            $out[$r->provider][$r->externalId] = true;
        }

        return $out;
    }
}
