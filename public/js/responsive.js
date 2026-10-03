// Roster Pro responsive shell enhancements
(() => {
  'use strict';

  const MOBILE_MAX = 1023;

  function isCompact() {
    return window.innerWidth <= MOBILE_MAX;
  }

  function ensureBackdrop() {
    let backdrop = document.querySelector('.rp-offcanvas-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'rp-offcanvas-backdrop';
      backdrop.hidden = true;
      document.body.appendChild(backdrop);
    }
    return backdrop;
  }

  function closeFallbackOffcanvas() {
    const sidebar = document.getElementById('mobileSidebar');
    const backdrop = document.querySelector('.rp-offcanvas-backdrop');
    if (!sidebar) return;

    sidebar.classList.remove('rp-open');
    sidebar.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('rp-menu-open');

    if (backdrop) backdrop.hidden = true;
  }

  function openFallbackOffcanvas() {
    const sidebar = document.getElementById('mobileSidebar');
    if (!sidebar) return;

    const backdrop = ensureBackdrop();
    sidebar.classList.add('rp-open');
    sidebar.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rp-menu-open');
    backdrop.hidden = false;
  }

  function enhanceMobileNavigation() {
    const toggle = document.getElementById('mobileSidebarToggleBtn');
    const sidebar = document.getElementById('mobileSidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', (event) => {
      if (window.bootstrap && window.bootstrap.Offcanvas) return;
      event.preventDefault();
      sidebar.classList.contains('rp-open') ? closeFallbackOffcanvas() : openFallbackOffcanvas();
    });

    const closeButton = sidebar.querySelector('[data-bs-dismiss="offcanvas"]');
    if (closeButton) {
      closeButton.addEventListener('click', () => {
        if (!(window.bootstrap && window.bootstrap.Offcanvas)) {
          closeFallbackOffcanvas();
        }
      });
    }

    sidebar.addEventListener('click', (event) => {
      if (!isCompact()) return;
      const link = event.target.closest('a[href]');
      if (link && !(link.getAttribute('href') || '').startsWith('#')) {
        if (!(window.bootstrap && window.bootstrap.Offcanvas)) {
          closeFallbackOffcanvas();
        }
      }
    });

    ensureBackdrop().addEventListener('click', closeFallbackOffcanvas);

    window.addEventListener('resize', () => {
      if (!isCompact()) closeFallbackOffcanvas();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeFallbackOffcanvas();
    });
  }

  function enhanceTables() {
    document.querySelectorAll('.table-responsive').forEach((wrapper, index) => {
      if (!wrapper.hasAttribute('tabindex')) wrapper.tabIndex = 0;
      if (!wrapper.hasAttribute('role')) wrapper.setAttribute('role', 'region');
      if (!wrapper.hasAttribute('aria-label')) {
        wrapper.setAttribute('aria-label', 'ตารางข้อมูลแบบเลื่อนได้ ' + (index + 1));
      }
    });
  }

  function enhanceMedia() {
    document.querySelectorAll('img').forEach((img) => {
      if (!img.hasAttribute('decoding')) img.decoding = 'async';
      if (!img.hasAttribute('loading') && !img.closest('.top-navbar')) img.loading = 'lazy';
    });
  }

  function markActionGroups() {
    document.querySelectorAll('.card-header, .modal-footer').forEach((container) => {
      const actions = container.querySelectorAll('.btn');
      if (actions.length >= 2) container.classList.add('rp-action-container');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    enhanceMobileNavigation();
    enhanceTables();
    enhanceMedia();
    markActionGroups();
  });
})();
