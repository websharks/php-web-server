<?php
namespace WebSharks\PhpWebServer;

use WebSharks\PhpWebServer\Classes\Router;

require_once __DIR__.'/stub.php';

if (is_string(Router::route())) {
    require Router::$response;
}
return (bool) Router::$response;
