// Warns after 5 minutes without activity and logs out after 10, matching the server's
// SESSION_TIMEOUT. While the user is active, a throttled ping keeps the server session alive.
(() => {
    const WARN_AFTER = 300;
    const LOGOUT_AFTER = 600;
    const modal = document.getElementById('timeout-modal');
    if (!modal) return;
    const countdown = modal.querySelector('[data-countdown]');
    let idleSeconds = 0;
    let lastPing = Date.now();

    function keepAlive() {
        lastPing = Date.now();
        fetch('keepalive', { method: 'POST', credentials: 'same-origin' });
    }

    function onActivity() {
        if (modal.classList.contains('open')) return; // must click "Stay Logged In"
        idleSeconds = 0;
        if (Date.now() - lastPing > 60000) keepAlive();
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(type =>
        document.addEventListener(type, onActivity, { passive: true }));

    modal.querySelector('[data-stay]').addEventListener('click', () => {
        modal.classList.remove('open');
        idleSeconds = 0;
        keepAlive();
    });

    setInterval(() => {
        idleSeconds++;
        const left = LOGOUT_AFTER - idleSeconds;
        if (left <= 0) {
            window.location.href = 'logout';
        } else if (idleSeconds >= WARN_AFTER) {
            modal.classList.add('open');
            countdown.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
        }
    }, 1000);
})();
