<?php

namespace App\Console\Commands;

use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlagHeritage extends Command
{
    protected $signature = 'vff:flag-heritage
        {codes* : Codes de lignes RFN à flagger (ex: 412000 411000)}
        {--unflag : Au contraire enlève le flag}';

    protected $description = 'Flag les lignes (par code RFN) comme heritage. Tous leurs segments passent en patrimoine (vert) sur la carte.';

    public function handle(): int
    {
        $codes = (array) $this->argument('codes');
        $unflag = (bool) $this->option('unflag');
        $value = $unflag ? false : true;
        $missing = 0;

        foreach ($codes as $code) {
            $line = Line::query()
                ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [
                    preg_replace('/\s+/', '', $code),
                ])
                ->first();

            if (! $line) {
                $this->warn("⚠ Ligne {$code} introuvable");
                $missing++;

                continue;
            }

            $updated = DB::table('line_segments')
                ->where('line_id', $line->id)
                ->update(['is_heritage' => $value]);

            $verb = $unflag ? 'Déflaggé' : 'Flaggé';
            $this->info("✓ {$verb} : {$line->name} ({$line->code}) → {$updated} segments");
        }

        // Code inconnu (faute de frappe…) : les autres lignes sont traitées, mais
        // l'échec est signalé au script appelant.
        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }
}
