<?php
/**
 * Database access.
 *
 * Two things changed from the original version, both of which matter:
 *
 * 1. The connection is opened once and reused. The old conn() built a
 *    brand new mysqli on every single call, so rendering one admin page
 *    with a handful of model calls opened a handful of TCP connections
 *    and threw them away. Under any real traffic that exhausts
 *    max_connections.
 *
 * 2. mysqli now throws on error instead of returning false. Silent
 *    failure is the worst possible default here: the old code could
 *    fail to insert a row and still report success to the user.
 */

declare(strict_types=1);

class Db
{
    /** @var mysqli|null Shared across every Model instance in the request. */
    private static $connection = null;

    /**
     * @throws RuntimeException when the database is unreachable
     */
    protected function conn(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        // Turn mysqli warnings into exceptions so nothing fails quietly.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        } catch (Throwable $e) {
            error_log('[db] connection failed: ' . $e->getMessage());

            throw new RuntimeException(
                'The database is not reachable. Check your .env settings and that MySQL is running.',
                0,
                $e
            );
        }

        // utf8mb4 so Amharic text, accented names and emoji all round-trip.
        $conn->set_charset('utf8mb4');

        self::$connection = $conn;

        return $conn;
    }

    /**
     * Run a prepared statement and hand back the result set.
     *
     * Every query in this application goes through here or through
     * execute(). There is no code path that concatenates user input into
     * SQL, which is what keeps injection off the table entirely.
     *
     * @param string $sql    SQL with ? placeholders
     * @param array  $params Values to bind, in order
     */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->prepared($sql, $params);
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $stmt->close();

        return $rows;
    }

    /** Fetch exactly one row, or null when nothing matched. */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);

        return $rows[0] ?? null;
    }

    /** Fetch a single scalar value from the first column of the first row. */
    protected function selectValue(string $sql, array $params = [], $default = null)
    {
        $row = $this->selectOne($sql, $params);

        if ($row === null) {
            return $default;
        }

        return reset($row);
    }

    /**
     * Run an INSERT / UPDATE / DELETE.
     *
     * @return int Rows affected
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt     = $this->prepared($sql, $params);
        $affected = $stmt->affected_rows;

        $stmt->close();

        return $affected;
    }

    /** Run an INSERT and return the new row's id. */
    protected function insert(string $sql, array $params = []): int
    {
        $stmt = $this->prepared($sql, $params);
        $id   = (int) $this->conn()->insert_id;

        $stmt->close();

        return $id;
    }

    /**
     * Prepare, bind and execute. Bind types are inferred from the PHP type
     * of each value, which avoids the class of bug where someone writes
     * "sss" for four parameters.
     */
    private function prepared(string $sql, array $params): mysqli_stmt
    {
        $stmt = $this->conn()->prepare($sql);

        if ($params !== []) {
            $types  = '';
            $values = [];

            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    // Everything else — including null — binds as a string.
                    // mysqli sends a PHP null as a real SQL NULL, so nullable
                    // columns work without a special case.
                    $types .= 's';
                }

                $values[] = $param;
            }

            // bind_param takes its arguments by reference, so the array has
            // to be built out of references rather than copies. Iterating
            // over a separate key list (instead of over $values itself)
            // avoids aliasing the array while it is being walked.
            $bindArgs = [$types];

            foreach (array_keys($values) as $key) {
                $bindArgs[] = &$values[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        }

        $stmt->execute();

        return $stmt;
    }

    /** Run a closure inside a transaction, rolling back if it throws. */
    protected function transaction(callable $work)
    {
        $conn = $this->conn();
        $conn->begin_transaction();

        try {
            $result = $work();
            $conn->commit();

            return $result;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
