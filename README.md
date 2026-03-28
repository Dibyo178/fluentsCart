![Shipping Icon](https://sourovdev.space/logo-full.svg)

# FluentCart Shipping Restriction

**FluentCart Shipping Restriction** is a powerful WordPress plugin that gives store owners full control over shipping destinations. Built with a modern **Vue.js 3** and **Tailwind CSS** admin interface, it provides real-time checkout validation to prevent unauthorized orders from restricted regions.

---

## 🔗 Project Resources

- 📦 **Tech Stack:** PHP, Laravel, Inertia.js, MySQL, Vue.js 3, Tailwind CSS, Axios, FluentCart Hooks

---

## ✨ Core Features

### 🌍 Universal Shipping Restrictions

- **Allowed List:** Restrict shipping only to specific countries (Whitelist).
- **Excluded List:** Block specific countries even if global shipping is enabled (Blacklist).
- **Conflict Management:** Prevents adding the same country to both lists simultaneously.
- 
## 🗄 Database Setup

To initialize the plugin's custom tables, please use the provided SQL schema. You can find it in the root directory or download it below:

- [📥 Download SQL Schema](https://drive.google.com/file/d/1BwDmJWt0DbcqYFImibCQknKQtH7dSc-a/view?usp=sharing)


### Installation Steps:
1. Open your **phpMyAdmin** or preferred SQL client.
2. Create a new database or select your existing WordPress database.
3. Import the `sql` file to create the `wp_fc_shipping_method_restrictions` and other necessary tables.
### 🛠 Dynamic System Modes

- **Global Mode:** Apply shipping rules across all available shipping methods.
- **Per Method Restriction:** Apply rules to a selected shipping method only.

### ⚡ Real-time Checkout Validation

- **Live Detection:** Monitors the country selection field instantly.
- **Smart Prevention:** Automatically disables the **"Place Order"** button and shows a warning message if a restricted country is selected.

### 📊 Order Metadata & Logging

- Stores validation status (Passed / Flagged) in order metadata.
- Displays the last 10 restriction activities inside the admin dashboard.

---

## 🚀 Installation & Setup

### 1️⃣ Prerequisites

- WordPress 6.0+
- PHP 8.1+
- Composer installed
- Node.js & npm installed

---

### 2️⃣ Installation Steps

#### 📂 Clone or Upload Plugin

Place the plugin folder inside:

/wp-content/plugins/

---

### 3️⃣ Install Backend Dependencies

```bash
composer install
```

---

### 4️⃣ Database Setup (Migration)

Run:

```bash
php artisan migrate
```

Then verify the database table:

wp_fc_shipping_method_restrictions

---

### 5️⃣ Install Frontend Dependencies

```bash
npm install
```

---

### 6️⃣ Run Frontend Development Server

```bash
npm run dev
```

---

### 7️⃣ Activate Plugin

Go to:

WordPress Admin → Plugins → Activate "FluentCart Shipping Restriction"

---

## 🛠 Tech Stack Details

| Component | Technology Used |
|-----------|-----------------|
| Backend   | Laravel, PHP (WordPress Plugin API) |
| Database  | MySQL (WPDB Custom Meta) |
| Frontend  | Vue.js 3, Inertia.js |
| Styling   | Tailwind CSS 3 |
| API Calls | Axios |
| UI Tools  | SweetAlert2, Dashicons, Custom SVG |

---

## ✅ Key Logic Flow

1. Admin enters country ISO code (e.g., US, UK, BD).
2. The system prioritizes the **Excluded List** over the **Allowed List**.
3. A JavaScript observer monitors checkout country changes.
4. If restricted → Checkout button is disabled + warning shown.
5. Validation result is saved in order metadata.

---

## 👨‍💻 Author

Sourov Purkayastha  
https://sourovdev.space/
