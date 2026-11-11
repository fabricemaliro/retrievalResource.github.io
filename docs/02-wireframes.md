# 2. Wireframes of the search and results pages

These low-fidelity wireframes come first. Each page then links to a screenshot of the implemented version (`docs/screenshots/`).
The slide's wireframe checklist is covered as follows:

| Checklist item | Where |
|---|---|
| Search box and button | W1 (home), repeated at the top of W2 |
| Filters and sorting | W2: left panel (type, department, year, access level, date range, specific fields) and the *Sort by* menu |
| Results list with snippets | W2: ranked cards with highlighted snippet, badges and metadata line |
| Document detail page | W4 |
| *What happens when nothing is found?* | W3 |

---

## W1 — Search home

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ [LU] Records Finder   Search  Add document  Evaluation  Audit log  Feedback  [EN|FR] ROLE│
├──────────────────────────────────────────────────────────────────────────────────────────┤
│                         SEARCH 505 RECORDS AVAILABLE TO YOU                              │
│                            What are you looking for?                                     │
│     ┌───────────────────────────────────────────────────────────────┐ ┌──────────┐       │
│     │ 🔍 Name, registration no., reference no. or keywords   |      │ │  Search  │       │
│     └───────────────────────────────────────────────────────────────┘ └──────────┘       │
│     ▸ Advanced search  (reference no. · reg. no. · title · author · type · dept · dates)  │
│     Tips: "exact phrase" · -word · admis* · LU/HR/2023/0042                              │
│                                                                                          │
│ ┌─ Browse by document type ───────────────────────────┐ ┌─ Your recent searches ────────┐ │
│ │ [Academic Transcript 140] [Admission Letter  82]    │ │ • fee waiver policy           │ │
│ │ [Student File       57]   [Fee Statement     55]    │ │ • Brian Okello transcript     │ │
│ │ [Meeting Minutes    40]   [Memo              30] …  │ ├─ What your role can see ──────┤ │
│ │  (only types and counts the user may access)        │ │ PUBLIC  ✓  INTERNAL ✓         │ │
│ └─────────────────────────────────────────────────────┘ │ CONFIDENTIAL ✓  RESTRICTED ✗  │ │
│                                                         └───────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Design decisions**

- **One dominant search box**, with the cursor already in it. The most common task needs no other control.
- **The placeholder teaches what can be searched**: names, numbers, keywords. The tips line teaches the query syntax without a help page.
- **Browse tiles** support users who prefer to recognise rather than recall, and they double as a map of the collection.
- **"What your role can see"** sets expectations before searching, so a missing confidential file is not taken for a broken system.
- **The EN | FR switch** sits in the header of every page and switches the whole site, documents included, without losing the current search.

Implemented: [`screenshots/02-home.png`](screenshots/02-home.png)

---

## W2 — Results page

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ ┌─────────────────────────────────────────────────────────────────────────┐ ┌──────────┐ │
│ │ 🔍 admission letter computer science 2024                               │ │  Search  │ │
│ └─────────────────────────────────────────────────────────────────────────┘ └──────────┘ │
│ ┌─ Refine results ──── Clear all ┐  82 documents for "admission letter…" · 3 ms  Sort ▾  │
│ │ DOCUMENT TYPE                  │  [Admission Letter ×]   ← active filters (removable)  │
│ │ ☐ Academic Transcript    140   │ ┌────────────────────────────────────────────────────┐│
│ │ ☑ Admission Letter        82   │ │ Admission Letter  CONFIDENTIAL      LU/ADM/2024/0103││
│ │ ☐ Student File            57   │ │ Admission Letter — Harriet Auma (BSc Computer Sci.)││
│ │   Show all 14                  │ │ …admitted to the Bachelor of Science in ▓Computer▓  ││
│ │ DEPARTMENT                     │ │ ▓Science▓ … for the ▓2024▓/2025 academic year…     ││
│ │ ☐ Admissions Office       82   │ │ Admissions Office · 12 Aug 2024 · Harriet Auma ·   ││
│ │ YEAR CREATED                   │ │ 24/U/01234                                         ││
│ │ ☐ 2025  11   ☐ 2024  18 …      │ └────────────────────────────────────────────────────┘│
│ │ ACCESS LEVEL                   │ ┌────────────────────────────────────────────────────┐│
│ │ ☐ Confidential            82   │ │ … next result …                                    ││
│ │ DATE CREATED  [from] [to]      │ └────────────────────────────────────────────────────┘│
│ │ ▸ Search in a specific field   │                                                       │
│ │ ☐ Include archived records     │           [1] 2  3  4  Next →                         │
│ │ [ Apply filters ]              │                                                       │
│ └────────────────────────────────┘                                                       │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Design decisions**

- **Facets show counts** and are calculated after access control. Each facet is counted with the other filters applied, so a user can never pick a filter that leads to zero results.
- **Ticking a filter applies it at once** (with JavaScript; an *Apply* button works without it). Active filters appear as removable chips above the results.
- **Each result card answers "is this the one?" without opening it.** The type and access badges come first, then the reference number, a highlighted title, the passage that best matches the query, and the department, date and student.
- **Sort options:** relevance (default when there is a query), newest (default when browsing), oldest, title A–Z.
- **On a phone,** the filters collapse into a *Filters* button ([`screenshots/12-mobile-results.png`](screenshots/12-mobile-results.png)).

Implemented: [`screenshots/03-results.png`](screenshots/03-results.png) (query) · [`screenshots/05-filtered.png`](screenshots/05-filtered.png) (facet + sort)

---

## W3 — No results

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ 🔍 tarnscript okelo                                                        [ Search ]     │
│ ┌ Refine ┐   No documents found                                                          │
│ │ dates  │   ┌───────────────────────────────────────────────────────────────────────┐   │
│ │ fields │   │ Did you mean  transcript okello ?                                     │   │
│ └────────┘   └───────────────────────────────────────────────────────────────────────┘   │
│              ┌─ Nothing matches "tarnscript okelo" ──────────────────────────────────┐   │
│              │ • Try the suggested spelling: transcript okello                        │   │
│              │ • Search again without filters (shown only if filters are active)     │   │
│              │ • Use fewer, more general words                                        │   │
│              │ • Search by reference no. (LU/REG/2024/0005) or reg. no. (21/U/04567)  │   │
│              │ • Use admis* for word beginnings · Browse by document type             │   │
│              │ Some records are restricted… ask the Records Office (mailto)           │   │
│              └────────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Design decisions.** A dead end is the worst outcome of a search, so the page always offers a next step:

- a one-click spelling correction;
- removal of filters;
- reformulation advice;
- browsing by type;
- a human contact, as the last resort.

The page also explains that restricted records are not shown, without confirming whether any exist. Every zero-result query is logged and listed on the Evaluation page, so the Records Office can add synonyms or metadata.

Implemented: [`screenshots/06-no-results.png`](screenshots/06-no-results.png)

---

## W4 — Document detail

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ ← Back to results / Academic Transcript                                                   │
│ ┌──────────────────────────────────────────────────────┐ ┌─────────────────────────────┐ │
│ │ Academic Transcript  CONFIDENTIAL     LU/FCI/2024/0012│ │ [   Download document    ]  │ │
│ │ Academic ▓Transcript▓ 2023/2024 — ▓Brian Okello▓     │ │ Downloads are audited       │ │
│ │ Summary sentence …                                   │ ├─ Metadata ──────────────────┤ │
│ │ [transcript] [results] [CGPA]   ← keyword chips      │ │ Reference no. · Type · Dept │ │
│ │ ──────────────────────────────────────────────────── │ │ Date · Academic year        │ │
│ │ Full text with the search terms highlighted …        │ │ Student · Programme · Author│ │
│ │                                                      │ │ Retention · Status · File   │ │
│ │                                                      │ │ Access: CONFIDENTIAL + rule │ │
│ │                                                      │ ├─ Related documents ─────────┤ │
│ │                                                      │ │ • Transcript 2022/2023 …    │ │
│ │                                                      │ ├─ Access history (Manager) ──┤ │
│ │                                                      │ │ Margaret Nankya viewed …    │ │
│ └──────────────────────────────────────────────────────┘ └─────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**Design decisions**

- **"Back to results" keeps the query, filters, sort and page,** so comparing several candidates costs one click each.
- **The search terms stay highlighted** in the full text, so users can see at once why the document matched.
- **"Related documents"** (more-like-this on the document's most characteristic terms) reaches the rest of a student's file, or the other versions of a policy, without a new search.
- **The metadata panel shows the access rule and the retention period.** Records staff need both.

Implemented: [`screenshots/04-document.png`](screenshots/04-document.png)

---

## Other screens

| Screen | Screenshot |
|---|---|
| Sign-in (with the demo accounts) | [`01-login.png`](screenshots/01-login.png) |
| Add a document (records capture form with metadata and access level) | [`07-upload.png`](screenshots/07-upload.png) |
| Peer feedback questionnaire | [`08-feedback.png`](screenshots/08-feedback.png) |
| Evaluation report | [`09-evaluation.png`](screenshots/09-evaluation.png) |
| Audit log | [`10-audit.png`](screenshots/10-audit.png) |
| The same search seen by the student (security trimming) | [`11-student-view.png`](screenshots/11-student-view.png) |
| **French version of every screen** | [`fr-01-login.png`](screenshots/fr-01-login.png) … [`fr-12-mobile-results.png`](screenshots/fr-12-mobile-results.png) |
