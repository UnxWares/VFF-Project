(function () {
    var french = '.md-paragraph, .markdown-list-style';
    function mark() {
        document.querySelectorAll('.bx--header-panel').forEach(function (el) { el.lang = 'fr'; });
        var main = document.getElementById('main-content');
        if (!main) return;
        if (location.pathname.indexOf('/guide/') !== -1) main.lang = 'fr';
        else main.removeAttribute('lang');
        main.querySelectorAll(french).forEach(function (el) { el.lang = 'fr'; });
    }
    new MutationObserver(mark).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', mark);
})();
