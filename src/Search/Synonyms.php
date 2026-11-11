<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Query expansion with a small bilingual (English/French) thesaurus of the records office vocabulary.
 * Users say "exams", "cheating", "school fees" or "notes"; the records say "examination", "malpractice",
 * "tuition" or "relevé". A synonym counts for the same query word, with a slightly lower weight.
 * Extend this list with the zero-result queries shown on the Evaluation page.
 */
final class Synonyms
{
    public const WEIGHT = 0.8;

    private const GROUPS = [
        ['exam', 'exams', 'examination', 'examinations', 'examen', 'examens'],
        ['cheating', 'malpractice', 'impersonation', 'fraude', 'tricherie'],
        ['grades', 'results', 'transcript', 'marks', 'notes', 'resultats', 'releve'],
        ['fees', 'tuition', 'frais', 'scolarite'],
        ['contract', 'appointment', 'employment', 'contrat', 'nomination', 'emploi'],
        ['employee', 'staff', 'personnel', 'employe'],
        ['letter', 'correspondence', 'lettre', 'courrier', 'correspondance'],
        ['meeting', 'minutes', 'reunion', 'proces'],
        ['rules', 'regulations', 'policy', 'politique', 'reglement'],
        ['scholarship', 'bursary', 'waiver', 'bourse', 'exoneration'],
        ['graduates', 'graduands', 'graduation', 'diplomes', 'diplomation'],
        ['enrolment', 'registration', 'admission', 'inscription'],
        ['timetable', 'schedule', 'calendrier', 'horaire'],
    ];

    private static ?array $map = null;

    /** @return list<string> stems equivalent to $stem (excluding itself) */
    public static function for(string $stem): array
    {
        if (self::$map === null) {
            self::$map = [];
            foreach (self::GROUPS as $group) {
                $stems = array_values(array_unique(array_map([Tokenizer::class, 'stem'], $group)));
                foreach ($stems as $s) {
                    self::$map[$s] = array_values(array_unique(array_merge(self::$map[$s] ?? [], array_diff($stems, [$s]))));
                }
            }
        }
        return self::$map[$stem] ?? [];
    }
}
