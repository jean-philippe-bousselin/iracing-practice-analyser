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

$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());

/**
 * Routing
 */
$app->get('/', function () use ($app){
    return $app['twig']->render('home.twig', array(
        'events' => getEvents($app),
        'runAsIframe' => false
    ));
})->bind('home');

$app->get('/event/add', function () use ($app) {
    return $app['twig']->render('event.twig', array(
        'action' => 'add',
        'runAsIframe' => false
    ));
})->bind('event-form');
$app->post('/event/add', function (Request $request) use ($app) {
    $name = $request->get('name');

    $app['db']->insert('event', array('name' => $name));

    return $app['twig']->render('event.twig', array(
        'action' => 'add',
        'success' => 'true',
        'name' => $name,
        'runAsIframe' => false
    ));
})->bind('event-create');

$app->get('/event/{id}/standings/{isIframe}', function ($id, $isIframe) use ($app){

    $sql = "SELECT * FROM event WHERE id = ?";
    $event = $app['db']->fetchAssoc($sql, array($id));

    if(!$event) {
        return $app->redirect('/');
    }

    $sql = 'select driver_name, 
                   MIN(fastest_lap) as mlap, 
                   SUM(lap_count) as tlaps  
            from result r
            join `session` s
            on s.id = r.session_id
            where s.event_id = ?
            group by driver_name 
            order by mlap;';

    $standings = $app['db']->fetchAll($sql, array($id));

    return $app['twig']->render('event-detail-standings.twig', array(
        'event'     => $event,
        'page'      => 'standings',
        'standings' => $standings,
        'runAsIframe' => $isIframe
    ));
})->value('isIframe', 'false')
    ->bind('event-standings');
$app->get('/event/{id}/evolution', function ($id) use ($app){

    $sql = "SELECT * FROM event WHERE id = ?";
    $event = $app['db']->fetchAssoc($sql, array($id));

    if(!$event) {
        return $app->redirect('/');
    }

    $sql = 'select driver_name, 
                    group_concat(CONCAT_WS(\'-\', session_id, fastest_lap)) as agg_flaps  
            from result r
            join `session` s
            on s.id = r.session_id
            where s.event_id = ?
            and fastest_lap > 0
            group by driver_name;';

    $results = $app['db']->fetchAll($sql, array($id));

    $array = array();
    foreach($results as $k => $result) {
        $array[$k] = array(
            'name' => $result['driver_name'],
            'data' => array()
        );
        $explodedTimes = explode(',', $result['agg_flaps']);
        foreach($explodedTimes as $v => $time) {
            $explodedTime = explode('-', $time);
            $array[$k]['data'][$explodedTime[0]] = lapTimeToSeconds($explodedTime[1]);
        }
    }

    return $app['twig']->render('event-detail-evolution.twig', array(
        'event'   => $event,
        'page'    => 'evolution',
        'results' => $array
    ));
})->bind('event-evolution');

$app->get('/event/{id}/{isIframe}', function ($id, $isIframe) use ($app){

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

    return $app['twig']->render('event-detail-sessions.twig', array(
        'sessions'    => $sessions,
        'event'       => $event,
        'page'        => 'sessions',
        'runAsIframe' => $isIframe
    ));
})->value('isIframe', 'false')
    ->bind('event-sessions');

$app->get('/session/upload', function () use ($app) {

    return $app['twig']->render('session.twig', array(
        'events' => getEvents($app),
        'runAsIframe' => false
    ));
})->bind('session-upload');

$app->post('/session/import', function (Request $request) use ($app) {
    $csvArray                      = parseCSVFromRequest($request);
    $processedResults              = processCSVContent($csvArray);
    $processedResults['eventId']   = $request->request->get('event');
    $processedResults['infos']     = $request->request->get('infos');

    createSession($processedResults, $app);

    return $app->redirect($app["url_generator"]->generate("event-sessions", array('id' => $request->request->get('event'))));


})->bind('session-import');
$app->get('/session/delete/{id}', function ($id) use ($app) {

    $sql = "SELECT event_id FROM session WHERE id = ?";
    $event = $app['db']->fetchAssoc($sql, array($id));

    $app['db']->delete('result', array('session_id'=> $id));
    $app['db']->delete('session', array('id'=> $id));
    return $app->redirect($app["url_generator"]->generate("event-sessions", array('id' => $event['event_id'])));
})->bind('session-delete');


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

    // get the stating line for lap times
    $startingLine = 4;
    foreach($csvArray as $key => $line) {
        if($line[0] == 'Fin Pos') {
            $startingLine = $key + 1;
        }
    }

    for($i = $startingLine; $i < count($csvArray); $i++) {
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
function lapTimeToSeconds($lapTime) {
  $exploded = explode(':', $lapTime);
  return (float) (((float) ($exploded[0] * 60)) + (float) $exploded[1]);
}
function createSession($sessionData, $app) {
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
