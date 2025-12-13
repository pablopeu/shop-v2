<?php
/**
 * Simple Router
 * Maneja el routing de la aplicación
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

class Router {
    private $routes = [];
    private $params = [];
    private $matchedRoute = null;

    public function get($route, $file) {
        $this->routes['GET'][$route] = $file;
    }

    public function post($route, $file) {
        $this->routes['POST'][$route] = $file;
    }

    public function dispatch($uri) {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // DEBUG logging
        error_log("ROUTER - Method: $method, URI after parse: $uri");
        error_log("ROUTER - Available routes: " . print_r(array_keys($this->routes[$method] ?? []), true));

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            error_log("ROUTER - Exact match found for $uri -> " . $this->routes[$method][$uri]);
            return $this->loadPage($this->routes[$method][$uri]);
        }

        // Try pattern match
        foreach ($this->routes[$method] ?? [] as $route => $file) {
            if ($this->matchRoute($route, $uri)) {
                error_log("ROUTER - Pattern match found for $uri -> $file");
                return $this->loadPage($file);
            }
        }

        // 404
        error_log("ROUTER - No match found for $uri, returning 404");
        http_response_code(404);
        $this->loadPage('pages/error.php');
    }

    private function matchRoute($route, $uri) {
        $pattern = preg_replace('/:[^\/]+/', '([^/]+)', $route);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);

            // Extract parameter names
            preg_match_all('/:([^\/]+)/', $route, $paramNames);
            $paramNames = $paramNames[1];

            // Map parameters
            foreach ($paramNames as $i => $name) {
                $this->params[$name] = $matches[$i] ?? null;
            }

            return true;
        }

        return false;
    }

    private function loadPage($file) {
        $fullPath = APP_PATH . '/' . $file;

        error_log("ROUTER - loadPage called with file: $file");
        error_log("ROUTER - Full path: $fullPath");
        error_log("ROUTER - File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));

        if (!file_exists($fullPath)) {
            error_log("ROUTER - ERROR: File not found at $fullPath");
            http_response_code(404);
            die('Page not found');
        }

        error_log("ROUTER - About to require file: $fullPath");

        // Store matched route for theme system
        $this->matchedRoute = $file;

        // Make router params available
        extract($this->params);

        require $fullPath;

        error_log("ROUTER - File required successfully");
    }

    public function getParam($name) {
        return $this->params[$name] ?? null;
    }

    public function getMatchedRoute() {
        return $this->matchedRoute;
    }
}

class AdminRouter extends Router {
    // Admin-specific routing if needed
}
