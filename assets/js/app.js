/* =====================================================================
   MemeRadar AI - Front-end helpers
   ===================================================================== */
(function () {
    'use strict';

    const BASE = (window.MR_BASE || '').replace(/\/$/, '');
    let csrfToken = null;

    const MR = {
        /** Fetch a CSRF token (cached). */
        async csrf() {
            if (csrfToken) return csrfToken;
            try {
                const r = await fetch(BASE + '/api/index.php?route=csrf', { credentials: 'same-origin' });
                const j = await r.json();
                csrfToken = j.token;
            } catch (e) { csrfToken = ''; }
            return csrfToken;
        },

        /** Call an API route. POST by default sends JSON + CSRF. */
        async api(route, opts = {}) {
            const method = opts.method || (opts.body ? 'POST' : 'GET');
            const headers = { 'Content-Type': 'application/json' };
            let body = opts.body || null;

            if (method !== 'GET') {
                headers['X-CSRF-Token'] = await MR.csrf();
                if (body && typeof body === 'object') body = JSON.stringify(body);
                else if (!body) body = JSON.stringify({});
            }
            const url = BASE + '/api/index.php?route=' + encodeURIComponent(route)
                + (opts.query ? '&' + opts.query : '');
            const r = await fetch(url, { method, headers, body, credentials: 'same-origin' });
            const j = await r.json();
            if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
            return j;
        },

        toast(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = 'toast-pop ' + type;
            el.textContent = msg;
            Object.assign(el.style, {
                position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999,
                background: type === 'error' ? '#ff4d6d' : '#171923',
                color: type === 'error' ? '#fff' : '#00ff99',
                border: '1px solid rgba(255,255,255,.1)', padding: '12px 18px',
                borderRadius: '12px', boxShadow: '0 10px 30px rgba(0,0,0,.4)', fontWeight: '600'
            });
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        },

        /* ---------- Watchlist buttons ---------- */
        initWatchButtons(removeOnToggle = false) {
            document.querySelectorAll('.watch-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault(); e.stopPropagation();
                    const coinId = btn.dataset.coin;
                    try {
                        const res = await MR.api('watchlist', { body: { coin_id: Number(coinId) } });
                        if (res.watching) {
                            btn.classList.add('active');
                            btn.querySelector('i')?.classList.replace('fa-regular', 'fa-solid');
                            const t = btn.querySelector('.wt'); if (t) t.textContent = 'Watching';
                            MR.toast('Added to watchlist');
                        } else {
                            btn.classList.remove('active');
                            btn.querySelector('i')?.classList.replace('fa-solid', 'fa-regular');
                            const t = btn.querySelector('.wt'); if (t) t.textContent = 'Watch';
                            MR.toast('Removed from watchlist');
                            if (removeOnToggle) btn.closest('tr')?.remove();
                        }
                    } catch (err) {
                        MR.toast(err.message || 'Login required', 'error');
                        if (String(err.message).includes('Auth')) location.href = BASE + '/login.php';
                    }
                });
            });
        },

        /* ---------- Follow wallet buttons ---------- */
        initFollowButtons() {
            document.querySelectorAll('.follow-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    try {
                        const res = await MR.api('follow', { body: { wallet: btn.dataset.wallet } });
                        btn.classList.toggle('active', res.following);
                        MR.toast(res.following ? 'Following wallet' : 'Unfollowed');
                    } catch (err) {
                        MR.toast(err.message || 'Login required', 'error');
                    }
                });
            });
        },

        /* ---------- Browser notifications ---------- */
        requestNotifications() {
            if (!('Notification' in window)) { MR.toast('Notifications not supported', 'error'); return; }
            Notification.requestPermission().then(p => {
                if (p === 'granted') {
                    MR.toast('Browser notifications enabled');
                    new Notification('MemeRadar AI', { body: 'You will now receive alert notifications.' });
                } else {
                    MR.toast('Notifications blocked', 'error');
                }
            });
        },

        /* ---------- Charts ---------- */
        _baseOpts(extra = {}) {
            return Object.assign({
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8a90a2' } },
                    y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8a90a2' } }
                }
            }, extra);
        },
        barChart(id, labels, data, label, color) {
            const ctx = document.getElementById(id); if (!ctx || !window.Chart) return;
            new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets: [{ label, data, backgroundColor: color || '#00ff99', borderRadius: 6 }] },
                options: MR._baseOpts()
            });
        },
        lineChart(id, labels, data, label, color) {
            const ctx = document.getElementById(id); if (!ctx || !window.Chart) return;
            new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: [{ label, data, borderColor: color || '#ffcc00', backgroundColor: 'rgba(255,204,0,.12)', fill: true, tension: .4, pointRadius: 3 }] },
                options: MR._baseOpts()
            });
        },
        radarChart(id, labels, data, label) {
            const ctx = document.getElementById(id); if (!ctx || !window.Chart) return;
            new Chart(ctx, {
                type: 'radar',
                data: { labels, datasets: [{ label, data, borderColor: '#00ff99', backgroundColor: 'rgba(0,255,153,.18)', pointBackgroundColor: '#00ff99' }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { r: { grid: { color: 'rgba(255,255,255,.08)' }, angleLines: { color: 'rgba(255,255,255,.08)' }, pointLabels: { color: '#8a90a2' }, ticks: { display: false, backdropColor: 'transparent' } } }
                }
            });
        }
    };

    window.MR = MR;

    // Auto-init common components
    document.addEventListener('DOMContentLoaded', () => {
        // Tooltips
        if (window.bootstrap) {
            document.querySelectorAll('[title]').forEach(el => {
                try { new bootstrap.Tooltip(el); } catch (e) {}
            });
        }
    });
})();
