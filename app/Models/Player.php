<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Player extends Model
{
    use HasFactory;

    static function getPlayers(){
        $players = DB::table('jugadores as j')
            ->join('users as u','j.idJugador','=','id')
            ->join('estadisticas as e','j.idJugador','=','e.idJugador')
            ->select('j.apellidos','j.categoria','j.ranking','j.genero',
                    'u.name','e.partidasGanadas','e.partidasPerdidas')
            ->get();

        return $players;
    }

}
