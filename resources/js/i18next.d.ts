// Types d'i18next pour le front. Les chaînes viennent de Laravel (`lang/*/*.php`) et arrivent
// au démarrage : elles ne sont pas typées, donc les variables d'une clé ne peuvent pas être
// vérifiées à la compilation. Depuis i18next 27, `strictInterpolationChecks` vaut `true` par
// défaut et refuse tout `t(clé)` sans options ; on garde le comportement de la version 26.
import 'i18next';

declare module 'i18next' {
    interface CustomTypeOptions {
        strictInterpolationChecks: false;
    }
}
