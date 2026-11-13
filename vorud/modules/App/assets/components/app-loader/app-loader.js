customElements.define('app-loader', class extends HTMLElement {

    static get observedAttributes() {
        return ['label', 'mode'];
    }

    constructor() {
        super();
        this._screenReaderLabel = null;
    }

    connectedCallback() {
        this.render();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) this.render();
    }

    render() {

        let template;
        let mode = this.getAttribute('mode');
        let label = this.statusLabel();

        if (!this.hasAttribute('role')) {
            this.setAttribute('role', 'status');
        }

        if (!this.hasAttribute('aria-live')) {
            this.setAttribute('aria-live', 'polite');
        }

        this.setAttribute('aria-busy', 'true');

        switch (mode) {
            case 'dots':
                template = '<div></div><div></div><div></div><div></div>';
                break;
            default:

                if (mode !== 'orbit') {
                    this.setAttribute('mode', 'orbit');
                }

                template = '<div><div></div><div></div><div></div></div>';
        }

        this.innerHTML = template;
        this._screenReaderLabel = document.createElement('span');
        this._screenReaderLabel.className = 'app-loader-sr';
        this._screenReaderLabel.textContent = label;
        this.appendChild(this._screenReaderLabel);
    }

    statusLabel() {
        return this.getAttribute('label')
            || (window.App && App.i18n ? App.i18n.get('Loading') : 'Loading');
    }
});

customElements.define('app-loader-cover', class extends HTMLElement {

    static get observedAttributes() {
        return ['label', 'mode'];
    }

    constructor() {
        super();
        this._screenReaderLabel = null;
    }

    connectedCallback() {
        this.innerHTML = `
        <div>
            <app-loader></app-loader>
            <div class="app-loader-cover-label"></div>
        </div>
        `;

        this.labelElement = this.querySelector('.app-loader-cover-label');
        this.loaderElement = this.querySelector('app-loader');

        this.render();
    }

    attributeChangedCallback() {
        this.render();
    }

    render() {

        if (!this.labelElement) return;

        const label = this.getAttribute('label') || (window.App && App.i18n ? App.i18n.get('Loading') : 'Loading');
        const visibleLabel = this.getAttribute('label') || '';
        const mode = this.getAttribute('mode');

        if (!this.hasAttribute('role')) {
            this.setAttribute('role', 'status');
        }

        if (!this.hasAttribute('aria-live')) {
            this.setAttribute('aria-live', 'polite');
        }

        this.setAttribute('aria-busy', 'true');

        this.labelElement.innerText = visibleLabel;
        this.loaderElement.setAttribute('label', label);

        if (mode) {
            this.loaderElement.setAttribute('mode', mode);
        } else {
            this.loaderElement.removeAttribute('mode');
        }
    }
});
