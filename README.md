# LU Records Finder

**🇫🇷 Système web de recherche documentaire bilingue (français / anglais) en PHP natif · 🇬🇧 Bilingual (English / French) web-based information retrieval system in native PHP**

[Français](#français) · [English](#english)

---

## Français

Mini-projet du module *Resource Retrieval and Information Systems Roles* : un système de recherche pour le service des archives d'une université dont le personnel « perd du temps à retrouver le bon dossier ».

- **Entièrement bilingue.** Un sélecteur **EN | FR** dans l'en-tête bascule tout ce qui est visible : menus, libellés, messages, dates, nombres, **et les documents eux-mêmes** (titre, résumé, mots-clés, texte intégral, signataire, téléchargement). La première visite suit la langue du navigateur ; le choix est ensuite mémorisé. On peut chercher en français ou en anglais.
- **Interface de recherche.** Une barre de recherche, des champs avancés, des filtres (facettes) avec compteurs, le tri, des extraits surlignés (insensibles aux accents), « Vouliez-vous dire… », une page d'aide quand rien n'est trouvé, une page de détail et des documents liés.
- **Moteur de recherche.** Index inversé maison et classement **BM25** avec pondération par champ. Il gère les expressions exactes, l'exclusion, les préfixes, les références et numéros d'inscription exacts, et un thésaurus de synonymes français/anglais.
- **Contrôle d'accès.** Quatre rôles et quatre niveaux d'accès, appliqués *dans* la requête (filtrage de sécurité), plus un journal d'audit.
- **Évaluation.** Cinq requêtes de test avec jugements de pertinence, exécutées dans la langue de l'interface (P@5, R-précision, rappel, MAP, MRR, étapes). S'y ajoutent les statistiques de recherche et de clics, un formulaire d'avis des pairs, la vérification des accès et un rapport imprimable.

PHP 8.1+ natif, sans framework ni Composer. Fonctionne sous **SQLite** (aucune configuration) ou **MySQL/MariaDB** (XAMPP, WAMP, Laragon).

### Démarrage rapide

```bash
cd records-ir
php -S localhost:8000 -t public
```

Ouvrez <http://localhost:8000>. À la première requête, la base est créée et remplie d'environ 500 documents fictifs bilingues.

| Utilisateur | Rôle | Mot de passe |
|---|---|---|
| `manager` | Responsable des archives (voit tout) | `Password123!` |
| `officer` | Agent des archives | `Password123!` |
| `fci.admin` | Personnel de la Faculté d'informatique | `Password123!` |
| `finance` | Personnel des finances | `Password123!` |
| `student` | Étudiant Brian Okello | `Password123!` |

**Avec XAMPP / MySQL :**

1. Copiez le dossier `records-ir` dans `htdocs/`.
2. Dans phpMyAdmin, créez une base vide `records_ir` (utf8mb4).
3. Dans `config.php`, mettez `'driver' => 'mysql'` ainsi que votre utilisateur et votre mot de passe MySQL.
4. Ouvrez <http://localhost/records-ir/public/>.

Les extensions `pdo_sqlite` ou `pdo_mysql` doivent être activées ; `mbstring` et `zip` sont recommandées (`zip` sert à extraire le texte des DOCX).

### Commandes utiles

```bash
php bin/install.php --fresh     # supprimer et recharger les données de démonstration
php bin/install.php --reindex   # reconstruire l'index de recherche
php tests/run.php               # 30 vérifications automatiques (moteur, accès, bilinguisme, évaluation)
```

### Recherches à essayer

| Requête | Ce qu'elle montre |
|---|---|
| `relevé de notes Brian Okello` | Classement : ses deux relevés en tête, affichés en français |
| `LU/HR/2023/0042` ou `21/U/04567` | Correspondance exacte d'un identifiant |
| `politiqe exonératoin` | Page sans résultat avec « Vouliez-vous dire politique exonération ? » |
| `"fraude aux examens" -sénat` | Expression exacte + exclusion |
| `tricherie examens` | Synonymes (→ fraude, examination) |
| Cliquer **EN** puis **FR** sur une page de résultats | La même recherche, traduite |
| `relevé de notes` connecté en `student` | Contrôle d'accès : seulement ses propres dossiers |

### Ajouter une langue ou une traduction

- **Texte d'interface :** ajoutez la clé anglaise et sa traduction dans `lang/fr.php` (ou créez `lang/xx.php` et ajoutez la langue dans `src/I18n.php`).
- **Documents :** le formulaire *Ajouter un document* propose, pour chaque champ descriptif, une version anglaise et une version française. Un document saisi dans une seule langue reste affiché dans cette langue, avec une mention.

### Livrables (dossier `docs/fr/`)

| Exigence | Où |
|---|---|
| Mini-projet : système web de recherche pour un service d'archives universitaire | toute l'application |
| Évaluation : **interface de recherche** | `index.php`, `search.php`, `document.php` · `docs/fr/02-maquettes.md` |
| Évaluation : **fonctionnalité de recherche** | `src/Search/*` · `docs/fr/01-justification-conception.md` §1.5–1.6 |
| Évaluation : **rapport d'évaluation** | `evaluation.php` · `docs/fr/04-rapport-evaluation.md` |
| Livrable : maquette des pages de recherche et de résultats | `docs/fr/02-maquettes.md` |
| Livrable : champs de recherche et filtres | `docs/fr/01-justification-conception.md` §1.3 · panneau de filtres |
| Livrable : parcours de la requête au document | `docs/fr/03-parcours-utilisateur.md` |
| Justifier : utilisateurs · métadonnées · contrôle d'accès | `docs/fr/01-justification-conception.md` §1.2–1.4 |
| Évaluer : cinq requêtes · compter les étapes · avis des pairs | `docs/fr/04-rapport-evaluation.md` §4.1, §4.6 |

La version anglaise des mêmes documents se trouve dans `docs/`. Les captures d'écran des deux langues sont dans `docs/screenshots/` (préfixe `fr-` pour le français).

---

## English

Mini-project for *Resource Retrieval and Information Systems Roles*: a search system for a university records office whose staff "waste time finding the right file".

- **Fully bilingual.** An **EN | FR** switch in the header changes everything visible: menus, labels, messages, dates, numbers, **and the documents themselves** (title, summary, keywords, full text, signatory, download). The first visit follows the browser's language; the choice is then remembered. Queries work in either language.
- **Search interface.** One search box, advanced fields, filters (facets) with counts, sorting, accent-insensitive highlighted snippets, "did you mean", a helpful no-results page, a document detail page and related documents.
- **Retrieval functionality.** Its own inverted index and **BM25** ranking with field weights. It handles phrase search, exclusion, prefixes, exact reference and registration numbers, and a French/English synonym thesaurus.
- **Access control.** Four roles and four access levels, applied *inside* the search query (security trimming), plus an audit log.
- **Evaluation.** Five test queries with relevance judgements, run in the interface language (P@5, R-precision, recall, MAP, MRR, steps). It also includes search and click analytics, a peer feedback form, an access-control check and a printable report.

Native PHP 8.1+, with no framework and no Composer. It runs on **SQLite** (zero setup) or **MySQL/MariaDB**.

### Quick start

```bash
cd records-ir
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. Sign in as `manager` with password `Password123!`; the other demo accounts are in the table above.

**With XAMPP / MySQL:** copy the folder into `htdocs/`, create an empty `records_ir` database (utf8mb4), set `'driver' => 'mysql'` and your credentials in `config.php`, then open <http://localhost/records-ir/public/>.

`php tests/run.php` runs 30 automated checks. `php bin/install.php --fresh` reloads the demo data, and `--reindex` rebuilds the index.

### Try these searches

| Query | Shows |
|---|---|
| `Brian Okello transcript` | Ranking: his two transcripts first |
| `LU/HR/2023/0042` or `21/U/04567` | Exact identifier match |
| `tarnscript okelo` | No-results page with "Did you mean…" |
| `"examination malpractice" -senate` | Phrase + exclusion |
| `cheating in exams` | Synonym expansion (→ malpractice, examination) |
| Click **FR** then **EN** on a results page | The same search, translated |
| `transcript` signed in as `student` | Access control: only his own records |

### Project structure

```
records-ir/
├── config.php                  configuration (database, upload limits, demo mode)
├── lang/fr.php                 French translations of the interface (English text = key)
├── public/                     web root: index, search, document, download, upload, evaluation, feedback, audit, login
├── src/
│   ├── I18n.php, Doc.php       language selection, translation, language-aware document fields
│   ├── Search/                 Tokenizer (EN+FR), QueryParser, Indexer, SearchEngine (BM25), Highlighter, Synonyms (EN↔FR)
│   ├── AccessControl.php       role/attribute rules (SQL + PHP)
│   ├── Evaluation.php          test queries (EN + FR), metrics, analytics
│   ├── Seeder.php              fictional bilingual demo collection
│   └── …                       App, Auth, Database, Installer, Logger, TextExtractor, Catalog, helpers
├── storage/                    SQLite database and uploaded files (not web-accessible)
├── tests/run.php
└── docs/                       deliverables in English; docs/fr/ in French; screenshots/ in both languages
```

### Mapping to the assignment

| Requirement | Where (English / French) |
|---|---|
| Search interface | `docs/02-wireframes.md` / `docs/fr/02-maquettes.md` |
| Retrieval functionality | `docs/01-design-justification.md` §1.5–1.6 / `docs/fr/01-justification-conception.md` |
| Evaluation report | `docs/04-evaluation-report.md` / `docs/fr/04-rapport-evaluation.md` |
| User flow | `docs/03-user-flow.md` / `docs/fr/03-parcours-utilisateur.md` |
| Users · metadata · access control | `docs/01-design-justification.md` §1.2–1.4 / `docs/fr/01-justification-conception.md` |

All names, numbers and documents in the demo data are invented.
