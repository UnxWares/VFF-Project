<?php

namespace App\Http\Controllers;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Http\Requests\QuizAnswerRequest;
use App\Http\Requests\QuizQuestionRequest;
use App\Services\Quiz\MaterialQuiz;
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
        $game = QuizGame::tryFrom((string) $request->query('game', ''));
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

        // Adresse complète (niveau et graine) : c'est elle qui se partage pour rejouer la même série.
        $level = QuizLevel::tryFrom((string) $request->query('level', ''));
        $seed = (string) $request->query('seed', '');
        if ($level === null || ! QuizSeries::validSeed($seed)) {
            return redirect()->route('quiz', [
                'game' => $game->value,
                'level' => ($level ?? QuizLevel::Easy)->value,
                'seed' => QuizSeries::validSeed($seed) ? $seed : QuizSeries::newSeed(),
            ]);
        }

        return Inertia::render('Quiz', [
            'available' => $available,
            'game' => $game->value,
            'level' => $level->value,
            'seed' => $seed,
            'questions' => $series->questions($game, $level, $seed),
        ]);
    }

    /** Réponse à une question, donnée seulement avec le choix du joueur ; rien n'est enregistré. */
    public function answer(QuizAnswerRequest $request, QuizSeries $series): JsonResponse
    {
        $pick = $series->pick($request->game(), $request->level(), $request->seed(), $request->index());
        abort_if($pick === null, 404);
        $result = $series->reveal($request->game(), $pick, $request->choice());
        abort_if($result === null, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    /**
     * Photo d'une question du jeu du matériel : redirection vers l'image Commons, dont l'adresse
     * contient souvent le nom de la série et n'apparaît donc pas dans la question.
     */
    public function photo(QuizQuestionRequest $request, QuizSeries $series, MaterialQuiz $materials): RedirectResponse
    {
        abort_unless($request->game() === QuizGame::Materials, 404);
        $pick = $series->pick($request->game(), $request->level(), $request->seed(), $request->index());
        abort_if($pick === null, 404);

        return redirect()->away($materials->photoUrl($pick->subject))->header('Cache-Control', 'private, max-age=3600');
    }
}
