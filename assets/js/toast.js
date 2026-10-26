// Shows a small confirmation message at the bottom-right of the screen.
// Call it from anywhere: toast("Saved.");
function toast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    document.getElementById('toast-msg').textContent = message;
    el.classList.add('show');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}
