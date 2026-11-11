# 4. Evaluation report

**System:** LU Records Finder, a bilingual (English/French) web-based information retrieval system for a university records office.
**Collection:** 512 documents of 14 types from 10 departments, each in English and French (fictional, generated with a fixed seed), indexed as 2,692 terms and about 52,700 postings.
**Method:** the three steps of the slide "Evaluate":

1. Try five realistic queries, in each language.
2. Count the steps needed to reach a document.
3. Collect peer feedback.

Two more checks were added: search-log analytics and an access-control check.
Every figure in this report is produced live by the **Evaluation** page (`evaluation.php`, which runs the queries in the interface language) and by `php tests/run.php`. The page can be printed to PDF from its *Print* button.

---

## 4.1 Five realistic test queries

Each query is a real need of one of the personas (see *Design justification*). It is typed exactly as that user would type it, once in English and once in French.
The **relevant set** is fixed *before* running the query, from the metadata (for example "all transcripts whose student is Brian Okello"). It does not depend on what the system returns, and it is the same in both languages.

| # | User | Information need | Query in English | Query in French |
|---|---|---|---|---|
| Q1 | Registry officer | Verify the record of a graduate for an employer | `Brian Okello transcript` | `relevé de notes Brian Okello` |
| Q2 | Admissions officer | Compile the 2024 Computer Science admission letters | `admission letter computer science 2024` + filter | `lettre d'admission informatique 2024` + filter |
| Q3 | Finance officer | Answer a student asking about fee waivers | `fee waiver policy` | `politique exonération des frais` |
| Q4 | Faculty administrator | List every meeting that discussed examination malpractice | `minutes examination malpractice` | `procès-verbal fraude aux examens` |
| Q5 | HR officer | Open a contract quoted by reference in a letter | `LU/HR/2023/0042` | `LU/HR/2023/0042` |

### Results (identical in English and French)

| # | Relevant | Retrieved | 1st relevant at | P@5 | R-precision | Recall@10 | Average precision | Steps |
|---|---|---|---|---|---|---|---|---|
| Q1 | 2 | 330 | rank 1 | 0.40* | 1.00 | 100% | 1.00 | 3 |
| Q2 | 6 | 82 | rank 1 | 1.00 | 1.00 | 100% | 1.00 | 4 |
| Q3 | 3 | 312 | rank 1 | 0.60* | 1.00 | 100% | 1.00 | 3 |
| Q4 | 11 | 344 | rank 1 | 1.00 | 1.00 | 91%** | 1.00 | 3 |
| Q5 | 1 | 1 | rank 1 | 0.20* | 1.00 | 100% | 1.00 | 3 |
| **Mean** | | | | **0.64** | **1.00** | **98%** | **MAP 1.00** | **3.2** |

Mean Reciprocal Rank = 1.00 in both languages. Average search time ≈ 3 ms on a laptop. The results are identical on SQLite and on MySQL/MariaDB.

\* P@5 cannot exceed *(number of relevant documents) / 5*. Q1 has only 2 relevant documents, Q3 has 3 and Q5 has 1, so 0.40, 0.60 and 0.20 are the best possible values. R-precision (precision at rank R, where R is the number of relevant documents) and average precision are the fair measures here.
\*\* Q4 has 11 relevant documents and a page shows 10, so recall@10 can be at most 91%. All 11 are ranked 1 to 11.

### How the measures are defined

| Measure | Meaning |
|---|---|
| **Precision@k** | Share of the first *k* results that are relevant |
| **Recall@k** | Share of all relevant documents found in the first *k* results |
| **R-precision** | Precision at rank R, where R = number of relevant documents |
| **Average precision (AP)** | Mean of the precision values at the rank of each relevant document. It rewards putting *all* relevant documents at the top |
| **MAP** | Mean of AP over the queries |
| **Reciprocal rank** | 1 / rank of the first relevant result |
| **Steps** | Number of user actions: type the query + press Enter + each filter click + each "next page" + open the document |

### Why the ranking works, in both languages

- **Q1.** Name and type words appear in the title and metadata (weights ×3 and ×2). The coordination factor then puts the two documents matching every query word above Brian's other records. In French, `relevé` and `notes` match the French title *Relevé de notes*.
- **Q2.** The *Admission Letter* facet removes everything else in one click. *Computer Science* / *informatique* and *2024* then rank the right cohort first.
- **Q3.** *waiver* and *exonération* are rare in the collection (high idf) and appear in the titles of exactly the relevant documents.
- **Q4.** The relevant minutes contain *malpractice* in English and *fraude aux examens* in French, in their title, keywords or text. The bilingual thesaurus also links `exam ↔ examination ↔ examen` and `cheating ↔ malpractice ↔ fraude ↔ tricherie`.
- **Q5.** Identifiers are indexed as one token (`luhr20230042`) and given an exact-match boost. They work the same in both languages.

### Steps to reach a document

| Path | Actions |
|---|---|
| Typical query (Q1, Q3, Q4, Q5) | type → Enter → open result = **3** |
| With one filter (Q2) | type → Enter → tick *Admission Letter* → open = **4** |
| Average across the tests | **3.2** |
| Changing the language | +1 click, once (the choice is remembered) |

---

## 4.2 Limitations found while testing

The perfect MAP must be read with care, for two reasons:

- **The data is generated.** It comes from consistent templates, so titles and metadata are cleaner than in a real registry, and the French version of each document is a complete translation.
- **The judgements come from metadata.** The system indexes the same metadata.

Harder probe queries show where the system is weaker.

| Probe query | What happened | Cause | Possible improvement |
|---|---|---|---|
| `transcript of Okello` | Transcripts of several different Okellos come first | Ambiguous surname | Ask for the first name or registration number; add a "Student" facet when several students match |
| `exam timetable` / `calendrier des examens` | No timetable exists. The *Annual Leave Schedule* memo (*Calendrier des congés annuels*) comes first | The synonym `timetable ↔ calendrier` raises recall but brings in an unrelated "calendrier": the precision/recall trade-off of query expansion | Weight synonyms lower, or restrict them by document type; add real timetables to the collection |
| `cheating in exams` / `tricherie examens` *(before query expansion)* | Only 1 result | The records say *malpractice* / *fraude* and *examination* / *examens* | **Fixed:** the bilingual thesaurus now returns the malpractice minutes first in both languages |
| Any multi-word query | About 300 documents retrieved (e.g. 330 for Q1) | OR semantics, so nothing relevant is missed. Indexing both languages also lengthens the long tail of weak matches | Acceptable because users rarely go past page 1 and facets narrow the set. An "all words" option or a minimum score could be added |
| Document uploaded in one language | Shown in its original language, with a notice; searchable only with words in that language (plus thesaurus synonyms) | No automatic translation | Staff can paste a translation into the second language field; machine translation could be added later |
| Scanned PDF upload | Findable only by its metadata | No OCR | Run Tesseract OCR at upload time |
| Collections much larger than 10⁴ documents | Response time grows | Ranking runs in PHP | Move the index to MySQL FULLTEXT, Meilisearch or Elasticsearch, and keep access control as a query filter |

---

## 4.3 Search analytics

The system logs every search (query, filters, number of results, time) and every result opened from a results page (position clicked). The Evaluation page turns these logs into indicators:

| Indicator | What it tells the Records Office |
|---|---|
| Zero-result rate and the list of zero-result queries | Missing records, missing synonyms (in either language) or missing metadata |
| Click-through rate | Whether the results page answers the need |
| Average position clicked / share of clicks on result 1 | Ranking quality as experienced by real users (rank 1 is ideal) |
| Average results and response time | Load and performance |
| Most frequent queries | Which records are in demand (candidates for quick links) |

The database ships empty of logs. The figures fill up as soon as people use the system. Run a session with peers and read the page again.

---

## 4.4 Access-control check

Number of active documents each account can find, generated by the Evaluation page. It is the same in both languages, because access control does not depend on the language.

| Account | Public (35) | Internal (87) | Confidential (379) | Restricted (4) | Total |
|---|---|---|---|---|---|
| `manager`, Records Manager | 35 | 87 | 379 | 4 | 505 |
| `officer`, Records Officer | 35 | 87 | 379 | 0 | 501 |
| `fci.admin`, Faculty of Computing staff | 35 | 87 | 42 (own faculty) | 0 | 164 |
| `finance`, Finance staff | 35 | 87 | 60 (own department) | 0 | 182 |
| `student`, Brian Okello | 35 | 0 | 6 (his own records) | 0 | 41 |

*(Seven archived records are excluded from these counts.)*

The automated tests also confirm the following:

- A student searching `transcript` or `relevé de notes` sees only his own transcripts.
- Faculty staff see no HR contracts.
- A restricted document opened by URL returns *not found* for everyone except the Records Manager, and the attempt is written to the audit log.

---

## 4.5 Bilingual quality checks

| Check | Result |
|---|---|
| Every interface string used in the code has a French translation (`lang/fr.php`) | 323 / 323 |
| Browser test: no English interface text left on any French page (12 screens) | passed |
| Every demo document has a French title, summary, keywords, text and author | 512 / 512 |
| Same query in English and French → same relevant documents at the top | 5 / 5 test queries |
| Download of a text record in the chosen language (`…-fr.txt` / `…-en.txt`) | passed |
| Document uploaded in French only, viewed in English → shown in French with a notice | passed |

---

## 4.6 Peer feedback

**How to collect it.** Give each peer one task, Q1 to Q5, in the language of their choice. They complete it while signed in with the matching demo account, then fill in **Give feedback** (*Votre avis*). The form asks:

- which task they tried;
- whether they found the document;
- how many clicks it took;
- five statements rated 1 to 5: easy to use, results relevant, found quickly, filters useful, overall satisfaction;
- a free comment.

The Evaluation page averages the answers and lists the comments.

**Results.** *(Fill in after the peer session. The system does not ship with any feedback, so that no responses are invented.)*

| Statement | Mean (1–5) |
|---|---|
| The system was easy to use | … |
| The results matched what I was looking for | … |
| I found the document quickly | … |
| The filters and sorting helped me narrow the results | … |
| Overall, I would use this system for my work | … |
| Task completed / average clicks reported | …% / … |

Main comments and changes made: …

---

## 4.7 Conclusion

The interface meets the case-study need in both languages. A records officer reaches the right document in about **3 actions**, and on the five test queries the first result was always relevant, whether typed in English or in French (**MRR 1.00, MAP 1.00**). Confidential and restricted records are never exposed to unauthorised roles, not even in counts or snippets.

The main risk in real use is **vocabulary mismatch** between users and records, now in two languages. The design addresses it in three ways:

- the bilingual thesaurus, whose side effects (e.g. *calendrier*) must be watched;
- accent-insensitive matching and spelling suggestions;
- the zero-result log, which shows the Records Office which terms to add.

The next improvements would be OCR for scanned files, translation help for documents uploaded in one language, a "Student" facet to disambiguate common names, and an external search engine once the collection grows beyond tens of thousands of documents.
