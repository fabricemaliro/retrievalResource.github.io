# 1. Justification de la conception

**Étude de cas.** Le service des archives d'une université conserve des milliers de documents étudiants et administratifs. Le personnel perd du temps à retrouver le bon dossier.
**Tâche.** Concevoir une interface simple de recherche d'information pour ce système d'information organisationnel.
**Solution.** *LU Records Finder*, un système web de recherche documentaire **bilingue (anglais/français)** en PHP natif (SQLite ou MySQL), pour le service des archives de l'Université de Lakeview (fictive).

Les diapositives posent trois questions de justification : qui sont les utilisateurs, quelles métadonnées servent à la recherche, et comment l'accès est contrôlé. Les sections 1.2 à 1.4 y répondent dans cet ordre. La section 1.5 explique le fonctionnement de la recherche, la section 1.6 le bilinguisme et la section 1.7 ce qui a été laissé de côté.

---

## 1.1 Le problème en termes de système d'information

| Symptôme au service des archives | Cause | Réponse de la conception |
|---|---|---|
| Le personnel ouvre plusieurs armoires ou dossiers avant de trouver le bon | Les documents sont classés selon **une seule** dimension (par ex. le département), mais on les cherche selon **plusieurs** (nom, numéro, date, type) | Chaque document est décrit par des **métadonnées** structurées et son **texte intégral** est indexé : on peut le retrouver par n'importe laquelle de ces entrées |
| Deux personnes classent le même type de document différemment | Pas de vocabulaire contrôlé | Listes fixes pour le *type de document*, le *département* et le *niveau d'accès* ; numéros de référence générés (`LU/DEPT/ANNÉE/NNNN`) |
| Des données étudiantes confidentielles sont visibles par quiconque accède à l'étagère ou au dossier partagé | L'accès n'est pas lié aux rôles | Contrôle d'accès par rôle et par attribut, appliqué **dans** la requête de recherche |
| Personne ne sait qui a emprunté ou consulté un dossier | Absence de traçabilité | Journal d'audit des consultations, téléchargements, ajouts et refus d'accès |
| Les recherches infructueuses passent inaperçues | Pas de boucle de retour | Les journaux de recherche et de clics alimentent la page **Évaluation** (requêtes sans résultat, taux de clic, rang cliqué) |
| Le personnel et les étudiants ne parlent pas tous la même langue de travail | Interface et documents dans une seule langue | Interface et documents **en anglais et en français**, au choix de l'utilisateur |

Le système remplit aussi les quatre rôles classiques d'un système d'information dans une organisation :

- **Opérationnel :** le personnel retrouve rapidement les documents.
- **Contrôle :** contrôle d'accès et piste d'audit.
- **Aide à la décision :** les statistiques d'usage montrent quels documents sont demandés et introuvables.
- **Gestion des archives :** une durée de conservation est associée à chaque type de document.

---

## 1.2 Qui sont les utilisateurs

La diapositive demande *« Qui cherche, et pourquoi ? »*. Cinq profils ont été tirés de l'étude de cas. Chacun correspond à un compte de démonstration.

| Profil (compte de démo) | Besoin d'information typique | Requête typique | Rôle et accès |
|---|---|---|---|
| **Responsable des archives** (`manager`) | Supervise toute la collection, répond aux demandes juridiques, contrôle les usages | `avis juridique licenciement`, journal d'audit | `admin` : tout, y compris le *restreint* |
| **Agent des archives** (`officer`) | Traite au quotidien les demandes du personnel, des employeurs et des étudiants ; enregistre les nouveaux documents | `relevé de notes Brian Okello`, `LU/REG/2024/0005` | `records_officer` : tout sauf le *restreint* ; peut ajouter des documents et consulter l'évaluation |
| **Personnel de département**, par ex. un administrateur de faculté (`fci.admin`) ou un agent des finances (`finance`) | Retrouve les dossiers de son service et les documents de portée générale | `procès-verbal fraude aux examens`, `politique exonération des frais` | `dept_staff` : public + interne + **confidentiel de son seul département** |
| **Étudiant** (`student`, Brian Okello) | Consulte les politiques et ses propres dossiers | `politique exonération`, `relevé de notes` | `student` : public + **ses propres dossiers** (reconnus par son numéro d'inscription) |
| *(Demandeur externe : employeur, ministère)* | Demande une vérification | — | Pas de compte. La demande est traitée par un agent des archives et conservée comme *Correspondance* |

**Ce que ces utilisateurs ont en commun.** Ils sont occupés, peu technophiles, et connaissent généralement *un seul fait précis* sur le document cherché : un nom, un numéro d'inscription, une référence, une réunion ou une année. La conception en découle :

- **Une seule barre de recherche d'abord**, comme Google, avec le curseur déjà placé (`autofocus` ; la touche `/` y ramène depuis n'importe quelle page).
- **Les identifiants fonctionnent tels quels.** `21/U/04567` ou `LU/HR/2023/0042` renvoie le document exact au rang 1, avec le badge *Numéro exact*.
- **Les fautes de frappe sont pardonnées.** `politiqe exonératoin` donne « Vouliez-vous dire *politique exonération* ? ».
- **Les mots du quotidien fonctionnent.** L'expansion de requête relie `tricherie` → *fraude*, `examens` → *examination*, `scolarité` → *tuition*.
- **Les filtres viennent ensuite.** Ils réduisent une liste trop longue. Ce n'est pas un formulaire à remplir avant de pouvoir chercher.
- **Deux langues.** Chacun choisit le français ou l'anglais avec le sélecteur **EN | FR** de l'en-tête. Tout le site suit ce choix : menus, libellés, messages, dates, nombres, et les documents eux-mêmes. Les requêtes peuvent être saisies dans l'une ou l'autre langue (voir §1.6).

---

## 1.3 Quelles métadonnées servent à la recherche

La diapositive demande *« Quels champs comptent le plus ? »*. Chaque document porte les champs ci-dessous. Les colonnes du milieu indiquent où chaque champ est utilisé : indexé pour la recherche par mots-clés (avec son poids de classement), comme filtre, ou pour l'affichage et le tri.

| Champ | Exemple | Recherche par mots (poids) | Filtre | Affichage / tri | Pourquoi c'est important |
|---|---|---|---|---|---|
| **Numéro de référence** | `LU/FIN/2022/0007` | ✓ bonus de correspondance exacte | champ avancé | carte de résultat | Identifiant unique cité dans les courriers et les dossiers : le chemin le plus rapide vers un document connu |
| **Titre** | *Politique d'exonération des frais de scolarité et de bourses* | ✓ **×3** | champ avancé | titre, tri A–Z | Texte court le plus descriptif ; un mot du titre indique ce dont parle le document |
| **Type de document** | *Relevé de notes* | ✓ ×2 | ✓ facette | badge | Liste contrôlée de 14 types ; la façon la plus courante d'affiner |
| **Département (propriétaire)** | *Faculté d'informatique et TI* | ✓ ×2 | ✓ facette | carte de résultat | Contexte organisationnel et base de l'accès par département |
| **Date de création** / année | 2 sept. 2024 | — | ✓ facette + plage de dates | tri récents/anciens | On se souvient souvent du *quand* (« le PV de l'an dernier ») |
| **Année universitaire** | 2023/2024 | ✓ ×2 | — | panneau de métadonnées | La façon dont une université pense le temps |
| **Nom de l'étudiant** | Brian Okello | ✓ ×2 | — | carte de résultat | La plupart des demandes étudiantes partent d'un nom |
| **Numéro d'inscription** | `21/U/04567` | ✓ bonus de correspondance exacte | champ avancé | carte de résultat | Distingue les homonymes ; rattache un document à son étudiant pour le contrôle d'accès |
| **Programme** | Licence en informatique | ✓ ×2 | — | panneau de métadonnées | Nécessaire pour les demandes par promotion (par ex. toutes les admissions 2024 en informatique) |
| **Auteur / signataire** | Registraire académique | ✓ ×2 | champ avancé | carte de résultat | « La note envoyée par l'économe » |
| **Mots-clés** | *exonération des frais, bourse, aide financière* | ✓ ×2 | puces cliquables | page de détail | Ajoutent des termes absents du texte (synonymes, thèmes) |
| **Résumé** | une phrase | ✓ ×1,5 | — | page de détail | Meilleur extrait quand il n'y a pas de texte intégral |
| **Texte intégral** | corps du document | ✓ ×1 | recherche d'expression | extrait surligné | Retrouve un document par ce qu'il contient |
| **Versions linguistiques** | titre, résumé, mots-clés, texte et auteur en anglais *et* en français | ✓ les deux indexées | — | affichées dans la langue de l'utilisateur | Le même document est trouvé et lu par le personnel anglophone et francophone |
| **Niveau d'accès** | *Confidentiel* | — | ✓ facette | badge coloré | Explique pourquoi on peut, ou non, voir un document |
| **Statut** | actif / archivé | — | « Inclure les documents archivés » (agents) | badge | Écarte les anciens documents sans les supprimer |
| **Durée de conservation** | *Permanente*, *7 ans* | — | — | page de détail | Obligation de gestion des archives (calendrier d'élimination) |
| **Fichier** (nom, type, taille) | contrat.pdf, 240 Ko | texte extrait des DOCX, TXT et PDF textuels | — | téléchargement | Le document original |

**Pourquoi pondérer les champs.** Un mot présent dans le *titre* est un indice de pertinence plus fort que le même mot perdu dans le corps du texte. L'indexeur multiplie donc les fréquences des termes par les poids ci-dessus avant le classement (BM25). C'est pourquoi *« Procès-verbal de la réunion extraordinaire du Comité des examens sur la fraude aux examens »* passe devant les documents qui ne citent la fraude qu'en passant.

**Les vocabulaires contrôlés** (type, département, niveau d'accès) gardent des compteurs de facettes cohérents. Un champ « type » en texte libre éclaterait « PV », « procès-verbal », « compte rendu » et « Minutes » en quatre valeurs.

---

## 1.4 Comment l'accès est contrôlé

Notes des étudiants, soldes de frais, dossiers disciplinaires et contrats de travail sont des données personnelles. La loi ougandaise de 2019 sur la protection des données et de la vie privée (*Data Protection and Privacy Act*), comme le RGPD, exige que seules les personnes qui en ont besoin puissent les voir. La conception combine quatre mécanismes.

### a) Classement de chaque document (quatre niveaux d'accès)

| Niveau | Qui peut le voir | Exemples |
|---|---|---|
| **Public** | Tout utilisateur connecté, y compris les étudiants | Politiques, plans de cours, listes des diplômés |
| **Interne** | Tout le personnel | Notes de service, la plupart des PV, rapports financiers, synthèses de résultats |
| **Confidentiel** | Service des archives + **département propriétaire** ; un étudiant voit **les siens** | Relevés de notes, lettres d'admission, relevés de frais, contrats de travail |
| **Restreint** | Responsable des archives uniquement | Dossiers disciplinaires, avis juridiques |

### b) Règles par rôle et par attribut

| Rôle | Public | Interne | Confidentiel | Restreint |
|---|---|---|---|---|
| Responsable des archives (`admin`) | ✓ | ✓ | ✓ | ✓ |
| Agent des archives | ✓ | ✓ | ✓ | ✗ |
| Personnel de département | ✓ | ✓ | seulement si `document.département = utilisateur.département` | ✗ |
| Étudiant | ✓ | ✗ | seulement si `document.n°_inscription = utilisateur.n°_inscription` | ✗ |

### c) Filtrage de sécurité : appliqué dans la requête, pas seulement dans l'interface

La même règle est appliquée deux fois (voir `src/AccessControl.php`) :

1. **Comme condition SQL dans la requête de recherche.** Un document que l'utilisateur ne peut pas ouvrir n'entre jamais dans les candidats. Il n'apparaît ni dans les résultats, ni dans les compteurs de facettes, ni dans les « documents liés », ni dans les suggestions orthographiques. Cacher seulement le bouton *Ouvrir* laisserait fuiter de l'information : un résultat intitulé *« Dossier disciplinaire — Brian Okello »* est lui-même confidentiel.
2. **Comme vérification avant l'ouverture ou le téléchargement** (`document.php`, `download.php`). Cela bloque quiconque devine une adresse comme `document.php?id=123`. La réponse est le même message *« n'existe pas ou vous n'êtes pas autorisé »*, que le fichier existe ou non, pour qu'on ne puisse pas découvrir les dossiers restreints par tâtonnement.

La page Évaluation vérifie ces règles sur les données réelles. La section 4 du rapport d'évaluation montre que seul le responsable voit les 4 documents restreints, que les finances voient 60 des 379 documents confidentiels, et que l'étudiant en voit 6, tous les siens.

### d) Contrôles complémentaires

- **Authentification.** Les mots de passe sont stockés hachés (`password_hash`). L'identifiant de session est régénéré à la connexion. La connexion est bloquée 60 s après 5 échecs.
- **Protection des formulaires.** Chaque formulaire POST porte un jeton CSRF. Tout l'affichage est échappé et toutes les requêtes SQL sont préparées, contre le XSS et l'injection SQL.
- **Fichiers envoyés.** Ils sont contrôlés par une liste d'extensions autorisées et une taille maximale, stockés hors de la racine web sous un nom aléatoire, et servis uniquement par `download.php`, après la vérification d'accès.
- **Moindre privilège à l'enregistrement.** Un agent des archives ne peut pas classer un document en *Restreint* ; seul le responsable le peut.
- **Piste d'audit.** Connexions, échecs de connexion, consultations, téléchargements, ajouts, reconstructions de l'index et refus d'accès sont enregistrés. La page de détail montre au responsable l'historique d'accès de chaque document.

---

## 1.5 Fonctionnement de la recherche

```
        INDEXATION (à l'ajout d'un document)                         INTERROGATION (chaque recherche)
 document ──► champs EN+FR ─► normaliser ─► découper ─► retirer     requête ─► analyser ("expressions", -exclure, préfixe*, n°)
 (titre, mots-clés,         (minuscules,   (mots +    mots vides            │
  métadonnées, résumé,       sans accents)  n°)       + raciner             ▼
  texte)                                                   │         même analyse + synonymes EN/FR
                                                           ▼                │
                                        INDEX INVERSÉ  terms(terme, df)  ◄──┘ recherche
                                                       postings(terme, doc, tf pondéré)
                                                           │
                                                           ▼
                              candidats ∩ condition de contrôle d'accès ∩ filtres (SQL)
                                                           │
                                                           ▼
                BM25 × coordination (part des mots de la requête trouvés) + bonus d'identifiant exact
                                                           │
                                                           ▼
            compteurs de facettes → filtres → tri → page de 10 → extraits surlignés dans la langue choisie
```

- **Index inversé** (tables `terms` et `postings`). Pour chaque terme, il liste les documents qui le contiennent, avec une fréquence pondérée par champ. C'est ce qui évite à un moteur de relire tous les documents à chaque recherche.
- **BM25** (`k1 = 1,2`, `b = 0,75`), la fonction de classement standard des moteurs modernes. Un mot de la requête compte davantage s'il est fréquent dans le document et rare dans la collection (*idf*), avec une correction pour la longueur du document.
- **Facteur de coordination.** Un document qui contient tous les mots de la requête passe devant un document qui en répète un seul.
- **Facettes.** Elles sont comptées sur l'ensemble des résultats déjà filtrés par le contrôle d'accès. Chaque facette est comptée avec les *autres* facettes appliquées, pour que l'utilisateur voie toujours combien de documents chaque option renverrait.
- **Extraits.** Le moteur choisit la fenêtre de 280 caractères qui contient le plus de mots distincts de la requête, et surligne les correspondances sans tenir compte des accents (`<mark>`).
- **Expansion de requête.** Un petit thésaurus bilingue (`src/Search/Synonyms.php`) rapproche le vocabulaire des utilisateurs de celui des documents. Les synonymes comptent avec un poids de 0,8.
- **Suggestions orthographiques.** Pour un mot inconnu ou rare, le moteur propose le mot indexé le plus proche en distance d'édition (Levenshtein), comparé sans accents et affiché avec ses accents.

---

## 1.6 Interface et documents bilingues (anglais / français)

- **Choix de la langue.** Un sélecteur **EN | FR** figure dans l'en-tête de chaque page, y compris la page de connexion. La première visite suit la langue préférée du navigateur. Le choix est ensuite conservé dans la session et dans un cookie pendant un an. Changer de langue garde la page, la requête et les filtres en cours.
- **L'interface.** Le texte anglais sert de clé de traduction (`__('Search')`) ; les traductions françaises sont dans `lang/fr.php`, un fichier unique qu'on peut compléter pour une troisième langue. Un script vérifie que chaque clé utilisée dans le code est traduite, et un test navigateur échoue s'il reste du texte d'interface anglais sur une page française. Les dates (`2 sept. 2024`), les nombres (`1 805 000 UGX`, `3,88`) et les tailles de fichier (`Mo`) suivent les conventions françaises.
- **Les documents.** Chaque document stocke ses champs descriptifs dans les deux langues (`title`/`title_fr`, `description`/`description_fr`, `keywords`/`keywords_fr`, `content`/`content_fr`, `author`/`author_fr`). Les vocabulaires contrôlés (types, départements, programmes, niveaux d'accès, durées de conservation) sont traduits par le dictionnaire. Un document est affiché, surligné, relié et **téléchargé** dans la langue de l'utilisateur.
- **Documents dans une seule langue.** Un fichier envoyé dans une seule langue est affiché dans cette langue, avec la mention *« Ce document n'est disponible qu'en anglais »* et un badge *« En anglais uniquement »* dans les résultats. Le formulaire d'ajout prévoit un champ anglais et un champ français pour chaque élément descriptif, pour que le personnel puisse ajouter une traduction.
- **Recherche bilingue.** Les deux versions d'un document alimentent le même index inversé. L'analyseur connaît les mots vides anglais et français, ignore les accents (`relevé` = `releve`) et gère les pluriels français (`journaux` → `journal`). Le thésaurus relie les deux vocabulaires (`transcript ↔ relevé ↔ notes`, `malpractice ↔ fraude`, `waiver ↔ exonération`). Résultat : `relevé de notes Brian Okello` et `Brian Okello transcript` renvoient les mêmes documents, et le surlignage ignore les accents.
- **Les fichiers originaux ne sont pas traduits.** Un PDF ou un DOCX envoyé est restitué tel quel. Seuls les documents texte et les métadonnées sont exportés dans la langue choisie.

---

## 1.7 Périmètre et hypothèses

- **Pas d'OCR.** Les PDF numérisés sont conservés et téléchargeables, mais on ne les retrouve que par leurs métadonnées et le texte éventuellement collé à l'enregistrement. Le texte est extrait des DOCX, des TXT et des PDF textuels.
- **Pas de traduction automatique.** La seconde langue d'un document ajouté doit être saisie par le personnel.
- **Données de démonstration uniquement.** Le jeu de données (512 documents, 88 étudiants) est généré et fictif. La graine aléatoire est fixe, donc les résultats sont reproductibles.
- **Volume.** Le classement s'exécute en PHP, ce qui convient jusqu'à quelques dizaines de milliers de documents. Au-delà, la même conception reposerait sur un moteur dédié (MySQL FULLTEXT, Meilisearch, Elasticsearch ou OpenSearch). Le contrôle d'accès serait alors appliqué comme filtre de la requête du moteur.
- **Authentification unique.** En production, les comptes viendraient de l'annuaire de l'université (LDAP ou Microsoft Entra ID) plutôt que d'une table locale.
