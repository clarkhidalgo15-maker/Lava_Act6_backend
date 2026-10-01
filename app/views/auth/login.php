<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Product Manager</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a, #111827);
            color: #e5e7eb;
            min-height: 100vh;
            display: grid;
            place-items: center;
        }
        .card {
            width: min(92vw, 460px);
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.35);
        }
        h2 {
            margin: 0 0 8px;
            font-size: 2rem;
            text-align: center;
        }
        p {
            text-align: center;
            color: #cbd5e1;
            margin-bottom: 22px;
        }
        form {
            display: grid;
            gap: 14px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #f8fafc;
        }
        button {
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            background: #f97316;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }
        .msg {
            min-height: 22px;
            font-size: 0.9rem;
            color: #fca5a5;
        }
        .link {
            margin-top: 18px;
            text-align: center;
            font-size: 0.95rem;
        }
        .link a { color: #fbbf24; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Login</h2>
        <p>Access your product dashboard</p>
        <form id="loginForm">
            <input type="text" id="username" name="username" placeholder="Username or email" required>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
        <div id="message" class="msg"></div>
        <div class="link">Need an account? <a href="/register">Create one</a></div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const message = document.getElementById('message');

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            message.textContent = '';

            const payload = {
                username: document.getElementById('username').value,
                password: document.getElementById('password').value
            };

            try {
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Login failed.');
                }

                localStorage.setItem('access_token', data.tokens.access_token);
                localStorage.setItem('refresh_token', data.tokens.refresh_token);
                localStorage.setItem('user', JSON.stringify(data.user));
                window.location.href = '/dashboard';
            } catch (error) {
                message.textContent = error.message;
            }
        });
    </script>
</body>
</html>
