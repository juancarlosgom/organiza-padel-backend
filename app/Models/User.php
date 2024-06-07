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
        self::createStatisticsPlayer($datosUser->id,$puntosRanking);
    }

    static function createStatisticsPlayer($idUser,$pointsRanking){
        DB::table('estadisticas')
            ->insert([
               'idJugador' => $idUser,
               'partidasJugadas' => 0,
               'partidasGanadas' => 0,
               'partidasPerdidas' => 0,
               'puntosRanking' => $pointsRanking,
            ]);
    }

    static function getPointsRanking($categoria){
        switch ($categoria){
            case '1': $points = 2100;
                break;
            case '2': $points = 1700;
                break;
            case '3': $points = 1100;
                break;
            case '4': $points = 600;
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


    static function getAdminUserGame($user){
        $dateToday = Partida::getDateToday();
        $onlyDate = Partida::getOnlyDate($dateToday);
        $onlyHour = Partida::getOnlyHour($dateToday);
        $games = self::getAdminGames($user);
        $verifiedGames = self::checkCanPutResult($games,$onlyHour, $onlyDate);
        return $verifiedGames;
    }

    static function getAdminGames($user){
        $games = DB::table('partidas as p')
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
            ->where('p.adminPartida','=',$user->id)
            ->where('p.cerrada','=',1)
            ->get();
        return $games;
    }

    static function checkCanPutResult($games,$hour, $date){
        $dateCarbon = Carbon::parse($date);
        $hourCarbon = Carbon::parse($hour);
        $verifiedGames = array();
        foreach ($games as $game){
            $dateGame = Carbon::parse($game->fecha);
            $endHourGame = Carbon::parse($game->horaFin);
            if(($dateCarbon->eq($dateGame) && $hourCarbon->gt($endHourGame)) || ($dateCarbon->gt($dateGame))){
                array_push($verifiedGames,$game);
            }
        }
        return $verifiedGames;
    }

    static function subtractRankingPoints($idUser){
        $player = self::getUserAll($idUser);
        if(self::checkSubtractPoints($player)) {
            DB::table('jugadores')
                ->where('idJugador','=',$idUser)
                ->decrement('ranking',50);
        }
        $player = self::getUserAll($idUser);
        $category = self::checkCategory($player->ranking);
        self::updateCategory($player->idJugador,$category);
    }

    static function addRankingPoints($idUser){
        $player = self::getUserAll($idUser);
        if(self::checkAddPoints($player)) {
            DB::table('jugadores')
                ->where('idJugador','=',$idUser)
                ->increment('ranking',50);
        }
        $player = self::getUserAll($idUser);
        $category = self::checkCategory($player->ranking);
        self::updateCategory($player->idJugador,$category);
    }

    static function checkCategory($points){
       if(($points <= 2400) && ($points >= 2000)){
           return '1';
       }
        if(($points < 2000) && ($points >= 1600)){
            return '2';
        }
        if(($points < 1600) && ($points >= 1000)){
            return '3';
        }
        if(($points < 1000) && ($points >= 400)){
            return '4';
        }
        return '5';
    }

    static function checkSubtractPoints($user){
        if(($user->ranking - 50)  < 0){
            return false;
        }
        return true;
    }

    static function checkAddPoints($user){
        if(($user->ranking + 50)  > 2400){
            return false;
        }
        return true;
    }

    static function updateCategory($idUser,$category){
        DB::table('jugadores')
            ->where('idJugador','=',$idUser)
            ->update(['categoria' => $category]);
    }

    static function getHistoryUser($idUser){
        $history = DB::table('historicos as h')
            ->join('jugadores as j1', 'h.jugador1', '=', 'j1.idJugador')
            ->join('jugadores as j2', 'h.jugador2', '=', 'j2.idJugador')
            ->join('jugadores as j3', 'h.jugador3', '=', 'j3.idJugador')
            ->join('jugadores as j4', 'h.jugador4', '=', 'j4.idJugador')
            ->select('h.*',
                'j1.apellidos as apellidos1', 'j1.posicionPista as pos1', 'j1.categoria as cat1', 'j1.genero as g1',
                'j2.apellidos as apellidos2','j2.posicionPista as pos2', 'j2.categoria as cat2', 'j2.genero as g2',
                'j3.apellidos as apellidos3','j3.posicionPista as pos3', 'j3.categoria as cat3', 'j3.genero as g3',
                'j4.apellidos as apellidos4','j4.posicionPista as pos4', 'j4.categoria as cat4', 'j4.genero as g4')
            ->where('h.jugador1', $idUser)
            ->orWhere('h.jugador2', $idUser)
            ->orWhere('h.jugador3', $idUser)
            ->orWhere('h.jugador4', $idUser)
            ->get();

        return $history;
    }

    static function getStatisticsUser($idUser){
        $statistics = DB::table('estadisticas as e')
            ->join('jugadores as j','e.idJugador','=','j.idJugador')
            ->select('e.*','j.*')
            ->where('e.idJugador','=',$idUser)
            ->first();

        return $statistics;
    }

    static function updateStatisticsUser($idUser,$column){
        $userAll = self::getUserAll($idUser);

        DB::table('estadisticas')
            ->where('idJugador','=',$idUser)
            ->update([
                $column => DB::raw("$column + 1"),
                'partidasJugadas' => DB::raw("partidasJugadas + 1"),
                'puntosRanking' => $userAll->ranking,
            ]);
            //->increment($colum,1);

        /*self::updateStatisticsUserGames($idUser);
        self::updateStatisticsUserPoints($idUser);*/
    }

    /*static function updateStatisticsUserGames($idUser){
        DB::table('estadisticas')
            ->where('idJugador','=',$idUser)
            ->increment('partidasJugadas',1);
    }

    static function updateStatisticsUserPoints($idUser){
        $userAll = self::getUserAll($idUser);
        DB::table('estadisticas')
            ->where('idJugador','=',$idUser)
            ->update([
                'puntosRanking' => $userAll->ranking,
            ]);
    }*/

    static function updateDateUser($datosUser,$idUser){

        DB::table('jugadores')
            ->where('idJugador','=',$idUser)
            ->update([
                'apellidos' => $datosUser['apellidos'],
                'telefono' => $datosUser['telefono'],
                'posicionPista' => $datosUser['posicionPista'],
                'tallaCamiseta' => $datosUser['tallaCamiseta'],
                'genero' =>  $datosUser['genero'],
            ]);

        DB::table('users')
            ->where('id','=',$idUser)
            ->update([
                'name' => $datosUser['name'],
                'email' => $datosUser['email'],
            ]);
    }


}
