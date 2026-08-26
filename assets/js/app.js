/* Evidence majetku - spolecny JS (bez zavislosti) */
(function () {
    'use strict';

    // --- Hromadny vyber v seznamu majetku ---
    var bulkBar = document.getElementById('bulk-bar');
    if (bulkBar) {
        var checks = function () { return document.querySelectorAll('.bulk-check'); };
        var selected = function () {
            return Array.prototype.filter.call(checks(), function (c) { return c.checked; })
                .map(function (c) { return c.value; });
        };
        var refresh = function () {
            var ids = selected();
            bulkBar.style.display = ids.length ? 'flex' : 'none';
            document.getElementById('bulk-count').textContent = ids.length;
            bulkBar.querySelectorAll('[data-bulk]').forEach(function (a) {
                a.href = a.dataset.bulk + '?ids=' + ids.join(',');
            });
        };
        Array.prototype.forEach.call(checks(), function (c) { c.addEventListener('change', refresh); });
        var all = document.getElementById('bulk-all');
        if (all) {
            all.addEventListener('change', function () {
                Array.prototype.forEach.call(checks(), function (c) { c.checked = all.checked; });
                refresh();
            });
        }
    }

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
