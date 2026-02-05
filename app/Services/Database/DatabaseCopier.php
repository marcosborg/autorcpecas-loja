<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;

class DatabaseCopier
{
    /**
     * Copia schema + dados de uma connection para outra (MySQL).
     *
     * @param  array{
     *   include_framework_tables?: bool,
     *   exclude_tables?: list<string>,
     *   chunk_size?: int,
     * }  $options
     */
    public function copy(string $fromConnection, string $toConnection, array $options = []): string
    {
        if ($fromConnection === $toConnection) {
            throw new \InvalidArgumentException('Origem e destino não podem ser a mesma connection.');
        }

        $chunkSize = (int) ($options['chunk_size'] ?? 500);
        $chunkSize = max(1, min(2000, $chunkSize));

        $includeFramework = (bool) ($options['include_framework_tables'] ?? false);

        $exclude = array_map(static fn ($t) => trim((string) $t), (array) ($options['exclude_tables'] ?? []));
        $exclude = array_values(array_filter($exclude, static fn ($t) => $t !== ''));

        if (! $includeFramework) {
            $exclude = array_values(array_unique([
                ...$exclude,
                'cache',
                'cache_locks',
                'sessions',
                'jobs',
                'failed_jobs',
                'job_batches',
                'password_reset_tokens',
                'personal_access_tokens',
            ]));
        }

        $from = DB::connection($fromConnection);
        $to = DB::connection($toConnection);

        $tables = $this->listTables($fromConnection);
        $tables = array_values(array_filter($tables, static fn ($t) => ! in_array($t, $exclude, true)));

        $out = [];
        $out[] = 'Copiar BD';
        $out[] = "- from: {$fromConnection} (".$from->getDatabaseName().')';
        $out[] = "- to: {$toConnection} (".$to->getDatabaseName().')';
        $out[] = '- tables: '.count($tables);
        $out[] = '';

        $to->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $out[] = "== {$table} ==";

            $createSql = $this->showCreateTable($fromConnection, $table);

            $to->statement('DROP TABLE IF EXISTS '.$this->quoteIdent($table));
            $to->statement($createSql);

            $count = $this->countRows($fromConnection, $table);
            $out[] = "rows: {$count}";

            if ($count > 0) {
                $this->copyTableData($fromConnection, $toConnection, $table, $chunkSize);
            }
        }

        $to->statement('SET FOREIGN_KEY_CHECKS=1');

        $out[] = '';
        $out[] = 'OK';

        return implode("\n", $out);
    }

    /**
     * @return list<string>
     */
    private function listTables(string $connection): array
    {
        $rows = DB::connection($connection)->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');

        $tables = [];

        foreach ($rows as $row) {
            $arr = (array) $row;
            $values = array_values($arr);
            $name = $values[0] ?? null;

            if (is_string($name) && $name !== '') {
                $tables[] = $name;
            }
        }

        sort($tables, SORT_STRING);

        return $tables;
    }

    private function showCreateTable(string $connection, string $table): string
    {
        $row = DB::connection($connection)->selectOne('SHOW CREATE TABLE '.$this->quoteIdent($table));

        if (! $row) {
            throw new \RuntimeException("Não foi possível obter CREATE TABLE de [{$table}].");
        }

        $values = array_values((array) $row);
        $sql = $values[1] ?? null;

        if (! is_string($sql) || trim($sql) === '') {
            throw new \RuntimeException("CREATE TABLE inválido para [{$table}].");
        }

        return $sql;
    }

    private function countRows(string $connection, string $table): int
    {
        $row = DB::connection($connection)->selectOne('SELECT COUNT(*) AS c FROM '.$this->quoteIdent($table));
        $c = is_object($row) ? (($row->c ?? null)) : null;

        return is_numeric($c) ? (int) $c : 0;
    }

    private function copyTableData(string $fromConnection, string $toConnection, string $table, int $chunkSize): void
    {
        $fromPdo = DB::connection($fromConnection)->getPdo();
        $stmt = $fromPdo->prepare('SELECT * FROM '.$this->quoteIdent($table));
        $stmt->execute();

        $chunk = [];

        while (true) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                break;
            }

            $chunk[] = $row;

            if (count($chunk) >= $chunkSize) {
                DB::connection($toConnection)->table($table)->insert($chunk);
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            DB::connection($toConnection)->table($table)->insert($chunk);
        }
    }

    private function quoteIdent(string $value): string
    {
        $value = str_replace('`', '``', $value);

        return '`'.$value.'`';
    }
}
