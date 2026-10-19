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

class HistorialReingresoController extends Controller
{

    public function index()
    {
        $arrayProyectos = TipoProyecto::orderBy('nombre')->get();

        return view('backend.admin.historial.reingreso.vistahistorialreingreso',
            compact('arrayProyectos'));
    }

    public function tabla(Request $request)
    {
        $lista = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('tipoproyecto as tp', 'tp.id', '=', 's.id_tipoproyecto')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->when($request->proyecto, fn($q) =>
            $q->where('s.id_tipoproyecto', $request->proyecto)
            )
            ->when($request->fecha_desde, fn($q) =>
            $q->whereDate('r.fecha', '>=', $request->fecha_desde)
            )
            ->when($request->fecha_hasta, fn($q) =>
            $q->whereDate('r.fecha', '<=', $request->fecha_hasta)
            )
            ->when($request->material, function ($q) use ($request) {
                $busqueda = '%' . $request->material . '%';
                $q->where(function ($q2) use ($busqueda) {
                    $q2->where('m.nombre', 'LIKE', $busqueda)
                        ->orWhere('m.codigo', 'LIKE', $busqueda);
                });
            })
            ->select(
                'r.id',
                'r.fecha',
                'r.cantidad',
                'r.justificacion',
                'r.archivo',
                'r.created_at',
                's.id as id_salida',
                's.fecha as fecha_salida',
                'tp.nombre as proyecto',
                'tp.transferido',
                'um.nombre as medida',
                'ed.precio',
                DB::raw('COALESCE(ed.nombre, m.nombre) as material')
            )
            ->orderByDesc('r.fecha')
            ->orderByDesc('r.id')
            ->get();

        $motivos = $this->motivosBloqueo($lista->pluck('id')->all());

        $totalCantidad = 0;
        $totalDinero   = 0;

        foreach ($lista as $fila) {
            $fila->fecha_fmt        = date('d/m/Y', strtotime($fila->fecha));
            $fila->fecha_salida_fmt = date('d/m/Y', strtotime($fila->fecha_salida));
            $fila->total            = $fila->cantidad * $fila->precio;
            $fila->url_archivo      = $fila->archivo ? asset('storage/' . $fila->archivo) : null;
            $fila->es_pdf           = $fila->archivo
                ? strtolower(pathinfo($fila->archivo, PATHINFO_EXTENSION)) === 'pdf'
                : false;
            $fila->motivo           = $motivos[$fila->id] ?? null;
            $fila->puede_editar     = ((int) $fila->transferido === 0);

            $totalCantidad += $fila->cantidad;
            $totalDinero   += $fila->total;
        }

        return view('backend.admin.historial.reingreso.tablahistorialreingreso',
            compact('lista', 'totalCantidad', 'totalDinero'));
    }


    public function eliminar(Request $request)
    {
        DB::beginTransaction();

        try {
            $reingreso = DB::table('salidas_detalle_reingreso')
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            if (!$reingreso) {
                DB::rollBack();
                return ['success' => 0];
            }

            // Bloquea el lote para que nadie lo use mientras se valida
            $idEntradaDetalle = DB::table('salidas_detalle')
                ->where('id', $reingreso->id_salida_detalle)
                ->value('id_entrada_detalle');

            DB::table('entradas_detalle')
                ->where('id', $idEntradaDetalle)
                ->lockForUpdate()
                ->first();

            // Misma validación que usa la tabla para pintar el botón
            $motivo = $this->motivosBloqueo([$reingreso->id])[$reingreso->id] ?? null;

            if ($motivo) {
                DB::rollBack();
                return ['success' => 2, 'mensaje' => $motivo];
            }

            DB::table('salidas_detalle_reingreso')->where('id', $reingreso->id)->delete();

            DB::commit();

            // El archivo se borra al final: si algo fallara antes, no se pierde
            if ($reingreso->archivo) {
                Storage::disk('public')->delete($reingreso->archivo);
            }

            return ['success' => 1];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('eliminar reingreso: ' . $e->getMessage());
            return ['success' => 99];
        }
    }

    /**
     * Devuelve [id_reingreso => motivo|null]. null = se puede eliminar.
     *
     * Reglas:
     *  1) El proyecto debe estar abierto (no transferido).
     *  2) El stock libre actual debe cubrir lo que este reingreso devolvió;
     *     si ya se usó en otra salida o reserva, borrarlo dejaría el stock negativo.
     */
    private function motivosBloqueo(array $idsReingreso): array
    {
        if (empty($idsReingreso)) {
            return [];
        }

        $rows = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('tipoproyecto as tp', 'tp.id', '=', 's.id_tipoproyecto')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->whereIn('r.id', $idsReingreso)
            ->select(
                'r.id',
                'r.cantidad',
                'tp.transferido',
                'tp.nombre as proyecto',
                'ed.id as id_entrada_detalle',
                'ed.cantidad_inicial'
            )
            ->get();

        $idsEd = $rows->pluck('id_entrada_detalle')->unique()->values();

        $salidas = DB::table('salidas_detalle')
            ->whereIn('id_entrada_detalle', $idsEd)
            ->select('id_entrada_detalle', DB::raw('SUM(cantidad_salida) as total'))
            ->groupBy('id_entrada_detalle')
            ->pluck('total', 'id_entrada_detalle');

        $reingresos = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->whereIn('sd.id_entrada_detalle', $idsEd)
            ->select('sd.id_entrada_detalle', DB::raw('SUM(r.cantidad) as total'))
            ->groupBy('sd.id_entrada_detalle')
            ->pluck('total', 'id_entrada_detalle');

        $reservas = DB::table('reservas')
            ->whereIn('id_entrada_detalle', $idsEd)
            ->where('despachado', 0)
            ->select('id_entrada_detalle', DB::raw('SUM(cantidad) as total'))
            ->groupBy('id_entrada_detalle')
            ->pluck('total', 'id_entrada_detalle');

        $motivos = [];

        foreach ($rows as $row) {

            // Regla 1: proyecto cerrado
            if ($row->transferido == 1) {
                $motivos[$row->id] = 'No se puede eliminar: el proyecto "' . $row->proyecto
                    . '" ya fue cerrado y este reingreso forma parte del cierre.';
                continue;
            }

            // Regla 2: el material reingresado ya se usó
            $libre = $row->cantidad_inicial
                - ((int) ($salidas[$row->id_entrada_detalle] ?? 0)
                    - (int) ($reingresos[$row->id_entrada_detalle] ?? 0))
                - (int) ($reservas[$row->id_entrada_detalle] ?? 0);

            if ($libre - $row->cantidad < 0) {
                $motivos[$row->id] = 'No se puede eliminar: este reingreso devolvió '
                    . $row->cantidad . ' unidad(es) al stock, pero el stock libre actual de ese material es '
                    . max(0, $libre) . '. Ese material ya se usó en otra salida o reserva. '
                    . 'Para poder eliminarlo, primero debe anularse la salida o reserva que lo consumió.';
                continue;
            }

            $motivos[$row->id] = null;
        }

        return $motivos;
    }


    public function informacion(Request $request)
    {
        $reingreso = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('tipoproyecto as tp', 'tp.id', '=', 's.id_tipoproyecto')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->where('r.id', $request->id)
            ->select(
                'r.id',
                'r.fecha',
                'r.cantidad',
                'r.justificacion',
                'r.archivo',
                's.fecha as fecha_salida',
                'tp.transferido',
                DB::raw('COALESCE(ed.nombre, m.nombre) as material')
            )
            ->first();

        if (!$reingreso) {
            return ['success' => 0];
        }

        if ((int) $reingreso->transferido === 1) {
            return [
                'success' => 3,
                'mensaje' => 'No se puede editar: el proyecto ya fue cerrado y este reingreso forma parte del cierre.',
            ];
        }

        return [
            'success'   => 1,
            'reingreso' => [
                'id'            => $reingreso->id,
                'fecha'         => $reingreso->fecha,
                'fecha_salida'  => $reingreso->fecha_salida,
                'cantidad'      => $reingreso->cantidad,
                'material'      => $reingreso->material,
                'justificacion' => $reingreso->justificacion,
                'archivo_url'   => $reingreso->archivo ? asset('storage/' . $reingreso->archivo) : null,
            ],
        ];
    }

    public function editar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'            => 'required|integer',
            'fecha'         => 'required|date',
            'justificacion' => 'required|string|min:5|max:1000',
            'archivo'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return ['success' => 0, 'mensaje' => $validator->errors()->first()];
        }

        DB::beginTransaction();
        $rutaNueva = null;

        try {
            $reingreso = DB::table('salidas_detalle_reingreso')
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            if (!$reingreso) {
                DB::rollBack();
                return ['success' => 0, 'mensaje' => 'El reingreso no existe o ya fue eliminado.'];
            }

            $info = DB::table('salidas_detalle as sd')
                ->join('salidas as s', 's.id', '=', 'sd.id_salida')
                ->join('tipoproyecto as tp', 'tp.id', '=', 's.id_tipoproyecto')
                ->where('sd.id', $reingreso->id_salida_detalle)
                ->select('sd.id_entrada_detalle', 's.fecha as fecha_salida', 'tp.transferido')
                ->first();

            // Regla 1: proyecto cerrado
            if ((int) $info->transferido === 1) {
                DB::rollBack();
                return [
                    'success' => 3,
                    'mensaje' => 'No se puede editar: el proyecto ya fue cerrado y este reingreso forma parte del cierre.',
                ];
            }

            $nuevaFecha = Carbon::parse($request->fecha)->startOfDay();

            // Regla 2: no anterior a la salida de origen
            if ($nuevaFecha->lt(Carbon::parse($info->fecha_salida)->startOfDay())) {
                DB::rollBack();
                return [
                    'success' => 4,
                    'mensaje' => 'La fecha del reingreso no puede ser anterior a la salida de origen ('
                        . Carbon::parse($info->fecha_salida)->format('d-m-Y') . ').',
                ];
            }

            // Regla 3: no futura
            if ($nuevaFecha->gt(Carbon::now('America/El_Salvador')->startOfDay())) {
                DB::rollBack();
                return ['success' => 5, 'mensaje' => 'La fecha del reingreso no puede ser futura.'];
            }

            // Regla 4: al adelantar la fecha, no pasar por encima de otra salida del mismo lote
            $fechaActual = Carbon::parse($reingreso->fecha)->startOfDay();

            if ($nuevaFecha->gt($fechaActual)) {
                $conflicto = DB::table('salidas_detalle as sd2')
                    ->join('salidas as s2', 's2.id', '=', 'sd2.id_salida')
                    ->where('sd2.id_entrada_detalle', $info->id_entrada_detalle)
                    ->where('sd2.id', '!=', $reingreso->id_salida_detalle)
                    ->whereDate('s2.fecha', '>=', $fechaActual->toDateString())
                    ->whereDate('s2.fecha', '<', $nuevaFecha->toDateString())
                    ->orderBy('s2.fecha')
                    ->select('s2.id', 's2.fecha')
                    ->first();

                if ($conflicto) {
                    DB::rollBack();
                    return [
                        'success' => 6,
                        'mensaje' => 'No se puede mover la fecha a esa día: la salida #' . $conflicto->id
                            . ' del ' . Carbon::parse($conflicto->fecha)->format('d-m-Y')
                            . ' usa este mismo material y quedaría registrada antes de que volviera a bodega.',
                    ];
                }
            }

            // Documento: reemplazar, quitar o conservar
            $archivoFinal   = $reingreso->archivo;
            $borrarAnterior = false;

            if ($request->hasFile('archivo')) {
                $rutaNueva      = $request->file('archivo')->store('reingresos', 'public');
                $archivoFinal   = $rutaNueva;
                $borrarAnterior = !empty($reingreso->archivo);
            } elseif ($request->quitar_archivo == 1 && $reingreso->archivo) {
                $archivoFinal   = null;
                $borrarAnterior = true;
            }

            DB::table('salidas_detalle_reingreso')
                ->where('id', $reingreso->id)
                ->update([
                    'fecha'         => $nuevaFecha->toDateString(),
                    'justificacion' => $request->justificacion,
                    'archivo'       => $archivoFinal,
                    'updated_at'    => now(),
                ]);

            DB::commit();

            // El archivo anterior se borra al final: si algo fallara antes, no se pierde
            if ($borrarAnterior) {
                Storage::disk('public')->delete($reingreso->archivo);
            }

            return ['success' => 1];

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($rutaNueva) {
                Storage::disk('public')->delete($rutaNueva);
            }

            Log::error('editar reingreso: ' . $e->getMessage());
            return ['success' => 99];
        }
    }


}
