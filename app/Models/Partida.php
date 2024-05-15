<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Partida extends Model
{
    use HasFactory;



    static function singUpGamePlayer($idPlayer, $numberPlayer ,$idPartida){
        //TODO - Comprobar si el jugador ya juega ese día a la misma hora
        //Obtener datos de la partida
        $reserve = self::getReserve($idPartida);
        $getReserveDay = Pista::getGamesDay($reserve->fecha,$idPlayer);

        if(Pista::checkPlayerNotTwoGames($getReserveDay,$reserve->horaInicio)){
            return false;
        }

        DB::table('partidas')
            ->where('idPartida','=',$idPartida)
            ->update([
                'jugador'.$numberPlayer => $idPlayer,
            ]);
        if(self::checkClosedGame($idPartida)){
            self::closedGame($idPartida);
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

        $openGamesDay = DB::table('reservaspistas')
                            ->where('fecha','=',$onlyDate)
                            ->where('searchGame','=',1)
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
            }
        }
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
}
