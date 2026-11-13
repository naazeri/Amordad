let appTagsInstanceCount = 0;

customElements.define('app-tags', class extends HTMLElement {
    constructor() {
        super();
        this.tags = [];
        this.selectedSuggestionIndex = -1;
        this.filteredSuggestions = [];
        this.normalizedSuggestions = [];
        this._listId = `app-tags-listbox-${++appTagsInstanceCount}`;
        this._lastAutoAriaLabel = null;

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'app-tags-input-autocomplete-dropdown';
        this.dropdown.id = this._listId;
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.style.display = 'none';
        document.body.appendChild(this.dropdown);

        // Bind methods that need 'this' context
        this.updateDropdownPosition = this.updateDropdownPosition.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);
        this.updateSuggestions = this.updateSuggestions.bind(this);
        this.showDropdown = this.showDropdown.bind(this);
        this.hideDropdown = this.hideDropdown.bind(this);
        this.handleClickOutside = this.handleClickOutside.bind(this);
    }

    static get observedAttributes() {
        return ['max-tags', 'allow-duplicates', 'min-chars', 'placeholder', 'strict-mode', 'suggestions'];
    }

    get maxTags() {
        return parseInt(this.getAttribute('max-tags')) || Infinity;
    }

    get allowDuplicates() {
        return this.hasAttribute('allow-duplicates');
    }

    get minChars() {
        return parseInt(this.getAttribute('min-chars')) || 0;
    }

    get placeholder() {
        return this.getAttribute('placeholder') || App.i18n.get('Type and press Enter to add tags');
    }

    get strictMode() {
        return this.hasAttribute('strict-mode');
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            switch (name) {
                case 'suggestions':
                    this.normalizeSuggestions();
                    if (this.dropdown.style.display !== 'none') {
                        this.updateSuggestions();
                    }
                    break;
                case 'placeholder':
                    if (this.input) {
                        this.input.placeholder = this.placeholder;
                        this.setupInputAccessibility();
                    }
                    break;
                }
        }
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        this.setupInputAccessibility();
        this.normalizeSuggestions();
    }

    disconnectedCallback() {
        this.dropdown.remove();
        window.removeEventListener('scroll', this.updateDropdownPosition);
        window.removeEventListener('resize', this.updateDropdownPosition);
        document.removeEventListener('click', this.handleClickOutside);
    }

    render() {
        const html = `
            <div class="app-tags-list"></div>
            <input type="text" class="app-tags-input" placeholder="${this.placeholder}">
        `;

        this.innerHTML = '';
        this.innerHTML += html;

        this.input = this.querySelector('.app-tags-input');
        this.tagList = this.querySelector('.app-tags-list');
    }

    setupInputAccessibility() {

        if (!this.input) {
            return;
        }

        if (!this.input.hasAttribute('role')) {
            this.input.setAttribute('role', 'combobox');
        }

        const currentAriaLabel = this.input.getAttribute('aria-label');

        if (!this.input.hasAttribute('aria-labelledby') && (!currentAriaLabel || currentAriaLabel === this._lastAutoAriaLabel)) {
            this.input.setAttribute('aria-label', this.placeholder);
            this._lastAutoAriaLabel = this.placeholder;
        }

        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('aria-controls', this._listId);
        this.input.setAttribute('aria-expanded', 'false');
    }

    setupEventListeners() {
        // Input events
        this.input.addEventListener('keydown', this.handleKeydown);
        this.input.addEventListener('input', this.updateSuggestions);
        this.input.addEventListener('focus', this.updateSuggestions);

        // Document and window events
        document.addEventListener('click', this.handleClickOutside);
        window.addEventListener('scroll', this.updateDropdownPosition, true);
        window.addEventListener('resize', this.updateDropdownPosition);
    }

    handleClickOutside(e) {
        if (!this.contains(e.target) && !this.dropdown.contains(e.target)) {
            this.hideDropdown();
        }
    }

    updateDropdownPosition() {
        if (this.dropdown.style.display === 'none') return;

        const inputRect = this.getBoundingClientRect();
        const dropdownHeight = this.dropdown.offsetHeight;
        const windowHeight = window.innerHeight;

        const spaceBelow = windowHeight - inputRect.bottom;
        const showBelow = spaceBelow >= Math.min(dropdownHeight, 200) || spaceBelow >= inputRect.top;

        if (showBelow) {
            this.dropdown.style.top = `${inputRect.bottom + window.scrollY}px`;
        } else {
            this.dropdown.style.top = `${inputRect.top + window.scrollY - dropdownHeight}px`;
        }

        this.dropdown.style.left = `${inputRect.left + window.scrollX}px`;
        this.dropdown.style.width = `${inputRect.width}px`;
    }

    getLabelForValue(value) {
        const suggestion = this.normalizedSuggestions.find(s => s.value === value);
        return suggestion ? suggestion.label : value;
    }

    normalizeSuggestions() {
        const suggestions = this.getSuggestions();
        this.normalizedSuggestions = suggestions.map(suggestion => {
            if (typeof suggestion === 'string') {
                return { value: suggestion, label: suggestion };
            }
            return suggestion;
        });
    }

    getSuggestions() {
        const suggestionsAttr = this.getAttribute('suggestions');
        if (suggestionsAttr) {
            try {
                return JSON.parse(suggestionsAttr);
            } catch (e) {
                // If parsing fails, treat as comma-separated string
                return suggestionsAttr.split(',').map(s => s.trim());
            }
        }
        return [];
    }

    setSuggestions(suggestions) {
        if (Array.isArray(suggestions)) {
            // Convert suggestions to string to match the attribute format
            const suggestionsString = JSON.stringify(suggestions);
            this.setAttribute('suggestions', suggestionsString);
            this.normalizeSuggestions();

            // If dropdown is open, update it
            if (this.dropdown.style.display !== 'none') {
                this.updateSuggestions();
            }

            this.setTags(this.getTags());
        } else {
            throw new Error('Suggestions must be an array');
        }
    }

    handleKeydown(e) {
        switch (e.key) {
            case 'Enter':
                e.preventDefault();
                if (this.selectedSuggestionIndex !== -1) {
                    this.addTag(this.filteredSuggestions[this.selectedSuggestionIndex].value);
                } else if (!this.strictMode) {
                    const inputValue = this.input.value.trim();
                    if (inputValue) {
                        this.addTag(inputValue);
                    }
                }
                break;
            case 'Backspace':
                if (this.input.value === '') {
                    this.removeLastTag();
                }
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.moveSelection(1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.moveSelection(-1);
                break;
            case 'Escape':
                this.hideDropdown();
                break;
            case 'Tab':
                if (this.selectedSuggestionIndex !== -1) {
                    e.preventDefault();
                    this.addTag(this.filteredSuggestions[this.selectedSuggestionIndex].value);
                }
                break;
        }
    }

    updateSuggestions() {
        const value = this.input.value.trim().toLowerCase();

        if (value.length < this.minChars) {
            this.hideDropdown();
            return;
        }

        this.filteredSuggestions = this.normalizedSuggestions.filter(suggestion => {
            const matchesSearch = !value || suggestion.label.toLowerCase().includes(value) ||
                                suggestion.value.toLowerCase().includes(value);
            const notDuplicate = this.allowDuplicates || !this.tags.includes(suggestion.value);
            return matchesSearch && notDuplicate;
        });

        if (this.filteredSuggestions.length === 0) {
            this.hideDropdown();
            return;
        }

        this.selectedSuggestionIndex = -1;
        this.showDropdown();
    }

    showDropdown() {
        if (!this.dropdown.parentNode) {
            document.body.appendChild(this.dropdown);
        }

        this.dropdown.style.display = 'block';
        this.renderDropdown();
        this.updateDropdownPosition();
        this.input.setAttribute('aria-expanded', 'true');
    }

    hideDropdown() {
        this.dropdown.style.display = 'none';
        this.selectedSuggestionIndex = -1;

        if (this.input) {
            this.input.setAttribute('aria-expanded', 'false');
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    renderDropdown() {
        this.dropdown.innerHTML = '';

        this.filteredSuggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            const selected = index === this.selectedSuggestionIndex;

            item.className = `app-tags-autocomplete-item ${selected ? 'selected' : ''}`;
            item.dataset.index = String(index);
            item.id = `${this._listId}-option-${index}`;
            item.textContent = suggestion.label;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.addEventListener('click', () => {
                const index = parseInt(item.dataset.index);
                this.addTag(this.filteredSuggestions[index].value);
            });
            this.dropdown.appendChild(item);
        });

        if (this.selectedSuggestionIndex !== -1) {
            const selectedItem = this.dropdown.children[this.selectedSuggestionIndex];
            if (selectedItem) {
                this.input.setAttribute('aria-activedescendant', selectedItem.id);
            }
        } else if (this.input) {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    moveSelection(direction) {
        if (this.filteredSuggestions.length === 0) return;

        this.selectedSuggestionIndex += direction;

        if (this.selectedSuggestionIndex >= this.filteredSuggestions.length) {
            this.selectedSuggestionIndex = 0;
        } else if (this.selectedSuggestionIndex < 0) {
            this.selectedSuggestionIndex = this.filteredSuggestions.length - 1;
        }

        this.renderDropdown();

        // Ensure selected item is visible in dropdown
        const selectedItem = this.dropdown.children[this.selectedSuggestionIndex];
        if (selectedItem) {
            this.input.setAttribute('aria-activedescendant', selectedItem.id);
            selectedItem.scrollIntoView({ block: 'nearest' });
        }
    }

    addTag(value, silent = false) {
        if (!value) return;

        if (this.tags.length >= this.maxTags) {
            this.dispatchEvent(new CustomEvent('tag-error', {
                detail: { message: 'Maximum tags limit reached' }
            }));
            return;
        }

        if (!this.allowDuplicates && this.tags.includes(value)) {
            this.dispatchEvent(new CustomEvent('tag-error', {
                detail: { message: 'Duplicate tag' }
            }));
            return;
        }

        const label = this.getLabelForValue(value);
        const tagElement = document.createElement('div');
        tagElement.className = 'app-tags-tag';

        const textElement = document.createElement('span');
        textElement.className = 'app-tags-tag-text';
        textElement.textContent = label;

        const removeElement = document.createElement('span');
        removeElement.className = 'app-tags-tag-remove';
        removeElement.textContent = '×';
        removeElement.setAttribute('role', 'button');
        removeElement.setAttribute('tabindex', '0');
        removeElement.setAttribute('aria-label', `${App.i18n.get('Remove')} ${label}`);

        const remove = () => {
            this.removeTag(value);
            tagElement.remove();
        };

        removeElement.addEventListener('click', remove);
        removeElement.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                remove();
            }
        });

        tagElement.appendChild(textElement);
        tagElement.appendChild(removeElement);

        this.tagList.appendChild(tagElement);
        this.tags.push(value);

        this.input.value = '';
        this.hideDropdown();

        if (!silent) {
            this.dispatchEvent(new CustomEvent('tags-changed', {
                detail: { tags: this.tags }
            }));
        }
    }

    removeTag(value) {
        const index = this.tags.indexOf(value);
        if (index > -1) {
            this.tags.splice(index, 1);
            this.dispatchEvent(new CustomEvent('tags-changed', {
                detail: { tags: this.tags }
            }));
        }
    }

    removeLastTag() {
        if (this.tags.length > 0) {
            const lastTag = this.tags[this.tags.length - 1];
            this.removeTag(lastTag);
            this.tagList.lastElementChild.remove();
        }
    }

    // Public API methods
    getTags() {
        return [...this.tags];
    }

    setTags(tags) {
        this.tags = [];
        this.tagList.innerHTML = '';
        tags.forEach(tag => {
            const value = typeof tag === 'string' ? tag : tag.value;
            this.addTag(value, true);
        });
        this.dispatchEvent(new CustomEvent('tags-changed', {
            detail: { tags: this.tags }
        }));
    }

    clearTags() {
        this.tags = [];
        this.tagList.innerHTML = '';
        this.dispatchEvent(new CustomEvent('tags-changed', {
            detail: { tags: this.tags }
        }));
    }
});
