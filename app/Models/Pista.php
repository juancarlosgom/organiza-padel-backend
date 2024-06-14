<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pista extends Model
{
    use HasFactory;


    static function reservarPista( $idPista,$horaInicio, $horaFin, $fecha,$usuario,$searchGame,$gender){
        //Comprobar si el jugador ya juega ese día a la misma hora
        $getReserveDay = self::getGamesDay($fecha,$usuario->id);

        if(self::checkPlayerNotTwoGames($getReserveDay,$horaInicio)){
            return false;
        }

        if(self::checkHourReserve($fecha,$horaInicio,false)){
            return false;
        }

        $idReserva = DB::table('reservaspistas')->insertGetId([
            'idPista' => $idPista,
            'horaInicio' => $horaInicio,
            'horaFin' => $horaFin,
            'fecha' => $fecha,
            'idUsuario' => $usuario->id,
            'searchGame' => $searchGame,
        ]);

        if($searchGame){
            if(self::checkHourReserve($fecha,$horaInicio,true)){
                self::deleteReserveTrack($idReserva);
                return false;
            }
            self::openGame($idReserva,$idPista,$usuario->id,$gender);
        }

        return true;
    }

    static function checkHourReserve($date,$horaInicio,$pulsOneHour){
        $dateToday = Partida::getDateToday();
        $currentDate = Carbon::parse(Partida::getOnlyDate($dateToday));
        $currentTime = Carbon::parse(Partida::getOnlyHour($dateToday));
        if($pulsOneHour){
            $currentTime->addHour();
        }
        $hourGame = Carbon::parse($horaInicio);
        $dateGame = Carbon::parse($date);
        if($currentDate->eq($dateGame) && $currentTime->gt($hourGame)){
            return true;
        }
        return false;
    }

    static function checkPlayerNotTwoGames($tracksDay,$horaInicio){
        foreach ($tracksDay as $track){
            if($track->horaInicio == $horaInicio){
                return true;
            }
        }
        return false;
    }

    //En esta consulta obtengo en las partidas reservadas o apuntadas de esa $fecha de este $idUser
    static function getGamesDay($fecha,$idUser){
        $tracksReserve = DB::table('reservaspistas as r')
            ->leftJoin('partidas as p','r.idReserva','=','p.idReserva')
            ->where('r.fecha','=',$fecha)
            ->where(function($query) use ($idUser) {
                $query->where('p.jugador1', $idUser)
                    ->orWhere('p.jugador2', $idUser)
                    ->orWhere('p.jugador3', $idUser)
                    ->orWhere('p.jugador4', $idUser)
                    ->orWhere('r.idUsuario', $idUser);
            })
            ->get();
        return $tracksReserve;
    }

    static function openGame($idReserve, $idTrack, $idPlayer1,$gender){
        //Comprobar si el jugador ya juega ese día a la misma hora

        $usuarioAll = User::getUserAll($idPlayer1);
        DB::table('partidas')->insert([
            'idPista' => $idTrack,
            'categoria' => $usuarioAll->categoria,
            'genero' => $gender,
            'puntosRanking' => 50,
            'idReserva' => $idReserve,
            'jugador1' => $idPlayer1,
            'cerrada' => false,
            'adminPartida' => $idPlayer1,
        ]);
    }

    static function getTracksReserveDay($fecha){
        $tracksReserve = DB::table('reservaspistas')
            ->where('fecha','=',$fecha)
            ->get();
        return $tracksReserve;
    }

    static function checkDateMoreOneWeak($date){
        $dateCurrent = Partida::getDateToday();
        $dateOnlyCurrent = Carbon::parse(Partida::getOnlyDate($dateCurrent));
        $dateReserve = Carbon::parse($date);
        $dateOnlyCurrent->addWeek();
        if($dateOnlyCurrent->gt($dateReserve)){
            return false;
        }
        return true;

    }

    static function getGamesOpen($token){
        //Tabla user
        $user = User::getUser($token);
        $idUser = $user->id;

        $gamesOpen = DB::table('partidas as p')
            ->join('reservaspistas as r','p.idReserva','=','r.idReserva')
            ->leftJoin('jugadores as j1', 'p.jugador1', '=', 'j1.idJugador')
            ->leftJoin('users as u1', 'u1.id', '=', 'j1.idJugador')
            ->leftJoin('jugadores as j2', 'p.jugador2', '=', 'j2.idJugador')
            ->leftjoin('users as u2', 'u2.id', '=', 'j2.idJugador')
            ->leftJoin('jugadores as j3', 'p.jugador3', '=', 'j3.idJugador')
            ->leftjoin('users as u3', 'u3.id', '=', 'j3.idJugador')
            ->leftJoin('jugadores as j4', 'p.jugador4', '=', 'j4.idJugador')
            ->leftjoin('users as u4', 'u4.id', '=', 'j4.idJugador')
                //Compruebo que no tenga ese idUsuario para saber si muestro o no
            ->where(function ($query) use ($idUser) {
                $query->where(function ($query2) use ($idUser) {
                    $query2->whereNot('p.jugador1', '=', $idUser)
                        ->orWhereNull('p.jugador1');
                })
                    ->where(function ($query2) use ($idUser) {
                        $query2->whereNot('p.jugador2', '=', $idUser)
                            ->orWhereNull('p.jugador2');
                    })
                    ->where(function ($query2) use ($idUser) {
                        $query2->whereNot('p.jugador3', '=', $idUser)
                            ->orWhereNull('p.jugador3');
                    })
                    ->where(function ($query2) use ($idUser) {
                        $query2->whereNot('p.jugador4', '=', $idUser)
                            ->orWhereNull('p.jugador4');
                    });
            })
            ->where('p.cerrada','=',0)
            ->select('p.*', 'r.*' ,
                'j1.apellidos as apellidos1', 'j1.posicionPista as pos1', 'j1.categoria as cat1', 'j1.genero as g1',
                'j2.apellidos as apellidos2','j2.posicionPista as pos2', 'j2.categoria as cat2', 'j2.genero as g2',
                'j3.apellidos as apellidos3','j3.posicionPista as pos3', 'j3.categoria as cat3', 'j3.genero as g3',
                'j4.apellidos as apellidos4','j4.posicionPista as pos4', 'j4.categoria as cat4', 'j4.genero as g4')
            ->get();

        return $gamesOpen;
    }

    static function deleteReserveTrack($idReserve){
        DB::table('reservaspistas')
            ->where('idReserva','=',$idReserve)
            ->delete();
    }


}
