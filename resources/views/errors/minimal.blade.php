{{--
    Vue d'erreur du framework (401, 402, 404, 500...) : remplace la page brute de Laravel par la page
    de secours du site. Les pages d'erreur courantes (403, 404, 405, 419, 429, 500, 503) passent d'abord
    par bootstrap/app.php (page Inertia « Error », avec en-tête et pied de page).
--}}
@include('errors.fallback', ['status' => (int) trim($__env->yieldContent('code')), 'detail' => null])
