<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller
{
    public $db;
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->db = load_class('database', 'database');
        lava_instance()->db = $this->db;
        $this->api = load_class('Api', 'libraries');
    }

    public function register()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || strlen($username) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->api->respond_error('Provide a username, valid email, and password of at least 8 characters.', 400);
        }

        $existing = $this->db->raw(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->api->respond_error('Username or email is already registered.', 409);
        }

        $this->db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user']
        );

        $this->api->respond(['message' => 'Account created. You can now sign in.'], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $identifier = trim((string) ($input['identifier'] ?? $input['username'] ?? $input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->api->respond_error('Username or email and password are required.', 400);
        }

        $user = $this->db->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$identifier, $identifier]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid username/email or password.', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('This account is inactive.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => $user['role'] === 'admin' ? ['read', 'write', 'delete'] : ['read'],
        ]);

        unset($user['password'], $user['is_active']);
        $user['id'] = (int) $user['id'];

        $this->api->respond([
            'message' => 'Signed in successfully.',
            'user' => $user,
            'tokens' => $tokens,
        ]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $refreshToken = (string) ($input['refresh_token'] ?? '');

        if ($refreshToken === '') {
            $this->api->respond_error('Refresh token is required.', 400);
        }

        $this->api->refresh_access_token($refreshToken);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();
        $input = $this->api->body();

        if (!empty($input['refresh_token'])) {
            $this->api->revoke_refresh_token((string) $input['refresh_token']);
        }

        $this->api->respond(['message' => 'Signed out successfully.']);
    }
}