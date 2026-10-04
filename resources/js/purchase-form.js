/**
 * Purchase entry form: medicine lookup and live line totals.
 * Lines are submitted as a regular form (items[i][field]) and validated
 * server-side by StorePurchaseRequest.
 */
const toCents = (v) => Math.round(Number(v || 0) * 100);

export default function purchaseForm() {
    return {
        currency: '',
        lookupUrl: '',
        items: [],
        query: '',
        results: [],
        timer: null,
        paid: '',

        init() {
            this.currency = this.$el.dataset.currency || '';
            this.lookupUrl = this.$el.dataset.lookup;
            const old = JSON.parse(this.$el.dataset.old || '[]');
            this.items = Array.isArray(old) ? old.map((i) => ({ ...i, _name: i._name || '' })) : [];
            this.paid = this.$el.dataset.paid || '';
        },

        onInput() {
            clearTimeout(this.timer);
            if (this.query.trim().length < 2) {
                this.results = [];
                return;
            }
            this.timer = setTimeout(async () => {
                const res = await fetch(`${this.lookupUrl}?q=${encodeURIComponent(this.query.trim())}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                this.results = res.ok ? await res.json() : [];
            }, 200);
        },

        add(m) {
            this.items.push({
                medicine_id: m.id,
                _name: m.name,
                _unit: m.unit,
                batch_number: '',
                expiry_date: '',
                quantity: 1,
                unit_cost: m.purchase_price,
                selling_price: m.selling_price,
                discount: 0,
                tax_rate: 0,
            });
            this.query = '';
            this.results = [];
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-batch-input]');
                inputs[inputs.length - 1]?.focus();
            });
        },

        remove(i) {
            this.items.splice(i, 1);
        },

        line(item) {
            const gross = toCents(item.unit_cost) * (parseInt(item.quantity, 10) || 0);
            const discount = Math.min(toCents(item.discount), gross);
            const tax = Math.round(((gross - discount) * Number(item.tax_rate || 0)) / 100);
            return { gross, discount, tax, total: gross - discount + tax };
        },

        get totals() {
            return this.items.reduce(
                (t, item) => {
                    const l = this.line(item);
                    t.subtotal += l.gross;
                    t.discount += l.discount;
                    t.tax += l.tax;
                    t.total += l.total;
                    return t;
                },
                { subtotal: 0, discount: 0, tax: 0, total: 0 },
            );
        },

        get due() {
            return Math.max(0, this.totals.total - toCents(this.paid));
        },

        fmt(cents) {
            return `${this.currency}${(cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
    };
}
