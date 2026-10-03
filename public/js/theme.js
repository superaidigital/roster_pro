(() => {
  'use strict';

  const STORAGE_KEY = 'rp-theme';
  const root = document.documentElement;

  function normalize(value) {
    return value === 'dark' ? 'dark' : 'light';
  }

  function currentTheme() {
    return normalize(root.getAttribute('data-theme') || localStorage.getItem(STORAGE_KEY));
  }

  function updateButtons(theme) {
    document.querySelectorAll('[data-rp-theme-toggle]').forEach((button) => {
      const icon = button.querySelector('[data-rp-theme-icon]');
      const label = button.querySelector('[data-rp-theme-label]');
      const isDark = theme === 'dark';

      button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      button.setAttribute('aria-label', isDark ? 'เปลี่ยนเป็นโหมดสว่าง' : 'เปลี่ยนเป็นโหมดมืด');
      button.setAttribute('title', isDark ? 'โหมดสว่าง' : 'โหมดมืด');

      if (icon) {
        icon.className = 'bi ' + (isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill');
      }
      if (label) {
        label.textContent = isDark ? 'โหมดสว่าง' : 'โหมดมืด';
      }
    });
  }

  function applyTheme(theme, persist = true) {
    theme = normalize(theme);
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme);

    if (persist) {
      localStorage.setItem(STORAGE_KEY, theme);
    }

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', theme === 'dark' ? '#071521' : '#f8fbfd');
    }

    updateButtons(theme);
    window.dispatchEvent(new CustomEvent('roster:themechange', { detail: { theme } }));
  }

  function toggleTheme() {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(currentTheme(), false);

    document.addEventListener('click', (event) => {
      const toggle = event.target.closest('[data-rp-theme-toggle]');
      if (!toggle) return;
      event.preventDefault();
      toggleTheme();
    });
  });

  window.RosterTheme = {
    get: currentTheme,
    set: applyTheme,
    toggle: toggleTheme
  };
})();
