document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-notifications]');
    if (!(widget instanceof HTMLElement)) return;

    const endpoint = widget.dataset.endpoint;
    const token = widget.dataset.csrfToken;
    const panel = widget.querySelector('[data-notification-panel]');
    const list = widget.querySelector('[data-notification-list]');
    const markAll = widget.querySelector('[data-notification-read-all]');
    const triggers = document.querySelectorAll('[data-notification-toggle]');
    if (!endpoint || !token || !(panel instanceof HTMLElement) ||
        !(list instanceof HTMLElement) || triggers.length === 0) return;

    let loading = false;

    function updateCount(count) {
        const value = Math.max(0, Number(count) || 0);
        document.querySelectorAll('[data-notification-count]').forEach(label => {
            label.textContent = value > 99 ? '99+' : String(value);
            label.hidden = value === 0;
        });
        document.querySelectorAll('[data-notification-toggle]').forEach(button => {
            button.setAttribute('aria-label', value ? `Notifications, ${value} unread` : 'Notifications');
        });
    }

    function closePanel() {
        panel.hidden = true;
        triggers.forEach(button => button.setAttribute('aria-expanded', 'false'));
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': token } : {}),
                ...options.headers,
            },
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Notifications could not be loaded.');
        return result;
    }

    function showEmpty(message) {
        const empty = document.createElement('p');
        empty.className = 'notification-empty';
        empty.textContent = message;
        list.replaceChildren(empty);
    }

    async function loadNotifications() {
        if (loading) return;
        loading = true;
        try {
            const result = await api(endpoint);
            updateCount(result.unread_count);
            if (!result.notifications.length) {
                showEmpty('You are all caught up.');
                return;
            }
            list.replaceChildren();
            result.notifications.forEach(notification => {
                const link = document.createElement('a');
                link.className = `notification-item${notification.read_at ? '' : ' unread'}`;
                link.href = notification.target_url;
                link.dataset.notificationId = String(notification.notification_id);
                const title = document.createElement('strong');
                title.textContent = notification.title;
                const message = document.createElement('span');
                message.textContent = notification.message;
                const time = document.createElement('time');
                const createdAt = new Date(String(notification.created_at).replace(' ', 'T'));
                time.textContent = Number.isNaN(createdAt.getTime())
                    ? ''
                    : createdAt.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                link.append(title, message, time);
                list.append(link);
            });
        } catch (error) {
            showEmpty(error instanceof Error ? error.message : 'Notifications could not be loaded.');
        } finally {
            loading = false;
        }
    }

    triggers.forEach(button => button.addEventListener('click', async () => {
        const shouldOpen = panel.hidden;
        if (!shouldOpen) {
            closePanel();
            return;
        }
        panel.hidden = false;
        triggers.forEach(trigger => trigger.setAttribute('aria-expanded', 'true'));
        await loadNotifications();
    }));

    list.addEventListener('click', async event => {
        const link = event.target instanceof Element ? event.target.closest('[data-notification-id]') : null;
        if (!(link instanceof HTMLAnchorElement)) return;
        event.preventDefault();
        const targetUrl = link.href;
        try {
            await api(endpoint, {
                method: 'POST',
                body: JSON.stringify({ action: 'read', notification_id: link.dataset.notificationId }),
            });
            window.location.assign(targetUrl);
        } catch (error) {
            showEmpty(error instanceof Error ? error.message : 'The notification could not be opened.');
        }
    });

    markAll?.addEventListener('click', async () => {
        try {
            await api(endpoint, { method: 'POST', body: JSON.stringify({ action: 'read_all' }) });
            await loadNotifications();
        } catch (error) {
            showEmpty(error instanceof Error ? error.message : 'Notifications could not be updated.');
        }
    });

    document.addEventListener('click', event => {
        if (event.target instanceof Element &&
            !widget.contains(event.target) &&
            !event.target.closest('[data-notification-toggle]')) {
            closePanel();
        }
    });
    document.addEventListener('scroll', event => {
        if (!panel.hidden && event.target instanceof Node && !panel.contains(event.target)) {
            closePanel();
        }
    }, true);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadNotifications();
    });

    loadNotifications();
    window.setInterval(loadNotifications, 30000);
});
