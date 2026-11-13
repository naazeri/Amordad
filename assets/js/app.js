/**
 * Amordad — Vanilla JS (no frameworks)
 */
(function () {
    'use strict';

    /* ========== 1. Home Tile Image Rotation ========== */
    function initTileRotation() {
        var tiles = document.querySelectorAll('[data-tile-images]');
        if (!tiles.length) return;

        // Respect the user's reduced-motion preference: keep the first image only
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var intervalMs = 4000;
        var timers = [];

        // Rotation images are deferred (data-src) so they don't compete with the
        // first paint; load them once the page has finished loading.
        function loadDeferred() {
            document.querySelectorAll('[data-tile-images] img[data-src]').forEach(function (img) {
                img.src = img.getAttribute('data-src');
                img.removeAttribute('data-src');
            });
        }
        if (document.readyState === 'complete') {
            loadDeferred();
        } else {
            window.addEventListener('load', loadDeferred);
        }

        function start() {
            stop();
            tiles.forEach(function (wrap) {
                var imgs = wrap.querySelectorAll('img');
                if (imgs.length <= 1) return;

                var current = 0;
                imgs.forEach(function (img, i) {
                    if (!img.classList.contains('hidden')) current = i;
                });

                timers.push(setInterval(function () {
                    var next = current;
                    while (next === current) {
                        next = Math.floor(Math.random() * imgs.length);
                    }
                    // Skip images that have not loaded yet
                    if (imgs[next].hasAttribute('data-src')) return;
                    imgs[current].classList.add('hidden');
                    imgs[current].setAttribute('aria-hidden', 'true');
                    imgs[next].classList.remove('hidden');
                    current = next;
                }, intervalMs));
            });
        }

        function stop() {
            timers.forEach(clearInterval);
            timers = [];
        }

        // Pause while the tab is in the background
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop(); else start();
        });

        start();
    }

    /* ========== 2. Project Detail Tabs ========== */
    function initTabs() {
        document.querySelectorAll('.project-tabs').forEach(function (container) {
            var tabBtns = Array.prototype.slice.call(container.querySelectorAll('[data-tab-btn]'));
            if (!tabBtns.length) return;

            function activate(btn, focus) {
                var btnRectBefore = btn.getBoundingClientRect();

                tabBtns.forEach(function (b) {
                    var active = b === btn;
                    b.classList.toggle('project-tabs__btn--active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                    b.setAttribute('tabindex', active ? '0' : '-1');
                    var panel = container.querySelector('[data-tab-panel="' + b.getAttribute('data-tab-btn') + '"]');
                    if (panel) {
                        if (active) panel.removeAttribute('hidden'); else panel.setAttribute('hidden', '');
                    }
                });

                // When description tab is active, hide main content body (except gallery); show it on other tabs
                var contentBody = document.getElementById('project-content');
                if (contentBody) {
                    var isDesc = btn.getAttribute('data-tab-btn') === 'desc';
                    if (isDesc) {
                        contentBody.setAttribute('hidden', '');
                    } else {
                        contentBody.removeAttribute('hidden');
                    }
                }

                if (focus) {
                    try {
                        btn.focus({ preventScroll: true });
                    } catch (e) {
                        btn.focus();
                    }
                }

                // Keep scroll position completely stable and prevent jumps
                var btnRectAfter = btn.getBoundingClientRect();
                var delta = btnRectAfter.top - btnRectBefore.top;
                if (Math.abs(delta) > 0.5) {
                    window.scrollBy(0, delta);
                }
            }

            tabBtns.forEach(function (btn, index) {
                btn.addEventListener('click', function () {
                    activate(btn, false);
                });

                // Arrow keys move between tabs (direction-aware), Home/End jump
                btn.addEventListener('keydown', function (e) {
                    var rtl = getComputedStyle(container).direction === 'rtl';
                    var forward = rtl ? 'ArrowLeft' : 'ArrowRight';
                    var backward = rtl ? 'ArrowRight' : 'ArrowLeft';
                    var target = null;

                    if (e.key === forward) target = tabBtns[(index + 1) % tabBtns.length];
                    else if (e.key === backward) target = tabBtns[(index - 1 + tabBtns.length) % tabBtns.length];
                    else if (e.key === 'Home') target = tabBtns[0];
                    else if (e.key === 'End') target = tabBtns[tabBtns.length - 1];

                    if (target) {
                        e.preventDefault();
                        activate(target, true);
                    }
                });
            });

            // Sync initial state of content body with the active tab button
            var initialActiveBtn = container.querySelector('.project-tabs__btn--active') || tabBtns[0];
            var initialContentBody = document.getElementById('project-content');
            if (initialActiveBtn && initialContentBody) {
                if (initialActiveBtn.getAttribute('data-tab-btn') === 'desc') {
                    initialContentBody.setAttribute('hidden', '');
                }
            }
        });
    }

    /* ========== 3. Lightbox ========== */
    var lightbox = {
        overlay: null,
        img: null,
        prevBtn: null,
        nextBtn: null,
        closeBtn: null,
        slides: [],
        current: 0,
        returnFocus: null,

        init: function () {
            this.overlay = document.getElementById('lightbox');
            if (!this.overlay) return;

            var triggers = document.querySelectorAll('[data-lightbox-src]');
            if (!triggers.length) return;

            this.img = this.overlay.querySelector('img');
            this.prevBtn = this.overlay.querySelector('.lightbox-prev');
            this.nextBtn = this.overlay.querySelector('.lightbox-next');
            this.closeBtn = this.overlay.querySelector('.lightbox-close');

            var self = this;

            // Close button
            this.closeBtn.addEventListener('click', function () {
                self.close();
            });

            // Backdrop click
            this.overlay.addEventListener('click', function (e) {
                if (e.target === self.overlay) self.close();
            });

            // Prev/Next
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', function () { self.prev(); });
            }
            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', function () { self.next(); });
            }

            // Keyboard: Escape, arrows, and keep Tab focus inside the dialog
            document.addEventListener('keydown', function (e) {
                if (!self.isOpen()) return;
                if (e.key === 'Escape') self.close();
                if (e.key === 'ArrowLeft') self.prev();
                if (e.key === 'ArrowRight') self.next();
                if (e.key === 'Tab') self.trapFocus(e);
            });

            // Touch Swipe for Mobile
            var touchStartX = 0;
            var touchStartY = 0;
            this.overlay.addEventListener('touchstart', function (e) {
                if (e.touches && e.touches.length > 0) {
                    touchStartX = e.touches[0].clientX;
                    touchStartY = e.touches[0].clientY;
                }
            }, { passive: true });

            this.overlay.addEventListener('touchend', function (e) {
                if (e.changedTouches && e.changedTouches.length > 0) {
                    var diffX = e.changedTouches[0].clientX - touchStartX;
                    var diffY = e.changedTouches[0].clientY - touchStartY;
                    if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX < 0) {
                            self.next();
                        } else {
                            self.prev();
                        }
                    }
                }
            }, { passive: true });

            // Gallery links (they work as plain links to the full image without JS)
            triggers.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.slides = [];
                    var seen = {};
                    document.querySelectorAll('[data-lightbox-src]').forEach(function (b) {
                        var src = b.getAttribute('data-lightbox-src');
                        if (src && !seen[src]) {
                            seen[src] = true;
                            self.slides.push({
                                src: src,
                                alt: b.getAttribute('data-lightbox-alt') || ''
                            });
                        }
                    });

                    var targetSrc = link.getAttribute('data-lightbox-src');
                    var targetIndex = 0;
                    for (var i = 0; i < self.slides.length; i++) {
                        if (self.slides[i].src === targetSrc) {
                            targetIndex = i;
                            break;
                        }
                    }

                    self.returnFocus = link;
                    self.open(targetIndex);
                });
            });
        },

        isOpen: function () {
            return this.overlay.classList.contains('lightbox-overlay--open');
        },

        focusables: function () {
            return Array.prototype.filter.call(
                this.overlay.querySelectorAll('button'),
                function (b) { return !b.disabled && b.style.display !== 'none'; }
            );
        },

        trapFocus: function (e) {
            var items = this.focusables();
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            } else if (!this.overlay.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
            }
        },

        open: function (index) {
            this.current = index;
            this.show();
            this.overlay.removeAttribute('hidden');
            this.overlay.classList.add('lightbox-overlay--open');
            document.body.style.overflow = 'hidden';
            this.closeBtn.focus();
        },

        close: function () {
            this.overlay.classList.remove('lightbox-overlay--open');
            this.overlay.setAttribute('hidden', '');
            document.body.style.overflow = '';
            if (this.returnFocus) {
                this.returnFocus.focus();
                this.returnFocus = null;
            }
        },

        show: function () {
            var slide = this.slides[this.current];
            if (!slide) return;
            this.img.src = slide.src;
            this.img.alt = slide.alt;

            if (this.prevBtn) {
                this.prevBtn.disabled = (this.current === 0);
            }
            if (this.nextBtn) {
                this.nextBtn.disabled = (this.current >= this.slides.length - 1);
            }
            // Hide prev/next if only 1 slide
            if (this.slides.length <= 1) {
                if (this.prevBtn) this.prevBtn.style.display = 'none';
                if (this.nextBtn) this.nextBtn.style.display = 'none';
            } else {
                if (this.prevBtn) this.prevBtn.style.display = '';
                if (this.nextBtn) this.nextBtn.style.display = '';
            }
            // Keep focus inside the dialog if the focused arrow just got disabled
            if (document.activeElement && document.activeElement.disabled) {
                this.closeBtn.focus();
            }
        },

        prev: function () {
            if (this.current > 0) {
                this.current--;
                this.show();
            }
        },

        next: function () {
            if (this.current < this.slides.length - 1) {
                this.current++;
                this.show();
            }
        }
    };

    /* ========== 4. Mobile Menu Toggle ========== */
    function initMobileMenu() {
        var btn = document.getElementById('mobile-menu-btn');
        var nav = document.getElementById('mobile-nav');
        if (!btn || !nav) return;

        function closeMenu() {
            nav.classList.remove('header__mobile-nav--open');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('header__mobile-nav--open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close on link click
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        // Close when the viewport grows to the desktop layout
        var desktop = window.matchMedia('(min-width: 768px)');
        var onChange = function (e) { if (e.matches) closeMenu(); };
        if (desktop.addEventListener) desktop.addEventListener('change', onChange);
        else if (desktop.addListener) desktop.addListener(onChange);
    }

    /* ========== 5. Locale Switchers (header + mobile selects) ========== */
    function initLocaleSwitcher() {
        // Option values are the target URLs. Navigate on mouse/touch selection
        // or on Enter, but not on every arrow key press (WCAG 3.2.2).
        document.querySelectorAll('[data-locale-switcher]').forEach(function (select) {
            var initial = select.value;
            var keyboardChange = false;

            function go() {
                if (select.value && select.value !== initial) {
                    window.location.href = select.value;
                }
            }

            select.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    go();
                } else if (e.key.indexOf('Arrow') === 0 || e.key === 'Home' || e.key === 'End') {
                    keyboardChange = true;
                }
            });

            select.addEventListener('change', function () {
                if (keyboardChange) {
                    keyboardChange = false;
                    return;
                }
                go();
            });

            select.addEventListener('blur', function () {
                keyboardChange = false;
            });
        });
    }

    /* ========== Initialize on DOM ready ========== */
    document.addEventListener('DOMContentLoaded', function () {
        initTileRotation();
        initTabs();
        lightbox.init();
        initMobileMenu();
        initLocaleSwitcher();
    });
})();
