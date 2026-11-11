# 3. Parcours utilisateur : de la requête au document

```mermaid
flowchart TD
    A([Un agent a besoin d'un dossier]) --> B[Connexion]
    B --> C{Rôle}
    C -->|Responsable / Agent / Personnel / Étudiant| D[Accueil de la recherche<br/>barre de recherche + parcours par type]
    D --> E[Saisir la requête et Entrée<br/>nom, n° d'inscription, référence, mots-clés<br/>en français ou en anglais]
    E --> F[[Le contrôle d'accès restreint l'index<br/>aux documents autorisés pour le rôle]]
    F --> G[Résultats classés<br/>extraits, badges, facettes avec compteurs]
    G --> H{Le document est-il<br/>en première page ?}
    H -->|Oui| K[Détail du document<br/>métadonnées, texte surligné, documents liés]
    H -->|Trop de résultats| I[Affiner : filtres type, département,<br/>année, accès - ou tri / champs avancés]
    I --> G
    G -->|0 résultat| J[Page sans résultat<br/>vouliez-vous dire, retirer les filtres,<br/>chercher par numéro, parcourir, contacter les archives]
    J --> E
    K --> L[Télécharger - enregistré dans le journal d'audit]
    K --> M[Retour aux résultats, requête conservée]
    M --> G
    L --> N([Tâche terminée<br/>3 actions en général])
```

![Parcours utilisateur](parcours-utilisateur.png)

*(Source : [`parcours-utilisateur.mmd`](parcours-utilisateur.mmd), Mermaid. Version anglaise : [`../user-flow.png`](../user-flow.png).)*

## Parcours principal : 3 actions

| Étape | Action de l'utilisateur | Réponse du système |
|---|---|---|
| 0 | Se connecte | Charge le rôle, le département et le numéro d'inscription utilisés par le contrôle d'accès, ainsi que la langue choisie |
| 1 | Saisit `relevé de notes Brian Okello` dans la barre de recherche (déjà active) | — |
| 2 | Appuie sur **Entrée** | Analyse la requête, interroge l'index inversé, ne garde que les documents autorisés pour le rôle, les classe avec BM25, compte les facettes, construit les extraits dans la langue choisie et enregistre la recherche |
| 3 | Clique sur le premier résultat | Vérifie à nouveau l'accès, enregistre le clic (rang 1) et la consultation, puis affiche les métadonnées, le texte surligné et les documents liés |
| (4) | Clique sur **Télécharger** | Vérifie l'accès, écrit une entrée d'audit et transmet le fichier original (ou un export texte dans la langue choisie) |

## Parcours alternatifs

| Situation | Parcours | Actions en plus |
|---|---|---|
| Trop de résultats | Cocher une facette (par ex. *Lettre d'admission*) ou changer le tri | +1 par filtre |
| Référence ou numéro d'inscription connu | Le saisir ou le coller, par ex. `LU/HR/2023/0042` → correspondance exacte au rang 1 | 0 |
| Faute de frappe | Page sans résultat → **Vouliez-vous dire … ?** | +1 |
| Rien n'est trouvé | Retirer les filtres → reformuler → parcourir par type → écrire au Service des archives | +1 à +3 |
| Comparer des candidats | Ouvrir un résultat → **Retour aux résultats** (requête, filtres et page conservés) → ouvrir le suivant | +2 par candidat |
| Préfère l'anglais | Cliquer **EN** dans l'en-tête : la même page, la même requête et les mêmes résultats s'affichent en anglais | +1 (une seule fois) |
| Document interdit (par ex. adresse devinée) | Page générique « n'existe pas ou non autorisé » ; le refus est inscrit au journal d'audit | — |

La page Évaluation compte les étapes de chaque requête de test. La moyenne est de **3,2 actions** entre la saisie de la requête et l'ouverture du bon document, en français comme en anglais.
