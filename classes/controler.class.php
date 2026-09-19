<?php
/**
 * Application logic.
 *
 * Sits between the pages and the Model. Validates, decides, records to
 * the audit log, and sends mail. Returns plain arrays — it never echoes
 * or redirects, so the same method can back a form post and, later, an
 * API endpoint without changing.
 *
 * The class name keeps the original project's spelling so existing
 * `new Controler()` calls still work.
 */

declare(strict_types=1);

class Controler extends Model
{
    // =====================================================
    //  Registration & accounts
    // =====================================================

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function register(string $username, string $email, string $password, array $extra = []): array
    {
        $username = trim($username);
        $email    = trim($email);

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            return ['ok' => false, 'error' => 'Usernames are 3–50 characters and can use letters, numbers, dot, dash and underscore.'];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }

        $passwordCheck = $this->checkPasswordStrength($password);
        if ($passwordCheck !== null) {
            return ['ok' => false, 'error' => $passwordCheck];
        }

        $result = $this->createUser(
            $username,
            $email,
            $password,
            'user',
            $extra['full_name'] ?? null,
            $extra['phone'] ?? null,
            $extra['company'] ?? null
        );

        if ($result['ok']) {
            // Any request this person sent before signing up, using the
            // same address, now belongs to their account.
            $this->linkOrphanRequests((int) $result['id'], $email);
        }

        return $result;
    }

    /**
     * Returns an error string, or null when the password is acceptable.
     *
     * Length is the only rule that reliably correlates with strength, so
     * that is what is enforced. Forcing a symbol and a digit mostly
     * produces "Password1!" and a sticky note.
     */
    public function checkPasswordStrength(string $password): ?string
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }

        if (strlen($password) > 200) {
            return 'That password is too long.';
        }

        $tooCommon = ['password', '12345678', 'qwertyui', 'password1', 'abc12345', 'letmein1'];
        if (in_array(strtolower($password), $tooCommon, true)) {
            return 'That password is one of the first an attacker tries. Pick something else.';
        }

        return null;
    }

    private function linkOrphanRequests(int $userId, string $email): void
    {
        $this->execute(
            'UPDATE contact_messages SET user_id = ? WHERE user_id IS NULL AND email = ?',
            [$userId, $email]
        );
    }

    public function user(int $id): ?array
    {
        return $this->findUserById($id);
    }

    public function userByUsername(string $username): ?array
    {
        return $this->findUserByUsername($username);
    }

    public function updateProfile(int $userId, array $fields): array
    {
        $email = trim($fields['email'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }

        return $this->updateUserProfile(
            $userId,
            $this->nullIfBlank($fields['full_name'] ?? ''),
            $this->nullIfBlank($fields['phone'] ?? ''),
            $this->nullIfBlank($fields['company'] ?? ''),
            $email
        );
    }

    public function changePassword(int $userId, string $current, string $new, string $confirm): array
    {
        $user = $this->findUserById($userId);

        if ($user === null || !password_verify($current, $user['password'])) {
            return ['ok' => false, 'error' => 'Your current password is not correct.'];
        }

        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'The two new passwords do not match.'];
        }

        $problem = $this->checkPasswordStrength($new);
        if ($problem !== null) {
            return ['ok' => false, 'error' => $problem];
        }

        $this->updateUserPassword($userId, $new);

        return ['ok' => true];
    }

    public function users(int $page, string $search = '', string $role = ''): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listUsers($limit, $offset, $search, $role);
    }

    public function clientOptions(): array
    {
        return $this->listClientOptions();
    }

    public function changeUserRole(int $userId, string $role, array $actor): array
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            return ['ok' => false, 'error' => 'Unknown role.'];
        }

        if ($userId === (int) $actor['id'] && $role !== 'admin') {
            // Otherwise the last admin can lock themselves out of their
            // own admin panel with one misclick and no way back in.
            return ['ok' => false, 'error' => 'You cannot remove your own admin access.'];
        }

        $target = $this->findUserById($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'That user no longer exists.'];
        }

        $this->setUserRole($userId, $role);
        $this->audit($actor, 'user.role_change', 'user', (string) $userId,
            "Changed {$target['username']} from {$target['role']} to {$role}");

        return ['ok' => true];
    }

    public function changeUserStatus(int $userId, string $status, array $actor): array
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            return ['ok' => false, 'error' => 'Unknown status.'];
        }

        if ($userId === (int) $actor['id']) {
            return ['ok' => false, 'error' => 'You cannot suspend your own account.'];
        }

        $target = $this->findUserById($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'That user no longer exists.'];
        }

        $this->setUserStatus($userId, $status);
        $this->audit($actor, 'user.status_change', 'user', (string) $userId,
            ($status === 'suspended' ? 'Suspended ' : 'Reactivated ') . $target['username']);

        return ['ok' => true];
    }

    // =====================================================
    //  Password reset
    // =====================================================

    /**
     * Always reports success, whether or not the address is registered.
     *
     * Saying "no account with that email" turns this form into a free
     * tool for checking which of your customers has an account here.
     */
    public function requestPasswordReset(string $email): array
    {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }

        $user = $this->findUserByEmail($email);

        if ($user !== null) {
            $token = $this->createResetToken((int) $user['id']);
            $this->sendResetEmail($user, $token);
        }

        return ['ok' => true];
    }

    public function resetTokenIsValid(string $rawToken): bool
    {
        return $rawToken !== '' && $this->findValidReset($rawToken) !== null;
    }

    public function completePasswordReset(string $rawToken, string $password, string $confirm): array
    {
        $reset = $rawToken !== '' ? $this->findValidReset($rawToken) : null;

        if ($reset === null) {
            return ['ok' => false, 'error' => 'That reset link has expired or has already been used. Request a new one.'];
        }

        if ($password !== $confirm) {
            return ['ok' => false, 'error' => 'The two passwords do not match.'];
        }

        $problem = $this->checkPasswordStrength($password);
        if ($problem !== null) {
            return ['ok' => false, 'error' => $problem];
        }

        $this->updateUserPassword((int) $reset['user_id'], $password);
        $this->consumeResetToken($rawToken);

        // Someone who has just proved control of the mailbox should not
        // still be locked out by a previous brute-force attempt.
        $this->clearAttempts('username', $reset['username']);

        return ['ok' => true];
    }

    private function sendResetEmail(array $user, string $rawToken): void
    {
        $link = $this->absoluteUrl('reset_password.php?token=' . urlencode($rawToken));

        $subject = 'Reset your ' . APP_NAME . ' password';
        $body    = "Hi " . ($user['full_name'] ?: $user['username']) . ",\r\n\r\n"
                 . "Someone asked to reset the password on your account. If that was you, "
                 . "open this link to choose a new one:\r\n\r\n"
                 . $link . "\r\n\r\n"
                 . "The link stops working in " . RESET_TOKEN_MINUTES . " minutes.\r\n"
                 . "If it wasn't you, ignore this email — nothing has changed.\r\n";

        $this->sendMail($user['email'], $subject, $body);
    }

    // =====================================================
    //  Client requests
    // =====================================================

    /**
     * @return array{ok: bool, errors?: array, error?: string, reference?: string}
     */
    public function submitRequest(array $input, ?int $userId = null): array
    {
        $fullname = trim($input['fullname'] ?? '');
        $email    = trim($input['email'] ?? '');
        $phone    = trim($input['phone'] ?? '');
        $service  = trim($input['service'] ?? '');
        $message  = trim($input['message'] ?? '');

        $errors = [];

        if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 100) {
            $errors['fullname'] = 'Enter your name, 2–100 characters.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
            $errors['email'] = 'Enter a valid email address so we can reply.';
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)) {
            $errors['phone'] = 'Enter a phone number using digits, spaces, + and -.';
        }

        if (mb_strlen($service) > 100) {
            $errors['service'] = 'That service name is too long.';
        }

        if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
            $errors['message'] = 'Tell us a bit more — 10 to 2000 characters.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'error' => 'Check the highlighted fields.', 'errors' => $errors];
        }

        $created = $this->createRequest(
            $fullname,
            $email,
            $this->nullIfBlank($phone),
            $this->nullIfBlank($service),
            $message,
            $userId
        );

        $this->notifyAdminOfRequest($created['reference'], $fullname, $email, $phone, $service, $message);

        return ['ok' => true, 'reference' => $created['reference']];
    }

    private function notifyAdminOfRequest(
        string $reference,
        string $fullname,
        string $email,
        string $phone,
        string $service,
        string $message
    ): void {
        if (ADMIN_NOTIFY_EMAIL === '') {
            return;
        }

        $subject = "New request {$reference} from {$fullname}";
        $body    = "A new request came in through the website.\r\n\r\n"
                 . "Reference: {$reference}\r\n"
                 . "Name:      {$fullname}\r\n"
                 . "Email:     {$email}\r\n"
                 . "Phone:     " . ($phone !== '' ? $phone : 'not given') . "\r\n"
                 . "Service:   " . ($service !== '' ? $service : 'not specified') . "\r\n\r\n"
                 . "Message:\r\n{$message}\r\n\r\n"
                 . "Open it here: " . $this->absoluteUrl('admin_message.php?ref=' . urlencode($reference)) . "\r\n";

        $this->sendMail(ADMIN_NOTIFY_EMAIL, $subject, $body, $email);
    }

    public function requests(int $page, string $status = '', string $search = ''): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listRequests($limit, $offset, $status, $search);
    }

    public function requestsFor(array $user, int $page): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listRequestsForUser((int) $user['id'], (string) $user['email'], $limit, $offset);
    }

    public function request(int $id): ?array
    {
        return $this->findRequest($id);
    }

    public function requestStatusCounts(): array
    {
        return $this->countRequestsByStatus();
    }

    /** Mark 'new' as 'read' the first time an admin opens it. */
    public function markRequestRead(array $request): void
    {
        if ($request['status'] === 'new') {
            $this->setRequestStatus((int) $request['id'], 'read');
        }
    }

    public function updateRequestStatus(int $id, string $status, array $actor): array
    {
        $allowed = ['new', 'read', 'in_progress', 'replied', 'closed'];

        if (!in_array($status, $allowed, true)) {
            return ['ok' => false, 'error' => 'Unknown status.'];
        }

        $request = $this->findRequest($id);
        if ($request === null) {
            return ['ok' => false, 'error' => 'That request no longer exists.'];
        }

        $this->setRequestStatus($id, $status);
        $this->audit($actor, 'request.status_change', 'contact_message', (string) $id,
            "{$request['reference']} moved from {$request['status']} to {$status}");

        return ['ok' => true];
    }

    public function replyToRequest(int $id, string $reply, array $actor): array
    {
        $reply = trim($reply);

        if (mb_strlen($reply) < 2) {
            return ['ok' => false, 'error' => 'Write a reply before sending.'];
        }

        $request = $this->findRequest($id);
        if ($request === null) {
            return ['ok' => false, 'error' => 'That request no longer exists.'];
        }

        $this->saveRequestReply($id, $reply, (int) $actor['id']);

        $subject = 'Re: your request ' . $request['reference'];
        $body    = "Hi {$request['fullname']},\r\n\r\n"
                 . "{$reply}\r\n\r\n"
                 . "— " . APP_NAME . "\r\n"
                 . "Your reference: {$request['reference']}\r\n";

        $this->sendMail($request['email'], $subject, $body);

        $this->audit($actor, 'request.reply', 'contact_message', (string) $id,
            "Replied to {$request['reference']} ({$request['fullname']})");

        return ['ok' => true];
    }

    public function removeRequest(int $id, array $actor): array
    {
        $request = $this->findRequest($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'That request no longer exists.'];
        }

        $this->deleteRequest($id);

        // Recorded before the row is gone for good — the whole point of
        // an audit log is that it outlives the thing it describes.
        $this->audit($actor, 'request.delete', 'contact_message', (string) $id,
            "Deleted {$request['reference']} from {$request['fullname']}",
            ['email' => $request['email'], 'submitted_at' => $request['submitted_at']]);

        return ['ok' => true];
    }

    public function requestActivity(int $days = 14): array
    {
        return $this->requestsPerDay($days);
    }

    // =====================================================
    //  Documents
    // =====================================================

    /**
     * Validate and store one uploaded file.
     *
     * @param array $file One entry from $_FILES
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function storeUpload(array $file, array $meta, array $actor): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'No file was received.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['ok' => false, 'error' => 'Choose a file to upload.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['ok' => false, 'error' => 'That file is larger than the server allows.'];
            default:
                return ['ok' => false, 'error' => 'The upload did not finish. Try again.'];
        }

        if ($file['size'] <= 0) {
            return ['ok' => false, 'error' => 'That file is empty.'];
        }

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'Files must be under ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.'];
        }

        // is_uploaded_file confirms this really came through an HTTP
        // upload and isn't a path an attacker talked us into reading.
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'That upload could not be verified.'];
        }

        $originalName = $this->sanitiseFilename((string) $file['name']);
        $extension    = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed      = ALLOWED_UPLOAD_TYPES;

        if ($extension === '' || !array_key_exists($extension, $allowed)) {
            return ['ok' => false, 'error' => 'That file type is not accepted. Allowed: ' . implode(', ', array_keys($allowed)) . '.'];
        }

        // Trust the file's actual contents, not the extension or the
        // Content-Type header the browser sent — both are attacker-chosen.
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = (string) $finfo->file($file['tmp_name']);

        if (!in_array($realMime, $allowed[$extension], true)) {
            return ['ok' => false, 'error' => 'That file\'s contents do not match its .' . $extension . ' extension.'];
        }

        if (!is_dir(DOCUMENTS_PATH) && !mkdir(DOCUMENTS_PATH, 0750, true) && !is_dir(DOCUMENTS_PATH)) {
            return ['ok' => false, 'error' => 'Document storage is not writable. Check folder permissions.'];
        }

        // Stored under a random name so nothing about the client or the
        // file is guessable from disk, and two clients uploading
        // "statement.pdf" never collide.
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $target     = DOCUMENTS_PATH . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'The file could not be saved. Check folder permissions.'];
        }

        @chmod($target, 0640);

        $id = $this->createDocument([
            'owner_id'      => (int) $meta['owner_id'],
            'uploaded_by'   => (int) $actor['id'],
            'request_id'    => $meta['request_id'] ?? null,
            'direction'     => $meta['direction'] ?? 'from_client',
            'category'      => $this->nullIfBlank($meta['category'] ?? ''),
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'mime_type'     => $realMime,
            'size_bytes'    => (int) $file['size'],
            'sha256'        => hash_file('sha256', $target),
            'note'          => $this->nullIfBlank($meta['note'] ?? ''),
        ]);

        $this->audit($actor, 'document.upload', 'document', (string) $id,
            'Uploaded ' . $originalName, ['owner_id' => $meta['owner_id'], 'bytes' => $file['size']]);

        return ['ok' => true, 'id' => $id];
    }

    public function document(int $id): ?array
    {
        return $this->findDocument($id);
    }

    public function documents(int $page, ?int $ownerId = null, string $search = ''): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listDocuments($limit, $offset, $ownerId, $search);
    }

    public function documentCount(?int $ownerId = null): int
    {
        return $this->countDocuments($ownerId);
    }

    public function removeDocument(int $id, array $actor): array
    {
        $doc = $this->findDocument($id);

        if ($doc === null) {
            return ['ok' => false, 'error' => 'That file no longer exists.'];
        }

        $path = DOCUMENTS_PATH . '/' . $doc['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }

        $this->deleteDocumentRow($id);
        $this->audit($actor, 'document.delete', 'document', (string) $id,
            'Deleted ' . $doc['original_name'], ['owner_id' => $doc['owner_id']]);

        return ['ok' => true];
    }

    // =====================================================
    //  Invoices
    // =====================================================

    /**
     * Turn posted line items into clean numbers and totals.
     *
     * Money is handled as float only for the arithmetic and rounded to
     * two decimals before it ever reaches the database, where it lands in
     * a DECIMAL column. Storing money in a FLOAT column is how invoices
     * end up off by a cent.
     */
    public function buildInvoiceTotals(array $rawItems, float $taxRate): array
    {
        $items    = [];
        $subtotal = 0.0;

        foreach ($rawItems as $raw) {
            $description = trim((string) ($raw['description'] ?? ''));

            if ($description === '') {
                continue; // blank row from the form — skip it
            }

            $quantity  = round((float) ($raw['quantity'] ?? 0), 2);
            $unitPrice = round((float) ($raw['unit_price'] ?? 0), 2);
            $lineTotal = round($quantity * $unitPrice, 2);

            $items[] = [
                'description' => mb_substr($description, 0, 255),
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'line_total'  => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        $subtotal  = round($subtotal, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'items'      => $items,
            'subtotal'   => $subtotal,
            'tax_rate'   => $taxRate,
            'tax_amount' => $taxAmount,
            'total'      => round($subtotal + $taxAmount, 2),
        ];
    }

    public function saveInvoice(array $input, array $items, array $actor, ?int $invoiceId = null): array
    {
        $clientId = (int) ($input['client_id'] ?? 0);

        if ($clientId <= 0 || $this->findUserById($clientId) === null) {
            return ['ok' => false, 'error' => 'Choose a client for this invoice.'];
        }

        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one line item.'];
        }

        $issueDate = $this->validDate($input['issue_date'] ?? '') ?? date('Y-m-d');
        $dueDate   = $this->validDate($input['due_date'] ?? '') ?? date('Y-m-d', strtotime('+14 days'));

        if ($dueDate < $issueDate) {
            return ['ok' => false, 'error' => 'The due date cannot be before the issue date.'];
        }

        $taxRate = (float) ($input['tax_rate'] ?? VAT_RATE);
        if ($taxRate < 0 || $taxRate > 100) {
            return ['ok' => false, 'error' => 'The tax rate must be between 0 and 100.'];
        }

        $status  = in_array($input['status'] ?? '', ['draft', 'sent', 'paid', 'overdue', 'void'], true)
            ? $input['status']
            : 'draft';

        $totals = $this->buildInvoiceTotals($items, $taxRate);

        $payload = [
            'client_id'  => $clientId,
            'created_by' => (int) $actor['id'],
            'issue_date' => $issueDate,
            'due_date'   => $dueDate,
            'currency'   => CURRENCY,
            'subtotal'   => $totals['subtotal'],
            'tax_rate'   => $totals['tax_rate'],
            'tax_amount' => $totals['tax_amount'],
            'total'      => $totals['total'],
            'status'     => $status,
            'notes'      => $this->nullIfBlank($input['notes'] ?? ''),
        ];

        if ($invoiceId === null) {
            $payload['invoice_no'] = $this->nextInvoiceNumber();
            $newId = $this->createInvoice($payload, $totals['items']);

            $this->audit($actor, 'invoice.create', 'invoice', (string) $newId,
                "Created {$payload['invoice_no']} for " . CURRENCY . ' ' . number_format($totals['total'], 2));

            return ['ok' => true, 'id' => $newId];
        }

        $existing = $this->findInvoice($invoiceId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'That invoice no longer exists.'];
        }

        $this->updateInvoice($invoiceId, $payload, $totals['items']);
        $this->audit($actor, 'invoice.update', 'invoice', (string) $invoiceId,
            "Updated {$existing['invoice_no']}");

        return ['ok' => true, 'id' => $invoiceId];
    }

    public function invoice(int $id): ?array
    {
        return $this->findInvoice($id);
    }

    public function invoiceItems(int $invoiceId): array
    {
        return $this->findInvoiceItems($invoiceId);
    }

    public function invoices(int $page, ?int $clientId = null, string $status = '', string $search = ''): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listInvoices($limit, $offset, $clientId, $status, $search);
    }

    public function invoiceSummary(?int $clientId = null): array
    {
        $this->refreshOverdueInvoices();

        return $this->invoiceTotals($clientId);
    }

    public function revenueSeries(int $months = 6): array
    {
        return $this->revenueByMonth($months);
    }

    public function markInvoice(int $id, string $status, array $actor): array
    {
        if (!in_array($status, ['draft', 'sent', 'paid', 'overdue', 'void'], true)) {
            return ['ok' => false, 'error' => 'Unknown invoice status.'];
        }

        $invoice = $this->findInvoice($id);
        if ($invoice === null) {
            return ['ok' => false, 'error' => 'That invoice no longer exists.'];
        }

        $this->setInvoiceStatus($id, $status);
        $this->audit($actor, 'invoice.status_change', 'invoice', (string) $id,
            "{$invoice['invoice_no']} marked {$status}");

        if ($status === 'sent') {
            $this->sendInvoiceEmail($invoice);
        }

        return ['ok' => true];
    }

    public function removeInvoice(int $id, array $actor): array
    {
        $invoice = $this->findInvoice($id);

        if ($invoice === null) {
            return ['ok' => false, 'error' => 'That invoice no longer exists.'];
        }

        if ($invoice['status'] === 'paid') {
            // A paid invoice is a financial record. Voiding keeps the
            // number in the sequence and the history intact; deleting it
            // leaves a hole an auditor will ask about.
            return ['ok' => false, 'error' => 'A paid invoice cannot be deleted. Mark it void instead.'];
        }

        $this->deleteInvoiceRow($id);
        $this->audit($actor, 'invoice.delete', 'invoice', (string) $id,
            "Deleted {$invoice['invoice_no']}", ['total' => $invoice['total']]);

        return ['ok' => true];
    }

    private function sendInvoiceEmail(array $invoice): void
    {
        $link = $this->absoluteUrl('invoice_view.php?id=' . (int) $invoice['id']);

        $subject = 'Invoice ' . $invoice['invoice_no'] . ' from ' . APP_NAME;
        $body    = "Hi " . ($invoice['client_name'] ?: $invoice['client_username']) . ",\r\n\r\n"
                 . "Invoice {$invoice['invoice_no']} is ready.\r\n\r\n"
                 . "Amount due: " . $invoice['currency'] . ' ' . number_format((float) $invoice['total'], 2) . "\r\n"
                 . "Due date:   " . $invoice['due_date'] . "\r\n\r\n"
                 . "View it here: {$link}\r\n\r\n"
                 . "— " . APP_NAME . "\r\n";

        $this->sendMail($invoice['client_email'], $subject, $body);
    }

    // =====================================================
    //  Audit log
    // =====================================================

    /**
     * Record one action. Never throws: a failure to write the log must
     * not take down the operation the user was performing, but it does
     * go to the error log so the gap is visible.
     */
    public function audit(array $actor, string $action, ?string $entityType = null, ?string $entityId = null, ?string $summary = null, array $meta = []): void
    {
        try {
            $this->writeAuditEntry([
                'actor_id'    => isset($actor['id']) ? (int) $actor['id'] : null,
                'actor_name'  => (string) ($actor['username'] ?? 'system'),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'summary'     => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT'])
                    ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null,
            ]);
        } catch (Throwable $e) {
            error_log('[audit] could not write entry: ' . $e->getMessage());
        }
    }

    public function auditEntries(int $page, string $action = '', string $search = ''): array
    {
        $limit  = PER_PAGE;
        $offset = max(0, ($page - 1) * $limit);

        return $this->listAuditEntries($limit, $offset, $action, $search);
    }

    public function auditActions(): array
    {
        return $this->distinctAuditActions();
    }

    public function recentActivity(int $limit = 6): array
    {
        return $this->recentAuditEntries($limit);
    }

    // =====================================================
    //  Shared helpers
    // =====================================================

    /**
     * Send mail, or log it.
     *
     * PHP's mail() needs a working MTA. On XAMPP, `php -S`, and most
     * shared hosting it silently does nothing, which makes "the reset
     * email never arrived" impossible to debug. With MAIL_LOG_ONLY=true
     * every message is written to storage/mail.log instead, so the flow
     * is testable before SMTP exists. Swap in PHPMailer here for
     * production; nothing else in the app needs to change.
     */
    protected function sendMail(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $headers = 'From: ' . MAIL_FROM . "\r\n"
                 . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $headers .= 'Reply-To: ' . $replyTo . "\r\n";
        }

        if (MAIL_LOG_ONLY) {
            $entry = str_repeat('=', 60) . "\n"
                   . '[' . date('Y-m-d H:i:s') . "] TO: {$to}\n"
                   . "SUBJECT: {$subject}\n\n{$body}\n";

            if (!is_dir(STORAGE_PATH)) {
                @mkdir(STORAGE_PATH, 0750, true);
            }

            @file_put_contents(STORAGE_PATH . '/mail.log', $entry, FILE_APPEND | LOCK_EX);

            return true;
        }

        // Header injection guard: a newline in the subject would let an
        // attacker append their own headers and turn this into a relay.
        $subject = str_replace(["\r", "\n"], ' ', $subject);

        return @mail($to, $subject, $body, $headers);
    }

    protected function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

        return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
    }

    /**
     * Strip anything from an uploaded filename that could escape the
     * storage folder or confuse the browser on download.
     */
    protected function sanitiseFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N}\s._-]/u', '_', $name) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return $name === '' ? 'file' : mb_substr($name, 0, 200);
    }

    protected function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function validDate(string $value): ?string
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return ($date !== false && $date->format('Y-m-d') === $value) ? $value : null;
    }
}
