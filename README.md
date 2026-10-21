# 🍔 Snack Zone — Restaurant POS & Management System

A full-stack restaurant management system with a PHP admin panel, Next.js customer portal, Point of Sale (POS), profit tracking, and order management.

---

## ✨ Features

- **POS System** — Bill generation, token numbers, bill history, reprint
- **Admin Dashboard** — Live analytics: today's sales, orders, revenue
- **Order Management** — Pending → Confirmed → Dispatched workflow with real-time updates
- **Menu Management** — Categories, items with images, add-ons, stock toggle
- **Profit Tracking** — Daily/weekly/monthly breakdown: Sales vs Raw Material Cost
- **Raw Material Purchases** — Record costs by date with supplier linkage
- **Supplier Management** — Vendor directory with contact and items supplied
- **Table Booking System** — Customer bookings with confirmation emails
- **Receptionist Panel** — Limited-access role for order management only
- **Customer Portal** — Login, order history, profile, live order status
- **Email Notifications** — OTP, order confirmation, booking confirmation via Gmail SMTP
- **Slideshow & Banners** — Homepage slide/banner management from admin
- **Chat System** — Admin ↔ Customer messaging per order
- **WhatsApp Integration** — One-click WhatsApp button

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Next.js 15, React 18, TypeScript, Tailwind CSS |
| Backend | PHP 8.x, MySQLi (prepared statements) |
| Database | MySQL 5.7+ |
| Email | PHPMailer 6.x (Gmail SMTP) |
| Local Dev | XAMPP / WAMP |

---

## 📋 Prerequisites

- **XAMPP** or **WAMP** (PHP 8.0+ with MySQL)
- **Node.js** 18+ and npm
- **Git**
- A Gmail account with [App Password](https://support.google.com/accounts/answer/185833) enabled

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/IHSAN-ANWAR/POS-Snackzone.git
cd POS-Snackzone
```

Place the folder inside your web root:
- XAMPP: `C:\xampp\htdocs\restaurant\` (Windows) or `/opt/lampp/htdocs/restaurant/`
- WAMP: `C:\wamp64\www\restaurant\`

### 2. Database Setup

1. Start XAMPP (Apache + MySQL)
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
3. Create a new database: `snackzone`
4. Import the base schema:
   - Select `snackzone` database → **SQL** tab
   - Import `database_schema.sql`
5. Run the profit tracking migration:
   - Import `migrations/001_profit_tracking.sql`

### 3. Backend Environment

```bash
cp Admin/.env.example Admin/.env
```

Edit `Admin/.env` with your values:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=snackzone
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_gmail_app_password
```

### 4. Frontend Environment

```bash
cp .env.local.example .env.local
```

Edit `.env.local`:

```env
NEXT_PUBLIC_API_URL=http://localhost/restaurant/Admin
NEXT_PUBLIC_WHATSAPP_NUMBER=923000000000
```

### 5. Install Frontend Dependencies

```bash
npm install
npm run dev
```

Visit: `http://localhost:3000`

### 6. Access Admin Panel

Open: `http://localhost/restaurant/Admin/`

Register your first admin account via:
`http://localhost/restaurant/Admin/register.php`

---

## 🔐 Environment Variables

### `Admin/.env`

| Variable | Description |
|---|---|
| `DB_HOST` | Database host (usually `localhost`) |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |
| `DB_NAME` | Database name |
| `FRONTEND_ORIGIN` | Next.js origin for CORS |
| `ALLOWED_ORIGINS` | Comma-separated production origins |
| `MAIL_HOST` | SMTP host (`smtp.gmail.com`) |
| `MAIL_PORT` | SMTP port (`587`) |
| `MAIL_USERNAME` | Gmail address |
| `MAIL_PASSWORD` | Gmail App Password (16 chars, no spaces) |
| `SITE_URL` | Backend base URL |

### `.env.local`

| Variable | Description |
|---|---|
| `NEXT_PUBLIC_API_URL` | PHP backend URL |
| `NEXT_PUBLIC_WHATSAPP_NUMBER` | WhatsApp number with country code |

---

## 🗄 Database Tables

| Table | Purpose |
|---|---|
| `admin_users` | Admin & receptionist accounts |
| `orders` | Customer orders with items (JSON), status |
| `menu_items` | Food items with price, image, category |
| `menu_item_addons` | Add-ons linked to menu items |
| `categories` | Menu categories |
| `bookings` | Table reservations |
| `suppliers` | Vendor/supplier directory |
| `raw_material_purchases` | Daily raw material cost tracking |
| `shift_materials` | Shift-based material tracking |
| `daily_shifts` | Shift records |
| `homepage_slides` | Slideshow images |
| `notices` | Admin notices |
| `chat_sessions` | Customer order chat sessions |
| `chat_messages` | Chat messages |
| `site_settings` | Global site configuration |

---

## 📁 Project Structure

```
snackzone/
├── Admin/                   # PHP backend
│   ├── action.php           # Main API endpoint (all actions)
│   ├── index.php            # Admin panel UI
│   ├── config.php           # DB connection, env loader
│   ├── db.php               # Schema bootstrap
│   ├── mailer.php           # Email helper (PHPMailer)
│   ├── receptionist.php     # Receptionist panel
│   ├── .env.example         # Template — copy to .env
│   └── .htaccess            # Security headers
│
├── app/                     # Next.js 15 App Router
│   ├── (site)/              # Customer-facing pages
│   │   ├── page.tsx         # Homepage
│   │   ├── menu/            # Menu page
│   │   ├── order/           # Place order
│   │   ├── orders/          # Order history
│   │   ├── booking/         # Table booking
│   │   ├── login/           # Customer login
│   │   ├── register/        # Customer registration
│   │   ├── profile/         # Customer profile
│   │   └── contact/         # Contact form
│   └── layout.tsx           # Root layout
│
├── components/              # Shared React components
│   ├── Navbar.tsx
│   ├── Footer.tsx
│   └── WhatsAppButton.tsx
│
├── img/                     # Uploaded images (gitignored)
│   ├── menu/                # Menu item images
│   ├── banners/             # Banner images
│   ├── slides/              # Slideshow images
│   └── notices/             # Notice images
│
├── migrations/              # Database migrations
│   └── 001_profit_tracking.sql
│
├── database_schema.sql      # Full DB schema
├── .env.local.example       # Frontend env template
└── README.md
```

---

## 🔒 Security Notes

> **Important — before going live:**

1. **Never commit `.env` files** — they are gitignored, keep them local
2. **Change default passwords** immediately after first setup
3. **Use HTTPS** in production — configure SSL on your hosting
4. **Set file permissions** on the `img/` upload directory: `755` for directories, `644` for files
5. **Gmail App Password** — Enable 2-Step Verification first, then generate a 16-character App Password
6. **Database user** — In production, create a dedicated MySQL user with only SELECT/INSERT/UPDATE/DELETE privileges (not root)

---

## 📜 License

MIT License — free to use and modify for personal or commercial projects.

---

## 👤 Author

**Ihsan Anwar**
- GitHub: [@IHSAN-ANWAR](https://github.com/IHSAN-ANWAR)
