customElements.define('app-avatar', class extends HTMLElement {

    static get observedAttributes() {
        return ['name', 'size', 'color'];
    }

    constructor() {
        super();

        this.canvas = document.createElement('canvas');
        this._lastAutoAriaLabel = null;
    }

    connectedCallback() {
        this.render();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.render();
        }
    }

    render() {

        if (!this.isConnected) {
            return;
        }

        if (!this.contains(this.canvas)) {
            this.replaceChildren(this.canvas);
        }

        this.draw();
    }

    draw() {

        const palette = [
            '#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#34495e', '#16a085', '#27ae60', '#2980b9', '#8e44ad', '#2c3e50',
            '#f1c40f', '#e67e22', '#e74c3c', '#ecf0f1', '#95a5a6', '#f39c12', '#d35400', '#c0392b', '#bdc3c7', '#7f8c8d'
        ];

        const name = (this.getAttribute('name') || '').trim();
        const color = this.getAttribute('color') || null;
        const size = Math.max(parseInt(this.getAttribute('size') || '60', 10) || 60, 16);
        const scale = window.devicePixelRatio || 1;
        const parts = name ? name.toUpperCase().split(/\s+/).filter(Boolean) : [];
        const initials = parts.length > 1
            ? `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`
            : (parts[0] ? parts[0].charAt(0) : '?');
        const baseChar = initials === '?' ? 'A' : initials.charAt(0);
        const colorIndex = Math.abs(baseChar.charCodeAt(0) - 65) % palette.length;
        const context = this.canvas.getContext('2d');

        if (!context) {
            return null;
        }

        this.style.setProperty('--app-avatar-size', `${size}px`);

        if (!this.hasAttribute('role')) {
            this.setAttribute('role', 'img');
        }

        const nextAriaLabel = name || 'Avatar';
        const currentAriaLabel = this.getAttribute('aria-label');

        if (!currentAriaLabel || currentAriaLabel === this._lastAutoAriaLabel) {
            this.setAttribute('aria-label', nextAriaLabel);
            this._lastAutoAriaLabel = nextAriaLabel;
        }

        this.canvas.width = size * scale;
        this.canvas.height = size * scale;

        context.setTransform(scale, 0, 0, scale, 0, 0);
        context.clearRect(0, 0, size, size);
        context.fillStyle = color || palette[colorIndex];
        context.fillRect(0, 0, size, size);
        context.font = `600 ${Math.round(size / 2)}px system-ui, sans-serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillStyle = '#fff';
        context.fillText(initials, size / 2, size / 2);

        return this.canvas.toDataURL();
    }
});
