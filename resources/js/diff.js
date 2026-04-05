/**
 * Alpine.js diff viewer component using the `diff` npm package.
 * Provides word-level and character-level diff with unified and side-by-side HTML output.
 */
import { diffWords, diffChars } from 'diff';

export default function diffViewer() {
    return {
        showDiff: false,
        diffMode: 'words', // 'words' or 'chars'
        diffSelection: [],
        oldText: '',
        newText: '',
        oldLabel: '',
        newLabel: '',
        changes: [],

        openDiff(oldText, newText, oldLabel, newLabel) {
            this.oldText = oldText || '';
            this.newText = newText || '';
            this.oldLabel = oldLabel || 'Old';
            this.newLabel = newLabel || 'New';
            this.computeDiff();
            this.showDiff = true;
        },

        closeDiff() {
            this.showDiff = false;
        },

        toggleMode(mode) {
            this.diffMode = mode;
            this.computeDiff();
        },

        computeDiff() {
            if (this.diffMode === 'chars') {
                this.changes = diffChars(this.oldText, this.newText);
            } else {
                this.changes = diffWords(this.oldText, this.newText);
            }
        },

        get unifiedHtml() {
            return this.changes.map(part => {
                const escaped = this.escapeHtml(part.value);
                if (part.added) {
                    return '<span class="bg-green-200 dark:bg-green-900/40 text-green-900 dark:text-green-300">' + escaped + '</span>';
                }
                if (part.removed) {
                    return '<span class="bg-red-200 dark:bg-red-900/40 text-red-900 dark:text-red-300 line-through">' + escaped + '</span>';
                }
                return escaped;
            }).join('');
        },

        get stats() {
            let added = 0, removed = 0;
            this.changes.forEach(part => {
                if (part.added) added += part.value.length;
                if (part.removed) removed += part.value.length;
            });
            return { added, removed };
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };
}
