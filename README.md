# Medicare Hospital website and booking service

This project is the BootstrapMade **Clinic** static template (Bootstrap 5.3.7, HTML, CSS, and vanilla JavaScript) with a small PHP 8.2+ appointment service. It uses SQLite for local development and Azure SQL Database when deployed to Azure. No JavaScript or PHP package installation is required.

## What was added

- SQLite tables for departments, doctors, doctor/department assignments, recurring availability, one-off availability overrides, patients, appointments, and rate limits.
- Authenticated ElevenLabs webhook tools for checking slots and creating a confirmed booking.
- Short-lived, signed slot tokens and database transactions to prevent stale or simultaneous bookings.
- Unique database indexes that prevent both doctor/time and patient/time duplicates.
- A website booking form using the same availability and booking rules.
- A password-protected, local-computer-only staff schedule at `/staff/`; it is not linked from the public website.

The supplied roster is seeded automatically. Appointments are Monday through Saturday in the listed 15-minute slots. Sundays are rejected. Each department has its own consultation fee, starting at Rs. 300; payment is not collected online and is paid at the hospital.

## Requirements

- PHP 8.2 or newer
- Local database extensions: `pdo_sqlite` and `sqlite3`
- Azure database extension: `pdo_sqlsrv` plus Microsoft ODBC Driver 18 (included in the Docker image)
- HTTPS for production
- Apache with `.htaccess` support, or equivalent Nginx rules that block `/app`, `/database`, `/storage`, `.env`, and directory listing

## Run locally (beginner-friendly Docker method)

1. Install Docker Desktop.
2. Copy `.env.example` to `.env`.
3. Generate two independent random secrets. In PowerShell, run this twice:

   ```powershell
   -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 48 | ForEach-Object {[char]$_})
   ```

   Put one result in `ELEVENLABS_WEBHOOK_SECRET` and the other in `SLOT_TOKEN_SECRET`.
4. Generate the staff password hash after the container is built:

   ```powershell
   docker compose run --rm website php -r "echo password_hash('Choose-A-Strong-Password', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Copy the complete output into `STAFF_PASSWORD_HASH` in `.env`. Wrap the hash in single quotes (for example `STAFF_PASSWORD_HASH='$2y$...'`) so Docker Compose treats every `$` literally.
5. Set `APP_URL=http://localhost:8080` and `ALLOWED_ORIGINS=http://localhost:8080`.
6. Start the site:

   ```powershell
   docker compose up --build
   ```

7. Open `http://localhost:8080`, test booking at `/appointment.html`, open the local staff sign-in at `/staff/`, and check health at `/api/health.php`.

The schema and seed data are created automatically on the first request. The Docker volume keeps the SQLite file when the container restarts.

## Deploy to Azure

Use [AZURE_DEPLOYMENT.md](AZURE_DEPLOYMENT.md) for the Azure App Service, Azure SQL, firewall, environment setting, verification, and ElevenLabs sequence. Azure production must use `DB_CONNECTION=sqlsrv`; do not place SQLite on an Azure Storage mount.

## Deploy to another Apache/PHP host with SQLite

1. Choose a host that provides PHP 8.2+, PDO SQLite, HTTPS, and a persistent filesystem. Do not use an ephemeral/serverless filesystem for this SQLite design.
2. Upload this project to the web root and keep `DB_CONNECTION=sqlite`.
3. Copy `.env.example` to `.env`, fill every placeholder, set `APP_ENV=production`, set the public HTTPS `APP_URL`, and set `ALLOWED_ORIGINS` to the exact site origin (for example `https://hospital.example.com`, without a trailing slash).
4. If the host permits it, create a non-public persistent data directory and set `DATABASE_PATH` to its absolute path. Otherwise keep `storage/medicare.sqlite`; the included Apache rule blocks web access to it.
5. Make only the database directory writable by the PHP/web-server user. Do not make the whole project world-writable.
6. Confirm that requesting `/.env`, `/app/bootstrap.php`, `/database/schema.sql`, and `/storage/medicare.sqlite` returns 403/404.
7. Visit `/api/health.php`; it should return `{"ok":true,..."status":"ready"}`.
8. Make one test booking from `/appointment.html`. A remote request to `/staff/` should return 404 because staff access is intentionally local-only.
9. Configure the two ElevenLabs webhook tools using [ELEVENLABS_SETUP.md](ELEVENLABS_SETUP.md).

Run the database/duplicate-protection checks at any time with `python -m unittest tests.test_schema -v`.

For a Docker host, build `Dockerfile`, attach a persistent volume at `/data`, and set `DATABASE_PATH=/data/medicare.sqlite`. Set all `.env` values in the host's secret/environment dashboard instead of uploading `.env`.

## Backups and operations

- Back up the SQLite database at least daily. Use your host's volume snapshot, or run `sqlite3 /path/medicare.sqlite ".backup '/safe/path/backup.sqlite'"`.
- `/staff/` accepts only localhost connections and is intended for XAMPP/local development. For Azure SQL inspection, use the Azure portal Query Editor or another access-controlled SQL client. A remotely accessible staff administration design requires separate authentication and security work.
- Availability changes can be made in the `availability` table. Doctor leave goes in `availability_overrides` with `is_available=0`; `1` re-enables a recurring slot that was otherwise overridden.
- This prototype stores patient contact details. Before real clinical use, obtain appropriate legal/security review, define retention, access logging, breach response, and backup policies, and do not store symptoms or sensitive medical details unless required and protected.

## API behavior

Both booking clients must check availability first. The response includes a signed `confirmation_token` for each free slot, valid for 10 minutes. Creation requires that token and JSON boolean `confirmed: true`. Even with a valid token, the transaction rechecks availability and unique indexes decide the race safely. A conflict returns HTTP 409 with `slot_unavailable` or `duplicate_booking`; the caller should check availability again.
