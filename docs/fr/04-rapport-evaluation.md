# 4. Rapport d'évaluation

**Système :** LU Records Finder, système web de recherche documentaire bilingue (anglais/français) pour le service des archives d'une université.
**Collection :** 512 documents de 14 types, issus de 10 départements, chacun en anglais et en français (fictifs, générés avec une graine fixe). Ils sont indexés en 2 692 termes et environ 52 700 entrées d'index.
**Méthode :** les trois étapes de la diapositive « Évaluer » :

1. Essayer cinq requêtes réalistes, dans chaque langue.
2. Compter les étapes nécessaires pour atteindre un document.
3. Recueillir l'avis des pairs.

Deux contrôles ont été ajoutés : l'analyse des journaux de recherche et la vérification du contrôle d'accès.
Tous les chiffres de ce rapport sont produits en direct par la page **Évaluation** (`evaluation.php`, qui exécute les requêtes dans la langue de l'interface) et par `php tests/run.php`. La page peut être imprimée en PDF avec son bouton *Imprimer*.

---

## 4.1 Cinq requêtes de test réalistes

Chaque requête correspond à un vrai besoin de l'un des profils (voir *Justification de la conception*). Elle est saisie exactement comme cet utilisateur le ferait, une fois en anglais et une fois en français.
L'**ensemble pertinent** est fixé *avant* d'exécuter la requête, à partir des métadonnées (par exemple « tous les relevés de notes dont l'étudiant est Brian Okello »). Il ne dépend pas de ce que renvoie le système et il est le même dans les deux langues.

| # | Utilisateur | Besoin d'information | Requête en français | Requête en anglais |
|---|---|---|---|---|
| Q1 | Agent de la scolarité | Vérifier le dossier d'un diplômé pour un employeur | `relevé de notes Brian Okello` | `Brian Okello transcript` |
| Q2 | Agent des admissions | Rassembler les lettres d'admission 2024 en informatique | `lettre d'admission informatique 2024` + filtre | `admission letter computer science 2024` + filtre |
| Q3 | Agent des finances | Répondre à un étudiant sur les exonérations de frais | `politique exonération des frais` | `fee waiver policy` |
| Q4 | Administrateur de faculté | Lister toutes les réunions qui ont traité de la fraude aux examens | `procès-verbal fraude aux examens` | `minutes examination malpractice` |
| Q5 | Agent des ressources humaines | Ouvrir un contrat cité par sa référence dans un courrier | `LU/HR/2023/0042` | `LU/HR/2023/0042` |

### Résultats (identiques en français et en anglais)

| # | Pertinents | Trouvés | 1er pertinent | P@5 | R-précision | Rappel@10 | Précision moyenne | Étapes |
|---|---|---|---|---|---|---|---|---|
| Q1 | 2 | 330 | rang 1 | 0,40* | 1,00 | 100 % | 1,00 | 3 |
| Q2 | 6 | 82 | rang 1 | 1,00 | 1,00 | 100 % | 1,00 | 4 |
| Q3 | 3 | 312 | rang 1 | 0,60* | 1,00 | 100 % | 1,00 | 3 |
| Q4 | 11 | 344 | rang 1 | 1,00 | 1,00 | 91 %** | 1,00 | 3 |
| Q5 | 1 | 1 | rang 1 | 0,20* | 1,00 | 100 % | 1,00 | 3 |
| **Moyenne** | | | | **0,64** | **1,00** | **98 %** | **MAP 1,00** | **3,2** |

Rang réciproque moyen (MRR) = 1,00 dans les deux langues. Temps de recherche moyen ≈ 3 ms sur un ordinateur portable. Les résultats sont identiques sous SQLite et sous MySQL/MariaDB.

\* La P@5 ne peut pas dépasser *(nombre de documents pertinents) / 5*. Q1 n'a que 2 documents pertinents, Q3 en a 3 et Q5 en a 1 : 0,40, 0,60 et 0,20 sont donc les meilleures valeurs possibles. La R-précision (précision au rang R, R étant le nombre de documents pertinents) et la précision moyenne sont ici les mesures justes.
\*\* Q4 a 11 documents pertinents et une page en affiche 10 : le rappel@10 est donc plafonné à 91 %. Les 11 sont classés de 1 à 11.

### Définition des mesures

| Mesure | Signification |
|---|---|
| **Précision@k** | Part des *k* premiers résultats qui sont pertinents |
| **Rappel@k** | Part de tous les documents pertinents trouvés dans les *k* premiers résultats |
| **R-précision** | Précision au rang R, où R = nombre de documents pertinents |
| **Précision moyenne (AP)** | Moyenne des précisions au rang de chaque document pertinent. Elle récompense le fait de placer *tous* les documents pertinents en tête |
| **MAP** | Moyenne des AP sur l'ensemble des requêtes |
| **Rang réciproque** | 1 / rang du premier résultat pertinent |
| **Étapes** | Nombre d'actions de l'utilisateur : saisir la requête + Entrée + chaque clic de filtre + chaque « page suivante » + ouverture du document |

### Pourquoi le classement fonctionne, dans les deux langues

- **Q1.** Le nom et les mots du type figurent dans le titre et les métadonnées (poids ×3 et ×2). Le facteur de coordination place ensuite les deux documents qui contiennent tous les mots de la requête devant les autres dossiers de Brian. En français, `relevé` et `notes` correspondent au titre *Relevé de notes*.
- **Q2.** La facette *Lettre d'admission* écarte tout le reste en un clic. *informatique* / *Computer Science* et *2024* classent ensuite la bonne promotion en tête.
- **Q3.** *exonération* et *waiver* sont rares dans la collection (idf élevé) et figurent dans le titre des seuls documents pertinents.
- **Q4.** Les procès-verbaux pertinents contiennent *fraude aux examens* en français et *malpractice* en anglais, dans leur titre, leurs mots-clés ou leur texte. Le thésaurus bilingue relie aussi `examen ↔ exam ↔ examination` et `fraude ↔ tricherie ↔ malpractice ↔ cheating`.
- **Q5.** Les identifiants sont indexés comme un seul terme (`luhr20230042`) et reçoivent un bonus de correspondance exacte. Ils fonctionnent de la même façon dans les deux langues.

### Étapes pour atteindre un document

| Parcours | Actions |
|---|---|
| Requête typique (Q1, Q3, Q4, Q5) | saisir → Entrée → ouvrir le résultat = **3** |
| Avec un filtre (Q2) | saisir → Entrée → cocher *Lettre d'admission* → ouvrir = **4** |
| Moyenne des tests | **3,2** |
| Changer de langue | +1 clic, une seule fois (le choix est mémorisé) |

---

## 4.2 Limites constatées pendant les tests

La MAP parfaite doit être lue avec prudence, pour deux raisons :

- **Les données sont générées.** Elles viennent de modèles cohérents : titres et métadonnées sont plus propres que dans un vrai service d'archives, et la version française de chaque document est une traduction complète.
- **Les jugements viennent des métadonnées.** Le système indexe ces mêmes métadonnées.

Des requêtes plus difficiles montrent les points faibles du système.

| Requête testée | Constat | Cause | Amélioration possible |
|---|---|---|---|
| `relevé de notes Okello` | Les relevés de plusieurs Okello différents arrivent en tête | Nom de famille ambigu | Demander le prénom ou le numéro d'inscription ; ajouter une facette « Étudiant » quand plusieurs étudiants correspondent |
| `calendrier des examens` / `exam timetable` | Aucun calendrier d'examens n'existe. La note *Calendrier des congés annuels* arrive en tête | Le synonyme `timetable ↔ calendrier` augmente le rappel mais fait remonter un « calendrier » sans rapport : c'est l'arbitrage rappel/précision de l'expansion de requête | Baisser le poids des synonymes ou les limiter par type de document ; ajouter de vrais calendriers d'examens à la collection |
| `tricherie examens` / `cheating in exams` *(avant l'expansion de requête)* | Un seul résultat | Les documents disent *fraude* / *malpractice* et *examens* / *examination* | **Corrigé :** le thésaurus bilingue renvoie désormais les procès-verbaux sur la fraude en tête, dans les deux langues |
| Toute requête de plusieurs mots | Environ 300 documents trouvés (par ex. 330 pour Q1) | Logique OU, pour ne rien manquer de pertinent. Indexer les deux langues allonge aussi la traîne de correspondances faibles | Acceptable car on dépasse rarement la page 1 et les facettes réduisent l'ensemble. On pourrait ajouter une option « tous les mots » ou un score minimal |
| Document ajouté dans une seule langue | Affiché dans sa langue d'origine, avec une mention ; retrouvable seulement avec des mots de cette langue (plus les synonymes du thésaurus) | Pas de traduction automatique | Le personnel peut coller une traduction dans le second champ de langue ; une traduction automatique pourrait être ajoutée plus tard |
| PDF numérisé | Retrouvable uniquement par ses métadonnées | Pas d'OCR | Lancer l'OCR Tesseract à l'envoi du fichier |
| Collection de bien plus de 10⁴ documents | Le temps de réponse augmente | Le classement s'exécute en PHP | Passer l'index sur MySQL FULLTEXT, Meilisearch ou Elasticsearch, en gardant le contrôle d'accès comme filtre de la requête |

---

## 4.3 Statistiques d'usage

Le système enregistre chaque recherche (requête, filtres, nombre de résultats, temps) et chaque résultat ouvert depuis une page de résultats (rang cliqué). La page Évaluation en tire des indicateurs :

| Indicateur | Ce qu'il apprend au Service des archives |
|---|---|
| Taux de recherches sans résultat et liste de ces requêtes | Documents manquants, synonymes manquants (dans l'une ou l'autre langue) ou métadonnées manquantes |
| Taux de clic | Si la page de résultats répond au besoin |
| Rang moyen cliqué / part des clics sur le 1er résultat | Qualité du classement telle que la vivent les utilisateurs (le rang 1 est idéal) |
| Nombre moyen de résultats et temps de réponse | Charge et performances |
| Requêtes les plus fréquentes | Documents les plus demandés (candidats à des liens rapides) |

La base est livrée sans journaux. Les chiffres se remplissent dès que le système est utilisé. Faites une séance avec vos pairs, puis consultez à nouveau la page.

---

## 4.4 Vérification du contrôle d'accès

Nombre de documents actifs que chaque compte peut trouver, généré par la page Évaluation. Il est le même dans les deux langues, car le contrôle d'accès ne dépend pas de la langue.

| Compte | Public (35) | Interne (87) | Confidentiel (379) | Restreint (4) | Total |
|---|---|---|---|---|---|
| `manager`, Responsable des archives | 35 | 87 | 379 | 4 | 505 |
| `officer`, Agent des archives | 35 | 87 | 379 | 0 | 501 |
| `fci.admin`, Faculté d'informatique | 35 | 87 | 42 (sa faculté) | 0 | 164 |
| `finance`, Finances | 35 | 87 | 60 (son département) | 0 | 182 |
| `student`, Brian Okello | 35 | 0 | 6 (ses propres dossiers) | 0 | 41 |

*(Sept documents archivés sont exclus de ces comptes.)*

Les tests automatiques confirment aussi les points suivants :

- Un étudiant qui cherche `relevé de notes` ou `transcript` ne voit que ses propres relevés.
- Le personnel de faculté ne voit aucun contrat RH.
- Un document restreint ouvert par son adresse renvoie *introuvable* pour tous sauf le responsable des archives, et la tentative est inscrite au journal d'audit.

---

## 4.5 Contrôles de qualité du bilinguisme

| Contrôle | Résultat |
|---|---|
| Chaque texte d'interface utilisé dans le code a une traduction française (`lang/fr.php`) | 323 / 323 |
| Test navigateur : aucun texte d'interface anglais sur les pages françaises (12 écrans) | réussi |
| Chaque document de démonstration a un titre, un résumé, des mots-clés, un texte et un auteur en français | 512 / 512 |
| Même requête en français et en anglais → mêmes documents pertinents en tête | 5 / 5 requêtes de test |
| Téléchargement d'un document texte dans la langue choisie (`…-fr.txt` / `…-en.txt`) | réussi |
| Document ajouté en français seulement, consulté en anglais → affiché en français avec une mention | réussi |

---

## 4.6 Avis des pairs

**Comment le recueillir.** Confiez à chaque pair une tâche, de Q1 à Q5, dans la langue de son choix. Il l'effectue connecté avec le compte de démonstration correspondant, puis remplit **Votre avis**. Le formulaire demande :

- quelle tâche a été effectuée ;
- si le document a été trouvé ;
- combien de clics il a fallu ;
- cinq affirmations notées de 1 à 5 : facile à utiliser, résultats pertinents, trouvé rapidement, filtres utiles, satisfaction globale ;
- un commentaire libre.

La page Évaluation calcule les moyennes et liste les commentaires.

**Résultats.** *(À remplir après la séance avec les pairs. Le système est livré sans aucun avis, pour qu'aucune réponse ne soit inventée.)*

| Affirmation | Moyenne (1–5) |
|---|---|
| Le système était facile à utiliser | … |
| Les résultats correspondaient à ce que je cherchais | … |
| J'ai trouvé le document rapidement | … |
| Les filtres et le tri m'ont aidé à affiner les résultats | … |
| Globalement, j'utiliserais ce système pour mon travail | … |
| Tâche réussie / clics déclarés en moyenne | … % / … |

Principaux commentaires et modifications apportées : …

---

## 4.7 Conclusion

L'interface répond au besoin de l'étude de cas dans les deux langues. Un agent des archives atteint le bon document en **3 actions** environ, et sur les cinq requêtes de test le premier résultat était toujours pertinent, que la requête soit saisie en français ou en anglais (**MRR 1,00, MAP 1,00**). Les documents confidentiels et restreints ne sont jamais exposés aux rôles non autorisés, pas même dans les compteurs ou les extraits.

Le principal risque en usage réel est l'**écart de vocabulaire** entre les utilisateurs et les documents, désormais dans deux langues. La conception y répond de trois façons :

- le thésaurus bilingue, dont il faut surveiller les effets de bord (par ex. *calendrier*) ;
- la correspondance insensible aux accents et les suggestions orthographiques ;
- le journal des requêtes sans résultat, qui indique au Service des archives les termes à ajouter.

Les prochaines améliorations seraient l'OCR des fichiers numérisés, une aide à la traduction des documents ajoutés dans une seule langue, une facette « Étudiant » pour distinguer les homonymes, et un moteur de recherche externe quand la collection dépassera quelques dizaines de milliers de documents.
