import { Controller } from '@hotwired/stimulus';

/*
 * Révélation discrète d'une section à l'entrée dans le viewport.
 *
 * Le contenu est visible tant que le contrôleur n'a pas « armé »
 * l'animation : sans JavaScript, ou sans IntersectionObserver, la page
 * reste entièrement lisible.
 *
 * <section class="af-reveal" data-controller="reveal">…</section>
 */
export default class extends Controller {
    connect() {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        // L'animation est de toute façon neutralisée en CSS, mais autant
        // ne pas observer inutilement.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.element.classList.add('is-armed');

        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        this.element.classList.add('is-in');
                        this.disconnect();
                    }
                });
            },
            { threshold: 0.12 }
        );

        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
        this.observer = null;
    }
}
