<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Partida extends Model
{
    use HasFactory;



    static function singUpGamePlayer($player, $numberPlayer ,$idPartida){
        //Comprobar si el jugador ya juega ese día a la misma hora
        //Obtener datos de la reserva
        $reserve = self::getReserve($idPartida);
        $getReserveDay = Pista::getGamesDay($reserve->fecha,$player->idJugador);
        //Obtengo los de la partida
        //$game = self::getOpenGame($idPartida);

        if(Pista::checkPlayerNotTwoGames($getReserveDay,$reserve->horaInicio)){
            return false;
        }

        DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->update([
                'jugador'.$numberPlayer => $player->idJugador,
            ]);
        if(self::checkClosedGame($idPartida)){
            self::closedGame($idPartida);
        }

        return true;
    }

    static function checkPlayerCanSingUp($game, $user){
        if(($game->categoria === $user->categoria) && ($game->genero === $user->genero)){
            return false;
        }

        if(($game->categoria === $user->categoria) && ($game->genero === 'Mixta')){
            return false;
        }

        return true;
    }

    static function getReserve($idOpenGame){
        $openGame = self::getOpenGame($idOpenGame);
        $reserve = DB::table('reservaspistas')
            ->where('idReserva','=',$openGame->idReserva)
            ->first();
        return $reserve;
    }

    static function getOpenGame($idOpenGame){
        $openGame = DB::table('partidas')
            ->where('idPartida','=',$idOpenGame)
            ->first();
        return $openGame;
    }

    static function checkClosedGame($idPartida){
        $game = DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->first();
        if(($game->jugador1 !== null) && ($game->jugador2 !== null) &&
            ($game->jugador3 !== null) && ($game->jugador4 !== null) ){
            return true;
        }
        return false;
    }

    static function closedGame($idPartida){
        DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->update([
                'cerrada' => true,
            ]);
    }

    static function getOpenGamesDay($dateToday){
        $onlyDate = self::getOnlyDate($dateToday);

        $openGamesDay = DB::table('reservaspistas as r')
                            ->join('partidas as p','r.idReserva','=','p.idReserva')
                            ->where('r.fecha','=',$onlyDate)
                            ->where('r.searchGame','=',1)
                            ->where('p.cerrada','=',0)
                            ->get();
        return $openGamesDay;
    }

    static function checkOpenGames($dateToday){
        $openGamesToDelete = array();
        $onlyHour = self::getOnlyHour($dateToday);
        $currentTime = Carbon::createFromTimeString($onlyHour);
        $currentTimePlusOne = $currentTime->addHour();
        $openGamesDay = self::getOpenGamesDay($dateToday);
        foreach ($openGamesDay as $openGame){
            $gameTime = Carbon::createFromTimeString($openGame->horaInicio);
            if ($currentTimePlusOne->gt($gameTime)){
                array_push($openGamesToDelete, $openGame);
            }
        }
        return $openGamesToDelete;
    }

    static function getDateToday(){
        $dateToday = new \DateTime('now', new \DateTimeZone('Europe/Madrid'));
        //Obtengo en formato como la BD
        $date = $dateToday->format('Y-m-d H:i:s');
        return $date;
    }
    static function getOnlyDate($date){
        $onlyDate = explode(' ',$date);
        return $onlyDate[0];
    }

    static function getOnlyHour($date){
        $onlyHours = explode(' ',$date);
        $hours = explode(':',$onlyHours[1]);
        $hour = $hours[0].':'.$hours[1];
        return $hour;
    }

    static function deleteOpenGamesNotClosed($openGamesToDelete){
        foreach ($openGamesToDelete as $openGame){
            DB::table('reservaspistas')
                ->where('idReserva','=',$openGame->idReserva)
                ->delete();
            DB::table('partidas')
                ->where('idReserva','=',$openGame->idReserva)
                ->delete();
        }
    }

    static function deleteUserOpenGame($idGame, $userId, $player){
        DB::table('partidas')
            ->where('idPartida','=',$idGame)
            ->update([$player => null]);
        $openGame = self::getOpenGame($idGame);

        if(self::checkUpdateClose($openGame)){
            DB::table('partidas')
                ->where('idPartida','=',$idGame)
                ->update(['cerrada' => 0]);
        }

        if(self::checkDeleteReserve($openGame)){
            self::deleteGame($idGame);
            Pista::deleteReserveTrack($openGame->idReserva);

        }else{
            $reserve = Reserve::getReserveWithOpenGame($openGame->idReserva);
            if($userId === $reserve->idUsuario){
                $newUser = self::getNewUserReserve($reserve->idReserva);
                Reserve::updateUserReserveToOpenGame($reserve->idReserva,$newUser->new_user);
                self::updateUserAdminOpenGame($reserve->idReserva,$newUser->new_user);
            }
        }
    }

    static function updateUserAdminOpenGame($idReserve,$idNewUser){
        DB::table('partidas')
            ->where('idReserva','=',$idReserve)
            ->update(['adminPartida' => $idNewUser]);
    }

    static function checkDeleteReserve($game){
        if(($game->jugador1 === null) && ($game->jugador2 === null) &
            ($game->jugador3 === null) && ($game->jugador4 === null) ){
            return true;
        }
        return false;
    }

    static function checkUpdateClose($game){
        if(($game->jugador1 === null) || ($game->jugador2 === null) ||
            ($game->jugador3 === null) || ($game->jugador4 === null) ){
            return true;
        }
        return false;
    }

    static function deleteGame($idGame){
        DB::table('partidas')
            ->where('idPartida','=',$idGame)
            ->delete();
    }
    static function getNewUserReserve($idReserve){
        $newUser =  DB::table('partidas')
            ->where('idReserva','=',$idReserve)
            ->selectRaw('COALESCE(jugador1, jugador2, jugador3, jugador4) AS new_user')
            ->first();

        return $newUser;
    }

    static function getGame($idGame){
        $game = DB::table('partidas as p')
            ->join('jugadores as j1', 'p.jugador1', '=', 'j1.idJugador')
            ->join('users as u1', 'u1.id', '=', 'j1.idJugador')
            ->join('jugadores as j2', 'p.jugador2', '=', 'j2.idJugador')
            ->join('users as u2', 'u2.id', '=', 'j2.idJugador')
            ->join('jugadores as j3', 'p.jugador3', '=', 'j3.idJugador')
            ->join('users as u3', 'u3.id', '=', 'j3.idJugador')
            ->join('jugadores as j4', 'p.jugador4', '=', 'j4.idJugador')
            ->join('users as u4', 'u4.id', '=', 'j4.idJugador')
            ->select('p.*',
                'j1.apellidos as apellidos1', 'j1.posicionPista as pos1', 'j1.categoria as cat1', 'j1.genero as g1',
                'j2.apellidos as apellidos2','j2.posicionPista as pos2', 'j2.categoria as cat2', 'j2.genero as g2',
                'j3.apellidos as apellidos3','j3.posicionPista as pos3', 'j3.categoria as cat3', 'j3.genero as g3',
                'j4.apellidos as apellidos4','j4.posicionPista as pos4', 'j4.categoria as cat4', 'j4.genero as g4')
            ->where('p.idPartida','=',$idGame)
            ->get();

        return $game;
    }

    static function addResultGame($datos,$idUser,$player,$idGame){
        $game = DB::table('partidas as p')
            ->join('reservaspistas as r','p.idReserva','=','r.idReserva')
            ->where('p.idPartida','=',$idGame)
            ->first();
        DB::table('resultadospartidas')
            ->insert([
                'jugador1' => $datos['jugador1'],
                'jugador2' => $datos['jugador2'],
                'jugador3' => $datos['jugador3'],
                'jugador4' => $datos['jugador4'],
                'parejaGanadora' => $datos['parejaGanadora'],
                'resultado' => $datos['resultado'],
                'confirm'.$player => $idUser,
                'fecha' => $game->fecha,
                'horaInicio' => $game->horaInicio,
                'horaFin' => $game->horaFin,
                'idPista' => $game->idPista,
                'categoria' => $game->categoria,
            ]);
    }

    static function deleteGameAndReserve($idPartida){
        $game =  DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->first();

        DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->delete();
        DB::table('reservaspistas')
            ->where('idReserva','=',$game->idReserva)
            ->delete();
    }

    static function getConfirmGames($idUser){
        $games = DB::table('resultadospartidas as r')
                    ->join('jugadores as j1', 'r.jugador1', '=', 'j1.idJugador')
                    ->join('jugadores as j2', 'r.jugador2', '=', 'j2.idJugador')
                    ->join('jugadores as j3', 'r.jugador3', '=', 'j3.idJugador')
                    ->join('jugadores as j4', 'r.jugador4', '=', 'j4.idJugador')
                    ->select('r.*',
                        'j1.apellidos as apellidos1', 'j1.posicionPista as pos1', 'j1.categoria as cat1', 'j1.genero as g1',
                        'j2.apellidos as apellidos2','j2.posicionPista as pos2', 'j2.categoria as cat2', 'j2.genero as g2',
                        'j3.apellidos as apellidos3','j3.posicionPista as pos3', 'j3.categoria as cat3', 'j3.genero as g3',
                        'j4.apellidos as apellidos4','j4.posicionPista as pos4', 'j4.categoria as cat4', 'j4.genero as g4')
                    ->where('jugador1','=',$idUser)
                    ->orWhere('jugador2','=',$idUser)
                    ->orWhere('jugador3','=',$idUser)
                    ->orWhere('jugador4','=',$idUser)
                    ->get();
        return $games;
    }

    static function checkNotConfirm($games,$idUser){
        $verifiedGames = array();
        foreach ($games as $game){
            if(($game->confirm1 !== $idUser) && ($game->confirm2 !== $idUser)
                && ($game->confirm3 !== $idUser) && ($game->confirm4 !== $idUser)){
                array_push($verifiedGames,$game);
            }
        }
        return $verifiedGames;
    }

    static function updateConfirmUserResult($idResult,$playerConfirm, $idUser){
        DB::table('resultadospartidas')
            ->where('idResultado','=',$idResult)
            ->update(['confirm'.$playerConfirm => $idUser]);
        $result = self::getResult($idResult);
        if(self::checkAllConfirm($result)){
            self::checkWinCouple($result);
            self::addResultHistory($result);
            self::deleteResult($result->idResultado);
        }
    }

    static function deleteResult($idResult){
        DB::table('resultadospartidas')
            ->where('idResultado','=',$idResult)
            ->delete();
    }

    static function addResultHistory($result){
        DB::table('historicos')
            ->insert([
               'jugador1' => $result->jugador1,
               'jugador2' => $result->jugador2,
               'jugador3' => $result->jugador3,
               'jugador4' => $result->jugador4,
               'resultado' => $result->resultado,
               'parejaGanadora' => $result->parejaGanadora,
               'horaInicio' => $result->horaInicio,
               'horaFin' => $result->horaFin,
               'fecha' => $result->fecha,
               'categoria' => $result->categoria,
            ]);
    }

    static function checkAllConfirm($result){
        if($result->confirm1 !== null && $result->confirm2 !== null
            && $result->confirm3 !== null && $result->confirm4 !== null){
            return true;
        }
        return false;
    }

    static function getResult($idResult){
        $result = DB::table('resultadospartidas')
                ->where('idResultado','=',$idResult)
                ->first();
        return $result;
    }

    static function checkWinCouple($result){
        if($result->parejaGanadora === '1'){
            User::addRankingPoints($result->jugador1);
            User::addRankingPoints($result->jugador2);
            User::subtractRankingPoints($result->jugador3);
            User::subtractRankingPoints($result->jugador4);
            User::updateStatisticsUser($result->jugador1,'partidasGanadas');
            User::updateStatisticsUser($result->jugador2,'partidasGanadas');
            User::updateStatisticsUser($result->jugador3,'partidasPerdidas');
            User::updateStatisticsUser($result->jugador4,'partidasPerdidas');
        }else{
            User::addRankingPoints($result->jugador3);
            User::addRankingPoints($result->jugador4);
            User::subtractRankingPoints($result->jugador1);
            User::subtractRankingPoints($result->jugador2);
            User::updateStatisticsUser($result->jugador3,'partidasGanadas');
            User::updateStatisticsUser($result->jugador4,'partidasGanadas');
            User::updateStatisticsUser($result->jugador1,'partidasPerdidas');
            User::updateStatisticsUser($result->jugador2,'partidasPerdidas');
        }
    }
}
