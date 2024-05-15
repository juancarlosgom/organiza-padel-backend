<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Reserve extends Model
{
    use HasFactory;


    static function getReserveWithOpenGame($idReserve){
        $reserve = DB::table('reservaspistas as r')
            ->join('partidas as p','r.idReserva','=','p.idReserva')
            ->where('r.idReserva','=',$idReserve)
            ->first();
        return $reserve;
    }

    static function updateUserReserveToOpenGame($idReserve,$idNewUser){
        DB::table('reservaspistas')
            ->where('idReserva','=',$idReserve)
            ->update(['idusuario' => $idNewUser]);
    }
}
