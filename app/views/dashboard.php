<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = $user ?? ['username' => 'Guest'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 28px;
            background: #0f172a;
            color: white;
        }
        .brand { font-weight: 700; }
        .user-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logout {
            background: #f97316;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
        }
        .container {
            max-width: 1100px;
            margin: 28px auto;
            padding: 0 20px 40px;
        }
        .panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 24px;
            margin-top: 20px;
        }
        form {
            display: grid;
            gap: 12px;
        }
        label {
            display: grid;
            gap: 6px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        input, textarea, button {
            font: inherit;
        }
        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        button[type="submit"], .primary-btn {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 10px;
            text-align: left;
        }
        th { background: #f8fafc; }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .small-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            font-weight: 600;
        }
        .edit { background: #fef3c7; color: #92400e; }
        .delete { background: #fee2e2; color: #991b1b; }
        .status {
            color: #0f766e;
            font-weight: 700;
        }
        .empty {
            color: #64748b;
            padding: 20px 0;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">Product Management System</div>
        <div class="user-row">
            <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
            <button class="logout" id="logoutBtn">Logout</button>
        </div>
    </div>

    <div class="container">
        <div class="panel">
            <h2>Products</h2>
            <div class="grid">
                <div>
                    <h3 id="formTitle">Add Product</h3>
                    <form id="productForm">
                        <input type="hidden" id="productId">
                        <label>
                            Product Name
                            <input type="text" id="productName" required>
                        </label>
                        <label>
                            Description
                            <textarea id="description"></textarea>
                        </label>
                        <label>
                            Price
                            <input type="number" id="price" step="0.01" min="0" required>
                        </label>
                        <label>
                            Quantity
                            <input type="number" id="quantity" min="0" required>
                        </label>
                        <button type="submit" id="submitBtn">Save Product</button>
                        <button type="button" class="primary-btn" id="cancelEdit" style="display:none; background:#64748b;">Cancel</button>
                    </form>
                </div>

                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody"></tbody>
                    </table>
                    <div id="emptyState" class="empty" style="display:none;">No products found.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const token = localStorage.getItem('access_token');
        if (!token) {
            window.location.href = '/login';
        }

        const productForm = document.getElementById('productForm');
        const productTableBody = document.getElementById('productTableBody');
        const emptyState = document.getElementById('emptyState');
        const formTitle = document.getElementById('formTitle');
        const submitBtn = document.getElementById('submitBtn');
        const cancelEditBtn = document.getElementById('cancelEdit');

        async function request(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...(options.headers || {}),
                    Authorization: `Bearer ${token}`
                }
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || 'Request failed.');
            }

            return data;
        }

        function renderProducts(products) {
            productTableBody.innerHTML = '';
            if (!products.length) {
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';
            products.forEach(product => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td>${product.product_name}</td>
                    <td>$${Number(product.price).toFixed(2)}</td>
                    <td>${product.quantity}</td>
                    <td>
                        <div class="actions">
                            <button class="small-btn edit" data-id="${product.id}">Edit</button>
                            <button class="small-btn delete" data-id="${product.id}">Delete</button>
                        </div>
                    </td>
                `;
                productTableBody.appendChild(row);
            });

            document.querySelectorAll('.edit').forEach(button => {
                button.addEventListener('click', () => populateForm(button.dataset.id));
            });

            document.querySelectorAll('.delete').forEach(button => {
                button.addEventListener('click', () => deleteProduct(button.dataset.id));
            });
        }

        async function loadProducts() {
            try {
                const data = await request('/api/products');
                renderProducts(data.products || []);
            } catch (error) {
                alert(error.message);
            }
        }

        productForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const id = document.getElementById('productId').value;
            const payload = {
                product_name: document.getElementById('productName').value,
                description: document.getElementById('description').value,
                price: document.getElementById('price').value,
                quantity: document.getElementById('quantity').value
            };

            try {
                if (id) {
                    await request(`/api/products/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                } else {
                    await request('/api/products', { method: 'POST', body: JSON.stringify(payload) });
                }

                resetForm();
                await loadProducts();
            } catch (error) {
                alert(error.message);
            }
        });

        async function deleteProduct(id) {
            if (!confirm('Delete this product?')) return;

            try {
                await request(`/api/products/${id}`, { method: 'DELETE' });
                await loadProducts();
            } catch (error) {
                alert(error.message);
            }
        }

        async function populateForm(id) {
            try {
                const data = await request(`/api/products/${id}`);
                const product = data.product;
                document.getElementById('productId').value = product.id;
                document.getElementById('productName').value = product.product_name;
                document.getElementById('description').value = product.description || '';
                document.getElementById('price').value = product.price;
                document.getElementById('quantity').value = product.quantity;
                formTitle.textContent = 'Edit Product';
                submitBtn.textContent = 'Update Product';
                cancelEditBtn.style.display = 'inline-block';
            } catch (error) {
                alert(error.message);
            }
        }

        function resetForm() {
            document.getElementById('productId').value = '';
            document.getElementById('productName').value = '';
            document.getElementById('description').value = '';
            document.getElementById('price').value = '';
            document.getElementById('quantity').value = '';
            formTitle.textContent = 'Add Product';
            submitBtn.textContent = 'Save Product';
            cancelEditBtn.style.display = 'none';
        }

        cancelEditBtn.addEventListener('click', resetForm);
        document.getElementById('logoutBtn').addEventListener('click', () => {
            localStorage.removeItem('access_token');
            localStorage.removeItem('refresh_token');
            localStorage.removeItem('user');
            window.location.href = '/logout';
        });

        loadProducts();
    </script>
</body>
</html>
