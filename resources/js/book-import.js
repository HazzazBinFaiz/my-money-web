/**
 * Alpine component behind the "import from other book" widget.
 *
 * Lists the contacts or categories that exist in the user's other books but
 * not in the active one, so importing can never create a duplicate name.
 */
export default function bookImport({ listUrl }) {
    return {
        open: false,
        loading: false,
        error: null,
        items: [],
        selected: [],
        search: '',

        openModal() {
            this.open = true;
            this.error = null;
            this.selected = [];
            this.search = '';
            this.load();
        },

        async load() {
            this.loading = true;

            try {
                const response = await fetch(listUrl, { headers: { Accept: 'application/json' } });
                const payload = await response.json();

                this.items = payload.data ?? [];
            } catch (error) {
                this.error = 'Could not load the other books.';
            } finally {
                this.loading = false;
            }
        },

        visible() {
            const needle = this.search.trim().toLowerCase();

            if (needle === '') {
                return this.items;
            }

            return this.items.filter((item) => item.name.toLowerCase().includes(needle));
        },

        toggle(id) {
            const index = this.selected.indexOf(id);

            if (index === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(index, 1);
            }
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        allVisibleSelected() {
            const visible = this.visible();

            return visible.length > 0 && visible.every((item) => this.isSelected(item.id));
        },

        toggleAll() {
            const visible = this.visible();

            if (this.allVisibleSelected()) {
                visible.forEach((item) => this.toggle(item.id));

                return;
            }

            visible.forEach((item) => {
                if (! this.isSelected(item.id)) {
                    this.selected.push(item.id);
                }
            });
        },
    };
}
