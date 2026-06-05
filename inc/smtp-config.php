<?php
/**
 * Configuration SMTP pour l'envoi d'emails
 *
 * @package Gastro_Starter
 */

// Vérifier que ce fichier est bien inclus
if (!defined('ABSPATH')) {
    die('Accès direct interdit');
}

// Log que le fichier de configuration SMTP est chargé
error_log('Configuration SMTP Mon Restaurant chargée');

// Configuration SMTP OVH
define('SMTP_HOST', 'ssl0.ovh.net');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

// Authentification SMTP
define('SMTP_USER', 'contact@mon-restaurant.fr');
define('SMTP_PASS', '');

// Configuration des emails
define('RESERVATION_FROM_EMAIL', 'contact@mon-restaurant.fr');
define('RESERVATION_FROM_NAME', 'Mon Restaurant');

// Configuration de débogage
define('SMTP_DEBUG', true);
define('SMTP_DEBUG_OUTPUT', 'error_log');

// Paramètres supplémentaires pour améliorer la fiabilité
define('SMTP_TIMEOUT', 30); // Timeout en secondes
define('SMTP_KEEP_ALIVE', true); // Garder la connexion active
define('SMTP_VERIFY_PEER', false); // Désactiver la vérification SSL en développement
define('SMTP_VERIFY_PEER_NAME', false); // Désactiver la vérification du nom d'hôte en développement

// Log de confirmation de la configuration
error_log('Configuration SMTP Mon Restaurant : Host=' . SMTP_HOST . ', Port=' . SMTP_PORT . ', User=' . SMTP_USER); 