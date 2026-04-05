/**
 * Alpine.js visual composer component for block-based prompt editing.
 * Parses template content into typed blocks that can be reordered via drag-and-drop.
 */
export default function composer() {
    return {
        blocks: [],
        _sortableInstance: null,

        parseContent(content) {
            this.blocks = [];
            if (!content) return;

            // Split on {{variable}} and {{>slug}} patterns
            const regex = /(\{\{>\s*[a-z0-9_-]+\s*\}\}|\{\{\s*[a-z_][a-z0-9_]*\s*\}\})/gi;
            const parts = content.split(regex);

            parts.forEach((part) => {
                if (!part) return;

                const includeMatch = part.match(/^\{\{>\s*([a-z0-9_-]+)\s*\}\}$/i);
                if (includeMatch) {
                    this.blocks.push({
                        id: this.uid(),
                        type: 'include',
                        value: includeMatch[1],
                    });
                    return;
                }

                const varMatch = part.match(/^\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}$/i);
                if (varMatch) {
                    this.blocks.push({
                        id: this.uid(),
                        type: 'variable',
                        value: varMatch[1],
                    });
                    return;
                }

                this.blocks.push({
                    id: this.uid(),
                    type: 'text',
                    value: part,
                });
            });
        },

        serialize() {
            return this.blocks.map(block => {
                if (block.type === 'variable') return '{{' + block.value + '}}';
                if (block.type === 'include') return '{{>' + block.value + '}}';
                return block.value;
            }).join('');
        },

        addTextBlock() {
            this.blocks.push({ id: this.uid(), type: 'text', value: '' });
        },

        addVariableBlock(name = '') {
            const varName = name || prompt('Variable name:');
            if (varName) {
                this.blocks.push({ id: this.uid(), type: 'variable', value: varName.trim() });
            }
        },

        addIncludeBlock(slug = '') {
            const fragSlug = slug || prompt('Fragment slug:');
            if (fragSlug) {
                this.blocks.push({ id: this.uid(), type: 'include', value: fragSlug.trim() });
            }
        },

        removeBlock(index) {
            this.blocks.splice(index, 1);
        },

        uid() {
            return 'b_' + Math.random().toString(36).substring(2, 9);
        },

        initSortable(el) {
            if (this._sortableInstance) {
                this._sortableInstance.destroy();
            }
            if (typeof Sortable !== 'undefined') {
                this._sortableInstance = Sortable.create(el, {
                    handle: '.composer-handle',
                    animation: 150,
                    onEnd: (evt) => {
                        const moved = this.blocks.splice(evt.oldIndex, 1)[0];
                        this.blocks.splice(evt.newIndex, 0, moved);
                    },
                });
            }
        },
    };
}
