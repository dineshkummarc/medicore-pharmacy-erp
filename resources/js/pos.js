/**
 * Point-of-sale screen.
 *
 * Totals are calculated in integer cents with the same rounding as
 * SaleService so the preview matches the invoice. The server re-validates
 * and recalculates everything; this is only a preview.
 */
const STORAGE_KEY = 'pos.cart.v1';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } : {}),
            ...(options.headers || {}),
        },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const error = new Error(first || data.message || 'Something went wrong. Please try again.');
        error.status = response.status;
        throw error;
    }

    return data;
}

const toCents = (v) => Math.round(Number(v || 0) * 100);
const pct = (cents, percent) => Math.round((cents * Number(percent || 0)) / 100);

export default function pos() {
    return {
        config: {},
        methods: [],
        routes: {},

        query: '',
        results: [],
        highlighted: 0,
        searching: false,
        searchTimer: null,

        cart: [],
        customer: null,
        customerQuery: '',
        customerResults: [],
        customerOpen: false,
        newCustomer: { name: '', phone: '' },
        showNewCustomer: false,

        paymentMethodId: null,
        amountReceived: '',
        notes: '',
        submitting: false,
        completed: null,

        init() {
            const el = this.$el;
            this.config = JSON.parse(el.dataset.config);
            this.methods = JSON.parse(el.dataset.methods);
            this.routes = JSON.parse(el.dataset.routes);
            this.paymentMethodId = this.methods[0]?.id ?? null;
            this.restore();

            this.$watch('cart', () => this.persist(), { deep: true });
            this.$watch('customer', () => this.persist());

            this.$nextTick(() => this.$refs.search?.focus());
        },

        /* ---------- search ---------- */

        onSearchInput() {
            clearTimeout(this.searchTimer);
            if (this.query.trim().length < 1) {
                this.results = [];
                return;
            }
            this.searchTimer = setTimeout(() => this.search(), 180);
        },

        async search() {
            const term = this.query.trim();
            if (!term) return [];
            this.searching = true;
            try {
                this.results = await request(`${this.routes.search}?q=${encodeURIComponent(term)}`);
                this.highlighted = 0;
            } catch (e) {
                this.toast(e.message, 'error');
            } finally {
                this.searching = false;
            }
            return this.results;
        },

        /**
         * Enter in the search box. Barcode scanners type the code and press
         * Enter quickly, so search immediately and add an exact match.
         */
        async onSearchEnter() {
            clearTimeout(this.searchTimer);
            const term = this.query.trim();
            if (!term) return;

            const results = await this.search();
            const exact = results.find((r) => r.barcode === term || (r.sku || '').toUpperCase() === term.toUpperCase());
            const pick = exact || results[this.highlighted] || (results.length === 1 ? results[0] : null);

            if (pick) this.add(pick);
        },

        move(step) {
            if (!this.results.length) return;
            this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
        },

        add(item) {
            if (item.stock <= 0) {
                this.toast(`${item.name} has no sellable stock (expired or sold out).`, 'error');
                return;
            }

            const line = this.cart.find((l) => l.id === item.id);
            if (line) {
                if (line.qty >= line.stock) {
                    this.toast(`Only ${line.stock} ${item.unit}(s) of ${item.name} available.`, 'error');
                } else {
                    line.qty++;
                }
            } else {
                this.cart.push({
                    id: item.id,
                    name: item.name,
                    sku: item.sku,
                    unit: item.unit,
                    price: item.price,
                    tax_rate: item.tax_rate,
                    stock: item.stock,
                    rx: item.rx,
                    next_expiry: item.next_expiry,
                    qty: 1,
                    discount: 0,
                });
            }

            this.query = '';
            this.results = [];
            this.$nextTick(() => this.$refs.search?.focus());
        },

        remove(index) {
            this.cart.splice(index, 1);
        },

        clampQty(line) {
            let qty = parseInt(line.qty, 10);
            if (!Number.isFinite(qty) || qty < 1) qty = 1;
            if (qty > line.stock) {
                qty = line.stock;
                this.toast(`Only ${line.stock} available for ${line.name}.`, 'error');
            }
            line.qty = qty;
        },

        clampDiscount(line) {
            let d = Number(line.discount);
            if (!Number.isFinite(d) || d < 0) d = 0;
            if (d > this.config.maxDiscount) d = this.config.maxDiscount;
            line.discount = Math.round(d * 100) / 100;
        },

        expiresSoon(line) {
            if (!line.next_expiry) return false;
            const days = (new Date(line.next_expiry) - new Date()) / 86400000;
            return days <= this.config.expiryAlertDays;
        },

        clearCart() {
            this.cart = [];
            this.customer = null;
            this.amountReceived = '';
            this.notes = '';
        },

        /* ---------- totals (cents) ---------- */

        lineCents(line) {
            const gross = toCents(line.price) * line.qty;
            const discount = pct(gross, line.discount);
            const tax = this.config.taxEnabled ? pct(gross - discount, line.tax_rate) : 0;
            return { gross, discount, tax, total: gross - discount + tax };
        },

        get totals() {
            return this.cart.reduce(
                (acc, line) => {
                    const c = this.lineCents(line);
                    acc.subtotal += c.gross;
                    acc.discount += c.discount;
                    acc.tax += c.tax;
                    acc.total += c.total;
                    return acc;
                },
                { subtotal: 0, discount: 0, tax: 0, total: 0 },
            );
        },

        get receivedCents() {
            return this.amountReceived === '' ? this.totals.total : toCents(this.amountReceived);
        },

        get changeCents() {
            return Math.max(0, this.receivedCents - this.totals.total);
        },

        get dueCents() {
            return Math.max(0, this.totals.total - this.receivedCents);
        },

        get itemCount() {
            return this.cart.reduce((n, l) => n + Number(l.qty || 0), 0);
        },

        fmt(cents) {
            const value = (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return `${this.config.currency}${value}`;
        },

        get canSubmit() {
            if (!this.cart.length || this.submitting || !this.paymentMethodId) return false;
            if (this.dueCents > 0 && (!this.customer || !this.config.canCredit)) return false;
            return true;
        },

        get blockReason() {
            if (!this.cart.length) return 'Add medicines to the cart';
            if (this.dueCents > 0 && !this.config.canCredit) return 'Collect the full amount — you cannot sell on credit';
            if (this.dueCents > 0 && !this.customer) return 'Select a customer to leave a balance due';
            return '';
        },

        /* ---------- customers ---------- */

        async searchCustomers() {
            const term = this.customerQuery.trim();
            if (term.length < 2) {
                this.customerResults = [];
                return;
            }
            try {
                this.customerResults = await request(`${this.routes.customers}?q=${encodeURIComponent(term)}`);
                this.customerOpen = true;
            } catch (e) {
                this.toast(e.message, 'error');
            }
        },

        pickCustomer(c) {
            this.customer = c;
            this.customerQuery = '';
            this.customerResults = [];
            this.customerOpen = false;
        },

        async createCustomer() {
            try {
                const c = await request(this.routes.quickCustomer, { method: 'POST', body: JSON.stringify(this.newCustomer) });
                this.pickCustomer(c);
                this.showNewCustomer = false;
                this.newCustomer = { name: '', phone: '' };
                this.toast('Customer added.');
            } catch (e) {
                this.toast(e.message, 'error');
            }
        },

        /* ---------- checkout ---------- */

        async checkout() {
            if (!this.canSubmit) {
                if (this.blockReason) this.toast(this.blockReason, 'error');
                return;
            }

            this.submitting = true;
            try {
                const sale = await request(this.routes.store, {
                    method: 'POST',
                    body: JSON.stringify({
                        customer_id: this.customer?.id ?? null,
                        payment_method_id: this.paymentMethodId,
                        amount_received: this.amountReceived === '' ? null : this.amountReceived,
                        notes: this.notes || null,
                        items: this.cart.map((l) => ({ medicine_id: l.id, quantity: l.qty, discount_percent: l.discount || 0 })),
                    }),
                });

                this.completed = { ...sale, change: this.changeCents };
                this.clearCart();
            } catch (e) {
                this.toast(e.message, 'error');
                if (e.status === 419) this.toast('Your session expired. Reload the page and sign in again.', 'error');
            } finally {
                this.submitting = false;
            }
        },

        newSale() {
            this.completed = null;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        shortcuts(e) {
            if (e.key === 'F2' || (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName))) {
                e.preventDefault();
                this.$refs.search?.focus();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.checkout();
            }
        },

        /* ---------- persistence (per browser, convenience only) ---------- */

        persist() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ cart: this.cart, customer: this.customer }));
            } catch (_) { /* storage unavailable */ }
        },

        restore() {
            try {
                const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                if (saved?.cart?.length) {
                    this.cart = saved.cart;
                    this.customer = saved.customer || null;
                }
            } catch (_) { /* ignore */ }
        },

        toast(message, type = 'success') {
            Alpine.store('toasts').push(message, type);
        },
    };
}
