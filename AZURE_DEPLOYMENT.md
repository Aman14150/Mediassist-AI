# Azure App Service and Azure SQL deployment

The production design is one Linux App Service custom container connected to one Azure SQL Database. SQLite remains available only for local development. Do not mount an Azure Storage account for the live database.

## 1. Create Azure SQL Database

1. In the Azure portal, create **Azure SQL Database** and apply the **Free offer** if it is available for your subscription.
2. Use a new logical SQL server in the same region as the App Service.
3. Select the option that pauses the database when its free monthly limit is reached if you want to prevent overage charges.
4. Keep the database empty. The application creates the tables and seeds departments, doctors, assignments, and availability after its first connection.
5. Retain the server hostname, database name, SQL administrator username, and password.

The deployment login currently needs permission to create tables and indexes and to read/write application data. After initialization, a separate least-privilege application login is recommended for real use.

## 2. Build and publish the container

The included `Dockerfile` contains PHP 8.3, Apache, SQLite for local use, Microsoft ODBC Driver 18, and `pdo_sqlsrv` for Azure SQL.

Publish the image using Azure Container Registry, GitHub Container Registry, Docker Hub, or an Azure Deployment Center workflow. If you create Azure Container Registry, note that it is a separately billed Azure resource.

## 3. Create App Service

1. Create a **Web App** using a Linux custom container.
2. Select the published image and expose container port `80`.
3. Start with one instance. The Free App Service plan is appropriate only for testing and has no production SLA.
4. Enable HTTPS Only.

In **Settings > Environment variables**, add:

```text
APP_ENV=production
APP_URL=https://YOUR-APP-NAME.azurewebsites.net
APP_TIMEZONE=Asia/Kolkata
ALLOWED_ORIGINS=https://YOUR-APP-NAME.azurewebsites.net

DB_CONNECTION=sqlsrv
AZURE_SQL_HOST=YOUR-SERVER.database.windows.net
AZURE_SQL_PORT=1433
AZURE_SQL_DATABASE=YOUR-DATABASE
AZURE_SQL_USERNAME=YOUR-SQL-LOGIN
AZURE_SQL_PASSWORD=YOUR-SQL-PASSWORD

ELEVENLABS_WEBHOOK_SECRET=AT-LEAST-32-RANDOM-CHARACTERS
SLOT_TOKEN_SECRET=A-DIFFERENT-AT-LEAST-32-RANDOM-CHARACTERS
STAFF_PASSWORD_HASH=YOUR-PHP-PASSWORD-HASH
```

Do not add `DATABASE_PATH` in Azure. Mark secrets as deployment-slot settings if you later use deployment slots.

## 4. Allow the App Service to reach Azure SQL

In the App Service **Properties** page, copy all outbound IP addresses. In the SQL server **Networking** page, allow those IP addresses through the SQL firewall. This is narrower than enabling access from every Azure service.

The application requires outbound TCP access to Azure SQL on port 1433. The connection enforces encryption and validates the Azure SQL certificate.

## 5. Verify deployment

Open:

```text
https://YOUR-APP-NAME.azurewebsites.net/api/health.php
```

A successful Azure response contains:

```json
{"ok":true,"service":"medicare-booking","status":"ready","database":"sqlsrv","timezone":"Asia/Kolkata"}
```

Then test the public departments endpoint and make one test booking from `appointment.html`. Use the Azure SQL portal Query Editor to verify the booking:

```sql
SELECT TOP (20)
    a.booking_reference,
    p.full_name,
    d.name AS doctor_name,
    dep.name AS department_name,
    a.appointment_date,
    a.slot_time,
    a.status
FROM appointments a
JOIN patients p ON p.id = a.patient_id
JOIN doctors d ON d.id = a.doctor_id
JOIN departments dep ON dep.id = a.department_id
ORDER BY a.created_at DESC;
```

The `/staff/` dashboard intentionally accepts only localhost connections, so it is not an Azure administration page.

## 6. Connect ElevenLabs

After health and website booking tests pass, follow `ELEVENLABS_SETUP.md`. Replace its example hostname with the App Service HTTPS hostname and configure both tools with a bearer-token secret exactly matching `ELEVENLABS_WEBHOOK_SECRET`.

The two endpoints are:

```text
POST https://YOUR-APP-NAME.azurewebsites.net/api/elevenlabs/check-availability.php
POST https://YOUR-APP-NAME.azurewebsites.net/api/elevenlabs/create-appointment.php
```

Do not expose the bearer secret or an ElevenLabs API key in browser JavaScript.

## 7. Before real patient use

Move off the Free App Service plan, configure monitoring and alerts, use a least-privilege database identity, test backup restoration, define retention and incident-response procedures, and obtain an appropriate privacy/security review for patient contact data and call transcripts.
