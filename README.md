# 📊 Support Insights Dashboard

![Support Report](docs/dashboard-support-report.png)

A **Symfony + MySQL analytics dashboard** that transforms raw Support Query data into  
live **KPIs, complaint themes, and downloadable reports**.  

Built as part of my **Afrihost Support → Dev & Data journey** to demonstrate bridging  
Support operations and Development insights.

---

## 🚀 Features

- **KPI Cards** – Total queries, **First Contact Resolution %**, and **Avg time-to-resolve**  
- **Category Split** – Controllable vs Uncontrollable queries  
- **Top Complaint Themes** – Top 10 controllable complaint reasons  
- **Daily Trend** – Line chart showing queries over the last 30 days  
- **Filters** – Date range + Product dropdown  
- **CSV Exports** – One-click download for Categories, Themes, Trend, and Raw data  

---

## 📸 Screenshots

**Dashboard Overview**  
![Support Report](docs/dashboard-support-report.png)

**Category Split**  
![Category Split](docs/dashboard-split.png)

**Top 10 Complaint Themes**  
![Top 10 Themes](docs/dashboard-top-10-themes.png)

**KPI Cards**  
![KPI Cards](docs/dashboard-kpis.png)

**All Products View**  
![All Products](docs/dashboard-all-products.png)

---

## 🧱 Architecture

- **Frontend**: Twig templates (Symfony)  
- **Backend**: Symfony controllers (PHP)  
- **Database**: MySQL (via Doctrine DBAL)  
- **Server**: XAMPP (Apache + PHP)  
- **ORM/DB Layer**: Doctrine ORM  

---

## ⚙️ Setup

1. **Clone this repository**
   ```bash
   git clone https://github.com/zolilemcelu/support-insights-dashboard.git
   cd support-insights-dashboard
