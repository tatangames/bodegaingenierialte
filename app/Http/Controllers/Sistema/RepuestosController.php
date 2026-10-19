<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\ObjetoEspecifico;
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

    public function index()
    {
        $lUnidad           = UnidadMedida::orderBy('nombre', 'ASC')->get();
        $lObjetoEspecifico = ObjetoEspecifico::with('cuenta')
            ->orderBy('nombre', 'ASC')->get();

        return view('backend.admin.inventario.vistainventario',
            compact('lUnidad', 'lObjetoEspecifico'));
    }

    public function tablaMateriales()
    {
        $filtro = request('filtro', 'todos'); // 'todos' | 'sin_objeto'

        $query = Materiales::with('objetoEspecifico')
            ->orderBy('nombre', 'ASC');

        if ($filtro === 'sin_objeto') {
            $query->whereNull('id_objespecifico');
        }

        $lista = $query->get();

        // Medidas: id => nombre
        $medidas = UnidadMedida::pluck('nombre', 'id');

        // Entradas por material
        $entradasPorMaterial = DB::table('entradas_detalle')
            ->select('id_material', DB::raw('SUM(cantidad_inicial) as total'))
            ->groupBy('id_material')
            ->pluck('total', 'id_material');

        // Salidas brutas por material
        $salidasPorMaterial = DB::table('salidas_detalle as sd')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->select('ed.id_material', DB::raw('SUM(sd.cantidad_salida) as total'))
            ->groupBy('ed.id_material')
            ->pluck('total', 'ed.id_material');

        // Reingresos por material
        $reingresosPorMaterial = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->select('ed.id_material', DB::raw('SUM(r.cantidad) as total'))
            ->groupBy('ed.id_material')
            ->pluck('total', 'ed.id_material');

        foreach ($lista as $item) {
            $item->medida = $medidas[$item->id_medida] ?? '';

            $entradas   = (int) ($entradasPorMaterial[$item->id] ?? 0);
            $reingresos = (int) ($reingresosPorMaterial[$item->id] ?? 0);

            // Salidas netas = salidas brutas - lo que volvió
            $salidas = (int) ($salidasPorMaterial[$item->id] ?? 0) - $reingresos;

            $item->entradas   = $entradas;
            $item->salidas    = $salidas;
            $item->reingresos = $reingresos; // por si quieres mostrarlo en la tabla
            $item->total      = $entradas - $salidas;

            // Relación ya cargada con with()
            $item->objeto_especifico = $item->objetoEspecifico;
        }

        return view('backend.admin.inventario.tablainventario', compact('lista'));
    }

    public function nuevoMaterial(Request $request)
    {
        $validar = Validator::make($request->all(), [
            'nombre'           => 'required',
            'id_objespecifico' => 'required|exists:objeto_especifico,id',
        ]);
        if ($validar->fails()) { return ['success' => 0]; }

        $dato = new Materiales();
        $dato->nombre           = $request->nombre;
        $dato->id_medida        = $request->unidad ?: null;
        $dato->id_objespecifico = $request->id_objespecifico;
        $dato->codigo           = $request->codigo;

        return $dato->save() ? ['success' => 1] : ['success' => 2];
    }

    public function informacionMaterial(Request $request)
    {
        $validar = Validator::make($request->all(), ['id' => 'required']);
        if ($validar->fails()) { return ['success' => 0]; }

        if ($lista = Materiales::where('id', $request->id)->first()) {
            $arrayUnidad         = UnidadMedida::orderBy('nombre', 'ASC')->get();
            $arrayObjetoEspecifico = ObjetoEspecifico::with('cuenta.rubro')
                ->orderBy('nombre', 'ASC')->get();

            return [
                'success'           => 1,
                'material'          => $lista,
                'unidad'            => $arrayUnidad,
                'objeto_especifico' => $arrayObjetoEspecifico,
            ];
        }

        return ['success' => 2];
    }

    public function editarMaterial(Request $request)
    {
        $validar = Validator::make($request->all(), [
            'id'               => 'required',
            'nombre'           => 'required',
            'id_objespecifico' => 'required|exists:objeto_especifico,id',
        ]);
        if ($validar->fails()) { return ['success' => 0]; }

        // Bloquear edición si el material ya tiene entradas registradas.
        // (El botón "Editar" ya se deshabilita en la vista, pero validamos
        // también aquí por si el endpoint se llama directamente).
        $tieneEntradas = EntradasDetalle::where('id_material', $request->id)->exists();

        if ($tieneEntradas) {
            return [
                'success' => 3,
                'msg'     => 'Este material ya tiene entradas registradas y no puede editarse.',
            ];
        }

        Materiales::where('id', $request->id)->update([
            'id_medida'        => $request->unidad ?: null,
            'id_objespecifico' => $request->id_objespecifico,
            'nombre'           => $request->nombre,
            'codigo'           => $request->codigo,
        ]);

        return ['success' => 1];
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

        // ── Validar que el proyecto no esté cerrado ──
        $proyecto = Tipoproyecto::find($request->tipoproyecto);
        if (!$proyecto || $proyecto->transferido == 1) {
            return ['success' => 2]; // proyecto cerrado o no existe
        }

        DB::beginTransaction();

        try {
            $datosContenedor = json_decode($request->contenedorArray, true);

            // ── Cabecera ──
            $registro = new Entradas();
            $registro->id_tipoproyecto = $request->tipoproyecto;
            $registro->fecha = Carbon::parse($request->fecha);
            $registro->descripcion = $request->descripcion;
            $registro->factura = $request->lote;
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




}
