const menuToggle = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('#main-nav');

if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        mainNav.classList.toggle('is-open', !isOpen);
    });

    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            menuToggle.setAttribute('aria-expanded', 'false');
            mainNav.classList.remove('is-open');
        });
    });
}

document.querySelectorAll('[data-history-back]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (link.classList.contains('page-back-static')) {
            return;
        }

        if (window.history.length > 1) {
            event.preventDefault();
            window.history.back();
            return;
        }

        if (link.tagName === 'BUTTON') {
            window.location.assign(link.dataset.fallback || 'index.php');
        }
    });
});

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const adminSidebarToggle = document.querySelector('.admin-sidebar-toggle');
const adminSidebar = document.querySelector('#admin-sidebar');
const adminBackdrop = document.querySelector('[data-sidebar-close]');
const adminSidebarCollapseToggle = document.querySelector('.admin-sidebar-collapse-toggle');
const adminSidebarOpenToggle = document.querySelector('.admin-sidebar-open-toggle');
const adminShell = adminSidebar ? adminSidebar.closest('.admin-shell') : null;

const revealItems = document.querySelectorAll('[data-reveal]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (revealItems.length > 0 && !reducedMotion && 'IntersectionObserver' in window) {
    document.body.classList.add('motion-ready');
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealItems.forEach((item) => revealObserver.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}

if (adminSidebarToggle && adminSidebar) {
    const closeSidebar = () => {
        adminSidebarToggle.setAttribute('aria-expanded', 'false');
        adminSidebar.classList.remove('is-open');
        if (adminBackdrop) {
            adminBackdrop.classList.remove('is-open');
        }
    };

    adminSidebarToggle.addEventListener('click', () => {
        const isOpen = adminSidebarToggle.getAttribute('aria-expanded') === 'true';
        adminSidebarToggle.setAttribute('aria-expanded', String(!isOpen));
        adminSidebar.classList.toggle('is-open', !isOpen);
        if (adminBackdrop) {
            adminBackdrop.classList.toggle('is-open', !isOpen);
        }
    });

    if (adminBackdrop) {
        adminBackdrop.addEventListener('click', closeSidebar);
    }

    adminSidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
}

if (adminSidebar && adminShell) {
    const mobileAdminLayout = window.matchMedia('(max-width: 960px)');
    const setSidebarExpanded = (isExpanded) => {
        adminShell.classList.toggle('sidebar-collapsed', !isExpanded);
        adminSidebar.inert = !isExpanded;
        adminSidebar.setAttribute('aria-hidden', String(!isExpanded));

        [adminSidebarCollapseToggle, adminSidebarOpenToggle].forEach((toggle) => {
            if (!toggle) {
                return;
            }

            toggle.setAttribute('aria-expanded', String(isExpanded));
            const label = isExpanded ? 'Perkecil sidebar' : 'Buka sidebar';
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);
        });
    };

    if (adminSidebarCollapseToggle) {
        adminSidebarCollapseToggle.addEventListener('click', () => setSidebarExpanded(false));
    }

    if (adminSidebarOpenToggle) {
        adminSidebarOpenToggle.addEventListener('click', () => setSidebarExpanded(true));
    }

    mobileAdminLayout.addEventListener('change', (event) => {
        if (event.matches) {
            setSidebarExpanded(true);
        }
    });
}
