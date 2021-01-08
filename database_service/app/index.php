<?php
include_once ("Models/GenericModels.php");
include_once ("Models/iRestObject.php");
include_once ("Models/Mongo/Users.php");
include_once ("Models/Mongo/Cinemas.php");
include_once ("Models/Mongo/Movies.php");
include_once ("Models/Mongo/db_mongo.php");
include_once ("Utils/Logs.php");

use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Models\Mongo\UserM as User;
use Models\Mongo\CinemaM as Cinema;
use Models\Mongo\MovieM as Movie;
use RestAPI\Result;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

/* =====================
 *  -- API endpoints --
 * ===================== */


/* =====================
 *        USERS
 * ===================== */


// POST /login -- DEPRECATED
// Login a user based on given POST parameters
//$app->post('/login', function (Request $request, Response $response, $args) {
//
//    logger("\n --- At [POST] /login");
//    $params = (array)$request->getParsedBody();
//    $result = User::Login($params['username'], $params['password']);
//
//    if ($result instanceof Result)
//    {
//        logger("Couldn't login user");
//        $response->getBody()->write($result->msg);
//        return $response->withStatus(401);
//    }
//    else
//        $response->getBody()->write(json_encode($result));
//
//    return $response;
//});

// GET /users
// - Retrieve all users' info
$app->get('/users', function (Request $request, Response $response, $args) {

    logger("\n --- At [GET] /users");
    $users = User::getAll();

    $response->getBody()->write(json_encode($users));
    return $response;
});


// POST /users
// - Add user to database
$app->post('/users', function (Request $request, Response $response, $args) {

    logger("\n --- At [POST] /users - (Add User)");
    // Get all POST parameters
    $params = (array)$request->getParsedBody();

    $result = User::addOne(new User($params));
    if ($result->success)
        return $response->withStatus(201);
    else
        $response->getBody()->write($result->msg);
        return $response->withStatus(400);
});

// GET /users/{k_id}
// - Retrieve SINGLE user's info
$app->get('/users/{k_id}', function (Request $request, Response $response, $args) {

    logger("\n --- At [GET] /users/{$args['k_id']}");
    // Get all POST parameters
    $user = User::getOne($args['k_id']);

    if (!empty($user))
        logger("User found!");

    $response->getBody()->write(json_encode($user));
    return $response;
});

// GET /users/search/{username}
// - Search for user with given username (usernames are unique)
$app->get('/users/search/{username}', function (Request $request, Response $response, $args) {

    logger("\n --- At [GET] /users/search/{$args['username']} (Search User)");
    $user = User::searchByUsername($args['username']);

    $response->getBody()->write($user->username . "\n");
    $response->getBody()->write($user->role . "\n");
    return $response;
});

// PUT /users/{k_id}
// - Edit user
$app->put('/users/{k_id}', function (Request $request, Response $response, $args) {

    logger("\n --- At [PUT] /users/{k_id} - (Edit User)");
    // Get all parameters
    $params = (array)$request->getParsedBody();
    $res = User::updateOne($args['k_id'], new User($params));

    if ($res->success == false)
        return $response->withStatus(401);

    return $response->withStatus(204);
});

// DELETE /users/{k_id}
// - Delete user
$app->delete('/users/{k_id}', function (Request $request, Response $response, $args) {

    logger("\n --- At [DELETE] /users/{k_id} - (Delete User)");
    $res = User::deleteOne($args['k_id']);

    if ($res->success == false)
        return $response->withStatus(401);

    return $response->withStatus(204);
});


/* =====================
 *        CINEMAS
 * ===================== */


// [ - UNUSED - ]
// GET /cinemas
// - Retrieve all cinemas
//$app->get('/cinemas', function (Request $request, Response $response, $args) {
//
//    logger("\n --- At [GET] /cinemas");
//    $users = Cinemas::getAll();
//
//    $response->getBody()->write(json_encode($users));
//    return $response;
//});

// GET /users/{k_id}/cinemas
// - Retrieve all users cinemas
$app->get('/users/{k_id}/cinemas', function (Request $request, Response $response, $args) {

    $k_id = $args['k_id'];
    logger("\n --- At [GET] /users/".$k_id."/cinemas");
    $cinemas = Cinema::getAllOwned($k_id);

    $response->getBody()->write(json_encode($cinemas));
    return $response;
});


// POST /users/{k_id}/cinemas
// - Add cinema to user
$app->post('/users/{k_id}/cinemas', function (Request $request, Response $response, $args) {

    $k_id = $args['k_id'];
    logger("\n --- At [POST] /users/".$k_id."/cinemas");

    // Get all POST parameters
    $params = (array)$request->getParsedBody();

    $result = Cinema::addOne(new Cinema($params, $k_id));
    if ($result->success)
    {
        return $response->withStatus(201);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

// PUT /users/{k_id}/cinemas/{c_id}
// - Edit Cinema
$app->put('/users/{k_id}/cinemas/{c_id}', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id']; // User ID
    $c_id = $args['c_id']; // Cinema ID
    logger("\n --- At [PUT] /users/{id}/cinemas/{c_id} - (Edit Cinema)");

    // Get all parameters
    $params = (array)$request->getParsedBody();
    $res = Cinema::updateOne($c_id, new Cinema($params, $user_k_id));

    if ($res->success)
        return $response->withStatus(204);

    return $response->withStatus(401);
});

// DELETE /users/{k_id}/cinemas/{c_id}
// - Delete Cinema
$app->delete('/users/{k_id}/cinemas/{c_id}', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id']; // User ID
    $c_id = $args['c_id']; // Cinema ID
    logger("\n --- At [DELETE] /users/{id}/cinemas/{c_id} - (Delete Cinema)");
    $res = Cinema::deleteOne($c_id);

    if ($res->success == false)
        return $response->withStatus(401);
    else
        return $response->withStatus(204);
});



/* =====================
 *        MOVIES
 * ===================== */


// POST /users/{k_id}/cinemas/{c_uid}/movies
// - Add movie to cinema
$app->post('/users/{k_id}/cinemas/{cinema_name}/movies', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    $cinema_name = $args['cinema_name'];
    logger("\n --- At [POST] /users/".$user_k_id."/cinemas/".$cinema_name."/movies");

    // Get all POST parameters
    $params = (array)$request->getParsedBody();
    $result = Movie::addOne(new Movie($params));

    if ($result->success)
    {
        return $response->withStatus(201);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

// GET /users/{k_id}/movies/owned
// - Retrieve all users movies
$app->get('/users/{k_id}/movies/owned', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    logger("\n --- At [GET] /users/".$user_k_id."/movies/owned");
    $movies = Movie::getAllOwned($user_k_id);

    $response->getBody()->write(json_encode($movies));
    return $response;
});

// GET /users/{k_id}/movies/all
// - Retrieve all users movies
$app->get('/users/{k_id}/movies/all', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    logger("\n --- At [GET] /users/".$user_k_id."/movies/all");
    $movies = Movie::getAll($user_k_id);

    $response->getBody()->write(json_encode($movies));
    return $response;
});

// POST /users/{k_id}/movies/search/{search_term}
// - Search Movies based on search term
$app->post('/users/{k_id}/movies/search', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];

    // Get all POST parameters
    $params = (array)$request->getParsedBody();

    logger("\n --- At [GET] /users/".$user_k_id."/movies/search");
    $movies = Movie::getAll($user_k_id, $params);
    $response->getBody()->write(json_encode($movies));
    return $response;
});




// PUT /users/{k_id}/cinemas/{c_uid}/movies
// - Edit movie
$app->put('/users/{k_id}/movies/{m_id}', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    $m_id = $args['m_id'];
    logger("\n --- At [PUT] /users/".$user_k_id."/movies/".$m_id);

    // Get all POST parameters
    $params = (array)$request->getParsedBody();
    $result = Movie::updateOne($m_id, new Movie($params));

    if ($result->success)
    {
        return $response->withStatus(204);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

// Delete /users/{k_id}/movies/{m_id}
// - Delete a movie
$app->delete('/users/{k_id}/movies/{m_id}', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    $m_id = $args['m_id'];
    logger("\n --- At [DELETE] /users/".$user_k_id."/movies/".$m_id);

    $result = Movie::deleteOne($m_id);

    if ($result->success)
    {
        return $response->withStatus(204);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

/* =====================
 *        FAVORITES
 * ===================== */

// Add /users/{k_id}/favorites
// - Add a favorite movie to user
$app->post('/users/{k_id}/favorites', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];

    // Get all POST parameters
    $params = (array)$request->getParsedBody();
    $m_id = $params["movie_id"];
    logger("\n --- At [POST] /users/".$user_k_id."/favorites");

    $result = User::addFavorite($user_k_id, $m_id);

    if ($result->success)
    {
        return $response->withStatus(201);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

// Delete /users/{k_id}/favorites/{m_id}
// - Delete a favorite movie from user
$app->delete('/users/{k_id}/favorites/{m_id}', function (Request $request, Response $response, $args) {

    $user_k_id = $args['k_id'];
    $m_id = $args['m_id'];
    logger("\n --- At [DELETE] /users/".$user_k_id."/favorites/".$m_id);

    $result = User::removeFavorite($user_k_id, $m_id);

    if ($result->success)
    {
        return $response->withStatus(204);
    }
    else
    {
        $response->getBody()->write($result->msg);
        return $response->withStatus(403);
    }

});

/* =====================
 *        LOGS/OTHER
 * ===================== */

// GET /logs
$app->get('/logs', function (Request $request, Response $response, $args) {
    $response->getBody()->write(getLogs());
    return $response;
});








/* ----- Error Handling ----- */

$customErrorHandler = function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app) {

    $payload = ['error' => $exception->getMessage()];

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );

    return $response;
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
//$errorMiddleware->setErrorHandler(\Slim\Exception\HttpNotFoundException::class, $customErrorHandler,false);



$app->run();

