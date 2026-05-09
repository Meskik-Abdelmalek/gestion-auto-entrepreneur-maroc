# 🇲🇦 Moroccan AE System v2.0

**Système de gestion complet et sécurisé pour Auto-Entrepreneurs marocains**

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?logo=tailwindcss)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-22c55e)](LICENSE)
[![Mobile First](https://img.shields.io/badge/Design-Mobile_First-0078d4)](https://developer.mozilla.org/en-US/docs/Glossary/Mobile_First)
[![Fluent Design](https://img.shields.io/badge/UI-Microsoft_Fluent-0078d4)](https://fluent2.microsoft.design)

---

## ✨ Fonctionnalités

| Module | Description |
|--------|-------------|
| 🔐 **Authentification** | Login sécurisé (bcrypt, rate-limiting, remember-me, session hardening) |
| 📊 **Dashboard** | KPIs temps réel, graphiques SVG, jauges plafond, alertes, tendances |
| 📄 **Factures** | Création, édition, impression A4, bulk actions, export CSV, duplication |
| 📋 **Devis** | Système complet DEV-YYYYMM-NNN, conversion en facture en 1 clic |
| 👥 **Clients** | Annuaire, historique factures, CA par client, autocomplete |
| 💸 **Dépenses** | Suivi des charges, donut chart catégories, résultat net |
| 📅 **Déclarations** | IR trimestriel + CNSS, liens DGI/Simpl-AE/Damancom |
| 📬 **Relances** | Tracker impayés, indicateurs urgence 🟢🟡🟠🔴 |
| 🏦 **Banque** | Rapprochement bancaire avec liaison factures |
| 📈 **Rapport Annuel** | CA mensuel, top clients, tableau détaillé, impression |
| ⚙️ **Paramètres** | Activités professionnelles, sécurité, taux fiscaux |
| 🔍 **Recherche Globale** | `Ctrl+K` — factures, devis, clients en temps réel |
| 🌙 **Mode Sombre** | Toggle persistant, Fluent Design dark tokens |

---

## 🚀 Installation Rapide

### Prérequis
- PHP 8.1+
- MySQL 8.0+ (ou MariaDB 10.6+)
- Serveur web : Apache / Nginx / Caddy

### Option 1 — Installateur Web (recommandé)

```bash
# 1. Cloner le dépôt
git clone https://github.com/your-org/moroccan-ae-system.git
cd moroccan-ae-system

# 2. Ouvrir dans le navigateur
http://votre-domaine.com/install.php
```

L'installateur crée la base de données, configure le compte admin et votre profil AE en 4 étapes.

> ⚠️ **Supprimez `install.php`** après l'installation !

### Option 2 — Installation manuelle

```bash
# 1. Cloner
git clone https://github.com/your-org/moroccan-ae-system.git
cd moroccan-ae-system

# 2. Base de données
mysql -u root -p < sql/schema.sql

# 3. Configuration
cp config.example.php config.php
# Éditer config.php avec vos credentials

# 4. Serveur de développement
php -S localhost:8080
```

**Identifiants par défaut** : `admin` / `AEMaroc2026!` — **Changez-les immédiatement !**

---

## 🎯 Activités Professionnelles

Lors de la configuration, renseignez vos activités depuis votre espace AE officiel :

1. Connectez-vous sur **[rn.ae.gov.ma](https://rn.ae.gov.ma/)**
2. Accédez à votre fiche d'entreprise
3. Copiez le libellé exact de votre **Activité Professionnelle**
4. Renseignez-la dans **Paramètres → Activités**

Ces activités apparaîtront sur vos factures et devis.

---

## 📐 Architecture du Projet

```
moroccan-ae/
├── 📁 includes/
│   ├── auth.php           ← Authentification (bcrypt, sessions, remember-me)
│   ├── functions.php      ← Helpers: stats, quotes, exports, search
│   ├── header.php         ← Layout + auth guard + nav
│   └── footer.php         ← JS: dark mode, search, drawer
├── 📁 api/
│   ├── invoices.php       ← CRUD factures (delete, mark_paid, duplicate, bulk)
│   ├── quotes.php         ← CRUD devis (convert, delete, duplicate)
│   └── search.php         ← Recherche globale JSON
├── 📁 sql/
│   └── schema.sql         ← 9 tables MySQL avec indexes
├── dashboard.php          ← Tableau de bord
├── invoices.php           ← Liste factures (bulk, filter, sort, CSV)
├── invoice-new.php        ← Créer facture
├── invoice-view.php       ← Voir + imprimer facture
├── invoice-edit.php       ← Modifier facture
├── quotes.php             ← Liste devis
├── quote-new.php          ← Créer devis
├── quote-view.php         ← Voir + imprimer devis
├── quote-edit.php         ← Modifier devis
├── clients.php            ← Gestion clients
├── expenses.php           ← Dépenses
├── declarations.php       ← IR + CNSS
├── reminders.php          ← Relances
├── bank.php               ← Rapprochement bancaire
├── report.php             ← Rapport annuel
├── settings.php           ← Paramètres + sécurité
├── login.php              ← Page de connexion
├── logout.php             ← Déconnexion
├── install.php            ← Installateur web (supprimer après install)
├── config.php             ← Configuration DB (généré à l'install)
├── .htaccess              ← Sécurité Apache
└── manifest.json          ← PWA
```

---

## 🔐 Sécurité

| Fonctionnalité | Détail |
|---|---|
| **Hachage** | `password_hash()` bcrypt (PASSWORD_DEFAULT) |
| **Rate limiting** | 5 tentatives max, verrouillage 15 min |
| **Session** | Régénération d'ID, fingerprint IP+UA, httponly cookies |
| **Remember me** | Token hashé (SHA-256), rotation à chaque usage |
| **CSRF** | Token par session sur tous les formulaires POST |
| **XSS** | `htmlspecialchars()` sur toutes les sorties |
| **SQL injection** | PDO avec requêtes préparées exclusivement |
| **Headers** | X-Content-Type-Options, X-Frame-Options, Referrer-Policy |
| **Accès fichiers** | `.htaccess` bloque `includes/` et `sql/` |

---

## 📋 Système de Devis (Nouveau v2)

- Format numérotation : `DEV-202605-001`
- Statuts : `Brouillon → Envoyé → Accepté / Refusé / Expiré`
- **Conversion en facture** en 1 clic (copie toutes les lignes)
- Validité configurable (défaut : 30 jours)
- Zone de signature client sur l'impression
- Export CSV, duplication, impression A4

---

## 🧾 Conformité Légale Maroc

- ✅ Exonéré de TVA — Art. 91-I-B-1° du CGI
- ✅ IR 1% sur CA Services / 0.5% sur CA Commerce
- ✅ Plafonds : 200 000 MAD (Services) / 500 000 MAD (Commerce)
- ✅ Déclaration trimestrielle (Simpl-AE / Damancom)
- ✅ Cotisations CNSS mensuelles
- ✅ Activités depuis registre officiel [rn.ae.gov.ma](https://rn.ae.gov.ma/)

---

## ⚙️ Configuration `.htaccess` (Apache)

Le fichier `.htaccess` inclus configure :
- Redirection HTTPS automatique (décommentez si nécessaire)
- Protection des fichiers sensibles (`config.php`, `sql/`)
- En-têtes de sécurité HTTP
- Cache navigateur pour les assets statiques

---

## 🌐 Hébergement (Recommandé)

Le système est conçu pour être ultra-léger et fonctionner sur n'importe quel hébergement mutualisé ou VPS.

**🔥 Notre Recommandation : Hostinger Business**
Pour une sécurité optimale, des sauvegardes quotidiennes automatiques (crucial pour vos données financières) et une installation en 1 clic, nous recommandons le plan Business de Hostinger.

👉 **[Obtenir l'Hébergement Hostinger (Lien Affilié)](https://www.hostinger.com/cart?product=hosting%3Ahostinger_business&period=12&referral_type=cart_link&REFERRALCODE=MeskikCode&referral_id=019e09f2-af8d-7060-ba07-3424437635f2)**

### Installation sur cPanel / Hostinger
1. Téléchargez le `.zip` de la dernière release.
2. Uploadez-le dans le dossier `public_html` via le Gestionnaire de Fichiers.
3. Créez une base de données MySQL vide.
4. Ouvrez votre nom de domaine dans le navigateur (ex: `https://votre-site.com/install.php`).
5. Suivez les 4 étapes de l'assistant d'installation.
6. ⚠️ **Supprimez `install.php`** immédiatement après via votre gestionnaire de fichiers.

### Nginx (VPS Avancé)
```nginx
server {
    root /var/www/moroccan-ae;
    index index.php dashboard.php;
    location ~ \.php$ { fastcgi_pass unix:/run/php/php8.1-fpm.sock; include fastcgi_params; }
    location ~* ^/(includes|sql)/ { deny all; }
    location ~ /\.ht { deny all; }
}
```

### Docker (développement)
```bash
docker run -p 8080:80 -v $(pwd):/var/www/html php:8.1-apache
```

---

## 🤝 Contribuer

Les contributions sont les bienvenues !

```bash
# Fork → Clone → Branche → Commit → Push → Pull Request
git checkout -b feature/ma-fonctionnalite
git commit -m "feat: ajouter X"
git push origin feature/ma-fonctionnalite
```

**Guidelines :**
- Vanilla PHP — pas de framework
- Tailwind CSS uniquement (pas de CSS custom sauf nécessaire)
- PDO avec requêtes préparées obligatoire
- `h()` sur toutes les sorties HTML
- `verifyCsrf()` sur tous les POST

---

## 📄 Licence

**MIT License** — Libre d'utilisation, modification et distribution commerciale.

---

<div align="center">
    <strong>Made with ❤️ for Moroccan Auto-Entrepreneurs</strong><br>
    <sub>Moroccan AE System v2.0 · Open Source · MIT License</sub>
</div>
