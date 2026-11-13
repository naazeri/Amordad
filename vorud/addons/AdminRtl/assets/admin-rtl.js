/**
 * Cockpit Admin RTL Helper
 *
 * Automatically inspects fields in Cockpit admin forms:
 * - Persian fields (_fa) -> sets dir="rtl" and data-rtl="true"
 * - English (_en) and Turkish (_tr) -> sets dir="ltr" and data-rtl="false"
 */

(function () {
    function isPersianField(container) {
        var id = container.id || '';
        if (id.endsWith('_fa') || id.includes('_fa-') || id.includes('-fa-') || id.endsWith('fa')) {
            return true;
        }
        var label = container.querySelector('label');
        if (label) {
            var txt = (label.textContent || '').toLowerCase();
            if (txt.includes('(persian)') || txt.includes('فارسی')) {
                return true;
            }
        }
        return false;
    }

    function isLtrField(container) {
        var id = container.id || '';
        if (id.endsWith('_en') || id.endsWith('_tr') || id.includes('_en-') || id.includes('_tr-') || id.endsWith('slug')) {
            return true;
        }
        var label = container.querySelector('label');
        if (label) {
            var txt = (label.textContent || '').toLowerCase();
            if (txt.includes('(english)') || txt.includes('(turkish)') || txt.includes('انگلیسی') || txt.includes('ترکی')) {
                return true;
            }
        }
        return false;
    }

    function applyDirection() {
        var containers = document.querySelectorAll('app-fieldcontainer');
        containers.forEach(function (container) {
            if (isPersianField(container)) {
                if (container.getAttribute('data-rtl') !== 'true') {
                    container.setAttribute('data-rtl', 'true');
                }
                var inputs = container.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]), textarea');
                inputs.forEach(function (el) {
                    if (el.getAttribute('dir') !== 'rtl') {
                        el.setAttribute('dir', 'rtl');
                        el.setAttribute('data-rtl', 'true');
                    }
                });
                var editors = container.querySelectorAll('.tiptap.ProseMirror, .ProseMirror, [contenteditable="true"]');
                editors.forEach(function (el) {
                    if (el.getAttribute('dir') !== 'rtl') {
                        el.setAttribute('dir', 'rtl');
                        el.setAttribute('data-rtl', 'true');
                    }
                });
            } else if (isLtrField(container)) {
                if (container.getAttribute('data-rtl') !== 'false') {
                    container.setAttribute('data-rtl', 'false');
                }
                var inputs = container.querySelectorAll('input, textarea');
                inputs.forEach(function (el) {
                    if (el.getAttribute('dir') !== 'ltr') {
                        el.setAttribute('dir', 'ltr');
                        el.setAttribute('data-rtl', 'false');
                    }
                });
                var editors = container.querySelectorAll('.tiptap.ProseMirror, .ProseMirror, [contenteditable="true"]');
                editors.forEach(function (el) {
                    if (el.getAttribute('dir') !== 'ltr') {
                        el.setAttribute('dir', 'ltr');
                        el.setAttribute('data-rtl', 'false');
                    }
                });
            }
        });

        // Standalone inputs or textareas with name matching _fa or _en/_tr
        document.querySelectorAll('input[name$="_fa"], textarea[name$="_fa"]').forEach(function (el) {
            if (el.getAttribute('dir') !== 'rtl') {
                el.setAttribute('dir', 'rtl');
                el.setAttribute('data-rtl', 'true');
            }
        });
        document.querySelectorAll('input[name$="_en"], input[name$="_tr"], textarea[name$="_en"], textarea[name$="_tr"]').forEach(function (el) {
            if (el.getAttribute('dir') !== 'ltr') {
                el.setAttribute('dir', 'ltr');
                el.setAttribute('data-rtl', 'false');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyDirection);
    } else {
        applyDirection();
    }

    // Debounced MutationObserver to keep watch on Vue re-renders & dialogs
    var timer = null;
    var observer = new MutationObserver(function () {
        if (timer) return;
        timer = setTimeout(function () {
            timer = null;
            applyDirection();
        }, 60);
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: false
    });
})();
