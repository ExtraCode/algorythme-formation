import { Controller } from '@hotwired/stimulus';

/*
 * Filtre du catalogue des formations par domaine.
 *
 * Les domaines viennent de SmartOF : une option radio par domaine dans le
 * formulaire, et la même valeur en data-domaine sur chaque carte.
 */
export default class extends Controller {
    static targets = ['form', 'carte', 'compteur', 'vide'];

    connect() {
        this.filtrer();
    }

    filtrer() {
        const coche = this.formTarget.querySelector('input[name="domaine"]:checked');
        const domaine = coche ? coche.value : 'tout';
        let visibles = 0;

        this.carteTargets.forEach((carte) => {
            const ok = domaine === 'tout' || carte.dataset.domaine === domaine;
            carte.hidden = !ok;
            if (ok) visibles++;
        });

        this.compteurTarget.textContent = this.libelleCompteur(visibles);
        this.videTarget.hidden = visibles !== 0;
    }

    // Le navigateur remet les radios à zéro après l'événement reset :
    // on laisse passer un tour avant de relire le formulaire.
    reinitialiser() {
        setTimeout(() => this.filtrer(), 0);
    }

    libelleCompteur(n) {
        if (n === 0) return 'Aucune action de formation affichée.';
        if (n === 1) return '1 action de formation affichée.';
        return `${n} actions de formation affichées.`;
    }
}
