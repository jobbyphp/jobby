<?php

require(dirname(__DIR__) . '/vendor/autoload.php');

spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require(dirname(__DIR__) . "/src/{$class}.php");
});
