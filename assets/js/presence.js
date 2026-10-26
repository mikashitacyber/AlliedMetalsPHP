document.addEventListener('DOMContentLoaded', () => {
    const dot = document.querySelector('.status-dot');
    const managesAvailability = dot?.classList.contains('online') ?? false;
    const idleTimeout = 120000;
    const heartbeatInterval = 45000;
    const heartbeatUrl = new URL('../../presence_heartbeat.php', document.currentScript.src);
    let lastActivity = Date.now();
    let lastHeartbeat = 0;

    function sendHeartbeat() {
        const now = Date.now();
        if (now - lastHeartbeat < heartbeatInterval) return;
        lastHeartbeat = now;

        fetch(heartbeatUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
        }).catch(error => console.error('Could not update employee availability.', error));
    }

    function markActive() {
        if (document.hidden) return;
        lastActivity = Date.now();
        if (managesAvailability && dot.classList.contains('offline')) {
            dot.classList.replace('offline', 'online');
        }
        sendHeartbeat();
    }

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach(eventName => {
        document.addEventListener(eventName, markActive, { passive: true });
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) markActive();
    });

    markActive();

    window.setInterval(() => {
        if (managesAvailability && Date.now() - lastActivity >= idleTimeout) {
            dot.classList.replace('online', 'offline');
        }
    }, 5000);
});
