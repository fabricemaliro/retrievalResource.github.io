<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Demo data: a fictional university records office (~500 documents), every document in English AND French.
 * Generation is deterministic (fixed random seed) so the evaluation results are reproducible.
 * All names, numbers and documents are invented.
 */
final class Seeder
{
    private const DEPARTMENTS = [
        'REG' => 'Academic Registry',
        'ADM' => 'Admissions Office',
        'EXM' => 'Examinations Office',
        'FIN' => 'Finance & Bursary',
        'HR' => 'Human Resources',
        'LEG' => 'Legal & Compliance',
        'FCI' => 'Faculty of Computing & IT',
        'FBM' => 'Faculty of Business & Management',
        'FED' => 'Faculty of Education',
        'FHS' => 'Faculty of Health Sciences',
    ];

    private const PROGRAMMES = [
        'BCS' => ['Bachelor of Science in Computer Science', 'FCI', 3],
        'BIT' => ['Bachelor of Information Technology', 'FCI', 3],
        'BSE' => ['Bachelor of Science in Software Engineering', 'FCI', 4],
        'BBA' => ['Bachelor of Business Administration', 'FBM', 3],
        'BCOM' => ['Bachelor of Commerce (Accounting)', 'FBM', 3],
        'BHRM' => ['Bachelor of Human Resource Management', 'FBM', 3],
        'BEDA' => ['Bachelor of Education (Arts)', 'FED', 3],
        'BEDS' => ['Bachelor of Education (Science)', 'FED', 3],
        'BNS' => ['Bachelor of Nursing Science', 'FHS', 4],
        'BPH' => ['Bachelor of Science in Public Health', 'FHS', 3],
    ];

    /** [code, English name, French name] */
    private const COURSES = [
        'FCI' => [
            ['CSC 1101', 'Introduction to Programming', 'Introduction à la programmation'], ['CSC 1102', 'Computer Organisation', 'Architecture des ordinateurs'],
            ['CSC 1203', 'Discrete Mathematics', 'Mathématiques discrètes'], ['CSC 1204', 'Web Design and Development', 'Conception et développement web'],
            ['CSC 2101', 'Data Structures and Algorithms', 'Structures de données et algorithmes'], ['CSC 2102', 'Database Systems', 'Systèmes de bases de données'],
            ['CSC 2203', 'Operating Systems', "Systèmes d'exploitation"], ['CSC 2204', 'Object-Oriented Programming', 'Programmation orientée objet'],
            ['CSC 3101', 'Software Engineering', 'Génie logiciel'], ['CSC 3102', 'Computer Networks', 'Réseaux informatiques'],
            ['CSC 3203', 'Information Retrieval Systems', "Systèmes de recherche d'information"], ['CSC 3204', 'Artificial Intelligence', 'Intelligence artificielle'],
        ],
        'FBM' => [
            ['BAM 1101', 'Principles of Management', 'Principes de management'], ['BAM 1102', 'Business Mathematics', 'Mathématiques financières'],
            ['ACC 1201', 'Financial Accounting I', 'Comptabilité financière I'], ['ECO 1202', 'Microeconomics', 'Microéconomie'],
            ['BAM 2101', 'Organisational Behaviour', 'Comportement organisationnel'], ['ACC 2102', 'Cost Accounting', 'Comptabilité analytique'],
            ['BAM 2203', 'Marketing Management', 'Gestion du marketing'], ['HRM 2204', 'Human Resource Planning', 'Planification des ressources humaines'],
            ['BAM 3101', 'Strategic Management', 'Management stratégique'], ['ACC 3102', 'Auditing and Assurance', 'Audit et assurance'],
            ['BAM 3203', 'Entrepreneurship', 'Entrepreneuriat'], ['FIN 3204', 'Corporate Finance', "Finance d'entreprise"],
        ],
        'FED' => [
            ['EDU 1101', 'Foundations of Education', "Fondements de l'éducation"], ['EDU 1102', 'Educational Psychology', "Psychologie de l'éducation"],
            ['EDU 1203', 'Curriculum Studies', 'Étude des programmes scolaires'], ['EDU 1204', 'Teaching Methods', "Méthodes d'enseignement"],
            ['EDU 2101', 'Assessment and Evaluation', 'Mesure et évaluation'], ['EDU 2102', 'Sociology of Education', "Sociologie de l'éducation"],
            ['EDU 2203', 'Educational Technology', 'Technologies éducatives'], ['EDU 2204', 'Guidance and Counselling', 'Orientation et conseil'],
            ['EDU 3101', 'School Practice', 'Stage pratique en établissement'], ['EDU 3102', 'Research Methods in Education', 'Méthodes de recherche en éducation'],
            ['EDU 3203', 'Educational Management', 'Gestion des établissements scolaires'], ['EDU 3204', 'Inclusive Education', 'Éducation inclusive'],
        ],
        'FHS' => [
            ['NUR 1101', 'Human Anatomy', 'Anatomie humaine'], ['NUR 1102', 'Physiology', 'Physiologie'],
            ['NUR 1203', 'Fundamentals of Nursing', 'Fondements des soins infirmiers'], ['PHC 1204', 'Introduction to Public Health', 'Introduction à la santé publique'],
            ['NUR 2101', 'Medical-Surgical Nursing', 'Soins infirmiers médico-chirurgicaux'], ['PHC 2102', 'Epidemiology', 'Épidémiologie'],
            ['NUR 2203', 'Pharmacology', 'Pharmacologie'], ['PHC 2204', 'Biostatistics', 'Biostatistique'],
            ['NUR 3101', 'Maternal and Child Health', 'Santé maternelle et infantile'], ['PHC 3102', 'Health Policy and Management', 'Politiques et gestion de la santé'],
            ['NUR 3203', 'Community Health Nursing', 'Soins infirmiers communautaires'], ['PHC 3204', 'Environmental Health', 'Santé environnementale'],
        ],
    ];

    private const FIRST = [
        'Grace', 'Joseph', 'Sarah', 'Daniel', 'Esther', 'Isaac', 'Ruth', 'Samuel', 'Mercy', 'David', 'Faith', 'Peter', 'Joan',
        'Moses', 'Agnes', 'Patrick', 'Doreen', 'Ivan', 'Winnie', 'Kevin', 'Sharon', 'Allan', 'Florence', 'Ronald', 'Christine',
        'Emmanuel', 'Annet', 'Denis', 'Harriet', 'Fatuma', 'Abdul', 'Aisha', 'Hassan', 'Brian', 'Lydia', 'Timothy', 'Prossy',
        'Collins', 'Juliet', 'Martin', 'Naomi', 'Edgar', 'Rebecca', 'Godfrey', 'Shamim',
    ];
    private const FEMALE = ['Grace', 'Sarah', 'Esther', 'Ruth', 'Mercy', 'Faith', 'Joan', 'Agnes', 'Doreen', 'Winnie', 'Sharon', 'Florence',
        'Christine', 'Annet', 'Harriet', 'Fatuma', 'Aisha', 'Lydia', 'Prossy', 'Juliet', 'Naomi', 'Rebecca', 'Shamim'];
    private const LAST = [
        'Okello', 'Namutebi', 'Mugisha', 'Achieng', 'Wanjiru', 'Ssempala', 'Nakato', 'Otieno', 'Kato', 'Atim', 'Byaruhanga',
        'Akello', 'Mwangi', 'Nansubuga', 'Tumusiime', 'Opio', 'Kirabo', 'Ouma', 'Nalwanga', 'Ochieng', 'Kamau', 'Babirye',
        'Lubega', 'Auma', 'Mukasa', 'Nabirye', 'Odongo', 'Asiimwe', 'Kiprotich', 'Nambi',
    ];

    private const DISTRICTS = ['Kampala', 'Wakiso', 'Mukono', 'Jinja', 'Mbarara', 'Gulu', 'Lira', 'Mbale', 'Masaka', 'Fort Portal', 'Arua', 'Soroti', 'Kisumu', 'Nairobi'];
    private const GRADES = [['A', 5.0], ['B+', 4.5], ['B', 4.0], ['B', 4.0], ['C+', 3.5], ['C+', 3.5], ['C', 3.0], ['D+', 2.5], ['A', 5.0], ['B+', 4.5]];
    private const MONTHS = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
    ];
    private const SCHEMES = [
        'gov' => ['Government sponsorship', "bourse du gouvernement", "au titre d'une bourse du gouvernement"],
        'priv' => ['Private sponsorship', 'financement privé', "au titre d'un financement privé"],
    ];
    private const TITLES_FR = ['Dr.' => 'Dr', 'Prof.' => 'Pr', 'Mr.' => 'M.', 'Ms.' => 'Mme', 'Mrs.' => 'Mme'];

    private array $dept = [];
    private array $seq = [];
    private array $students = [];
    private array $usedNames = [];
    private array $usedNumbers = [];
    private int $count = 0;

    private function __construct(private readonly PDO $db)
    {
    }

    public static function run(PDO $db): array
    {
        mt_srand(2026);
        $seeder = new self($db);
        $seeder->departments();
        $users = $seeder->users();
        $seeder->coreDocuments();
        $seeder->studentDocuments();
        $seeder->institutionalDocuments();
        return ['departments' => count(self::DEPARTMENTS), 'users' => $users, 'documents' => $seeder->count];
    }

    // ------------------------------------------------------------------ setup

    private function departments(): void
    {
        $stmt = $this->db->prepare('INSERT INTO departments (code, name) VALUES (?, ?)');
        foreach (self::DEPARTMENTS as $code => $name) {
            $stmt->execute([$code, $name]);
            $this->dept[$code] = (int) $this->db->lastInsertId();
        }
    }

    private function users(): int
    {
        $users = [
            ['manager', 'Margaret Nankya', 'admin', 'REG', null],
            ['officer', 'Paul Ssekandi', 'records_officer', 'REG', null],
            ['fci.admin', 'Dr. Ruth Akello', 'dept_staff', 'FCI', null],
            ['finance', 'Joseph Mugerwa', 'dept_staff', 'FIN', null],
            ['student', 'Brian Okello', 'student', 'FCI', '21/U/04567'],
        ];
        $hash = password_hash('Password123!', PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, full_name, password_hash, role, department_id, student_no, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($users as [$username, $name, $role, $dept, $sno]) {
            $stmt->execute([$username, $name, $hash, $role, $this->dept[$dept], $sno, date('Y-m-d H:i:s')]);
        }
        return count($users);
    }

    // ------------------------------------------------------------------ helpers

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    private static function fr(string $english): string
    {
        return I18n::in('fr', $english);
    }

    private static function deptFr(string $code): string
    {
        return self::fr(self::DEPARTMENTS[$code]);
    }

    private static function progFr(string $key): string
    {
        return self::fr(self::PROGRAMMES[$key][0]);
    }

    private function ref(string $code, int $year): string
    {
        $key = "$code/$year";
        $this->seq[$key] = ($this->seq[$key] ?? 99) + 1;
        return sprintf('LU/%s/%d/%04d', $code, $year, $this->seq[$key]);
    }

    private function date(int $year, int $fromMonth = 1, int $toMonth = 12): string
    {
        if ($year >= 2026) {
            $toMonth = min($toMonth, 10);
            $fromMonth = min($fromMonth, $toMonth);
        }
        return sprintf('%04d-%02d-%02d', $year, mt_rand($fromMonth, $toMonth), mt_rand(1, 28));
    }

    private static function longDate(string $date, string $lang): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return $lang === 'fr'
            ? ($d === 1 ? '1er' : $d) . ' ' . self::MONTHS['fr'][$m - 1] . " $y"
            : $d . ' ' . self::MONTHS['en'][$m - 1] . " $y";
    }

    private static function ugx(int|float $amount, string $lang): string
    {
        return $lang === 'fr' ? number_format($amount, 0, ',', ' ') . ' UGX' : 'UGX ' . number_format($amount);
    }

    private static function num(float $value, string $lang): string
    {
        $s = (string) $value;
        return $lang === 'fr' ? str_replace('.', ',', $s) : $s;
    }

    private static function ordinal(int $n, string $lang): string
    {
        if ($lang === 'fr') {
            return $n === 1 ? '1re' : $n . 'e';
        }
        return $n . (($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    }

    /** @return array{0:string, 1:string} [English, French] person name, optionally with a title */
    private function person(bool $withTitle = false): array
    {
        $first = $this->pick(self::FIRST);
        $name = $first . ' ' . $this->pick(self::LAST);
        if (!$withTitle) {
            return [$name, $name];
        }
        $civil = in_array($first, self::FEMALE, true) ? $this->pick(['Ms.', 'Mrs.']) : 'Mr.';
        $title = $this->pick(['Dr.', 'Prof.', 'Dr.', $civil, $civil]);
        return ["$title $name", self::TITLES_FR[$title] . " $name"];
    }

    private function save(array $d): int
    {
        $row = array_merge([
            'programme' => null, 'student_no' => null, 'student_name' => null, 'author' => null, 'author_fr' => null,
            'academic_year' => null, 'keywords' => '', 'keywords_fr' => '', 'description' => '', 'description_fr' => '',
            'status' => 'active', 'language' => 'both',
        ], $d);
        $row['department_id'] = $this->dept[$row['dept']];
        unset($row['dept']);
        $row['doc_year'] = (int) substr($row['date_created'], 0, 4);
        $row['retention_period'] = Catalog::RETENTION[$row['document_type']];
        $row['created_at'] = $row['date_created'] . ' 09:00:00';
        $row['updated_at'] = $row['created_at'];
        if ($row['doc_year'] <= 2021 && in_array($row['document_type'], ['memo', 'correspondence', 'financial_report'], true) && $this->chance(0.35)) {
            $row['status'] = 'archived';
        }
        $columns = array_keys($row);
        $this->db->prepare('INSERT INTO documents (' . implode(', ', $columns) . ') VALUES (' . Database::placeholders($columns) . ')')
            ->execute(array_values($row));
        $this->count++;
        return (int) $this->db->lastInsertId();
    }

    // ------------------------------------------------------------------ student records

    private function newStudent(?int $entry = null, ?string $prog = null): array
    {
        do {
            $first = $this->pick(self::FIRST);
            $last = $this->pick(self::LAST);
        } while (isset($this->usedNames["$first $last"]));
        $this->usedNames["$first $last"] = true;

        $entry ??= mt_rand(2020, 2025);
        do {
            $sno = sprintf('%02d/U/%05d', $entry % 100, mt_rand(1000, 29999));
        } while (isset($this->usedNumbers[$sno]));
        $this->usedNumbers[$sno] = true;

        return [
            'first' => $first, 'last' => $last, 'name' => "$first $last", 'entry' => $entry,
            'prog' => $prog ?? $this->pick(array_keys(self::PROGRAMMES)), 'sno' => $sno,
            'female' => in_array($first, self::FEMALE, true),
            'district' => $this->pick(self::DISTRICTS),
            'scheme' => $this->chance(0.3) ? 'gov' : 'priv',
        ];
    }

    private function studentDocuments(): void
    {
        for ($i = 0; $i < 84; $i++) {
            $this->students[] = $this->newStudent();
        }
        for ($i = 0; $i < 4; $i++) {                 // guarantee a 2024 Computer Science intake
            $this->students[] = $this->newStudent(2024, 'BCS');
        }

        foreach ($this->students as $s) {
            if ($this->chance(0.9)) {
                $this->admissionLetter($s);
            }
            if ($this->chance(0.7)) {
                $this->studentFile($s);
            }
            $duration = self::PROGRAMMES[$s['prog']][2];
            $lastYear = min($s['entry'] + $duration - 1, 2025);
            for ($y = max($s['entry'], $lastYear - 1); $y <= $lastYear; $y++) {
                if ($y < 2025 || $this->chance(0.5)) {
                    $this->transcript($s, $y);
                }
            }
            if ($this->chance(0.6)) {
                $this->feeStatement($s, mt_rand($s['entry'], min($lastYear, 2025)));
            }
            if ($this->chance(0.05)) {
                $this->disciplinary($s);
            }
            if ($this->chance(0.12) && $s['entry'] <= 2022) {
                $this->verificationRequest($s);
            }
        }
    }

    private function admissionLetter(array $s, ?string $ref = null, ?string $date = null): void
    {
        [$programme, $faculty, $duration] = self::PROGRAMMES[$s['prog']];
        $progFr = self::progFr($s['prog']);
        $date ??= $this->date($s['entry'], 7, 9);
        $y = $s['entry'];
        $ay = "$y/" . ($y + 1);
        $fee = mt_rand(1200, 2100) * 1000;
        $mode = $this->pick([['Day', 'de jour'], ['Evening', 'du soir'], ['Weekend', 'de week-end']]);
        $orientation = "$y-08-" . mt_rand(10, 25);
        [$schemeEn, $schemeShortFr, $schemeFr] = self::SCHEMES[$s['scheme']];
        $dear = $s['female'] ? 'Chère' : 'Cher';
        $admis = $s['female'] ? 'admise' : 'admis';

        $this->save([
            'reference_no' => $ref ?? $this->ref('ADM', $y),
            'title' => "Admission Letter — {$s['name']} ({$programme})",
            'title_fr' => "Lettre d'admission — {$s['name']} ({$progFr})",
            'document_type' => 'admission_letter', 'dept' => 'ADM',
            'programme' => $programme, 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => 'Dr. Stephen Wasswa, Academic Registrar', 'author_fr' => 'Dr Stephen Wasswa, Registraire académique',
            'date_created' => $date, 'academic_year' => $ay,
            'keywords' => "admission, offer letter, {$y} intake, " . strtolower($schemeEn),
            'keywords_fr' => "admission, lettre d'offre, promotion {$y}, {$schemeShortFr}",
            'description' => "Offer of admission to {$s['name']} for the {$programme}, {$ay} intake ({$schemeEn}).",
            'description_fr' => "Offre d'admission à {$s['name']} en {$progFr}, promotion {$ay} ({$schemeShortFr}).",
            'content' => "Dear {$s['first']} {$s['last']},\n\nRE: OFFER OF ADMISSION — {$programme}\n\n"
                . "We are pleased to inform you that you have been admitted to the {$programme} in the " . self::DEPARTMENTS[$faculty]
                . " for the {$ay} academic year under {$schemeEn}. Your registration number is {$s['sno']}.\n\n"
                . "The programme runs for {$duration} years on a {$mode[0]} basis. You are required to report for orientation on "
                . self::longDate($orientation, 'en') . " and to complete registration within two weeks of the start of the semester.\n\n"
                . 'Tuition for the first year is ' . self::ugx($fee, 'en') . ' per semester, payable through the university bank account before registration. '
                . "Bring the originals of your academic certificates (UCE, UACE or equivalent), your national identity card and two passport photographs.\n\n"
                . "This admission is subject to verification of your academic documents. Congratulations on your admission.\n\nDr. Stephen Wasswa, Academic Registrar",
            'content_fr' => "{$dear} {$s['first']} {$s['last']},\n\nOBJET : OFFRE D'ADMISSION — {$progFr}\n\n"
                . "Nous avons le plaisir de vous informer que vous êtes {$admis} en {$progFr} au sein de la " . self::deptFr($faculty)
                . " pour l'année universitaire {$ay}, {$schemeFr}. Votre numéro d'inscription est {$s['sno']}.\n\n"
                . "Le programme dure {$duration} ans, en cours {$mode[1]}. Vous devez vous présenter à la séance d'orientation le "
                . self::longDate($orientation, 'fr') . " et finaliser votre inscription dans les deux semaines suivant le début du semestre.\n\n"
                . 'Les frais de scolarité de la première année s\'élèvent à ' . self::ugx($fee, 'fr') . ' par semestre, payables sur le compte bancaire de l\'université avant l\'inscription. '
                . "Veuillez apporter les originaux de vos diplômes (UCE, UACE ou équivalent), votre carte d'identité nationale et deux photos d'identité.\n\n"
                . "Cette admission est soumise à la vérification de vos documents académiques. Félicitations pour votre admission.\n\nDr Stephen Wasswa, Registraire académique",
            'access_level' => 'confidential',
        ]);
    }

    private function studentFile(array $s, ?string $ref = null): void
    {
        [$programme, $faculty] = self::PROGRAMMES[$s['prog']];
        $progFr = self::progFr($s['prog']);
        $date = $this->date($s['entry'], 8, 10);
        $dob = sprintf('%04d-%02d-%02d', $s['entry'] - mt_rand(18, 23), mt_rand(1, 12), mt_rand(1, 28));
        $hall = $this->pick([['Nile Hall', 'Résidence du Nil'], ['Victoria Hall', 'Résidence Victoria'], ['Elgon Hall', 'Résidence Elgon'], ['Rwenzori Hall', 'Résidence Rwenzori'], ['Non-resident', 'Externe']]);
        [$kin] = $this->person();
        $relation = $this->pick([['parent', 'parent'], ['guardian', 'tuteur'], ['sibling', 'frère ou sœur'], ['spouse', 'conjoint']]);
        $kenyan = in_array($s['district'], ['Kisumu', 'Nairobi'], true);
        [$schemeEn, $schemeShortFr] = self::SCHEMES[$s['scheme']];
        $ay = "{$s['entry']}/" . ($s['entry'] + 1);
        $e = $s['female'] ? 'e' : '';

        $this->save([
            'reference_no' => $ref ?? $this->ref('REG', $s['entry']),
            'title' => "Student Registration File — {$s['name']} ({$s['sno']})",
            'title_fr' => "Dossier d'inscription de l'étudiant — {$s['name']} ({$s['sno']})",
            'document_type' => 'student_file', 'dept' => 'REG',
            'programme' => $programme, 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => 'Academic Registry', 'author_fr' => self::deptFr('REG'), 'date_created' => $date, 'academic_year' => $ay,
            'keywords' => 'student file, registration, bio data, next of kin',
            'keywords_fr' => "dossier étudiant, inscription, données personnelles, personne à contacter",
            'description' => "Personal and registration file of {$s['name']}, {$programme}, entry {$s['entry']}.",
            'description_fr' => "Dossier personnel et d'inscription de {$s['name']}, {$progFr}, entrée en {$s['entry']}.",
            'content' => "STUDENT REGISTRATION FILE\n\nName: {$s['name']}\nRegistration number: {$s['sno']}\nGender: " . ($s['female'] ? 'Female' : 'Male')
                . "\nDate of birth: " . self::longDate($dob, 'en') . "\nHome district: {$s['district']}\nNationality: " . ($kenyan ? 'Kenyan' : 'Ugandan')
                . "\nProgramme: {$programme}\nFaculty: " . self::DEPARTMENTS[$faculty] . "\nYear of entry: {$s['entry']}\nSponsorship: {$schemeEn}\n"
                . "Hall of residence: {$hall[0]}\nNext of kin: {$kin} ({$relation[0]})\n\n"
                . "Registration history: the student registered for semester one of {$ay} on " . self::longDate($date, 'en')
                . '. Copies of the admission letter, academic certificates, national identity card and medical examination form are held on this file. '
                . 'Any change of programme, deferment or name change must be recorded here.',
            'content_fr' => "DOSSIER D'INSCRIPTION DE L'ÉTUDIANT\n\nNom : {$s['name']}\nNuméro d'inscription : {$s['sno']}\nSexe : " . ($s['female'] ? 'Féminin' : 'Masculin')
                . "\nDate de naissance : " . self::longDate($dob, 'fr') . "\nDistrict d'origine : {$s['district']}\nNationalité : " . ($kenyan ? "kényane" : 'ougandaise')
                . "\nProgramme : {$progFr}\nFaculté : " . self::deptFr($faculty) . "\nAnnée d'entrée : {$s['entry']}\nFinancement : {$schemeShortFr}\n"
                . "Résidence universitaire : {$hall[1]}\nPersonne à contacter : {$kin} ({$relation[1]})\n\n"
                . "Historique d'inscription : l'étudiant{$e} s'est inscrit{$e} au premier semestre de {$ay} le " . self::longDate($date, 'fr')
                . ". Le dossier contient des copies de la lettre d'admission, des diplômes, de la carte d'identité nationale et du certificat médical. "
                . 'Tout changement de programme, report ou changement de nom doit y être consigné.',
            'access_level' => 'confidential',
        ]);
    }

    private function transcript(array $s, int $year, ?string $ref = null, ?string $date = null): void
    {
        [$programme, $faculty] = self::PROGRAMMES[$s['prog']];
        $progFr = self::progFr($s['prog']);
        $yos = min(4, $year - $s['entry'] + 1);
        $level = array_values(array_filter(self::COURSES[$faculty], static fn ($c) => (int) $c[0][4] === min($yos, 3)));
        shuffle($level);
        $en = $fr = [];
        $points = 0;
        $units = 0;
        foreach (array_slice($level, 0, 4) as [$code, $name, $nameFr]) {
            [$grade, $gp] = $this->pick(self::GRADES);
            $cu = $this->pick([3, 4]);
            $en[] = "{$code} {$name} — {$cu} CU — Grade {$grade} ({$gp})";
            $fr[] = "{$code} {$nameFr} — {$cu} crédits — Note {$grade} (" . self::num($gp, 'fr') . ')';
            $points += $gp * $cu;
            $units += $cu;
        }
        $gpa = round($points / $units, 2);
        $cgpa = round(max(2.0, min(5.0, $gpa + (mt_rand(-30, 30) / 100))), 2);
        $class = $cgpa >= 4.4 ? ['First Class', 'Première classe'] : ($cgpa >= 3.6 ? ['Second Class (Upper Division)', 'Deuxième classe (division supérieure)']
            : ($cgpa >= 2.8 ? ['Second Class (Lower Division)', 'Deuxième classe (division inférieure)'] : ['Pass', 'Passable']));
        $standing = $cgpa >= 2.0 ? ['Normal progress', 'Progression normale'] : ['Probation', "Mise à l'épreuve"];
        $ay = "$year/" . ($year + 1);
        $date ??= $this->date($year + 1, 7, 9);

        $this->save([
            'reference_no' => $ref ?? $this->ref($faculty, $year + 1),
            'title' => "Academic Transcript {$ay} — {$s['name']}",
            'title_fr' => "Relevé de notes {$ay} — {$s['name']}",
            'document_type' => 'transcript', 'dept' => $faculty,
            'programme' => $programme, 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => 'Examinations Office', 'author_fr' => self::deptFr('EXM'), 'date_created' => $date, 'academic_year' => $ay,
            'keywords' => "transcript, results, CGPA, year {$yos}",
            'keywords_fr' => "relevé de notes, résultats, CGPA, année {$yos}",
            'description' => "Academic transcript of {$s['name']} ({$s['sno']}) for {$ay}, year {$yos}, CGPA {$cgpa}.",
            'description_fr' => "Relevé de notes de {$s['name']} ({$s['sno']}) pour {$ay}, année {$yos}, CGPA " . self::num($cgpa, 'fr') . '.',
            'content' => "ACADEMIC TRANSCRIPT\n\nName: {$s['name']}\nRegistration number: {$s['sno']}\nProgramme: {$programme}\n"
                . 'Faculty: ' . self::DEPARTMENTS[$faculty] . "\nAcademic year: {$ay}\nYear of study: {$yos}\n\nCourse units:\n" . implode("\n", $en)
                . "\n\nSemester GPA: {$gpa}\nCumulative GPA (CGPA): {$cgpa}\nCurrent classification: {$class[0]}\nAcademic standing: {$standing[0]}"
                . "\n\nIssued by the Examinations Office on " . self::longDate($date, 'en')
                . '. This transcript is not valid without the university seal and the signature of the Academic Registrar.',
            'content_fr' => "RELEVÉ DE NOTES\n\nNom : {$s['name']}\nNuméro d'inscription : {$s['sno']}\nProgramme : {$progFr}\n"
                . 'Faculté : ' . self::deptFr($faculty) . "\nAnnée universitaire : {$ay}\nAnnée d'études : {$yos}\n\nUnités d'enseignement :\n" . implode("\n", $fr)
                . "\n\nMoyenne du semestre (GPA) : " . self::num($gpa, 'fr') . "\nMoyenne cumulée (CGPA) : " . self::num($cgpa, 'fr')
                . "\nMention actuelle : {$class[1]}\nSituation académique : {$standing[1]}"
                . "\n\nDélivré par le Service des examens le " . self::longDate($date, 'fr')
                . ". Ce relevé n'est valable qu'avec le sceau de l'université et la signature du Registraire académique.",
            'access_level' => 'confidential',
        ]);
    }

    private function feeStatement(array $s, int $year, ?string $ref = null): void
    {
        $programme = self::PROGRAMMES[$s['prog']][0];
        $progFr = self::progFr($s['prog']);
        $tuition = mt_rand(1200, 2100) * 1000;
        $functional = mt_rand(150, 320) * 1000;
        $total = $tuition + $functional;
        $paid = (int) ($total * $this->pick([1, 1, 1, 0.75, 0.5, 0.9]));
        $balance = $total - $paid;
        $ay = "$year/" . ($year + 1);
        $date = $this->date($year + 1, 1, 6);
        $gov = $s['scheme'] === 'gov';
        $e = $s['female'] ? 'e' : '';
        $lines = static fn (string $l) => $l === 'fr'
            ? 'Frais de scolarité (deux semestres) : ' . self::ugx($tuition, 'fr') . "\nFrais de fonctionnement : " . self::ugx($functional, 'fr')
              . "\nTotal facturé : " . self::ugx($total, 'fr') . "\nPaiements reçus : " . self::ugx($paid, 'fr') . "\nSolde : " . self::ugx($balance, 'fr')
            : 'Tuition (two semesters): ' . self::ugx($tuition, 'en') . "\nFunctional fees: " . self::ugx($functional, 'en')
              . "\nTotal invoiced: " . self::ugx($total, 'en') . "\nPayments received: " . self::ugx($paid, 'en') . "\nBalance: " . self::ugx($balance, 'en');

        $this->save([
            'reference_no' => $ref ?? $this->ref('FIN', $year + 1),
            'title' => "Fee Statement {$ay} — {$s['name']}",
            'title_fr' => "Relevé de frais {$ay} — {$s['name']}",
            'document_type' => 'fee_statement', 'dept' => 'FIN',
            'programme' => $programme, 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => "Bursar's Office", 'author_fr' => "Bureau de l'économe", 'date_created' => $date, 'academic_year' => $ay,
            'keywords' => 'fees, tuition, payment, balance, ' . ($balance > 0 ? 'outstanding' : 'cleared'),
            'keywords_fr' => 'frais, scolarité, paiement, solde, ' . ($balance > 0 ? 'impayé' : 'soldé'),
            'description' => "Tuition and functional fees statement of {$s['name']} for {$ay}; balance " . self::ugx($balance, 'en') . '.',
            'description_fr' => "Relevé des frais de scolarité et de fonctionnement de {$s['name']} pour {$ay} ; solde : " . self::ugx($balance, 'fr') . '.',
            'content' => "FEE STATEMENT — {$ay}\n\nStudent: {$s['name']}\nRegistration number: {$s['sno']}\nProgramme: {$programme}\n\n" . $lines('en') . "\n\n"
                . ($gov ? 'Tuition is covered by government sponsorship; only functional fees are payable by the student. ' : '')
                . ($balance > 0 ? 'Status: OUTSTANDING. The student must clear the balance before sitting end-of-semester examinations.' : 'Status: CLEARED. The student is cleared to sit examinations.'),
            'content_fr' => "RELEVÉ DE FRAIS — {$ay}\n\nÉtudiant{$e} : {$s['name']}\nNuméro d'inscription : {$s['sno']}\nProgramme : {$progFr}\n\n" . $lines('fr') . "\n\n"
                . ($gov ? "Les frais de scolarité sont couverts par la bourse du gouvernement ; seuls les frais de fonctionnement sont à la charge de l'étudiant{$e}. " : '')
                . ($balance > 0 ? "Statut : IMPAYÉ. L'étudiant{$e} doit régler le solde avant de passer les examens de fin de semestre." : "Statut : SOLDÉ. L'étudiant{$e} est autorisé{$e} à passer les examens."),
            'access_level' => 'confidential',
        ]);
    }

    private function disciplinary(array $s): void
    {
        $year = mt_rand(max(2021, $s['entry']), 2025);
        $offence = $this->pick([
            ['examination malpractice (possession of unauthorised notes)', 'fraude aux examens (possession de notes non autorisées)'],
            ['impersonation during an examination', "substitution de personne lors d'un examen"],
            ['assault of a fellow student', "agression d'un autre étudiant"],
            ['damage to university property', "dégradation de biens de l'université"],
            ['forgery of a fees receipt', "falsification d'un reçu de paiement"],
        ]);
        $outcome = $this->pick([
            ['a written warning', 'un avertissement écrit'], ['suspension for one semester', "une suspension d'un semestre"],
            ['cancellation of the affected examination results', "l'annulation des résultats d'examen concernés"], ['a fine and restitution', 'une amende et la réparation du préjudice'],
        ]);
        $e = $s['female'] ? 'e' : '';
        $this->save([
            'reference_no' => $this->ref('LEG', $year),
            'title' => "Disciplinary Case File — {$s['name']} ({$s['sno']})",
            'title_fr' => "Dossier disciplinaire — {$s['name']} ({$s['sno']})",
            'document_type' => 'disciplinary', 'dept' => 'LEG',
            'programme' => self::PROGRAMMES[$s['prog']][0], 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => 'Students Disciplinary Committee', 'author_fr' => 'Comité de discipline des étudiants',
            'date_created' => $this->date($year), 'academic_year' => "$year/" . ($year + 1),
            'keywords' => 'disciplinary, hearing, misconduct', 'keywords_fr' => 'discipline, audience, faute',
            'description' => "Record of the disciplinary hearing of {$s['name']} for {$offence[0]}.",
            'description_fr' => "Compte rendu de l'audience disciplinaire de {$s['name']} pour {$offence[1]}.",
            'content' => "The Students Disciplinary Committee heard the case of {$s['name']} ({$s['sno']}), charged with {$offence[0]}. "
                . "After hearing the student and the witnesses, the committee resolved to impose {$outcome[0]}. The student was informed of the right of appeal to the Vice Chancellor within fourteen days.",
            'content_fr' => "Le Comité de discipline des étudiants a examiné le dossier de {$s['name']} ({$s['sno']}), poursuivi{$e} pour {$offence[1]}. "
                . "Après avoir entendu l'étudiant{$e} et les témoins, le comité a décidé de prononcer {$outcome[1]}. L'étudiant{$e} a été informé{$e} de son droit de faire appel auprès du Vice-chancelier dans un délai de quatorze jours.",
            'access_level' => 'restricted',
        ]);
    }

    private function verificationRequest(array $s, ?string $ref = null, ?string $date = null): void
    {
        $company = $this->pick([
            ['Stanbic Bank Uganda', 'Stanbic Bank Uganda'], ['MTN Uganda', 'MTN Uganda'], ['Ministry of Health', 'Ministère de la Santé'],
            ['Uganda Revenue Authority', 'Autorité fiscale ougandaise'], ['Kenya Commercial Bank', 'Kenya Commercial Bank'], ['Save the Children', 'Save the Children'],
        ]);
        $year = $date ? (int) substr($date, 0, 4) : mt_rand(2024, 2026);
        $date ??= $this->date($year);
        $programme = self::PROGRAMMES[$s['prog']][0];
        $progFr = self::progFr($s['prog']);
        $this->save([
            'reference_no' => $ref ?? $this->ref('REG', $year),
            'title' => "Request for Verification of Academic Documents — {$s['name']}",
            'title_fr' => "Demande de vérification des documents académiques — {$s['name']}",
            'document_type' => 'correspondence', 'dept' => 'REG',
            'programme' => $programme, 'student_no' => $s['sno'], 'student_name' => $s['name'],
            'author' => "Human Resources Manager, {$company[0]}", 'author_fr' => "Responsable des ressources humaines, {$company[1]}", 'date_created' => $date,
            'keywords' => 'verification, employer, certificate, background check',
            'keywords_fr' => 'vérification, employeur, diplôme, contrôle des antécédents',
            'description' => "{$company[0]} asks the Registry to confirm the academic records of {$s['name']}.",
            'description_fr' => "{$company[1]} demande au Service de la scolarité de confirmer le dossier académique de {$s['name']}.",
            'content' => "Dear Academic Registrar,\n\n{$s['name']} has applied for a position with {$company[0]} and has presented academic documents issued by Lakeview University "
                . "for the {$programme} (registration number {$s['sno']}). Kindly verify the authenticity of the attached certificate and academic transcript and confirm the classification awarded.\n\n"
                . "The candidate's signed consent to this verification is attached.\n\nYours faithfully,\nHuman Resources Manager, {$company[0]}",
            'content_fr' => "Monsieur le Registraire académique,\n\n{$s['name']} a postulé à un poste chez {$company[1]} et a présenté des documents académiques délivrés par l'Université de Lakeview "
                . "pour la {$progFr} (numéro d'inscription {$s['sno']}). Nous vous prions de bien vouloir vérifier l'authenticité du diplôme et du relevé de notes ci-joints et de confirmer la mention obtenue.\n\n"
                . "Le consentement signé du candidat à cette vérification est joint.\n\nVeuillez agréer nos salutations distinguées,\nResponsable des ressources humaines, {$company[1]}",
            'access_level' => 'confidential',
        ]);
    }

    // ------------------------------------------------------------------ core documents (used by the evaluation)

    private function coreDocuments(): void
    {
        $brian = [
            'first' => 'Brian', 'last' => 'Okello', 'name' => 'Brian Okello', 'entry' => 2021, 'prog' => 'BCS', 'sno' => '21/U/04567',
            'female' => false, 'district' => 'Lira', 'scheme' => 'priv',
        ];
        $this->usedNames['Brian Okello'] = true;
        $this->usedNumbers['21/U/04567'] = true;
        $this->admissionLetter($brian, 'LU/ADM/2021/0003', '2021-08-16');
        $this->studentFile($brian, 'LU/REG/2021/0014');
        $this->transcript($brian, 2022, 'LU/FCI/2023/0011', '2023-09-04');
        $this->transcript($brian, 2023, 'LU/FCI/2024/0012', '2024-09-02');
        $this->feeStatement($brian, 2023, 'LU/FIN/2024/0031');
        $this->verificationRequest($brian, 'LU/REG/2025/0009', '2025-03-11');

        $this->save([
            'reference_no' => 'LU/FIN/2022/0007',
            'title' => 'Tuition Fee Waiver and Bursary Policy',
            'title_fr' => "Politique d'exonération des frais de scolarité et de bourses",
            'document_type' => 'policy', 'dept' => 'FIN', 'author' => 'University Council', 'author_fr' => "Conseil d'université", 'date_created' => '2022-05-20',
            'keywords' => 'fee waiver, bursary, financial aid, needy students, scholarships',
            'keywords_fr' => 'exonération des frais, bourse, aide financière, étudiants démunis, bourses d\'études',
            'description' => 'Sets the criteria and procedure for granting full or partial tuition fee waivers and bursaries to students.',
            'description_fr' => "Fixe les critères et la procédure d'octroi d'exonérations totales ou partielles des frais de scolarité et de bourses aux étudiants.",
            'content' => "1. PURPOSE\nThis policy establishes a fair and transparent procedure for granting tuition fee waivers and bursaries to academically qualified students who cannot afford tuition.\n\n"
                . "2. SCOPE\nThe policy applies to all undergraduate students registered at Lakeview University.\n\n"
                . "3. ELIGIBILITY\nA student may apply for a fee waiver if they are an orphan, a person with a disability, from a household below the poverty line, or a dependant of a deceased member of staff. Applicants must have a CGPA of at least 3.0.\n\n"
                . "4. LEVELS OF WAIVER\nThe Fee Waiver Committee may grant a full waiver (100%), a partial waiver (50%) or a bursary covering functional fees only. Waivers are reviewed every academic year.\n\n"
                . "5. PROCEDURE\nApplications are submitted to the Dean of Students with supporting evidence by the end of the second week of the semester. The Finance Committee approves the final list.\n\n"
                . "6. REVIEW\nThis policy shall be reviewed every three years by the University Council.",
            'content_fr' => "1. OBJET\nLa présente politique établit une procédure équitable et transparente d'octroi d'exonérations des frais de scolarité et de bourses aux étudiants méritants qui ne peuvent pas payer leurs frais.\n\n"
                . "2. CHAMP D'APPLICATION\nLa politique s'applique à tous les étudiants de premier cycle inscrits à l'Université de Lakeview.\n\n"
                . "3. CONDITIONS D'ÉLIGIBILITÉ\nUn étudiant peut demander une exonération des frais s'il est orphelin, en situation de handicap, issu d'un ménage vivant sous le seuil de pauvreté, ou à la charge d'un membre du personnel décédé. Les candidats doivent avoir une moyenne cumulée (CGPA) d'au moins 3,0.\n\n"
                . "4. NIVEAUX D'EXONÉRATION\nLe Comité d'exonération peut accorder une exonération totale (100 %), une exonération partielle (50 %) ou une bourse couvrant uniquement les frais de fonctionnement. Les exonérations sont réexaminées chaque année universitaire.\n\n"
                . "5. PROCÉDURE\nLes demandes, accompagnées des pièces justificatives, sont adressées au Doyen des étudiants avant la fin de la deuxième semaine du semestre. Le Comité des finances approuve la liste définitive.\n\n"
                . "6. RÉVISION\nLa présente politique est révisée tous les trois ans par le Conseil d'université.",
            'access_level' => 'public',
        ]);
        $this->save([
            'reference_no' => 'LU/FIN/2024/0019',
            'title' => 'Amendment to the Tuition Fee Waiver Policy (2024)',
            'title_fr' => "Amendement à la politique d'exonération des frais de scolarité (2024)",
            'document_type' => 'policy', 'dept' => 'FIN', 'author' => 'University Council', 'author_fr' => "Conseil d'université", 'date_created' => '2024-06-14',
            'keywords' => 'fee waiver, amendment, bursary, refugees', 'keywords_fr' => 'exonération des frais, amendement, bourse, réfugiés',
            'description' => 'Extends fee waiver eligibility to refugee students and lowers the minimum CGPA from 3.0 to 2.8.',
            'description_fr' => "Étend l'exonération des frais aux étudiants réfugiés et abaisse la moyenne cumulée minimale de 3,0 à 2,8.",
            'content' => "The University Council, at its sitting of 14 June 2024, amended the Tuition Fee Waiver and Bursary Policy (LU/FIN/2022/0007) as follows:\n\n"
                . "(a) Section 3 is amended to extend eligibility to registered refugee students.\n(b) The minimum CGPA for a fee waiver is lowered from 3.0 to 2.8.\n"
                . "(c) A student granted a waiver must sign a community service agreement of 40 hours per academic year.\n\n"
                . 'All other provisions of the policy remain in force. The amendment takes effect from the 2024/2025 academic year.',
            'content_fr' => "Le Conseil d'université, lors de sa séance du 14 juin 2024, a modifié la Politique d'exonération des frais de scolarité et de bourses (LU/FIN/2022/0007) comme suit :\n\n"
                . "a) L'article 3 est modifié afin d'étendre l'éligibilité aux étudiants réfugiés enregistrés.\nb) La moyenne cumulée minimale requise pour une exonération est abaissée de 3,0 à 2,8.\n"
                . "c) L'étudiant bénéficiaire d'une exonération doit signer un engagement de 40 heures de service communautaire par année universitaire.\n\n"
                . "Toutes les autres dispositions de la politique restent en vigueur. L'amendement prend effet à compter de l'année universitaire 2024/2025.",
            'access_level' => 'public',
        ]);
        $this->save([
            'reference_no' => 'LU/FIN/2024/0021',
            'title' => 'Minutes of the Finance Committee Meeting — Approval of Fee Waiver Amendment',
            'title_fr' => "Procès-verbal de la réunion du Comité des finances — Approbation de l'amendement sur l'exonération des frais",
            'document_type' => 'minutes', 'dept' => 'FIN', 'author' => 'Secretary, Finance Committee', 'author_fr' => 'Secrétaire du Comité des finances', 'date_created' => '2024-05-28',
            'academic_year' => '2023/2024', 'keywords' => 'finance committee, fee waiver, amendment, bursary',
            'keywords_fr' => 'comité des finances, exonération des frais, amendement, bourse',
            'description' => 'The Finance Committee recommended the amendment of the fee waiver policy to the University Council.',
            'description_fr' => "Le Comité des finances a recommandé au Conseil d'université la modification de la politique d'exonération des frais.",
            'content' => "MIN 04/FC/2024: Review of the Tuition Fee Waiver and Bursary Policy. The Bursar reported that 312 students benefited from fee waivers in 2023/2024 at a cost of UGX 1.4 billion. "
                . "Members noted that refugee students were not covered. The committee recommended that eligibility be extended to refugee students and that the minimum CGPA be lowered to 2.8. "
                . 'The recommendation was forwarded to the University Council for approval.',
            'content_fr' => "PV 04/CF/2024 : Révision de la Politique d'exonération des frais de scolarité et de bourses. L'économe a indiqué que 312 étudiants ont bénéficié d'une exonération des frais en 2023/2024, pour un coût de 1,4 milliard UGX. "
                . "Les membres ont relevé que les étudiants réfugiés n'étaient pas couverts. Le comité a recommandé d'étendre l'éligibilité aux étudiants réfugiés et d'abaisser la moyenne cumulée minimale à 2,8. "
                . "La recommandation a été transmise au Conseil d'université pour approbation.",
            'access_level' => 'internal',
        ]);
        $this->save([
            'reference_no' => 'LU/REG/2024/0005',
            'title' => 'Minutes of the 45th Senate Meeting',
            'title_fr' => 'Procès-verbal de la 45e réunion du Sénat',
            'document_type' => 'minutes', 'dept' => 'REG', 'author' => 'Academic Registrar (Secretary to Senate)', 'author_fr' => 'Registraire académique (secrétaire du Sénat)', 'date_created' => '2024-07-18',
            'academic_year' => '2023/2024', 'keywords' => 'senate, examination malpractice, results approval, graduation',
            'keywords_fr' => 'sénat, fraude aux examens, approbation des résultats, remise des diplômes',
            'description' => 'Senate approved the semester two results, considered examination malpractice cases and set the graduation date.',
            'description_fr' => 'Le Sénat a approuvé les résultats du second semestre, examiné les cas de fraude aux examens et fixé la date de remise des diplômes.',
            'content' => "MIN 12/SEN/2024: Examination malpractice. Senate received the report of the Examinations Committee on 27 cases of examination malpractice from the semester two examinations, including 9 cases of impersonation and 14 cases of possession of unauthorised materials. "
                . "Senate resolved that the results of the culpable students be cancelled and that the cases of impersonation be referred to the Students Disciplinary Committee.\n\n"
                . "MIN 13/SEN/2024: Approval of results. Senate approved the provisional results of all faculties for semester two of 2023/2024.\n\n"
                . 'MIN 14/SEN/2024: Graduation. The 18th graduation ceremony was set for 22 November 2024.',
            'content_fr' => "PV 12/SEN/2024 : Fraude aux examens. Le Sénat a reçu le rapport du Comité des examens sur 27 cas de fraude aux examens survenus lors des examens du second semestre, dont 9 cas de substitution de personne et 14 cas de possession de documents non autorisés. "
                . "Le Sénat a décidé d'annuler les résultats des étudiants reconnus coupables et de renvoyer les cas de substitution de personne devant le Comité de discipline des étudiants.\n\n"
                . "PV 13/SEN/2024 : Approbation des résultats. Le Sénat a approuvé les résultats provisoires de toutes les facultés pour le second semestre 2023/2024.\n\n"
                . 'PV 14/SEN/2024 : Remise des diplômes. La 18e cérémonie de remise des diplômes a été fixée au 22 novembre 2024.',
            'access_level' => 'internal',
        ]);
        $this->save([
            'reference_no' => 'LU/EXM/2024/0008',
            'title' => 'Minutes of the Special Examinations Committee Meeting on Examination Malpractice',
            'title_fr' => 'Procès-verbal de la réunion extraordinaire du Comité des examens sur la fraude aux examens',
            'document_type' => 'minutes', 'dept' => 'EXM', 'author' => 'Examinations Officer', 'author_fr' => 'Responsable des examens', 'date_created' => '2024-07-02',
            'academic_year' => '2023/2024', 'keywords' => 'examinations committee, malpractice, impersonation, cheating',
            'keywords_fr' => 'comité des examens, fraude, substitution de personne, tricherie',
            'description' => 'Special meeting to investigate examination malpractice cases before they were reported to Senate.',
            'description_fr' => "Réunion extraordinaire chargée d'enquêter sur les cas de fraude aux examens avant leur présentation au Sénat.",
            'content' => 'The special meeting was convened to investigate cases of examination malpractice reported by invigilators. The committee interviewed 27 students and reviewed the scripts and the unauthorised materials confiscated. '
                . 'It found 21 students culpable. The committee recommended stricter identity checks at examination venues, the use of examination permits with photographs, and CCTV in the main examination hall.',
            'content_fr' => "La réunion extraordinaire a été convoquée pour enquêter sur les cas de fraude aux examens signalés par les surveillants. Le comité a entendu 27 étudiants et examiné les copies ainsi que les documents non autorisés confisqués. "
                . "Il a reconnu 21 étudiants coupables. Le comité a recommandé des contrôles d'identité plus stricts dans les salles d'examen, l'usage d'autorisations d'examen avec photographie et la vidéosurveillance dans la grande salle d'examen.",
            'access_level' => 'confidential',
        ]);
        $this->save([
            'reference_no' => 'LU/HR/2023/0042',
            'title' => 'Employment Contract — Dr. Florence Atim, Senior Lecturer',
            'title_fr' => 'Contrat de travail — Dr Florence Atim, maître de conférences',
            'document_type' => 'staff_contract', 'dept' => 'HR', 'author' => 'Director, Human Resources', 'author_fr' => 'Directeur des ressources humaines', 'date_created' => '2023-01-09',
            'keywords' => 'employment contract, senior lecturer, appointment, computing',
            'keywords_fr' => 'contrat de travail, maître de conférences, nomination, informatique',
            'description' => 'Three-year contract appointing Dr. Florence Atim as Senior Lecturer in the Faculty of Computing & IT.',
            'description_fr' => "Contrat de trois ans nommant Dr Florence Atim maître de conférences à la Faculté d'informatique et TI.",
            'content' => 'This contract is made between Lakeview University and Dr. Florence Atim, appointed Senior Lecturer in the Faculty of Computing & IT with effect from 1 February 2023 for a period of three years, renewable. '
                . 'The appointment is on salary scale M4. The first six months are a probation period. Duties include teaching undergraduate and postgraduate course units, supervising research students and serving on faculty committees.',
            'content_fr' => "Le présent contrat est conclu entre l'Université de Lakeview et Dr Florence Atim, nommée maître de conférences à la Faculté d'informatique et TI à compter du 1er février 2023 pour une durée de trois ans, renouvelable. "
                . "La nomination relève de l'échelle salariale M4. Les six premiers mois constituent une période d'essai. Les fonctions comprennent l'enseignement en premier et troisième cycles, l'encadrement d'étudiants chercheurs et la participation aux comités de la faculté.",
            'access_level' => 'confidential',
        ]);
        $this->save([
            'reference_no' => 'LU/LEG/2024/0003',
            'title' => 'Legal Opinion on a Pending Staff Dismissal Case',
            'title_fr' => 'Avis juridique sur une procédure de licenciement en cours',
            'document_type' => 'correspondence', 'dept' => 'LEG', 'author' => 'University Legal Counsel', 'author_fr' => "Conseiller juridique de l'université", 'date_created' => '2024-10-07',
            'keywords' => 'legal opinion, dismissal, litigation, confidential', 'keywords_fr' => 'avis juridique, licenciement, contentieux, confidentiel',
            'description' => 'Privileged legal advice to the Vice Chancellor on a dismissal case before the Industrial Court.',
            'description_fr' => 'Conseil juridique confidentiel au Vice-chancelier sur une affaire de licenciement portée devant le Tribunal du travail.',
            'content' => 'This opinion is privileged and is addressed to the Vice Chancellor only. It assesses the legal risks of the pending dismissal case before the Industrial Court and recommends an out-of-court settlement.',
            'content_fr' => "Le présent avis est couvert par le secret professionnel et est adressé au seul Vice-chancelier. Il évalue les risques juridiques de l'affaire de licenciement pendante devant le Tribunal du travail et recommande un règlement à l'amiable.",
            'access_level' => 'restricted',
        ]);
    }

    // ------------------------------------------------------------------ institutional records

    private function institutionalDocuments(): void
    {
        $this->minutesDocuments(36);
        $this->policies();
        $this->memos(30);
        $this->staffContracts(22);
        $this->financialReports();
        $this->graduationLists();
        $this->courseOutlines();
        $this->examResults(16);
        $this->otherCorrespondence();
    }

    private function minutesDocuments(int $n): void
    {
        // body => [department, access, agenda pool, French name with article ("du Sénat")]
        $bodies = [
            'Senate' => ['REG', 'internal', ['malpractice', 'results', 'graduation', 'curriculum', 'records'], 'du Sénat'],
            'University Council' => ['REG', 'confidential', ['budget', 'fees', 'staff', 'ict'], "du Conseil d'université"],
            'Finance Committee' => ['FIN', 'internal', ['budget', 'fees', 'ict'], 'du Comité des finances'],
            'Examinations Committee' => ['EXM', 'internal', ['malpractice', 'results', 'records'], 'du Comité des examens'],
            'Admissions Committee' => ['ADM', 'internal', ['admissions', 'records', 'fees'], 'du Comité des admissions'],
            'Staff Appointments Board' => ['HR', 'confidential', ['staff'], 'de la Commission de nomination du personnel'],
            'Faculty Board' => ['', 'internal', ['results', 'curriculum', 'admissions', 'malpractice'], ''],
        ];
        $itemNames = [
            'malpractice' => ['examination malpractice', 'fraude aux examens'], 'results' => ['results', 'résultats'],
            'graduation' => ['graduation', 'remise des diplômes'], 'curriculum' => ['curriculum', 'programmes'], 'records' => ['records', 'archives'],
            'budget' => ['budget', 'budget'], 'fees' => ['fees', 'frais'], 'staff' => ['staff', 'personnel'], 'ict' => ['ICT', 'informatique'],
            'admissions' => ['admissions', 'admissions'],
        ];
        $counter = [];
        for ($i = 0; $i < $n; $i++) {
            $body = $this->pick(array_keys($bodies));
            [$dept, $access, $pool, $bodyFr] = $bodies[$body];
            $faculty = $this->pick(['FCI', 'FBM', 'FED', 'FHS']);
            $label = $body === 'Faculty Board' ? 'Faculty Board of the ' . self::DEPARTMENTS[$faculty] : $body;
            $bodyFr = $bodyFr ?: 'du Conseil de la ' . self::deptFr($faculty);
            $dept = $dept ?: $faculty;
            $year = mt_rand(2021, 2026);
            $date = $this->date($year);
            $counter[$label] = ($counter[$label] ?? mt_rand(20, 40)) + 1;
            $num = $counter[$label];

            shuffle($pool);
            $items = array_slice($pool, 0, min(count($pool), mt_rand(2, 3)));
            $en = $fr = [];
            foreach ($items as $k => $item) {
                [$textEn, $textFr] = $this->agendaItem($item, $year, $faculty);
                $en[] = sprintf('MIN %02d/%d: ', $k + 1, $year) . $textEn;
                $fr[] = sprintf('PV %02d/%d : ', $k + 1, $year) . $textFr;
            }
            $people = [$this->person(true), $this->person(true), $this->person(true), $this->person(true)];
            $secretary = $this->person(true);
            $itemsEn = implode(', ', array_map(static fn ($x) => $itemNames[$x][0], $items));
            $itemsFr = implode(', ', array_map(static fn ($x) => $itemNames[$x][1], $items));

            $this->save([
                'reference_no' => $this->ref($dept, $year),
                'title' => 'Minutes of the ' . self::ordinal($num, 'en') . " {$label} Meeting",
                'title_fr' => 'Procès-verbal de la ' . self::ordinal($num, 'fr') . " réunion {$bodyFr}",
                'document_type' => 'minutes', 'dept' => $dept,
                'author' => "Secretary, {$body}", 'author_fr' => "Secrétaire {$bodyFr}", 'date_created' => $date,
                'academic_year' => ($year - 1) . "/$year",
                'keywords' => strtolower($body) . ', minutes, ' . $itemsEn,
                'keywords_fr' => 'procès-verbal, réunion, ' . $itemsFr,
                'description' => "Minutes of the {$label} held on " . self::longDate($date, 'en') . ". Items: {$itemsEn}.",
                'description_fr' => "Procès-verbal de la réunion {$bodyFr} tenue le " . self::longDate($date, 'fr') . ". Points : {$itemsFr}.",
                'content' => "Present: {$people[0][0]} (Chair), {$people[1][0]}, {$people[2][0]}, {$people[3][0]}.\nIn attendance: {$secretary[0]} (Secretary).\n\n" . implode("\n\n", $en),
                'content_fr' => "Présents : {$people[0][1]} (président), {$people[1][1]}, {$people[2][1]}, {$people[3][1]}.\nAssistait : {$secretary[1]} (secrétaire).\n\n" . implode("\n\n", $fr),
                'access_level' => $access,
            ]);
        }
    }

    /** @return array{0:string, 1:string} */
    private function agendaItem(string $item, int $year, string $faculty): array
    {
        $sem = $this->pick([['first', 'premier'], ['second', 'second']]);
        $ay = ($year - 1) . "/$year";
        $progKey = $this->pick(array_keys(array_filter(self::PROGRAMMES, static fn ($p) => $p[1] === $faculty)));
        $prog = [self::PROGRAMMES[$progKey][0], self::progFr($progKey)];
        $fac = [self::DEPARTMENTS[$faculty], self::deptFr($faculty)];
        $m = mt_rand(0, 11);
        $month = [self::MONTHS['en'][$m], self::MONTHS['fr'][$m]];

        switch ($item) {
            case 'malpractice':
                $a = mt_rand(4, 30);
                $b = mt_rand(2, 12);
                return [
                    "Examination malpractice cases. The meeting considered {$a} cases of examination malpractice reported during the {$sem[0]} semester examinations of {$ay}, including impersonation and possession of unauthorised materials. {$b} students were found culpable and their results for the affected course units were cancelled; the remaining cases were referred for further investigation.",
                    "Cas de fraude aux examens. La réunion a examiné {$a} cas de fraude aux examens signalés lors des examens du {$sem[1]} semestre de {$ay}, notamment des substitutions de personne et la possession de documents non autorisés. {$b} étudiants ont été reconnus coupables et leurs résultats pour les unités d'enseignement concernées ont été annulés ; les autres cas ont été renvoyés pour complément d'enquête.",
                ];
            case 'results':
                $p = mt_rand(71, 96);
                return [
                    "Approval of examination results. The meeting received and approved the provisional examination results of the {$fac[0]} for the {$sem[0]} semester of {$ay}. The pass rate was {$p}%. Students with retakes were advised to register for the affected course units in the next semester.",
                    "Approbation des résultats d'examen. La réunion a reçu et approuvé les résultats provisoires de la {$fac[1]} pour le {$sem[1]} semestre de {$ay}. Le taux de réussite était de {$p} %. Les étudiants ayant des rattrapages ont été invités à s'inscrire aux unités concernées le semestre suivant.",
                ];
            case 'graduation':
                $g = mt_rand(900, 2600);
                $gm = $this->pick([[9, 'October', 'octobre'], [10, 'November', 'novembre'], [11, 'December', 'décembre']]);
                return [
                    "Preparations for the graduation ceremony. A total of {$g} graduands were cleared to graduate in {$gm[1]} {$year}. Students with outstanding fees will not appear on the graduation list.",
                    "Préparatifs de la cérémonie de remise des diplômes. Au total, {$g} diplômés ont été autorisés à recevoir leur diplôme en {$gm[2]} {$year}. Les étudiants ayant des frais impayés ne figureront pas sur la liste des diplômés.",
                ];
            case 'curriculum':
                return [
                    "Review of the {$prog[0]} curriculum. The revised curriculum introduces new course units on emerging technologies and was approved for submission to the National Council for Higher Education (NCHE) for accreditation.",
                    "Révision de la maquette de la {$prog[1]}. La maquette révisée introduit de nouvelles unités d'enseignement sur les technologies émergentes et a été approuvée pour être soumise au Conseil national de l'enseignement supérieur (NCHE) en vue de son accréditation.",
                ];
            case 'records':
                $f = mt_rand(4000, 30000);
                return [
                    'Records management. The Academic Registrar reported that ' . number_format($f) . ' student files have been scanned and indexed in the records retrieval system. Members were reminded that original files must not leave the records centre without a signed requisition.',
                    'Gestion des archives. Le Registraire académique a indiqué que ' . number_format($f, 0, ',', ' ') . " dossiers d'étudiants ont été numérisés et indexés dans le système de recherche documentaire. Il a été rappelé aux membres que les dossiers originaux ne doivent pas quitter le centre d'archives sans demande signée.",
                ];
            case 'budget':
                $p = mt_rand(62, 98);
                return [
                    "Budget performance. The Bursar presented the budget performance report for the quarter ending {$month[0]} {$year}. Revenue collection stood at {$p}% of the target, mainly from tuition.",
                    "Exécution budgétaire. L'économe a présenté le rapport d'exécution budgétaire du trimestre clos en {$month[1]} {$year}. Les recettes s'élevaient à {$p} % de l'objectif, principalement grâce aux frais de scolarité.",
                ];
            case 'fees':
                $p = mt_rand(3, 10);
                return [
                    "Review of tuition fees for the $year/" . ($year + 1) . " academic year. The committee recommended an increase of {$p}% for private students to cover rising operational costs, subject to approval by Council.",
                    "Révision des frais de scolarité pour l'année universitaire $year/" . ($year + 1) . ". Le comité a recommandé une hausse de {$p} % pour les étudiants du secteur privé afin de couvrir l'augmentation des coûts de fonctionnement, sous réserve de l'approbation du Conseil.",
                ];
            case 'staff':
                $a = mt_rand(3, 15);
                $b = mt_rand(2, 9);
                return [
                    "Staff appointments and promotions. The meeting approved the appointment of {$a} academic staff and the promotion of {$b} lecturers to the rank of Senior Lecturer.",
                    "Nominations et promotions du personnel. La réunion a approuvé la nomination de {$a} enseignants et la promotion de {$b} chargés de cours au rang de maître de conférences.",
                ];
            case 'ict':
                $cost = mt_rand(200000, 900000) * 1000;
                return [
                    'ICT infrastructure. Members approved the upgrade of the campus network and the student information system at an estimated cost of ' . self::ugx($cost, 'en') . '.',
                    "Infrastructure informatique. Les membres ont approuvé la modernisation du réseau du campus et du système d'information des étudiants pour un coût estimé à " . self::ugx($cost, 'fr') . '.',
                ];
            default: // admissions
                $a = mt_rand(300, 2400);
                $pts = mt_rand(38, 47);
                return [
                    "Admissions for the {$year} intake. The committee admitted {$a} new students. The cut-off points for the {$prog[0]} were set at {$pts} for private applicants.",
                    "Admissions de la promotion {$year}. Le comité a admis {$a} nouveaux étudiants. Le seuil d'admission en {$prog[1]} a été fixé à {$pts} points pour les candidats du secteur privé.",
                ];
        }
    }

    private function policies(): void
    {
        // [title, title_fr, dept, keywords, keywords_fr, summary, summary_fr, topic_fr]
        $policies = [
            ['Student Records Management Policy', 'Politique de gestion des dossiers étudiants', 'REG', 'records management, student files, retention, confidentiality', 'gestion des archives, dossiers étudiants, conservation, confidentialité',
                'Defines how student records are created, stored, accessed, retained and disposed of.', 'Définit la manière dont les dossiers étudiants sont créés, conservés, consultés, archivés et éliminés.', 'gestion des dossiers étudiants'],
            ['Examinations Regulations', 'Règlement des examens', 'EXM', 'examinations, invigilation, grading, retakes, appeals', 'examens, surveillance, notation, rattrapages, recours',
                'Rules governing the conduct of examinations, grading, retakes and appeals.', 'Règles régissant le déroulement des examens, la notation, les rattrapages et les recours.', 'examens'],
            ['Admissions Policy', "Politique d'admission", 'ADM', 'admissions, entry requirements, mature age, international students', "admissions, conditions d'accès, candidats adultes, étudiants internationaux",
                'Entry requirements and admission procedures for all programmes.', "Conditions d'accès et procédures d'admission pour tous les programmes.", 'admissions'],
            ['Academic Integrity and Examination Malpractice Policy', "Politique d'intégrité académique et de lutte contre la fraude aux examens", 'EXM', 'academic integrity, plagiarism, malpractice, sanctions', 'intégrité académique, plagiat, fraude, sanctions',
                'Defines plagiarism and examination malpractice and the sanctions applicable.', 'Définit le plagiat et la fraude aux examens ainsi que les sanctions applicables.', 'intégrité académique'],
            ['Data Protection and Privacy Policy', 'Politique de protection des données personnelles et de la vie privée', 'LEG', 'data protection, privacy, personal data, consent', 'protection des données, vie privée, données personnelles, consentement',
                'How the university collects, processes and protects personal data in line with the Data Protection and Privacy Act, 2019.', "Manière dont l'université collecte, traite et protège les données personnelles conformément à la loi de 2019 sur la protection des données et de la vie privée.", 'protection des données'],
            ['Staff Recruitment and Promotion Policy', 'Politique de recrutement et de promotion du personnel', 'HR', 'recruitment, promotion, academic staff, appointments', 'recrutement, promotion, personnel enseignant, nominations',
                'Procedures for recruiting and promoting academic and administrative staff.', 'Procédures de recrutement et de promotion du personnel enseignant et administratif.', 'recrutement du personnel'],
            ['ICT Acceptable Use Policy', "Charte d'utilisation des ressources informatiques", 'REG', 'ICT, email, internet, acceptable use, passwords', 'informatique, courriel, internet, usage acceptable, mots de passe',
                'Rules for the use of university computers, networks and email.', "Règles d'utilisation des ordinateurs, des réseaux et de la messagerie de l'université.", 'utilisation des ressources informatiques'],
            ['Research Ethics Policy', "Politique d'éthique de la recherche", 'REG', 'research ethics, consent, ethics committee', "éthique de la recherche, consentement, comité d'éthique",
                'Ethical approval requirements for research involving human participants.', "Exigences d'approbation éthique pour les recherches impliquant des participants humains.", 'éthique de la recherche'],
            ['Student Accommodation Policy', 'Politique de logement étudiant', 'REG', 'halls of residence, accommodation, allocation', 'résidences universitaires, logement, attribution',
                'Allocation and rules of university halls of residence.', 'Attribution des chambres et règlement des résidences universitaires.', 'logement étudiant'],
            ['Records Retention and Disposal Schedule', "Calendrier de conservation et d'élimination des archives", 'REG', 'retention, disposal, archives, records schedule', 'conservation, élimination, archives, calendrier de conservation',
                'How long each category of university record must be kept before archiving or disposal.', "Durée de conservation de chaque catégorie de documents de l'université avant archivage ou élimination.", 'conservation des archives'],
            ['Graduation Policy', 'Politique de remise des diplômes', 'REG', 'graduation, clearance, certificates, gowns', 'remise des diplômes, quitus, diplômes, toges',
                'Clearance requirements and procedures for graduation.', 'Conditions de quitus et procédures de remise des diplômes.', 'remise des diplômes'],
            ['Student Fees Payment Policy', 'Politique de paiement des frais de scolarité', 'FIN', 'fees payment, instalments, deadlines, penalties', 'paiement des frais, échéancier, délais, pénalités',
                'Deadlines, instalment plans and penalties for the payment of tuition.', 'Délais, échéanciers et pénalités applicables au paiement des frais de scolarité.', 'paiement des frais'],
            ['Postgraduate Supervision Policy', "Politique d'encadrement des études supérieures", 'REG', 'postgraduate, supervision, thesis, dissertation', 'études supérieures, encadrement, thèse, mémoire',
                'Responsibilities of supervisors and postgraduate students.', 'Responsabilités des encadrants et des étudiants de troisième cycle.', 'encadrement des études supérieures'],
            ['Sexual Harassment Prevention Policy', 'Politique de prévention du harcèlement sexuel', 'LEG', 'harassment, reporting, investigation, protection', 'harcèlement, signalement, enquête, protection',
                'Prevention, reporting and investigation of sexual harassment.', 'Prévention, signalement et traitement des cas de harcèlement sexuel.', 'prévention du harcèlement sexuel'],
        ];
        $authors = [['University Council', "Conseil d'université"], ['Senate', 'Sénat'], ['Office of the Vice Chancellor', 'Bureau du Vice-chancelier']];
        foreach ($policies as [$title, $titleFr, $dept, $kw, $kwFr, $summary, $summaryFr, $topicFr]) {
            $year = mt_rand(2019, 2025);
            $topic = strtolower(str_replace(' Policy', '', $title));
            $author = $this->pick($authors);
            $this->save([
                'reference_no' => $this->ref($dept, $year),
                'title' => $title, 'title_fr' => $titleFr, 'document_type' => 'policy', 'dept' => $dept,
                'author' => $author[0], 'author_fr' => $author[1],
                'date_created' => $this->date($year), 'keywords' => $kw, 'keywords_fr' => $kwFr, 'description' => $summary, 'description_fr' => $summaryFr,
                'content' => "1. PURPOSE\n{$summary}\n\n2. SCOPE\nThis policy applies to all students, staff and units of Lakeview University.\n\n"
                    . "3. POLICY STATEMENTS\nThe university shall maintain clear procedures on {$topic}, communicate them to all members of the university community and apply them consistently. "
                    . "Key terms covered: {$kw}.\n\n4. RESPONSIBILITIES\nThe " . self::DEPARTMENTS[$dept] . " is responsible for implementing this policy and for keeping the related records.\n\n"
                    . "5. REVIEW\nThis policy shall be reviewed every three years or earlier if the law changes.",
                'content_fr' => "1. OBJET\n{$summaryFr}\n\n2. CHAMP D'APPLICATION\nLa présente politique s'applique à l'ensemble des étudiants, du personnel et des services de l'Université de Lakeview.\n\n"
                    . "3. PRINCIPES\nL'université établit des procédures claires en matière de {$topicFr}, les communique à l'ensemble de la communauté universitaire et les applique de manière cohérente. "
                    . "Termes clés : {$kwFr}.\n\n4. RESPONSABILITÉS\nLe service responsable (" . self::deptFr($dept) . ") est chargé de la mise en œuvre de la présente politique et de la conservation des documents correspondants.\n\n"
                    . "5. RÉVISION\nLa présente politique est révisée tous les trois ans, ou plus tôt en cas d'évolution de la loi.",
                'access_level' => 'public',
            ]);
        }
    }

    private function memos(int $n): void
    {
        // [subject, subject_fr, dept, body, body_fr]
        $topics = [
            ['Deadline for Semester Registration', "Date limite d'inscription semestrielle", 'REG',
                'All continuing students must complete online registration for the semester within two weeks of reporting. Students who fail to register will not appear on examination lists.',
                "Tous les étudiants en poursuite d'études doivent finaliser leur inscription en ligne dans les deux semaines suivant la rentrée. Les étudiants non inscrits ne figureront pas sur les listes d'examen."],
            ['Submission of Examination Results to the Examinations Office', "Transmission des résultats d'examen au Service des examens", 'EXM',
                'Heads of department are reminded to submit marked scripts and examination results within three weeks after the last paper. Late submission delays the approval of results by Senate.',
                "Les chefs de département sont priés de transmettre les copies corrigées et les résultats dans les trois semaines suivant la dernière épreuve. Tout retard repousse l'approbation des résultats par le Sénat."],
            ['Scanning and Digitisation of Student Files', 'Numérisation des dossiers étudiants', 'REG',
                'The Records Office will scan all active student files during the recess. Faculties are requested to return borrowed files to the records centre before scanning starts.',
                "Le Service des archives numérisera tous les dossiers étudiants actifs pendant les vacances. Les facultés sont priées de restituer les dossiers empruntés au centre d'archives avant le début de la numérisation."],
            ['Payment of Tuition Fees Before Examinations', 'Paiement des frais de scolarité avant les examens', 'FIN',
                'Students with outstanding tuition balances will not be issued examination permits. Faculties should not allow students without permits into examination rooms.',
                "Aucune autorisation d'examen ne sera délivrée aux étudiants ayant un solde impayé. Les facultés ne doivent pas admettre en salle d'examen les étudiants sans autorisation."],
            ['Staff Performance Appraisal', 'Évaluation annuelle du personnel', 'HR',
                'All staff are required to complete the annual performance appraisal form and discuss it with their supervisor before the end of the month.',
                "Tout le personnel doit remplir le formulaire d'évaluation annuelle et en discuter avec son supérieur avant la fin du mois."],
            ['Annual Leave Schedule', 'Calendrier des congés annuels', 'HR',
                'Heads of department must submit the annual leave schedule for their staff to Human Resources to ensure service continuity.',
                'Les chefs de département doivent transmettre aux Ressources humaines le calendrier des congés annuels de leur personnel afin d\'assurer la continuité du service.'],
            ['Change of Programme Requests', 'Demandes de changement de programme', 'ADM',
                'Students wishing to change programme must apply through the Admissions Office within the first three weeks of the semester and must meet the entry requirements of the new programme.',
                "Les étudiants souhaitant changer de programme doivent en faire la demande au Service des admissions dans les trois premières semaines du semestre et remplir les conditions d'accès du nouveau programme."],
            ['Late Admissions', 'Admissions tardives', 'ADM',
                'No late admissions will be processed after the fourth week of the semester. Applicants should be advised to apply for the next intake.',
                'Aucune admission tardive ne sera traitée après la quatrième semaine du semestre. Les candidats doivent être invités à postuler pour la promotion suivante.'],
            ['Invigilation Schedule for End of Semester Examinations', 'Calendrier de surveillance des examens de fin de semestre', 'EXM',
                "The invigilation schedule is attached. Invigilators must report thirty minutes before each paper and check every candidate's examination permit and identity card.",
                "Le calendrier de surveillance est joint. Les surveillants doivent se présenter trente minutes avant chaque épreuve et vérifier l'autorisation d'examen et la carte d'identité de chaque candidat."],
            ['Retrieval of Archived Files from the Records Centre', "Consultation des dossiers archivés au centre d'archives", 'REG',
                'Requests for archived files must be made on the file requisition form and signed by the head of department. Files must be returned within five working days.',
                'Les demandes de dossiers archivés doivent être faites sur le formulaire de demande signé par le chef de département. Les dossiers doivent être restitués dans un délai de cinq jours ouvrables.'],
            ['Use of Official Email for University Business', "Utilisation de la messagerie officielle pour les affaires de l'université", 'REG',
                'All official correspondence must be sent from university email accounts. Personal email accounts must not be used to send student records.',
                "Toute correspondance officielle doit être envoyée depuis une adresse électronique de l'université. Les adresses personnelles ne doivent pas être utilisées pour transmettre des dossiers étudiants."],
            ['Issuance of Academic Transcripts', 'Délivrance des relevés de notes', 'EXM',
                'Transcripts will be issued only to students who have cleared all fees and returned library books. Requests take five working days to process.',
                'Les relevés de notes ne sont délivrés qu\'aux étudiants ayant réglé tous leurs frais et restitué les livres de la bibliothèque. Le traitement des demandes prend cinq jours ouvrables.'],
        ];
        $recipients = [
            ['All Deans and Heads of Department', 'Tous les doyens et chefs de département'], ['All Staff', 'Tout le personnel'], ['All Students', 'Tous les étudiants'],
            ['Faculty Administrators', 'Administrateurs de faculté'], ['Records Officers', 'Agents des archives'],
        ];
        for ($i = 0; $i < $n; $i++) {
            [$subject, $subjectFr, $dept, $body, $bodyFr] = $this->pick($topics);
            $year = mt_rand(2020, 2026);
            $date = $this->date($year);
            $from = ['Head, ' . self::DEPARTMENTS[$dept], 'Responsable — ' . self::deptFr($dept)];
            $to = $this->pick($recipients);
            $this->save([
                'reference_no' => $this->ref($dept, $year),
                'title' => "Memo: {$subject}", 'title_fr' => "Note de service : {$subjectFr}",
                'document_type' => 'memo', 'dept' => $dept, 'author' => $from[0], 'author_fr' => $from[1], 'date_created' => $date,
                'keywords' => 'memo, circular, ' . strtolower($subject), 'keywords_fr' => 'note de service, circulaire, ' . mb_strtolower($subjectFr),
                'description' => "Internal memo from the {$from[0]} to {$to[0]} on " . strtolower($subject) . '.',
                'description_fr' => "Note interne ({$from[1]}) adressée à : {$to[1]}. Objet : " . mb_strtolower($subjectFr) . '.',
                'content' => "INTERNAL MEMO\n\nTo: {$to[0]}\nFrom: {$from[0]}\nDate: " . self::longDate($date, 'en') . "\nSubject: {$subject}\n\n{$body}\n\nYour cooperation is highly appreciated.",
                'content_fr' => "NOTE DE SERVICE\n\nÀ : {$to[1]}\nDe : {$from[1]}\nDate : " . self::longDate($date, 'fr') . "\nObjet : {$subjectFr}\n\n{$bodyFr}\n\nNous vous remercions de votre collaboration.",
                'access_level' => 'internal',
            ]);
        }
    }

    private function staffContracts(int $n): void
    {
        // [position, scale, French masculine, French feminine]
        $positions = [
            ['Lecturer', 'M5', 'chargé de cours', 'chargée de cours'], ['Senior Lecturer', 'M4', 'maître de conférences', 'maître de conférences'],
            ['Assistant Lecturer', 'M6', "assistant d'enseignement", "assistante d'enseignement"], ['Associate Professor', 'M3', 'professeur associé', 'professeure associée'],
            ['Records Assistant', 'U6', 'assistant aux archives', 'assistante aux archives'], ['Accountant', 'U4', 'comptable', 'comptable'],
            ['Administrative Assistant', 'U7', 'assistant administratif', 'assistante administrative'], ['Systems Administrator', 'U4', 'administrateur systèmes', 'administratrice systèmes'],
        ];
        for ($i = 0; $i < $n; $i++) {
            [$position, $scale, $posM, $posF] = $this->pick($positions);
            $academic = $scale[0] === 'M';
            $unit = $academic ? $this->pick(['FCI', 'FBM', 'FED', 'FHS']) : $this->pick(['REG', 'FIN', 'EXM', 'ADM']);
            $first = $this->pick(self::FIRST);
            $female = in_array($first, self::FEMALE, true);
            $name = $first . ' ' . $this->pick(self::LAST);
            $title = $academic ? $this->pick(['Dr.', 'Dr.', 'Prof.', $female ? 'Ms.' : 'Mr.']) : ($female ? $this->pick(['Ms.', 'Mrs.']) : 'Mr.');
            $nameEn = "$title $name";
            $nameFr = self::TITLES_FR[$title] . " $name";
            $posFr = $female ? $posF : $posM;
            $year = mt_rand(2020, 2026);
            $date = $this->date($year);
            $years = $this->pick([2, 3, 3, 5]);
            $this->save([
                'reference_no' => $this->ref('HR', $year),
                'title' => "Employment Contract — {$nameEn}, {$position}",
                'title_fr' => "Contrat de travail — {$nameFr}, {$posFr}",
                'document_type' => 'staff_contract', 'dept' => 'HR', 'author' => 'Director, Human Resources', 'author_fr' => 'Directeur des ressources humaines', 'date_created' => $date,
                'keywords' => 'employment contract, appointment, ' . strtolower($position), 'keywords_fr' => 'contrat de travail, nomination, ' . $posFr,
                'description' => "{$years}-year contract appointing {$nameEn} as {$position} in the " . self::DEPARTMENTS[$unit] . '.',
                'description_fr' => "Contrat de {$years} ans nommant {$nameFr} {$posFr} (" . self::deptFr($unit) . ').',
                'content' => "This contract is made between Lakeview University and {$nameEn}, appointed {$position} in the " . self::DEPARTMENTS[$unit]
                    . ' with effect from ' . self::longDate($date, 'en') . " for a period of {$years} years, renewable subject to satisfactory performance. "
                    . "The appointment is on salary scale {$scale}. The first six months are a probation period. Either party may terminate the contract by giving three months' notice in writing.",
                'content_fr' => "Le présent contrat est conclu entre l'Université de Lakeview et {$nameFr}, nommé" . ($female ? 'e' : '') . " {$posFr} (" . self::deptFr($unit)
                    . ') à compter du ' . self::longDate($date, 'fr') . " pour une durée de {$years} ans, renouvelable sous réserve de résultats satisfaisants. "
                    . "La nomination relève de l'échelle salariale {$scale}. Les six premiers mois constituent une période d'essai. Chacune des parties peut résilier le contrat moyennant un préavis écrit de trois mois.",
                'access_level' => 'confidential',
            ]);
        }
    }

    private function financialReports(): void
    {
        for ($year = 2021; $year <= 2026; $year++) {
            foreach ([1, 2, 3, 4] as $q) {
                if (($year === 2026 && $q > 3) || !$this->chance(0.45)) {
                    continue;
                }
                $date = $this->date($year, min(12, $q * 3 + 1), 12);
                $revenue = mt_rand(9000000, 21000000) * 1000;
                $share = mt_rand(70, 88);
                $spend = mt_rand(8000000, 19000000) * 1000;
                $arrears = mt_rand(900000, 3000000) * 1000;
                $this->save([
                    'reference_no' => $this->ref('FIN', $year),
                    'title' => "Quarterly Financial Report — Q{$q} {$year}", 'title_fr' => "Rapport financier trimestriel — T{$q} {$year}",
                    'document_type' => 'financial_report', 'dept' => 'FIN', 'author' => 'University Bursar', 'author_fr' => "Économe de l'université", 'date_created' => $date,
                    'keywords' => 'financial report, revenue, expenditure, budget, quarter', 'keywords_fr' => 'rapport financier, recettes, dépenses, budget, trimestre',
                    'description' => "Revenue and expenditure of the university for quarter {$q} of {$year}.",
                    'description_fr' => "Recettes et dépenses de l'université pour le trimestre {$q} de {$year}.",
                    'content' => 'Total revenue for the quarter was ' . self::ugx($revenue, 'en') . ", of which tuition contributed {$share}%. Expenditure stood at " . self::ugx($spend, 'en')
                        . ', mainly salaries, utilities and teaching materials. Outstanding student fee balances amounted to ' . self::ugx($arrears, 'en') . '. The Bursar recommended stricter enforcement of the fees payment deadlines.',
                    'content_fr' => 'Les recettes totales du trimestre se sont élevées à ' . self::ugx($revenue, 'fr') . ", dont {$share} % provenant des frais de scolarité. Les dépenses ont atteint " . self::ugx($spend, 'fr')
                        . ', principalement les salaires, les charges et le matériel pédagogique. Les soldes de frais impayés des étudiants s\'élevaient à ' . self::ugx($arrears, 'fr') . ". L'économe a recommandé une application plus stricte des délais de paiement.",
                    'access_level' => 'internal',
                ]);
            }
            if ($year <= 2025) {
                $assets = mt_rand(80000000, 160000000) * 1000;
                $this->save([
                    'reference_no' => $this->ref('FIN', $year + 1),
                    'title' => "Audited Financial Statements for the Year Ended 30 June {$year}",
                    'title_fr' => "États financiers audités de l'exercice clos le 30 juin {$year}",
                    'document_type' => 'financial_report', 'dept' => 'FIN', 'author' => 'Office of the Auditor General', 'author_fr' => "Bureau de l'Auditeur général",
                    'date_created' => $this->date(min(2026, $year + 1), 1, 5),
                    'keywords' => 'audit, financial statements, annual report, auditor general', 'keywords_fr' => 'audit, états financiers, rapport annuel, auditeur général',
                    'description' => "Audited annual financial statements of Lakeview University for the financial year {$year}.",
                    'description_fr' => "États financiers annuels audités de l'Université de Lakeview pour l'exercice {$year}.",
                    'content' => 'The auditors issued an unqualified opinion on the financial statements. Total assets were ' . self::ugx($assets, 'en')
                        . '. The management letter raised concerns about the reconciliation of student fee receipts and the record keeping of fixed assets.',
                    'content_fr' => 'Les auditeurs ont émis une opinion sans réserve sur les états financiers. Le total de l\'actif s\'élevait à ' . self::ugx($assets, 'fr')
                        . '. La lettre de recommandations a relevé des insuffisances dans le rapprochement des reçus de frais des étudiants et la tenue du registre des immobilisations.',
                    'access_level' => 'confidential',
                ]);
            }
        }
    }

    private function graduationLists(): void
    {
        foreach ([2022, 2023, 2024, 2025] as $i => $year) {
            $graduands = array_filter($this->students, fn ($s) => $s['entry'] + self::PROGRAMMES[$s['prog']][2] === $year);
            if (!$graduands) {
                continue;
            }
            $n = 16 + $i;
            $this->save([
                'reference_no' => $this->ref('REG', $year),
                'title' => 'List of Graduands — ' . self::ordinal($n, 'en') . " Graduation Ceremony ({$year})",
                'title_fr' => 'Liste des diplômés — ' . self::ordinal($n, 'fr') . " cérémonie de remise des diplômes ({$year})",
                'document_type' => 'graduation_list', 'dept' => 'REG', 'author' => 'Academic Registrar', 'author_fr' => 'Registraire académique', 'date_created' => "$year-11-15",
                'academic_year' => ($year - 1) . "/$year",
                'keywords' => 'graduation, graduands, ceremony, degrees awarded', 'keywords_fr' => 'remise des diplômes, diplômés, cérémonie, diplômes décernés',
                'description' => 'Graduands approved by Senate for the ' . self::ordinal($n, 'en') . " graduation ceremony held in November {$year}.",
                'description_fr' => 'Diplômés approuvés par le Sénat pour la ' . self::ordinal($n, 'fr') . " cérémonie de remise des diplômes de novembre {$year}.",
                'content' => 'The following candidates were approved by Senate to graduate at the ' . self::ordinal($n, 'en') . " graduation ceremony:\n\n"
                    . implode("\n", array_map(static fn ($s) => "{$s['name']} ({$s['sno']}) — " . self::PROGRAMMES[$s['prog']][0], $graduands)),
                'content_fr' => 'Les candidats suivants ont été autorisés par le Sénat à recevoir leur diplôme lors de la ' . self::ordinal($n, 'fr') . " cérémonie de remise des diplômes :\n\n"
                    . implode("\n", array_map(static fn ($s) => "{$s['name']} ({$s['sno']}) — " . self::progFr($s['prog']), $graduands)),
                'access_level' => 'public',
            ]);
        }
    }

    private function courseOutlines(): void
    {
        foreach (self::COURSES as $faculty => $courses) {
            shuffle($courses);
            foreach (array_slice($courses, 0, 4) as [$code, $name, $nameFr]) {
                $year = mt_rand(2022, 2026);
                $cu = $this->pick([3, 4]);
                [$lecturer, $lecturerFr] = $this->person(true);
                $this->save([
                    'reference_no' => $this->ref($faculty, $year),
                    'title' => "Course Outline — {$code} {$name}", 'title_fr' => "Plan de cours — {$code} {$nameFr}",
                    'document_type' => 'course_outline', 'dept' => $faculty, 'author' => $lecturer, 'author_fr' => $lecturerFr, 'date_created' => $this->date($year, 1, 9),
                    'academic_year' => "$year/" . ($year + 1),
                    'keywords' => 'course outline, syllabus, ' . strtolower($name), 'keywords_fr' => 'plan de cours, programme, ' . mb_strtolower($nameFr),
                    'description' => "Syllabus, learning outcomes and assessment of {$code} {$name}.",
                    'description_fr' => "Programme, acquis d'apprentissage et modalités d'évaluation de {$code} {$nameFr}.",
                    'content' => "Course unit: {$code} {$name}\nCredit units: {$cu}\n\nDescription: This course introduces students to the key concepts, methods and practice of " . strtolower($name)
                        . ".\n\nLearning outcomes: by the end of the course students should be able to explain the main concepts of " . strtolower($name) . ', apply them to practical problems and evaluate their results.'
                        . "\n\nAssessment: coursework 40% (assignments, tests and a mini-project), final examination 60%.",
                    'content_fr' => "Unité d'enseignement : {$code} {$nameFr}\nCrédits : {$cu}\n\nDescription : ce cours initie les étudiants aux concepts, méthodes et pratiques de la matière « {$nameFr} »."
                        . "\n\nAcquis d'apprentissage : à l'issue du cours, les étudiants doivent être capables d'en expliquer les concepts principaux, de les appliquer à des problèmes pratiques et d'en évaluer les résultats."
                        . "\n\nÉvaluation : contrôle continu 40 % (devoirs, tests et mini-projet), examen final 60 %.",
                    'access_level' => 'public',
                ]);
            }
        }
    }

    private function examResults(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $faculty = $this->pick(['FCI', 'FBM', 'FED', 'FHS']);
            [$code, $name, $nameFr] = $this->pick(self::COURSES[$faculty]);
            $year = mt_rand(2021, 2025);
            $sem = $this->pick([['Semester I', 'Semestre I'], ['Semester II', 'Semestre II']]);
            $ay = "$year/" . ($year + 1);
            $cohort = array_values(array_filter($this->students, fn ($s) => self::PROGRAMMES[$s['prog']][1] === $faculty && $s['entry'] <= $year));
            shuffle($cohort);
            $rows = implode("\n", array_map(fn ($s) => "{$s['sno']}  " . $this->pick(self::GRADES)[0], array_slice($cohort, 0, 8)));
            $candidates = mt_rand(40, 180);
            $pass = mt_rand(68, 97);
            $dist = [mt_rand(5, 20), mt_rand(10, 25), mt_rand(15, 30), mt_rand(10, 20), mt_rand(5, 15), mt_rand(2, 10)];
            $this->save([
                'reference_no' => $this->ref('EXM', $year + 1),
                'title' => "Examination Results — {$code} {$name} ({$sem[0]}, {$ay})",
                'title_fr' => "Résultats d'examen — {$code} {$nameFr} ({$sem[1]}, {$ay})",
                'document_type' => 'exam_results', 'dept' => 'EXM', 'author' => 'Examinations Office', 'author_fr' => self::deptFr('EXM'), 'date_created' => $this->date($year + 1, 1, 8),
                'academic_year' => $ay,
                'keywords' => 'examination results, grades, pass rate, ' . strtolower($code), 'keywords_fr' => "résultats d'examen, notes, taux de réussite, " . strtolower($code),
                'description' => "Provisional results of {$code} {$name}: {$candidates} candidates, {$pass}% pass rate.",
                'description_fr' => "Résultats provisoires de {$code} {$nameFr} : {$candidates} candidats, {$pass} % de réussite.",
                'content' => "Course unit: {$code} {$name}\nFaculty: " . self::DEPARTMENTS[$faculty] . "\nSemester: {$sem[0]}, {$ay}\nCandidates: {$candidates}\n\n"
                    . vsprintf('Grade distribution: A %d%%, B+ %d%%, B %d%%, C+ %d%%, C %d%%, F %d%%.', $dist) . "\n\nSample of results by registration number:\n{$rows}",
                'content_fr' => "Unité d'enseignement : {$code} {$nameFr}\nFaculté : " . self::deptFr($faculty) . "\nSemestre : {$sem[1]}, {$ay}\nCandidats : {$candidates}\n\n"
                    . vsprintf('Répartition des notes : A %d %%, B+ %d %%, B %d %%, C+ %d %%, C %d %%, F %d %%.', $dist) . "\n\nÉchantillon des résultats par numéro d'inscription :\n{$rows}",
                'access_level' => 'internal',
            ]);
        }
    }

    private function otherCorrespondence(): void
    {
        $letters = [
            ['Letter to the National Council for Higher Education on Programme Accreditation', "Lettre au Conseil national de l'enseignement supérieur sur l'accréditation des programmes", 'REG',
                'NCHE, accreditation, programmes', 'NCHE, accréditation, programmes',
                'The Registry submits revised curricula to the National Council for Higher Education (NCHE) for accreditation and requests a site visit.',
                "Le Service de la scolarité soumet les maquettes révisées au Conseil national de l'enseignement supérieur (NCHE) pour accréditation et demande une visite sur site."],
            ['Response to the Ministry of Education Request for Enrolment Statistics', "Réponse à la demande de statistiques d'inscription du ministère de l'Éducation", 'REG',
                'ministry, enrolment statistics, data request', "ministère, statistiques d'inscription, demande de données",
                'The Registry provides enrolment figures by programme, gender and nationality requested by the Ministry of Education and Sports.',
                "Le Service de la scolarité transmet les effectifs par programme, sexe et nationalité demandés par le ministère de l'Éducation et des Sports."],
            ['Request for Replacement of a Lost Academic Certificate', "Demande de remplacement d'un diplôme perdu", 'REG',
                'lost certificate, replacement, police report', 'diplôme perdu, duplicata, déclaration de perte',
                'A former student requests a replacement certificate; a police report and a sworn affidavit are attached.',
                'Un ancien étudiant demande un duplicata de son diplôme ; une déclaration de perte établie par la police et une déclaration sous serment sont jointes.'],
            ['Notice to the Bank on Change of Fees Collection Account', 'Notification à la banque du changement de compte de recouvrement des frais', 'FIN',
                'bank, fees collection, account', 'banque, recouvrement des frais, compte',
                'The Bursar notifies the bank of the new account to be used for collecting tuition from students.',
                "L'économe informe la banque du nouveau compte à utiliser pour encaisser les frais de scolarité des étudiants."],
            ['Invitation to Tender for the Records Centre Shelving', "Appel d'offres pour les rayonnages du centre d'archives", 'REG',
                'tender, procurement, records centre, shelving', "appel d'offres, marchés publics, centre d'archives, rayonnages",
                'Invitation to suppliers to bid for the supply and installation of mobile shelving in the records centre.',
                "Invitation aux fournisseurs à soumissionner pour la fourniture et l'installation de rayonnages mobiles au centre d'archives."],
        ];
        foreach ($letters as [$title, $titleFr, $dept, $kw, $kwFr, $summary, $summaryFr]) {
            foreach ([0, 1] as $copy) {
                if ($copy === 1 && !$this->chance(0.5)) {
                    continue;
                }
                $year = mt_rand(2020, 2026);
                $this->save([
                    'reference_no' => $this->ref($dept, $year),
                    'title' => $title . ($copy ? " ({$year})" : ''), 'title_fr' => $titleFr . ($copy ? " ({$year})" : ''),
                    'document_type' => 'correspondence', 'dept' => $dept,
                    'author' => 'Head, ' . self::DEPARTMENTS[$dept], 'author_fr' => 'Responsable — ' . self::deptFr($dept), 'date_created' => $this->date($year),
                    'keywords' => $kw, 'keywords_fr' => $kwFr, 'description' => $summary, 'description_fr' => $summaryFr,
                    'content' => "{$summary}\n\nThe letter was copied to the Vice Chancellor and the University Secretary, and a copy is kept on the correspondence file of the " . self::DEPARTMENTS[$dept] . '.',
                    'content_fr' => "{$summaryFr}\n\nUne copie de la lettre a été adressée au Vice-chancelier et au Secrétaire général de l'université ; un exemplaire est conservé dans le dossier de correspondance (" . self::deptFr($dept) . ').',
                    'access_level' => 'internal',
                ]);
            }
        }
    }
}
