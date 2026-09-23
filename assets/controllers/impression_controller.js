import { Controller } from '@hotwired/stimulus';

/*
 * Déplie les accordéons (`<details>`) le temps de l'impression : une fiche
 * imprimée doit contenir le programme complet, quel que soit ce qui est
 * ouvert à l'écran.
 */
export default class extends Controller {
    connect() {
        this.avantImpression = () => this.deplier();
        this.apresImpression = () => this.replier();

        window.addEventListener('beforeprint', this.avantImpression);
        window.addEventListener('afterprint', this.apresImpression);
    }

    disconnect() {
        window.removeEventListener('beforeprint', this.avantImpression);
        window.removeEventListener('afterprint', this.apresImpression);
    }

    deplier() {
        this.replies = this.details().filter((details) => !details.open);
        this.replies.forEach((details) => (details.open = true));
    }

    // Seuls les accordéons ouverts pour l'impression sont refermés : ceux que
    // le visiteur avait ouverts lui-même restent ouverts.
    replier() {
        (this.replies ?? []).forEach((details) => (details.open = false));
        this.replies = [];
    }

    details() {
        return Array.from(this.element.querySelectorAll('details'));
    }
}
