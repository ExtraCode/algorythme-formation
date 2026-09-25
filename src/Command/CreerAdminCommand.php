<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un compte d'accès à l'admin, ou change le mot de passe d'un compte
 * existant. Le mot de passe est demandé sans écho, jamais en argument : il
 * finirait dans l'historique du shell.
 *
 *     php bin/console admin:creer contact@algorythme-formation.fr
 */
#[AsCommand(
    name: 'admin:creer',
    description: "Crée un compte admin, ou change son mot de passe s'il existe",
)]
class CreerAdminCommand extends Command
{
    private const int LONGUEUR_MIN = 12;

    public function __construct(
        private readonly UserRepository              $users,
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email du compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string)$input->getArgument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email invalide.');

            return Command::INVALID;
        }

        $motDePasse = (string)$io->askHidden(
            sprintf('Mot de passe (%d caractères minimum)', self::LONGUEUR_MIN),
            function (?string $saisie): string {
                if (mb_strlen((string)$saisie) < self::LONGUEUR_MIN) {
                    throw new \RuntimeException(sprintf('%d caractères minimum.', self::LONGUEUR_MIN));
                }

                return (string)$saisie;
            },
        );

        $user = $this->users->findOneBy(['email' => $email]);
        $existait = $user !== null;
        $user ??= (new User())->setEmail($email);

        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));

        $this->em->persist($user);
        $this->em->flush();

        $io->success($existait
            ? sprintf('Mot de passe de %s mis à jour.', $email)
            : sprintf('Compte admin %s créé.', $email));

        return Command::SUCCESS;
    }
}
