// Roster Pro global progress indicators
(() => {
  'use strict';

  let activeRequests = 0;
  let progressValue = 0;
  let trickleTimer = null;

  function elements() {
    return {
      root: document.getElementById('rpGlobalProgress'),
      bar: document.getElementById('rpGlobalProgressBar'),
      label: document.getElementById('rpGlobalProgressLabel'),
      live: document.getElementById('rpProgressLive')
    };
  }

  function setProgress(value, label = '') {
    const { root, bar, label: labelEl, live } = elements();
    if (!root || !bar) return;

    progressValue = Math.max(0, Math.min(100, value));
    root.classList.add('is-visible');
    root.setAttribute('aria-valuenow', String(Math.round(progressValue)));
    bar.style.transform = 'scaleX(' + (progressValue / 100) + ')';

    if (label && labelEl) labelEl.textContent = label;
    if (label && live) live.textContent = label;
  }

  function start(label = 'กำลังโหลด...') {
    activeRequests += 1;
    if (activeRequests > 1) return;

    clearInterval(trickleTimer);
    setProgress(8, label);

    trickleTimer = window.setInterval(() => {
      if (progressValue < 88) {
        const increment = progressValue < 45 ? 7 : progressValue < 70 ? 4 : 1.5;
        setProgress(progressValue + increment);
      }
    }, 280);
  }

  function done(label = 'เสร็จสิ้น') {
    activeRequests = Math.max(0, activeRequests - 1);
    if (activeRequests > 0) return;

    clearInterval(trickleTimer);
    setProgress(100, label);

    window.setTimeout(() => {
      const { root, bar, label: labelEl } = elements();
      if (root) root.classList.remove('is-visible');
      if (bar) bar.style.transform = 'scaleX(0)';
      if (labelEl) labelEl.textContent = '';
      progressValue = 0;
    }, 320);
  }

  function fail(label = 'เกิดข้อผิดพลาด') {
    activeRequests = 0;
    clearInterval(trickleTimer);
    const { root } = elements();
    if (root) root.classList.add('is-error');
    setProgress(100, label);

    window.setTimeout(() => {
      if (root) {
        root.classList.remove('is-visible', 'is-error');
      }
      progressValue = 0;
    }, 1200);
  }

  function setButtonLoading(button, label = 'กำลังประมวลผล...') {
    if (!button || button.dataset.rpLoading === '1') return;

    button.dataset.rpLoading = '1';
    button.dataset.rpOriginalHtml = button.innerHTML;
    button.disabled = true;
    button.classList.add('rp-btn-loading');
    button.innerHTML =
      '<span class="rp-spinner" aria-hidden="true"></span>' +
      '<span class="rp-loading-text">' + label + '</span>';
  }

  function restoreButton(button) {
    if (!button || button.dataset.rpLoading !== '1') return;
    button.disabled = false;
    button.classList.remove('rp-btn-loading');
    button.innerHTML = button.dataset.rpOriginalHtml || button.innerHTML;
    delete button.dataset.rpLoading;
    delete button.dataset.rpOriginalHtml;
  }

  function enhanceNavigation() {
    document.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (!link) return;
      if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;

      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

      try {
        const url = new URL(link.href, window.location.href);
        if (url.origin === window.location.origin) {
          start('กำลังเปิดหน้า...');
        }
      } catch (_) {}
    });

    window.addEventListener('pageshow', () => {
      activeRequests = 1;
      done();
    });
  }

  function enhanceForms() {
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (event.defaultPrevented) return;

      const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitter instanceof HTMLButtonElement) {
        const label = submitter.dataset.loadingText || 'กำลังบันทึก...';
        setButtonLoading(submitter, label);
      }

      form.classList.add('rp-form-submitting');
      form.setAttribute('aria-busy', 'true');
      start(form.dataset.progressLabel || 'กำลังบันทึกข้อมูล...');
    });
  }

  function enhanceFetch() {
    if (!window.fetch) return;

    const originalFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
      const init = args[1] || {};
      const requestHeaders = new Headers(init.headers || {});
      if (requestHeaders.get('X-Roster-Silent') === '1') {
        return originalFetch(...args);
      }

      start('กำลังประมวลผล...');
      try {
        const response = await originalFetch(...args);
        if (!response.ok && response.status >= 500) {
          fail('ระบบตอบกลับผิดพลาด');
        } else {
          done();
        }
        return response;
      } catch (error) {
        fail('เชื่อมต่อไม่สำเร็จ');
        throw error;
      }
    };
  }

  function enhanceXHR() {
    if (!window.XMLHttpRequest) return;

    const OriginalXHR = window.XMLHttpRequest;
    const originalOpen = OriginalXHR.prototype.open;
    const originalSend = OriginalXHR.prototype.send;

    OriginalXHR.prototype.open = function(method, url, ...rest) {
      this.__rpTrackProgress = true;
      return originalOpen.call(this, method, url, ...rest);
    };

    OriginalXHR.prototype.send = function(body) {
      if (this.__rpTrackProgress) {
        start('กำลังโหลดข้อมูล...');
        this.addEventListener('loadend', () => done(), { once: true });
        this.addEventListener('error', () => fail('เชื่อมต่อไม่สำเร็จ'), { once: true });
      }
      return originalSend.call(this, body);
    };
  }

  document.addEventListener('DOMContentLoaded', () => {
    enhanceNavigation();
    enhanceForms();
    enhanceFetch();
    enhanceXHR();
  });

  window.RosterProgress = {
    start,
    set: setProgress,
    done,
    fail,
    setButtonLoading,
    restoreButton
  };
})();
