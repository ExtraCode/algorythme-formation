<?php

namespace App\Repository;

use App\Entity\Requete;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Requete>
 */
class RequeteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Requete::class);
    }

    /**
     * Les requêtes de la plus récente à la plus ancienne, par page ;
     * celles d'un seul visiteur si une empreinte est donnée.
     *
     * @return Paginator<Requete>
     */
    public function page(int $page, int $parPage, ?string $empreinte = null): Paginator
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.creeLe', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $parPage)
            ->setMaxResults($parPage);

        if ($empreinte !== null) {
            $qb->andWhere('r.empreinte = :empreinte')->setParameter('empreinte', $empreinte);
        }

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Nombre total de recherches de chaque visiteur donné.
     *
     * @param list<string> $empreintes
     *
     * @return array<string, int> empreinte => nombre de requêtes
     */
    public function compterParEmpreinte(array $empreintes): array
    {
        if (!$empreintes) {
            return [];
        }

        $lignes = $this->createQueryBuilder('r')
            ->select('r.empreinte, COUNT(r.id) AS nombre')
            ->where('r.empreinte IN (:empreintes)')
            ->setParameter('empreintes', array_values(array_unique($empreintes)))
            ->groupBy('r.empreinte')
            ->getQuery()
            ->getArrayResult();

        return array_column($lignes, 'nombre', 'empreinte');
    }

    /**
     * Chiffres du tableau de bord.
     *
     * @return array{total: int, visiteurs: int, semaine: int, sansResultat: int}
     */
    public function statistiques(): array
    {
        $ligne = $this->createQueryBuilder('r')
            ->select(
                'COUNT(r.id) AS total',
                'COUNT(DISTINCT r.empreinte) AS visiteurs',
                'SUM(CASE WHEN r.creeLe >= :semaine THEN 1 ELSE 0 END) AS semaine',
            )
            ->setParameter('semaine', new DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleResult();

        // Liste JSON vide : l'analyse a tourné mais rien ne convenait.
        $sansResultat = (int)$this->getEntityManager()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM requete WHERE JSON_LENGTH(formations) = 0"
        );

        return [
            'total' => (int)$ligne['total'],
            'visiteurs' => (int)$ligne['visiteurs'],
            'semaine' => (int)$ligne['semaine'],
            'sansResultat' => $sansResultat,
        ];
    }
}
