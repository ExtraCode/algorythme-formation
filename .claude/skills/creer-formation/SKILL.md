---
name: creer-formation
description: Créer (ou réécrire) une formation dans SmartOF en s'inspirant d'une formation en ligne (URL d'un concurrent, d'un catalogue…). À utiliser quand l'utilisateur donne une URL de formation, demande de créer / ajouter / réécrire une formation ou une fiche produit SmartOF.
---

# Créer une formation SmartOF à partir d'une formation en ligne

Argument attendu : l'URL de la formation source, éventuellement suivie des tarifs INTER / INTRA
et de consignes (durée, public…). S'il manque l'URL, la demander.

Les échanges avec l'utilisateur se font en français.

## Outil

`node .claude/skills/creer-formation/smartof.mjs <endpoint> [payload.json]`, toujours précédé de
`MSYS_NO_PATHCONV=1` sous Git Bash. Sortie : code HTTP en 1re ligne, puis le JSON.
Écrire les fichiers de travail (payloads, sauvegardes) dans le scratchpad de la session.

Endpoints utiles : `/api/produit/list` (renvoie `{ produits: [...] }`), `/api/produit/get`
(`{"produitFormationUid": "…"}`), `/api/produit/create`, `/api/produit/update`.
Doc OpenAPI : `https://europe-west3-algorythme-formation-mobileo.cloudfunctions.net/docs/swagger/swagger-ui-init.js`
(clé `swaggerDoc`).

## Déroulé

1. **Lire la source** (WebFetch ; si le site bloque, passer par Chrome) : titre, durée, public,
   prérequis, objectifs, programme, points forts.
2. **Chercher les doublons** : `produit/list`, comparer noms et programmes. Si une formation proche
   existe, le signaler et proposer : réécrire l'existante, créer une formation complémentaire,
   ou changer de source. Ne pas créer de doublon sans accord.
3. **Rédiger une version originale.** S'inspirer, ne jamais recopier : autre découpage, autres
   formulations, ateliers qui nous ressemblent, contenu à jour. Un programme copié se repère
   (concurrence, SEO). Prendre une fiche existante comme modèle de ton (`produit/get`).
4. **Montrer le brouillon complet** à l'utilisateur et **attendre sa validation** avant tout
   appel d'écriture.
5. **Créer** avec `meta.status = "En cours de rédaction"` (invisible sur le site).
6. **Vérifier** en relisant le produit via l'API (champ par champ), puis faire le point.

## Format des champs (conventions validées par le client)

Payload de `produit/create` : `customId`, `updatedAt` (ISO, requis), `meta {nom, status}`,
`description` (objet **complet**, tous les champs présents, `""` / `0` si vides,
`annexe: {documentName: ""}`), `archived: false`, `custom_fields` (les 20, `""` si vides).
`produit/update` : même chose + `produitFormationUid` ; partir d'un `produit/get` frais, ne modifier
que les champs voulus, renvoyer `updatedAt` tel quel (sinon 400).

- `meta.nom` : titre public (`intituleDeLaFormation` reste vide). Il donne l'URL de la page :
  **ne pas renommer** une formation publiée.
- `customId` : code court en majuscules (`PY-FOND`, `LLM-GEN`, `CHATGPT-INIT`).
- `dureeDeLaFormation` : en heures, 7 h par jour. `modeDOrganisation` : `"Présentiel"`.
- `objectifsDeLaFormation`, `publicVise`, `preRequis` : une ligne par point, préfixée `• `.
  Objectifs formulés avec un verbe d'action, évaluables (Qualiopi).
- `contenuDeLaFormation` :
  ```
  JOUR 1 – Titre de la journée
  ◦ Séquence (2h)
  • Point abordé
  • Atelier : …

  ◦ Séquence suivante (1h30)
  …
  ```
  - Titre de journée **sur la même ligne** que `JOUR n`, séparé par ` – `.
  - Formation d'**un seul jour** : pas de ligne `JOUR`, directement les `◦`.
  - Chaque journée totalise 7 h ; vérifier que la somme des séquences = durée totale.
- Champs perso :
  - `custom_field_1` : `"oui"` pour mettre en page d'accueil (sinon `""`).
  - `custom_field_2` : accroche du catalogue, une phrase.
  - `custom_field_3` : nombre de professionnels formés → `"0"` pour une nouvelle formation.
  - `custom_field_4` : domaine, **reprendre une valeur existante à l'identique** (ex.
    `Intelligence Artificielle`, `Développement informatique`, `Big data et analytics`).
  - `custom_field_5` : note sur 5 → `"0"` pour une nouvelle formation.
  - `custom_field_6` : ancien sous-domaine, supprimé dans SmartOF → toujours `""`.
  - `custom_field_7` : « Ce que ça change, concrètement », 3 blocs :
    ```
    ◦ Situation de travail
    Aujourd'hui : ce qui se passe sans la formation (concret, une phrase).
    Après : ce qui change après la formation (une phrase).
    ```
  - `custom_field_8` à `20` : libres.
- Les champs de modalités (`modalitesPedagogiques`, `modalitesDEvaluationEtDeSuivi`,
  `modalitesDAccesALaFormation`, `profilDuOuDesFormateurs`, `moyensEtSupportsPedagogiques`,
  `lieuDeLaFormation`) sont vides sur toutes les fiches : les laisser vides, le site les gère.

## Limites de l'API à annoncer à l'utilisateur

- **Tarifs** : l'API ne les expose pas. Après création, l'utilisateur ajoute à la main dans SmartOF
  un tarif intitulé `INTER` et un tarif `INTRA` (le site lit ces intitulés).
- **Hors BPF** : `produit/create` coche « hors BPF » par défaut ; à décocher à la main.
- Passage en `Actif` : fait par l'utilisateur dans SmartOF après relecture. Le catalogue du site se
  rafraîchit en 10 minutes au plus (cache).

Le mapping côté site est dans `src/Service/CatalogueFormations.php`.
