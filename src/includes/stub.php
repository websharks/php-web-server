<?php
namespace WebSharks\PhpWebServer;

if (PHP_SAPI !== 'cli-server') {
    exit('Not the built-in CLI server.');
}
require_once dirname(__FILE__, 2).'/vendor/autoload.php';
