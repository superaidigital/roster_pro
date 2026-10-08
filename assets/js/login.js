(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const password = document.getElementById('passwordInput');
    const toggle = document.getElementById('togglePasswordBtn');
    const caps = document.getElementById('capsWarning');
    const form = document.getElementById('loginForm');
    const submit = document.getElementById('loginSubmit');

    const setCaps = (event) => {
      if (!caps || !event.getModifierState) return;
      caps.classList.toggle('is-visible', event.getModifierState('CapsLock'));
    };

    password?.addEventListener('keyup', setCaps);
    password?.addEventListener('keydown', setCaps);
    password?.addEventListener('blur', () => caps?.classList.remove('is-visible'));

    toggle?.addEventListener('click', () => {
      if (!password) return;
      const reveal = password.type === 'password';
      password.type = reveal ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      toggle.setAttribute('aria-label', reveal ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
      const icon = toggle.querySelector('i');
      if (icon) icon.className = reveal ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill';
      password.focus({preventScroll:true});
    });

    form?.addEventListener('submit', () => {
      if (!submit) return;
      submit.disabled = true;
      submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>กำลังเข้าสู่ระบบ';
    });
  });
})();
