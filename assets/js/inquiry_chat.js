document.addEventListener('DOMContentLoaded', () => {
    const thread = document.querySelector('[data-inquiry-chat]');
    if (!thread) return;

    const status = document.querySelector('[data-chat-status]');
    const viewerRole = thread.dataset.viewerRole;
    const inquiryId = thread.dataset.inquiryId;
    let lastMessageId = Number(thread.dataset.lastMessageId || 0);
    let polling = false;

    function appendMessage(message) {
        const item = document.createElement('div');
        item.className = `msg ${message.sender_role === 'Customer' ? 'cust' : 'staff'}`;

        const sender = document.createElement('div');
        sender.className = 'who';
        sender.textContent = message.sender_role === viewerRole
            ? (viewerRole === 'Customer' ? 'You' : 'You (Staff)')
            : (viewerRole === 'Customer' ? 'Allied Metals' : thread.dataset.customerName);

        const body = document.createElement('div');
        body.className = 'msg-body';
        body.textContent = message.body;

        item.append(sender, body);
        thread.appendChild(item);
    }

    async function refreshMessages() {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const url = new URL(thread.dataset.endpoint, window.location.href);
            url.searchParams.set('id', inquiryId);
            url.searchParams.set('after_id', String(lastMessageId));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Could not refresh this conversation.');

            const shouldScroll = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 40;
            result.messages.forEach(message => {
                appendMessage(message);
                lastMessageId = Math.max(lastMessageId, Number(message.message_id));
            });
            if (result.messages.length && shouldScroll) thread.scrollTop = thread.scrollHeight;
            if (status) status.hidden = true;
        } catch (error) {
            console.error('Unable to refresh inquiry messages:', error);
            if (status) {
                status.textContent = 'Live updates are temporarily unavailable. Retrying…';
                status.hidden = false;
            }
        } finally {
            polling = false;
        }
    }

    window.setInterval(refreshMessages, 3000);
    document.addEventListener('visibilitychange', refreshMessages);
});
