(() => {
  'use strict';

  function controller() {
    const p = new URLSearchParams(window.location.search);
    return (p.get('c') || 'dashboard').toLowerCase();
  }

  function action() {
    const p = new URLSearchParams(window.location.search);
    return (p.get('a') || 'index').toLowerCase();
  }

  function enhancePage() {
    const main = document.querySelector('.app-main');
    if (!main) return;

    const firstContainer = Array.from(main.children).find((el) =>
      el.matches('.container, .container-fluid, [class*="container"]')
    );

    if (firstContainer) firstContainer.classList.add('rp-page');

    const scope = firstContainer || main;
    const candidates = Array.from(scope.children).slice(0, 4);
    for (const el of candidates) {
      const heading = el.querySelector && el.querySelector('h1,h2,h3,h4');
      if (heading && !el.matches('.card,.row')) {
        el.classList.add('rp-page-heading');
        break;
      }
    }

    scope.querySelectorAll('.card').forEach((card) => card.classList.add('rp-surface-card'));
    scope.querySelectorAll('form').forEach((form) => form.classList.add('rp-form'));
    scope.querySelectorAll('table').forEach((table) => table.classList.add('rp-data-table'));
  }

  function markActiveSidebar() {
    const c = controller();
    document.querySelectorAll('#desktopSidebar a[href], #mobileSidebar a[href]').forEach((link) => {
      try {
        const u = new URL(link.href, window.location.href);
        const lc = (u.searchParams.get('c') || '').toLowerCase();
        if (lc && lc === c) {
          link.classList.add('active');
          link.setAttribute('aria-current', 'page');
        }
      } catch (_) {}
    });
  }

  function makeBottomNav() {
    if (!document.querySelector('.app-main') || document.querySelector('.rp-mobile-bottom-nav')) return;

    const c = controller();
    const items = [
      ['dashboard', 'index.php?c=dashboard', 'bi-grid-1x2-fill', 'หน้าหลัก'],
      ['roster', 'index.php?c=roster', 'bi-calendar3', 'ตารางเวร'],
      ['leave', 'index.php?c=leave', 'bi-calendar2-minus', 'ลา'],
      ['profile', 'index.php?c=profile', 'bi-person-circle', 'โปรไฟล์']
    ];

    const nav = document.createElement('nav');
    nav.className = 'rp-mobile-bottom-nav';
    nav.setAttribute('aria-label', 'เมนูด่วนบนมือถือ');

    items.forEach(([key, href, icon, label]) => {
      const a = document.createElement('a');
      a.href = href;
      a.className = 'rp-mobile-bottom-nav__item' + (c === key ? ' active' : '');
      a.innerHTML = '<i class="bi ' + icon + '"></i><span>' + label + '</span>';
      nav.appendChild(a);
    });

    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'rp-mobile-bottom-nav__item';
    more.innerHTML = '<i class="bi bi-list"></i><span>เมนู</span>';
    more.setAttribute('aria-label', 'เปิดเมนูเพิ่มเติม');
    more.addEventListener('click', () => {
      const button = document.getElementById('mobileSidebarToggleBtn');
      if (button) button.click();
    });
    nav.appendChild(more);

    document.body.appendChild(nav);
  }

  function mobileActionStack() {
    if (window.innerWidth > 767) return;
    document.querySelectorAll('.card-header, .rp-page-heading').forEach((el) => {
      const buttons = el.querySelectorAll('.btn, form');
      if (buttons.length >= 2) el.classList.add('rp-mobile-actions');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('rp-ui-redesign');
    enhancePage();
    markActiveSidebar();
    makeBottomNav();
    mobileActionStack();
  });
})();
