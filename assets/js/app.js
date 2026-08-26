/* Evidence majetku - spolecny JS (bez zavislosti) */
(function () {
    'use strict';

    // --- Prepinac light/dark ---
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var html = document.documentElement;
            var next = html.dataset.theme === 'dark' ? 'light' : 'dark';
            html.dataset.theme = next;
            delete html.dataset.themeAuto;

            document.cookie = 'theme=' + next + '; path=/; max-age=' + (86400 * 365) + '; samesite=Lax'
                + (location.protocol === 'https:' ? '; secure' : '');

            // ulozeni preference k uzivateli (fire-and-forget)
            var csrf = document.querySelector('input[name="_csrf"]');
            if (csrf && window.fetch) {
                var body = new URLSearchParams();
                body.set('theme', next);
                body.set('_csrf', csrf.value);
                fetch(document.querySelector('base') ? document.querySelector('base').href + 'theme' : '/theme', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).catch(function () {});
            }
        });
    }
})();
