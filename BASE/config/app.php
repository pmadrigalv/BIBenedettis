<?php

$appBasePath = getenv('APP_BASE_PATH');

if ($appBasePath === false) {
    $appBasePath = '';
}

$appBasePath = trim((string)$appBasePath);

if ($appBasePath === '' || $appBasePath === '/') {
    $appBasePath = '';
} else {
    $appBasePath = '/' . trim($appBasePath, '/');
}

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', $appBasePath);
}

if (!function_exists('appUrl')) {
    function appUrl($path = '')
    {
        $normalizedPath = '/' . ltrim((string)$path, '/');

        if ($normalizedPath === '/') {
            return APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/';
        }

        return APP_BASE_PATH . $normalizedPath;
    }
}