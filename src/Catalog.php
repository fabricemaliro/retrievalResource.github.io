<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Controlled vocabularies used as metadata (document types, access levels, roles). */
final class Catalog
{
    public const TYPES = [
        'transcript' => 'Academic Transcript',
        'admission_letter' => 'Admission Letter',
        'student_file' => 'Student Registration File',
        'fee_statement' => 'Fee Statement',
        'exam_results' => 'Examination Results',
        'minutes' => 'Meeting Minutes',
        'policy' => 'Policy',
        'memo' => 'Memo',
        'staff_contract' => 'Staff Contract',
        'financial_report' => 'Financial Report',
        'graduation_list' => 'Graduation List',
        'course_outline' => 'Course Outline',
        'correspondence' => 'Correspondence',
        'disciplinary' => 'Disciplinary Record',
    ];

    /** Retention schedule (records management): how long each type must be kept. */
    public const RETENTION = [
        'transcript' => 'Permanent',
        'admission_letter' => '7 years after graduation',
        'student_file' => 'Permanent',
        'fee_statement' => '7 years',
        'exam_results' => 'Permanent',
        'minutes' => 'Permanent',
        'policy' => 'Until superseded + 5 years',
        'memo' => '3 years',
        'staff_contract' => '7 years after end of employment',
        'financial_report' => '10 years',
        'graduation_list' => 'Permanent',
        'course_outline' => 'Until superseded',
        'correspondence' => '5 years',
        'disciplinary' => '10 years',
    ];

    public const ACCESS = [
        'public' => ['Public', 'Every signed-in user, including students'],
        'internal' => ['Internal', 'All university staff'],
        'confidential' => ['Confidential', 'Records Office and the owning department; a student sees only their own records'],
        'restricted' => ['Restricted', 'Records Manager only'],
    ];

    public const ROLES = [
        'admin' => 'Records Manager',
        'records_officer' => 'Records Officer',
        'dept_staff' => 'Department Staff',
        'student' => 'Student',
    ];

    private static ?array $departments = null;

    public static function typeLabel(?string $type): string
    {
        return __(self::TYPES[$type] ?? (string) $type);
    }

    /** @return array<int, array{id:int, code:string, name:string}> */
    public static function departments(PDO $db): array
    {
        if (self::$departments === null) {
            self::$departments = [];
            foreach ($db->query('SELECT id, code, name FROM departments ORDER BY name') as $row) {
                self::$departments[(int) $row['id']] = ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => __($row['name']), 'name_en' => $row['name']];
            }
        }
        return self::$departments;
    }

    public static function accessLabel(string $level): string
    {
        return __(self::ACCESS[$level][0] ?? $level);
    }

    public static function accessDescription(string $level): string
    {
        return __(self::ACCESS[$level][1] ?? '');
    }

    public static function roleLabel(string $role): string
    {
        return __(self::ROLES[$role] ?? $role);
    }

    public static function resetCache(): void
    {
        self::$departments = null;
    }
}
