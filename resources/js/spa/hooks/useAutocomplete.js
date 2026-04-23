import { useState, useCallback, useRef, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import client from '../api/client.js';

async function fetchVariables() {
    const { data } = await client.get('/internal/variables', { baseURL: '' });
    return data;
}

async function fetchFragments() {
    const { data } = await client.get('/internal/fragments', { baseURL: '' });
    return data;
}

export default function useAutocomplete(textareaRef) {
    const [isOpen, setIsOpen] = useState(false);
    const [triggerType, setTriggerType] = useState(null); // 'variable' | 'fragment'
    const [triggerStart, setTriggerStart] = useState(0);
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [position, setPosition] = useState({ top: 0, left: 0 });
    const skipNextInput = useRef(false);

    const { data: variablesData } = useQuery({
        queryKey: ['autocomplete', 'variables'],
        queryFn: fetchVariables,
        staleTime: 60000,
    });

    const { data: fragmentsData } = useQuery({
        queryKey: ['autocomplete', 'fragments'],
        queryFn: fetchFragments,
        staleTime: 60000,
    });

    const variables = variablesData ?? [];
    const fragments = fragmentsData ?? [];

    const getFilteredItems = useCallback(() => {
        const items = triggerType === 'fragment'
            ? fragments.map(f => ({ value: f.slug, label: f.name, type: 'fragment' }))
            : variables.map(v => ({ value: v, label: v, type: 'variable' }));

        if (!query) return items;
        const q = query.toLowerCase();
        return items.filter(item =>
            item.value.toLowerCase().includes(q) || item.label.toLowerCase().includes(q)
        );
    }, [triggerType, variables, fragments, query]);

    const filteredItems = getFilteredItems();

    const calculatePosition = useCallback((textarea, cursorPos) => {
        const text = textarea.value.substring(0, cursorPos);
        const lines = text.split('\n');
        const lineNumber = lines.length - 1;
        const lineHeight = 20;
        const charWidth = 8.4;
        const lastLineLength = lines[lines.length - 1].length;

        const top = (lineNumber + 1) * lineHeight - textarea.scrollTop + 8;
        const left = Math.min(lastLineLength * charWidth, textarea.clientWidth - 200) + 16;

        return { top: Math.max(top, 0), left: Math.max(left, 16) };
    }, []);

    const handleInput = useCallback(() => {
        if (skipNextInput.current) {
            skipNextInput.current = false;
            return;
        }

        const textarea = textareaRef.current;
        if (!textarea) return;

        const cursorPos = textarea.selectionStart;
        const textBefore = textarea.value.substring(0, cursorPos);

        // Check for {{> (fragment trigger)
        const fragmentMatch = textBefore.match(/\{\{>([a-zA-Z0-9_-]*)$/);
        if (fragmentMatch) {
            setTriggerType('fragment');
            setTriggerStart(cursorPos - fragmentMatch[0].length);
            setQuery(fragmentMatch[1]);
            setSelectedIndex(0);
            setPosition(calculatePosition(textarea, cursorPos));
            setIsOpen(true);
            return;
        }

        // Check for {{ (variable trigger)
        const varMatch = textBefore.match(/\{\{([a-zA-Z_][a-zA-Z0-9_]*)?$/);
        if (varMatch && !textBefore.endsWith('}}')) {
            setTriggerType('variable');
            setTriggerStart(cursorPos - varMatch[0].length);
            setQuery(varMatch[1] || '');
            setSelectedIndex(0);
            setPosition(calculatePosition(textarea, cursorPos));
            setIsOpen(true);
            return;
        }

        setIsOpen(false);
    }, [textareaRef, calculatePosition]);

    const insertItem = useCallback((item) => {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const before = textarea.value.substring(0, triggerStart);
        const after = textarea.value.substring(textarea.selectionStart);

        const insertion = item.type === 'fragment'
            ? `{{>${item.value}}}`
            : `{{${item.value}}}`;

        const newValue = before + insertion + after;
        const newCursor = before.length + insertion.length;

        // Update the textarea value via native setter to trigger React's onChange
        const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
            window.HTMLTextAreaElement.prototype, 'value'
        ).set;
        nativeInputValueSetter.call(textarea, newValue);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        skipNextInput.current = true;
        setIsOpen(false);

        requestAnimationFrame(() => {
            textarea.selectionStart = newCursor;
            textarea.selectionEnd = newCursor;
            textarea.focus();
        });
    }, [textareaRef, triggerStart]);

    const handleKeyDown = useCallback((e) => {
        if (!isOpen || filteredItems.length === 0) return false;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex(prev => (prev + 1) % filteredItems.length);
            return true;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex(prev => (prev - 1 + filteredItems.length) % filteredItems.length);
            return true;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            insertItem(filteredItems[selectedIndex]);
            return true;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            setIsOpen(false);
            return true;
        }

        return false;
    }, [isOpen, filteredItems, selectedIndex, insertItem]);

    const dismiss = useCallback(() => {
        setIsOpen(false);
    }, []);

    return {
        isOpen,
        filteredItems,
        selectedIndex,
        position,
        triggerType,
        handleInput,
        handleKeyDown,
        insertItem,
        dismiss,
    };
}
