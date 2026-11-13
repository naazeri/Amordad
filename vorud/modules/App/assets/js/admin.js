const isUserLoggedOut = () => document.getElementById('app-session-login');
const isAppSearchActive = () => document.getElementById('app-search');

let showAppSearch = function(value) {

    let isActive = isAppSearchActive(),
        isLoggedOut = isUserLoggedOut();

    if (!isActive && !isLoggedOut) {
        VueView.ui.modal('app:assets/dialog/app-search.js', {value}, {}, {}, 'app-search');
    } else if (isActive && !isLoggedOut){
        isActive.querySelector('input').focus();
    }
}

window.AppEventStream =  {
    _idle: null,
    _interval: 5000,
    _pending: false,
    _needsRefresh: false,
    _registry: {
        notify: [
            function(evt) {
                App.ui.notify(evt.data.message, evt.data.status || 'info', {
                    timeout: evt.data.timeout || false
                });
            }
        ],

        alert: [
            function(evt) {
                App.ui.alert(evt.data.message);
            }
        ],

        logout: [
            function(evt) {
                App.ui.notify(evt.data?.message || 'You have been logged out!', 'danger');

                setTimeout(() => {
                    window.location.href = App.route('/auth/logout?force=1');
                }, 1500);
            }
        ]
    },

    _schedule(delay = this._interval) {

        if (this._idle) {
            clearTimeout(this._idle);
        }

        this._idle = setTimeout(() => this._check(), delay);
    },

    _syncSessionState(rsp) {

        let isActive = document.getElementById('app-session-login');

        App.csrf = rsp && rsp.csrf || '';

        if (rsp && rsp.status && isActive) {
            isActive.closest('kiss-dialog').close();
        }

        if (rsp && !rsp.status && !isActive) {
            VueView.ui.modal(Vue.defineAsyncComponent(() => import(`${App.route('/auth/dialog')}?v=${App.version}`)));
        }
    },

    _check(force = false) {

        if (this._pending) {
            this._needsRefresh = this._needsRefresh || force;
            return;
        }

        this._pending = true;

        App.request('/app-event-stream').then(rsp => {

            rsp = rsp || {};

            this._syncSessionState(rsp);

            (rsp.events || []).forEach(evt => {
                this.trigger(evt);
            });
        }).catch(() => {
            // keep polling on transient failures
        }).finally(() => {

            this._pending = false;

            if (this._needsRefresh) {
                this._needsRefresh = false;
                this._check();
                return;
            }

            this._schedule();
        });
    },

    start() {

        this.stop();
        this._check(true);
    },

    refresh() {

        this.stop();
        this._check(true);
    },

    stop() {

        if (this._idle) {
            clearTimeout(this._idle);
        }
    },

    on(eventType, fn) {

        if (!this._registry[eventType]) this._registry[eventType] = [];

        this._registry[eventType].push(fn);
    },

    trigger(evt) {

        if (evt.type && this._registry[evt.type]) {
            this._registry[evt.type].forEach(fn => fn(evt));
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {

    AppEventStream.start();

    // bind global command for app search
    Mousetrap.bind(['alt+f', 'ctrl+space'], e => {
        e.preventDefault();
        showAppSearch();
        return false;
    });

    document.addEventListener('keydown', (e) => {

        const IGNORED_KEYS = [
            'Escape', 'Enter', 'Tab',
            'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
            'Backspace', 'Delete', 'Home', 'End', 'PageUp', 'PageDown',
            'Space', 'CapsLock', 'Shift'
        ];

        //ctrl-c, ctrl-v etc.
        if (e.ctrlKey || e.altKey || e.metaKey || IGNORED_KEYS.includes(e.key)) return;

        if (
            e.target.tagName?.toLowerCase() === 'body' &&
            /^[A-Za-zÀ-ÿ]$/u.test(e.key) &&
            !isAppSearchActive() &&
            !isUserLoggedOut()
        ) {
            showAppSearch(e.key);
        }
    }, false);

    document.querySelectorAll('[app-search]').forEach(ele => {
        ele.addEventListener('click', e => {
            e.preventDefault();
            showAppSearch();
        });
    });

}, false);

document.addEventListener('visibilitychange', function() {

    if (document.hidden) {
        return
    }

    AppEventStream.refresh();
    App.storage.load();

}, false);
