<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Reserva;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HistorialController extends Controller
{

    public function indexHistorialEntradas()
    {
        $arrayProyectos = TipoProyecto::orderBy('nombre')->get(); // ajusta el modelo si es diferente

        return view('backend.admin.historial.entradas.vistahistorialentradas',
            compact('arrayProyectos'));
    }

    public function tablaHistorialEntradas(Request $request)
    {
        $arrayEntradas = Entradas::with([
            'tipoproyecto',
            'tipoproyectoTransferencia'
        ])
            ->when($request->proyecto, fn($q) =>
            $q->where('id_tipoproyecto', $request->proyecto)
            )
            ->when($request->fecha_desde, fn($q) =>
            $q->whereDate('fecha', '>=', $request->fecha_desde)
            )
            ->when($request->fecha_hasta, fn($q) =>
            $q->whereDate('fecha', '<=', $request->fecha_hasta)
            )
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                $item->fecha_fmt = date('d/m/Y', strtotime($item->fecha));
                return $item;
            });

        return view('backend.admin.historial.entradas.tablahistorialentradas',
            compact('arrayEntradas'));
    }

    public function informacionEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'entrada' => [
                'id'          => $entrada->id,
                'fecha'       => $entrada->fecha,   // YYYY-MM-DD directo para el input type="date"
                'factura'     => $entrada->factura,
                'descripcion' => $entrada->descripcion,
            ]
        ]);
    }

    public function editarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $entrada->fecha       = $request->fecha;
        $entrada->factura     = $request->factura     ?: null;
        $entrada->descripcion = $request->descripcion ?: null;
        $entrada->save();

        return response()->json(['success' => 1]);
    }


    public function eliminarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        DB::beginTransaction();

        try {

            // ──────────────────────────────────────────────────────────
            // BLOQUEO: la entrada es DESTINO de una transferencia
            // ──────────────────────────────────────────────────────────
            $esDestinoTransferencia = Transferencia::where('id_entrada', $entrada->id)
                ->exists();

            if ($esDestinoTransferencia) {
                DB::rollback();
                return response()->json([
                    'success' => 3,
                    'msg'     => 'Esta entrada proviene de una transferencia. '
                        . 'Elimínela desde el Historial de Transferencias.',
                ]);
            }

            $idsDetalle = $entrada->detalle()->pluck('id');

            if ($idsDetalle->isNotEmpty()) {

                // ──────────────────────────────────────────────────────
                // BLOQUEO: tiene salidas NORMALES registradas
                // ──────────────────────────────────────────────────────
                // Solo bloqueamos salidas reales (es_transferencia = false).
                // Las salidas de transferencia se limpian en cascada más abajo.
                $tieneSalidasNormales = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                    ->whereHas('salida', fn($q) => $q->where('es_transferencia', false))
                    ->exists();

                if ($tieneSalidasNormales) {
                    DB::rollback();
                    return response()->json([
                        'success' => 4,
                        'msg'     => 'Esta entrada tiene salidas registradas. '
                            . 'No se puede eliminar.',
                    ]);
                }

                // ──────────────────────────────────────────────────────
                // BLOQUEO: reservas ya despachadas
                // ──────────────────────────────────────────────────────
                $reservas       = Reserva::whereIn('id_entrada_detalle', $idsDetalle)->get();
                $hayDespachadas = $reservas->where('despachado', 1)->count() > 0;

                if ($hayDespachadas) {
                    DB::rollback();
                    return response()->json([
                        'success' => 2,
                        'msg'     => 'Esta entrada tiene reservas ya despachadas. '
                            . 'No se puede eliminar.',
                    ]);
                }

                // Borrar reservas pendientes
                Reserva::whereIn('id_entrada_detalle', $idsDetalle)
                    ->where('despachado', 0)
                    ->delete();

                // ──────────────────────────────────────────────────────
                // SALIDAS DE TRANSFERENCIA huérfanas (limpiar en cascada)
                // ──────────────────────────────────────────────────────
                $idsSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                    ->pluck('id_salida')
                    ->unique();

                SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

                if ($idsSalidas->isNotEmpty()) {
                    $salidasHuerfanas = Salidas::whereIn('id', $idsSalidas)
                        ->whereDoesntHave('detalle')
                        ->pluck('id');

                    if ($salidasHuerfanas->isNotEmpty()) {
                        Salidas::whereIn('id', $salidasHuerfanas)->delete();
                    }
                }

                // TransferenciaDetalle huérfano
                TransferenciaDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

                // Borrar entradas_detalle
                $entrada->detalle()->delete();
            }

            $entrada->delete();

            DB::commit();
            return response()->json(['success' => 1]);

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('eliminarEntrada: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }

    public function detalleEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $detalle = $entrada->detalle()
            ->with('material')
            ->get()
            ->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'codigo'         => $item->codigo ?? '',
                    'marca'            => $item->material->codigo ?? '',
                    'material'       => $item->material->nombre ?? '',
                    'unidad_medida'   => $item->material->unidadMedida->nombre ?? '—',  // ← nuevo
                    'cantidad_inicial'=> $item->cantidad_inicial,
                    'precio'         => number_format($item->precio, 4),
                    'precio_raw'     => $item->precio,  // sin formato para el input
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }

    public function editarDetalleEntrada(Request $request)
    {
        $detalle = EntradasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        $detalle->codigo = $request->codigo ?: null;
        $detalle->precio = $request->precio;
        $detalle->save();

        return response()->json(['success' => 1]);
    }


    public function eliminarDetalleEntrada(Request $request)
    {
        $detalle = EntradasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        // 1) Cargar entrada y proyecto
        $entrada = Entradas::find($detalle->id_entradas);
        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $proyecto = TipoProyecto::find($entrada->id_tipoproyecto);
        if (!$proyecto) {
            return response()->json(['success' => 0]);
        }

        // 2) Bloquear si el proyecto está cerrado (transferido)
        if ($proyecto->transferido) {
            return response()->json([
                'success' => 2,
                'msg' => 'No se puede eliminar: el proyecto está cerrado.'
            ]);
        }

        // 3) Bloquear si la entrada es destino de una transferencia
        //    (no debería editarse desde aquí, sino desde el historial de transferencias)
        if ($entrada->es_transferencia) {
            return response()->json([
                'success' => 3,
                'msg' => 'Este material proviene de una transferencia. Elimínelo desde el Historial de Transferencias.'
            ]);
        }

        // 4) Bloquear si el detalle tiene salidas registradas
        $tieneSalidas = SalidasDetalle::where('id_entrada_detalle', $detalle->id)->exists();
        if ($tieneSalidas) {
            return response()->json([
                'success' => 4,
                'msg' => 'Este material ya tiene salidas registradas y no puede eliminarse.'
            ]);
        }

        // 5) Bloquear si el detalle tiene reservas
        $tieneReservas = DB::table('reservas')
            ->where('id_entrada_detalle', $detalle->id)
            ->exists();
        if ($tieneReservas) {
            return response()->json([
                'success' => 5,
                'msg' => 'Este material tiene reservas asociadas y no puede eliminarse.'
            ]);
        }

        // 6) Bloquear si el detalle fue incluido en una transferencia (proyecto cerrado en el pasado)
        $estaEnTransferencia = DB::table('transferencia_detalle')
            ->where('id_entrada_detalle', $detalle->id)
            ->exists();
        if ($estaEnTransferencia) {
            return response()->json([
                'success' => 6,
                'msg' => 'Este material está incluido en una transferencia y no puede eliminarse.'
            ]);
        }

        // 7) Eliminar
        DB::beginTransaction();
        try {
            $entradaId = $detalle->id_entradas;
            $detalle->delete();

            // Si era el último detalle de la entrada, eliminar también la cabecera
            $quedan = EntradasDetalle::where('id_entradas', $entradaId)->count();

            if ($quedan === 0) {
                Entradas::where('id', $entradaId)->delete();
                DB::commit();
                return response()->json([
                    'success'         => 1,
                    'entrada_borrada' => true
                ]);
            }

            DB::commit();
            return response()->json([
                'success'         => 1,
                'entrada_borrada' => false
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => 99,
                'msg' => 'Error al eliminar: ' . $e->getMessage()
            ]);
        }
    }


    public function vistaExtrasEntrada($id)
    {
        $entrada = Entradas::with('tipoproyecto')->find($id);

        if (!$entrada || $entrada->tipoproyecto->transferido == 1) {
            return redirect()->route('admin.historial.entradas.index')
                ->with('error', 'El proyecto está cerrado, no se pueden agregar extras');
        }

        return view('backend.admin.historial.entradas.vistaextras', compact('entrada'));
    }

    public function guardarExtrasEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id_entrada);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        // Verificar que el proyecto no esté cerrado
        if ($entrada->tipoproyecto->transferido == 1) {
            return response()->json(['success' => 1, 'mensaje' => 'El proyecto está cerrado']);
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor)) {
            return response()->json(['success' => 0]);
        }

        foreach ($contenedor as $item) {
            EntradasDetalle::create([
                'id_entradas'      => $entrada->id,
                'id_material'      => $item['idMaterial'],
                'cantidad_inicial' => $item['infoCantidad'],
                'codigo'           => $item['infoCodigo'] ?: null,
                'precio'           => $item['infoPrecio'],
            ]);
        }

        return response()->json(['success' => 2]);
    }

    //***** ========================================================================================= **********


    public function indexHistorialSalidas()
    {
        $arrayProyectos = TipoProyecto::orderBy('nombre')->get();

        return view('backend.admin.historial.salidas.vistahistorialsalidas',
            compact('arrayProyectos'));
    }

    public function tablaHistorialSalidas(Request $request)
    {
        // Sin filtros -> trae todos los resultados. La única protección
        // real contra la carga automática al entrar a la pantalla vive en
        // el front-end: esta ruta ya no se llama al cargar la vista, solo
        // cuando el usuario presiona "Filtrar" (con o sin filtros llenos).

        $arraySalidas = Salidas::with('tipoproyecto')
            ->when($request->proyecto, fn($q) =>
            $q->where('id_tipoproyecto', $request->proyecto)
            )
            ->when($request->fecha_desde, fn($q) =>
            $q->whereDate('fecha', '>=', $request->fecha_desde)
            )
            ->when($request->fecha_hasta, fn($q) =>
            $q->whereDate('fecha', '<=', $request->fecha_hasta)
            )
            // ── Filtro por material ──────────────────────────────
            ->when($request->material, function ($q) use ($request) {
                $busqueda = '%' . $request->material . '%';
                $q->whereHas('detalles.entradaDetalle.material', function ($q2) use ($busqueda) {
                    $q2->where('nombre', 'LIKE', $busqueda)
                        ->orWhere('codigo', 'LIKE', $busqueda);
                });
            })
            // ────────────────────────────────────────────────────
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                $item->fecha_fmt = date('d/m/Y', strtotime($item->fecha));
                return $item;
            });

        return view('backend.admin.historial.salidas.tablahistorialsalidas',
            compact('arraySalidas'));
    }


    public function informacionSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'salida'  => [
                'id'          => $salida->id,
                'fecha'       => $salida->fecha,
                'descripcion' => $salida->descripcion,
            ]
        ]);
    }


    public function editarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        // ── La nueva fecha no puede ser anterior al ingreso de ningún ítem ──
        $entradaConflicto = DB::table('salidas_detalle as sd')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('entradas as e',          'e.id',  '=', 'ed.id_entradas')
            ->join('materiales as m',        'm.id',  '=', 'ed.id_material')
            ->where('sd.id_salida', $salida->id)
            ->where('e.fecha', '>', $request->fecha)
            ->orderBy('e.fecha', 'desc')
            ->select('m.nombre as nombre_material', 'e.fecha as fecha_ingreso')
            ->first();

        if ($entradaConflicto) {
            return response()->json([
                'success'         => 2,
                'nombre_material' => $entradaConflicto->nombre_material,
                'fecha_salida'    => Carbon::parse($request->fecha)->format('d-m-Y'),
                'fecha_ingreso'   => Carbon::parse($entradaConflicto->fecha_ingreso)->format('d-m-Y'),
            ]);
        }

        // ── La nueva fecha no puede ser posterior al primer reingreso de esta salida ──
        $primerReingreso = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->where('sd.id_salida', $salida->id)
            ->min('r.fecha');

        if ($primerReingreso && Carbon::parse($request->fecha)->gt(Carbon::parse($primerReingreso))) {
            return response()->json([
                'success'        => 3,
                'mensaje'        => 'La fecha de la salida no puede ser posterior a su primer reingreso ('
                    . Carbon::parse($primerReingreso)->format('d-m-Y') . ').',
            ]);
        }

        $salida->fecha       = $request->fecha;
        $salida->descripcion = $request->descripcion ?: null;
        $salida->save();

        return response()->json(['success' => 1]);
    }



    public function eliminarSalida(Request $request)
    {
        DB::beginTransaction();

        try {
            // Bloquea la salida mientras se valida y se borra
            $salida = Salidas::with('tipoproyecto')
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            if (!$salida) {
                DB::rollBack();
                return response()->json(['success' => 0]);
            }

            // 1) El proyecto debe existir y estar abierto
            if (!$salida->tipoproyecto || $salida->tipoproyecto->transferido) {
                DB::rollBack();
                return response()->json([
                    'success' => 2,
                    'mensaje' => 'No se puede eliminar porque el proyecto ya fue transferido.',
                ]);
            }

            // 2) No debe pertenecer a una transferencia
            $tieneTransferencia = $salida->es_transferencia
                || $salida->id_tipoproyecto_transferencia
                || Transferencia::where('id_salida', $salida->id)->exists();

            if ($tieneTransferencia) {
                DB::rollBack();
                return response()->json([
                    'success' => 2,
                    'mensaje' => 'No se puede eliminar porque esta salida pertenece a una transferencia. '
                        . 'Elimínela desde el Historial de Transferencias.',
                ]);
            }

            // 3) No debe provenir del despacho de una reserva
            if (Reserva::where('id_salida', $salida->id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => 2,
                    'mensaje' => 'No se puede eliminar porque esta salida proviene del despacho de una reserva.',
                ]);
            }

            // 4) Ninguna línea debe tener reingresos
            $tieneReingresos = DB::table('salidas_detalle_reingreso as r')
                ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
                ->where('sd.id_salida', $salida->id)
                ->exists();

            if ($tieneReingresos) {
                DB::rollBack();
                return response()->json([
                    'success' => 2,
                    'mensaje' => 'No se puede eliminar porque esta salida tiene reingresos registrados.',
                ]);
            }

            // salidas_detalle apunta a salidas, hay que borrarla primero
            $salida->detalle()->delete();
            $salida->delete();

            DB::commit();
            return response()->json(['success' => 1]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('eliminarSalida: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }

    public function detalleSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $items = $salida->detalle()
            ->with('entradaDetalle.material.unidadMedida')
            ->get();

        // Reingresos de todas las líneas en una sola consulta
        $reingresos = DB::table('salidas_detalle_reingreso')
            ->whereIn('id_salida_detalle', $items->pluck('id'))
            ->orderBy('fecha')
            ->get()
            ->groupBy('id_salida_detalle');

        $detalle = $items->map(function ($item) use ($reingresos) {
            $lista = $reingresos->get($item->id, collect());

            return [
                'id'              => $item->id,
                'codigo'          => $item->entradaDetalle->id_material ?? '',
                'material'        => $item->entradaDetalle->material->nombre ?? '',
                'unidad'          => $item->entradaDetalle->material->unidadMedida->nombre ?? '',
                'cantidad_salida' => $item->cantidad_salida,
                'precio'          => number_format($item->entradaDetalle->precio, 4),

                // Nuevo: reingresos de la línea
                'reingresado'     => (int) $lista->sum('cantidad'),
                'reingresos'      => $lista->map(fn($r) => [
                    'fecha'         => date('d/m/Y', strtotime($r->fecha)),
                    'cantidad'      => $r->cantidad,
                    'justificacion' => $r->justificacion,
                    'archivo'       => $r->archivo ? asset('storage/' . $r->archivo) : null,
                ])->values(),
            ];
        });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }



    public function eliminarDetalleSalida(Request $request)
    {
        $salida = Salidas::with('tipoproyecto')->find($request->id_salida);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        // 1) El proyecto debe existir y estar activo (sin transferir)
        if (!$salida->tipoproyecto || $salida->tipoproyecto->transferido) {
            return response()->json([
                'success' => 2,
                'mensaje' => 'No se puede eliminar porque el proyecto ya fue transferido.',
            ]);
        }

        // 2) La salida no debe estar relacionada a una transferencia
        $tieneTransferencia = $salida->id_tipoproyecto_transferencia
            || Transferencia::where('id_salida', $salida->id)->exists();

        if ($tieneTransferencia) {
            return response()->json([
                'success' => 2,
                'mensaje' => 'No se puede eliminar porque esta salida pertenece a una transferencia.',
            ]);
        }

        // 3) La línea no debe tener reingresos
        $lineaConReingreso = DB::table('salidas_detalle_reingreso')
            ->where('id_salida_detalle', $request->id_detalle)
            ->exists();

        if ($lineaConReingreso) {
            return response()->json([
                'success' => 2,
                'mensaje' => 'No se puede eliminar porque este material tiene reingresos registrados.',
            ]);
        }

        DB::beginTransaction();

        try {
            $fila = $salida->detalle()->where('id', $request->id_detalle)->first();

            if (!$fila) {
                DB::rollBack();
                return response()->json(['success' => 0]);
            }

            $fila->delete();

            // Si era el último item, se elimina también la salida
            $restantes       = $salida->detalle()->count();
            $salidaEliminada = false;

            if ($restantes === 0) {
                $salida->delete();
                $salidaEliminada = true;
            }

            DB::commit();

            return response()->json([
                'success'          => 1,
                'restantes'        => $restantes,
                'salida_eliminada' => $salidaEliminada,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('eliminarDetalleSalida: ' . $e->getMessage());
            return response()->json(['success' => 0]);
        }
    }



    public function vistaExtrasSalida($id)
    {
        $salida = Salidas::with('tipoproyecto')->find($id);

        if (!$salida || $salida->tipoproyecto->transferido == 1) {
            return redirect()->route('admin.historial.salidas.index')
                ->with('error', 'El proyecto está cerrado, no se pueden agregar extras');
        }

        return view('backend.admin.historial.salidas.vistaextrassalidas', compact('salida'));
    }

    public function guardarExtrasSalida(Request $request)
    {
        $salida = Salidas::with('tipoproyecto')->find($request->id_salida);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        if ($salida->tipoproyecto->transferido == 1) {
            return response()->json(['success' => 0, 'mensaje' => 'El proyecto está cerrado']);
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor)) {
            return response()->json(['success' => 0]);
        }

        // Agrupar por lote para validar bien si se repite el mismo material
        $agrupado = [];
        foreach ($contenedor as $item) {
            $id = $item['infoIdEntradaDeta'];
            $agrupado[$id] = ($agrupado[$id] ?? 0) + (int) $item['infoCantidad'];
        }

        DB::beginTransaction();

        try {
            $fila = 1;

            foreach ($agrupado as $idEntradaDetalle => $cantidad) {

                // Bloquea el lote mientras se valida y se guarda
                $entradasDetalle = EntradasDetalle::where('id', $idEntradaDetalle)
                    ->lockForUpdate()
                    ->first();

                if (!$entradasDetalle) {
                    DB::rollBack();
                    return response()->json(['success' => 2, 'fila' => $fila]);
                }

                // Disponible = inicial - (salidas - reingresos)
                $totalSalido = SalidasDetalle::where('id_entrada_detalle', $entradasDetalle->id)
                    ->sum('cantidad_salida');

                $reingresado = DB::table('salidas_detalle_reingreso as r')
                    ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
                    ->where('sd.id_entrada_detalle', $entradasDetalle->id)
                    ->sum('r.cantidad');

                $disponible = $entradasDetalle->cantidad_inicial - ($totalSalido - $reingresado);

                if ($cantidad > $disponible) {
                    DB::rollBack();
                    return response()->json(['success' => 2, 'fila' => $fila]);
                }

                $fila++;
            }

            foreach ($agrupado as $idEntradaDetalle => $cantidad) {
                SalidasDetalle::create([
                    'id_salida'          => $salida->id,
                    'id_entrada_detalle' => $idEntradaDetalle,
                    'cantidad_salida'    => $cantidad,
                ]);
            }

            DB::commit();
            return response()->json(['success' => 10]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('guardarExtrasSalida: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }













}
