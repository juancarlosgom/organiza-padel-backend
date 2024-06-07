<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class Tournament extends Model
{
    use HasFactory;


    static function addNewTournament($title,$description,$dateStart, $dateEnd, $price, $img){
        //Compruebo que la fehca de inicio del torneo no sea menor o igual a la fecha actual mas una semana
        if(self::checkDateStart($dateStart)){
            return true;
        }
        //Compruebo que la fehca de fin del torneo no sea menor a la fecha de inicio
        if(self::checkDateEnd($dateStart,$dateEnd)){
            return true;
        }

        //nameImg = idTournament
        $nameImg = self::insertNewTournament($title,$description,$dateStart, $dateEnd, $price, null);

        // TODO : Subir image
        //$img->getClientOriginalName();
        //$nameImg = Str::slug(strtolower($title));
        $extension = $img->getClientOriginalExtension();
        $image = $nameImg.'.'.$extension;
        Storage::putFileAs('public/torneos',$img,$image);

        self::moveImgPublic($image);

        //$urlImg = public_path('torneos/'.$image);

        self::updateUrlImgTournament($nameImg,$image);

        //Reservo las pistas para las fechas del torneo, se reservan los días completos
        self::reserveTracksForTournament($nameImg,$dateStart,$dateEnd);

        return false;
    }

    static function checkDateStart($dateStart){
        $dateToday = Partida::getDateToday();
        $currentDate = Carbon::parse(Partida::getOnlyDate($dateToday));
        $currentDate->addWeek()->addDay();
        $dateTournamentStart = Carbon::parse($dateStart);
        if($currentDate->lt($dateTournamentStart)){
            return false;
        }
        return true;
    }

    static function checkDateEnd($dateStart,$dateEnd){
        $dateTournamentStart = Carbon::parse($dateStart);
        $dateTournamentEnd = Carbon::parse($dateEnd);
       // $dateTournamentStart->addWeek()->addDay();
        if($dateTournamentEnd->lt($dateTournamentStart)){
            return true;
        }
        return false;
    }

    static function insertNewTournament($title,$description,$dateStart, $dateEnd, $price, $urlImg){
        $idTournament = DB::table('torneos')
            ->insertGetId([
               'idClub' => 1,
               'fechaInicio' => $dateStart,
               'fechaFin' => $dateEnd,
               'parejasTotales' => 40,
               'parejasActuales' => 0,
               'precioInscripcion' => $price,
               'minPartidas' => 3,
               'titulo' => $title,
               'descripcion' => $description,
               'urlImg' => $urlImg
            ]);
        return $idTournament;
    }

    static function updateUrlImgTournament($idTournament,$nameImg){
        DB::table('torneos')
            ->where('idTorneo','=',$idTournament)
            ->update(['urlImg' => $nameImg]);
    }

    static function updateCoupleTournament($idTournament){
        DB::table('torneos')
            ->where('idTorneo','=',$idTournament)
            ->increment('parejasActuales',1);
    }

    static function moveImgPublic($nameImg){
        $originUbi = storage_path('app/public/torneos/'.$nameImg);
        $newUbi = public_path('torneos/'.$nameImg);
        File::move($originUbi,$newUbi);
    }

    static function getTournaments(){
        $tournaments = DB::table('torneos')
            ->get();
        return $tournaments;
    }

    static function singUpTournament($data,$idTorneo){
       $idCouple =  DB::table('parejastorneos')
            ->insertGetId([
                'idTorneo' => $idTorneo,
                'nombre1' => $data['name1'],
                'apellidos1' => $data['lastName1'],
                'dni1' => $data['dni1'],
                'tallaCamiseta1' => $data['size1'],
                'nombre2' => $data['name2'],
                'apellidos2' => $data['lastName2'],
                'dni2' => $data['dni2'],
                'tallaCamiseta2' => $data['size2'],
                'categoria' => $data['category'],
                'email' => $data['email'],
            ]);

       $tournament = self::getTournament($idTorneo);
       if($tournament->parejasActuales + 1 > $tournament->parejasTotales){
            self::deleteCouple($idCouple);
           return true;
       }

        self::updateCoupleTournament($idTorneo);
        return false;
    }

    static function reserveTracksForTournament($idTournament,$dateStart,$dateEnd){
        $dateTournamentStart = Carbon::parse($dateStart);
        $dateTournamentEnd = Carbon::parse($dateEnd);
        $difDays = $dateTournamentStart->diffInDays($dateTournamentEnd);
        $date = $dateStart;
        for ($day = 0; $day <= $difDays; $day++){
            for($track = 1; $track <= 4; $track++){
                for($hour = 1; $hour <= 9; $hour++){
                    $hourTrackStart = self::getHourStart($hour);
                    $hourTrackEnd = self::getHourEnd($hour);
                    DB::table('reservaspistas')
                        ->insert([
                            'idPista' => $track,
                            'horaInicio' => $hourTrackStart,
                            'horaFin' => $hourTrackEnd,
                            'fecha' => $date,
                            'idUsuario' => 31,
                            'searchGame' => 0,
                            'idTorneo' => $idTournament,
                        ]);
                }
            }
            $dateCarbon = Carbon::parse($date);
            $datePlusDay = $dateCarbon->addDay();
            $date = $datePlusDay->toDateString();
        }
    }

    static function getHourStart($index){
        switch ($index){
        case 1: return '08:30';
        case 2: return '10:00';
        case 3: return '11:30';
        case 4: return '13:00';
        case 5: return '14:30';
        case 6: return '16:00';
        case 7: return '17:30';
        case 8: return '19:00';
        case 9: return '20:30';
            default: return  '00:00';
        }
    }
    static function getHourEnd($index){
        switch ($index){
            case 1: return '10:00';
            case 2: return '11:30';
            case 3: return '13:00';
            case 4: return '14:30';
            case 5: return '16:00';
            case 6: return '17:30';
            case 7: return '19:00';
            case 8: return '20:30';
            case 9: return '22:00';
            default: return  '00:00';
        }
    }

    static function checkOtherTournament($dateStartNewTournament, $dateEndNewTournament){
        $dateStartTournament = Carbon::parse($dateStartNewTournament);
        $dateEndTournament = Carbon::parse($dateEndNewTournament);
        $tournaments = self::getTournaments();
        foreach ($tournaments as $tournament){
            $dateStart = Carbon::parse($tournament->fechaInicio);
            $dateEnd = Carbon::parse($tournament->fechaFin);
            if($dateStartTournament->lessThanOrEqualTo($dateEnd) && $dateEndTournament->greaterThanOrEqualTo($dateStart)){
                return true;
            }
        }

        return false;
    }

    static function getTournament($idTournament){
        $tournament = DB::table('torneos')
            ->where('idTorneo','=',$idTournament)
            ->first();
        return $tournament;
    }
    static function deleteCouple($idCouple){
        DB::table('parejastorneos')
            ->where('idPareja','=',$idCouple)
            ->delete();
    }

}
