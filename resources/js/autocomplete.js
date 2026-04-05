/**
 * Alpine.js autocomplete component for {{variable}} and {{>fragment}} patterns.
 * Monitors a textarea for trigger patterns and shows a positioned dropdown.
 */
export default function autocomplete() {
    return {
        showDropdown: false,
        items: [],
        filteredItems: [],
        selectedIndex: 0,
        triggerType: null, // 'variable' or 'fragment'
        triggerStart: null,
        query: '',

        // Caches
        _variablesCache: null,
        _fragmentsCache: null,

        async fetchVariables() {
            if (this._variablesCache) return this._variablesCache;
            try {
                const res = await fetch('/internal/variables');
                this._variablesCache = await res.json();
            } catch {
                this._variablesCache = [];
            }
            return this._variablesCache;
        },

        async fetchFragments() {
            if (this._fragmentsCache) return this._fragmentsCache;
            try {
                const res = await fetch('/internal/fragments');
                this._fragmentsCache = await res.json();
            } catch {
                this._fragmentsCache = [];
            }
            return this._fragmentsCache;
        },

        async handleInput(event) {
            const textarea = event.target;
            const pos = textarea.selectionStart;
            const text = textarea.value.substring(0, pos);

            // Check for {{> pattern (fragment include)
            const includeMatch = text.match(/\{\{>\s*([a-z0-9-]*)$/i);
            if (includeMatch) {
                this.triggerType = 'fragment';
                this.triggerStart = pos - includeMatch[0].length;
                this.query = includeMatch[1].toLowerCase();
                const fragments = await this.fetchFragments();
                this.items = fragments.map(f => ({ value: f.slug, label: f.name, slug: f.slug }));
                this.filterItems();
                return;
            }

            // Check for {{ pattern (variable)
            const varMatch = text.match(/\{\{\s*([a-z_0-9]*)$/i);
            if (varMatch) {
                this.triggerType = 'variable';
                this.triggerStart = pos - varMatch[0].length;
                this.query = varMatch[1].toLowerCase();
                const variables = await this.fetchVariables();
                this.items = variables.map(v => ({ value: v, label: v }));
                this.filterItems();
                return;
            }

            this.dismiss();
        },

        filterItems() {
            if (!this.query) {
                this.filteredItems = this.items;
            } else {
                this.filteredItems = this.items.filter(item =>
                    item.value.toLowerCase().includes(this.query) ||
                    item.label.toLowerCase().includes(this.query)
                );
            }
            this.selectedIndex = 0;
            this.showDropdown = this.filteredItems.length > 0;
        },

        handleKeydown(event) {
            if (!this.showDropdown) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.selectedIndex = (this.selectedIndex + 1) % this.filteredItems.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.selectedIndex = (this.selectedIndex - 1 + this.filteredItems.length) % this.filteredItems.length;
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                if (this.filteredItems.length > 0) {
                    event.preventDefault();
                    this.insertSelected();
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                this.dismiss();
            }
        },

        insertSelected() {
            const item = this.filteredItems[this.selectedIndex];
            if (!item) return;

            const textarea = this.$refs.editorTextarea;
            if (!textarea) return;

            const before = textarea.value.substring(0, this.triggerStart);
            const after = textarea.value.substring(textarea.selectionStart);

            let insertion;
            if (this.triggerType === 'fragment') {
                insertion = '{{>' + item.value + '}}';
            } else {
                insertion = '{{' + item.value + '}}';
            }

            textarea.value = before + insertion + after;
            const newPos = before.length + insertion.length;
            textarea.setSelectionRange(newPos, newPos);

            // Trigger Livewire update
            textarea.dispatchEvent(new Event('input', { bubbles: true }));

            this.dismiss();
        },

        dismiss() {
            this.showDropdown = false;
            this.items = [];
            this.filteredItems = [];
            this.selectedIndex = 0;
            this.triggerType = null;
            this.triggerStart = null;
            this.query = '';
        },

        positionDropdown(textarea) {
            const dropdown = this.$refs.autocompleteDropdown;
            if (!dropdown || !textarea) return;

            // Approximate position based on cursor
            const lineHeight = 20;
            const text = textarea.value.substring(0, textarea.selectionStart);
            const lines = text.split('\n');
            const currentLine = lines.length;
            const charOffset = lines[lines.length - 1].length;

            const top = (currentLine * lineHeight) - textarea.scrollTop + 8;
            const left = Math.min(charOffset * 7.8, textarea.clientWidth - 200);

            dropdown.style.top = top + 'px';
            dropdown.style.left = left + 'px';
        }
    };
}
