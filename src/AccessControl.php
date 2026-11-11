<?php

declare(strict_types=1);

namespace App;

/**
 * Role-based + attribute-based access control.
 *
 *  Role             | public | internal | confidential                 | restricted
 *  -----------------+--------+----------+------------------------------+-----------
 *  admin            |  yes   |   yes    | yes                          | yes
 *  records_officer  |  yes   |   yes    | yes                          | no
 *  dept_staff       |  yes   |   yes    | own department only          | no
 *  student          |  yes   |   no     | own records (student_no)     | no
 *
 * The same rule is applied twice: as a SQL condition inside the search query
 * ("security trimming": documents the user may not open never appear in results,
 * facets or counts) and as a PHP check before a document is opened or downloaded.
 */
final class AccessControl
{
    /** @return array{0:string, 1:list<mixed>} SQL condition and its parameters */
    public static function where(array $user, string $alias = 'd'): array
    {
        $a = $alias;
        return match ($user['role'] ?? '') {
            'admin' => ['1 = 1', []],
            'records_officer' => ["$a.access_level <> 'restricted'", []],
            'dept_staff' => [
                "($a.access_level IN ('public', 'internal') OR ($a.access_level = 'confidential' AND $a.department_id = ?))",
                [(int) $user['department_id']],
            ],
            'student' => [
                "($a.access_level = 'public' OR ($a.student_no = ? AND $a.access_level <> 'restricted'))",
                [(string) $user['student_no']],
            ],
            default => ['1 = 0', []],
        };
    }

    public static function canView(array $user, array $doc): bool
    {
        if ($doc['status'] === 'archived' && !self::canSeeArchived($user)) {
            return false;
        }
        $level = $doc['access_level'];
        return match ($user['role'] ?? '') {
            'admin' => true,
            'records_officer' => $level !== 'restricted',
            'dept_staff' => in_array($level, ['public', 'internal'], true)
                || ($level === 'confidential' && (int) $doc['department_id'] === (int) $user['department_id']),
            'student' => $level === 'public'
                || ($level !== 'restricted' && $doc['student_no'] !== null && $doc['student_no'] === $user['student_no']),
            default => false,
        };
    }

    public static function canManage(?array $user): bool
    {
        return in_array($user['role'] ?? '', ['admin', 'records_officer'], true);
    }

    public static function canSeeArchived(?array $user): bool
    {
        return self::canManage($user);
    }

    public static function isAdmin(?array $user): bool
    {
        return ($user['role'] ?? '') === 'admin';
    }

    /** Access levels a user may assign when uploading. */
    public static function assignableLevels(array $user): array
    {
        $levels = array_keys(Catalog::ACCESS);
        return self::isAdmin($user) ? $levels : array_values(array_diff($levels, ['restricted']));
    }
}
