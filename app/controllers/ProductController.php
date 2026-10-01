<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller {
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

    private function require_auth_user()
    {
        $payload = $this->api->require_jwt();

        $userId = (int)($payload['sub'] ?? 0);
        if ($userId <= 0) {
            $this->api->respond_error('Unauthorized user.', 401);
        }

        $stmt = $this->db->raw('SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1', [$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('User not found.', 401);
        }

        return $user;
    }

    public function list_products()
    {
        $this->require_auth_user();

        $stmt = $this->db->raw('SELECT * FROM products ORDER BY id DESC');
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond([
            'products' => $products,
        ]);
    }

    public function show_product($id)
    {
        $this->require_auth_user();

        $stmt = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['product' => $product]);
    }

    public function create_product()
    {
        $this->require_auth_user();
        $this->api->require_method('POST');

        $input = $this->api->body();
        $productName = trim((string)($input['product_name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $price = (float)($input['price'] ?? 0);
        $quantity = (int)($input['quantity'] ?? 0);

        if ($productName === '' || $price <= 0 || $quantity < 0) {
            $this->api->respond_error('Product name, valid price, and quantity are required.', 400);
        }

        $productModel = load_class('Product', 'models');
        $newId = $productModel->insert([
            'product_name' => $productName,
            'description' => $description,
            'price' => number_format($price, 2, '.', ''),
            'quantity' => $quantity,
        ]);

        $stmt = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$newId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function update_product($id)
    {
        $this->require_auth_user();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }

        $input = $this->api->body();
        $existing = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $productName = trim((string)($input['product_name'] ?? $existing['product_name']));
        $description = trim((string)($input['description'] ?? $existing['description']));
        $price = isset($input['price']) ? (float)$input['price'] : (float)$existing['price'];
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : (int)$existing['quantity'];

        if ($productName === '' || $price <= 0 || $quantity < 0) {
            $this->api->respond_error('Product name, valid price, and quantity are required.', 400);
        }

        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?',
            [$productName, $description, number_format($price, 2, '.', ''), $quantity, $id]
        );

        $updated = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product updated successfully.',
            'product' => $updated,
        ]);
    }

    public function delete_product($id)
    {
        $this->require_auth_user();
        $this->api->require_method('DELETE');

        $existing = $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->db->raw('DELETE FROM products WHERE id = ?', [$id]);

        $this->api->respond([
            'message' => 'Product deleted successfully.',
            'deleted_id' => (int)$id,
        ]);
    }
}
