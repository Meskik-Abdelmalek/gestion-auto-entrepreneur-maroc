# 🇲🇦 Gestion Auto-Entrepreneur Maroc — Système de Facturation Complet v2.0

> **Logiciel open-source de facturation, devis, déclaration chiffre d'affaires Maroc et gestion complète pour Auto-Entrepreneurs marocains.**  
> Construit en PHP 8 & Tailwind CSS. Hébergeable sur n'importe quel mutualisé ou VPS.

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?logo=tailwindcss)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-22c55e)](LICENSE)
[![Mobile First](https://img.shields.io/badge/Design-Mobile_First-0078d4)](https://developer.mozilla.org/en-US/docs/Glossary/Mobile_First)
[![Fluent Design](https://img.shields.io/badge/UI-Microsoft_Fluent-0078d4)](https://fluent2.microsoft.design)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/pulls)
[![Stars](https://img.shields.io/github/stars/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc?style=social)](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/stargazers)

---

## 🧭 Table des Matières

- [À Propos du Projet](#-à-propos-du-projet)
- [Pour Qui Est Ce Logiciel ?](#-pour-qui-est-ce-logiciel-)
- [Fonctionnalités](#-fonctionnalités)
- [Captures d'Écran](#-captures-décran)
- [Installation Rapide](#-installation-rapide)
- [Architecture du Projet](#-architecture-du-projet)
- [Conformité Légale Maroc](#-conformité-légale-maroc)
- [Fiscalité Auto-Entrepreneur Maroc](#-fiscalité-auto-entrepreneur-maroc)
- [Sécurité](#-sécurité)
- [Système de Devis](#-système-de-devis)
- [Hébergement Recommandé](#-hébergement-recommandé)
- [FAQ](#-foire-aux-questions-faq)
- [Contribuer](#-contribuer)
- [Roadmap](#roadmap)
- [Licence](#-licence)

---

## 📖 À Propos du Projet

**Gestion Auto-Entrepreneur Maroc** est un système web complet, open-source et gratuit, conçu spécifiquement pour les **auto-entrepreneurs marocains** inscrits sur le [portail auto-entrepreneur officiel (rn.ae.gov.ma)](https://rn.ae.gov.ma/).

La gestion administrative d'un **auto-entrepreneur au Maroc** est unique : taux d'IR spécifiques (1% services / 0.5% commerce), plafonds de chiffre d'affaires, déclaration trimestrielle via **Simpl-AE** ou **Damancom**, cotisations CNSS mensuelles, et exonération de TVA selon l'Art. 91-I-B-1° du CGI. Aucun logiciel généraliste ne couvre tous ces besoins. Ce projet le fait.

**Ce que ce logiciel résout concrètement :**
- Créer des **factures auto entrepreneur Maroc** professionnelles aux normes locales en moins d'une minute
- Suivre le **chiffre d'affaires** par rapport aux plafonds légaux (200k/500k MAD)
- Calculer automatiquement l'**IR trimestriel** et les cotisations **CNSS**
- Générer des **devis** et les convertir en factures en 1 clic
- Relancer les impayés et gérer la **trésorerie** simplement
- Rapprocher les relevés bancaires avec les factures émises

> **Mots-clés :** facture auto entrepreneur maroc · déclaration chiffre d'affaires maroc · auto-entrepreneur maroc · portail auto-entrepreneur · taxe professionnelle maroc · simpl-ae · damancom · gestion ae maroc · logiciel comptabilité maroc · système facturation maroc

---

## 🎯 Pour Qui Est Ce Logiciel ?

Ce système s'adresse à :

| Profil | Cas d'usage |
|--------|-------------|
| 🧑‍💻 **Développeur / Freelance IT** | Facturer ses clients en MAD, suivre les projets, gérer les devis |
| 🎨 **Designer / Créatif** | Devis illustrés, conversion facture, suivi des paiements |
| 📚 **Enseignant / Formateur** | Facturation de sessions, suivi annuel CA |
| 🔧 **Artisan / Prestataire** | Facturation de services, gestion des charges |
| 📊 **Consultant** | Suivi clientèle, rapport annuel, rapprochement bancaire |
| 🏪 **Commerce / Revente** | Plafond 500k MAD, taux IR 0.5%, suivi stock simplifié |

**Non requis :** aucune connaissance en comptabilité, aucun abonnement, aucun cloud tiers. Vos données restent sur **votre propre hébergement**.

---

## ✨ Fonctionnalités

### 📊 Tableau de Bord (Dashboard)
- KPIs temps réel : chiffre d'affaires du mois, trimestre, année
- **Jauges de plafond** CA visuelles (200k MAD services / 500k MAD commerce)
- Graphiques SVG des tendances mensuelles — sans dépendance JS externe
- Alertes automatiques : factures en retard, plafond proche, déclaration imminente
- Résumé net : CA – Dépenses = Résultat net estimé

### 📄 Factures Auto-Entrepreneur Maroc
- Création rapide avec autocomplétion client et produits
- Impression **A4 professionnelle** avec mentions légales AE (n° registre, exonération TVA)
- **Bulk actions** : marquer payées, supprimer, exporter en CSV
- Duplication en 1 clic pour factures récurrentes
- Filtres avancés : par statut, client, période, montant
- Export CSV pour votre comptable ou votre déclaration fiscale

### 📋 Devis (Nouveau v2)
- Numérotation automatique au format `DEV-YYYYMM-NNN`
- Statuts complets : `Brouillon → Envoyé → Accepté / Refusé / Expiré`
- **Conversion en facture en 1 clic** — toutes les lignes sont copiées
- Zone de signature client sur l'impression
- Validité configurable (défaut : 30 jours)
- Export CSV, duplication, impression A4

### 👥 Gestion Clients
- Annuaire clients complet avec fiche détaillée
- Historique de toutes les factures par client
- Chiffre d'affaires généré par client (top clients)
- **Autocomplétion** à la création de facture/devis

### 💸 Suivi des Dépenses
- Enregistrement des charges professionnelles par catégorie
- **Donut chart** interactif des catégories de dépenses
- Calcul automatique du résultat net (CA – Charges)

### 📅 Déclarations Fiscales
- **IR trimestriel** : calcul automatique selon votre activité (1% ou 0.5%)
- **CNSS mensuel** : rappels et suivi des cotisations sociales
- Lien direct vers le portail officiel **[rn.ae.gov.ma](https://rn.ae.gov.ma/)** pour la déclaration
- Historique des déclarations passées

### 📬 Relances Impayés
- Tracker des factures impayées avec **indicateurs d'urgence** colorés 🟢🟡🟠🔴
- Jours de retard calculés automatiquement
- Vue consolidée de tous les impayés

### 🏦 Rapprochement Bancaire
- Liaison des transactions bancaires aux factures émises
- Suivi des encaissements vs facturé
- Détection des écarts

### 📈 Rapport Annuel
- CA mensuel visualisé sur 12 mois
- Classement **top clients** par CA annuel
- Tableau détaillé de toutes les factures de l'année
- Impression / export du rapport complet

### ⚙️ Paramètres & Profil AE
- Gestion des **activités professionnelles** (depuis votre fiche [rn.ae.gov.ma](https://rn.ae.gov.ma/))
- Configuration des taux fiscaux selon votre régime
- Gestion de la sécurité du compte (mot de passe, sessions)
- Logo et informations légales affichés sur les documents

### 🔍 Recherche Globale
- Raccourci clavier `Ctrl+K` — accès instantané depuis n'importe quelle page
- Recherche unifiée : factures, devis, clients en temps réel
- Résultats filtrés et cliquables

### 🌙 Mode Sombre / Clair
- Toggle persistant (préférence sauvegardée)
- Implémenté avec **Microsoft Fluent Design dark tokens**
- Confort visuel pour une utilisation longue

### 📱 Progressive Web App (PWA)
- Installable sur smartphone comme une application native
- Interface **Mobile First** — utilisable sur téléphone au bureau du client
- `manifest.json` inclus

---

## 📸 Captures d'Écran

> *Des captures d'écran du dashboard, des factures et du module de déclaration seront ajoutées prochainement. Contribuez en ouvrant une PR !*

---

## 🚀 Installation Rapide

### Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| PHP | 8.1+ |
| MySQL | 8.0+ (ou MariaDB 10.6+) |
| Serveur web | Apache / Nginx / Caddy |
| Extensions PHP | `pdo_mysql`, `mbstring`, `openssl` |

---

### Option 1 — Installateur Web ✅ (Recommandé pour débutants)

L'installateur **graphique en 4 étapes** crée la base de données, configure le compte admin et votre profil AE automatiquement — aucune ligne de commande requise.

```bash
# 1. Cloner le dépôt
git clone https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc.git
cd gestion-auto-entrepreneur-maroc

# 2. Ouvrir l'installateur dans le navigateur
# http://votre-domaine.com/install.php
```

**Les 4 étapes de l'installateur :**
1. Vérification de l'environnement PHP
2. Configuration de la connexion MySQL
3. Création des tables et données initiales
4. Création du compte administrateur + profil AE

> ⚠️ **Sécurité critique : Supprimez `install.php`** immédiatement après installation !

**Identifiants par défaut** : `admin` / `AEMaroc2026!` — **Changez-les immédiatement après connexion !**

---

### Option 2 — Installation Manuelle (Développeurs)

```bash
# 1. Cloner le projet
git clone https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc.git
cd gestion-auto-entrepreneur-maroc

# 2. Créer la base de données et importer le schéma
mysql -u root -p -e "CREATE DATABASE ae_maroc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p ae_maroc < sql/schema.sql

# 3. Configurer la connexion
cp config.example.php config.php
nano config.php  # Renseignez vos credentials MySQL

# 4. Lancer le serveur de développement local
php -S localhost:8080
```

---

### Option 3 — Docker (Développement Local Rapide)

```bash
# Lancer un conteneur PHP + Apache avec le code monté
docker run -p 8080:80 -v $(pwd):/var/www/html php:8.1-apache

# Ou avec docker-compose (MySQL inclus) — voir docker-compose.yml
docker-compose up -d
```

---

### Installation sur Hébergement cPanel / Hostinger

1. Téléchargez le `.zip` de la [dernière release](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/releases)
2. Uploadez dans `public_html` via le **Gestionnaire de Fichiers cPanel**
3. Créez une base de données MySQL vide depuis cPanel → **Bases de données MySQL**
4. Ouvrez `https://votre-domaine.com/install.php` dans le navigateur
5. Suivez les 4 étapes de l'assistant
6. ⚠️ **Supprimez `install.php`** depuis le gestionnaire de fichiers

---

### Configuration Nginx (VPS)

```nginx
server {
    listen 443 ssl;
    server_name votre-domaine.com;
    root /var/www/gestion-ae-maroc;
    index index.php dashboard.php;

    # Sécurité : bloquer includes/ et sql/
    location ~* ^/(includes|sql)/ {
        deny all;
        return 404;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## 🎯 Activités Professionnelles

Lors de la configuration, renseignez vos activités depuis votre espace AE officiel. Ces activités apparaissent sur vos **factures auto entrepreneur Maroc** et vos devis.

1. Connectez-vous sur **[rn.ae.gov.ma](https://rn.ae.gov.ma/)** (portail auto-entrepreneur officiel)
2. Accédez à votre fiche d'entreprise
3. Copiez le libellé **exact** de votre Activité Professionnelle
4. Renseignez-la dans **Paramètres → Activités**

> 💡 Vous pouvez enregistrer **plusieurs activités** si vous êtes multi-activités (ex: développement web + formation informatique).

---

## 🧾 Conformité Légale Maroc

Ce logiciel est conçu en conformité avec la **législation marocaine** applicable aux auto-entrepreneurs :

| Règle | Détail |
|-------|--------|
| ✅ **Exonération TVA** | Art. 91-I-B-1° du Code Général des Impôts (CGI) |
| ✅ **IR Services** | 1% du chiffre d'affaires HT |
| ✅ **IR Commerce** | 0.5% du chiffre d'affaires HT |
| ✅ **Plafond Services** | 200 000 MAD / an |
| ✅ **Plafond Commerce** | 500 000 MAD / an |
| ✅ **Déclaration CA** | Trimestrielle via Simpl-AE ou Damancom |
| ✅ **CNSS** | Cotisations sociales mensuelles |
| ✅ **Registre officiel** | Activités depuis [rn.ae.gov.ma](https://rn.ae.gov.ma/) |

---

## 💡 Fiscalité Auto-Entrepreneur Maroc

Cette section explique comment le système calcule automatiquement vos obligations fiscales.

### Impôt sur le Revenu (IR) — Déclaration Trimestrielle

L'auto-entrepreneur au Maroc est soumis à un **IR libératoire** sur son chiffre d'affaires trimestriel :

```
Activité de Services       →  IR = CA trimestriel × 1%
Activité Commerciale       →  IR = CA trimestriel × 0.5%
Activité Artisanale        →  IR = CA trimestriel × 1%
```

**Exemple concret :**
- CA trimestre 1 (Services) : 15 000 MAD
- IR dû : 15 000 × 1% = **150 MAD**
- À payer via **[rn.ae.gov.ma](https://rn.ae.gov.ma/)** avant le **31 du mois suivant la fin du trimestre**

Le tableau de bord affiche votre IR estimé en temps réel.

### Plafonds de Chiffre d'Affaires

Le régime AE est maintenu **tant que le CA annuel ne dépasse pas** :

| Activité | Plafond annuel |
|----------|---------------|
| Prestations de services | **200 000 MAD** |
| Vente de marchandises | **500 000 MAD** |
| Activité mixte | Le plafond de la branche principale s'applique |

> 🔔 Le système affiche des **alertes automatiques** à 80%, 90% et 100% du plafond.

### Cotisations CNSS

Les cotisations CNSS sont **mensuelles** et basées sur le CA déclaré. Le système génère des rappels mensuels et un récapitulatif annuel.

### Taxe Professionnelle (TP)

Les auto-entrepreneurs bénéficient d'une **exonération totale de la taxe professionnelle** pendant les 5 premières années d'activité. Au-delà, le système vous alertera. La **taxe professionnelle Maroc** n'est donc pas calculée automatiquement pour les nouvelles inscriptions.

---

## 📐 Architecture du Projet

```
gestion-auto-entrepreneur-maroc/
│
├── 📁 includes/                   ← Cœur de l'application
│   ├── auth.php                   ← Authentification (bcrypt, sessions, remember-me)
│   ├── functions.php              ← Helpers: stats, devis, exports, recherche
│   ├── header.php                 ← Layout + auth guard + navigation principale
│   └── footer.php                 ← JS global : dark mode, Ctrl+K, drawer mobile
│
├── 📁 api/                        ← Endpoints JSON (appels AJAX)
│   ├── invoices.php               ← CRUD factures (delete, mark_paid, duplicate, bulk)
│   ├── quotes.php                 ← CRUD devis (convert, delete, duplicate)
│   └── search.php                 ← Recherche globale — renvoie JSON
│
├── 📁 sql/
│   └── schema.sql                 ← 9 tables MySQL avec indexes optimisés
│
├── dashboard.php                  ← Tableau de bord principal
├── invoices.php                   ← Liste factures (bulk, filtres, tri, CSV)
├── invoice-new.php                ← Créer une nouvelle facture
├── invoice-view.php               ← Voir + imprimer facture A4
├── invoice-edit.php               ← Modifier une facture existante
├── quotes.php                     ← Liste des devis
├── quote-new.php                  ← Créer un nouveau devis
├── quote-view.php                 ← Voir + imprimer devis A4
├── quote-edit.php                 ← Modifier un devis
├── clients.php                    ← Annuaire clients + historique
├── expenses.php                   ← Suivi des dépenses et charges
├── declarations.php               ← Module IR trimestriel + CNSS
├── reminders.php                  ← Tracker des relances impayées
├── bank.php                       ← Rapprochement bancaire
├── report.php                     ← Rapport annuel complet
├── settings.php                   ← Paramètres profil + sécurité
├── login.php                      ← Page de connexion sécurisée
├── logout.php                     ← Déconnexion + invalidation session
├── install.php                    ← Installateur web 4 étapes (⚠️ à supprimer!)
├── config.php                     ← Configuration DB (généré par l'installateur)
├── .htaccess                      ← Sécurité Apache + headers HTTP
└── manifest.json                  ← PWA manifest
```

### Schéma de la Base de Données (9 tables)

```sql
users           -- Compte(s) admin + préférences
clients         -- Annuaire clients
invoices        -- Factures (header)
invoice_items   -- Lignes de facture (détail)
quotes          -- Devis (header)
quote_items     -- Lignes de devis (détail)
expenses        -- Dépenses et charges
bank_entries    -- Transactions bancaires
declarations    -- Historique des déclarations IR/CNSS
```

---

## 🔐 Sécurité

La sécurité est une priorité absolue pour un logiciel qui gère des **données financières sensibles**.

| Couche | Mécanisme | Détail |
|--------|-----------|--------|
| **Mots de passe** | bcrypt | `password_hash()` avec `PASSWORD_DEFAULT` |
| **Brute-force** | Rate limiting | 5 tentatives max, verrouillage 15 min |
| **Sessions** | Hardening complet | Régénération d'ID, fingerprint IP+UA, cookies `httponly` |
| **Remember Me** | Token rotatif | Token hashé SHA-256, rotation à chaque usage |
| **CSRF** | Token par session | Présent sur tous les formulaires POST |
| **XSS** | Échappement systématique | `htmlspecialchars()` sur toutes les sorties HTML via `h()` |
| **SQL Injection** | PDO préparé | Requêtes préparées exclusivement, zéro concaténation |
| **Headers HTTP** | Sécurité navigateur | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |
| **Accès fichiers** | `.htaccess` | Bloque l'accès direct à `includes/` et `sql/` |
| **Config** | Hors webroot | `config.php` protégé par `.htaccess` |

### Bonnes Pratiques Post-Installation

```bash
# 1. Supprimer l'installateur
rm install.php

# 2. Changer le mot de passe admin par défaut
# → Paramètres → Sécurité → Changer mot de passe

# 3. Vérifier les permissions des fichiers
chmod 600 config.php
chmod 750 includes/ sql/

# 4. Activer HTTPS (certificat Let's Encrypt gratuit)
certbot --nginx -d votre-domaine.com
```

---

## 📋 Système de Devis

Le module de devis a été entièrement revu en v2 pour couvrir le workflow complet d'un **auto-entrepreneur marocain** qui répond à des appels d'offres ou travaille en avant-vente.

### Format de Numérotation

```
DEV-202605-001
│   │      └── Numéro séquentiel dans le mois (auto-incrémenté)
│   └────────── Année + Mois (YYYYMM)
└────────────── Préfixe fixe DEV
```

### Workflow Complet

```
Brouillon  →  Envoyé  →  ┌── Accepté  →  [Converti en Facture FAC-YYYYMM-NNN]
                          ├── Refusé
                          └── Expiré (automatique après date de validité)
```

### Conversion Devis → Facture

La conversion est **instantanée et complète** : toutes les lignes (désignation, quantité, prix unitaire, remise) sont copiées dans la nouvelle facture. Le devis est marqué `Converti` et lié à la facture créée.

---

## ⚙️ Configuration `.htaccess` Apache

Le fichier `.htaccess` fourni configure :
- Redirection HTTPS automatique (à décommenter après installation du certificat SSL)
- Protection des fichiers sensibles (`config.php`, `sql/`, `includes/`)
- En-têtes de sécurité HTTP (CSP, HSTS, X-Frame-Options...)
- Compression GZIP pour les assets statiques
- Cache navigateur optimisé (CSS, JS, images)

---

## 🌐 Hébergement Recommandé

Le système est conçu pour être **ultra-léger** et fonctionner sur n'importe quel hébergement mutualisé avec PHP 8.1+ et MySQL — sans installation de frameworks, sans Composer, sans npm.

### Critères de Choix d'Hébergement

Pour un logiciel de **gestion financière**, les critères prioritaires sont :
1. ✅ **Sauvegardes automatiques quotidiennes** (vos données de facturation sont critiques)
2. ✅ **PHP 8.1+** et **MySQL 8.0+**
3. ✅ **HTTPS / SSL** inclus (Let's Encrypt ou certificat propriétaire)
4. ✅ **Support cPanel** pour faciliter la gestion de la base de données

**🔥 Notre Recommandation : Hostinger Business**

Pour une sécurité optimale, des sauvegardes quotidiennes automatiques (crucial pour vos données financières) et une installation en 1 clic, nous recommandons le plan Business de Hostinger.

👉 **[Obtenir l'Hébergement Hostinger (Lien Affilié)](https://www.hostinger.com/cart?product=hosting%3Ahostinger_business&period=12&referral_type=cart_link&REFERRALCODE=MeskikCode&referral_id=019e09f2-af8d-7060-ba07-3424437635f2)**

> **Note :** Ce lien est un lien affilié — vous bénéficiez d'une **réduction exclusive** sur votre commande, et son utilisation soutient le développement open-source de ce projet.

---

## ❓ Foire aux Questions (FAQ)

### 🏛️ Questions Légales & Fiscales

**Q : Ce logiciel est-il conforme à la législation marocaine pour auto-entrepreneurs ?**  
R : Oui. Les taux d'IR (1%/0.5%), les plafonds (200k/500k MAD), la numérotation des factures, la mention d'exonération TVA (Art. 91-I-B-1° du CGI) et les liens vers Simpl-AE/Damancom sont tous intégrés.

**Q : Mes factures ont-elles une valeur légale ?**  
R : Les factures générées incluent toutes les mentions légales obligatoires pour un AE marocain : numéro de registre, activité professionnelle, exonération TVA, numérotation séquentielle. Elles sont imprimables en A4. Consultez votre conseiller fiscal pour validation dans votre cas spécifique.

**Q : Comment déclarer mon chiffre d'affaires avec ce logiciel ?**  
R : Le module **Déclarations** calcule automatiquement votre IR trimestriel et vous fournit le montant exact à déclarer. Toutes les démarches se font désormais directement sur **[rn.ae.gov.ma](https://rn.ae.gov.ma/)** (les anciens portails Simpl-AE et Damancom ne sont plus actifs). Le logiciel ne se connecte pas directement au portail — vous saisissez manuellement le montant calculé.

**Q : La taxe professionnelle Maroc est-elle gérée ?**  
R : Les AE bénéficient d'une exonération de la taxe professionnelle pendant 5 ans. Le logiciel vous alertera à l'approche de la fin de cette période. La TP n'est pas calculée pour les inscriptions récentes.

---

### 💻 Questions Techniques

**Q : Faut-il des connaissances en programmation pour installer ce logiciel ?**  
R : Non. L'installateur web graphique (Option 1) ne nécessite aucune ligne de commande. Vous aurez besoin d'un accès à un hébergement web avec PHP et MySQL.

**Q : Le logiciel fonctionne-t-il sur un hébergement mutualisé Maroc (OVH, Hostinger, etc.) ?**  
R : Oui. N'importe quel hébergement avec PHP 8.1+ et MySQL 8.0+ fonctionne. Pas besoin de VPS ou de serveur dédié.

**Q : Mes données sont-elles stockées sur vos serveurs ?**  
R : Non. Ce logiciel est **auto-hébergé**. Vos données restent intégralement sur votre propre hébergement. Pas de cloud tiers, pas d'abonnement, pas de données partagées.

**Q : Peut-on utiliser MariaDB à la place de MySQL ?**  
R : Oui. MariaDB 10.6+ est entièrement compatible.

**Q : Peut-on avoir plusieurs utilisateurs ?**  
R : La v2 fonctionne avec un compte admin unique. Le support multi-utilisateurs (collaborateurs, comptable en lecture seule) est prévu dans la roadmap v3.

**Q : Le logiciel est-il disponible en arabe ?**  
R : L'interface est actuellement en français (langue officielle administrative au Maroc). Le support de l'arabe (RTL) est dans la roadmap.

**Q : Peut-on utiliser ce logiciel sur smartphone ?**  
R : Oui. L'interface est **Mobile First** et installable comme une PWA (Progressive Web App) sur Android et iOS.

---

### 📄 Questions sur la Facturation

**Q : Comment numéroter mes factures ?**  
R : Le système génère automatiquement les numéros au format `FAC-YYYYMM-NNN` (ex: `FAC-202605-042`). La numérotation est séquentielle et ne peut pas être dupliquée.

**Q : Puis-je personnaliser le modèle de facture avec mon logo ?**  
R : Pas encore dans la v2 — c'est une fonctionnalité très demandée, prévue dans la **v2.1** (voir Roadmap). Les factures affichent actuellement le nom et les informations de votre profil AE.

**Q : Comment exporter mes factures pour mon comptable ?**  
R : L'export CSV est disponible directement depuis la liste des factures avec filtres (période, statut, client). Chaque ligne de facture est détaillée.

---

## Roadmap

### v2.1 (Court terme)
- [ ] **Upload de logo** sur les factures et devis (très demandé)
- [ ] **Plusieurs banques, portefeuilles électroniques, espèces et plusieurs tirelires** pour gérer tous les revenus, les dépenses et les transactions entre eux (très demandé)
- [ ] Envoi de factures/devis par e-mail depuis l'interface
- [ ] Import relevé bancaire (CSV OCP, Attijariwafa, BMCE...)
- [ ] Génération PDF côté serveur (Dompdf)
- [ ] Mode multi-activités amélioré

### v3.0 (Moyen terme)
- [ ] Interface en **arabe** (support RTL complet)
- [ ] **Multi-utilisateurs** (comptable en lecture seule, collaborateur)
- [ ] API REST complète (pour intégration avec d'autres outils)
- [ ] Notifications e-mail automatiques (relances, échéances déclaration)

### Idées Communauté (Votes bienvenus)
- [ ] Application mobile native (React Native / Flutter)
- [ ] Support multi-devises (EUR, USD pour clients étrangers)
- [ ] Module devis illustrés (pour designers, architectes)
- [ ] Synchronisation Google Drive / Dropbox des PDF

> 💡 **Proposez une fonctionnalité** en ouvrant une [issue GitHub](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/issues/new?labels=enhancement&template=feature_request.md)

---

## 🤝 Contribuer

Les contributions sont les bienvenues et encouragées ! Ce projet est **fait par et pour la communauté des auto-entrepreneurs marocains**.

```bash
# Workflow standard
git fork https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc
git clone https://github.com/VOTRE-USERNAME/gestion-auto-entrepreneur-maroc
git checkout -b feature/ma-nouvelle-fonctionnalite
# ... vos modifications ...
git commit -m "feat: ajouter X"
git push origin feature/ma-nouvelle-fonctionnalite
# → Ouvrir une Pull Request sur GitHub
```

### Guidelines de Contribution

| Règle | Détail |
|-------|--------|
| **Pas de framework PHP** | Vanilla PHP uniquement — accessibilité maximale |
| **CSS** | Tailwind CSS uniquement (pas de CSS custom sauf si absolument nécessaire) |
| **Base de données** | PDO avec requêtes préparées obligatoire — aucune concaténation SQL |
| **Sorties HTML** | Toujours utiliser `h()` (wrapper `htmlspecialchars`) sur toutes les variables |
| **Formulaires POST** | Toujours appeler `verifyCsrf()` au début du handler |
| **JavaScript** | Vanilla JS uniquement — pas de jQuery, pas de framework |
| **Tests** | Tester sur PHP 8.1 minimum, MySQL 8.0 et MariaDB 10.6 |

### Types de Contributions Appréciées

- 🐛 **Correction de bugs** — ouvrez une issue d'abord pour discussion
- 🌐 **Traduction arabe** — très demandée par la communauté
- 📸 **Captures d'écran** — pour enrichir la documentation
- 📝 **Documentation** — guides d'installation, tutoriels vidéo
- 🧪 **Tests** — scripts de test, rapports de compatibilité hébergeurs marocains
- 💡 **Retours d'expérience** — utilisez-vous le logiciel ? Partagez votre feedback !

---

## 📊 Statistiques du Projet

- **Langage principal :** PHP (Vanilla, pas de framework)
- **Frontend :** HTML5 + Tailwind CSS 3.x + SVG natif
- **Base de données :** MySQL 8.0 / MariaDB 10.6
- **Tables :** 9 tables relationnelles
- **Pages :** 20+ modules fonctionnels
- **Licence :** MIT (utilisation commerciale autorisée)

---

## 🔗 Ressources Officielles Auto-Entrepreneur Maroc

Toutes les démarches administratives de l'auto-entrepreneur au Maroc (inscription, déclaration de chiffre d'affaires, paiement IR et CNSS) se font désormais sur le portail unique officiel :

| Ressource | URL |
|-----------|-----|
| 🏛️ Portail Auto-Entrepreneur — inscription, déclaration CA, paiement IR & CNSS | [rn.ae.gov.ma](https://rn.ae.gov.ma/) |

---

## 📄 Licence

**MIT License** — Libre d'utilisation, modification et distribution, y compris à des fins commerciales.

```
Copyright (c) 2026 Meskik Abdelmalek

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software...
```

Voir le fichier [LICENSE](LICENSE) pour le texte complet.

---

## 🙏 Remerciements

- La communauté **auto-entrepreneurs marocains** pour leurs retours terrain
- [Tailwind CSS](https://tailwindcss.com/) et [Microsoft Fluent Design](https://fluent2.microsoft.design/) pour le système de design
- Tous les **contributeurs GitHub** qui améliorent ce projet

---

<div align="center">

### ⭐ Si ce projet vous aide dans votre activité d'auto-entrepreneur, laissez une étoile !

**[GitHub](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc)** · **[Issues](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/issues)** · **[Releases](https://github.com/Meskik-Abdelmalek/gestion-auto-entrepreneur-maroc/releases)**

---

**Made with ❤️ for Moroccan Auto-Entrepreneurs**

*Gestion Auto-Entrepreneur Maroc v2.0 · Open Source · MIT License*

*Mots-clés : facture auto entrepreneur maroc · déclaration chiffre d'affaires maroc · auto-entrepreneur maroc · portail auto-entrepreneur · taxe professionnelle maroc · gestion ae · logiciel facturation maroc · simpl-ae · damancom · ir trimestriel maroc · cnss auto entrepreneur*

</div>
