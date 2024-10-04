<?php

use Slim\Psr7\Request;
use Slim\Psr7\Response;

require_once __DIR__ . "/../Controllers/usuarioController.php";

require_once __DIR__ . "/../Controllers/juegoController.php";

require_once __DIR__ . "/../Controllers/calificacionController.php";

    /* 
    Consultas:
    - Se listan 5 juegos por pagina, cada juego debe mostrar la puntuacion promedio. Se puede filtrar por nombre, plataforma y por clasificacion por edad

        valorant / PC / ATP / puntuacion promedio
    */

    $authMiddleware = function($request, $handler){
        $response = new Response(); 
        $authHeader = $request->getHeader('Authorization');
        if(!$authHeader){
            $response->getBody()->write(json_encode(['error'=>'token no proporcionado']));
            return $response->withStatus(401);
        }
        // Extraer el token en base64
        $tokenBase64 = str_replace('Bearer ', '', $authHeader[0]);
        // Decodificar token
        $tokenDecoded = base64_decode($tokenBase64);
        // verifico si la decodificacion fue exitosa
        if(!$tokenDecoded){
            $response->getBody()->write(json_encode(['error'=>'token invalido']));
            return $response->withStatus(401);
        }
        // Hasta aca va bien si hago un echo me imprime el token

        // echo $tokenDecoded;
        $token = json_decode($tokenDecoded);

        // aca si imprimo, no imprime nada imprime vacio por lo que la decodificacion falla
        if(!$token || !isset($token->id) || !isset($token->date)){
            $response->getBody()->write(json_encode(['error'=>'Formato del token invalido']));
            return $response->withStatus(401);
        }
        // verifico si el token expiro
        try{
            $tokenDate = new DateTime($token->date);
            $currentDate = new DateTime();
            $tokenDate->modify('+1 hour');
            // verifico si expiro
            if($currentDate > $tokenDate){
                $response->getBody()->write(json_encode(['error'=>'token expirado']));
                return $response->withStatus(401);
            }
            // si el token es valido, se agrega el ID del usuario al request para que este disponible en los endpoints
            $request = $request->withAttribute('es_admin', $token->admin);
            return $handler->handle($request);
        }
        catch(Exception $e){
            $response->getBody()->write(json_encode(['error'=>'Error al validar el token']));
            return $response->withStatus(500);
        }
    };

    $app->post('/register', function(Request $request, Response $response){

        $userController = new usuarioController();

        $datos_usuario = $request->getParsedBody();

        $nombre = $datos_usuario['nombre_usuario'];
        $clave = $datos_usuario['clave'];
        
        $respuesta = $userController-> register($nombre, $clave);
        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    $app->post('/login', function(Request $request, Response $response){

        $usuarioController = new usuarioController();

        $datos_usuario = $request->getParsedBody();

        $nombre = $datos_usuario['nombre_usuario'];
        $clave = $datos_usuario['clave'];

        $respuesta = $usuarioController->login($nombre, $clave);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    // Usuarios

    $app->get('/usuario/{id}',function(Request $request, Response $response){

        $usuarioController = new usuarioController();
        $user_id = $request -> getAttribute('id');

        $respuesta = $usuarioController->getUser($user_id);

        $response->getBody()->write(json_encode($respuesta['result']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    $app->post('/usuario', function(Request $request, Response $response){

        $usuarioController = new usuarioController();

        $admin = $request->getAttribute('es_admin');
        
        $datos_usuario = $request->getParsedBody();

        $nombre = $datos_usuario['nombre_usuario'];
        $clave = $datos_usuario['clave'];
        $admin = $datos_usuario['es_admin'];

        $respuesta = $usuarioController->createUser($nombre, $clave, $admin);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    })->add($authMiddleware);

    $app->put('/usuario/{id}', function(Request $request, Response $response){

        $usuarioController = new usuarioController();

        $user_id = $request -> getAttribute('id');
        $datos_usuario = $request->getParsedBody();
        $nombre = $datos_usuario['nombre_usuario'];
        $clave = $datos_usuario['clave'];
        $admin = $datos_usuario['es_admin'];

        $respuesta = $usuarioController->editUser($user_id, $nombre, $clave, $admin);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-type', 'application/json')->withStatus($respuesta['status']);

    });

    $app->delete('/usuario/{id}', function(Request $request, Response $response){

        $usuarioController = new usuarioController();

        $user_id = $request -> getAttribute('id');

        $respuesta = $usuarioController->deleteUser($user_id);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    // Juegos -----------------------------------------------------------------------------

    //Listar los juegos de la página según los parámetrosde búsqueda incluyendo la puntuación promedio del juego.
    $app->get('/juegos', function(Request $request, Response $response){
        // http request
        $juegoController = new juegoController();

        $datos = $request->getQueryParams();
        // foreach 
        $texto = null;
        $pagina = $datos['pagina'];
        $clasificacion = $datos['clasificacion'];
        if(isset($datos['texto'])){
            $texto = $datos['texto']; // nombre de juego que busca por ejemplo si es "LO" -> podrian aparecer en el listado valorant, etc
        }
        $plataforma = $datos['plataforma'];

        $respuesta = $juegoController->getPagina($pagina, $clasificacion, $texto, $plataforma);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });

    $app->get('/juegos/{id}',function(Request $request, Response $response){

        $juegoController = new juegoController();

        $user_id = $request -> getAttribute('id');

        $respuesta = $juegoController->getJuego($user_id);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    // Da de alta un nuevo juego. Solo lo puede hacer un usuario logueado y que sea administrador.
    $app->post('/juego', function(Request $request, Response $response){

        $juegoController = new juegoController();

        $datos_juego = $request->getParsedBody();

        $nombre = $datos_juego['nombre'];
        $descripcion = $datos_juego['descripcion'];
        $imagen = $datos_juego['imagen'];
        $clasificacion_edad = $datos_juego['clasificacion_edad'];

        $respuesta = $juegoController->agregarJuego($nombre, $descripcion, $imagen, $clasificacion_edad);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });

    // actualiza los datos de un juego existente. Solo lo puede hacer un usuario logueado y que sea administrador.
    $app->put('/juego/{id}', function(Request $request, Response $response){

        $juegoController = new juegoController();

        $juego_id = $request -> getAttribute('id');
        $datos_juego = $request->getParsedBody();

        $nombre = $datos_juego['nombre'];
        $descripcion = $datos_juego['descripcion'];
        $imagen = $datos_juego['imagen'];
        $clasificacion_edad = $datos_juego['clasificacion_edad'];

        $respuesta = $juegoController->editarJuego($juego_id, $nombre, $descripcion, $imagen, $clasificacion_edad);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);
    });

    //  borra el juego siempre y cuando no tenga calificaciones. Solo lo puede hacer un usuario logueado y que sea administrador.
    $app->delete('/juego/{id}', function(Request $request, Response $response){

        $juegoController = new juegoController();

        $user_id = $request -> getAttribute('id');

        $respuesta = $juegoController->eliminarJuego($user_id);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });

    // Calificaciones ---------------------------------------------------------------------

    //  Crear una nueva calificación. Solo lo puede hacer unusuario logueado.
    $app->post('/calificacion', function(Request $request, Response $response){

        $calificacionController = new calificacionController();

        $datos_calificacion = $request->getParsedBody();

        $estrellas = $datos_calificacion['estrellas'];
        $id_usuario = $datos_calificacion['usuario_id'];
        $id_juego = $datos_calificacion['juego_id'];

        $respuesta = $calificacionController->createCalification($estrellas, $id_usuario, $id_juego);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });

    // Editar una calificación existente. Solo lo puede hacer un usuario logueado.
    $app->put('/calificacion/{id}', function(Request $request, Response $response){

        $calificacionController = new calificacionController();
        
        $calificacion_id = $request -> getAttribute('id');
        $datos_calificacion = $request->getParsedBody(5);

        $estrellas = $datos_calificacion['estrellas'];
        $id_usuario = $datos_calificacion['usuario_id'];
        $id_juego = $datos_calificacion['juego_id'];
        
        // put a la base

        $respuesta = $calificacionController->editCalificacion($calificacion_id, $estrellas, $id_usuario, $id_juego);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });

    // Eliminar una calificación. Solo lo puede hacer un usuario logueado.
    $app->delete('/calificacion/{id}', function(Request $request, Response $response){

        $calificacionController = new calificacionController();
        
        $calificacion_id = $request -> getAttribute('id');

        // delete a la base

        $respuesta = $calificacionController->deleteCalification($calificacion_id);

        $response->getBody()->write(json_encode($respuesta['result']));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($respuesta['status']);

    });
?>