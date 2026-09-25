import { Controller } from '@hotwired/stimulus';

/*
 * Signale qu'un envoi de formulaire est en cours de traitement.
 *
 * L'orientation attend la réponse de l'API Claude : sans signe de vie, le
 * visiteur croit que son clic n'a pas été pris en compte et recommence. Le
 * formulaire passe donc en `aria-busy` et son bouton est désactivé le temps
 * de l'analyse ; le changement de libellé est fait en CSS.
 *
 * <form data-controller="attente"
 *       data-action="attente#envoyer turbo:submit-end->attente#terminer">
 *     <button data-attente-target="bouton">…</button>
 * </form>
 */
export default class extends Controller {
    static targets = ['bouton'];

    connect() {
        // Turbo met la page en cache au moment de la quitter, formulaire
        // occupé compris : au retour arrière, il faut le rendre utilisable.
        this.reposer();
    }

    envoyer() {
        this.element.setAttribute('aria-busy', 'true');
        this.boutonTarget.disabled = true;
    }

    /*
     * Envoi terminé sans redirection (réseau coupé, erreur serveur) : le
     * visiteur reste sur la page, il doit pouvoir réessayer. Quand l'envoi
     * réussit, Turbo affiche la page suivante : rien à remettre en place.
     */
    terminer(event) {
        if (event.detail?.success === false) {
            this.reposer();
        }
    }

    reposer() {
        this.element.removeAttribute('aria-busy');
        this.boutonTarget.disabled = false;
    }
}
