<?php

namespace Tests\Unit\Support;

use App\Support\SqlFile;
use PHPUnit\Framework\TestCase;

class SqlFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__, 3).'/database/farm2home.sql';
    }

    public function test_schema_section_contains_only_ddl_for_24_tables(): void
    {
        $statements = SqlFile::schemaStatements($this->path);

        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'CREATE TABLE')));
        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'DROP TABLE IF EXISTS')));
        $this->assertSame([], array_filter($statements, fn ($s) => str_starts_with($s, 'INSERT')));
        $this->assertSame([], array_filter($statements, fn ($s) => str_contains($s, 'CREATE DATABASE') || str_starts_with($s, 'USE ')));
    }

    public function test_sample_section_contains_24_inserts(): void
    {
        $statements = SqlFile::sampleStatements($this->path);

        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'INSERT INTO')));
        $this->assertSame('SET FOREIGN_KEY_CHECKS = 0', $statements[0]);
        $this->assertSame('SET FOREIGN_KEY_CHECKS = 1', end($statements));
    }

    public function test_statements_have_no_comment_lines_or_trailing_semicolons(): void
    {
        foreach ([...SqlFile::schemaStatements($this->path), ...SqlFile::sampleStatements($this->path)] as $statement) {
            $this->assertStringStartsNotWith('--', $statement);
            $this->assertStringEndsNotWith(';', $statement);
        }
    }
}
