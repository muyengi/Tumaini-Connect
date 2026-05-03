# Tumaini Connect

Enterprise Church Outreach, Reporting, and Follow-up Management System for Tanzania.

## Stack
- PHP (procedural)
- MySQL (mysqli)
- Bootstrap 5
- Chart.js
- DataTables

## Setup (XAMPP)
1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin and import: `database/tumaini_connect_db.sql`.
3. Ensure project path is:
   - `C:/xampp/htdocs/Tumaini-Connect/tumaini-connect`
4. Open in browser:
   - `http://localhost/Tumaini-Connect/tumaini-connect/`

## Default Admin Login
- Email: `admin@tumaini.or.tz`
- Password: `Admin@123`

## Core Modules
- Authentication + RBAC
- Union management
- Conference/Field management
- Station management
- Public interest form
- Follow-up assignment and updates
- Role-based dashboard and analytics
- CSV report export

## Roles
- tanzania_admin: full visibility
- union_admin: union scope
- conference_admin: conference scope
- coordinator: assigned stations scope
- followup: assigned follow-up scope

## Notes
- CSRF protection is enabled on POST forms.
- Passwords are hashed with PHP `password_hash()`.
- Public form is available at:
  - `/modules/interests/public_form.php`

---

## Hosting via GitHub (Docker)

The project is containerised with Docker and ships a GitHub Actions workflow that automatically builds and publishes a Docker image to **GitHub Container Registry (GHCR)** on every push to `main`.

### Local development with Docker Compose

```bash
# 1. Copy the environment template
cp .env.example .env          # edit values if desired

# 2. Start the app + MySQL
docker compose up --build

# 3. Open in browser
http://localhost:8080
```

The SQL schema is imported automatically on first run from `database/tumaini_connect_db.sql`.

### Deploy to Railway.app (free tier)

1. Push this repository to GitHub.
2. Go to [railway.app](https://railway.app) → **New Project → Deploy from GitHub repo**.
3. Add a **MySQL** plugin in Railway; it will inject `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` automatically.
4. In the Railway service settings add the following environment variables and map them to Railway's MySQL variables:

   | Variable  | Value (Railway variable reference) |
   |-----------|-------------------------------------|
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}`              |
   | `DB_USER` | `${{MySQL.MYSQLUSER}}`              |
   | `DB_PASS` | `${{MySQL.MYSQLPASSWORD}}`          |
   | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}`          |

5. Railway will build the `Dockerfile` and deploy automatically.
6. Import the schema once via Railway's MySQL shell or a GUI client using the connection details Railway provides.

### Deploy to Render.com

1. Create a new **Web Service** and connect your GitHub repo.
2. Set the **Environment** to `Docker`.
3. Add a **MySQL** database (e.g. PlanetScale, Aiven, or any MySQL-compatible service) and note the connection details.
4. Add the four environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) in the Render dashboard.
5. Render builds and deploys on every push to `main`.

### Deploy to a VPS (DigitalOcean, Hetzner, etc.)

```bash
# On your server — pull the latest image built by GitHub Actions
docker pull ghcr.io/<your-github-username>/tumaini-connect:latest

# Run with your production DB credentials
docker run -d -p 80:80 \
  -e DB_HOST=your-db-host \
  -e DB_USER=your-db-user \
  -e DB_PASS=your-db-pass \
  -e DB_NAME=tumaini_connect_db \
  ghcr.io/<your-github-username>/tumaini-connect:latest
```

### GitHub Actions CI

The workflow at `.github/workflows/docker.yml` triggers on every push to `main` and:
- Builds the Docker image.
- Pushes it to `ghcr.io/<owner>/tumaini-connect` with the commit SHA tag and `latest`.

No secrets need to be configured — the workflow uses the built-in `GITHUB_TOKEN`.
