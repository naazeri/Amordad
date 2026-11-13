customElements.define('app-datetime', class extends HTMLElement {

    static get observedAttributes() {
        return [
            'datetime',
            'type',
            'format',
            'locale',
            'update-interval',
            'numeric',
            'hour12',
            'weekday',
            'era',
            'year',
            'month',
            'day',
            'hour',
            'minute',
            'second',
            'timezone',
            'timezonename'
        ];
    }

    constructor() {
        super();

        this.attachShadow({ mode: 'open' });
        this._interval = null;
        this._formatter = null;
        this._fullFormatter = null;
    }

    connectedCallback() {
        this._initializeFormatters();
        this._startUpdating();
    }

    disconnectedCallback() {
        this._stopUpdating();
    }

    attributeChangedCallback(name, oldValue, newValue) {

        if (oldValue === newValue) {
            return;
        }

        if (name === 'update-interval' || name === 'type') {
            this._initializeFormatters();
            this._startUpdating();
            return;
        }

        this._initializeFormatters();
        this._update();
    }

    _locale() {
        return this.getAttribute('locale')
            || document.documentElement.getAttribute('lang')
            || navigator.language
            || 'en';
    }

    _parseDate(input) {

        if (input === null || input === '') {
            return null;
        }

        if (/^-?\d+$/.test(input)) {
            const timestamp = parseInt(input, 10);
            return new Date(input.length <= 10 ? timestamp * 1000 : timestamp);
        }

        return new Date(input);
    }

    _timezone() {
        return this.getAttribute('timezone') || 'UTC';
    }

    _relativeStyle() {
        const style = this.getAttribute('format') || 'long';
        return ['long', 'short', 'narrow'].includes(style) ? style : 'long';
    }

    _dateTimeFormatOptions() {

        const type = this.getAttribute('type') || 'datetime';
        const format = this.getAttribute('format');
        const options = {};
        const timezone = this._timezone();
        const hour12 = this.getAttribute('hour12');

        if (timezone) {
            options.timeZone = timezone;
        }

        if (hour12 === null) {
            options.hour12 = false;
        } else if (hour12 === 'true') {
            options.hour12 = true;
        } else if (hour12 === 'false') {
            options.hour12 = false;
        }

        ['weekday', 'era', 'year', 'month', 'day', 'hour', 'minute', 'second'].forEach(attr => {
            const value = this.getAttribute(attr);
            if (value) {
                options[attr] = value;
            }
        });

        const timeZoneName = this.getAttribute('timezonename');
        if (timeZoneName) {
            options.timeZoneName = timeZoneName;
        }

        if (format && ['short', 'medium', 'long', 'full'].includes(format)) {
            if (type === 'date') {
                options.dateStyle = format;
            } else if (type === 'time') {
                options.timeStyle = format;
            } else if (type === 'datetime') {
                options.dateStyle = format;
                options.timeStyle = format;
            }
        }

        if (!Object.keys(options).some(key => {
            return ['dateStyle', 'timeStyle', 'year', 'month', 'day', 'hour', 'minute', 'second'].includes(key);
        })) {
            if (type === 'date') {
                options.year = 'numeric';
                options.month = '2-digit';
                options.day = '2-digit';
            } else if (type === 'time') {
                options.hour = '2-digit';
                options.minute = '2-digit';
            } else {
                options.year = 'numeric';
                options.month = '2-digit';
                options.day = '2-digit';
                options.hour = '2-digit';
                options.minute = '2-digit';
            }
        }

        return options;
    }

    _initializeFormatters() {

        const locale = this._locale();
        const type = this.getAttribute('type') || 'datetime';

        try {
            if (type === 'relative') {
                this._formatter = new Intl.RelativeTimeFormat(locale, {
                    numeric: this.getAttribute('numeric') || 'auto',
                    style: this._relativeStyle()
                });
            } else {
                this._formatter = new Intl.DateTimeFormat(locale, this._dateTimeFormatOptions());
            }

            this._fullFormatter = new Intl.DateTimeFormat(locale, Object.assign({
                dateStyle: 'full',
                timeStyle: 'full'
            }, this._timezone() ? { timeZone: this._timezone() } : {}));

        } catch (e) {
            console.warn('Formatter initialization failed:', e, 'Falling back to basic format');
            this._formatter = type === 'relative'
                ? new Intl.RelativeTimeFormat(locale)
                : new Intl.DateTimeFormat(locale);
            this._fullFormatter = new Intl.DateTimeFormat(locale, {
                dateStyle: 'full',
                timeStyle: 'full'
            });
        }
    }

    _startUpdating() {

        this._stopUpdating();
        this._update();

        if ((this.getAttribute('type') || 'datetime') === 'relative') {
            const interval = parseInt(this.getAttribute('update-interval') || '60000', 10) || 60000;
            this._interval = setInterval(() => this._update(), interval);
        }
    }

    _stopUpdating() {
        if (this._interval) {
            clearInterval(this._interval);
            this._interval = null;
        }
    }

    _relativeParts(date) {

        const diffInSeconds = (date.getTime() - Date.now()) / 1000;
        const abs = Math.abs(diffInSeconds);

        if (abs < 60) {
            return [Math.round(diffInSeconds), 'second'];
        }

        if (abs < 3600) {
            return [Math.round(diffInSeconds / 60), 'minute'];
        }

        if (abs < 86400) {
            return [Math.round(diffInSeconds / 3600), 'hour'];
        }

        if (abs < 2592000) {
            return [Math.round(diffInSeconds / 86400), 'day'];
        }

        if (abs < 31536000) {
            return [Math.round(diffInSeconds / 2592000), 'month'];
        }

        return [Math.round(diffInSeconds / 31536000), 'year'];
    }

    _update() {

        const date = this._parseDate(this.getAttribute('datetime'));
        const type = this.getAttribute('type') || 'datetime';
        const span = document.createElement('span');

        if (!date || Number.isNaN(date.getTime())) {
            span.textContent = '';
            span.removeAttribute('title');
            this.shadowRoot.replaceChildren(span);
            return;
        }

        if (type === 'relative') {
            const [value, unit] = this._relativeParts(date);
            span.textContent = this._formatter.format(value, unit);
        } else {
            span.textContent = this._formatter.format(date);
        }

        span.title = this._fullFormatter.format(date);
        this.shadowRoot.replaceChildren(span);
    }
});
