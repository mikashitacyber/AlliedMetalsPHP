// On pages with a sidebar (customer/staff/hr/admin portals), this adds a
// menu button to the top bar. On narrow screens the sidebar becomes a slide-in
// drawer; on wide screens the button collapses and expands the sidebar. Does nothing on pages without a .shell-sidebar
// (landing page and login).
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.shell-sidebar');
    const topbar = document.querySelector('.shell-topbar');
    if (!sidebar || !topbar) return;

    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-sidebar-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.appendChild(backdrop);

    const menuBtn = document.createElement('button');
    menuBtn.type = 'button';
    menuBtn.className = 'menu-btn';
    menuBtn.setAttribute('aria-label', 'Open navigation menu');
    menuBtn.setAttribute('aria-expanded', 'false');
    if (!sidebar.id) sidebar.id = 'portal-sidebar';
    menuBtn.setAttribute('aria-controls', sidebar.id);
    menuBtn.textContent = '☰';
    topbar.prepend(menuBtn);

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('show');
        menuBtn.setAttribute('aria-expanded', 'true');
        menuBtn.setAttribute('aria-label', 'Close navigation menu');
    }
    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('show');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.setAttribute('aria-label', 'Open navigation menu');
    }

    function isNarrowScreen() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function syncLayout() {
        if (isNarrowScreen()) {
            document.body.classList.remove('sidebar-collapsed');
            closeSidebar();
        } else {
            closeSidebar();
            menuBtn.setAttribute('aria-expanded', String(!document.body.classList.contains('sidebar-collapsed')));
        }
    }

    // Tablet and phone: toggle the drawer. Desktop: collapse or expand the sidebar.
    menuBtn.addEventListener('click', () => {
        if (isNarrowScreen()) {
            if (sidebar.classList.contains('mobile-open')) closeSidebar();
            else openSidebar();
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            const expanded = !document.body.classList.contains('sidebar-collapsed');
            menuBtn.setAttribute('aria-expanded', String(expanded));
            menuBtn.setAttribute('aria-label', expanded ? 'Close navigation menu' : 'Open navigation menu');
        }
    });
    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeSidebar();
            menuBtn.focus();
        }
    });
    window.addEventListener('resize', syncLayout);
    // Close the drawer automatically after tapping a nav link, so it
    // doesn't stay open covering the page you just navigated to.
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeSidebar));
    syncLayout();
});
