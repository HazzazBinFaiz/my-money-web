import { sankeyCircular } from 'd3-sankey-circular';

const SVG = 'http://www.w3.org/2000/svg';

/**
 * The Money Flow Sankey.
 *
 * The layout comes from d3-sankey-circular, which does two things the hand
 * rolled version could not: it orders nodes to reduce crossings, and it routes
 * an account to account transfer as a proper circular link instead of a loop
 * bent by hand.
 *
 * Everything else stays ours — colours come from the CSS variables so the chart
 * follows the theme, and the table under the diagram carries the same numbers
 * for anyone who cannot use it.
 */
export default function moneyFlow(payload) {
    return {
        empty: ! payload.links.length,

        init() {
            if (this.empty) {
                return;
            }

            this.draw();

            // The viewBox scales with the card, but a resize can change how many
            // nodes fit comfortably, so redraw on a settled resize.
            let pending;

            this.resize = () => {
                clearTimeout(pending);
                pending = setTimeout(() => this.draw(), 150);
            };

            window.addEventListener('resize', this.resize);
        },

        destroy() {
            window.removeEventListener('resize', this.resize);
        },

        draw() {
            const width = 1000;
            const rows = Math.max(
                payload.nodes.filter((node) => node.group === 'income').length,
                payload.nodes.filter((node) => node.group === 'accounts').length,
                payload.nodes.filter((node) => node.group === 'expense').length,
            );

            // Room for every node to keep a legible label.
            const height = Math.min(920, Math.max(380, rows * 46));
            const margin = { top: 42, right: 190, bottom: 42, left: 170 };

            const layout = sankeyCircular()
                .nodeId((node) => node.id)
                // Columns are pinned rather than derived: a transfer makes the
                // receiving account one hop deeper, and the layout would happily
                // give it a fourth column of its own.
                .nodeAlign((node) => ({ income: 0, accounts: 1, expense: 2 })[node.group] ?? 1)
                .nodeWidth(14)
                .nodePadding(20)
                .circularLinkGap(4)
                .extent([
                    [margin.left, margin.top],
                    [width - margin.right, height - margin.bottom],
                ]);

            // The layout mutates what it is given, so it gets a copy each time.
            const graph = layout({
                nodes: payload.nodes.map((node) => ({ ...node })),
                links: payload.links.map((link) => ({ ...link })),
            });

            // The layout packs from the top and shifts nodes about to make room
            // for links, so the frame is fitted to where the nodes actually
            // landed. Trusting the extent leaves dead space under the diagram and
            // can still clip the top label.
            const pad = 24;
            const top = Math.min(...graph.nodes.map((node) => node.y0));
            const bottom = Math.max(...graph.nodes.map((node) => node.y1));

            const svg = this.$refs.canvas;
            svg.setAttribute('viewBox', `0 ${top - pad} ${width} ${bottom - top + pad * 2}`);
            svg.replaceChildren();

            this.packEdges(graph);

            const ribbons = document.createElementNS(SVG, 'g');
            ribbons.setAttribute('class', 'viz-ribbons');
            svg.append(ribbons);

            // Loops last: they are thin and cross the busiest part of the picture.
            const ordered = [...graph.links].sort((a, b) => this.loops(a) - this.loops(b));

            for (const link of ordered) {
                ribbons.append(this.loops(link) ? this.transfer(link) : this.straight(link));
            }

            for (const node of graph.nodes) {
                svg.append(this.node(node));
            }
        },

        /**
         * A transfer between two accounts sits in one column. d3 only calls a
         * link circular when it closes a cycle, so this is decided on position.
         */
        loops(link) {
            return link.source.column === link.target.column ? 1 : 0;
        },

        /**
         * Where each ribbon meets its node.
         *
         * The layout packs a node's links in its own order, which knows nothing
         * about transfers. Ours leave last, flush with the bottom of the sending
         * account, and arrive first, at the top of the receiving one.
         */
        packEdges(graph) {
            const leaving = new Map();
            const arriving = new Map();

            for (const link of graph.links) {
                if (! leaving.has(link.source)) {
                    leaving.set(link.source, []);
                }

                if (! arriving.has(link.target)) {
                    arriving.set(link.target, []);
                }

                leaving.get(link.source).push(link);
                arriving.get(link.target).push(link);
            }

            const width = (links) => links.reduce((total, link) => total + link.width, 0);

            for (const [node, links] of leaving) {
                const transfers = links.filter((link) => this.loops(link));
                const rest = links.filter((link) => ! this.loops(link));

                let y = node.y0;

                for (const link of rest) {
                    link.y0 = y + link.width / 2;
                    y += link.width;
                }

                y = Math.max(y, node.y1 - width(transfers));

                for (const link of transfers) {
                    link.y0 = y + link.width / 2;
                    y += link.width;
                }
            }

            for (const [node, links] of arriving) {
                const transfers = links.filter((link) => this.loops(link));
                const rest = links.filter((link) => ! this.loops(link));

                let y = node.y0;

                for (const link of [...transfers, ...rest]) {
                    link.y1 = y + link.width / 2;
                    y += link.width;
                }
            }
        },

        /**
         * A straight link: one filled band, flush against both nodes.
         */
        straight(link) {
            const path = document.createElementNS(SVG, 'path');

            path.setAttribute('class', `viz-band viz-band--${link.side}`);
            path.setAttribute('d', this.band(link));
            path.setAttribute('tabindex', '0');
            path.append(this.title(`${link.sourceName} → ${link.targetName}: ${link.amount}`));

            return path;
        },

        /**
         * A same column transfer: a funnel out of the sending account, an S, and
         * a funnel into the receiving one.
         *
         * The two funnels are filled, so each end keeps its full width against
         * its node and the amount still reads at the joint. The S between them is
         * a stroked line of the neck's width — a thin filled band doubling back
         * on itself crosses its own boundaries and renders as lens shaped blobs.
         */
        transfer(link) {
            const group = document.createElementNS(SVG, 'g');
            group.setAttribute('class', `viz-flow viz-flow--${link.side}`);
            group.setAttribute('tabindex', '0');

            const half = Math.max(0.5, link.width / 2);
            const neck = Math.max(1.25, Math.min(half, 4.5));
            const taper = 22;

            const x0 = link.source.x1;
            const x1 = link.target.x0;
            const nx0 = x0 + taper;
            const nx1 = x1 - taper;
            const y0 = link.y0;
            const y1 = link.y1;

            const funnelOut = [
                `M${x0},${y0 - half}`,
                `C${x0 + taper * 0.55},${y0 - half} ${nx0 - taper * 0.45},${y0 - neck} ${nx0},${y0 - neck}`,
                `L${nx0},${y0 + neck}`,
                `C${nx0 - taper * 0.45},${y0 + neck} ${x0 + taper * 0.55},${y0 + half} ${x0},${y0 + half}`,
                'Z',
            ].join(' ');

            const funnelIn = [
                `M${x1},${y1 - half}`,
                `C${x1 - taper * 0.55},${y1 - half} ${nx1 + taper * 0.45},${y1 - neck} ${nx1},${y1 - neck}`,
                `L${nx1},${y1 + neck}`,
                `C${nx1 + taper * 0.45},${y1 + neck} ${x1 - taper * 0.55},${y1 + half} ${x1},${y1 + half}`,
                'Z',
            ].join(' ');

            // Tangents leave and arrive horizontally, so the line reads as an S
            // rather than a diagonal cut across the column.
            const reach = Math.max(22, Math.abs(y1 - y0) * 0.3);

            const neckPath = [
                `M${nx0},${y0}`,
                `C${nx0 + reach},${y0} ${nx1 - reach},${y1} ${nx1},${y1}`,
            ].join(' ');

            for (const d of [funnelOut, funnelIn]) {
                const wedge = document.createElementNS(SVG, 'path');
                wedge.setAttribute('class', 'viz-wedge');
                wedge.setAttribute('d', d);
                group.append(wedge);
            }

            const line = document.createElementNS(SVG, 'path');
            line.setAttribute('class', 'viz-neck');
            line.setAttribute('d', neckPath);
            line.setAttribute('stroke-width', neck * 2);
            group.append(line);

            group.append(this.title(`${link.sourceName} → ${link.targetName}: ${link.amount}`));

            return group;
        },

        /**
         * A constant width band from the source's right edge to the target's
         * left. link.y0 and link.y1 are the centres of each end.
         */
        band(link) {
            const half = Math.max(0.5, link.width / 2);
            const x0 = link.source.x1;
            const x1 = link.target.x0;
            const mid = (x0 + x1) / 2;

            const top0 = link.y0 - half;
            const top1 = link.y1 - half;
            const bottom0 = link.y0 + half;
            const bottom1 = link.y1 + half;

            return [
                `M${x0},${top0}`,
                `C${mid},${top0} ${mid},${top1} ${x1},${top1}`,
                `L${x1},${bottom1}`,
                `C${mid},${bottom1} ${mid},${bottom0} ${x0},${bottom0}`,
                'Z',
            ].join(' ');
        },

        node(node) {
            const group = document.createElementNS(SVG, 'g');

            const rect = document.createElementNS(SVG, 'rect');
            rect.setAttribute('class', `viz-node viz-node--${node.group}`);
            rect.setAttribute('x', node.x0);
            rect.setAttribute('y', node.y0);
            rect.setAttribute('width', node.x1 - node.x0);
            rect.setAttribute('height', Math.max(3, node.y1 - node.y0));
            rect.setAttribute('rx', '3');
            rect.append(this.title(`${node.name}: ${node.amount}`));

            const label = document.createElementNS(SVG, 'text');
            const onLeft = node.group === 'income';

            label.setAttribute('class', 'viz-label');
            label.setAttribute('x', onLeft ? node.x0 - 8 : node.x1 + 8);
            label.setAttribute('y', (node.y0 + node.y1) / 2);
            label.setAttribute('text-anchor', onLeft ? 'end' : 'start');
            label.setAttribute('dominant-baseline', 'middle');
            label.textContent = node.name;

            const value = document.createElementNS(SVG, 'tspan');
            value.setAttribute('class', 'viz-label-value');
            value.setAttribute('dx', '6');
            value.textContent = node.amount;
            label.append(value);

            group.append(rect, label);

            return group;
        },

        title(text) {
            const title = document.createElementNS(SVG, 'title');
            title.textContent = text;

            return title;
        },
    };
}
