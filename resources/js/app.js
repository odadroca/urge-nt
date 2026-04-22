import Alpine from 'alpinejs';

// Toast notification component (used by layouts/app.blade.php)
Alpine.data('toasts', () => ({
    items: [],
    add(detail) {
        const id = Date.now();
        this.items.push({
            id,
            message: detail.message || detail[0]?.message || '',
            type: detail.type || detail[0]?.type || 'success',
            visible: true,
        });
        setTimeout(() => {
            const t = this.items.find(i => i.id === id);
            if (t) t.visible = false;
            setTimeout(() => { this.items = this.items.filter(i => i.id !== id); }, 200);
        }, 3000);
    },
}));

window.Alpine = Alpine;
Alpine.start();
