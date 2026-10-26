// Adds a "Show / Hide" button to every password field on the page, so you can
// check what you typed (for example a temporary password you are about to give
// to a new user). Works on login, Admin account forms and profile pages alike.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="password"]').forEach(input => {
        if (input.dataset.pwEnhanced) return;
        input.dataset.pwEnhanced = '1';

        const wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';               // never submits the form
        btn.className = 'pw-toggle';
        btn.textContent = 'Show';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('aria-pressed', 'false');

        btn.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.textContent = reveal ? 'Hide' : 'Show';
            btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            input.focus();
        });
        wrap.appendChild(btn);
    });
});
