import { Controller } from '@hotwired/stimulus';

/*
 * Bascule entre les formules de session d'une formation (inter / intra).
 *
 * Chaque formule est rendue par le serveur ; le contrôleur ne fait que
 * masquer celles qui ne sont pas retenues. Sans JavaScript, la première
 * reste affichée et le détail complet figure dans les modalités.
 */
export default class extends Controller {
    static targets = ['panneau'];

    connect() {
        this.afficher();
    }

    choisir() {
        this.afficher();
    }

    afficher() {
        const coche = this.element.querySelector('input[name="formule"]:checked');
        const formule = coche ? coche.value : null;

        this.panneauTargets.forEach((panneau) => {
            panneau.hidden = formule !== null && panneau.dataset.formule !== formule;
        });
    }
}
