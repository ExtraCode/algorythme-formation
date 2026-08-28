import { Controller } from '@hotwired/stimulus';
import { signalPath, SIGNAL_VIEWBOX } from '../js/signal.js';

/*
 * Dessine la signature « l'onde qui se régularise ».
 *
 * <div data-controller="signal"
 *      data-signal-disorder-value="0.5"
 *      data-signal-seed-value="31"
 *      data-signal-height-value="28"></div>
 */
export default class extends Controller {
    static values = {
        disorder: { type: Number, default: 1 },
        seed: { type: Number, default: 7 },
        height: { type: Number, default: 32 },
        color: { type: String, default: 'var(--color-accent)' },
    };

    connect() {
        this.svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        this.svg.setAttribute('viewBox', SIGNAL_VIEWBOX);
        this.svg.setAttribute('preserveAspectRatio', 'none');
        this.svg.setAttribute('aria-hidden', 'true');
        this.svg.classList.add('af-signal');

        this.path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        this.path.setAttribute('fill', 'none');
        this.path.setAttribute('stroke-width', '1.5');
        this.path.setAttribute('stroke-linejoin', 'round');
        this.path.setAttribute('stroke-linecap', 'round');
        this.path.setAttribute('vector-effect', 'non-scaling-stroke');

        this.svg.appendChild(this.path);
        this.element.replaceChildren(this.svg);

        this.draw();
    }

    // Redessine dès qu'une valeur change (le quiz fait varier `disorder`).
    disorderValueChanged() {
        this.draw();
    }

    draw() {
        if (!this.path) {
            return;
        }

        this.svg.style.height = `${this.heightValue}px`;
        this.path.setAttribute('stroke', this.colorValue);
        this.path.setAttribute('d', signalPath(this.disorderValue, this.seedValue));
    }
}
