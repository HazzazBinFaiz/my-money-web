/**
 * Alpine component behind the report drill down.
 *
 * The detail is a server rendered fragment, so the modal only has to fetch it
 * and hold the open state.
 */
export default function reportDetail() {
    return {
        showing: false,
        loading: false,
        error: null,
        body: '',

        async open(url) {
            this.showing = true;
            this.loading = true;
            this.error = null;
            this.body = '';

            try {
                const response = await fetch(url, { headers: { Accept: 'text/html' } });

                if (! response.ok) {
                    throw new Error('failed');
                }

                this.body = await response.text();
            } catch (error) {
                this.error = 'Could not load that breakdown.';
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.showing = false;
            this.body = '';
        },
    };
}
