<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

/**
 * Un motor SQL mínimo en memoria, para las tablas propias del plugin.
 *
 * El plugin guarda el índice de sincronización y las homologaciones en tablas
 * suyas, y las consulta con SQL escrito a mano. Sin algo que ejecute ese SQL,
 * las pruebas de esos repositorios sólo pueden afirmar sobre la cadena
 * generada, que es justo lo que no importa: importa qué filas salen.
 *
 * Entiende sólo las formas que el plugin usa. **Cualquier otra lanza una
 * excepción a propósito**: un motor que devolviese un array vacío ante una
 * consulta que no supo leer convertiría cada prueba en un falso positivo.
 */
final class FakeSqlEngine
{
    /** @var array<string,list<array<string,mixed>>> tabla => filas */
    private array $tables = [];

    /** @var array<string,int> tabla => siguiente id */
    private array $nextId = [];

    public int $insertId = 0;

    /** @var list<string> Consultas SELECT ejecutadas, para poder contarlas. */
    public array $queries = [];

    /** @var array<string,list<string>> tabla => columnas de su clave única */
    private array $uniqueKeys = [];

    /**
     * Declara la clave única de una tabla.
     *
     * Hace falta cuando el código delega la deduplicación en la base de datos
     * —un `INSERT IGNORE`—: sin la clave, el motor aceptaría el duplicado y la
     * prueba daría por bueno un comportamiento que en producción no ocurre.
     *
     * @param list<string> $columns
     */
    public function declareUniqueKey(string $table, array $columns): void
    {
        $this->uniqueKeys[$table] = $columns;
    }

    /**
     * Ejecuta un `INSERT IGNORE INTO tabla (cols) VALUES (vals)`.
     *
     * @return int Filas insertadas: 0 si la clave única lo rechazó.
     */
    public function insertIgnore(string $sql): int
    {
        $sql = trim((string) preg_replace('/\s+/', ' ', $sql));
        if (!preg_match('/^INSERT IGNORE INTO (\S+) \((.+?)\) VALUES \((.+)\)$/i', $sql, $parts)) {
            throw new \RuntimeException('El motor de pruebas no entiende este INSERT: ' . $sql);
        }

        [, $table, $columnList, $valueList] = $parts;
        $columns = array_map('trim', explode(',', $columnList));
        $values = array_map(
            static fn(string $v): string|int => preg_match("/^'(.*)'$/s", trim($v), $m) === 1
                ? $m[1]
                : (int) trim($v),
            explode(',', $valueList)
        );

        if (count($columns) !== count($values)) {
            throw new \RuntimeException('Columnas y valores no cuadran en: ' . $sql);
        }

        $row = array_combine($columns, $values);

        foreach ($this->tables[$table] ?? [] as $existing) {
            $collides = true;
            foreach ($this->uniqueKeys[$table] ?? [] as $keyColumn) {
                if ((string) ($existing[$keyColumn] ?? '') !== (string) ($row[$keyColumn] ?? '')) {
                    $collides = false;
                    break;
                }
            }
            if ($collides && ($this->uniqueKeys[$table] ?? []) !== []) {
                return 0;
            }
        }

        $this->insert($table, $row);

        return 1;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $id = $this->nextId[$table] ?? 1;
        $this->nextId[$table] = $id + 1;
        $this->insertId = $id;
        $this->tables[$table][] = array_merge(['id' => $id], $data);

        return 1;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $affected = 0;
        foreach ($this->tables[$table] ?? [] as $index => $row) {
            if (!$this->matches($row, $where)) {
                continue;
            }
            $this->tables[$table][$index] = array_merge($row, $data);
            $affected++;
        }

        return $affected;
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        $affected = 0;
        foreach ($this->tables[$table] ?? [] as $index => $row) {
            if (!$this->matches($row, $where)) {
                continue;
            }
            unset($this->tables[$table][$index]);
            $affected++;
        }
        $this->tables[$table] = array_values($this->tables[$table] ?? []);

        return $affected;
    }

    /**
     * Ejecuta un SELECT y devuelve las filas resultantes como arrays.
     *
     * @return list<array<string,mixed>>
     */
    public function select(string $sql): array
    {
        $sql = trim((string) preg_replace('/\s+/', ' ', $sql));
        $this->queries[] = $sql;

        if (!preg_match('/^SELECT (.+?) FROM (\S+)(.*)$/i', $sql, $parts)) {
            throw new \RuntimeException('El motor de pruebas no entiende esta consulta: ' . $sql);
        }

        [, $columns, $table, $rest] = $parts;
        $rows = $this->tables[trim($table, '`')] ?? [];

        $rest = trim($rest);
        $where = $this->extractClause($rest, 'WHERE');
        $groupBy = $this->extractClause($rest, 'GROUP BY');
        $orderBy = $this->extractClause($rest, 'ORDER BY');
        $limit = $this->extractClause($rest, 'LIMIT');

        if ($where !== null) {
            $conditions = $this->parseWhere($where);
            $rows = array_values(array_filter(
                $rows,
                fn(array $row): bool => $this->satisfies($row, $conditions)
            ));
        }

        // Las expresiones `DATE(col)` se materializan como una columna más,
        // para que agrupar y ordenar por ellas no necesite un caso especial.
        $rows = $this->materializeExpressions($rows, $columns . ' ' . (string) $groupBy . ' ' . (string) $orderBy);

        if ($groupBy !== null) {
            $rows = $this->group($rows, array_map(
                fn(string $c): string => $this->columnAlias(trim($c)),
                explode(',', $groupBy)
            ));
        }

        // Los alias del SELECT (`... AS day`) tienen que existir como columna
        // antes de ordenar: `ORDER BY day ASC` se refiere al alias, no a la
        // expresión.
        $rows = $this->applyAliases($rows, $columns);

        if ($orderBy !== null) {
            $rows = $this->sort($rows, array_map('trim', explode(',', $orderBy)));
        }

        if ($limit !== null) {
            $rows = $this->applyLimit($rows, $limit);
        }

        return $this->project($rows, trim($columns));
    }

    /** Devuelve el trozo de la consulta que sigue a una palabra clave. */
    private function extractClause(string $rest, string $keyword): ?string
    {
        $stops = ['WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT'];
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b (.+?)(?= \b(?:' . implode('|', array_map(
            static fn(string $s): string => preg_quote($s, '/'),
            array_diff($stops, [$keyword])
        )) . ')\b|$)/i';

        return preg_match($pattern, $rest, $match) === 1 ? trim($match[1]) : null;
    }

    /** @return list<array{column:string,operator:string,value:mixed}> */
    private function parseWhere(string $where): array
    {
        $conditions = [];
        foreach (preg_split('/\bAND\b/i', $where) ?: [] as $condition) {
            $condition = trim($condition);
            if ($condition === '') {
                continue;
            }
            if (!preg_match("/^(\\S+) (=|>=|<=|>|<|!=) (?:'(.*)'|(-?\\d+))$/s", $condition, $match)) {
                throw new \RuntimeException('Condición no soportada por el motor de pruebas: ' . $condition);
            }
            // El grupo 4 sólo existe cuando el valor venía sin comillas, es
            // decir, cuando lo puso un marcador %d.
            $conditions[] = [
                'column'   => trim($match[1], '`'),
                'operator' => $match[2],
                'value'    => isset($match[4]) && $match[4] !== '' ? (int) $match[4] : $match[3],
            ];
        }

        return $conditions;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{column:string,operator:string,value:mixed}> $conditions
     */
    private function satisfies(array $row, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $actual = $row[$condition['column']] ?? null;
            $expected = $condition['value'];

            $result = match ($condition['operator']) {
                // La igualdad es laxa a propósito: MySQL no distingue entre el
                // entero 5 y la cadena '5' en una columna numérica, y las
                // pruebas no deberían tener que adivinar cuál de los dos llegó.
                '='  => (string) $actual === (string) $expected,
                '!=' => (string) $actual !== (string) $expected,
                '>=' => $this->compare($actual, $expected) >= 0,
                '<=' => $this->compare($actual, $expected) <= 0,
                '>'  => $this->compare($actual, $expected) > 0,
                '<'  => $this->compare($actual, $expected) < 0,
                default => throw new \RuntimeException('Operador no soportado: ' . $condition['operator']),
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $a @param mixed $b */
    private function compare($a, $b): int
    {
        return is_numeric($a) && is_numeric($b)
            ? ($a <=> $b)
            : strcmp((string) $a, (string) $b);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $where
     */
    private function matches(array $row, array $where): bool
    {
        foreach ($where as $column => $expected) {
            // La comparación es laxa a propósito: MySQL no distingue entre el
            // entero 5 y la cadena '5' en una columna numérica, y las pruebas
            // no deberían tener que adivinar cuál de los dos llegó.
            if ((string) ($row[$column] ?? '') !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private function group(array $rows, array $columns): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = implode("\0", array_map(static fn(string $c): string => (string) ($row[$c] ?? ''), $columns));
            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                $grouped[$key]['total'] = 0;
            }
            $grouped[$key]['total']++;
        }

        return array_values($grouped);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function applyAliases(array $rows, string $columns): array
    {
        foreach (explode(',', $columns) as $column) {
            $column = trim($column);
            if (preg_match('/^COUNT\(\*\) AS (\S+)$/i', $column, $match) === 1) {
                foreach ($rows as $index => $row) {
                    $rows[$index][$match[1]] = $row['total'] ?? null;
                }
                continue;
            }
            if (preg_match('/^(.+?) AS (\S+)$/i', $column, $match) === 1) {
                $source = $this->columnAlias($match[1]);
                foreach ($rows as $index => $row) {
                    $rows[$index][$match[2]] = $row[$source] ?? null;
                }
            }
        }

        return $rows;
    }

    /**
     * Nombre con el que una expresión queda disponible como columna.
     * `DATE(visited_at)` pasa a ser `date_visited_at`.
     */
    private function columnAlias(string $expression): string
    {
        return preg_match('/^DATE\((\w+)\)$/i', trim($expression), $match) === 1
            ? 'date_' . strtolower($match[1])
            : trim($expression);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function materializeExpressions(array $rows, string $sql): array
    {
        if (preg_match_all('/DATE\((\w+)\)/i', $sql, $matches) === 0) {
            return $rows;
        }

        foreach (array_unique($matches[1]) as $column) {
            foreach ($rows as $index => $row) {
                $value = (string) ($row[$column] ?? '');
                $rows[$index]['date_' . strtolower($column)] = substr($value, 0, 10);
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private function sort(array $rows, array $columns): array
    {
        $keys = array_map(function (string $column): array {
            $parts = preg_split('/\s+/', trim($column)) ?: [];

            return [
                'column'     => $this->columnAlias((string) ($parts[0] ?? '')),
                'descending' => strcasecmp((string) ($parts[1] ?? 'ASC'), 'DESC') === 0,
            ];
        }, $columns);

        usort($rows, function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                $comparison = $this->compare($a[$key['column']] ?? '', $b[$key['column']] ?? '');
                if ($comparison !== 0) {
                    return $key['descending'] ? -$comparison : $comparison;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function applyLimit(array $rows, string $limit): array
    {
        if (!preg_match('/^(\d+)(?: OFFSET (\d+))?$/i', $limit, $match)) {
            throw new \RuntimeException('LIMIT no soportado por el motor de pruebas: ' . $limit);
        }

        return array_slice($rows, (int) ($match[2] ?? 0), (int) $match[1]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function project(array $rows, string $columns): array
    {
        if ($columns === '*') {
            return $rows;
        }

        if (strcasecmp($columns, 'COUNT(*)') === 0) {
            return [['COUNT(*)' => count($rows)]];
        }

        if (preg_match('/^COUNT\(DISTINCT (\w+)\)$/i', $columns, $match) === 1) {
            $values = array_unique(array_map(
                static fn(array $row): string => (string) ($row[$match[1]] ?? ''),
                $rows
            ));

            return [['count' => count($values)]];
        }

        if (preg_match('/^DISTINCT (\S+)$/i', $columns, $match) === 1) {
            $values = array_values(array_unique(array_map(
                static fn(array $row): string => (string) ($row[$match[1]] ?? ''),
                $rows
            )));

            return array_map(static fn(string $v): array => [$match[1] => $v], $values);
        }

        $selected = [];
        foreach (explode(',', $columns) as $column) {
            $column = trim($column);
            if (preg_match('/^COUNT\(\*\)(?: AS (\S+))?$/i', $column, $match) === 1) {
                $selected[$match[1] ?? 'count'] = 'total';
                continue;
            }
            // `expresión AS alias`, con la expresión ya materializada arriba.
            if (preg_match('/^(.+?) AS (\S+)$/i', $column, $match) === 1) {
                $selected[$match[2]] = $this->columnAlias($match[1]);
                continue;
            }
            $column = preg_replace('/^\w+\./', '', $column) ?? $column;
            if (!preg_match('/^\w+$/', $column)) {
                throw new \RuntimeException('Columna no soportada por el motor de pruebas: ' . $column);
            }
            $selected[$column] = $column;
        }

        return array_map(static function (array $row) use ($selected): array {
            $projected = [];
            foreach ($selected as $alias => $source) {
                $projected[$alias] = $row[$source] ?? null;
            }

            return $projected;
        }, $rows);
    }

    /** @return list<array<string,mixed>> Todas las filas de una tabla, para afirmar sobre ellas. */
    public function rows(string $table): array
    {
        return array_values($this->tables[$table] ?? []);
    }
}
