<?php

/*
 * Vercel entry point.
 *
 * The PHP runtime points DOCUMENT_ROOT at the project root, but Laravel and
 * every static page live under public/. Re-root it before the framework boots.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__).'/public';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__).'/public/index.php';
