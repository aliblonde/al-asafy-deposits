# Project Credentials

This file contains the default credentials for the AL-ASAFY GROUP project as found in the seeding and configuration files.

## 1. Application Login Accounts
These accounts are defined in `sql/seed.sql`.

| Role | Username | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin` | `Admin@123` |
| **Staff User** | `staff` | `Staff@123` |
| **Investor (Demo)** | `investor1` | `Investor@123` |

## 2. Database Credentials
As configured in `config/db.php`.

*   **Host**: `127.0.0.1`
*   **Database Name**: `al_asafy_deposits`
*   **Username**: `root`
*   **Password**: *(Empty)*

> [!WARNING]
> These are default credentials. For security, please change them in a production environment and ensure this file is not accessible to unauthorized users.
