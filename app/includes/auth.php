<?php
/**
 * Authentication System
 * Manejo de autenticación de usuarios y administradores
 *
 * IMPORTANTE: Este archivo es cargado por bootstrap.php
 * functions.php y rate_limit.php ya están cargados
 */

// Requires ya manejados por bootstrap

/**
 * Get users file path (configurable location)
 * @return string Path to users.json file
 */
function get_users_file_path() {
    $users_path_file = __DIR__ . '/../.users_path';

    if (file_exists($users_path_file)) {
        $path = trim(file_get_contents($users_path_file));
        if (!empty($path)) {
            return $path;
        }
    }

    // Fallback to default location
    return __DIR__ . '/../data/passwords/users.json';
}

/**
 * Authenticate admin user
 * @param string $username Username
 * @param string $password Password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticate_admin($username, $password) {
    $credentials = __DIR__ . '/../config/credentials.php';
    if (file_exists($credentials)) {
        $config = require $credentials;
        $max_attempts = $config['security']['rate_limit_attempts'] ?? 5;
        $period = $config['security']['rate_limit_period'] ?? 900;
    } else {
        $max_attempts = 5;
        $period = 900;
    }

    // Check rate limit
    $identifier = $_SERVER['REMOTE_ADDR'] . ':' . $username;
    $rate_limit = check_rate_limit($identifier, $max_attempts, $period);

    if (!$rate_limit['allowed']) {
        $minutes = ceil($rate_limit['retry_after'] / 60);
        return [
            'success' => false,
            'message' => "Demasiados intentos fallidos. Intenta nuevamente en $minutes minutos.",
            'user' => null,
            'rate_limited' => true
        ];
    }

    // Load users
    $users_file = get_users_file_path();
    $users_data = read_json($users_file);

    if (!isset($users_data['users'])) {
        return [
            'success' => false,
            'message' => 'Error en el sistema de autenticación.',
            'user' => null
        ];
    }

    // Find user
    $user = null;
    foreach ($users_data['users'] as $u) {
        if ($u['username'] === $username) {
            $user = $u;
            break;
        }
    }

    if (!$user) {
        // Record failed attempt even if user doesn't exist (security)
        record_failed_attempt($identifier, $max_attempts, $period);

        return [
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos.',
            'user' => null
        ];
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Record failed attempt
        $is_blocked = record_failed_attempt($identifier, $max_attempts, $period);

        $message = 'Usuario o contraseña incorrectos.';
        if ($is_blocked) {
            $minutes = ceil($period / 60);
            $message = "Demasiados intentos fallidos. Bloqueado por $minutes minutos.";
        }

        return [
            'success' => false,
            'message' => $message,
            'user' => null
        ];
    }

    // Success - reset rate limit
    reset_rate_limit($identifier);

    // Update last login
    foreach ($users_data['users'] as &$u) {
        if ($u['id'] === $user['id']) {
            $u['last_login'] = get_timestamp();
            break;
        }
    }
    write_json($users_file, $users_data);

    // Log successful login
    log_admin_action('login', $user['username'], ['success' => true]);

    return [
        'success' => true,
        'message' => 'Autenticación exitosa.',
        'user' => $user
    ];
}

/**
 * Create admin session
 * @param array $user User data
 */
function create_admin_session($user) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * Destroy admin session
 */
function destroy_admin_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Log logout
    if (isset($_SESSION['username'])) {
        log_admin_action('logout', $_SESSION['username']);
    }

    // Destroy session
    $_SESSION = [];
    session_destroy();

    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

/**
 * Check session timeout
 * @param int $lifetime Session lifetime in seconds
 * @return bool Whether session is still valid
 */
function check_session_timeout($lifetime = 3600) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['login_time'])) {
        return false;
    }

    $elapsed = time() - $_SESSION['login_time'];

    if ($elapsed > $lifetime) {
        destroy_admin_session();
        return false;
    }

    // Refresh login time on activity
    $_SESSION['login_time'] = time();
    return true;
}

// hash_password() - Ahora definida en security.php (evitar duplicación)

/**
 * Create new admin user
 * @param string $username Username
 * @param string $password Password
 * @param string $email Email
 * @return array ['success' => bool, 'message' => string]
 */
function create_admin_user($username, $password, $email) {
    // Validar fortaleza de contraseña
    $password_validation = validate_password_strength($password);
    if (!$password_validation['valid']) {
        return [
            'success' => false,
            'message' => 'Contraseña no cumple con los requisitos de seguridad',
            'errors' => $password_validation['errors']
        ];
    }

    $users_file = get_users_file_path();
    $users_data = read_json($users_file);

    if (!isset($users_data['users'])) {
        $users_data = ['users' => []];
    }

    // Check if username exists
    foreach ($users_data['users'] as $user) {
        if ($user['username'] === $username) {
            return [
                'success' => false,
                'message' => 'El usuario ya existe.'
            ];
        }
    }

    // Create new user
    $new_user = [
        'id' => generate_id('admin-'),
        'username' => $username,
        'password' => hash_password($password),
        'email' => $email,
        'role' => 'admin',
        'created_at' => get_timestamp(),
        'last_login' => null
    ];

    $users_data['users'][] = $new_user;

    if (write_json($users_file, $users_data)) {
        log_admin_action('create_user', 'system', ['username' => $username]);

        return [
            'success' => true,
            'message' => 'Usuario creado exitosamente.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al crear el usuario.'
    ];
}

/**
 * Change admin password
 * @param string $user_id User ID
 * @param string $old_password Current password
 * @param string $new_password New password
 * @return array ['success' => bool, 'message' => string]
 */
function change_admin_password($user_id, $old_password, $new_password) {
    // Validar fortaleza de la nueva contraseña
    $password_validation = validate_password_strength($new_password);
    if (!$password_validation['valid']) {
        return [
            'success' => false,
            'message' => 'La nueva contraseña no cumple con los requisitos de seguridad',
            'errors' => $password_validation['errors']
        ];
    }

    $users_file = get_users_file_path();
    $users_data = read_json($users_file);

    if (!isset($users_data['users'])) {
        return [
            'success' => false,
            'message' => 'Error en el sistema.'
        ];
    }

    // Find user
    $user_index = null;
    foreach ($users_data['users'] as $index => $user) {
        if ($user['id'] === $user_id) {
            $user_index = $index;
            break;
        }
    }

    if ($user_index === null) {
        return [
            'success' => false,
            'message' => 'Usuario no encontrado.'
        ];
    }

    // Verify old password
    if (!password_verify($old_password, $users_data['users'][$user_index]['password'])) {
        return [
            'success' => false,
            'message' => 'Contraseña actual incorrecta.'
        ];
    }

    // Verificar que la nueva contraseña no sea igual a la actual
    if (password_verify($new_password, $users_data['users'][$user_index]['password'])) {
        return [
            'success' => false,
            'message' => 'La nueva contraseña debe ser diferente a la actual.'
        ];
    }

    // Update password
    $users_data['users'][$user_index]['password'] = hash_password($new_password);

    if (write_json($users_file, $users_data)) {
        log_admin_action('change_password', $users_data['users'][$user_index]['username']);

        return [
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al actualizar la contraseña.'
    ];
}

/**
 * Check if user is logged in as admin
 * @return bool Whether user is logged in as admin
 */
function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true &&
           isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
