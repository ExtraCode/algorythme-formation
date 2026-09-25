import { Controller } from '@hotwired/stimulus';

/*
 * Amène le visiteur sur ce qui vient de changer dans un formulaire
 * réaffiché : la première erreur, ou la confirmation d'envoi.
 *
 * Après un envoi, Turbo affiche la réponse comme une nouvelle page, en
 * haut. Le visiteur ne voit ni les messages sous les champs, ni la
 * confirmation qui a remplacé le formulaire, et croit que rien ne s'est
 * passé. Une redirection vers une ancre n'y change rien : le navigateur
 * retire le fragment de l'adresse d'une réponse redirigée, Turbo ne le
 * voit jamais.
 *
 * Le moment compte : Turbo (7.3) remet la page en haut juste après
 * `turbo:render`, sans événement pour s'y accrocher. Le défilement est
 * donc lancé depuis `turbo:render`, différé d'un tour de boucle pour
 * passer après cette remise en haut. Fait dans `connect()`, il serait
 * annulé (les contrôleurs sont connectés avant la fin du rendu).
 *
 * <section data-controller="reveler" data-action="turbo:render@document->reveler#reveler">
 *     <input aria-invalid="true">   ou   <p role="alert">…</p>   ou   <div role="status">…</div>
 * </section>
 */
export default class extends Controller {
    disconnect() {
        clearTimeout(this.minuteur);
    }

    reveler() {
        clearTimeout(this.minuteur);
        this.minuteur = setTimeout(() => this.montrer(), 0);
    }

    montrer() {
        const cible = this.element.querySelector('[aria-invalid="true"], [role="alert"], [role="status"]');

        if (!cible) {
            return;
        }

        // Saut immédiat : la page vient d'être remplacée, une animation
        // partirait du haut et n'aurait aucun sens pour le visiteur.
        cible.scrollIntoView({ block: 'center', behavior: 'instant' });

        if (typeof cible.focus === 'function') {
            cible.focus({ preventScroll: true });
        }
    }
}
