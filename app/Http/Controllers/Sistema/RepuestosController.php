<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Herramientas;
use App\Models\HistoHerramientaDescartada;
use App\Models\HistorialEntradas;
use App\Models\HistorialEntradasDeta;
use App\Models\Materiales;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RepuestosController extends Controller
{

    public function index(){
        $lUnidad = UnidadMedida::orderBy('nombre', 'ASC')->get();
        return view('backend.admin.inventario.vistainventario', compact('lUnidad'));
    }

    public function tablaMateriales(){

        $lista = Materiales::orderBy('nombre', 'ASC')->get();

        foreach ($lista as $item) {

            // Unidad de medida
            $medida = '';
            if($dataUnidad = UnidadMedida::where('id', $item->id_medida)->first()){
                $medida = $dataUnidad->nombre;
            }
            $item->medida = $medida;

            // STOCK REAL = entradas - salidas
            $entradas = DB::table('entradas_detalle')
                ->where('id_material', $item->id)  // 👈 id_material
                ->sum('cantidad_inicial');

            $salidas = DB::table('salidas_detalle as sd')
                ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')  // 👈 id_entrada_detalle
                ->where('ed.id_material', $item->id)  // 👈 id_material
                ->sum('sd.cantidad_salida');

            $item->total    = $entradas - $salidas;
            $item->entradas = $entradas;
            $item->salidas  = $salidas;
        }

        return view('backend.admin.inventario.tablainventario', compact('lista'));
    }

    public function nuevoMaterial(Request $request){

        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        $dato = new Materiales();
        $dato->id_medida = $request->unidad;
        $dato->nombre = $request->nombre;

        if($dato->save()){
            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }

    public function informacionMaterial(Request $request){
        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if($lista = Materiales::where('id', $request->id)->first()){

            $arrayUnidad = UnidadMedida::orderBy('nombre', 'ASC')->get();

            return ['success' => 1, 'material' => $lista, 'unidad' => $arrayUnidad];
        }else{
            return ['success' => 2];
        }
    }

    public function editarMaterial(Request $request){

        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        Materiales::where('id', $request->id)->update([
            'id_medida' => $request->unidad,
            'nombre' => $request->nombre,
        ]);

        return ['success' => 1];
    }


    public function infoHerramientaDescartar(Request $request){

        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        $info = Herramientas::where('id', $request->id)->first();


        return ['success' => 1, 'info' => $info];
    }


    public function descartarHerramientaInventario(Request $request){


        $rules = array(
            'id' => 'required',
            'cantidad' => 'required',
            'descripcion' => 'required'
        );

        $validator = Validator::make($request->all(), $rules);
        if ( $validator->fails()){
            return ['success' => 0];
        }

        DB::beginTransaction();


        try {

            $infoHerra = Herramientas::where('id', $request->id)->first();

            if($request->cantidad > $infoHerra->cantidad){

                // cantidad a descartar es mayor a la del inventario
                return ['success' => 1];
            }


            //**************************
            // RESTAR CANTIDAD A HERRAMIENTAS

            $restado = $infoHerra->cantidad - $request->cantidad;
            $fechaCarbon = Carbon::parse(Carbon::now());

            Herramientas::where('id', $request->id)->update([
                'cantidad' => $restado
            ]);


            // guardar historial del descartado
            $datoDescarto = new HistoHerramientaDescartada();
            $datoDescarto->id_histo_herra_salida = null;
            $datoDescarto->id_herramienta = $infoHerra->id;
            $datoDescarto->fecha = $fechaCarbon;
            $datoDescarto->cantidad = $request->cantidad;
            $datoDescarto->descripcion = $request->descripcion;
            $datoDescarto->save();


            DB::commit();
            return ['success' => 2];

        }catch(\Throwable $e){
            Log::info('err ' . $e);
            DB::rollback();
            return ['success' => 99];
        }

    }



    //*******************************************************************

    public function indexRegistroEntrada(){

        $arrayProyecto = TipoProyecto::where('transferido', 0)
            ->orderBy('nombre')
            ->get();

        return view('backend.admin.repuestos.registros.vistaentradaregistro', compact('arrayProyecto'));
    }


    public function buscadorMaterial(Request $request){

        if($request->get('query')){
            $query = $request->get('query');
            $data = Materiales::where('nombre', 'LIKE', "%{$query}%")
                ->get();

            foreach ($data as $dd){
                if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                    $dd->medida = "- " . $info->nombre;
                }else{
                    $dd->medida = "";
                }
            }

            $output = '<ul class="dropdown-menu" style="display:block; position:relative;">';
            $tiene = true;
            foreach($data as $row){

                // si solo hay 1 fila, No mostrara el hr, salto de linea
                if(count($data) == 1){
                    if(!empty($row)){
                        $tiene = false;
                        $output .= '
                 <li onclick="modificarValor(this)" id="'.$row->id.'"><a href="#" style="margin-left: 3px">'.$row->nombre . '  ' .$row->medida .'</a></li>
                ';
                    }
                }

                else{
                    if(!empty($row)){
                        $tiene = false;
                        $output .= '
                 <li onclick="modificarValor(this)" id="'.$row->id.'"><a href="#" style="margin-left: 3px">'.$row->nombre . ' ' .$row->medida .'</a></li>
                   <hr>
                ';
                    }
                }
            }
            $output .= '</ul>';
            if($tiene){
                $output = '';
            }
            echo $output;
        }
    }

    // GUARDAR ENTRADAS
    public function guardarEntrada(Request $request)
    {
        $rules = [
            'fecha'     => 'required',
            'tipoproyecto' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['success' => 0];
        }

        DB::beginTransaction();

        try {
            $datosContenedor = json_decode($request->contenedorArray, true);

            // ── Cabecera ──
            $registro = new Entradas();
            $registro->id_tipoproyecto = $request->tipoproyecto;
            $registro->fecha = Carbon::parse($request->fecha)
                ->setTimeFrom(Carbon::now());
            $registro->descripcion = $request->descripcion;
            $registro->factura = $request->factura;
            $registro->es_transferencia = 0;
            $registro->id_tipoproyecto_transferencia = null;
            $registro->save();

            // ── Detalle ──
            foreach ($datosContenedor as $fila) {
                $detalle = new EntradasDetalle();
                $detalle->id_entradas        = $registro->id;
                $detalle->id_material        = $fila['idMaterial'];
                $detalle->cantidad_inicial   = $fila['infoCantidad'];
                $detalle->precio             = $fila['infoPrecio'];
                $detalle->codigo             = $fila['infoCodigo'];
                $detalle->save();
            }

            DB::commit();
            return ['success' => 1];

        } catch (\Throwable $e) {
            Log::error('guardarEntrada: ' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }


    public function proyectosPorMaterial(Request $request)
    {
        $idMaterial = $request->id;

        // IDs de entradas_detalle de este material
        $idsEntradasDetalle = EntradasDetalle::where('id_material', $idMaterial)
            ->pluck('id');

        // Entradas por proyecto
        $entradas = EntradasDetalle::where('id_material', $idMaterial)
            ->with('entrada.tipoproyecto')
            ->get()
            ->groupBy(fn($item) => $item->entrada->id_tipoproyecto)
            ->map(fn($grupo) => [
                'proyecto' => $grupo->first()->entrada->tipoproyecto->nombre ?? '—',
                'entradas' => $grupo->sum('cantidad_inicial'),
            ]);

        // Salidas por proyecto usando whereIn
        $salidas = \App\Models\SalidasDetalle::whereIn('id_entrada_detalle', $idsEntradasDetalle)
            ->with('salida.tipoproyecto')
            ->get()
            ->groupBy(fn($item) => $item->salida->id_tipoproyecto)
            ->map(fn($grupo) => $grupo->sum('cantidad_salida'));

        // Combinar
        $proyectos = $entradas->map(function ($dato, $idProyecto) use ($salidas) {
            $sal = $salidas[$idProyecto] ?? 0;
            return [
                'proyecto'   => $dato['proyecto'],
                'entradas'   => $dato['entradas'],
                'salidas'    => $sal,
                'disponible' => $dato['entradas'] - $sal,
            ];
        })->values();

        return response()->json([
            'success'   => 1,
            'proyectos' => $proyectos,
        ]);
    }





    //*******************************************

    public function vistaDetalleMaterial($id){

        $infomaterial = Materiales::where('id', $id)->first();
        $medida = '';
        if($infoMedida = UnidadMedida::where('id', $infomaterial->id_medida)->first()){
            $medida = $infoMedida->nombre;
        }

        return view('backend.admin.inventario.detalle.vistadetalle', compact('id', 'infomaterial', 'medida'));
    }


    public function tablaDetalleMaterial($id){

        // SOLO HABRA 1 MATERIAL POR CADA PROYECTO
        $arrayEntradas =  Entradas::where('id_material', $id)->get();

        $pilaArrayEntrada = array();


        foreach ($arrayEntradas as $data){

            // VERIFICAR QUE LA CANTIDAD SEA MAYOR A 0 PARA PODER
            // MOSTRARLO
            if($data->cantidad > 0){
                array_push($pilaArrayEntrada, $data->id);
            }
        }

        $lista = Entradas::whereIn('id', $pilaArrayEntrada)
            ->orderBy('id_tipoproyecto', 'ASC')
            ->get();

        foreach ($lista as $info){
            // OBTENER NOMBRE DE PROYECTO

            $infoProyecto = TipoProyecto::where('id', $info->id_tipoproyecto)->first();
            $info->nombrepro = $infoProyecto->nombre;
        }

        return view('backend.admin.inventario.detalle.tabladetallematerial', compact('lista'));
    }



}
