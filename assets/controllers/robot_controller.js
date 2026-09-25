import { Controller } from '@hotwired/stimulus';

/*
 * Case « Je ne suis pas un robot » (reCAPTCHA v2) dans un formulaire.
 *
 * Le script de Google ne dessine la case qu'au chargement initial de la
 * page. Avec Turbo, la page est remplacée sans rechargement : le widget
 * est donc rendu explicitement à chaque connexion du contrôleur, et le
 * script n'est chargé qu'une fois.
 *
 * <div data-controller="robot" data-robot-cle-value="…clé de site…"></div>
 */

const URL_SCRIPT = 'https://www.google.com/recaptcha/api.js?onload=robotPret&render=explicit&hl=fr';

let chargement = null;

function chargerScript() {
    if (window.grecaptcha?.render) {
        return Promise.resolve();
    }

    if (!chargement) {
        chargement = new Promise((resolve) => {
            window.robotPret = resolve;
            const script = document.createElement('script');
            script.src = URL_SCRIPT;
            script.async = true;
            document.head.appendChild(script);
        });
    }

    return chargement;
}

export default class extends Controller {
    static values = { cle: String };

    connect() {
        // Au retour arrière, Turbo restaure une page où le widget avait
        // déjà été dessiné : on repart d'un élément vide.
        this.element.replaceChildren();

        chargerScript().then(() => {
            if (this.element.isConnected) {
                window.grecaptcha.render(this.element, { sitekey: this.cleValue });
            }
        });
    }
}
