<?php
/**
 * Configuration Template
 * Copiar a config.php y configurar valores
 *
 * SECURITY: Este archivo es generado por el instalador
 * NUNCA commitear config.php al repositorio
 */

return [
    // Base configuration
    'app_name' => 'Shop E-commerce',
    'app_url' => 'https://tu-dominio.com',
    'base_path' => '/shop', // Si está en subdirectorio

    // Security
    'secret_key' => 'CHANGE_THIS_SECRET_KEY', // Generado por instalador
    'csrf_token_expiry' => 3600, // 1 hora

    // Paths (configurados por instalador)
    'app_path' => '/var/www/shop-app',
    'public_path' => '/var/www/public_html/shop',

    // Database (futuro)
    'database' => [
        'enabled' => false,
        'host' => 'localhost',
        'name' => 'shop_db',
        'user' => 'shop_user',
        'password' => '',
    ],

    // Email
    'email' => [
        'from' => 'noreply@tu-dominio.com',
        'name' => 'Shop E-commerce',
    ],

    // Maintenance
    'maintenance_mode' => false,
    'maintenance_message' => 'Sitio en mantenimiento',

    // Debug (SIEMPRE false en producción)
    'debug' => false,
    'log_errors' => true,
];
