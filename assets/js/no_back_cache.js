// Some browsers keep a frozen copy of the previous page in memory and show it when
// you press Back. If that happens, reload so the server checks you are still signed in.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
