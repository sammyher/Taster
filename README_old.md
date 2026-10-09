# Taster

CS514 Database Product Project

# Local Setup Guide

## 1. Install Dependencies

The project relies on Composer to manage the Auth0 PHP SDK and environment variables.

1. Clone the team repo to your local.
2. Install composer if you haven't already. You can download it from [getcomposer.org](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos). I have brew installed on my mac, so I just ran `brew install composer` in my terminal. If you have brew, you can do the same.
3. Open your terminal, navigate to the root `Taster` directory, and run:
   `composer install`

## 2. Configure Environment Variables

Create a `.env` file in the root directory. Copy the following template. Replace the database pswd w/ ur local MySQL root password. We will be using the same Auth0 credentials so we all share the same pool of user accounts. But, we will be using our own local MysQL database for testing until Carter configures a live shared database on the  `taster.carterj.us` subdomain. Ask me for the Auth0 client secret, didn't feel comfortable sharing over a repo's readme lol.

```env
# Auth0 Credentials (Shared)
AUTH0_DOMAIN='dev-c7kqok8heuep60mb.us.auth0.com'
AUTH0_CLIENT_ID='90swK6Fu04qVUzalhWoojvLq7I1vCNIa'
AUTH0_CLIENT_SECRET='ASK_SAMMY_FOR_IT'
AUTH0_COOKIE_SECRET='f45c38c3d716e08168273b743f6c9bd4b8a5821448d87978babdc1951e260fd6'
AUTH0_BASE_URL='http://localhost:8000'

# Local Database Credentials (Not Shared)
DB_HOST='127.0.0.1'
DB_NAME='taster_db'
DB_USER='root'
DB_PASS='your_actual_mysql_password'
```

## 3. Database Setup

Everyone needs to create their own local MySQL database, run this in your MySQL CLI or Workbench.

```Bash
#command to use MySQL CLI
mysql -u root -p 
```

```MySQL
# Once in MySQL CLI, run the following commands to create the database and the Accounts table:
CREATE DATABASE IF NOT EXISTS taster_db;
USE taster_db;

CREATE TABLE IF NOT EXISTS Accounts (
    ID VARCHAR(255) PRIMARY KEY,
    DisplayName VARCHAR(100) NOT NULL,
    Email VARCHAR(255) UNIQUE NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 4. Run the Local Server

From the root directory, run the following command to start the local server:

```bash
php -S localhost:8000
```

Open `http://localhost:8000/index.php` in your browser.
