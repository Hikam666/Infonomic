<?php
declare(strict_types=1);

session_start();

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/app/config/config.php';
require_once BASE_PATH . '/app/config/constants.php';

// Core
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/core/Router.php';
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Auth.php';

require_once BASE_PATH . '/app/middleware/Middleware.php';

$csrfPath = BASE_PATH . '/app/core/CSRF.php';
if (file_exists($csrfPath)) {
    require_once $csrfPath;
}

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/app/models/' . $class . '.php',
        BASE_PATH . '/app/controllers/' . $class . '.php',
        BASE_PATH . '/app/controllers/Admin/' . $class . '.php',
        BASE_PATH . '/app/controllers/Auth/' . $class . '.php',
        BASE_PATH . '/app/controllers/Common/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

$router = new Router();

$router->get('/admin/login', 'AuthController@loginForm');
$router->post('/admin/login', 'AuthController@login');
$router->post('/admin/logout', 'AuthController@logout');

$router->get('/admin', 'DashboardController@index');
$router->get('/admin/dashboard', 'DashboardController@index');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
