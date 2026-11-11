<?php

declare(strict_types=1);

namespace App;

use App\Search\Indexer;
use PDO;

/** Creates the schema (SQLite or MySQL), loads the demo data and builds the search index. */
final class Installer
{
    private const TABLES = ['postings', 'terms', 'click_logs', 'search_logs', 'audit_logs', 'feedback', 'documents', 'users', 'departments'];

    public static function isInstalled(PDO $db): bool
    {
        try {
            $db->query('SELECT 1 FROM documents LIMIT 1');
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public static function install(PDO $db, bool $fresh = false): array
    {
        @set_time_limit(300);
        if ($fresh) {
            if (!Database::isSqlite($db)) {
                $db->exec('SET FOREIGN_KEY_CHECKS = 0');
            }
            foreach (self::TABLES as $table) {
                $db->exec("DROP TABLE IF EXISTS $table");
            }
            if (!Database::isSqlite($db)) {
                $db->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
        foreach (self::schema(Database::isSqlite($db)) as $sql) {
            $db->exec($sql);
        }
        Catalog::resetCache();
        $db->beginTransaction();
        $counts = Seeder::run($db);
        $db->commit();
        $index = (new Indexer($db))->rebuild();
        return $counts + ['index' => $index];
    }

    /** @return list<string> */
    public static function schema(bool $sqlite): array
    {
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $ref = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
        $long = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
        $end = $sqlite ? ')' : ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $bin = $sqlite ? '' : ' COLLATE utf8mb4_bin';

        return [
            "CREATE TABLE departments (
                id $id,
                code VARCHAR(10) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL
            $end",

            "CREATE TABLE users (
                id $id,
                username VARCHAR(60) NOT NULL UNIQUE,
                full_name VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL,
                department_id $ref NULL,
                student_no VARCHAR(30) NULL,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                FOREIGN KEY (department_id) REFERENCES departments (id)
            $end",

            "CREATE TABLE documents (
                id $id,
                reference_no VARCHAR(40) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                title_fr VARCHAR(255) NULL,
                document_type VARCHAR(30) NOT NULL,
                department_id $ref NOT NULL,
                programme VARCHAR(150) NULL,
                student_no VARCHAR(30) NULL,
                student_name VARCHAR(120) NULL,
                author VARCHAR(150) NULL,
                author_fr VARCHAR(150) NULL,
                date_created DATE NOT NULL,
                doc_year INT NOT NULL,
                academic_year VARCHAR(9) NULL,
                keywords VARCHAR(500) NULL,
                keywords_fr VARCHAR(500) NULL,
                description TEXT NULL,
                description_fr TEXT NULL,
                content $long NULL,
                content_fr $long NULL,
                language VARCHAR(5) NOT NULL DEFAULT 'both',
                access_level VARCHAR(15) NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'active',
                retention_period VARCHAR(60) NULL,
                file_name VARCHAR(255) NULL,
                file_path VARCHAR(255) NULL,
                file_mime VARCHAR(100) NULL,
                file_size INT NULL,
                created_by $ref NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                doc_length DOUBLE NOT NULL DEFAULT 0,
                search_text $long NULL,
                FOREIGN KEY (department_id) REFERENCES departments (id)
            $end",
            'CREATE INDEX idx_documents_type ON documents (document_type)',
            'CREATE INDEX idx_documents_department ON documents (department_id)',
            'CREATE INDEX idx_documents_year ON documents (doc_year)',
            'CREATE INDEX idx_documents_access ON documents (access_level)',
            'CREATE INDEX idx_documents_date ON documents (date_created)',
            'CREATE INDEX idx_documents_student ON documents (student_no)',

            "CREATE TABLE terms (
                id $id,
                term VARCHAR(64)$bin NOT NULL UNIQUE,
                display VARCHAR(64) NOT NULL,
                df INT NOT NULL DEFAULT 0
            $end",

            "CREATE TABLE postings (
                term_id $ref NOT NULL,
                doc_id $ref NOT NULL,
                tf DOUBLE NOT NULL,
                PRIMARY KEY (term_id, doc_id),
                FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
                FOREIGN KEY (doc_id) REFERENCES documents (id) ON DELETE CASCADE
            $end",
            'CREATE INDEX idx_postings_doc ON postings (doc_id)',

            "CREATE TABLE search_logs (
                id $id,
                user_id $ref NULL,
                query VARCHAR(255) NOT NULL,
                filters TEXT NULL,
                results_count INT NOT NULL,
                duration_ms DOUBLE NOT NULL,
                created_at DATETIME NOT NULL
            $end",

            "CREATE TABLE click_logs (
                id $id,
                search_id $ref NOT NULL,
                user_id $ref NULL,
                document_id $ref NOT NULL,
                position INT NOT NULL,
                created_at DATETIME NOT NULL
            $end",

            "CREATE TABLE audit_logs (
                id $id,
                user_id $ref NULL,
                action VARCHAR(40) NOT NULL,
                document_id $ref NULL,
                details TEXT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL
            $end",
            'CREATE INDEX idx_audit_document ON audit_logs (document_id)',

            "CREATE TABLE feedback (
                id $id,
                user_id $ref NULL,
                task VARCHAR(10) NULL,
                found INT NULL,
                clicks INT NULL,
                ease INT NOT NULL,
                relevance INT NOT NULL,
                speed INT NOT NULL,
                filters_useful INT NOT NULL,
                satisfaction INT NOT NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL
            $end",
        ];
    }
}
