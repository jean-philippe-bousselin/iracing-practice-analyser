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
$app->get('/', function () use ($app){
    return $app['twig']->render('home.twig', array(
        'events' => getEvents($app),
    ));
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
$app->get('/event/{id}', function ($id) use ($app){

    $sql = "SELECT * FROM event WHERE id = ?";
    $event = $app['db']->fetchAssoc($sql, array($id));

    if(!$event) {
        return $app->redirect('/');
    }

    $sessions = getSessions($id, $app);

    foreach($sessions as $key => $session) {

        $results = getResults($session['id'], $app);
        $sessions[$key]['driversResults'] = $results;
    }

    return $app['twig']->render('event-detail.twig', array(
        'sessions' => $sessions,
        'event' => $event,
    ));
});

$app->get('/session/upload', function () use ($app) {

    return $app['twig']->render('session.twig', array(
        'events' => getEvents($app)
    ));
});

$app->post('/session/import', function (Request $request) use ($app) {
    $csvArray                      = parseCSVFromRequest($request);
    $processedResults              = processCSVContent($csvArray);
    $processedResults['eventId']   = $request->request->get('event');
    $processedResults['infos']     = $request->request->get('infos');

    createSession($processedResults, $app);

    return $app->redirect('/index.php/event/' . $request->request->get('event'));
});

function getEvents($app) {
    $sql = "SELECT * FROM event ORDER BY name";
    return $app['db']->fetchAll($sql);
}
function getSessions($eventId, $app) {
    $sql = "SELECT * FROM `session` WHERE event_id = ?";
    return $app['db']->fetchAll($sql, array($eventId));
}
function getResults($sessionId, $app) {
    $sql = "SELECT * FROM result WHERE session_id = ?";
    return $app['db']->fetchAll($sql, array($sessionId));
}
function parseCSVFromRequest($request) {
    return array_map('str_getcsv', file($request->files->get('file')));
}
function processCSVContent($csvArray) {
    $array = array();
    $array['startTime']      = $csvArray[1][0];
    $array['sessionName']    = $csvArray[1][3];
    $array['driversResults'] = array();

    for($i = 4; $i < count($csvArray); $i++) {
        $driver = array();
        $driver['name']        = $csvArray[$i][7];
        $driver['car']         = $csvArray[$i][2];
        $driver['averageTime'] = $csvArray[$i][15];
        $driver['fastestTime'] = $csvArray[$i][16];
        $driver['incidents']   = $csvArray[$i][19];
        $driver['totalLaps']   = $csvArray[$i][18];

        array_push($array['driversResults'], $driver);
    }

    return $array;
}
function createSession($sessionData, $app) {
    $debug = 1;
    // Add session
    $app['db']->insert('session', array(
        'name'     => $sessionData['sessionName'],
        'datetime' => $sessionData['startTime'],
        'infos'    => $sessionData['infos'],
        'event_id' => $sessionData['eventId'],
    ));

    $sessionId = $app['db']->lastInsertId();

    // Add driver results
    foreach($sessionData['driversResults'] as $result) {
        $app['db']->insert('result', array(
            'driver_name' => $result['name'],
            'fastest_lap' => $result['fastestTime'],
            'average_lap' => $result['averageTime'],
            'lap_count'   => $result['totalLaps'],
            'session_id'  => $sessionId,
            'car'         => $result['car'],
            'incidents'   => $result['incidents'],
        ));
    }
}

$app->run();
