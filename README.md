# Niella's Rent-A-Tool

## Project Description

Niella's Rent-A-Tool is a web application for managing tool rentals. Renters can browse tools, submit rental requests, and upload payment proofs. Administrators manage their tool listings, rental requests, payments, and user accounts.

## Technology Stack

| Component | Technology |
| --- | --- |
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP with MySQLi |
| Database | MySQL / MariaDB |
| Local Development | XAMPP: Apache, PHP, MariaDB |
| Version Control | Git and GitHub |

PHP handles sessions, forms, and database operations. MySQL/MariaDB stores related records using tables and foreign keys. XAMPP provides the local development environment.

## Features

- User registration and login with admin and renter roles.
- Tool listing creation, viewing, and editing.
- Deletion of an administrator's own listings without rental records.
- Rental requests and approval or rejection.
- Manual payment submission and administrator verification.
- Profile and image uploads.
- Database tables for return logs and deposit refund records.

## Local Setup

These instructions are for a fresh installation.

1. Install XAMPP and start Apache and MySQL.
2. Clone or download this repository into:
   `C:\xampp\htdocs\PROJECTS`
3. Open `http://localhost/phpmyadmin/`.
4. Create an empty database named `projects`.
5. Select that database and import `schema.sql`.
6. Check the connection settings in `db.php`. The local defaults are:
   - Host: `localhost`
   - Username: `root`
   - Password: empty
   - Database: `projects`
7. Ensure PHP can create and write to the `uploads` folder.
8. Open `http://localhost/PROJECTS/login.php`.

## First Admin Account

The schema does not include default accounts. Newly registered accounts have the renter role.

1. Register an account through the application.
2. In phpMyAdmin, select the `projects` database.
3. Replace `your_registered_username` below with that account's username, then run:

```sql
UPDATE login
SET role = 'admin'
WHERE username = 'your_registered_username'
  AND role = 'renter';
```

4. Log out of the application and log in again to load the admin role.
5. Register a separate account for renter testing.

Categories are initially empty. Add category names through the `categories` table in phpMyAdmin if needed.

## Database

`schema.sql` contains 11 table definitions, including their indexes and foreign keys.

It does not include existing accounts, tool listings, rental transactions, payment records, or uploaded images. Create the accounts and listings needed for a fresh installation.

## Deployment Steps

1. Prepare hosting that supports PHP with MySQLi and MySQL/MariaDB.
2. Create a database through the hosting control panel.
3. Import `schema.sql` into the empty hosting database.
4. Upload the application files to the hosting web directory.
5. Configure `db.php` on the server using the hosting database credentials.
6. Ensure the application can create and write to `uploads`.
7. Open the hosted login page and create the initial admin account using the setup instructions above.
8. Test login, listings, rental requests, payments, and returns on the hosted application.

## Verified Delete Behavior

- An administrator can delete their own tool listing without rental records.
- Deletion is blocked when the listing has a rental record, including a pending request.

## Deployment Status

The application is currently running locally through XAMPP. The hosting provider and live URL will be added after online deployment.