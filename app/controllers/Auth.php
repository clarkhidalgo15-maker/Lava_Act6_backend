<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller {
    private const ADMIN_USERNAME = 'admin';
    private const ADMIN_PASSWORD = 'admin123';

    public $db;
    public $api;

    public function __construct()
    {
        parent::__construct();
        $this->db = load_class('database', 'database');
        lava_instance()->db = $this->db;
        $this->api = load_class('Api', 'libraries');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index()
    {
        if (!empty($_SESSION['user'])) {
            redirect('dashboard');
        }

        redirect('login');
    }

    public function login_page()
    {
        if (!empty($_SESSION['user'])) {
            redirect('dashboard');
        }

        $this->call->view('auth/login');
    }

    public function register_page()
    {
        if (!empty($_SESSION['user'])) {
            redirect('dashboard');
        }

        $this->call->view('auth/register');
    }

    public function dashboard()
    {
        if (empty($_SESSION['user'])) {
            redirect('login');
        }

        $this->call->view('dashboard', ['user' => $_SESSION['user']]);
    }

    public function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        redirect('login');
    }

    public function api_login()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();

        $username = trim((string)($input['username'] ?? $input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->api->respond_error('Username/email and password are required.', 400);
        }

        $stmt = $this->db->raw(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $username]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user && $username === self::ADMIN_USERNAME && hash_equals(self::ADMIN_PASSWORD, $password)) {
            $this->db->raw(
                "INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)",
                [self::ADMIN_USERNAME, 'admin@lavalust.local', password_hash(self::ADMIN_PASSWORD, PASSWORD_BCRYPT)]
            );
            $user = $this->db->raw(
                'SELECT * FROM users WHERE username = ? LIMIT 1',
                [self::ADMIN_USERNAME]
            )->fetch(PDO::FETCH_ASSOC);
        }

        $isStaticAdmin = $user && $user['username'] === self::ADMIN_USERNAME;
        $validPassword = $isStaticAdmin
            ? hash_equals(self::ADMIN_PASSWORD, $password)
            : ($user && password_verify($password, $user['password']));

        if (!$user || !$validPassword) {
            $this->api->respond_error('Invalid credentials.', 401);
        }

        if ((int)($user['is_active'] ?? 1) !== 1) {
            $this->api->respond_error('This account is inactive.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $isStaticAdmin ? 'admin' : ($user['role'] ?? 'user'),
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $isStaticAdmin ? 'admin' : ($user['role'] ?? 'user'),
        ];

        $this->api->respond([
            'message' => 'Login successful.',
            'user' => $_SESSION['user'],
            'tokens' => $tokens,
        ]);
    }

    public function api_register()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();

        $username = trim((string)($input['username'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            $this->api->respond_error('Username, email, and password are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('Please enter a valid email address.', 400);
        }

        if (strlen($password) < 6) {
            $this->api->respond_error('Password must be at least 6 characters long.', 400);
        }

        $existing = $this->db->raw(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->api->respond_error('Username or email already exists.', 409);
        }

        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'user',
            'is_active' => 1,
        ];

        $userModel = load_class('User', 'models');
        $insertedId = $userModel->insert($userData);

        $this->api->respond([
            'message' => 'User registered successfully.',
            'user_id' => $insertedId,
        ], 201);
    }
}
