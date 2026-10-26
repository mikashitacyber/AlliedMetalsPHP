document.addEventListener('DOMContentLoaded', () => {
    const messenger = document.querySelector('[data-direct-messenger]');
    if (!(messenger instanceof HTMLElement)) return;

    const endpoint = messenger.dataset.endpoint;
    const role = messenger.dataset.role;
    const userId = Number(messenger.dataset.userId);
    const csrfToken = messenger.dataset.csrfToken;
    const contactList = messenger.querySelector('[data-contact-list]');
    const searchInput = messenger.querySelector('[data-customer-search]');
    const conversationHead = messenger.querySelector('[data-conversation-head]');
    const thread = messenger.querySelector('[data-message-thread]');
    const form = messenger.querySelector('[data-message-form]');
    const composer = form?.querySelector('textarea');
    const sendButton = form?.querySelector('[data-message-send]');
    const imageInput = form?.querySelector('[data-message-image]');
    const imageSelection = form?.querySelector('[data-image-selection]');
    const clearImageButton = form?.querySelector('[data-image-clear]');
    const errorBox = messenger.querySelector('[data-messenger-error]');
    const backButton = messenger.querySelector('[data-messenger-back]');
    if (!endpoint || !role || !contactList || !conversationHead || !thread ||
        !(form instanceof HTMLFormElement) || !(composer instanceof HTMLTextAreaElement) ||
        !(sendButton instanceof HTMLButtonElement) || !(errorBox instanceof HTMLElement) ||
        !(imageInput instanceof HTMLInputElement) || !(imageSelection instanceof HTMLElement) ||
        !(clearImageButton instanceof HTMLButtonElement)) return;

    let contacts = [];
    let activeContact = null;
    let lastMessageId = 0;
    let loadingContacts = false;
    let loadingMessages = false;
    let selectionVersion = 0;
    let searchTimer = 0;
    let preparedImage = null;
    let imagePreparationVersion = 0;

    function selectedImage() {
        return preparedImage;
    }

    function clearSelectedImage() {
        imagePreparationVersion++;
        preparedImage = null;
        imageInput.value = '';
        imageSelection.textContent = '';
        imageSelection.hidden = true;
        clearImageButton.hidden = true;
    }

    function imageFile(blob, name, type) {
        return new File([blob], name, { type, lastModified: Date.now() });
    }

    async function readImageType(file) {
        const bytes = new Uint8Array(await file.slice(0, 16).arrayBuffer());
        if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
        if (bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) return 'image/png';
        if (String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' &&
            String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') return 'image/webp';
        return '';
    }

    async function decodeImage(file) {
        if (typeof createImageBitmap === 'function') return createImageBitmap(file);
        const url = URL.createObjectURL(file);
        try {
            const image = new Image();
            image.src = url;
            await image.decode();
            return image;
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function compressImage(file) {
        let source;
        try {
            source = await decodeImage(file);
        } catch {
            throw new Error('This phone photo format is not supported by this browser. Save or share it as JPG, then try again.');
        }
        const scale = Math.min(1, 3000 / source.width, 3000 / source.height, Math.sqrt(12000000 / (source.width * source.height)));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(source.width * scale));
        canvas.height = Math.max(1, Math.round(source.height * scale));
        const context = canvas.getContext('2d');
        if (!context) throw new Error('This browser cannot prepare the selected picture.');
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        if (typeof source.close === 'function') source.close();
        let blob = null;
        for (const quality of [0.82, 0.72, 0.62]) {
            blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
            if (blob && blob.size <= 5 * 1024 * 1024) break;
        }
        if (!blob || blob.size > 5 * 1024 * 1024) {
            throw new Error('This picture is still too large after compression. Choose a smaller photo.');
        }
        return imageFile(blob, 'photo.jpg', 'image/jpeg');
    }

    async function prepareImage(file) {
        const detectedType = await readImageType(file);
        const isHeic = /\.(heic|heif|avif)$/i.test(file.name) ||
            /image\/(heic|heif|avif)/i.test(file.type);
        if (['image/jpeg', 'image/png', 'image/webp'].includes(detectedType)) {
            const correctlyTyped = file.type === detectedType ? file : imageFile(file, file.name, detectedType);
            const image = await decodeImage(correctlyTyped);
            const tooLargeDimensions = image.width > 5000 || image.height > 5000 ||
                image.width * image.height > 12000000;
            if (typeof image.close === 'function') image.close();
            if (file.size > 4 * 1024 * 1024 || tooLargeDimensions) return compressImage(correctlyTyped);
            return correctlyTyped;
        }
        if (isHeic || file.type.startsWith('image/')) return compressImage(file);
        throw new Error('Choose a JPG, PNG, or WebP picture. If your phone uses HEIC, save or share it as JPG.');
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    }

    async function request(url, options = {}) {
        const headers = { Accept: 'application/json', ...options.headers };
        if (options.body) {
            headers['X-CSRF-Token'] = csrfToken;
            if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        }
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'The request could not be completed.');
        return result;
    }

    function contactName(contact) {
        return contact.name || 'User';
    }

    function renderContacts() {
        contactList.replaceChildren();
        if (!contacts.length) {
            const empty = document.createElement('div');
            empty.className = 'messenger-empty';
            empty.textContent = role === 'Customer'
                ? 'No employee has started a conversation with you yet.'
                : 'No active customer accounts match this search.';
            contactList.append(empty);
            return;
        }

        contacts.forEach(contact => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `messenger-contact${activeContact?.id === contact.id ? ' selected' : ''}`;
            button.setAttribute('aria-label', `${contactName(contact)} — ${contact.presence_text}`);
            const avatar = document.createElement('span');
            avatar.className = 'messenger-avatar';
            if (contact.picture) {
                const image = document.createElement('img');
                image.src = contact.picture;
                image.alt = '';
                image.addEventListener('error', () => {
                    avatar.textContent = contactName(contact).charAt(0).toUpperCase();
                }, { once: true });
                avatar.append(image);
            } else {
                avatar.textContent = contactName(contact).charAt(0).toUpperCase();
            }
            const details = document.createElement('span');
            details.className = 'messenger-contact-details';
            const name = document.createElement('strong');
            name.textContent = contactName(contact);
            const subtitle = document.createElement('span');
            subtitle.className = `messenger-presence ${contact.presence_class}`;
            subtitle.textContent = contact.detail
                ? `${contact.presence_text} · ${contact.detail}`
                : contact.presence_text;
            const latest = document.createElement('span');
            latest.className = 'messenger-last-message';
            latest.textContent = contact.last_message || (role === 'Customer'
                ? 'Employee conversation'
                : (contact.conversation_id ? 'Open conversation' : 'Start a conversation'));
            details.append(name, subtitle, latest);
            button.append(avatar, details);
            button.addEventListener('click', () => openContact(contact));
            contactList.append(button);
        });
    }

    function renderConversationHead(contact) {
        conversationHead.replaceChildren();
        if (backButton instanceof HTMLButtonElement) conversationHead.append(backButton);
        const avatar = document.createElement('span');
        avatar.className = 'messenger-avatar';
        if (contact.picture) {
            const image = document.createElement('img');
            image.src = contact.picture;
            image.alt = '';
            image.addEventListener('error', () => {
                avatar.textContent = contactName(contact).charAt(0).toUpperCase();
            }, { once: true });
            avatar.append(image);
        } else {
            avatar.textContent = contactName(contact).charAt(0).toUpperCase();
        }
        const details = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = contactName(contact);
        const subtitle = document.createElement('span');
        subtitle.className = `messenger-presence ${contact.presence_class}`;
        subtitle.textContent = contact.detail
            ? `${contact.presence_text} · ${contact.detail}`
            : contact.presence_text;
        details.append(name, subtitle);
        conversationHead.append(avatar, details);
    }

    function addMessage(message) {
        const item = document.createElement('article');
        item.className = `direct-message${Number(message.sender_id) === userId ? ' outgoing' : ' incoming'}${message.image_url && !message.body ? ' image-only' : ''}`;
        if (message.body) {
            const body = document.createElement('p');
            body.textContent = message.body;
            item.append(body);
        }
        if (message.image_url) {
            const link = document.createElement('a');
            link.href = message.image_url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'direct-message-image-link';
            const image = document.createElement('img');
            image.src = message.image_url;
            image.alt = 'Picture shared in this conversation';
            image.loading = 'lazy';
            link.append(image);
            item.append(link);
        }
        const timestamp = document.createElement('time');
        const parsedDate = new Date(String(message.created_at).replace(' ', 'T'));
        timestamp.textContent = Number.isNaN(parsedDate.getTime())
            ? ''
            : parsedDate.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        item.append(timestamp);
        thread.append(item);
    }

    async function refreshMessages() {
        if (!activeContact?.conversation_id || loadingMessages || document.hidden) return;
        loadingMessages = true;
        const conversationId = activeContact.conversation_id;
        const currentSelection = selectionVersion;
        try {
            const url = new URL(endpoint, window.location.href);
            url.searchParams.set('action', 'messages');
            url.searchParams.set('conversation_id', String(conversationId));
            url.searchParams.set('after_id', String(lastMessageId));
            const result = await request(url);
            if (currentSelection !== selectionVersion || activeContact?.conversation_id !== conversationId) return;
            const shouldScroll = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60;
            result.messages.forEach(message => {
                addMessage(message);
                lastMessageId = Math.max(lastMessageId, Number(message.message_id));
            });
            if (result.messages.length && shouldScroll) thread.scrollTop = thread.scrollHeight;
        } catch (error) {
            showError(error.message);
        } finally {
            loadingMessages = false;
        }
    }

    async function openContact(contact) {
        showError('');
        selectionVersion++;
        activeContact = contact;
        messenger.classList.add('conversation-active');
        if (window.matchMedia('(max-width: 760px)').matches) {
            messenger.classList.add('mobile-conversation-open');
        }
        lastMessageId = 0;
        thread.replaceChildren();
        renderContacts();
        renderConversationHead(contact);
        composer.disabled = false;
        sendButton.disabled = false;
        imageInput.disabled = false;
        try {
            if (role === 'Employee' && !contact.conversation_id) {
                const result = await request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify({ action: 'start', customer_id: contact.id }),
                });
                contact.conversation_id = Number(result.conversation_id);
                if (activeContact?.id === contact.id) activeContact.conversation_id = contact.conversation_id;
            }
            await refreshMessages();
            if (!window.matchMedia('(max-width: 760px)').matches) composer.focus();
        } catch (error) {
            composer.disabled = true;
            sendButton.disabled = true;
            imageInput.disabled = true;
            showError(error.message);
        }
    }

    async function refreshContacts() {
        if (loadingContacts || document.hidden) return;
        loadingContacts = true;
        try {
            const url = new URL(endpoint, window.location.href);
            url.searchParams.set('action', 'contacts');
            if (searchInput instanceof HTMLInputElement && searchInput.value.trim()) {
                url.searchParams.set('q', searchInput.value.trim());
            }
            const result = await request(url);
            contacts = result.contacts;
            if (activeContact) {
                const refreshed = contacts.find(contact => contact.id === activeContact.id);
                if (refreshed) {
                    refreshed.conversation_id ||= activeContact.conversation_id;
                    activeContact = refreshed;
                    renderConversationHead(refreshed);
                } else if (role === 'Customer') {
                    activeContact = null;
                    messenger.classList.remove('conversation-active');
                    messenger.classList.remove('mobile-conversation-open');
                    thread.replaceChildren();
                    composer.disabled = true;
                    sendButton.disabled = true;
                    imageInput.disabled = true;
                    clearSelectedImage();
                }
            }
            renderContacts();
        } catch (error) {
            showError(error.message);
        } finally {
            loadingContacts = false;
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!activeContact?.conversation_id || composer.disabled) return;
        const body = composer.value.trim();
        const image = selectedImage();
        if (!body && !image) return;
        sendButton.disabled = true;
        showError('');
        try {
            const payload = new FormData();
            payload.set('action', 'send');
            payload.set('conversation_id', String(activeContact.conversation_id));
            payload.set('body', body);
            if (image) payload.set('image', image);
            await request(endpoint, {
                method: 'POST',
                body: payload,
            });
            composer.value = '';
            clearSelectedImage();
            await refreshMessages();
            thread.scrollTop = thread.scrollHeight;
            await refreshContacts();
        } catch (error) {
            showError(error.message);
        } finally {
            sendButton.disabled = false;
            composer.focus();
        }
    });

    imageInput.addEventListener('change', async () => {
        const file = imageInput.files?.[0];
        if (!file) {
            clearSelectedImage();
            return;
        }
        const preparationVersion = ++imagePreparationVersion;
        preparedImage = null;
        showError('');
        imageSelection.textContent = 'Preparing picture…';
        imageSelection.hidden = false;
        sendButton.disabled = true;
        try {
            const image = await prepareImage(file);
            if (preparationVersion !== imagePreparationVersion) return;
            preparedImage = image;
            imageSelection.textContent = preparedImage.name === 'photo.jpg'
                ? `Ready to send · ${(preparedImage.size / (1024 * 1024)).toFixed(1)} MB`
                : `${preparedImage.name} · ${(preparedImage.size / (1024 * 1024)).toFixed(1)} MB`;
            clearImageButton.hidden = false;
        } catch (error) {
            if (preparationVersion !== imagePreparationVersion) return;
            clearSelectedImage();
            showError(error instanceof Error ? error.message : 'The selected picture could not be prepared.');
        } finally {
            if (preparationVersion === imagePreparationVersion) sendButton.disabled = false;
        }
    });
    clearImageButton.addEventListener('click', clearSelectedImage);
    backButton?.addEventListener('click', () => {
        messenger.classList.remove('conversation-active');
        messenger.classList.remove('mobile-conversation-open');
        composer.blur();
    });

    composer.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    searchInput?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(refreshContacts, 250);
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshContacts();
            refreshMessages();
        }
    });

    refreshContacts();
    window.setInterval(refreshContacts, 15000);
    window.setInterval(refreshMessages, 3000);
});
