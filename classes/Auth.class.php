<?php
/**
 * Authentication.
 *
 * This replaces the old View class, which was named for rendering but
 * actually did login, session setup and redirects all at once. Those are
 * three different jobs; only one of them belongs here.
 *
 * Notably, this class no longer sends redirect headers. The page that
 * called it decides where to go, which means login() can be tested and
 * reused without the side effect of ending the request.
 */

declare(strict_types=1);

class Auth extends Model
{
    /**
     * Attempt a login.
     *
     * @return array{ok: bool, user?: array, error?: string}
     */
    public function attempt(string $username, string $password): array
    {
        $username = trim($username);
        $ip       = $this->clientIp();

        // Two separate counters. Locking only on username lets anyone
        // lock a real customer out of their own account by failing five
        // times on purpose; locking only on IP misses distributed
        // guessing. Checking both closes each gap.
        if ($this->isLocked('ip', $ip)) {
            $minutes = $this->lockMinutesRemaining('ip', $ip);

            return ['ok' => false, 'error' => "Too many attempts from this connection. Try again in {$minutes} minute(s)."];
        }

        if ($username !== '' && $this->isLocked('username', $username)) {
            $minutes = $this->lockMinutesRemaining('username', $username);

            return ['ok' => false, 'error' => "This account is temporarily locked. Try again in {$minutes} minute(s)."];
        }

        $user = $username !== '' ? $this->findUserByUsername($username) : null;

        // The same message and the same amount of work whether the
        // username exists or not. Returning "no such user" faster than
        // "wrong password" is a timing side channel that tells an
        // attacker which usernames are real.
        if ($user === null) {
            password_verify($password, '$2y$10$usesomesillystringfoeswoeswoeswoeswoeswoeswoeswoeswoeswoesw');
            $this->registerFailure($username, $ip);

            return ['ok' => false, 'error' => 'That username and password do not match.'];
        }

        if (!password_verify($password, $user['password'])) {
            $this->registerFailure($username, $ip);

            return ['ok' => false, 'error' => 'That username and password do not match.'];
        }

        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'This account is suspended. Contact the office to reactivate it.'];
        }

        // If PHP's default cost has increased since this hash was made,
        // quietly upgrade it now that we have the plaintext in hand.
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->updateUserPassword((int) $user['id'], $password);
        }

        $this->clearAttempts('username', $username);
        $this->clearAttempts('ip', $ip);
        $this->touchLastLogin((int) $user['id']);

        $this->startSessionFor($user);

        return ['ok' => true, 'user' => $user];
    }

    private function registerFailure(string $username, string $ip): void
    {
        if ($username !== '') {
            $this->recordFailedAttempt('username', mb_substr($username, 0, 100));
        }

        $this->recordFailedAttempt('ip', $ip);
    }

    /**
     * Establish the logged-in session.
     *
     * session_regenerate_id() is the important line. Without it, an
     * attacker who can set a visitor's session id before they log in
     * (session fixation) ends up sharing the authenticated session.
     */
    private function startSessionFor(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['loggedin']    = true;
        $_SESSION['user_id']     = (int) $user['id'];
        $_SESSION['username']    = $user['username'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['full_name']   = $user['full_name'] ?? $user['username'];
        $_SESSION['login_at']    = time();
        $_SESSION['last_seen']   = time();
        // Bound to the browser so a stolen cookie replayed from another
        // client is a little harder to use.
        $_SESSION['fingerprint'] = self::fingerprint();
    }

    public static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /**
     * Best-effort client IP.
     *
     * X-Forwarded-For is only consulted when TRUST_PROXY is on, because
     * any client can send that header — behind no proxy it is simply a
     * value the attacker picked, and trusting it defeats IP rate limiting.
     */
    private function clientIp(): string
    {
        if (env_bool('TRUST_PROXY', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);

            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
