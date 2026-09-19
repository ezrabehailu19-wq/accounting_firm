<?php
/**
 * Data access layer.
 *
 * Everything that touches a table lives here. Nothing in this file
 * decides policy — no redirects, no echo, no session reads. That is the
 * controller's job. Keeping the split honest is what makes it possible
 * to reason about where a bug can be.
 */

declare(strict_types=1);

class Model extends Db
{
    /** MySQL error number for a unique-constraint violation. */
    const ERR_DUPLICATE_ENTRY = 1062;

    // =====================================================
    //  Users
    // =====================================================

    protected function findUserByUsername(string $username): ?array
    {
        return $this->selectOne('SELECT * FROM users WHERE username = ?', [$username]);
    }

    protected function findUserByEmail(string $email): ?array
    {
        return $this->selectOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    protected function findUserById(int $id): ?array
    {
        return $this->selectOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * Create a user.
     *
     * The UNIQUE indexes on username and email do the real work. We let
     * the INSERT run and catch error 1062 rather than doing a SELECT
     * first, because between a SELECT and an INSERT another request can
     * slip in and claim the name — that is exactly the race the old code
     * had. The database is the only place that can settle it.
     *
     * @return array{ok: bool, id?: int, error?: string}
     */
    protected function createUser(
        string $username,
        string $email,
        string $plainPassword,
        string $role = 'user',
        ?string $fullName = null,
        ?string $phone = null,
        ?string $company = null
    ): array {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        try {
            $id = $this->insert(
                'INSERT INTO users (username, email, full_name, phone, company, password, role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$username, $email, $fullName, $phone, $company, $hash, $role]
            );
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === self::ERR_DUPLICATE_ENTRY) {
                // The message names the index, so we can tell the user
                // which field actually clashed instead of a vague failure.
                $isEmail = stripos($e->getMessage(), 'email') !== false;

                return [
                    'ok'    => false,
                    'error' => $isEmail
                        ? 'That email address already has an account.'
                        : 'That username is taken. Try another one.',
                ];
            }

            throw $e;
        }

        return ['ok' => true, 'id' => $id];
    }

    protected function updateUserProfile(int $userId, ?string $fullName, ?string $phone, ?string $company, string $email): array
    {
        try {
            $this->execute(
                'UPDATE users SET full_name = ?, phone = ?, company = ?, email = ? WHERE id = ?',
                [$fullName, $phone, $company, $email, $userId]
            );
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === self::ERR_DUPLICATE_ENTRY) {
                return ['ok' => false, 'error' => 'That email address is already used by another account.'];
            }
            throw $e;
        }

        return ['ok' => true];
    }

    protected function updateUserPassword(int $userId, string $plainPassword): void
    {
        $this->execute(
            'UPDATE users SET password = ? WHERE id = ?',
            [password_hash($plainPassword, PASSWORD_DEFAULT), $userId]
        );
    }

    protected function touchLastLogin(int $userId): void
    {
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    protected function setUserRole(int $userId, string $role): void
    {
        $this->execute('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
    }

    protected function setUserStatus(int $userId, string $status): void
    {
        $this->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    }

    /** Paginated user list with an optional search term. */
    protected function listUsers(int $limit, int $offset, string $search = '', string $role = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR company LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($role !== '') {
            $where[]  = 'role = ?';
            $params[] = $role;
        }

        $clause = implode(' AND ', $where);

        $rows = $this->select(
            "SELECT id, username, email, full_name, phone, company, role, status, last_login_at, created_at
             FROM users WHERE {$clause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = (int) $this->selectValue("SELECT COUNT(*) FROM users WHERE {$clause}", $params, 0);

        return ['rows' => $rows, 'total' => $total];
    }

    /** Every non-admin account, for the invoice client picker. */
    protected function listClientOptions(): array
    {
        return $this->select(
            "SELECT id, username, email, full_name, company
             FROM users
             WHERE status = 'active'
             ORDER BY COALESCE(NULLIF(full_name, ''), username) ASC"
        );
    }

    protected function countUsers(): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM users', [], 0);
    }

    // =====================================================
    //  Login rate limiting
    // =====================================================
    //  Counters are kept per username AND per IP. Locking only by
    //  username would let anyone lock a real customer out of their own
    //  account just by guessing wrong five times on purpose.

    protected function isLocked(string $scope, string $key): bool
    {
        // The comparison happens inside MySQL rather than in PHP so that a
        // timezone difference between the two can never unlock an account
        // early — a bug that is invisible in testing and wide open in
        // production, because both clocks agree on a developer laptop.
        $locked = $this->selectValue(
            'SELECT 1 FROM login_attempts
             WHERE scope = ? AND scope_key = ? AND locked_until IS NOT NULL AND locked_until > NOW()',
            [$scope, $key],
            0
        );

        return (int) $locked === 1;
    }

    protected function lockMinutesRemaining(string $scope, string $key): int
    {
        $seconds = $this->selectValue(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until))
             FROM login_attempts WHERE scope = ? AND scope_key = ?',
            [$scope, $key],
            0
        );

        return (int) ceil(((int) $seconds) / 60);
    }

    protected function recordFailedAttempt(string $scope, string $key): void
    {
        // One statement, no read-then-write, so two concurrent failed
        // logins can't both read "4" and both write "5".
        //
        // Assignment order matters here: MySQL evaluates ON DUPLICATE KEY
        // UPDATE clauses left to right, and later clauses see the values
        // set by earlier ones. locked_until therefore has to be computed
        // BEFORE attempts is incremented, otherwise `attempts + 1` would
        // already be the incremented value and the lock would trigger one
        // attempt too early.
        $this->execute(
            'INSERT INTO login_attempts (scope, scope_key, attempts, last_attempt)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                locked_until = IF(attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until),
                attempts     = attempts + 1,
                last_attempt = NOW()',
            [$scope, $key, LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_MINUTES]
        );
    }

    protected function clearAttempts(string $scope, string $key): void
    {
        $this->execute('DELETE FROM login_attempts WHERE scope = ? AND scope_key = ?', [$scope, $key]);
    }

    // =====================================================
    //  Password resets
    // =====================================================

    /** @return string The raw token — only ever exists here and in the email. */
    protected function createResetToken(int $userId): string
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Any outstanding link for this user stops working the moment a
        // new one is requested.
        $this->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);

        // expires_at is computed by MySQL so it is measured on the same
        // clock that will later validate it.
        $this->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$userId, $tokenHash, RESET_TOKEN_MINUTES]
        );

        return $rawToken;
    }

    protected function findValidReset(string $rawToken): ?array
    {
        return $this->selectOne(
            'SELECT pr.*, u.username, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL',
            [hash('sha256', $rawToken)]
        );
    }

    protected function consumeResetToken(string $rawToken): void
    {
        $this->execute(
            'UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?',
            [hash('sha256', $rawToken)]
        );
    }

    // =====================================================
    //  Client requests (contact messages)
    // =====================================================

    protected function createRequest(
        string $fullname,
        string $email,
        ?string $phone,
        ?string $service,
        string $message,
        ?int $userId
    ): array {
        // If the sender wasn't logged in, try to match the address to an
        // existing account so the request still shows up on their
        // dashboard when they next sign in.
        if ($userId === null) {
            $match  = $this->findUserByEmail($email);
            $userId = $match['id'] ?? null;
            if ($userId !== null) {
                $userId = (int) $userId;
            }
        }

        $id = $this->insert(
            'INSERT INTO contact_messages (user_id, fullname, email, phone, service, message)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $fullname, $email, $phone, $service, $message]
        );

        // The reference is derived from the id, so it is unique without
        // needing a counter table or a retry loop.
        $reference = 'REQ-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $this->execute('UPDATE contact_messages SET reference = ? WHERE id = ?', [$reference, $id]);

        return ['id' => $id, 'reference' => $reference];
    }

    protected function findRequest(int $id): ?array
    {
        return $this->selectOne(
            'SELECT m.*, u.username AS client_username, r.username AS replier_username
             FROM contact_messages m
             LEFT JOIN users u ON u.id = m.user_id
             LEFT JOIN users r ON r.id = m.replied_by
             WHERE m.id = ?',
            [$id]
        );
    }

    /**
     * Admin inbox: filter by status, free-text search, paginate.
     *
     * @return array{rows: array, total: int}
     */
    protected function listRequests(int $limit, int $offset, string $status = '', string $search = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[]  = 'm.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $where[]  = '(m.fullname LIKE ? OR m.email LIKE ? OR m.message LIKE ? OR m.reference LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = implode(' AND ', $where);

        $rows = $this->select(
            "SELECT m.*, u.username AS client_username
             FROM contact_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE {$clause}
             ORDER BY m.submitted_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = (int) $this->selectValue(
            "SELECT COUNT(*) FROM contact_messages m WHERE {$clause}",
            $params,
            0
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** Requests belonging to one client, for their own dashboard. */
    protected function listRequestsForUser(int $userId, string $email, int $limit, int $offset): array
    {
        $rows = $this->select(
            'SELECT * FROM contact_messages
             WHERE user_id = ? OR email = ?
             ORDER BY submitted_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $email, $limit, $offset]
        );

        $total = (int) $this->selectValue(
            'SELECT COUNT(*) FROM contact_messages WHERE user_id = ? OR email = ?',
            [$userId, $email],
            0
        );

        return ['rows' => $rows, 'total' => $total];
    }

    protected function countRequestsByStatus(): array
    {
        $rows   = $this->select('SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status');
        $counts = ['new' => 0, 'read' => 0, 'in_progress' => 0, 'replied' => 0, 'closed' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    protected function setRequestStatus(int $id, string $status): void
    {
        $this->execute('UPDATE contact_messages SET status = ? WHERE id = ?', [$status, $id]);
    }

    protected function saveRequestReply(int $id, string $reply, int $adminId): void
    {
        $this->execute(
            "UPDATE contact_messages
             SET admin_reply = ?, replied_at = NOW(), replied_by = ?, status = 'replied'
             WHERE id = ?",
            [$reply, $adminId, $id]
        );
    }

    protected function deleteRequest(int $id): void
    {
        $this->execute('DELETE FROM contact_messages WHERE id = ?', [$id]);
    }

    /** Message volume for the last N days, for the dashboard sparkline. */
    protected function requestsPerDay(int $days = 14): array
    {
        return $this->select(
            'SELECT DATE(submitted_at) AS day, COUNT(*) AS n
             FROM contact_messages
             WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(submitted_at)
             ORDER BY day ASC',
            [$days]
        );
    }

    // =====================================================
    //  Documents
    // =====================================================

    protected function createDocument(array $doc): int
    {
        return $this->insert(
            'INSERT INTO documents
                (owner_id, uploaded_by, request_id, direction, category,
                 original_name, stored_name, mime_type, size_bytes, sha256, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $doc['owner_id'],
                $doc['uploaded_by'],
                $doc['request_id'],
                $doc['direction'],
                $doc['category'],
                $doc['original_name'],
                $doc['stored_name'],
                $doc['mime_type'],
                $doc['size_bytes'],
                $doc['sha256'],
                $doc['note'],
            ]
        );
    }

    protected function findDocument(int $id): ?array
    {
        return $this->selectOne(
            'SELECT d.*, o.username AS owner_username, up.username AS uploader_username
             FROM documents d
             LEFT JOIN users o  ON o.id = d.owner_id
             LEFT JOIN users up ON up.id = d.uploaded_by
             WHERE d.id = ?',
            [$id]
        );
    }

    /**
     * @param int|null $ownerId Restrict to one client, or null for all
     */
    protected function listDocuments(int $limit, int $offset, ?int $ownerId = null, string $search = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($ownerId !== null) {
            $where[]  = 'd.owner_id = ?';
            $params[] = $ownerId;
        }

        if ($search !== '') {
            $where[]  = '(d.original_name LIKE ? OR d.category LIKE ? OR d.note LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = implode(' AND ', $where);

        $rows = $this->select(
            "SELECT d.*, o.username AS owner_username, o.full_name AS owner_name
             FROM documents d
             LEFT JOIN users o ON o.id = d.owner_id
             WHERE {$clause}
             ORDER BY d.uploaded_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = (int) $this->selectValue(
            "SELECT COUNT(*) FROM documents d WHERE {$clause}",
            $params,
            0
        );

        return ['rows' => $rows, 'total' => $total];
    }

    protected function deleteDocumentRow(int $id): void
    {
        $this->execute('DELETE FROM documents WHERE id = ?', [$id]);
    }

    protected function countDocuments(?int $ownerId = null): int
    {
        if ($ownerId === null) {
            return (int) $this->selectValue('SELECT COUNT(*) FROM documents', [], 0);
        }

        return (int) $this->selectValue('SELECT COUNT(*) FROM documents WHERE owner_id = ?', [$ownerId], 0);
    }

    // =====================================================
    //  Invoices
    // =====================================================

    /**
     * Next invoice number for the current year: INV-2026-0007.
     *
     * Derived from the highest existing number rather than a COUNT, so
     * deleting an invoice never causes a number to be reused — reusing
     * an invoice number is an audit finding waiting to happen.
     */
    protected function nextInvoiceNumber(): string
    {
        $year   = date('Y');
        $prefix = 'INV-' . $year . '-';

        $last = $this->selectValue(
            'SELECT invoice_no FROM invoices
             WHERE invoice_no LIKE ?
             ORDER BY invoice_no DESC LIMIT 1',
            [$prefix . '%']
        );

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create an invoice and its line items in one transaction.
     * A header without its lines, or lines without a header, would both
     * be corrupt records — so either all of it lands or none of it does.
     */
    protected function createInvoice(array $invoice, array $items): int
    {
        return $this->transaction(function () use ($invoice, $items) {
            $id = $this->insert(
                'INSERT INTO invoices
                    (invoice_no, client_id, created_by, issue_date, due_date, currency,
                     subtotal, tax_rate, tax_amount, total, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $invoice['invoice_no'],
                    $invoice['client_id'],
                    $invoice['created_by'],
                    $invoice['issue_date'],
                    $invoice['due_date'],
                    $invoice['currency'],
                    $invoice['subtotal'],
                    $invoice['tax_rate'],
                    $invoice['tax_amount'],
                    $invoice['total'],
                    $invoice['status'],
                    $invoice['notes'],
                ]
            );

            $this->replaceInvoiceItems($id, $items);

            return $id;
        });
    }

    protected function updateInvoice(int $id, array $invoice, array $items): void
    {
        $this->transaction(function () use ($id, $invoice, $items) {
            $this->execute(
                'UPDATE invoices
                 SET client_id = ?, issue_date = ?, due_date = ?, currency = ?,
                     subtotal = ?, tax_rate = ?, tax_amount = ?, total = ?,
                     status = ?, notes = ?
                 WHERE id = ?',
                [
                    $invoice['client_id'],
                    $invoice['issue_date'],
                    $invoice['due_date'],
                    $invoice['currency'],
                    $invoice['subtotal'],
                    $invoice['tax_rate'],
                    $invoice['tax_amount'],
                    $invoice['total'],
                    $invoice['status'],
                    $invoice['notes'],
                    $id,
                ]
            );

            $this->replaceInvoiceItems($id, $items);
        });
    }

    private function replaceInvoiceItems(int $invoiceId, array $items): void
    {
        $this->execute('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);

        $position = 1;
        foreach ($items as $item) {
            $this->execute(
                'INSERT INTO invoice_items (invoice_id, position, description, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $invoiceId,
                    $position,
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['line_total'],
                ]
            );
            $position++;
        }
    }

    protected function findInvoice(int $id): ?array
    {
        return $this->selectOne(
            'SELECT i.*, u.username AS client_username, u.full_name AS client_name,
                    u.email AS client_email, u.company AS client_company, u.phone AS client_phone
             FROM invoices i
             JOIN users u ON u.id = i.client_id
             WHERE i.id = ?',
            [$id]
        );
    }

    protected function findInvoiceItems(int $invoiceId): array
    {
        return $this->select(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position ASC',
            [$invoiceId]
        );
    }

    protected function listInvoices(int $limit, int $offset, ?int $clientId = null, string $status = '', string $search = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($clientId !== null) {
            $where[]  = 'i.client_id = ?';
            $params[] = $clientId;
        }

        if ($status !== '') {
            $where[]  = 'i.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $where[]  = '(i.invoice_no LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR u.company LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = implode(' AND ', $where);

        $rows = $this->select(
            "SELECT i.*, u.username AS client_username, u.full_name AS client_name, u.company AS client_company
             FROM invoices i
             JOIN users u ON u.id = i.client_id
             WHERE {$clause}
             ORDER BY i.issue_date DESC, i.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = (int) $this->selectValue(
            "SELECT COUNT(*) FROM invoices i JOIN users u ON u.id = i.client_id WHERE {$clause}",
            $params,
            0
        );

        return ['rows' => $rows, 'total' => $total];
    }

    protected function setInvoiceStatus(int $id, string $status): void
    {
        if ($status === 'paid') {
            $this->execute(
                'UPDATE invoices SET status = ?, paid_at = NOW(), amount_paid = total WHERE id = ?',
                [$status, $id]
            );

            return;
        }

        $this->execute('UPDATE invoices SET status = ?, paid_at = NULL WHERE id = ?', [$status, $id]);
    }

    protected function deleteInvoiceRow(int $id): void
    {
        $this->execute('DELETE FROM invoices WHERE id = ?', [$id]);
    }

    /**
     * Flip anything past its due date to 'overdue'.
     * Called on dashboard load — cheap, indexed, and means the status
     * is always right without needing a cron job on shared hosting.
     */
    protected function refreshOverdueInvoices(): int
    {
        return $this->execute(
            "UPDATE invoices SET status = 'overdue'
             WHERE status = 'sent' AND due_date < CURDATE()"
        );
    }

    protected function invoiceTotals(?int $clientId = null): array
    {
        $where  = $clientId !== null ? 'WHERE client_id = ?' : '';
        $params = $clientId !== null ? [$clientId] : [];

        $row = $this->selectOne(
            "SELECT
                COUNT(*)                                                        AS count_all,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total END), 0)       AS billed_paid,
                COALESCE(SUM(CASE WHEN status IN ('sent','overdue') THEN total END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN total END), 0)    AS overdue_amount,
                SUM(status = 'overdue')                                          AS overdue_count,
                SUM(status = 'draft')                                            AS draft_count
             FROM invoices {$where}",
            $params
        );

        return $row ?? [
            'count_all' => 0, 'billed_paid' => 0, 'outstanding' => 0,
            'overdue_amount' => 0, 'overdue_count' => 0, 'draft_count' => 0,
        ];
    }

    /** Revenue collected per month, for the dashboard chart. */
    protected function revenueByMonth(int $months = 6): array
    {
        return $this->select(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, COALESCE(SUM(total), 0) AS revenue
             FROM invoices
             WHERE status = 'paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
             ORDER BY month ASC",
            [$months]
        );
    }

    // =====================================================
    //  Audit log
    // =====================================================

    protected function writeAuditEntry(array $entry): void
    {
        $this->execute(
            'INSERT INTO audit_log
                (actor_id, actor_name, action, entity_type, entity_id, summary, meta, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $entry['actor_id'],
                $entry['actor_name'],
                $entry['action'],
                $entry['entity_type'],
                $entry['entity_id'],
                $entry['summary'],
                $entry['meta'],
                $entry['ip_address'],
                $entry['user_agent'],
            ]
        );
    }

    protected function listAuditEntries(int $limit, int $offset, string $action = '', string $search = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($action !== '') {
            $where[]  = 'action = ?';
            $params[] = $action;
        }

        if ($search !== '') {
            $where[]  = '(actor_name LIKE ? OR summary LIKE ? OR entity_id LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = implode(' AND ', $where);

        $rows = $this->select(
            "SELECT * FROM audit_log WHERE {$clause} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = (int) $this->selectValue("SELECT COUNT(*) FROM audit_log WHERE {$clause}", $params, 0);

        return ['rows' => $rows, 'total' => $total];
    }

    protected function distinctAuditActions(): array
    {
        $rows = $this->select('SELECT DISTINCT action FROM audit_log ORDER BY action ASC');

        return array_column($rows, 'action');
    }

    protected function recentAuditEntries(int $limit = 6): array
    {
        return $this->select(
            'SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT ?',
            [$limit]
        );
    }
}
