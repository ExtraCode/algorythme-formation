<?php

namespace App\Service;

use AllowDynamicProperties;
use App\Entity\Formation;
use Brevo\Brevo;
use Brevo\Exceptions\BrevoException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestAttachmentItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AllowDynamicProperties]
class MailService
{

    public function __construct(TwigEnvironment $twig, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;

    }

    /**
     * Envoi d'un email transactionnel via l'API Brevo.
     *
     * SDK getbrevo/brevo-php 5.x : l'ancienne API (Brevo\Client\Api\TransactionalEmailsApi
     * + Brevo\Client\Model\SendSmtpEmail + Configuration) a disparu en v5, remplacée par
     * le client généré Brevo\Brevo et ses objets de requête typés.
     *
     * @param UploadedFile[] $attachments
     *
     * @throws BrevoException si Brevo refuse ou n'accepte pas l'envoi
     */
    public function sendBrevoMail(string $sujet,
                                  string $destinataire,
                                  string $messageMail,
                                  array  $attachments = [],
                                  string $mailExpediteur = "contact@algorythme-formation.fr"): void
    {
        $brevo = new Brevo($_ENV['APP_BREVO_API_KEY']);

        if ($_ENV['APP_ENV'] == "dev" || $_ENV['APP_ENV'] == "DEV") {
            $destinataire = $_ENV['APP_DESTINATAIRE_DEV'];
        }

        // Gestion des fichiers
        $formattedAttachments = [];

        foreach ($attachments as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $formattedAttachments[] = new SendTransacEmailRequestAttachmentItem([
                    'name' => $file->getClientOriginalName(),
                    'content' => base64_encode(file_get_contents($file->getPathname())),
                ]);
            }
        }

        $params = [
            'subject' => $sujet,
            'sender' => new SendTransacEmailRequestSender([
                'email' => $mailExpediteur,
                'name' => 'Algorythme Formation',
            ]),
            'to' => [new SendTransacEmailRequestToItem(['email' => $destinataire])],
            'htmlContent' => $messageMail,
        ];

        if (!empty($formattedAttachments)) {
            $params['attachment'] = $formattedAttachments;
        }

        try {
            $brevo->transactionalEmails->sendTransacEmail(new SendTransacEmailRequest($params));
        } catch (BrevoException $e) {
            // On journalise puis on relance : l'appelant décide quoi montrer
            // à l'utilisateur (un `echo` ici polluait la réponse HTTP).
            $this->logger->error('Échec de l\'envoi d\'un email Brevo.', [
                'sujet' => $sujet,
                'destinataire' => $destinataire,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Demande de rappel envoyée depuis le quiz de cadrage de la page d'accueil.
     *
     * @param array<string, string> $contact  nom, poste, email, tel
     * @param array<string, string> $reponses libellé de la question => réponse choisie
     *
     * @throws BrevoException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendDemandeRappel(array $contact, array $reponses): void
    {
        $sujet = 'Demande de rappel — ' . $contact['nom'] . ' (' . $contact['poste'] . ')';

        $message = $this->twig->render('emails/demande_rappel.html.twig', [
            'sujet' => $sujet,
            'contact' => $contact,
            'reponses' => $reponses,
        ]);

        $this->sendBrevoMail(
            $sujet,
            'contact@algorythme-formation.fr',
            $message,
        );
    }

    /*
     * Envoi un mail pour dire au formateur de la formation d'aller voir le message sur la formation
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendNotifConversationByEmail(Formation $formation): void
    {

        $sujet = "Nouveau message pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>vous venez de recevoir un message sur Eluv à propos de la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong></p>",
            'boutonText' => 'Voir le message',
            'boutonUrl' => $this->urlGenerator->generate('app_conversation_par_formation', ['idFormation' => $formation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formation->getFormateur()->getUser()->getEmail(), $message);

    }

    /*
     * Envoi un mail au formateur pour lui demander de signer électroniquement sa demande d'intervention
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendDemandeSignature(Formation $formation): void
    {

        $formateur = $formation->getSignatureFormateur();

        $sujet = "Demande d'intervention à signer pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Une demande d'intervention pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong> est prête à être signée.</p>",
            'boutonText' => 'Consulter et signer la demande',
            'boutonUrl' => $this->urlGenerator->generate('app_signature_voir', ['token' => $formation->getSignatureToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formateur->getUser()->getEmail(), $message);

    }

    /*
     * Relance le formateur qui n'a pas encore signé sa demande d'intervention
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendRelanceSignature(Formation $formation): void
    {

        $formateur = $formation->getSignatureFormateur();

        $sujet = "Rappel : demande d'intervention à signer pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Petit rappel : la demande d'intervention pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong>
                    est toujours en attente de votre signature.</p>",
            'boutonText' => 'Consulter et signer la demande',
            'boutonUrl' => $this->urlGenerator->generate('app_signature_voir', ['token' => $formation->getSignatureToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formateur->getUser()->getEmail(), $message);

    }

    /*
     * Prévient le formateur que sa demande d'intervention a été supprimée (annulée)
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendAnnulationDemandeSignature(Formation $formation): void
    {

        $formateur = $formation->getSignatureFormateur();

        $sujet = "Demande d'intervention annulée pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>La demande d'intervention qui vous avait été envoyée pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong>
                    a été annulée. Le lien de signature n'est donc plus valable.</p>
                    <p>N'hésitez pas à nous contacter si vous avez des questions.</p>",
        ]);

        $this->sendBrevoMail($sujet, $formateur->getUser()->getEmail(), $message);

    }

    /*
     * Envoi un mail au destinataire du devis pour lui demander de le signer électroniquement
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendDevisSignature(Formation $formation): void
    {

        $sujet = "Devis à signer pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Un devis pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong> est prêt à être signé.</p>",
            'boutonText' => 'Consulter et signer le devis',
            'boutonUrl' => $this->urlGenerator->generate('app_devis_voir', ['token' => $formation->getDevisToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formation->getDevisEmail(), $message);

    }

    /*
     * Relance le destinataire du devis qui ne l'a pas encore signé
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendRelanceDevisSignature(Formation $formation): void
    {

        $sujet = "Rappel : devis à signer pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Petit rappel : le devis pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong>
                    est toujours en attente de votre signature.</p>",
            'boutonText' => 'Consulter et signer le devis',
            'boutonUrl' => $this->urlGenerator->generate('app_devis_voir', ['token' => $formation->getDevisToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formation->getDevisEmail(), $message);

    }

    /*
     * Envoi un mail de confirmation au destinataire du devis une fois celui-ci signé,
     * avec un lien pour retrouver le PDF signé si besoin
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendDevisSignee(Formation $formation): void
    {

        $sujet = "Votre devis signé pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Le devis pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong> a bien été signé.</p>
                    <p>Vous pouvez le retrouver à tout moment via le lien ci-dessous.</p>",
            'boutonText' => 'Retrouver le devis signé',
            'boutonUrl' => $this->urlGenerator->generate('app_devis_voir', ['token' => $formation->getDevisToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $this->sendBrevoMail($sujet, $formation->getDevisEmail(), $message);

    }

    /*
     * Prévient le destinataire du devis que celui-ci a été supprimé (annulé)
     */
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function sendAnnulationDevis(Formation $formation): void
    {

        $sujet = "Devis annulé pour la formation " . $formation->getFormationPattern()->getNom();
        $message = $this->twig->render('emails/simple_message.html.twig', [
            'sujet' => $sujet,
            'message' => "Bonjour,
                    <p>Le devis qui vous avait été envoyé pour la formation <strong>" . $formation->getFormationPattern()->getNom() . "</strong>
                    a été annulé. Le lien de signature n'est donc plus valable.</p>
                    <p>N'hésitez pas à nous contacter si vous avez des questions.</p>",
        ]);

        $this->sendBrevoMail($sujet, $formation->getDevisEmail(), $message);

    }

}
