<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\HistorialEntradas;
use App\Models\HistorialSalidas;
use App\Models\HistorialTransferido;
use App\Models\TipoProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoProyectoController extends Controller
{

    public function index(){
        return view('backend.admin.tipoproyecto.vistatipoproyecto');
    }

    public function tablaProyectos(Request $request){
        $filtro = $request->get('filtro', 'vigente'); // por defecto vigentes

        if($filtro === 'cerrado'){
            $lista = TipoProyecto::where('transferido', 1)->orderBy('nombre', 'ASC')->get();
        } elseif($filtro === 'todos'){
            $lista = TipoProyecto::orderBy('nombre', 'ASC')->get();
        } else {
            $lista = TipoProyecto::where('transferido', 0)->orderBy('nombre', 'ASC')->get();
        }

        return view('backend.admin.tipoproyecto.tablatipoproyecto', compact('lista'));
    }

    public function nuevoProyecto(Request $request){
        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        $dato = new TipoProyecto();
        $dato->nombre = $request->nombre;
        $dato->transferido = 0;
        $dato->fecha_cierre = null;

        if($dato->save()){
            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }

    public function informacionProyecto(Request $request)
    {
        $regla = array(
            'id' => 'required'
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()) {
            return ['success' => 0];
        }

        $proyecto = TipoProyecto::where('id', $request->id)->first();

        if (!$proyecto) {
            return ['success' => 2];
        }

        $tieneEntradas = Entradas::where('id_tipoproyecto', $proyecto->id)->exists();

        return [
            'success'        => 1,
            'info'           => $proyecto,
            'tiene_entradas' => $tieneEntradas,
        ];
    }

    public function editarProyecto(Request $request)
    {
        $regla = array(
            'id' => 'required',
            'nombre' => 'required'
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()) {
            return ['success' => 0];
        }

        $proyecto = TipoProyecto::where('id', $request->id)->first();

        if (!$proyecto) {
            return ['success' => 2];
        }

        $tieneEntradas = Entradas::where('id_tipoproyecto', $proyecto->id)->exists();

        if ($tieneEntradas) {
            return ['success' => 3];
        }

        $proyecto->update([
            'nombre' => $request->nombre
        ]);

        return ['success' => 1];
    }


}
