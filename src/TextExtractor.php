<?php

declare(strict_types=1);

namespace App;

/** Extracts indexable text from uploaded files (txt, md, docx, simple PDFs). */
final class TextExtractor
{
    public const ALLOWED = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'txt' => 'text/plain', 'md' => 'text/markdown'];

    public static function extract(string $path, string $extension): string
    {
        $text = match ($extension) {
            'txt', 'md' => (string) file_get_contents($path),
            'docx' => self::docx($path),
            'pdf' => self::pdf($path),
            default => '',
        };
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
        return trim(mb_substr($text, 0, 200000));
    }

    private static function docx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", ' ', "\n"], $xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Best-effort extraction for text-based PDFs (scanned PDFs need OCR and return nothing). */
    private static function pdf(string $path): string
    {
        $data = (string) file_get_contents($path);
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                $content = $decoded !== false ? $decoded : $stream;
                if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj|(T\*|Td|TD|ET)/s', $content, $ops, PREG_SET_ORDER)) {
                    foreach ($ops as $op) {
                        if (!empty($op[3])) {
                            $text .= "\n";
                        } elseif (!empty($op[2])) {
                            $text .= self::pdfString($op[2]);
                        } elseif (!empty($op[1]) && preg_match_all('/\((.*?)(?<!\\\\)\)/s', $op[1], $parts)) {
                            $text .= implode('', array_map([self::class, 'pdfString'], $parts[1]));
                        }
                    }
                }
            }
        }
        return preg_match('/[A-Za-z]{3,}/', $text) ? $text : '';
    }

    private static function pdfString(string $s): string
    {
        return stripcslashes(str_replace(['\\(', '\\)'], ['(', ')'], $s));
    }
}
