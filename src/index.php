<?php

require_once __DIR__.'/vendor/autoload.php';

use \Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
    'debug' => true,
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'dbs.options' => array (
        'mysql_read' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'localhost',
            'dbname'    => 'ipa',
            'user'      => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
        )
    ),
));

/**
 * Routing
 */
$app->get('/', function () {
    return 'Hello!';
});

$app->get('/event/add', function () use ($app) {
    return $app['twig']->render('event.twig', array(
        'action' => 'add',
    ));
});
$app->post('/event/add', function (Request $request) use ($app) {
    $name = $request->get('name');

    $app['db']->insert('event', array('name' => $name));

    return $app['twig']->render('event.twig', array(
        'action' => 'add',
        'success' => 'true',
        'name' => $name
    ));
});

$app->get('/session/import', function () use ($app) {
    return $app['twig']->render('session.twig', array());
});

$app->run();
