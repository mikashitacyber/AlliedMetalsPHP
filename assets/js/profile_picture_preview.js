document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-profile-picture-input]');
    const control = document.querySelector('[data-profile-picture-control]');
    const removeInput = document.querySelector('[data-profile-picture-remove]');
    const removeButton = document.querySelector('[data-profile-picture-remove-button]');
    const status = document.querySelector('[data-profile-picture-status]');
    const preview = document.querySelector('.profile-picture-picker img, .profile-picture-picker .profile-photo-placeholder');
    if (!(input instanceof HTMLInputElement) || !(control instanceof HTMLElement) || !(removeInput instanceof HTMLInputElement) || !preview) return;

    let previewUrl = null;
    const placeholder = control.dataset.placeholder || '?';

    function showError(message) {
        if (!(status instanceof HTMLElement)) return;
        status.textContent = message;
        status.hidden = false;
    }

    function showPlaceholder() {
        const currentPreview = control.querySelector('.profile-photo-preview');
        if (!currentPreview || currentPreview.classList.contains('profile-photo-placeholder')) return;
        const replacement = document.createElement('div');
        replacement.className = 'profile-photo-preview profile-photo-placeholder';
        replacement.textContent = placeholder;
        currentPreview.replaceWith(replacement);
    }

    if (removeButton instanceof HTMLButtonElement) {
        removeButton.addEventListener('click', () => {
            removeInput.value = '1';
            showPlaceholder();
            removeButton.remove();
        });
    }

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            input.value = '';
            showError('The photo must be no larger than 2 MB.');
            return;
        }
        const extensionIsAllowed = /\.(jpe?g|png|webp)$/i.test(file.name);
        const mimeIsAllowed = file.type === '' || ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
        if (!extensionIsAllowed || !mimeIsAllowed) {
            input.value = '';
            showError('Choose a JPG, PNG, or WebP image.');
            return;
        }

        if (status instanceof HTMLElement) {
            status.textContent = '';
            status.hidden = true;
        }
        removeInput.value = '0';
        removeButton?.remove();
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(file);
        const currentPreview = control.querySelector('.profile-photo-preview');
        if (currentPreview instanceof HTMLImageElement) {
            currentPreview.src = previewUrl;
        } else {
            const image = document.createElement('img');
            image.className = 'profile-photo-preview';
            image.alt = 'Selected profile picture preview';
            image.src = previewUrl;
            currentPreview?.replaceWith(image);
        }
    });
});
