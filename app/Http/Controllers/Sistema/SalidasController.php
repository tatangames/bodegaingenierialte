<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\HistorialTransferido;
use App\Models\HistorialTransferidoDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SalidasController extends Controller
{

    public function indexRegistroSalida(){

        $arrayProyectos = TipoProyecto::orderBy('nombre')->get();
        return view('backend.admin.repuestos.salidas.vistasalidaregistro', compact('arrayProyectos'));
    }


    public function buscadorMaterialDisponible(Request $request)
    {
        if ($request->get('query')) {

            $query = $request->get('query');

            $materiales = Materiales::where('nombre', 'LIKE', "%{$query}%")->pluck('id');

            if ($materiales->isEmpty()) {
                return '';
            }
            // ✅ Agrupar por id_material y SUMAR todos los disponibles
            $listado = DB::table('entradas_detalle as ed')
                ->leftJoin(
                    DB::raw('(
            SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
            FROM salidas_detalle
            GROUP BY id_entrada_detalle
        ) as sd'),
                    'sd.id_entrada_detalle', '=', 'ed.id'
                )
                ->select(
                    'ed.id_material',
                    DB::raw('SUM(ed.cantidad_inicial) as total_inicial'),
                    DB::raw('COALESCE(SUM(sd.total_salido), 0) as total_salido'),
                    DB::raw('(SUM(ed.cantidad_inicial) - COALESCE(SUM(sd.total_salido), 0)) as disponible')
                )
                ->whereIn('ed.id_material', $materiales)
                ->groupBy('ed.id_material')
                ->havingRaw('disponible > 0')
                ->orderBy('ed.id_material')
                ->get();

            if ($listado->isEmpty()) {
                return '';
            }

            $output = '<ul class="dropdown-menu" style="display:block; position:relative; overflow:auto; max-height:300px; width:800px">';

            foreach ($listado as $row) {

                $infoMaterial = Materiales::with(['unidadMedida'])
                    ->find($row->id_material);

                if (!$infoMaterial) continue;

                $nombreCompleto = $infoMaterial->nombre .
                    " (" . optional($infoMaterial->unidadMedida)->nombre . ")";

                $output .= '
                    <li class="cursor-pointer" onclick="modificarValor(this)"
                        id="' . $row->id_material . '"
                        data-tipo="material">
                        ' . $nombreCompleto . ' - Disponible: ' . $row->disponible . '
                    </li>
                    <hr>
                ';
            }

            $output .= '</ul>';

            return $output;
        }
    }

    public function infoBodegaMaterialDetalleFila(Request $request)
    {
        $regla = ['id' => 'required'];

        $validar = Validator::make($request->all(), $regla);
        if ($validar->fails()) {
            return ['success' => 0];
        }

        $infoMaterial = Materiales::find($request->id);
        if (!$infoMaterial) {
            return ['success' => 0];
        }

        $infoMedida = UnidadMedida::find($infoMaterial->id_medida);

        $listado = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
            SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
            FROM salidas_detalle
            GROUP BY id_entrada_detalle
        ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->leftJoin('entradas as e', 'e.id', '=', 'ed.id_entradas')
            ->select(
                'ed.id',
                'ed.id_entradas',
                'ed.cantidad_inicial',
                'ed.precio',
                'e.fecha',
                DB::raw('COALESCE(sd.total_salido, 0) as total_salido'),
                DB::raw('(ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as cantidadActual')
            )
            ->where('ed.id_material', $request->id)
            ->havingRaw('cantidadActual > 0')
            ->orderBy('ed.id')
            ->get();

        foreach ($listado as $fila) {
            $fila->fechaIngreso = date("d-m-Y", strtotime($fila->fecha));
            $fila->precioFormat = '$' . number_format($fila->precio, 2, '.', ',');
        }

        $disponible = $listado->isEmpty() ? 1 : 0;

        return [
            'success'        => 1,
            'nombreMaterial' => $infoMaterial->nombre ?? '',
            'nombreMedida'   => $infoMedida->nombre   ?? '',
            'arrayIngreso'   => $listado,
            'disponible'     => $disponible,
        ];
    }

















    public function guardarSalida(Request $request)
    {
        $rules = [
            'fecha'    => 'required',
            'proyecto' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['success' => 0];
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor)) {
            return ['success' => 1];
        }

        DB::beginTransaction();

        try {

            // Validar cantidades antes de guardar
            foreach ($contenedor as $i => $item) {
                $idEntradaDetalle = $item['infoIdEntradaDeta'];
                $cantidadSalida   = (int) $item['infoCantidad'];

                $disponible = DB::table('entradas_detalle as ed')
                    ->leftJoin(
                        DB::raw('(
                        SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                        FROM salidas_detalle
                        GROUP BY id_entrada_detalle
                    ) as sd'),
                        'sd.id_entrada_detalle', '=', 'ed.id'
                    )
                    ->where('ed.id', $idEntradaDetalle)
                    ->selectRaw('(ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as disponible')
                    ->value('disponible');

                if (is_null($disponible) || $cantidadSalida > $disponible) {
                    DB::rollback();
                    return ['success' => 2, 'fila' => $i + 1];
                }
            }

            // Guardar cabecera en tabla salidas
            $salida = new Salidas();
            $salida->fecha           = $request->fecha;
            $salida->descripcion     = $request->descripcion;
            $salida->id_tipoproyecto = $request->proyecto;
            $salida->save();

            // Guardar detalle en salidas_detalle
            foreach ($contenedor as $item) {
                $detalle = new SalidasDetalle();
                $detalle->id_salida          = $salida->id;
                $detalle->id_entrada_detalle = $item['infoIdEntradaDeta'];
                $detalle->cantidad_salida    = (int) $item['infoCantidad'];
                $detalle->save();
            }

            DB::commit();
            return ['success' => 10];

        } catch (\Throwable $e) {
            Log::error('guardarSalida: ' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }





    // *****************************

    public function indexTransferencias(){

        // LISTADO DE PROYECTOS (MENOS EL ID 1 YA QUE SERA EL INVENTARIO GENERAL)
        // Y QUE NO HAYAN SIDO TRANSFERIDOS

        $tipoproyecto = TipoProyecto::orderBy('nombre')
            ->where('id', '!=', 1)
            ->where('transferido', '!=', 1)
            ->get();

        return view('backend.admin.repuestos.registros.vistatransferidos', compact('tipoproyecto'));
    }



    public function geenrarSalidaTransferencia(Request $request)
    {
        $rules = ['fecha' => 'required'];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['success' => 0];
        }

        DB::beginTransaction();

        try {

            // Evitar transferencia doble
            if (TipoProyecto::where('id', $request->idproyecto)->where('transferido', 1)->first()) {
                return ['success' => 1];
            }

            // Marcar como transferido
            TipoProyecto::where('id', $request->idproyecto)->update(['transferido' => 1]);

            // Manejar documento opcional
            $nomDocumento = null;
            if ($request->hasFile('documento')) {
                $cadena       = Str::random(15);
                $tiempo       = microtime();
                $nombre       = str_replace(' ', '_', $cadena . $tiempo);
                $extension    = '.' . $request->documento->getClientOriginalExtension();
                $nomDocumento = $nombre . strtolower($extension);
                $avatar       = $request->file('documento');
                $guardado     = Storage::disk('archivos')->put($nomDocumento, \File::get($avatar));

                if (!$guardado) {
                    DB::rollback();
                    return ['success' => 99];
                }
            }

            // Obtener todas las filas de entradas_detalle con disponible > 0 para ese proyecto
            $listado = DB::table('entradas_detalle as ed')
                ->leftJoin(
                    DB::raw('(
                    SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                    FROM salidas_detalle
                    GROUP BY id_entrada_detalle
                ) as sd'),
                    'sd.id_entrada_detalle', '=', 'ed.id'
                )
                ->join('entradas as e', 'e.id', '=', 'ed.id_entradas')
                ->where('e.id_tipoproyecto', $request->idproyecto)
                ->selectRaw('
                ed.id,
                ed.id_material,
                ed.precio,
                (ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as disponible
            ')
                ->havingRaw('disponible > 0')
                ->get();

            if ($listado->isEmpty()) {
                DB::rollback();
                return ['success' => 2];
            }

            // Cabecera en salidas para descontar inventario del proyecto origen
            $salidaTransf                   = new Salidas();
            $salidaTransf->fecha            = $request->fecha;
            $salidaTransf->descripcion      = $request->descripcion;
            $salidaTransf->id_tipoproyecto  = $request->idproyecto;
            $salidaTransf->es_transferencia = 1;
            $salidaTransf->save();

            // Cabecera transferencia
            $transferencia                  = new Transferencia();
            $transferencia->fecha           = $request->fecha;
            $transferencia->descripcion     = $request->descripcion;
            $transferencia->id_tipoproyecto = $request->idproyecto;
            $transferencia->documento       = $nomDocumento;
            $transferencia->save();

            // Cabecera entrada en bodega general (id_tipoproyecto = 1)
            $entrada                             = new Entradas();
            $entrada->id_tipoproyecto            = 1;
            $entrada->fecha                      = $request->fecha;
            $entrada->descripcion                = 'Transferencia desde proyecto #' . $request->idproyecto;
            $entrada->es_transferencia           = 1;
            $entrada->id_tipoproyecto_transferencia = $request->idproyecto;
            $entrada->save();

            foreach ($listado as $fila) {

                // Detalle transferencia
                $detalle                          = new TransferenciaDetalle();
                $detalle->id_transferencia        = $transferencia->id;
                $detalle->id_entrada_detalle      = $fila->id;
                $detalle->cantidad_transferencia  = $fila->disponible;
                $detalle->id_tipoproyecto_destino = 1;
                $detalle->save();

                // Descontar del proyecto origen via salidas_detalle
                $salida                     = new SalidasDetalle();
                $salida->id_salida          = $salidaTransf->id;
                $salida->id_entrada_detalle = $fila->id;
                $salida->cantidad_salida    = $fila->disponible;
                $salida->save();

                // Reingresar a bodega general via entradas_detalle
                $entradaDetalle                  = new EntradasDetalle();
                $entradaDetalle->id_entradas     = $entrada->id;
                $entradaDetalle->id_material     = $fila->id_material;
                $entradaDetalle->cantidad_inicial = $fila->disponible;
                $entradaDetalle->precio          = $fila->precio;
                $entradaDetalle->save();
            }

            DB::commit();
            return ['success' => 3];

        } catch (\Throwable $e) {
            Log::error('geenrarSalidaTransferencia: ' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }


}
