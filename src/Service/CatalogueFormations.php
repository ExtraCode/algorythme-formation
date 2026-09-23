<?php

namespace App\Service;

use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Catalogue public des formations, construit à partir des produits SmartOF.
 *
 * SmartOF est la source unique : rien n'est saisi côté site. Ce service fait
 * la traduction entre le produit SmartOF (champs libres, textes à puces) et
 * ce que les gabarits attendent (listes, déroulé par journée).
 */
class CatalogueFormations
{
    /**
     * Champs personnalisés SmartOF (Produit > Champs personnalisés).
     * L'API ne renvoie pas leurs libellés, seulement custom_field_N :
     * la correspondance est fixée ici.
     */
    private const string CHAMP_ACCROCHE = 'custom_field_2';
    private const string CHAMP_DOMAINE = 'custom_field_4';
    private const string CHAMP_SOUS_DOMAINE = 'custom_field_6';
    private const string CHAMP_NB_FORMES = 'custom_field_3';
    private const string CHAMP_NOTE = 'custom_field_5';
    private const string CHAMP_CE_QUI_CHANGE = 'custom_field_7';

    /** Note maximale saisie dans SmartOF (sur 5). */
    private const int NOTE_MAX = 5;

    /** Seuls les produits à ce statut sont publiés sur le site. */
    private const string STATUT_PUBLIE = 'Actif';

    /** Durée d'une journée de formation, pour afficher les jours à côté des heures. */
    private const int HEURES_PAR_JOUR = 7;

    /**
     * Formules de session, reconnues dans l'intitulé du tarif SmartOF
     * (SmartOF > Produit > Tarification).
     */
    private const string FORMULE_INTER = 'inter';
    private const string FORMULE_INTRA = 'intra';

    /**
     * Le catalogue est relu à cet intervalle : une modification dans SmartOF
     * apparaît sur le site au plus tard après ce délai.
     */
    private const int DUREE_CACHE = 600;

    /**
     * Le suffixe change dès que la forme du résumé change : au déploiement,
     * un cache écrit par la version précédente n'est pas servi aux gabarits
     * de la nouvelle.
     */
    private const string CLE_CACHE_LISTE = 'catalogue_formations_liste_v4';

    public function __construct(
        private readonly SmartOfApiService $smartOfApiService,
        private readonly SluggerInterface  $slugger,
        private readonly CacheInterface    $cache,
    )
    {
    }

    /**
     * Les formations publiées, triées par nom.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public function lister(): array
    {
        $formations = array_map($this->resume(...), $this->produitsPublies());

        usort($formations, static fn(array $a, array $b) => strcmp($a['slug'], $b['slug']));

        return $formations;
    }

    /**
     * Le catalogue tel qu'un conseiller a besoin de le lire pour rapprocher
     * une formation d'un besoin exprimé : ce que la formation apprend, à qui
     * elle s'adresse, ce qu'elle suppose déjà acquis.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public function pourOrientation(): array
    {
        $catalogue = [];

        foreach ($this->produitsPublies() as $produit) {
            $description = $produit['description'] ?? [];
            $resume = $this->resume($produit);

            $catalogue[] = [
                'slug' => $resume['slug'],
                'nom' => $resume['nom'],
                'domaine' => $resume['domaine']['nom'] ?? '',
                'sousDomaine' => $resume['sousDomaine'],
                'accroche' => $resume['accroche'],
                'duree' => $resume['dureeJours'] ?: $resume['duree'],
                'objectifs' => $this->puces($description['objectifsDeLaFormation'] ?? '')
                    ?: $this->puces($description['objectifsPedagogiques'] ?? ''),
                'publicVise' => $this->puces($description['publicVise'] ?? ''),
                'preRequis' => $this->puces($description['preRequis'] ?? ''),
            ];
        }

        return $catalogue;
    }

    /**
     * Les produits SmartOF publiables, bruts. C'est cette réponse qui est
     * mise en cache : les différentes vues du catalogue en sont dérivées
     * sans rappeler l'API.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function produitsPublies(): array
    {
        return $this->cache->get(self::CLE_CACHE_LISTE, function (ItemInterface $item): array {
            $item->expiresAfter(self::DUREE_CACHE);

            $response = $this->smartOfApiService->callSmartofApi('/api/produit/list');

            return array_values(array_filter(
                $response['produits'] ?? [],
                $this->estPubliable(...),
            ));
        });
    }

    /**
     * Les domaines saisis dans SmartOF, dédoublonnés et triés par ordre
     * alphabétique. Le slug est ASCII et en minuscules : le tri ignore donc
     * les accents et la casse.
     *
     * @return array<string, array{slug: string, nom: string}>
     *
     * @throws InvalidArgumentException
     */
    public function domaines(): array
    {
        $domaines = [];

        foreach ($this->lister() as $formation) {
            if ($formation['domaine'] !== null) {
                $domaines[$formation['domaine']['slug']] = $formation['domaine'];
            }
        }

        ksort($domaines);

        return $domaines;
    }

    /**
     * La fiche complète d'une formation publiée, ou null si le slug ne
     * correspond à aucune.
     *
     * @return array<string, mixed>|null
     *
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function fiche(string $slug): ?array
    {
        $resume = null;

        foreach ($this->lister() as $formation) {
            if ($formation['slug'] === $slug) {
                $resume = $formation;
                break;
            }
        }

        if ($resume === null) {
            return null;
        }

        $produit = $this->smartOfApiService->callSmartofApi('/api/produit/get', [
            'produitFormationUid' => $resume['id'],
        ]);

        $description = $produit['description'] ?? [];

        return $resume + [
            'objectifs' => $this->puces($description['objectifsDeLaFormation'] ?? ''),
            'objectifsPedagogiques' => $this->puces($description['objectifsPedagogiques'] ?? ''),
            'ceQuiChange' => $this->ceQuiChange((string)($produit['custom_fields'][self::CHAMP_CE_QUI_CHANGE] ?? '')),
            'deroule' => $this->deroule((string)($description['contenuDeLaFormation'] ?? '')),
            'publicVise' => $this->puces($description['publicVise'] ?? ''),
            'preRequis' => $this->puces($description['preRequis'] ?? ''),
            'methodes' => $this->puces($description['modalitesPedagogiques'] ?? ''),
            'moyens' => $this->puces($description['moyensEtSupportsPedagogiques'] ?? ''),
            'evaluation' => $this->puces($description['modalitesDEvaluationEtDeSuivi'] ?? ''),
            'acces' => $this->puces($description['modalitesDAccesALaFormation'] ?? ''),
            'profilFormateurs' => $this->puces($description['profilDuOuDesFormateurs'] ?? ''),
            'lieu' => trim((string)($description['lieuDeLaFormation'] ?? '')),
            // L'effectif des sessions est le même pour toutes les formations :
            // il est écrit dans le gabarit, pas repris de SmartOF.
            'misAJourLe' => $this->date($produit['updatedAt'] ?? null),
        ];
    }

    /**
     * Un produit est publiable s'il n'est pas archivé et que son statut
     * SmartOF vaut « Actif » : les programmes en cours de rédaction restent
     * invisibles du site.
     *
     * @param array<string, mixed> $produit
     */
    private function estPubliable(array $produit): bool
    {
        return ($produit['archived'] ?? false) !== true
            && ($produit['meta']['status'] ?? '') === self::STATUT_PUBLIE;
    }

    /**
     * Ce que la carte du catalogue affiche, et que la fiche complète reprend.
     *
     * @param array<string, mixed> $produit
     *
     * @return array<string, mixed>
     */
    private function resume(array $produit): array
    {
        $description = $produit['description'] ?? [];
        $champs = $produit['custom_fields'] ?? [];

        // L'intitulé de la fiche de formation n'est pas toujours saisi :
        // le nom du produit sert alors de titre public.
        $nom = trim((string)($description['intituleDeLaFormation'] ?? ''))
            ?: trim((string)($produit['meta']['nom'] ?? ''));

        $domaine = trim((string)($champs[self::CHAMP_DOMAINE] ?? ''));
        $tarifs = $this->tarifs($produit);

        return [
            'id' => $produit['produitFormationUid'],
            'slug' => $this->slugger->slug($nom)->lower()->toString(),
            'nom' => $nom,
            'domaine' => $domaine === '' ? null : [
                'slug' => $this->slugger->slug($domaine)->lower()->toString(),
                'nom' => $domaine,
            ],
            'sousDomaine' => trim((string)($champs[self::CHAMP_SOUS_DOMAINE] ?? '')),
            'accroche' => trim((string)($champs[self::CHAMP_ACCROCHE] ?? '')),
            'duree' => $this->dureeAffichee($description),
            'dureeJours' => $this->dureeEnJours($description),
            // La modalité (présentiel / distanciel) est la même pour toutes
            // nos formations : elle est écrite dans le gabarit, pas reprise de
            // `modeDOrganisation`, dont la saisie SmartOF est hétérogène.
            'tarifs' => $tarifs,
            'tarifSessionHT' => $this->tarifSessionHT($tarifs),
            'note' => $this->note($champs[self::CHAMP_NOTE] ?? ''),
            'nbFormes' => $this->nbFormes($champs[self::CHAMP_NB_FORMES] ?? ''),
        ];
    }

    /**
     * Note de satisfaction sur 5, saisie « 4.7 » ou « 4,7 ». Null quand elle
     * n'est pas renseignée : 0 signifie « pas encore de note », pas une
     * mauvaise note.
     */
    private function note(mixed $saisie): ?float
    {
        $note = (float)str_replace(',', '.', trim((string)$saisie));

        return $note > 0 ? min($note, self::NOTE_MAX) : null;
    }

    /**
     * Nombre de personnes formées. Null quand il n'est pas renseigné (0).
     */
    private function nbFormes(mixed $saisie): ?int
    {
        $nombre = (int)preg_replace('/\D/', '', (string)$saisie);

        return $nombre > 0 ? $nombre : null;
    }

    /**
     * Durée telle que saisie dans SmartOF, sinon calculée à partir des
     * heures : « 21 heures, 3 jours ».
     *
     * @param array<string, mixed> $description
     */
    private function dureeAffichee(array $description): string
    {
        $affichee = trim((string)($description['dureeDeLaFormationAffichee'] ?? ''));

        if ($affichee !== '') {
            return $affichee;
        }

        $heures = (float)($description['dureeDeLaFormation'] ?? 0);

        if ($heures <= 0) {
            return '';
        }

        $duree = rtrim(rtrim(number_format($heures, 1, ',', ' '), '0'), ',') . ' heures';

        $jours = $heures / self::HEURES_PAR_JOUR;

        if ($jours >= 1 && floor($jours) === $jours) {
            $duree .= sprintf(', %d jour%s', $jours, $jours > 1 ? 's' : '');
        }

        return $duree;
    }

    /**
     * Durée exprimée en journées : « 3 jours ». La fiche formation l'affiche
     * à la place des heures, plus parlant pour un commanditaire.
     *
     * Null quand le nombre d'heures ne tombe pas sur une journée ou une
     * demi-journée entière : mieux vaut alors s'en tenir aux heures.
     *
     * @param array<string, mixed> $description
     */
    private function dureeEnJours(array $description): ?string
    {
        $jours = $this->jours($description);

        if ($jours === null) {
            return null;
        }

        return rtrim(rtrim(number_format($jours, 1, ',', ' '), '0'), ',')
            . ' jour' . ($jours >= 2 ? 's' : '');
    }

    /**
     * Nombre de journées de formation. Null quand le volume horaire ne tombe
     * pas sur une journée ou une demi-journée entière.
     *
     * @param array<string, mixed> $description
     */
    private function jours(array $description): ?float
    {
        $heures = (float)($description['dureeDeLaFormation'] ?? 0);

        if ($heures <= 0) {
            return null;
        }

        $jours = $heures / self::HEURES_PAR_JOUR;

        if (abs($jours * 2 - round($jours * 2)) > 0.001) {
            return null;
        }

        return round($jours * 2) / 2;
    }

    /**
     * Les tarifs du produit, dans l'ordre de SmartOF. `formule` vaut
     * « inter » ou « intra » quand l'intitulé le précise, sinon null : le
     * gabarit s'en sert pour libeller chaque formule.
     *
     * Les montants SmartOF sont des totaux de session (prix unitaire ×
     * quantité), pas des prix à la journée.
     *
     * @param array<string, mixed> $produit
     *
     * @return array<int, array{formule: ?string, intitule: string, montantHT: float}>
     */
    private function tarifs(array $produit): array
    {
        $tarifs = [];

        foreach ($produit['presetTarification']['tarifs'] ?? [] as $tarif) {
            $montant = 0.0;

            foreach ($tarif['budget'] ?? [] as $ligne) {
                $montant += (float)($ligne['prixUnitaireHT'] ?? 0) * (float)($ligne['quantite'] ?? 1);
            }

            if ($montant <= 0) {
                continue;
            }

            $intitule = trim((string)($tarif['intitule'] ?? ''));

            $tarifs[] = [
                'formule' => $this->formule($intitule),
                'intitule' => $intitule,
                'montantHT' => $montant,
            ];
        }

        return $tarifs;
    }

    /**
     * « INTER » / « INTRA » saisis dans l'intitulé du tarif SmartOF.
     * Null quand le produit n'a qu'un tarif, sans formule précisée.
     */
    private function formule(string $intitule): ?string
    {
        $intitule = mb_strtolower($intitule);

        foreach ([self::FORMULE_INTER, self::FORMULE_INTRA] as $formule) {
            if (str_contains($intitule, $formule)) {
                return $formule;
            }
        }

        return null;
    }

    /**
     * Coût HT de session affiché sur la carte du catalogue : la formule
     * inter quand le produit distingue les deux, sinon le premier tarif.
     * Null en l'absence de tarif.
     *
     * @param array<int, array{formule: ?string, montantHT: float}> $tarifs
     */
    private function tarifSessionHT(array $tarifs): ?float
    {
        foreach ($tarifs as $tarif) {
            if ($tarif['formule'] === self::FORMULE_INTER) {
                return $tarif['montantHT'];
            }
        }

        return $tarifs[0]['montantHT'] ?? null;
    }

    /**
     * Les champs longs de SmartOF sont des textes à puces saisis à la main.
     * Chaque ligne devient un élément de liste, la puce en moins.
     *
     * @return array<int, string>
     */
    private function puces(mixed $texte): array
    {
        $lignes = preg_split('/\R/', trim((string)$texte)) ?: [];

        $items = [];

        foreach ($lignes as $ligne) {
            $ligne = trim(preg_replace('/^\s*[•◦▪\-–—*]\s*/u', '', $ligne) ?? '');

            if ($ligne !== '') {
                $items[] = $ligne;
            }
        }

        return $items;
    }

    /**
     * « Ce que ça change, concrètement », saisi dans SmartOF :
     *
     *     ◦ Ajouter un formulaire                      ← situation de travail
     *     Aujourd'hui : Validation dispersée…          ← avant la formation
     *     Après : Un FormType, des contraintes…        ← après la formation
     *
     * Une situation sans ses deux états n'est pas affichée : la comparaison
     * n'aurait pas de sens.
     *
     * @return array<int, array{tache: string, avant: string, apres: string}>
     */
    private function ceQuiChange(string $saisie): array
    {
        $paires = [];
        $paire = null;

        foreach (preg_split('/\R/', trim($saisie)) ?: [] as $ligne) {
            $ligne = trim($ligne);

            if (str_starts_with($ligne, '◦')) {
                $paires[] = ['tache' => trim(ltrim($ligne, "◦ \t")), 'avant' => '', 'apres' => ''];
                $paire = array_key_last($paires);

                continue;
            }

            if ($paire !== null && preg_match("/^(aujourd['’]hui|après|apres)\s*:\s*(.+)$/ui", $ligne, $etat)) {
                $cle = str_starts_with(mb_strtolower($etat[1]), 'ap') ? 'apres' : 'avant';
                $paires[$paire][$cle] = trim($etat[2]);
            }
        }

        return array_values(array_filter(
            $paires,
            static fn(array $p) => $p['tache'] !== '' && $p['avant'] !== '' && $p['apres'] !== '',
        ));
    }

    /**
     * Déroulé pédagogique, tel qu'il est saisi dans SmartOF :
     *
     *     JOUR 1 – Découverte du framework   ← journée (facultatif)
     *     ◦ Introduction (1h30)              ← séquence
     *     • Présentation de l'écosystème     ← point abordé
     *
     * Les formations saisies sans ligne « JOUR » donnent une seule journée
     * sans titre, qui contient toutes les séquences.
     *
     * @return array<int, array{titre: ?string, duree: ?string, sequences: array<int, array{titre: string, duree: ?string, points: array<int, string>}>}>
     */
    private function deroule(string $contenu): array
    {
        $journees = [];

        // Indices de la journée et de la séquence en cours de remplissage :
        // chaque ligne vient s'ajouter à la dernière ouverte.
        $journee = null;
        $sequence = null;

        foreach (preg_split('/\R/', trim($contenu)) ?: [] as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '') {
                continue;
            }

            if (preg_match('/^(JOUR\s*\d+)\s*[–—:.-]?\s*(.*)$/ui', $ligne, $titre)) {
                $journees[] = [
                    'titre' => rtrim($titre[1] . ' — ' . $titre[2], ' —'),
                    'duree' => null,
                    'sequences' => [],
                ];

                $journee = array_key_last($journees);
                $sequence = null;

                continue;
            }

            // Hors ligne « JOUR », tout est rattaché à une journée sans titre.
            if ($journee === null) {
                $journees[] = ['titre' => null, 'duree' => null, 'sequences' => []];
                $journee = array_key_last($journees);
            }

            if (str_starts_with($ligne, '◦')) {
                $journees[$journee]['sequences'][] = $this->sequence(ltrim($ligne, "◦ \t"));
                $sequence = array_key_last($journees[$journee]['sequences']);

                continue;
            }

            $point = trim(preg_replace('/^\s*[•▪\-–—*]\s*/u', '', $ligne) ?? '');

            if ($point === '') {
                continue;
            }

            // Un point sans séquence ouverte (contenu saisi sans « ◦ ») forme
            // une séquence à lui seul : aucune ligne n'est perdue.
            if ($sequence === null) {
                $journees[$journee]['sequences'][] = $this->sequence($point);
                $sequence = array_key_last($journees[$journee]['sequences']);

                continue;
            }

            $journees[$journee]['sequences'][$sequence]['points'][] = $point;
        }

        foreach ($journees as $index => $journeeSaisie) {
            $journees[$index]['duree'] = $this->dureeTotale($journeeSaisie['sequences']);
        }

        // Une journée sans séquence (ligne « JOUR » orpheline) n'a rien à montrer.
        return array_values(array_filter($journees, static fn(array $j) => $j['sequences'] !== []));
    }

    /**
     * Une séquence du déroulé, dont la durée est notée entre parenthèses en
     * fin d'intitulé : « Introduction à Symfony (1h30) ».
     *
     * @return array{titre: string, duree: ?string, points: array<int, string>}
     */
    private function sequence(string $intitule): array
    {
        $intitule = trim($intitule);
        $duree = null;

        if (preg_match('/^(.*?)\s*\(([^()]*\d[^()]*)\)$/u', $intitule, $trouve)) {
            $intitule = trim($trouve[1]);
            $duree = $this->dureeLisible($trouve[2]);
        }

        return ['titre' => $intitule, 'duree' => $duree, 'points' => []];
    }

    /**
     * « 1h30 » saisi dans SmartOF s'affiche « 1 h 30 », « 2h » devient « 2 h ».
     */
    private function dureeLisible(string $duree): ?string
    {
        $minutes = $this->dureeEnMinutes($duree);

        if ($minutes === null) {
            return trim($duree) ?: null;
        }

        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        if ($heures === 0) {
            return $reste . ' min';
        }

        return $reste === 0 ? $heures . ' h' : sprintf('%d h %02d', $heures, $reste);
    }

    /**
     * @param array<int, array{duree: ?string}> $sequences
     */
    private function dureeTotale(array $sequences): ?string
    {
        $minutes = 0;

        foreach ($sequences as $sequence) {
            $minutes += $this->dureeEnMinutes((string)$sequence['duree']) ?? 0;
        }

        return $minutes > 0 ? $this->dureeLisible(sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60)) : null;
    }

    /**
     * Accepte les notations rencontrées dans SmartOF : « 1h30 », « 2 h »,
     * « 45 min », « 1 h 30 ».
     */
    private function dureeEnMinutes(string $duree): ?int
    {
        if (preg_match('/(\d+)\s*h\s*(\d+)?/ui', $duree, $trouve)) {
            return (int)$trouve[1] * 60 + (int)($trouve[2] ?? 0);
        }

        if (preg_match('/(\d+)\s*(?:min|mn)/ui', $duree, $trouve)) {
            return (int)$trouve[1];
        }

        return null;
    }

    private function date(mixed $valeur): ?DateTimeImmutable
    {
        if (!is_string($valeur) || $valeur === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($valeur);
        } catch (\Exception) {
            return null;
        }
    }
}
