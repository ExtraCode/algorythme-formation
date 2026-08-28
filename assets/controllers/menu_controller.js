import { Controller } from '@hotwired/stimulus';

/*
 * Menu de navigation mobile (bouton burger de l'en-tête).
 */
export default class extends Controller {
    static targets = ['nav', 'burger', 'iconOpen', 'iconClose'];

    connect() {
        this.close();
    }

    toggle() {
        this.navTarget.classList.contains('is-open') ? this.close() : this.open();
    }

    open() {
        this.navTarget.classList.add('is-open');
        this.burgerTarget.setAttribute('aria-expanded', 'true');
        this.burgerTarget.setAttribute('aria-label', 'Fermer le menu');
        this.iconOpenTarget.hidden = true;
        this.iconCloseTarget.hidden = false;
    }

    close() {
        this.navTarget.classList.remove('is-open');
        this.burgerTarget.setAttribute('aria-expanded', 'false');
        this.burgerTarget.setAttribute('aria-label', 'Ouvrir le menu');
        this.iconOpenTarget.hidden = false;
        this.iconCloseTarget.hidden = true;
    }
}
