

let textcompleteInstanceCount = 0;

customElements.define('app-textcomplete', class extends HTMLElement {

    constructor() {
        super();
        this.itemList = [];
        this.triggerChar = '@';
        this.isAutocompleteActive = false;
        this._connected = false;
        this._ignoreInputEvent = false;
        this._listId = `app-textcomplete-list-${++textcompleteInstanceCount}`;
        this._lastAutoAriaLabel = null;

        this.autocompleteList = document.createElement('div');
        this.autocompleteList.className = 'app-textcomplete-autocomplete-list';
        this.autocompleteList.id = this._listId;
        this.autocompleteList.setAttribute('role', 'listbox');

        this._handleInput = e => this.handleInput(e);
        this._handleKeyDown = e => this.handleKeyDown(e);
        this._handleClickOutside = e => this.handleClickOutside(e);
    }

    static get observedAttributes() {
        return ['items', 'trigger'];
    }

    connectedCallback() {

        if (this._connected) {
            return;
        }

        this.input = this.querySelector('input, textarea');

        if (!this.input) {
            this.input = document.createElement('input');
            this.input.type = 'text';
            this.input.placeholder = 'Enter text...';
            this.appendChild(this.input);
        }

        this.input.addEventListener('input', this._handleInput);
        this.input.addEventListener('keydown', this._handleKeyDown);
        document.addEventListener('click', this._handleClickOutside);

        this.setupInputAccessibility();
        this.updateItems();
        this.updateTrigger();
        this._connected = true;
    }

    disconnectedCallback() {

        if (!this._connected) {
            return;
        }

        this.hideItems();

        if (this.input) {
            this.input.removeEventListener('input', this._handleInput);
            this.input.removeEventListener('keydown', this._handleKeyDown);
        }

        document.removeEventListener('click', this._handleClickOutside);

        this._connected = false;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'items') {
            this.updateItems();
        } else if (name === 'trigger') {
            this.updateTrigger();
        }
    }

    updateItems() {
        const itemsAttr = this.getAttribute('items');
        this.itemList = itemsAttr ? itemsAttr.split(',').map(s => s.trim()) : [];
    }

    updateTrigger() {
        const triggerAttr = this.getAttribute('trigger');
        this.triggerChar = triggerAttr ? triggerAttr : '@';
    }

    setupInputAccessibility() {

        if (!this.input.hasAttribute('role') && this.input.tagName !== 'TEXTAREA') {
            this.input.setAttribute('role', 'combobox');
        }

        if (this.input.tagName === 'TEXTAREA' && !this.input.hasAttribute('aria-multiline')) {
            this.input.setAttribute('aria-multiline', 'true');
        }

        const label = this.input.getAttribute('placeholder') || 'Text input';
        const currentAriaLabel = this.input.getAttribute('aria-label');

        if (!this.input.hasAttribute('aria-labelledby') && (!currentAriaLabel || currentAriaLabel === this._lastAutoAriaLabel)) {
            this.input.setAttribute('aria-label', label);
            this._lastAutoAriaLabel = label;
        }

        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('aria-controls', this._listId);
        this.input.setAttribute('aria-expanded', 'false');
    }

    handleInput(e) {

        if (this._ignoreInputEvent) {
            return;
        }

        const cursorPosition = e.target.selectionStart;
        const inputValue = e.target.value;
        const lastTriggerIndex = inputValue.lastIndexOf(this.triggerChar, cursorPosition);

        if (lastTriggerIndex !== -1 && lastTriggerIndex === cursorPosition - 1) {
            this.showItems(this.itemList);
        } else if (lastTriggerIndex !== -1) {
            const currentWord = inputValue.slice(lastTriggerIndex + 1, cursorPosition);
            const filteredItems = this.itemList.filter(item =>
                item.toLowerCase().startsWith(currentWord.toLowerCase())
            );
            this.showItems(filteredItems);
        } else {
            this.hideItems();
        }
    }

    showItems(items) {

        this.autocompleteList.innerHTML = '';

        if (items.length === 0) {
            this.hideItems();
            return;
        }

        // Append autocomplete list to body
        if (!this.autocompleteList.parentNode) {
            document.body.appendChild(this.autocompleteList);
        }

        const rect = this.input.getBoundingClientRect();

        Object.assign(this.autocompleteList.style, {
            top: `${rect.bottom + window.scrollY}px`,
            left: `${rect.left + window.scrollX}px`,
            width: `${rect.width}px`,
        });

        items.forEach((item, index) => {
            const div = document.createElement('div');
            div.textContent = item;
            div.className = 'autocomplete-item';
            div.id = `${this._listId}-option-${index}`;
            div.setAttribute('role', 'option');
            div.setAttribute('aria-selected', 'false');
            div.addEventListener('click', () => this.selectItem(item));
            this.autocompleteList.appendChild(div);
        });

        this.autocompleteList.style.display = 'block';
        this.isAutocompleteActive = true;
        this.setAttribute('active', 'true');
        this.input.setAttribute('aria-expanded', 'true');
        this.input.removeAttribute('aria-activedescendant');
    }

    hideItems() {
        this.autocompleteList.style.display = 'none';
        this.isAutocompleteActive = false;
        this.setAttribute('active', '');

        if (this.input) {
            this.input.setAttribute('aria-expanded', 'false');
            this.input.removeAttribute('aria-activedescendant');
        }

        if (this.autocompleteList.parentNode) {
            this.autocompleteList.parentNode.removeChild(this.autocompleteList);
        }
    }

    selectItem(item) {
        const cursorPosition = this.input.selectionStart;
        const inputValue = this.input.value;
        const lastTriggerIndex = inputValue.lastIndexOf(this.triggerChar, cursorPosition);

        const newValue = inputValue.slice(0, lastTriggerIndex) + item + inputValue.slice(cursorPosition);
        this.input.value = newValue;
        this.input.focus();

        const newCursorPosition = lastTriggerIndex + item.length;
        this.input.setSelectionRange(newCursorPosition, newCursorPosition);
        this.hideItems();
        this.notifyInputChanged();

        this.dispatchEvent(new CustomEvent('textcomplete-select', {
            bubbles: true,
            detail: {
                input: this.input,
                item,
                newValue
            }
        }));
    }

    handleKeyDown(e) {
        if (!this.isAutocompleteActive) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = this.autocompleteList.getElementsByClassName('autocomplete-item');
            if (items.length === 0) return;

            const activeItem = this.autocompleteList.querySelector('.autocomplete-item.active');
            let nextItem;

            if (!activeItem) {
                nextItem = e.key === 'ArrowDown' ? items[0] : items[items.length - 1];
            } else {
                activeItem.classList.remove('active');
                activeItem.setAttribute('aria-selected', 'false');
                const currentIndex = Array.from(items).indexOf(activeItem);
                const nextIndex = e.key === 'ArrowDown'
                    ? (currentIndex + 1) % items.length
                    : (currentIndex - 1 + items.length) % items.length;
                nextItem = items[nextIndex];
            }

            nextItem.classList.add('active');
            nextItem.setAttribute('aria-selected', 'true');
            this.input.setAttribute('aria-activedescendant', nextItem.id);
            nextItem.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            const activeItem = this.autocompleteList.querySelector('.autocomplete-item.active');
            if (activeItem) {
                e.preventDefault();
                e.stopImmediatePropagation();
                this.selectItem(activeItem.textContent);
            }
        } else if (e.key === ' ' || e.key === 'Escape') {
            this.cancelAutocomplete(e.key === 'Escape');
        }
    }

    cancelAutocomplete(notify = true) {
        this.hideItems();
        // Remove any partial input after the trigger character
        const cursorPosition = this.input.selectionStart;
        const inputValue = this.input.value;
        const lastTriggerIndex = inputValue.lastIndexOf(this.triggerChar, cursorPosition);
        if (lastTriggerIndex !== -1) {
            this.input.value = inputValue.slice(0, lastTriggerIndex + 1);
            this.input.setSelectionRange(lastTriggerIndex + 1, lastTriggerIndex + 1);
            if (notify) {
                this.notifyInputChanged();
            }
        }
    }

    handleClickOutside(e) {
        if (!this.contains(e.target)) {
            this.hideItems();
        }
    }

    notifyInputChanged() {
        this._ignoreInputEvent = true;

        try {
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
        } finally {
            this._ignoreInputEvent = false;
        }
    }
});
