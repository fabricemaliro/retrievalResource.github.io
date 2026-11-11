# 1. Design justification

**Case study.** A university records office stores thousands of student and administrative documents. Staff waste time finding the right file.
**Task.** Design a simple information retrieval interface for this organisational information system.
**Solution.** *LU Records Finder*, a **bilingual (English/French)** web-based information retrieval system (native PHP, SQLite or MySQL) for the fictional Lakeview University records office.

The slides ask three justification questions: who the users are, which metadata supports search, and how access is controlled. Sections 1.2–1.4 answer them in that order. Section 1.5 explains how retrieval works, and section 1.6 lists what was left out.

---

## 1.1 The problem in information-systems terms

| Symptom in the records office | Underlying cause | Design response |
|---|---|---|
| Staff open many cabinets or folders before finding a file | Records are filed by **one** dimension (e.g. department), but people look for them by **many** (name, number, date, type) | Every record is described by structured **metadata** and its **full text** is indexed, so it can be found from any of these entry points |
| Two people file the same type of document differently | No controlled vocabulary | Fixed lists for *document type*, *department* and *access level*; generated reference numbers (`LU/DEPT/YEAR/NNNN`) |
| Confidential student data is visible to anyone with access to the shelf or the shared drive | Access is not tied to roles | Role- and attribute-based access control, enforced **inside** the search query |
| Nobody knows who took or viewed a file | No accountability | Audit log of views, downloads, uploads and refused access |
| Searches that fail are invisible | No feedback loop | Search and click logs feed the **Evaluation** page (zero-result queries, click-through, rank clicked) |

The system also fills the four standard roles of an organisational information system:

- **Operational:** staff find and retrieve records quickly.
- **Control:** access control and an audit trail.
- **Decision support:** usage analytics show which records people need and cannot find.
- **Records management:** a retention period is recorded for every document type.

---

## 1.2 Who the users are

The slide asks *"Who searches, and why?"*. Five personas were derived from the case study. Each one maps to a demo account.

| Persona (demo account) | Typical information need | Typical query | Role and access |
|---|---|---|---|
| **Records Manager** (`manager`) | Oversees the whole collection, answers legal requests, audits use | `legal opinion dismissal`, audit log | `admin`: everything, including *restricted* |
| **Records Officer** (`officer`) | Serves daily requests from staff, employers and students; captures new documents | `Brian Okello transcript`, `LU/REG/2024/0005` | `records_officer`: all except *restricted*; can add documents and view the evaluation |
| **Department staff**, e.g. a faculty administrator (`fci.admin`) or a finance officer (`finance`) | Retrieves the records of their own unit and university-wide documents | `minutes examination malpractice`, `fee waiver policy` | `dept_staff`: public + internal + **confidential of their own department only** |
| **Student** (`student`, Brian Okello) | Checks policies and their own records | `fee waiver policy`, `transcript` | `student`: public + **their own records** (matched on registration number) |
| *(External requester: employer, ministry)* | Asks for a verification | — | No account. The request is handled by a Records Officer and kept as *Correspondence* |

**What these users have in common.** They are busy, non-technical and usually know *one exact fact* about the record they need: a name, a registration number, a reference number, a meeting, or a year. The design follows from that:

- **One search box first**, Google-style, with the cursor already in it (`autofocus`; press `/` from any page to return to it).
- **Identifiers work as typed.** `21/U/04567` or `LU/HR/2023/0042` returns the exact record at rank 1, marked *Exact number match*.
- **Spelling mistakes are forgiven.** `tarnscript okelo` gets "Did you mean *transcript okello*?".
- **Everyday words work.** Query expansion links `exams` → *examination*, `cheating` → *malpractice*, `school fees` → *tuition*.
- **Filters come second.** They narrow a result list that is too long. They are not a form users must fill in before they can search.
- **Two languages.** Each user picks English or French with the **EN | FR** switch in the header. The whole site follows the choice: menus, labels, messages, dates, numbers, and the documents themselves. Queries can be typed in either language (see §1.7).

---

## 1.3 Which metadata supports search

The slide asks *"Which fields matter most?"*. Every document carries the fields below. The middle columns show where each field is used: indexed for keyword search (with its ranking weight), as a filter, or as a display or sort key.

| Field | Example | Keyword search (weight) | Filter | Display / sort | Why it matters |
|---|---|---|---|---|---|
| **Reference number** | `LU/FIN/2022/0007` | ✓ exact-match boost | advanced field | result card | Unique identifier quoted in letters and files: the fastest route to a known record |
| **Title** | *Tuition Fee Waiver and Bursary Policy* | ✓ **×3** | advanced field | headline, A–Z sort | Most descriptive short text; words in the title signal what the document is *about* |
| **Document type** | *Academic Transcript* | ✓ ×2 | ✓ facet | badge | Controlled list of 14 types; the most common way to narrow results |
| **Department (owner)** | *Faculty of Computing & IT* | ✓ ×2 | ✓ facet | result card | Organisational context, and the basis of department-level access |
| **Date created** / year | 2 Sep 2024 | — | ✓ facet + date range | newest/oldest sort | Users often remember *when* ("last year's minutes") |
| **Academic year** | 2023/2024 | ✓ ×2 | — | metadata panel | How universities think about time |
| **Student name** | Brian Okello | ✓ ×2 | — | result card | Most student requests start from a name |
| **Registration number** | `21/U/04567` | ✓ exact-match boost | advanced field | result card | Disambiguates students with the same name; links a record to its student for access control |
| **Programme** | BSc Computer Science | ✓ ×2 | — | metadata panel | Needed for cohort requests (e.g. all 2024 Computer Science admissions) |
| **Author / signatory** | Academic Registrar | ✓ ×2 | advanced field | result card | "The memo the Bursar sent" |
| **Keywords** | *fee waiver, bursary, financial aid* | ✓ ×2 | clickable chips | detail page | Adds terms the text may not contain (synonyms, topics) |
| **Summary** | one sentence | ✓ ×1.5 | — | detail page | Better snippet when there is no full text |
| **Full text** | body of the document | ✓ ×1 | phrase search | snippet with highlights | Finds documents by what is said inside them |
| **Language versions** | title, summary, keywords, text, author in English *and* French | ✓ both indexed | — | shown in the user's language | The same record is found and read by English- and French-speaking staff |
| **Access level** | *Confidential* | — | ✓ facet | coloured badge | Tells users why they can, or cannot, see something |
| **Status** | active / archived | — | "Include archived" (officers) | badge | Keeps old records out of the way without deleting them |
| **Retention period** | *Permanent*, *7 years* | — | — | detail page | Records-management obligation (disposal schedule) |
| **File** (name, type, size) | contract.pdf, 240 KB | text extracted from DOCX, TXT and text-based PDFs | — | download | The original record |

**Why weight the fields.** A word in the *title* is stronger evidence of relevance than the same word deep in the body. The indexer therefore multiplies term frequencies by the weights above before ranking (BM25). This is why *"Minutes of the Special Examinations Committee Meeting on Examination Malpractice"* outranks documents that only mention malpractice in passing.

**Controlled vocabularies** (type, department, access level) keep the facet counts meaningful. A free-text "type" field would split "Minutes", "minute", "MoM" and "Meeting notes" into four values.

---

## 1.4 How access is controlled

Students' grades, fee balances, disciplinary cases and staff contracts are personal data. Uganda's Data Protection and Privacy Act, 2019, like the GDPR, requires that only people who need such data can see it. The design combines four mechanisms.

### a) Classification of every document (four access levels)

| Level | Who can see it | Examples |
|---|---|---|
| **Public** | Every signed-in user, including students | Policies, course outlines, graduation lists |
| **Internal** | All staff | Memos, most minutes, financial reports, exam result summaries |
| **Confidential** | Records Office + the **owning department**; a student sees **their own** | Transcripts, admission letters, fee statements, staff contracts |
| **Restricted** | Records Manager only | Disciplinary cases, legal opinions |

### b) Role-based and attribute-based rules

| Role | Public | Internal | Confidential | Restricted |
|---|---|---|---|---|
| Records Manager (`admin`) | ✓ | ✓ | ✓ | ✓ |
| Records Officer | ✓ | ✓ | ✓ | ✗ |
| Department staff | ✓ | ✓ | only if `document.department = user.department` | ✗ |
| Student | ✓ | ✗ | only if `document.student_no = user.student_no` | ✗ |

### c) Security trimming: enforced in the query, not just in the interface

The same rule is applied twice (see `src/AccessControl.php`):

1. **As a SQL condition inside the search query.** A document the user may not open never enters the candidate set. It does not appear in the results, the facet counts, the "related documents" or the spelling suggestions. Hiding only the *Open* button would still leak information: a result titled *"Disciplinary Case File — Brian Okello"* is itself confidential.
2. **As a check before opening or downloading** (`document.php`, `download.php`). This blocks anyone who guesses a URL such as `document.php?id=123`. The answer is the same *"does not exist or you are not authorised"* message whether the file exists or not, so restricted files cannot be discovered by probing.

The Evaluation page checks the rules against the live data. Section 4 of the evaluation report shows that only the manager sees the 4 restricted records, that Finance sees 60 of the 379 confidential records, and that the student sees 6 confidential records, all of them his own.

### d) Supporting controls

- **Authentication.** Passwords are stored as hashes (`password_hash`). The session ID is regenerated at login. Sign-in is locked for 60 s after 5 failed attempts.
- **Form protection.** Every POST form carries a CSRF token. All output is HTML-escaped and every query is prepared, against XSS and SQL injection.
- **Uploads.** Files are checked against a whitelist of extensions and a size limit, stored outside the web root under random names, and served only through `download.php` after the access check.
- **Least privilege when capturing records.** Records Officers cannot classify a document as *Restricted*; only the Manager can.
- **Audit trail.** Sign-ins, failed sign-ins, views, downloads, uploads, re-indexing and *refused* access are all recorded. Each document's detail page shows its access history to the Manager.

---

## 1.5 How retrieval works

```
            INDEXING (when a document is added)                      QUERYING (each search)
 document ──► fields ──► normalise ──► tokenise ──► remove     query ──► parse ("phrases", -exclude, prefix*, IDs)
 (title, keywords,      (lower case,   (words +    stop words           │
  metadata, summary,     no accents)    IDs)       + stem               ▼
  text)                                               │           same analysis + synonym expansion
                                                      ▼                 │
                                     INVERTED INDEX  terms(term, df)  ◄─┘ look-up
                                                     postings(term, doc, weighted tf)
                                                      │
                                                      ▼
                         candidates  ∩  access-control condition  ∩  filters (SQL)
                                                      │
                                                      ▼
                     BM25 score × coordination (share of query words matched) + exact-ID boost
                                                      │
                                                      ▼
                         facet counts → facet filters → sort → page of 10 → snippets + highlights
```

- **Inverted index** (tables `terms` and `postings`). For every term, it lists the documents containing it and a field-weighted frequency. This is how search engines avoid reading every document at query time.
- **BM25** (`k1 = 1.2`, `b = 0.75`), the standard ranking function of modern search engines. A query word scores more when it is frequent in the document and rare in the collection (*idf*), with a correction for document length.
- **Coordination factor.** A document matching all the query words beats one matching a single word many times.
- **Facets** are counted on the full, security-trimmed result set. Each facet is counted with the *other* facets applied, so users always see how many documents each option would return.
- **Snippets.** The engine picks the 280-character window that contains the most distinct query words, and highlights the matches (`<mark>`).
- **Query expansion** uses a small thesaurus (`src/Search/Synonyms.php`) to bridge user vocabulary and record vocabulary. Synonyms count with weight 0.8.
- **Spelling suggestions** propose, for unknown or rare words, the indexed word with the smallest edit distance (Levenshtein).

---

## 1.6 Bilingual interface and documents (English / French)

- **Choosing the language.** A **EN | FR** switch appears in the header of every page, including the sign-in page. The first visit follows the browser's preferred language. The choice is then kept in the session and in a cookie for a year. Switching keeps the current page, query and filters.
- **The interface.** The English text is the translation key (`__('Search')`); the French translations are in `lang/fr.php`, a single file that can be extended to a third language. A script checks that every key used in the code has a translation, and a browser test fails if any English interface text remains on a French page. Dates (`2 sept. 2024`), numbers (`1 805 000 UGX`, `3,88`) and file sizes (`Mo`) follow French conventions.
- **The documents.** Every record stores its descriptive fields in both languages (`title`/`title_fr`, `description`/`description_fr`, `keywords`/`keywords_fr`, `content`/`content_fr`, `author`/`author_fr`). Controlled vocabularies (types, departments, programmes, access levels, retention periods) are translated through the dictionary. A document is displayed, highlighted, related and **downloaded** in the user's language.
- **Documents in one language only.** A file uploaded in one language is shown in that language, with the notice *"This document is only available in French"* and an *"In French only"* badge in the results. The capture form has an English and a French field for each descriptive element, so staff can add a translation.
- **Bilingual retrieval.** Both language versions go into the same inverted index. The tokenizer knows English and French stop words, removes accents (`relevé` = `releve`) and handles French plurals (`journaux` → `journal`). The thesaurus links the two vocabularies (`transcript ↔ relevé ↔ notes`, `malpractice ↔ fraude`, `waiver ↔ exonération`). As a result, `relevé de notes Brian Okello` and `Brian Okello transcript` return the same documents, and highlighting ignores accents.
- **Original files are not translated.** An uploaded PDF or DOCX file is served exactly as it was uploaded. Only the text records and the metadata are exported in the chosen language.

## 1.7 Scope and assumptions

- **No OCR.** Scanned PDFs are stored and downloadable, but they are searchable only through their metadata and any text pasted in at capture. Text is extracted from DOCX, TXT and text-based PDFs.
- **Demo data only.** The data set (about 500 documents, 88 students) is generated and fictional. The random seed is fixed, so results are reproducible.
- **Scale.** Ranking runs in PHP, which is comfortable up to tens of thousands of documents. Beyond that, the same design would sit on a dedicated engine (MySQL FULLTEXT, Meilisearch, Elasticsearch or OpenSearch). Access control would then be applied as a filter on the engine query.
- **Single sign-on.** In production, accounts would come from the university's directory (LDAP or Microsoft Entra ID) rather than a local table.
