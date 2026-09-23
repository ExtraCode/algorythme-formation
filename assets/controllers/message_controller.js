import { Controller } from '@hotwired/stimulus';

/*
 * Fermeture d'un bandeau de message.
 *
 * Le message ne survit pas au chargement suivant : il n'y a rien à retenir,
 * il suffit de le retirer de la page.
 */
export default class extends Controller {
    fermer() {
        this.element.remove();
    }
}
