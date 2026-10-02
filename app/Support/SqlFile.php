<?php

namespace App\Support;

/**
 * Splits database/farm2home.sql into executable statements.
 * The file has two sections separated by marker comments.
 */
final class SqlFile
{
    private const SCHEMA_START = '-- 0. Laravel core tables';

    private const SAMPLE_START = '-- SAMPLE DATA';

    /** @return list<string> */
    public static function schemaStatements(string $path): array
    {
        $sql = self::read($path);
        $start = strpos($sql, self::SCHEMA_START);
        $end = strpos($sql, self::SAMPLE_START);

        return self::split(substr($sql, $start, $end - $start));
    }

    /** @return list<string> */
    public static function sampleStatements(string $path): array
    {
        $sql = self::read($path);

        return self::split(substr($sql, strpos($sql, self::SAMPLE_START)));
    }

    /**
     * Git on Windows may convert line endings to CRLF, so normalise first.
     */
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /** @return list<string> */
    private static function split(string $sql): array
    {
        $sql = preg_replace('/^--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(";\n", $sql."\n"))));
    }
}
