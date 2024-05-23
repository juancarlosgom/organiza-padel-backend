<?php

namespace App\Http\Controllers;

use App\Models\Partida;
use App\Models\Pista;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class ApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function register(Request $request){
        $datosRecibidos = $request->input();
        //Validaciones
        $rules = [
            'nombre' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required',
        ];
        $validator = Validator::make($request->input(),$rules);
        if($validator->fails()){
            return reponse()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ],400);
        }
        $usuairo = User::create([
            'name' => $datosRecibidos['nombre'],
            'email' => $datosRecibidos['email'],
            'password' => Hash::make($datosRecibidos['password'])
        ]);
        User::createUserPlayers($datosRecibidos,$usuairo);
        return response()->json([
            'status' => true,
            'message' => 'Usuario creado',
            'usuario' => $usuairo->id,
            //'token' => $usuairo->createToken('API TOKEN')->plainTextToken
        ],200);
    }

    public function login(Request $request){
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];
        $validator = Validator::make($request->input(),$rules);
        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ],400);
        }
        if(!Auth::attempt($request->only('email','password'))){
            return response()->json([
                'status' => false,
                'message' => 'No autorizado',
            ],401);
        }
        $usuario = User::where('email',$request->email)->first();
        return response()->json([
            'status' => true,
            'message' => 'Usuario logueado correctamente',
            'date' => $usuario,
            'token' => $usuario->createToken('API TOKEN')->plainTextToken,
        ],200);
    }

    public function logout(Request $request){
        //auth()->user()->tokens()->delete();
        $token = $request->getContent();
        User::removeTokensUser($token);
        return response()->json([
            'status' => true,
            'message' => 'Cierre de sesión correcto',
        ],200);
    }

    public function getDataUser(Request $request){
        $token = $request->getContent();
        $usuario = User::getUser($token);
        return response()->json([
            'status' => true,
            'usuario' => $usuario,
        ]);
    }

    public function reservarPista(Request $request){
        $datos = $request->all();
        $token = $request->bearerToken();
        $usuario = User::getUser($token);
        $horaInicio = $datos[0];
        $horaFin = $datos[1];
        $idPista = $datos[2];
        $datosFecha = explode('T',$datos[3]);
        $fecha = $datosFecha[0];
        $searchGame = $datos[4];
        $gender = $datos[5];
        if(Pista::reservarPista($idPista,$horaInicio,$horaFin,$fecha,$usuario,$searchGame,$gender)){
            return response()->json([
                'status' => true,
                'message' => 'Reserva realizada con éxito',
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No se ha podido hacer la reserva',
        ]);
    }

    public function getDate(Request $request){
        $datos = $request->all();
        $diasASumar = $datos[0];
        $userTimeZone = 'Europe/Madrid';
        $date = new DateTime('now', new \DateTimeZone($userTimeZone));
        //Sumar o restar dias
        $date->modify("$diasASumar days");
        $date->setTimezone(new \DateTimeZone('UTC'));
        $formatDate = $date->format('Y-m-d');
        $tracksReserve = Pista::getTracksReserveDay($formatDate);
        return response()->json([
            'status' => true,
            'datos' => $formatDate,
            'diasSumados' => $diasASumar,
            'pistasReservadas' => $tracksReserve,
        ]);
    }
    public function getFecha(Request $request){
        $datos = $request->all();
        $fecha = $datos[0];
        $tracksReserve = Pista::getTracksReserveDay($fecha);
        return response()->json([
            'status' => true,
            'fecha' => $fecha,
            'pistasReservadas' => $tracksReserve,
        ]);
    }

    public function getDataUserAll(Request $request){
        $token = $request->bearerToken();
        //Tabal users
        $usuario = User::getUser($token);
        //Tabla jugadores
        $usuarioAll = User::getUserAll($usuario->id);
        return response()->json([
            'status' => true,
            'usuarioAll' => $usuarioAll,
            'usuario' => $usuario,
        ]);
    }

    public function getGameOpen(Request $request){
        $token = $request->bearerToken();
        $gamesOpen = Pista::getGamesOpen($token);
        $dateToday = Partida::getDateToday();
        $dateOnly = Carbon::parse(Partida::getOnlyDate($dateToday));
        $hourOnly = Carbon::parse(Partida::getOnlyHour($dateToday));
        $index = 0;
        foreach ($gamesOpen as $game){
            $dateGame = Carbon::parse($game->fecha);
            $hourGame = Carbon::parse($game->horaInicio);
            if(($dateOnly->gt($dateGame)) || (($dateOnly->eq($dateGame)) && ($hourOnly->gt($hourGame)))){
                unset($gamesOpen[$index]);
            }
            $index++;
        }
        //Para devolver los datos en forma de array
        $gamesOpenArray = $gamesOpen->values()->all();
        return response()->json([
            'status' => true,
            'gamesOpen' => $gamesOpenArray,
        ]);
    }

    public function signUpGame(Request $request){
        $datos = $request->all();
        $idPartida = $datos[0];
        $numberPlayer = $datos[1];
        $token = $request->bearerToken();
        //Tabla users
        $usuario = User::getUser($token);
        //Tabla jugadores
        $usuarioAll = User::getUserAll($usuario->id);
        //Obtengo los de la partida
        $game = Partida::getOpenGame($idPartida);
        //TODO - Comprobar si el usuario cumple requisitos para apuntarse a la partida
        if(Partida::checkPlayerCanSingUp($game,$usuarioAll)){
            return response()->json([
                'status' => false,
                'message' => 'Error no pertenece a la categoría o género',
            ]);
        }

        if(Partida::singUpGamePlayer($usuarioAll,$numberPlayer,$idPartida)){
            return response()->json([
                'status' => true,
                'message' => 'Jugador incristo correctamente',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Error al inscribirte en la partida',
        ]);
    }

    public function checkOpenGames(){
        $dateToday = Partida::getDateToday();
       $openGamesToDelete = Partida::checkOpenGames($dateToday);
       Partida::deleteOpenGamesNotClosed($openGamesToDelete);
        return response()->json([
            'status' => true,
            'games' => $openGamesToDelete,
        ]);
    }

    public function getGamesUser(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $gamesUser = User::getGamesUser($user->id);
        return response()->json([
            'status' => true,
            'datos' => $gamesUser,
        ]);
    }

    public function deleteReserve(Request $request){
        $idReserve = $request->all();
        Pista::deleteReserveTrack($idReserve);
        return response()->json([
            'status' => true,
            'message' => 'Reserva eliminada',
        ]);
    }

    public function deleteUserOpenGame(Request $request){
        $datos = $request->all();
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $idGame = $datos[0];
        $player = $datos[1];
        Partida::deleteUserOpenGame($idGame,$user->id,$player);
        return response()->json([
            'status' => true,
            'message' => 'Player borrado de la partida',
            'player' => $player,
            'usuario' => $user->id,
            'partida' => $idGame,
        ]);
    }

    public function getIdUser(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        return response()->json([
            'status' => true,
            'id' => $user->id,
        ]);
    }

    public function getAdminGame(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $games = User::getAdminUserGame($user);
        return response()->json([
            'status' => true,
            'games' => $games,
        ]);
    }

    public function getDataGame(Request $request){
        $idGame = $request->getContent();
        $game = Partida::getGame($idGame);
        return response()->json([
            'status' => true,
            'game' => $game,
        ]);
    }

    public function addResultGame(Request $request){
        $data = $request->all();
        $token = $request->bearerToken();
        $user = User::getUser($token);
        Partida::addResultGame($data[0],$user->id,$data[2],$data[1]);
        // Borro de la BD la reserva y la partida.
        Partida::deleteGameAndReserve($data[1]);
        return response()->json([
            'status' => true,
            'message' => 'Resultado añadido correctamente',
        ]);
    }

    public function getConfirmGame(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $games = Partida::getConfirmGames($user->id);
        $verifiedGames = Partida::checkNotConfirm($games,$user->id);
        return response()->json([
            'status' => true,
            'message' => 'Confirmación obtenida correctamente',
            'games' => $verifiedGames,
        ]);
    }

    public function addConfirmGame(Request $request){
        $data = $request->all();
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $idResultado = $data[0];
        $playerConfirm = $data[1];
        Partida::updateConfirmUserResult($idResultado,$playerConfirm,$user->id);
        return response()->json([
            'status' => true,
            'message' => 'Confirmación añadida correctamente',
            'datos' => $data[1],
        ]);
    }

    public function getHistoryUser(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $history = User::getHistoryUser($user->id);
        return response()->json([
            'status' => true,
            'history' => $history,
        ]);
    }

    public function getStatisticsUser(Request $request){
        $token = $request->bearerToken();
        $user = User::getUser($token);
        $statistics = User::getStatisticsUser($user->id);
        return response()->json([
            'status' => true,
            'stastistics' => $statistics,
        ]);
    }

    public function updateDataUser(Request $request){
        $datos = $request->all();
        $token = $request->bearerToken();
        $user = User::getUser($token);
        User::updateDateUser($datos,$user->id);
        return response()->json([
            'status' => true,
            'datos' => $datos,
        ]);
    }

}
