<?php

namespace App\Command;

use App\Service\CatalogueFormations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Recharge le catalogue SmartOF en cache avant qu'il n'expire.
 *
 * À lancer par cron à un intervalle plus court que la durée du cache
 * (10 min), par exemple toutes les 8 minutes :
 *
 *     0-59/8 * * * *  php /chemin/du/site/bin/console catalogue:rafraichir
 *
 * Ainsi, l'appel SmartOF (environ 3 s) n'est jamais payé par un visiteur.
 * En cas d'échec, l'ancien cache reste servi et la commande sort en erreur,
 * pour que le cron puisse alerter.
 */
#[AsCommand(
    name: 'catalogue:rafraichir',
    description: 'Recharge le catalogue des formations depuis SmartOF',
)]
class RafraichirCatalogueCommand extends Command
{
    public function __construct(private readonly CatalogueFormations $catalogue)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $nombre = $this->catalogue->rafraichir();
        } catch (Throwable $e) {
            $io->error(sprintf('Catalogue non rechargé, l\'ancien cache reste servi : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Catalogue rechargé : %d formation(s) publiée(s).', $nombre));

        return Command::SUCCESS;
    }
}
