customElements.define('app-actionbar', class extends HTMLElement {

    static get observedAttributes() {
        return ['space'];
    }

    static syncBodyPadding() {

        if (!document.body) {
            return;
        }

        if (typeof this._basePaddingBottom === 'undefined') {
            this._basePaddingBottom = document.body.style.paddingBottom || '';
        }

        const bars = Array.from(document.querySelectorAll('app-actionbar')).filter(bar => {
            return bar.isConnected && bar.shouldReserveSpace() && bar.offsetHeight > 0;
        });

        if (!bars.length) {
            document.body.style.paddingBottom = this._basePaddingBottom;
            return;
        }

        const maxHeight = Math.max(...bars.map(bar => {
            return Math.ceil(bar.getBoundingClientRect().height || bar.offsetHeight || 0);
        }));

        const basePadding = this._basePaddingBottom || '0px';

        document.body.style.paddingBottom = `calc(${basePadding} + 2rem + ${maxHeight}px)`;
    }

    constructor() {
        super();

        this._connected = false;
        this._resizeObserver = null;
        this._mutationObserver = null;
        this._frame = null;
        this._settleTimer = null;
        this._settleTries = 0;

        this._onWindowLoad = () => this.scheduleUpdate();
        this._onWindowResize = () => this.scheduleUpdate();
        this._onViewportChange = () => this.scheduleUpdate();
    }

    connectedCallback() {

        if (this._connected) {
            return;
        }

        this._connected = true;

        window.addEventListener('load', this._onWindowLoad);
        window.addEventListener('resize', this._onWindowResize, { passive: true });

        if (window.visualViewport) {
            try {
                window.visualViewport.addEventListener('resize', this._onViewportChange, { passive: true });
                window.visualViewport.addEventListener('scroll', this._onViewportChange, { passive: true });
            } catch (e) {}
        }

        if ('ResizeObserver' in window) {
            this._resizeObserver = new ResizeObserver(() => this.scheduleUpdate());
            this._resizeObserver.observe(this);
        }

        if ('MutationObserver' in window) {
            this._mutationObserver = new MutationObserver(() => this.scheduleUpdate());
            this._mutationObserver.observe(this, {
                childList: true,
                subtree: true,
                characterData: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'hidden']
            });
        }

        this.startSettleLoop();
        this.scheduleUpdate();
    }

    disconnectedCallback() {

        if (!this._connected) {
            return;
        }

        this._connected = false;

        window.removeEventListener('load', this._onWindowLoad);
        window.removeEventListener('resize', this._onWindowResize);

        if (window.visualViewport) {
            try {
                window.visualViewport.removeEventListener('resize', this._onViewportChange);
                window.visualViewport.removeEventListener('scroll', this._onViewportChange);
            } catch (e) {}
        }

        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
            this._resizeObserver = null;
        }

        if (this._mutationObserver) {
            this._mutationObserver.disconnect();
            this._mutationObserver = null;
        }

        if (this._frame) {
            cancelAnimationFrame(this._frame);
            this._frame = null;
        }

        this.stopSettleLoop();
        this.constructor.syncBodyPadding();
    }

    attributeChangedCallback(name, oldValue, newValue) {

        if (oldValue !== newValue) {
            this.scheduleUpdate();
        }
    }

    shouldReserveSpace() {
        return this.getAttribute('space') !== 'false';
    }

    startSettleLoop() {

        this.stopSettleLoop();

        this._settleTries = 0;
        this._settleTimer = window.setInterval(() => {

            this._settleTries++;
            this.scheduleUpdate();

            if (!this.isConnected || this.offsetHeight > 0 || this._settleTries >= 20) {
                this.stopSettleLoop();
            }
        }, 50);
    }

    stopSettleLoop() {

        if (this._settleTimer) {
            clearInterval(this._settleTimer);
            this._settleTimer = null;
        }
    }

    scheduleUpdate() {

        if (this._frame) {
            return;
        }

        this._frame = requestAnimationFrame(() => {
            this._frame = null;
            this.update();
        });
    }

    update() {
        this.constructor.syncBodyPadding();
    }
});
