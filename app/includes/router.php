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

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            return $this->loadPage($this->routes[$method][$uri]);
        }

        // Try pattern match
        foreach ($this->routes[$method] ?? [] as $route => $file) {
            if ($this->matchRoute($route, $uri)) {
                return $this->loadPage($file);
            }
        }

        // 404
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

        if (!file_exists($fullPath)) {
            http_response_code(404);
            die('Page not found');
        }

        // Store matched route for theme system
        $this->matchedRoute = $file;

        // Make router params available
        extract($this->params);

        require $fullPath;
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
