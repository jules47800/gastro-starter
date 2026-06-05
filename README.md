# Gastro Starter — Thème WordPress complet pour restaurant

Thème duplicable pour restaurants, incluant un système de réservation, bons cadeaux, CRM client, emailing et SEO.

## Duplication pour un nouveau restaurant

1. Copier le dossier `gastro-starter/` et le renommer (ex: `mon-resto/`)
2. Dans `style.css`, changer le Theme Name
3. Activer le thème dans WordPress
4. Configurer via **Apparence > Personnaliser** :
   - Nom du restaurant, téléphone, adresse, SIRET
   - Email, URL Instagram
   - Couleurs du thème
5. Remplacer les images dans `assets/images/` (voir `IMAGES-A-REMPLACER.md`)
6. Configurer les clés Stripe dans **Réglages > Stripe**
7. Configurer Brevo dans **Réglages > Brevo**
8. Configurer SMTP dans `inc/smtp-config.php`

## Fonctionnalités

- **Réservations avancées**
  - Vérification de disponibilité par créneau et capacité configurable
  - Fermeture hebdomadaire, vacances, délai minimum
  - Emails: confirmation, annulation, rappel (opt-in)
  - Admin: ajout sans contrainte de capacité (surbooking possible)
- **Bons cadeaux**
  - Paiement Stripe intégré
  - Génération PDF automatique
  - Envoi par email avec suivi
- **CRM Client**
  - Statistiques par client, VIP flagging
  - Historique des réservations
- **Emailing**
  - Brevo (Sendinblue) intégré
  - Templates email responsive
  - Pixel tracking des ouvertures
- **Contenus**
  - CPT `daily_menu` (Menus), `testimonial` (Témoignages), `event` (Agenda)
- **Back-office**
  - Pages admin: Réservations, Clients, Statistiques, Galerie homepage, Horaires
  - Widgets tableau de bord
- **SEO**
  - Schema.org: Restaurant, Menu, Breadcrumb, BlogPosting
  - Meta dynamiques, sitemap
- **Multi-langue**
  - Français, Anglais, Espagnol (fichiers .po/.mo)

## Structure

```
gastro-starter/
├── inc/                 (modules PHP)
├── assets/css/          (styles frontend + admin)
├── assets/js/           (scripts frontend + admin)
├── assets/images/       (images placeholder)
├── template-parts/      (composants réutilisables)
├── languages/           (traductions)
└── templates/           (page templates additionnels)
```

## Prérequis

- WordPress 6.0+
- PHP 8.0+
- Clés API Stripe (pour les bons cadeaux)
- Compte Brevo (optionnel, pour l'emailing marketing)

## Licence

GNU General Public License v2 or later
