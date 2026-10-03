(() => {
  'use strict';

  const form = document.getElementById('fieldVisitForm');
  if (!form) return;

  const DB_NAME = 'roster-field-drafts';
  const STORE = 'drafts';
  const TTL = 8 * 60 * 60 * 1000;
  const userId = form.dataset.fieldUser || '0';
  const recordId = form.dataset.fieldRecord || '0';
  const draftKey = 'field-visit:' + userId + ':' + recordId;
  const sessionKeyB64 = form.dataset.fieldDraftKey || '';
  let saveTimer = null;
  let cryptoKeyPromise = null;

  function openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = () => {
        if (!req.result.objectStoreNames.contains(STORE)) {
          req.result.createObjectStore(STORE, { keyPath: 'key' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async function putDraft(record) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE, 'readwrite');
      tx.objectStore(STORE).put(record);
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
    });
  }

  async function getDraft() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE, 'readonly');
      const req = tx.objectStore(STORE).get(draftKey);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => reject(req.error);
    });
  }

  async function deleteDraft() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE, 'readwrite');
      tx.objectStore(STORE).delete(draftKey);
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
    });
  }

  function bytesToBase64(bytes) {
    let binary = '';
    bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
    return btoa(binary);
  }

  function base64ToBytes(value) {
    const binary = atob(value);
    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
  }

  async function getCryptoKey() {
    if (!window.crypto?.subtle || !sessionKeyB64) {
      throw new Error('Web Crypto unavailable');
    }

    if (!cryptoKeyPromise) {
      cryptoKeyPromise = window.crypto.subtle.importKey(
        'raw',
        base64ToBytes(sessionKeyB64),
        { name: 'AES-GCM' },
        false,
        ['encrypt', 'decrypt']
      );
    }

    return cryptoKeyPromise;
  }

  async function encryptData(data) {
    const key = await getCryptoKey();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const plain = new TextEncoder().encode(JSON.stringify(data));
    const encrypted = await window.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, plain);

    return {
      iv: bytesToBase64(iv),
      payload: bytesToBase64(new Uint8Array(encrypted))
    };
  }

  async function decryptData(record) {
    // Backward-compatible one-time recovery of legacy draft records.
    if (record?.data && !record?.payload) {
      return record.data;
    }

    if (!record?.iv || !record?.payload) return null;

    const key = await getCryptoKey();
    const decrypted = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: base64ToBytes(record.iv) },
      key,
      base64ToBytes(record.payload)
    );

    return JSON.parse(new TextDecoder().decode(decrypted));
  }

  function serializeForm() {
    const data = {};
    const fd = new FormData(form);

    for (const [key, value] of fd.entries()) {
      if (key === '_csrf' || key === 'photos[]' || value instanceof File) continue;
      data[key] = String(value);
    }

    form.querySelectorAll('input[type="checkbox"][name]').forEach((el) => {
      data[el.name] = el.checked ? '1' : '0';
    });

    return data;
  }

  function restoreForm(data) {
    Object.entries(data || {}).forEach(([name, rawValue]) => {
      const field = form.elements.namedItem(name);
      if (!field || name === 'status') return;
      if (field instanceof RadioNodeList) return;

      const value = rawValue == null ? '' : String(rawValue);

      if (field.type === 'checkbox') {
        field.checked = value === '1' || value === 'true';
      } else if (field.type !== 'file') {
        field.value = value;
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  function serverEditData() {
    const node = document.getElementById('fieldEditPayload');
    if (!node) return null;

    try {
      return JSON.parse(node.textContent || '{}');
    } catch (_) {
      return null;
    }
  }

  function applyServerEdit() {
    const data = serverEditData();
    if (!data) return false;

    restoreForm(data);

    const status = document.getElementById('fieldLocationStatus');
    if (data.latitude && data.longitude && status) {
      const accuracy = data.accuracy_m ? ' (±' + Math.round(Number(data.accuracy_m)) + ' ม.)' : '';
      status.textContent = Number(data.latitude).toFixed(6) + ', ' + Number(data.longitude).toFixed(6) + accuracy;
    }

    return true;
  }

  function showDraftNotice(message, tone = 'info') {
    const box = document.getElementById('fieldDraftNotice');
    if (!box) return;
    box.className = 'alert alert-' + tone;
    box.textContent = message;
  }

  async function saveDraft() {
    try {
      const encrypted = await encryptData(serializeForm());
      await putDraft({
        key: draftKey,
        savedAt: Date.now(),
        expiresAt: Date.now() + TTL,
        iv: encrypted.iv,
        payload: encrypted.payload
      });
    } catch (_) {
      // On unsupported/restricted browsers, avoid storing identifiable health data unencrypted.
    }
  }

  function queueSave() {
    clearTimeout(saveTimer);
    saveTimer = window.setTimeout(saveDraft, 700);
  }

  async function restoreDraft() {
    const hasServerEdit = applyServerEdit();

    try {
      if (form.dataset.fieldSaved === '1') {
        await deleteDraft();
        return;
      }

      const draft = await getDraft();
      if (!draft) {
        if (hasServerEdit) showDraftNotice('โหลดร่างจากระบบแล้ว คุณสามารถแก้ไขและบันทึกต่อได้', 'info');
        return;
      }

      if (draft.expiresAt < Date.now()) {
        await deleteDraft();
        if (hasServerEdit) showDraftNotice('โหลดร่างจากระบบแล้ว (ร่างออฟไลน์เดิมหมดอายุ)', 'info');
        return;
      }

      const data = await decryptData(draft);
      if (!data) return;

      restoreForm(data);
      const stamp = new Date(draft.savedAt).toLocaleTimeString('th-TH', {
        hour: '2-digit',
        minute: '2-digit'
      });
      showDraftNotice(
        'กู้คืนร่างเข้ารหัสบนอุปกรณ์นี้จากเวลา ' + stamp + ' แล้ว (รูปภาพไม่รวมอยู่ในร่างออฟไลน์)',
        'info'
      );

      // Migrate legacy plaintext record to encrypted format.
      if (draft.data && !draft.payload) queueSave();
    } catch (_) {
      if (hasServerEdit) showDraftNotice('โหลดร่างจากระบบแล้ว ร่างออฟไลน์เดิมไม่สามารถถอดรหัสได้', 'warning');
    }
  }

  form.addEventListener('input', queueSave);
  form.addEventListener('change', queueSave);

  document.querySelectorAll('[data-field-status]').forEach((button) => {
    button.addEventListener('click', () => {
      const status = document.getElementById('fieldStatus');
      if (status) status.value = button.dataset.fieldStatus || 'DRAFT';
    });
  });

  const referral = document.getElementById('referral_required');
  const referralWrap = document.getElementById('fieldReferralNoteWrap');
  function syncReferral() {
    if (!referral || !referralWrap) return;
    referralWrap.classList.toggle('d-none', !referral.checked);
  }
  referral?.addEventListener('change', syncReferral);

  const discard = document.getElementById('fieldDiscardDraft');
  if (discard) {
    discard.addEventListener('click', async () => {
      await deleteDraft();

      if (!applyServerEdit()) {
        form.reset();
        const dateField = document.getElementById('visit_date');
        if (dateField) dateField.value = new Date().toISOString().slice(0, 10);
      }

      syncReferral();
      showDraftNotice(
        recordId !== '0'
          ? 'ล้างร่างออฟไลน์แล้ว และคืนค่าจากร่างบนระบบ'
          : 'ล้างร่างบนอุปกรณ์นี้แล้ว',
        'secondary'
      );
    });
  }

  const chip = document.getElementById('fieldNetworkChip');
  const networkText = document.getElementById('fieldNetworkText');

  function renderNetwork() {
    const online = navigator.onLine;
    chip?.classList.toggle('is-online', online);
    chip?.classList.toggle('is-offline', !online);
    if (networkText) {
      networkText.textContent = online
        ? 'ออนไลน์ พร้อมบันทึกขึ้นระบบ'
        : 'ออฟไลน์ กำลังเก็บร่างเข้ารหัสบนเครื่อง';
    }
  }

  window.addEventListener('online', renderNetwork);
  window.addEventListener('offline', renderNetwork);
  renderNetwork();

  const locationButton = document.getElementById('fieldGetLocation');
  if (locationButton) {
    locationButton.addEventListener('click', () => {
      const status = document.getElementById('fieldLocationStatus');

      if (!navigator.geolocation) {
        if (status) status.textContent = 'อุปกรณ์นี้ไม่รองรับ GPS';
        return;
      }

      locationButton.disabled = true;
      locationButton.innerHTML = '<span class="rp-spinner" aria-hidden="true"></span> กำลังจับพิกัด...';
      if (status) status.textContent = 'กำลังขอพิกัดจากอุปกรณ์';

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude, accuracy } = position.coords;
          document.getElementById('fieldLatitude').value = latitude.toFixed(7);
          document.getElementById('fieldLongitude').value = longitude.toFixed(7);
          document.getElementById('fieldAccuracy').value = Math.round(accuracy || 0);

          if (status) {
            status.textContent =
              latitude.toFixed(6) + ', ' + longitude.toFixed(6) +
              ' (±' + Math.round(accuracy || 0) + ' ม.)';
          }

          locationButton.disabled = false;
          locationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i>จับพิกัดอีกครั้ง';
          queueSave();
        },
        (error) => {
          const message = error.code === 1
            ? 'ไม่ได้รับอนุญาตให้ใช้ตำแหน่ง'
            : 'ไม่สามารถอ่านพิกัดได้ กรุณาลองใหม่ในบริเวณที่รับสัญญาณได้ดี';

          if (status) status.textContent = message;
          locationButton.disabled = false;
          locationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i>ลองจับพิกัดอีกครั้ง';
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
      );
    });
  }

  const photoInput = document.getElementById('fieldPhotos');
  const preview = document.getElementById('fieldPhotoPreview');

  if (photoInput && preview) {
    photoInput.addEventListener('change', () => {
      preview.innerHTML = '';
      if ((photoInput.files || []).length > 3) {
        photoInput.value = '';
        preview.innerHTML = '';
        showDraftNotice('เลือกได้สูงสุด 3 รูป กรุณาเลือกใหม่ไม่เกิน 3 รูป', 'warning');
        return;
      }

      const files = Array.from(photoInput.files || []);
      files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;

        const img = document.createElement('img');
        img.alt = 'ตัวอย่างรูปประกอบ';
        const url = URL.createObjectURL(file);
        img.src = url;
        img.onload = () => URL.revokeObjectURL(url);
        preview.appendChild(img);
      });
    });
  }

  restoreDraft().finally(syncReferral);
})();
