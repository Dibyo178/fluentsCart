![Shipping Icon](https://sourovdev.space/logo-full.svg) 

 # FluentCart Shipping Restriction Plugin

A modern, secure shipping restriction plugin for **FluentCart**, built with **Laravel-style architecture**, **Inertia.js**, and **Vue.js**, fully compatible with **PHP 8.2**.

---

## Overview

**FluentCart Shipping Restriction** is a WordPress plugin that integrates deeply with **FluentCart** to control shipping availability based on customer country.

Administrators can define **allowed** and **excluded** countries globally or per shipping method.  
Restrictions are enforced on both the **frontend** (real-time UI feedback) and **backend** (server-side validation), making the system fully **bypass-proof**.

The admin panel is powered by **Vue.js + Inertia.js**, providing a clean and modern user experience similar to Laravel applications.


## Key Goals

- Prevent shipping to restricted countries
- Support global and per-method shipping rules
- Enforce rules securely via FluentCart backend hooks
- Provide a modern admin UI with logs and export support

---

## Features

### 🌍 Country-Based Restrictions
- Define allowed and excluded countries using ISO codes (`US`, `CA`, `DE`)
- Comma-separated and sanitized input

### 🔁 Global or Per-Method Modes
- Apply rules globally (all shipping methods)
- Apply rules to specific FluentCart shipping methods

### 🖥 Frontend Validation
- Real-time checkout warnings
- Automatically disables the **Place Order** button
- Clear user-facing messages (e.g. *We do not ship to this country*)

### 🛑 Backend Enforcement
- Uses FluentCart validation hooks
- Prevents API-based or direct checkout bypass
- Server-side country verification before order creation

### 🧾 Order Logging
- Logs applied rules (mode, allowed, excluded)
- Stored in FluentCart order metadata
- Useful for debugging and audits

### ⚙️ Admin UI (Laravel-style)
- Built with **Vue.js + Inertia.js**
- SPA-like experience inside WordPress admin
- CSV export support for logs

### 🧪 Dev & Production Ready
- Vite-powered local development
- Optimized production build assets

### 🔐 Security
- WordPress nonces
- Data sanitization
- Prepared database queries

---

## 📦 Tech Stack

- PHP **8.2**
- WordPress
- FluentCart
- Laravel-style architecture
- Inertia.js
- Vue.js
- Tailwind CSS
- Vite

---

## Requirements

- WordPress **5.0+** (tested up to latest)
- FluentCart (active and configured)
- PHP **8.2+**
- MySQL / MariaDB

**For development**
- Node.js
- Vite

---

## Installation

### 1.Download the Plugin: Clone this repository or download the ZIP

```bash
git clone https://github.com/Dibyo178/fluentsCart
```
### 2.Upload to WordPress

 - Navigate to your WordPress site's wp-content/plugins/ directory.
 - Copy the plugin folder (fluentcart-shipping-restriction) there.

 ### 3.Activate the Plugin:

 - Go to WordPress Admin > Plugins.
 - Find "FluentCart Shipping Restriction" and activate it.
   
 ### 3.Activate the Plugin:

 - Go to WordPress Admin > Plugins.
 - Find "FluentCart Shipping Restriction" and activate it.

  ### 4.Database Tables:

  
3. Select the database and create required tables:

```sql
CREATE TABLE IF NOT EXISTS wp_fc_shipping_method_restrictions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  method_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = Global Rule',
  allowed_countries TEXT NULL,
  excluded_countries TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY method_unique (method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

```
Example Default Insert (Global Rule)

```sql
INSERT INTO wp_fc_shipping_method_restrictions
(method_id, allowed_countries, excluded_countries)
VALUES
(0, 'US,CA,DE', 'BD,PK');


```

 ### 4.Build Assets (for Development):

  - Navigate to the plugin's resources/ directory (assuming Vue source there).
  - Run:
 
```
npm install
npm run dev  # For local dev
npm run build  # For production


```
## Usage
Admin Settings:
- Go to FluentCart > FC Shipping (submenu).
- Select mode: "GLOBAL" or a specific shipping method from the dropdown.
- Add ISO codes to Allowed/Excluded lists (e.g., "US", "CA").
- Save changes. Rules are applied immediately.

## Checkout Experience
Admin Settings:
-  On the checkout page, if a restricted country is selected:
-  A warning message appears (e.g., "🚫 We do not ship to CA.").
-  Submit button is disabled.
-  Server-side: Invalid checkouts are blocked with an error.

## Logs
Custom Tables:
- Uses FluentCart's wp_fct_order_meta for logs.
 
## Hooks Used:

- fluent_cart/validate_checkout_data: Backend validation.
- fluent_cart/shipping/available_methods: Filter methods.
- fluent_cart/order_created: Log restrictions.

## Options: 

- fc_restriction_mode: Stores current mode (global or method ID).

## Development Mode: 

-  Set $is_dev_mode = true; in the plugin file to load from Vite server (e.g., localhost:5173).
  

## 👨‍💻 Author

**Sourov Purkayastha**
🌐 [sourovdev.space](https://sourovdev.space/)




