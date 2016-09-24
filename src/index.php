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

$app->get('/session/upload', function () use ($app) {

    $sql = "SELECT * FROM event ORDER BY name";
    $events = $app['db']->fetchAll($sql);

    return $app['twig']->render('session.twig', array(
        'events' => $events
    ));
});

$app->post('/session/check-import', function (Request $request) use ($app) {
    $debug = 1;

    $csvArray                      = parseCSVFromRequest($request);
    $processedResults              = processCSVContent($csvArray);
    $processedResults['sessionId'] = $request->request->get('event');
    $processedResults['infos']     = $request->request->get('infos');

    createSession($processedResults, $app);

    $debug = 1;

    return $app['twig']->render('session-check.twig', array(
        'results' => $processedResults
    ));
});

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
        'event_id' => $sessionData['sessionId'],
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
