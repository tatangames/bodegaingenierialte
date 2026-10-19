<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\SalidaDetalleReingreso;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReingresoController extends Controller
{

    public function index()
    {
        $proyectos = DB::table('tipoproyecto')
            ->where('transferido', 0)
            ->orderBy('nombre')
            ->get();

        return view('backend.admin.repuestos.reingreso.vistareingreso', compact('proyectos'));
    }

    public function tabla($idproyecto)
    {
        // Si el proyecto ya fue transferido, no se muestra nada
        $abierto = DB::table('tipoproyecto')
            ->where('id', $idproyecto)
            ->where('transferido', 0)
            ->exists();

        if (!$abierto) {
            $lista = collect();
            return view('backend.admin.repuestos.reingreso.tablareingreso', compact('lista'));
        }

        $lista = DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->where('s.id_tipoproyecto', $idproyecto)
            ->where('s.es_transferencia', 0) // las transferencias a otro proyecto no se reingresan
            ->select(
                'sd.id',
                'sd.cantidad_salida',
                's.id as id_salida',
                's.fecha',
                's.descripcion',
                'um.nombre as medida',
                DB::raw('COALESCE(ed.nombre, m.nombre) as material'),
                DB::raw('(SELECT COALESCE(SUM(r.cantidad), 0)
                          FROM salidas_detalle_reingreso r
                          WHERE r.id_salida_detalle = sd.id) as reingresado')
            )
            ->orderByDesc('s.fecha')
            ->orderByDesc('sd.id')
            ->get();

        foreach ($lista as $fila) {
            $fila->fecha     = date('d-m-Y', strtotime($fila->fecha));
            $fila->pendiente = $fila->cantidad_salida - $fila->reingresado;
        }

        return view('backend.admin.repuestos.reingreso.tablareingreso', compact('lista'));
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_salida_detalle' => 'required|integer|exists:salidas_detalle,id',
            'cantidad'          => 'required|integer|min:1',
            'fecha'             => 'required|date',
            'justificacion'     => 'required|string|min:5',
            'archivo'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'mensaje' => $validator->errors()->first(),
            ]);
        }

        DB::beginTransaction();

        try {
            // Bloquea la fila para evitar reingresos duplicados simultáneos
            $detalle = DB::table('salidas_detalle')
                ->where('id', $request->id_salida_detalle)
                ->lockForUpdate()
                ->first();

            $yaReingresado = DB::table('salidas_detalle_reingreso')
                ->where('id_salida_detalle', $detalle->id)
                ->sum('cantidad');

            $maximo = $detalle->cantidad_salida - $yaReingresado;

            if ($request->cantidad > $maximo) {
                DB::rollBack();
                return response()->json([
                    'success' => 0,
                    'mensaje' => "Solo se pueden reingresar $maximo unidades como máximo.",
                ]);
            }

            $ruta = null;
            if ($request->hasFile('archivo')) {
                $ruta = $request->file('archivo')->store('reingresos', 'public');
            }

            SalidaDetalleReingreso::create([
                'id_salida_detalle' => $detalle->id,
                'cantidad'          => $request->cantidad,
                'fecha'             => $request->fecha,
                'justificacion'     => $request->justificacion,
                'archivo'           => $ruta,
            ]);

            DB::commit();

            return response()->json(['success' => 1]);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (!empty($ruta)) {
                Storage::disk('public')->delete($ruta);
            }

            report($e);

            return response()->json(['success' => 99, 'mensaje' => 'Error al registrar el reingreso.']);
        }
    }




}
