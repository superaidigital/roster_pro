// Roster Pro — Accessibility & QA behaviors
(function () {
    'use strict';

    function textOf(el) {
        return (el?.getAttribute('title') || el?.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function enhanceIconButtons(root) {
        root.querySelectorAll('button, a').forEach(function (el) {
            if (el.hasAttribute('aria-label')) return;

            const hasText = Array.from(el.childNodes).some(function (node) {
                return node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '';
            });
            const visibleText = textOf(el);
            const iconOnly = !hasText && el.querySelector('i, svg') && visibleText === '';

            if (iconOnly) {
                const fallback = el.getAttribute('title') || 'ปุ่มคำสั่ง';
                el.setAttribute('aria-label', fallback);
            }
        });
    }

    function enhanceScrollableRegions(root) {
        const selectors = [
            '.table-responsive',
            '.rp-data-table-wrap',
            '.rp-roster-table-wrap',
            '.rp-users-table-wrap'
        ];

        root.querySelectorAll(selectors.join(',')).forEach(function (region) {
            if (!region.hasAttribute('tabindex')) region.setAttribute('tabindex', '0');
            region.classList.add('rp-scroll-region');

            if (!region.hasAttribute('role')) region.setAttribute('role', 'region');

            if (!region.hasAttribute('aria-label')) {
                const table = region.querySelector('table');
                const caption = table?.querySelector('caption')?.textContent?.trim();
                region.setAttribute('aria-label', caption || 'ตารางข้อมูล เลื่อนแนวนอนได้');
            }
        });
    }

    function enhanceTables(root) {
        root.querySelectorAll('table').forEach(function (table) {
            if (!table.querySelector('caption')) {
                const caption = document.createElement('caption');
                caption.className = 'visually-hidden';
                const heading = table.closest('.rp-card, section')?.querySelector('h2, h3, .rp-card__title, .rp-section-title');
                caption.textContent = heading?.textContent?.trim() || 'ตารางข้อมูล';
                table.prepend(caption);
            }

            table.querySelectorAll('thead th').forEach(function (th) {
                if (!th.hasAttribute('scope')) th.setAttribute('scope', 'col');
            });
        });
    }

    function enhanceForms(root) {
        root.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.rpQaBound === '1') return;
            form.dataset.rpQaBound = '1';

            form.addEventListener('submit', function () {
                if (!form.checkValidity()) return;

                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(function (btn) {
                    if (btn.dataset.rpKeepEnabled === '1') return;

                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                    btn.classList.add('rp-is-busy');

                    if (btn.tagName === 'BUTTON' && !btn.querySelector('.rp-inline-spinner')) {
                        const spinner = document.createElement('span');
                        spinner.className = 'rp-inline-spinner';
                        spinner.setAttribute('aria-hidden', 'true');
                        btn.prepend(spinner);
                    }
                });

                form.setAttribute('aria-busy', 'true');
            }, { capture: true });
        });
    }

    function enhanceValidation(root) {
        root.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (field) {
            field.addEventListener('invalid', function () {
                field.setAttribute('aria-invalid', 'true');
                field.classList.add('rp-invalid');
            });

            field.addEventListener('input', function () {
                if (field.checkValidity()) {
                    field.removeAttribute('aria-invalid');
                    field.classList.remove('rp-invalid');
                }
            });

            field.addEventListener('change', function () {
                if (field.checkValidity()) {
                    field.removeAttribute('aria-invalid');
                    field.classList.remove('rp-invalid');
                }
            });
        });
    }

    function enhanceModals(root) {
        root.querySelectorAll('.modal').forEach(function (modal) {
            if (!modal.hasAttribute('aria-modal')) modal.setAttribute('aria-modal', 'true');

            const title = modal.querySelector('.modal-title');
            if (title && !title.id) {
                title.id = 'rpModalTitle_' + Math.random().toString(36).slice(2, 9);
            }
            if (title && !modal.hasAttribute('aria-labelledby')) {
                modal.setAttribute('aria-labelledby', title.id);
            }

            modal.addEventListener('shown.bs.modal', function () {
                const first = modal.querySelector('[autofocus], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href]');
                if (first) first.focus({ preventScroll: true });
            });
        });
    }

    function runEnhancements(root) {
        enhanceIconButtons(root);
        enhanceScrollableRegions(root);
        enhanceTables(root);
        enhanceForms(root);
        enhanceValidation(root);
        enhanceModals(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        runEnhancements(document);

        const main = document.getElementById('rpMainContent');
        if (main && location.hash === '#rpMainContent') {
            main.focus({ preventScroll: true });
        }

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        runEnhancements(node);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
