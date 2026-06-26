# FiveSix Legaspi Cafe — Finals Website

A full cafe website + internal management system built for our Web Applications final project.

## ✨ What this project includes

- Public-facing pages:
  - Home (`Website.html`)
  - Menu (`Menu.html`)
  - Contact (`Contacts.html`)
- Employee portal (`LOGIN.php`)
- Role-based internal system:
  - **Admin:** Dashboard, Stocks, Cashier, Orders, Profile
  - **Staff:** Cashier, Orders, Profile
- POS-style cashier checkout with:
  - Add-ons
  - Discounts (PWD / Senior / Student / Manual)
  - Receipt generation and printing
- Stock management with activity logs
- Order management with status updates

## 🛠️ Tech stack

- PHP (server-side logic + session auth)
- MySQL
- HTML / CSS / JavaScript
- Chart.js (analytics charts)
- html2canvas + jsPDF (report export)

## 📁 Project structure

```text
finals-website/
├─ FINALS WEBSITE/
│  ├─ Website.html, Menu.html, Contacts.html
│  ├─ LOGIN.php, auth.php, logout.php
│  ├─ db.php, db_config.example.php
│  ├─ Cashier.php, OrderingSystem.php, Stocks.php, Profile.php, Merchandise.php
│  ├─ databasetoSQL.php
│  ├─ database/
│  │  ├─ schema.sql
│  │  └─ seed.sql
│  ├─ *.css, *.js
│  └─ IMAGES/
└─ README.md
```

## 🚀 Run locally (XAMPP)

1. Put the project in your XAMPP htdocs folder.
2. Start **Apache** and **MySQL** from XAMPP.
3. Import database schema:
   - `C:\xampp\mysql\bin\mysql.exe -u root -p < "C:\xampp\htdocs\FINALS WEBSITE\finals-website\FINALS WEBSITE\database\schema.sql"`
4. Seed starter data:
   - `C:\xampp\mysql\bin\mysql.exe -u root -p < "C:\xampp\htdocs\FINALS WEBSITE\finals-website\FINALS WEBSITE\database\seed.sql"`
5. Optional: create `FINALS WEBSITE\db_config.local.php` from `db_config.example.php` if your DB credentials differ.
6. Open:
   - Public site: `http://localhost/FINALS%20WEBSITE/finals-website/FINALS%20WEBSITE/Website.html`
   - Login page: `http://localhost/FINALS%20WEBSITE/finals-website/FINALS%20WEBSITE/LOGIN.php`

### Default seeded login accounts

- Admin: `admin@fivesix.local` / `admin123`
- Staff: `staff1@fivesix.local` / `staff123`

## 🔐 Notes

- Session-based role checks are enforced in `auth.php`.
- Logging out now uses `logout.php`, which properly destroys the session.
- DB credentials are read from:
  1. `db_config.local.php` (if present), then
  2. environment variables (`MAIN_DB_*`, `LOGIN_DB_*`), then
  3. local defaults (`127.0.0.1:3306`, user `root`, blank password).

## 👥 Team / Course Context

This repository contains our final output for the Web Applications project and showcases:
- frontend presentation quality
- backend PHP/MySQL integration
- practical admin + cashier workflow design
