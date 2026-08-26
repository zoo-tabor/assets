<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mini-router: deklarativni tabulka rout s {param} placeholdery,
 * cesta se cte z REQUEST_URI (pretty URL, zadne ?route=).
 */
final class Router
{
    /** @var array<array{methods:string[],pattern:string,regex:string,handler:array{0:class-string,1:string}}> */
    private array $routes = [];

    /**
     * @param string $methods napr. 'GET' nebo 'GET|POST'
     * @param array{0:class-string,1:string} $handler [Controller::class, 'metoda']
     */
    public function add(string $methods, string $pattern, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [
            'methods' => explode('|', strtoupper($methods)),
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->{$action}(...array_values(array_map('urldecode', $params)));
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Stránka nenalezena']);
    }
}
