(function () {
    var french = '.md-paragraph, .markdown-list-style';
    function mark() {
        document.querySelectorAll('.bx--header-panel').forEach(function (el) { el.lang = 'fr'; });
        var main = document.getElementById('main-content');
        if (!main) return;
        var guide = location.pathname.match(/\/guide(-en|-de)?\//);
        if (guide) {
            main.lang = guide[1] ? guide[1].slice(1) : 'fr';
            return;
        }
        main.removeAttribute('lang');
        main.querySelectorAll(french).forEach(function (el) { el.lang = 'fr'; });
    }
    new MutationObserver(mark).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', mark);
})();
