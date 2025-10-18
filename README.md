# 🎮 Esports Tournament Management System

An **Esports Tournament Management System** built using **PHP**, **MySQL**, **HTML**, **CSS**, and **JavaScript**.  
This web application allows administrators to manage tournaments, teams, players, and match fixtures — while users can register, view tournaments, and track scores easily.

---

## 🚀 Features

### 👥 User Features
- Register and log in as a participant.
- Join tournaments and form teams.
- View game rules and live scoreboards.
- Confirm participation and make payments securely.
- Track match fixtures and results.

### 🧑‍💼 Admin Features
- Manage tournaments and team registrations.
- Add and edit games, rules, and fixtures.
- Approve teams and handle payments.
- Generate and update match schedules.
- View user profiles and statistics.

---

## 🗂️ Project Structure

```
Esports_Tournament/
│
├── admin/                   # Admin dashboard & management
│   ├── admin.php
│   ├── admin_login.php
│   ├── edit_tournament.php
│   ├── manage_payments.php
│   └── ...
│
├── auth/                    # Authentication (login/logout)
│   └── logout.php
│
├── assets/
│   ├── css/                 # Stylesheets
│   └── database.sql         # Database schema
│
├── includes/
│   └── navbar.php
│
├── tournament/              # Tournament-related pages
│   ├── registration.php
│   ├── fixtures & Scoreboard.php
│   ├── team_statistics.php
│   ├── val-rules.php
│   └── ...
│
├── user/                    # User profile management
│   ├── LogIn.php
│   ├── profile.php
│   ├── update.php
│   └── logout.php
│
├── db.php                   # Database connection file
├── index.php                # Homepage
├── Header.php               # Header component
├── news.php                 # Latest updates section
└── LICENSE
```

---

## ⚙️ Installation & Setup

### 🧩 Prerequisites
- PHP >= 7.4
- MySQL or XAMPP
- Web browser (Chrome, Firefox, etc.)

### 🏗️ Setup Instructions

1. **Clone this repository**
   ```bash
   git clone https://github.com/yourusername/Esports_Tournament.git
   ```

2. **Move project to your server directory**
   ```bash
   # Example (for XAMPP)
   mv Esports_Tournament /htdocs/
   ```

3. **Import the database**
   - Open phpMyAdmin.
   - Create a new database (e.g., `esports_tournament`).
   - Import the file `assets/database.sql`.

4. **Configure the database connection**
   - Open `db.php` and update:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "esports_tournament";
     ```

5. **Run the application**
   - Start Apache & MySQL from XAMPP.
   - Visit [http://localhost/Esports_Tournament](http://localhost/Esports_Tournament)

---

## 🧱 Technologies Used

| Technology | Purpose |
|-------------|----------|
| PHP | Backend server logic |
| MySQL | Database management |
| HTML/CSS | Frontend structure and design |
| JavaScript | Interactivity and dynamic updates |
| XAMPP | Local server environment |

---

## 🖼️ Screenshots

_Add screenshots of your UI here:_
```
/assets/screenshots/
```

---

## 📄 Documentation

Project report and ER diagram are available in:
```

```
 

---

## 📜 License

This project is licensed under the terms of the [MIT License](LICENSE).

---

> 💡 _Feel free to fork, contribute, and enhance this project for future Esports events!_
