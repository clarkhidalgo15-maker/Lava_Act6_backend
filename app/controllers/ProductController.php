<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
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

    private function require_user()
    {
        $payload = $this->api->require_jwt();
        $user = $this->db->raw(
            'SELECT id, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1',
            [(int) ($payload['sub'] ?? 0)]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('Unauthorized.', 401);
        }

        return $user;
    }

    private function require_admin()
    {
        $user = $this->require_user();
        if ($user['role'] !== 'admin') {
            $this->api->respond_error('Admin access required.', 403);
        }
    }

    private function validate_product(array $input, array $existing = [])
    {
        $name = trim((string) ($input['product_name'] ?? $existing['product_name'] ?? ''));
        $description = trim((string) ($input['description'] ?? $existing['description'] ?? ''));
        $priceInput = $input['price'] ?? $existing['price'] ?? null;
        $quantityInput = $input['quantity'] ?? $existing['quantity'] ?? null;

        if ($name === '' || strlen($name) > 100) {
            $this->api->respond_error('Product name is required and must be 100 characters or fewer.', 400);
        }
        if (!is_numeric($priceInput) || (float) $priceInput < 0 || (float) $priceInput > 99999999.99) {
            $this->api->respond_error('Price must be a valid non-negative amount.', 400);
        }
        $quantity = filter_var($quantityInput, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            $this->api->respond_error('Quantity must be a non-negative whole number.', 400);
        }

        return [
            'product_name' => $name,
            'description' => $description,
            'price' => number_format((float) $priceInput, 2, '.', ''),
            'quantity' => $quantity,
        ];
    }

    public function index()
    {
        $this->require_user();
        $products = $this->db->raw('SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY id DESC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->api->respond(['products' => $products]);
    }

    public function show($id)
    {
        $this->require_user();
        $product = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['product' => $product]);
    }

    public function create()
    {
        $this->require_admin();
        $this->api->require_method('POST');
        $product = $this->validate_product($this->api->body());

        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)',
            array_values($product)
        );
        $id = (int) $this->db->last_id();
        $created = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond(['message' => 'Product created.', 'product' => $created], 201);
    }

    public function update($id)
    {
        $this->require_admin();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        if (!in_array($method, ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed.', 405);
        }

        $existing = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [(int) $id])
            ->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $product = $this->validate_product($this->api->body(), $existing);
        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?',
            [...array_values($product), (int) $id]
        );
        $updated = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond(['message' => 'Product updated.', 'product' => $updated]);
    }

    public function delete($id)
    {
        $this->require_admin();
        $existing = $this->db->raw('SELECT id FROM products WHERE id = ? LIMIT 1', [(int) $id])
            ->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->db->raw('DELETE FROM products WHERE id = ?', [(int) $id]);
        $this->api->respond(['message' => 'Product deleted.', 'deleted_id' => (int) $id]);
    }
}