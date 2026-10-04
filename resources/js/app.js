import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import {
    Chart, LineController, BarController, LineElement, BarElement,
    PointElement, CategoryScale, LinearScale, Filler, Tooltip,
} from 'chart.js';

import pos from './pos';
import purchaseForm from './purchase-form';
import { lineChart, barChart } from './charts';

Chart.register(
    LineController, BarController, LineElement, BarElement,
    PointElement, CategoryScale, LinearScale, Filler, Tooltip,
);
Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--font-sans') || 'system-ui';
Chart.defaults.color = '#64748b';

window.Alpine = Alpine;
Alpine.plugin(focus);

Alpine.data('pos', pos);
Alpine.data('purchaseForm', purchaseForm);
Alpine.data('lineChart', lineChart);
Alpine.data('barChart', barChart);

/**
 * Global toast store: Alpine.store('toasts').push('Saved', 'success')
 */
Alpine.store('toasts', {
    items: [],
    push(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4000);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});

/**
 * Select-all checkbox helper for bulk actions.
 */
Alpine.data('bulkSelect', () => ({
    selected: [],
    toggleAll(event, ids) {
        this.selected = event.target.checked ? [...ids] : [];
    },
}));

Alpine.start();
