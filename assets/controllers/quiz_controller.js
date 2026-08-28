import { Controller } from '@hotwired/stimulus';

/*
 * Quiz de cadrage de la page d'accueil : quatre questions, un
 * récapitulatif, puis un formulaire de rappel.
 *
 * Les questions sont dans le template Twig (une `stepTarget` chacune) :
 * le contrôleur ne fait que piloter l'étape affichée, le récapitulatif
 * et l'envoi du formulaire vers la route Symfony `app_demande_rappel`.
 */
export default class extends Controller {
    static targets = [
        'step',
        'recap',
        'signal',
        'form',
        'sent',
        'answer',
        'reponse',
        'back',
    ];

    static values = {
        answersKey: { type: String, default: 'af-quiz' },
    };

    static NETWORK_ERROR =
        "L'envoi a échoué. Appelez-nous au 05 54 54 24 84, nous prenons le relais.";

    connect() {
        this.step = 0;
        this.answers = {};
        this.render();
    }

    // --- Navigation entre les questions ------------------------------

    pick({ params }) {
        this.answers[params.key] = params.value;
        this.store(this.answersKeyValue, this.answers);
        this.step += 1;
        this.render(true);
    }

    back() {
        this.step = Math.max(0, this.step - 1);
        this.render(true);
    }

    restart() {
        this.step = 0;
        this.answers = {};
        this.store(this.answersKeyValue, this.answers);
        this.hideMessage();
        this.formTarget.hidden = false;
        this.formTarget.reset();
        this.clearHints();
        this.render(true);
    }

    // --- Envoi de la demande de rappel --------------------------------

    async submit(event) {
        event.preventDefault();

        // Première passe côté client : évite un aller-retour réseau pour
        // des erreurs évidentes. Le serveur revalide de toute façon.
        const errors = this.validate(this.fieldValues());

        if (Object.keys(errors).length) {
            this.showHints(errors);

            return;
        }

        this.clearHints();
        this.hideMessage();

        // Les champs désactivés sortent du FormData : on le construit avant.
        const body = new FormData(this.formTarget);
        this.setBusy(true);

        try {
            const response = await fetch(this.formTarget.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
            });

            const payload = await response.json().catch(() => ({}));

            if (response.ok) {
                this.formTarget.hidden = true;
                this.showMessage(payload.message, false);

                return;
            }

            if (payload.errors) {
                this.showHints(payload.errors);

                return;
            }

            this.showMessage(payload.message ?? this.constructor.NETWORK_ERROR, true);
        } catch (error) {
            this.showMessage(this.constructor.NETWORK_ERROR, true);
        } finally {
            this.setBusy(false);
        }
    }

    // --- Rendu --------------------------------------------------------

    render(moveFocus = false) {
        const done = this.step >= this.stepTargets.length;

        this.stepTargets.forEach((step, index) => {
            step.hidden = index !== this.step;
        });

        this.recapTarget.hidden = !done;

        // Le bouton « Question précédente » n'a pas de sens sur la première.
        this.backTargets.forEach((button) => {
            button.hidden = this.step === 0;
        });

        if (done) {
            this.answerTargets.forEach((cell) => {
                cell.textContent = this.answers[cell.dataset.quizAnswer] ?? '—';
            });

            // Les réponses partent avec le formulaire.
            this.reponseTargets.forEach((input) => {
                input.value = this.answers[input.dataset.quizAnswer] ?? '';
            });
        }

        // L'onde se régularise à mesure que le cadrage se précise.
        if (this.hasSignalTarget) {
            const disorder = done ? 0 : 1 - this.step / this.stepTargets.length;
            this.signalTarget.dataset.signalDisorderValue = String(disorder);
        }

        if (moveFocus) {
            const visible = done ? this.recapTarget : this.stepTargets[this.step];
            visible.querySelector('h3')?.focus();
        }
    }

    // --- Utilitaires --------------------------------------------------

    fieldValues() {
        const { elements } = this.formTarget;

        return {
            nom: elements.nom.value.trim(),
            poste: elements.poste.value.trim(),
            email: elements.email.value.trim(),
            tel: elements.tel.value.trim(),
        };
    }

    validate(values) {
        const errors = {};

        if (!values.nom) {
            errors.nom = "Indiquez votre nom pour qu'on sache qui rappeler.";
        }

        if (!values.poste) {
            errors.poste = 'Indiquez votre poste.';
        }

        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(values.email)) {
            errors.email = 'Indiquez un email valide pour recevoir la confirmation.';
        }

        if (values.tel.replace(/\D/g, '').length < 10) {
            errors.tel = 'Indiquez un numéro à 10 chiffres pour être rappelé.';
        }

        return errors;
    }

    setBusy(busy) {
        this.formTarget
            .querySelectorAll('button, input:not([type="hidden"])')
            .forEach((field) => {
                field.disabled = busy;
            });
    }

    showMessage(text, isError) {
        this.sentTarget.textContent = text;
        this.sentTarget.classList.toggle('quiz__sent--error', Boolean(isError));
        this.sentTarget.hidden = false;
        this.sentTarget.focus();
    }

    hideMessage() {
        this.sentTarget.hidden = true;
        this.sentTarget.textContent = '';
    }

    showHints(errors) {
        this.clearHints();

        Object.entries(errors).forEach(([name, message]) => {
            const hint = this.formTarget.querySelector(`[data-quiz-hint="${name}"]`);
            if (hint) {
                hint.textContent = message;
            }

            this.formTarget.elements[name]?.setAttribute('aria-invalid', 'true');
        });

        this.formTarget.elements[Object.keys(errors)[0]]?.focus();
    }

    clearHints() {
        this.formTarget.querySelectorAll('[data-quiz-hint]').forEach((hint) => {
            hint.textContent = '';
        });

        this.formTarget.querySelectorAll('[aria-invalid]').forEach((field) => {
            field.removeAttribute('aria-invalid');
        });
    }

    // Le stockage local peut être indisponible (mode privé, quota) :
    // l'échec ne doit jamais interrompre le parcours.
    store(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Silencieux : le quiz reste utilisable sans persistance.
        }
    }
}
