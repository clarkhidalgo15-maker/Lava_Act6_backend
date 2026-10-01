# Product API and React Frontend

The LavaLust API stays in this repository. The independent React/Vite app is in `Lavafrontend/`. The browser only calls the API; it never connects to MySQL.

## Local Setup

1. Copy `.env.example` to `.env` and enter the Aiven MySQL connection values. Use `DB_DRIVER=mysql`, `DB_CHARSET=utf8mb4`, and the Aiven CA certificate path in `DB_SSL_CA` when TLS verification is required.
2. Generate separate secrets for `JWT_SECRET` and `REFRESH_TOKEN_KEY` (each at least 32 characters). For example, run `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` twice. Keep the values in `.env` and never commit that file.
3. The products table is assumed to exist already. Create the authentication tables in Aiven if they do not exist yet:

```sql
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'moderator', 'user') NOT NULL DEFAULT 'user',
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token TEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  jti TEXT NOT NULL,
  KEY user_id_idx (user_id)
);
```

4. Set `CORS_ALLOWED_ORIGIN=http://localhost:5173` in the backend `.env`.
5. In `Lavafrontend/`, copy `.env.example` to `.env`, set `VITE_API_BASE_URL` to the LavaLust public API URL ending in `/api`, then run `npm install` and `npm run dev`.
6. Create an account from the frontend sign-in screen, then sign in. Registration always assigns the `user` role; product endpoints require a valid, active-user JWT.

The current Aiven database has a static admin account: username `admin`, password `admin`. New registrations receive the `user` role and can view products only. Change the admin password before exposing a deployment publicly; `admin/admin` is a demo credential.

## Render Deployment

Deploy the API as a Render **Web Service** using this repository's Dockerfile. The container listens on Render's `PORT` (default `10000`). Add these environment variables in the Render dashboard, never in source control:

- `APP_ENV=production`
- `DB_DRIVER=mysql`
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` from Aiven
- `DB_CHARSET=utf8mb4`
- `DB_SSL_CA` set to the path of Aiven's CA certificate uploaded as a Render secret file
- `JWT_SECRET` and `REFRESH_TOKEN_KEY`, each generated as a distinct random value of at least 32 characters
- `CORS_ALLOWED_ORIGIN` set to the exact deployed frontend origin, for example `https://your-frontend.onrender.com`

For the frontend, create a Render **Static Site** from the same repository with root directory `Lavafrontend`, build command `npm install && npm run build`, and publish directory `dist`. Set `VITE_API_BASE_URL` to `https://your-api.onrender.com/api` before the build. Update the API's `CORS_ALLOWED_ORIGIN` to the resulting Static Site URL and redeploy the API if that value changes.

## Endpoints

- `POST /api/auth/register` and `POST /api/auth/login`
- `POST /api/auth/refresh` and authenticated `POST /api/auth/logout`
- Authenticated `GET /api/products`, `GET /api/products/{id}`, `POST /api/products`, `PUT/PATCH /api/products/{id}`, and `DELETE /api/products/{id}`

All API responses use the LavaLust `Api` library. Product endpoints require `Authorization: Bearer <access_token>`.