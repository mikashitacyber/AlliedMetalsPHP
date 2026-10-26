document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-support-chat]');
    if (!widget) return;

    const panel = widget.querySelector('.support-chat-panel');
    const openButton = widget.querySelector('[data-chat-open]');
    const closeButton = widget.querySelector('[data-chat-close]');
    const form = widget.querySelector('[data-chat-form]');
    const input = widget.querySelector('#support-chat-input');
    const messages = widget.querySelector('[data-chat-messages]');
    const sendButton = form.querySelector('button[type="submit"]');

    function openChat() {
        panel.hidden = false;
        openButton.setAttribute('aria-expanded', 'true');
        if (!window.matchMedia('(max-width: 720px)').matches) input.focus();
    }

    function closeChat() {
        panel.hidden = true;
        openButton.setAttribute('aria-expanded', 'false');
        openButton.focus();
    }

    function appendFormattedText(container, text) {
        const parts = text.split(/(\*\*[^*]+\*\*)/g);
        parts.forEach(part => {
            if (part.startsWith('**') && part.endsWith('**')) {
                const strong = document.createElement('strong');
                strong.textContent = part.slice(2, -2);
                container.appendChild(strong);
            } else {
                container.appendChild(document.createTextNode(part));
            }
        });
    }

    function renderAnswer(bubble, text) {
        const lines = text.split(/\r?\n/);
        let list = null;

        lines.forEach(line => {
            const bullet = line.match(/^\s*[-*]\s+(.+)$/);
            if (bullet) {
                if (!list) {
                    list = document.createElement('ul');
                    bubble.appendChild(list);
                }
                const item = document.createElement('li');
                appendFormattedText(item, bullet[1]);
                list.appendChild(item);
                return;
            }

            list = null;
            if (line.trim() === '') return;
            const paragraph = document.createElement('p');
            appendFormattedText(paragraph, line);
            bubble.appendChild(paragraph);
        });
    }

    function appendMessage(role, text, formatted = false) {
        const message = document.createElement('div');
        message.className = `support-chat-message ${role}`;
        const bubble = document.createElement('div');
        bubble.className = 'support-chat-bubble';
        if (formatted) {
            renderAnswer(bubble, text);
        } else {
            const paragraph = document.createElement('p');
            paragraph.textContent = text;
            bubble.appendChild(paragraph);
        }
        message.appendChild(bubble);
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
    }

    openButton.addEventListener('click', openChat);
    closeButton.addEventListener('click', closeChat);
    widget.querySelectorAll('[data-chat-prompt]').forEach(button => {
        button.addEventListener('click', () => {
            input.value = button.dataset.chatPrompt;
            input.focus();
        });
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question) return;

        appendMessage('user', question);
        input.value = '';
        if (window.matchMedia('(max-width: 720px)').matches) input.blur();
        input.disabled = true;
        sendButton.disabled = true;
        sendButton.setAttribute('aria-label', 'Getting answer');

        fetch(widget.dataset.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': widget.dataset.csrfToken,
            },
            body: JSON.stringify({ question }),
        })
            .then(async response => {
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'The assistant could not reply right now.');
                return result;
            })
            .then(result => appendMessage('assistant', result.answer, true))
            .catch(error => appendMessage('assistant', `${error.message} For help, email info@alliedmetals.com.ph or call +63 2 8400 9716.`))
            .finally(() => {
                input.disabled = false;
                sendButton.disabled = false;
                sendButton.setAttribute('aria-label', 'Send message');
                if (!window.matchMedia('(max-width: 720px)').matches) input.focus();
            });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) closeChat();
    });
});
