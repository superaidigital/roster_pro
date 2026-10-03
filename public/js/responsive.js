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

  function enhanceStackedTables() {
    document.querySelectorAll('table.table').forEach((table) => {
      if (
        table.classList.contains('table-roster') ||
        table.dataset.mobileView === 'scroll' ||
        table.closest('.calendar-scroll-shell')
      ) {
        table.classList.add('rp-scroll-table');
        return;
      }

      const headRows = table.querySelectorAll('thead tr');
      const firstHead = headRows[0];
      if (!firstHead || headRows.length > 1) {
        table.classList.add('rp-scroll-table');
        return;
      }

      const headers = Array.from(firstHead.querySelectorAll('th')).map((th) =>
        th.textContent.replace(/\s+/g, ' ').trim()
      );

      if (!headers.length || headers.length > 9) {
        table.classList.add('rp-scroll-table');
        return;
      }

      table.classList.add('rp-stack-table');

      table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = Array.from(row.children).filter((cell) => cell.tagName === 'TD');
        if (!cells.length) return;

        const isEmptyState = cells.length === 1 && Number(cells[0].getAttribute('colspan') || 1) > 1;
        if (isEmptyState) {
          row.classList.add('rp-empty-row');
          return;
        }

        cells.forEach((cell, index) => {
          if (!cell.dataset.label) {
            const label = headers[index] || 'ข้อมูล';
            cell.dataset.label = label;
          }
        });
      });
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
    enhanceStackedTables();
    enhanceMedia();
    markActionGroups();
  });
})();
