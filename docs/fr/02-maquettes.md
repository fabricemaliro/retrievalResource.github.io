# 2. Maquettes (wireframes) des pages de recherche et de résultats

Ces maquettes basse fidélité viennent d'abord. Chaque page renvoie ensuite à une capture de la version réalisée, en français (`../screenshots/fr-*.png`) et en anglais (`../screenshots/*.png`).
La liste de contrôle des maquettes de la diapositive est couverte ainsi :

| Élément de la liste | Où |
|---|---|
| Barre de recherche et bouton | M1 (accueil), répétée en haut de M2 |
| Filtres et tri | M2 : panneau de gauche (type, département, année, niveau d'accès, plage de dates, champs précis) et menu *Trier par* |
| Liste de résultats avec extraits | M2 : cartes classées, avec extrait surligné, badges et ligne de métadonnées |
| Page de détail du document | M4 |
| *Que se passe-t-il quand rien n'est trouvé ?* | M3 |

---

## M1 — Accueil de la recherche

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ [LU] Records Finder  Rechercher  Ajouter  Évaluation  Journal  Votre avis  [EN|FR]  RÔLE │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│                       RECHERCHEZ PARMI 505 DOCUMENTS ACCESSIBLES                         │
│                               Que recherchez-vous ?                                      │
│     ┌───────────────────────────────────────────────────────────────┐ ┌────────────┐     │
│     │ 🔍 Nom, n° d'inscription, référence ou mots-clés     |        │ │ Rechercher │     │
│     └───────────────────────────────────────────────────────────────┘ └────────────┘     │
│     ▸ Recherche avancée (référence · n° d'inscription · titre · auteur · type · dates)    │
│     Astuces : "expression exacte" · -mot · admis* · LU/HR/2023/0042 · FR ou EN           │
│                                                                                          │
│ ┌─ Parcourir par type de document ────────────────────┐ ┌─ Vos recherches récentes ────┐ │
│ │ [Relevé de notes   140] [Lettre d'admission  82]    │ │ • politique exonération      │ │
│ │ [Dossier d'inscr.   57] [Relevé de frais     55]    │ │ • relevé de notes Okello     │ │
│ │ [Procès-verbal      41] [Note de service     24] …  │ ├─ Ce que votre rôle voit ─────┤ │
│ │ (uniquement les types et compteurs autorisés)       │ │ PUBLIC ✓  INTERNE ✓          │ │
│ └─────────────────────────────────────────────────────┘ │ CONFIDENTIEL ✓ RESTREINT ✗   │ │
│                                                         └──────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Choix de conception**

- **Une barre de recherche dominante**, avec le curseur déjà placé. La tâche la plus courante ne demande aucune autre commande.
- **Le texte indicatif dit ce qu'on peut chercher** : noms, numéros, mots-clés. La ligne d'astuces enseigne la syntaxe sans page d'aide.
- **Les tuiles par type** servent ceux qui préfèrent reconnaître plutôt que se souvenir, et donnent une carte de la collection.
- **« Ce que votre rôle permet de voir »** fixe les attentes avant la recherche : un dossier confidentiel absent n'est pas pris pour une panne.
- **Le sélecteur EN | FR** est dans l'en-tête de chaque page. Il bascule tout le site, documents compris, sans perdre la recherche en cours.

Réalisé : [`fr-02-home.png`](../screenshots/fr-02-home.png) · [`02-home.png`](../screenshots/02-home.png)

---

## M2 — Page de résultats

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ ┌──────────────────────────────────────────────────────────────────────┐ ┌────────────┐  │
│ │ 🔍 lettre d'admission informatique 2024                              │ │ Rechercher │  │
│ └──────────────────────────────────────────────────────────────────────┘ └────────────┘  │
│ ┌─ Affiner ── Tout effacer ─┐  82 documents pour « lettre d'admission… » · 4 ms  Tri ▾  │
│ │ TYPE DE DOCUMENT          │  [Lettre d'admission ×]   ← filtres actifs (retirables)   │
│ │ ☐ Relevé de notes    140  │ ┌─────────────────────────────────────────────────────┐   │
│ │ ☑ Lettre d'admission  82  │ │ Lettre d'admission  CONFIDENTIEL     LU/ADM/2024/0103│   │
│ │ ☐ Dossier d'inscr.    57  │ │ Lettre d'admission — Harriet Auma (Licence en inf.) │   │
│ │   Afficher les 14         │ │ …vous êtes admise en Licence en ▓informatique▓ …    │   │
│ │ DÉPARTEMENT               │ │ …pour l'année universitaire ▓2024▓/2025…            │   │
│ │ ☐ Service des admissions  │ │ Service des admissions · 12 août 2024 · H. Auma ·   │   │
│ │ ANNÉE DE CRÉATION         │ │ 24/U/01234                                          │   │
│ │ ☐ 2025  11  ☐ 2024  18 …  │ └─────────────────────────────────────────────────────┘   │
│ │ NIVEAU D'ACCÈS            │ ┌─────────────────────────────────────────────────────┐   │
│ │ ☐ Confidentiel       82   │ │ … résultat suivant …                                │   │
│ │ DATE DE CRÉATION [du][au] │ └─────────────────────────────────────────────────────┘   │
│ │ ▸ Champ précis            │                                                          │
│ │ ☐ Inclure les archivés    │         [1] 2  3  4  Suivant →                           │
│ │ [ Appliquer les filtres ] │                                                          │
│ └───────────────────────────┘                                                          │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Choix de conception**

- **Les facettes affichent des compteurs**, calculés après le contrôle d'accès. Chaque facette est comptée avec les autres filtres appliqués : on ne peut jamais choisir un filtre qui mène à zéro résultat.
- **Cocher un filtre l'applique aussitôt** (avec JavaScript ; un bouton *Appliquer* fonctionne sans). Les filtres actifs apparaissent en puces retirables au-dessus des résultats.
- **Chaque carte de résultat répond à « est-ce le bon ? » sans l'ouvrir.** Viennent d'abord les badges de type et d'accès, puis la référence, le titre surligné, le passage qui correspond le mieux à la requête, et enfin le département, la date et l'étudiant.
- **Options de tri :** pertinence (par défaut avec une requête), plus récents (par défaut en parcours), plus anciens, titre A–Z.
- **Un document disponible dans une seule langue** porte le badge *« En anglais uniquement »*.
- **Sur téléphone,** les filtres se replient derrière un bouton *Filtres* ([`fr-12-mobile-results.png`](../screenshots/fr-12-mobile-results.png)).

Réalisé : [`fr-03-results.png`](../screenshots/fr-03-results.png) (requête) · [`fr-05-filtered.png`](../screenshots/fr-05-filtered.png) (facette + tri)

---

## M3 — Aucun résultat

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ 🔍 politiqe exonératoin                                                 [ Rechercher ]   │
│ ┌ Affiner ┐  Aucun document trouvé                                                      │
│ │ dates   │  ┌───────────────────────────────────────────────────────────────────────┐   │
│ │ champs  │  │ Vouliez-vous dire  politique exonération ?                             │   │
│ └─────────┘  └───────────────────────────────────────────────────────────────────────┘   │
│              ┌─ Aucun résultat pour « politiqe exonératoin » ─────────────────────────┐   │
│              │ • Essayez l'orthographe suggérée : politique exonération                │   │
│              │ • Relancer la recherche sans filtres (si des filtres sont actifs)       │   │
│              │ • Utilisez des mots moins nombreux et plus généraux                     │   │
│              │ • Cherchez par référence (LU/REG/2024/0005) ou n° d'inscription         │   │
│              │ • Utilisez admis* pour un début de mot · Parcourir par type             │   │
│              │ Certains documents sont restreints… contactez le Service des archives  │   │
│              └─────────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Choix de conception.** Une impasse est le pire résultat d'une recherche, donc la page propose toujours une étape suivante :

- une correction orthographique en un clic, qui garde les accents ;
- le retrait des filtres ;
- des conseils de reformulation ;
- le parcours par type ;
- un contact humain, en dernier recours.

La page explique aussi que les documents restreints ne sont pas affichés, sans confirmer qu'il en existe. Chaque requête sans résultat est enregistrée et listée sur la page Évaluation, pour que le Service des archives ajoute des synonymes ou des métadonnées.

Réalisé : [`fr-06-no-results.png`](../screenshots/fr-06-no-results.png)

---

## M4 — Détail du document

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ ← Retour aux résultats / Relevé de notes                                                  │
│ ┌──────────────────────────────────────────────────────┐ ┌─────────────────────────────┐ │
│ │ Relevé de notes  CONFIDENTIEL         LU/FCI/2024/0012│ │ [ Télécharger le document ] │ │
│ │ ▓Relevé▓ de ▓notes▓ 2023/2024 — ▓Brian Okello▓       │ │ Téléchargements audités     │ │
│ │ Résumé …                                             │ ├─ Métadonnées ───────────────┤ │
│ │ [relevé de notes] [résultats] [CGPA] ← mots-clés     │ │ Référence · Type · Départ.  │ │
│ │ ──────────────────────────────────────────────────── │ │ Date · Année universitaire  │ │
│ │ Texte intégral avec les termes cherchés surlignés …  │ │ Étudiant · Programme ·Auteur│ │
│ │                                                      │ │ Conservation · Statut       │ │
│ │                                                      │ │ Disponible en : Anglais ·   │ │
│ │                                                      │ │ Français · Accès + règle    │ │
│ │                                                      │ ├─ Documents liés ────────────┤ │
│ │                                                      │ ├─ Historique (responsable) ──┤ │
│ └──────────────────────────────────────────────────────┘ └─────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Choix de conception**

- **« Retour aux résultats » garde la requête, les filtres, le tri et la page.** Comparer plusieurs candidats ne coûte qu'un clic chacun.
- **Les termes cherchés restent surlignés** dans le texte intégral : on voit tout de suite pourquoi le document correspond.
- **« Documents liés »** (documents semblables, à partir des termes les plus caractéristiques) mène au reste du dossier d'un étudiant, ou aux autres versions d'une politique, sans nouvelle recherche.
- **Le panneau de métadonnées affiche la règle d'accès, la durée de conservation et les langues disponibles.**
- **Le téléchargement d'un document texte** se fait dans la langue de l'interface (`…-fr.txt`).

Réalisé : [`fr-04-document.png`](../screenshots/fr-04-document.png)

---

## Autres écrans

| Écran | Capture (FR) | Capture (EN) |
|---|---|---|
| Connexion (avec les comptes de démo) | [`fr-01-login.png`](../screenshots/fr-01-login.png) | [`01-login.png`](../screenshots/01-login.png) |
| Ajout d'un document (champs anglais et français) | [`fr-07-upload.png`](../screenshots/fr-07-upload.png) | [`07-upload.png`](../screenshots/07-upload.png) |
| Questionnaire d'avis des pairs | [`fr-08-feedback.png`](../screenshots/fr-08-feedback.png) | [`08-feedback.png`](../screenshots/08-feedback.png) |
| Rapport d'évaluation | [`fr-09-evaluation.png`](../screenshots/fr-09-evaluation.png) | [`09-evaluation.png`](../screenshots/09-evaluation.png) |
| Journal d'audit | [`fr-10-audit.png`](../screenshots/fr-10-audit.png) | [`10-audit.png`](../screenshots/10-audit.png) |
| La même recherche vue par l'étudiant (filtrage de sécurité) | [`fr-11-student-view.png`](../screenshots/fr-11-student-view.png) | [`11-student-view.png`](../screenshots/11-student-view.png) |
