<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    static function createUserPlayers($datos,$datosUser){
        $puntosRanking = self::getPointsRanking($datos['categoria']);
        DB::table('jugadores')->insert([
            'idJugador' => $datosUser->id,
            'dni' => $datos['dni'],
            'apellidos' => $datos['apellidos'],
            'telefono' => $datos['telefono'],
            'posicionPista' => $datos['posicionPista'],
            'ranking' => $puntosRanking,
            'tallaCamiseta' => $datos['tallaCamiseta'],
            'categoria' => $datos['categoria'],
            'genero' => $datos['genero'],
        ]);
    }

    static function getPointsRanking($categoria){
        switch ($categoria){
            case '1': $points = 1000;
                break;
            case '2': $points = 800;
                break;
            case '3': $points = 600;
                break;
            case '4': $points = 400;
                break;
            case '5': $points = 200;
                break;
            default: $points = 0;
                break;
        }

        return $points;
    }
    static function removeTokensUser($token){
        $idToken = User::getIdToken($token);
        $idUsuario = User::getIdUsuario($idToken);
        DB::table('personal_access_tokens')
            ->where('tokenable_id','=',$idUsuario->tokenable_id)
            ->delete();
    }
    static function getUser($token){
        $idToken = self::getIdToken($token);
        $idUsuario = self::getIdUsuario($idToken);
        $usuario = DB::table('users')
            ->where('id','=',$idUsuario->tokenable_id)
            ->first();
        return $usuario;
    }

    static function getIdToken($token){
        return explode('|',$token);
    }
    static function getIdUsuario($idToken){
        return DB::table('personal_access_tokens')
            ->where('id','=',$idToken[0])
            ->first();
    }

    static function getUserAll($idUser){
        $userAll = DB::table('jugadores')
            ->where('idJugador','=',$idUser)
            ->first();
        return $userAll;
    }

    static function getGamesUser($idUser){
        $gamesUser = array();
       $openGames = self::getOpenGamesUser($idUser);
       $reserveGames = self::getReserveGamesUser($idUser);
       $dateToday = Partida::getDateToday();
       $dateOnly = Carbon::parse(Partida::getOnlyDate($dateToday));
       $hourOnly = Carbon::parse(Partida::getOnlyHour($dateToday));
       foreach ($openGames as $game){
            $dateGame = Carbon::parse($game->fecha);
            $hourGame = Carbon::parse($game->horaInicio);
            if($dateOnly->eq($dateGame) && $hourOnly->lt($hourGame)) {
                array_push($gamesUser,$game);
            }
            if($dateOnly->lt($dateGame)){
                array_push($gamesUser,$game);
            }
       }
       foreach ($reserveGames as $game){
           $dateReserve = Carbon::parse($game->fecha);
           $hourReserve = Carbon::parse($game->horaInicio);
           if($dateOnly->eq($dateReserve) && $hourOnly->lt($hourReserve)) {
               array_push($gamesUser, $game);
           }
           if($dateOnly->lt($dateReserve)){
               array_push($gamesUser, $game);
           }
       }
       return $gamesUser;
    }
    static function getReserveGamesUser($idUser){
        $gamesUser = DB::table('reservaspistas as r')
            ->where('r.idUsuario','=',$idUser)
            ->whereNotExists(function ($query) use ($idUser) {
                $query->select(DB::raw(1))
                    ->from('partidas')
                    ->whereRaw('partidas.idReserva = r.idReserva')
                    //Compruebo que no aparezca ese id en nigún jugador evitando así que se dupliquen
                    ->where(function ($query) use ($idUser) {
                        $query->where('partidas.jugador1', '=', $idUser)
                            ->orWhere('partidas.jugador2', '=', $idUser)
                            ->orWhere('partidas.jugador3', '=', $idUser)
                            ->orWhere('partidas.jugador4', '=', $idUser);
                    });
            })
            ->get();

        return $gamesUser;
    }

    static function getOpenGamesUser($idUser){
        $gamesUser = DB::table('partidas as p')
            ->join('reservaspistas as r','p.idReserva','=','r.idReserva')
            ->leftJoin('jugadores as j1', 'p.jugador1', '=', 'j1.idJugador')
            ->leftJoin('jugadores as j2', 'p.jugador2', '=', 'j2.idJugador')
            ->leftJoin('jugadores as j3', 'p.jugador3', '=', 'j3.idJugador')
            ->leftJoin('jugadores as j4', 'p.jugador4', '=', 'j4.idJugador')
            ->select('p.*', 'r.*' ,
                'j1.apellidos as apellidos1', 'j1.posicionPista as pos1', 'j1.categoria as cat1', 'j1.genero as g1',
                'j2.apellidos as apellidos2','j2.posicionPista as pos2', 'j2.categoria as cat2', 'j2.genero as g2',
                'j3.apellidos as apellidos3','j3.posicionPista as pos3', 'j3.categoria as cat3', 'j3.genero as g3',
                'j4.apellidos as apellidos4','j4.posicionPista as pos4', 'j4.categoria as cat4', 'j4.genero as g4')
            ->where('p.jugador1','=',$idUser)
            ->orWhere('p.jugador2','=',$idUser)
            ->orWhere('p.jugador3','=',$idUser)
            ->orWhere('p.jugador4','=',$idUser)
            ->get();

        return $gamesUser;
    }


}
