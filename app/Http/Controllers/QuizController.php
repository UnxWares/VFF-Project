<?php

namespace App\Http\Controllers;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Http\Requests\QuizAnswerRequest;
use App\Http\Requests\QuizQuestionRequest;
use App\Services\Quiz\MaterialQuiz;
use App\Services\Quiz\QuizPick;
use App\Services\Quiz\QuizSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quiz du rail (/quiz) : reconnaître une ligne à son tracé ou une série de matériel à sa photo.
 *
 * Route web seulement (pas d'API publique) : la page reçoit les dix questions sans les réponses ;
 * chaque réponse est vérifiée par answer(), après le choix. Aucun score ni aucune réponse n'est
 * enregistré côté serveur.
 */
class QuizController extends Controller
{
    public function index(Request $request, QuizSeries $series): Response|RedirectResponse
    {
        // Paramètre absent ou passé en tableau (`game[]=…`, adresses de robots) : valeur vide.
        $query = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $game = QuizGame::tryFrom($query('game'));
        $available = array_combine(
            array_map(fn (QuizGame $g): string => $g->value, QuizGame::cases()),
            array_map($series->available(...), QuizGame::cases()),
        );
        if ($game === null || ! $available[$game->value]) {
            return Inertia::render('Quiz', [
                'available' => $available,
                'game' => null,
            ]);
        }

        // Adresse complète (niveau, graine, version de la série) : c'est elle qui se partage pour rejouer
        // la même série.
        $level = QuizLevel::tryFrom($query('level'));
        $seed = $query('seed');
        $version = $query('v');
        if ($level === null || ! QuizSeries::validSeed($seed)) {
            $level ??= QuizLevel::Easy;
            $seed = QuizSeries::validSeed($seed) ? $seed : QuizSeries::newSeed();
            $version = '';
        }
        $picks = $series->picks($game, $level, $seed);
        $current = QuizSeries::version($picks);
        if ($version === '') {
            return redirect()->route('quiz', ['game' => $game->value, 'level' => $level->value, 'seed' => $seed, 'v' => $current]);
        }
        if ($version !== $current) {
            // La base a changé depuis la création de l'adresse et la série n'est plus la même : la page
            // le dit et propose une nouvelle série, au lieu de poser d'autres questions sous la même adresse.
            return Inertia::render('Quiz', [
                'available' => $available,
                'game' => $game->value,
                'level' => $level->value,
                'stale' => true,
            ]);
        }

        return Inertia::render('Quiz', [
            'available' => $available,
            'game' => $game->value,
            'level' => $level->value,
            'seed' => $seed,
            'version' => $current,
            'questions' => $series->questions($game, $level, $seed, $picks),
        ]);
    }

    /**
     * Réponse à une question, donnée seulement avec le choix du joueur ; rien n'est enregistré. Si la
     * série a changé depuis l'affichage de la page (version différente), la réponse n'est pas jugée :
     * 409, et la page propose une nouvelle série.
     */
    public function answer(QuizAnswerRequest $request, QuizSeries $series): JsonResponse
    {
        $pick = $this->currentPick($request, $series);
        if ($pick === false) {
            return response()->json(['message' => 'This series has changed since the page was loaded.', 'stale' => true], 409)
                ->header('Cache-Control', 'no-store');
        }
        abort_if($pick === null, 404);
        $result = $series->reveal($request->game(), $pick, $request->choice());
        abort_if($result === null, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    /**
     * Photo d'une question du jeu du matériel : redirection vers l'image Commons. L'adresse de l'image
     * contient souvent le nom de la série : elle ne figure ni dans la page ni dans ses données, mais le
     * navigateur suit la redirection et l'adresse finale reste visible dans ses outils (Commons ne
     * fournit pas d'adresse d'image sans le nom du fichier). Série changée : 409.
     */
    public function photo(QuizQuestionRequest $request, QuizSeries $series, MaterialQuiz $materials): RedirectResponse
    {
        abort_unless($request->game() === QuizGame::Materials, 404);
        $pick = $this->currentPick($request, $series);
        abort_if($pick === false, 409);
        abort_if($pick === null, 404);

        return redirect()->away($materials->photoUrl($pick->subject))->header('Cache-Control', 'private, max-age=3600');
    }

    /**
     * Question demandée, tirée de nouveau : false si la série n'a plus la version de l'adresse, null si
     * la question n'existe pas.
     */
    private function currentPick(QuizQuestionRequest $request, QuizSeries $series): QuizPick|false|null
    {
        $picks = $series->picks($request->game(), $request->level(), $request->seed());
        if (QuizSeries::version($picks) !== $request->version()) {
            return false;
        }

        return $picks[$request->index()] ?? null;
    }
}
