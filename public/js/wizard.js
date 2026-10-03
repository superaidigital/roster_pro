(() => {
  'use strict';

  function normalizeTitle(text, fallback) {
    const clean = String(text || '').replace(/\s+/g, ' ').trim();
    return clean || fallback;
  }

  function createStep(title) {
    const section = document.createElement('section');
    section.className = 'rp-wizard-step-panel';
    section.dataset.rpStep = '';
    section.dataset.rpStepTitle = title;
    return section;
  }

  function autoGroupProfile(form) {
    const headings = Array.from(form.children).filter((el) => el.matches('h5.section-title'));
    if (headings.length < 2) return;

    const configuredTitles = (form.dataset.rpWizardTitles || '')
      .split('|')
      .map((value) => value.trim())
      .filter(Boolean);

    headings.forEach((heading, index) => {
      if (heading.parentElement !== form) return;

      const title = configuredTitles[index] || normalizeTitle(heading.textContent, 'ขั้นตอน ' + (index + 1));
      const section = createStep(title);
      form.insertBefore(section, heading);

      let node = heading;
      while (node) {
        const next = node.nextElementSibling;
        if (node !== heading && node.matches('h5.section-title')) break;

        section.appendChild(node);

        if (!next || next.matches('h5.section-title')) break;
        node = next;
      }
    });
  }

  function autoGroupLeave(form) {
    const typeField = form.querySelector('#leave_type');
    const startField = form.querySelector('#start_date');
    const reasonField = form.querySelector('textarea[name="reason"]');
    const medSection = form.querySelector('#med_cert_section');
    const submitButton = form.querySelector('#btnSubmitLeave');

    if (!typeField || !startField || !reasonField || !submitButton) return;

    const typeBlock = typeField.closest('.mb-4') || typeField.parentElement;
    const dateBlock = startField.closest('.row') || startField.parentElement;
    const reasonBlock = reasonField.closest('.mb-4') || reasonField.parentElement;

    const definitions = [
      { title: 'ประเภทการลา', nodes: [typeBlock] },
      { title: 'ช่วงวันที่ลา', nodes: [dateBlock] },
      { title: 'รายละเอียดและยืนยัน', nodes: [reasonBlock, medSection, submitButton] }
    ];

    definitions.forEach((definition) => {
      const validNodes = definition.nodes.filter(Boolean).filter((node) => node.parentElement === form);
      if (!validNodes.length) return;

      const section = createStep(definition.title);
      form.insertBefore(section, validNodes[0]);
      validNodes.forEach((node) => section.appendChild(node));
    });
  }

  function createProgressUI(steps) {
    const wrapper = document.createElement('div');
    wrapper.className = 'rp-wizard-progress';
    wrapper.setAttribute('aria-label', 'ความคืบหน้าของแบบฟอร์ม');

    const desktop = document.createElement('ol');
    desktop.className = 'rp-wizard-desktop';
    desktop.innerHTML = steps.map((step, index) => `
      <li class="rp-wizard-progress-step" data-rp-progress-step="${index}">
        <span class="rp-wizard-number">${index + 1}</span>
        <span class="rp-wizard-progress-copy">
          <strong>${step.dataset.rpStepTitle || 'ขั้นตอน ' + (index + 1)}</strong>
          <small>ขั้นตอนที่ ${index + 1}</small>
        </span>
      </li>
    `).join('');

    const mobile = document.createElement('div');
    mobile.className = 'rp-wizard-mobile';
    mobile.innerHTML = `
      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <strong data-rp-mobile-step-label>ขั้นตอนที่ 1 จาก ${steps.length}</strong>
        <span data-rp-mobile-percent>0%</span>
      </div>
      <div class="rp-wizard-mobile-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <span data-rp-mobile-bar></span>
      </div>
      <div class="rp-wizard-mobile-title" data-rp-mobile-title></div>
    `;

    wrapper.append(desktop, mobile);
    return wrapper;
  }

  function firstInvalidControl(step) {
    const controls = Array.from(step.querySelectorAll('input, select, textarea'))
      .filter((control) => !control.disabled && control.type !== 'hidden');

    return controls.find((control) =>
      typeof control.checkValidity === 'function' && !control.checkValidity()
    ) || null;
  }

  function reportInvalid(control) {
    if (!control) return;
    if (typeof control.reportValidity === 'function') control.reportValidity();
    control.focus({ preventScroll: true });
    control.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function validateStep(step) {
    const invalid = firstInvalidControl(step);
    if (!invalid) return true;
    reportInvalid(invalid);
    return false;
  }

  function addNavigation(step, index, total) {
    const nav = document.createElement('div');
    nav.className = 'rp-wizard-actions';

    if (index > 0) {
      const back = document.createElement('button');
      back.type = 'button';
      back.className = 'btn btn-light rp-wizard-back';
      back.innerHTML = '<i class="bi bi-arrow-left me-1"></i> ย้อนกลับ';
      nav.appendChild(back);
    }

    if (index < total - 1) {
      const next = document.createElement('button');
      next.type = 'button';
      next.className = 'btn btn-primary rp-wizard-next ms-auto';
      next.innerHTML = 'ถัดไป <i class="bi bi-arrow-right ms-1"></i>';
      nav.appendChild(next);
    }

    if (nav.children.length) step.appendChild(nav);
  }

  function enhanceForm(form) {
    if (form.dataset.rpWizardReady === '1') return;

    if (form.dataset.rpWizard === 'profile') autoGroupProfile(form);
    if (form.dataset.rpWizard === 'leave') autoGroupLeave(form);

    const steps = Array.from(form.querySelectorAll(':scope > [data-rp-step]'));
    if (steps.length < 2) return;

    form.dataset.rpWizardReady = '1';
    form.classList.add('rp-wizard-ready');

    form.noValidate = true;

    const progress = createProgressUI(steps);
    progress.style.setProperty('--rp-step-count', String(steps.length));
    const firstStep = steps[0];
    form.insertBefore(progress, firstStep);

    steps.forEach((step, index) => addNavigation(step, index, steps.length));

    let current = 0;

    function render(nextIndex, shouldFocus = true) {
      current = Math.max(0, Math.min(steps.length - 1, nextIndex));

      steps.forEach((step, index) => {
        const active = index === current;
        step.hidden = !active;
        step.classList.toggle('is-active', active);
        step.setAttribute('aria-hidden', active ? 'false' : 'true');
      });

      progress.querySelectorAll('[data-rp-progress-step]').forEach((item, index) => {
        item.classList.toggle('is-complete', index < current);
        item.classList.toggle('is-current', index === current);
      });

      const percent = Math.round(((current + 1) / steps.length) * 100);
      const mobileLabel = progress.querySelector('[data-rp-mobile-step-label]');
      const mobilePercent = progress.querySelector('[data-rp-mobile-percent]');
      const mobileBar = progress.querySelector('[data-rp-mobile-bar]');
      const mobileTrack = progress.querySelector('.rp-wizard-mobile-track');
      const mobileTitle = progress.querySelector('[data-rp-mobile-title]');

      if (mobileLabel) mobileLabel.textContent = `ขั้นตอนที่ ${current + 1} จาก ${steps.length}`;
      if (mobilePercent) mobilePercent.textContent = percent + '%';
      if (mobileBar) mobileBar.style.width = percent + '%';
      if (mobileTrack) mobileTrack.setAttribute('aria-valuenow', String(percent));
      if (mobileTitle) mobileTitle.textContent = steps[current].dataset.rpStepTitle || '';

      form.dataset.rpCurrentStep = String(current + 1);

      if (shouldFocus) {
        progress.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    form.addEventListener('click', (event) => {
      const nextButton = event.target.closest('.rp-wizard-next');
      const backButton = event.target.closest('.rp-wizard-back');

      if (nextButton) {
        if (!validateStep(steps[current])) return;
        render(current + 1);
      }

      if (backButton) {
        render(current - 1);
      }
    });

    form.addEventListener('submit', (event) => {
      for (let index = 0; index < steps.length; index += 1) {
        const invalid = firstInvalidControl(steps[index]);
        if (invalid) {
          event.preventDefault();
          render(index);
          window.setTimeout(() => reportInvalid(invalid), 0);
          return;
        }
      }
    });

    render(0, false);
  }

  function createSkeleton(target, rows = 4) {
    if (!target) return null;

    const skeleton = document.createElement('div');
    skeleton.className = 'rp-skeleton-screen';
    skeleton.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < rows; index += 1) {
      const row = document.createElement('div');
      row.className = 'rp-skeleton-row';
      row.innerHTML = '<span></span><span></span><span></span>';
      skeleton.appendChild(row);
    }

    target.dataset.rpPreviousDisplay = target.style.display || '';
    target.style.display = 'none';
    target.insertAdjacentElement('beforebegin', skeleton);
    return skeleton;
  }

  function removeSkeleton(target) {
    if (!target) return;
    const previous = target.previousElementSibling;
    if (previous && previous.classList.contains('rp-skeleton-screen')) previous.remove();
    target.style.display = target.dataset.rpPreviousDisplay || '';
    delete target.dataset.rpPreviousDisplay;
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-rp-wizard]').forEach(enhanceForm);
  });

  window.RosterWizard = { enhance: enhanceForm };
  window.RosterLoading = { showSkeleton: createSkeleton, hideSkeleton: removeSkeleton };
})();
