<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $idsDetalle = $entrada->detalle()->pluck('id');

        if ($idsDetalle->isNotEmpty()) {

            // 4. IDs de salidas afectadas ANTES de borrar sus detalles
            $idsSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                ->pluck('id_salida')
                ->unique();

            // 5. Borrar salidas_detalle que apuntan a estos entradas_detalle
            SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

            // 6. Borrar salidas que quedaron sin ningún detalle
            if ($idsSalidas->isNotEmpty()) {
                $salidasHuerfanas = Salidas::whereIn('id', $idsSalidas)
                    ->whereDoesntHave('detalle')
                    ->pluck('id');

                if ($salidasHuerfanas->isNotEmpty()) {
                    Salidas::whereIn('id', $salidasHuerfanas)->delete();
                }
            }

            // 7. Borrar entradas_detalle
            $entrada->detalle()->delete();
        }

        // 8. Borrar la entrada
        $entrada->delete();

        return response()->json(['success' => 1]);
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
                    'material'       => $item->material->nombre ?? '',
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

        $salida->fecha       = $request->fecha;
        $salida->descripcion = $request->descripcion ?: null;
        $salida->save();

        return response()->json(['success' => 1]);
    }

    public function eliminarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        // salidas_detalle apunta a salidas, hay que borrarla primero
        $salida->detalle()->delete();
        $salida->delete();

        return response()->json(['success' => 1]);
    }

    public function detalleSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $detalle = $salida->detalle()
            ->with('entradaDetalle.material')
            ->get()
            ->map(function ($item) {
                return [
                    'codigo'         => $item->entradaDetalle->id_material ?? '',
                    'material'       => $item->entradaDetalle->material->nombre ?? '',
                    'cantidad_salida'=> $item->cantidad_salida,
                    'precio'         => number_format($item->entradaDetalle->precio, 4),
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
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
        $salida = Salidas::find($request->id_salida);

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

        // Misma validación que el guardado original
        foreach ($contenedor as $index => $item) {
            $entradasDetalle = EntradasDetalle::find($item['infoIdEntradaDeta']);

            if (!$entradasDetalle) {
                return response()->json(['success' => 2, 'fila' => $index + 1]);
            }

            // Calcular cantidad disponible actual
            $totalSalido = SalidasDetalle::where('id_entrada_detalle', $entradasDetalle->id)
                ->sum('cantidad_salida');

            $disponible = $entradasDetalle->cantidad_inicial - $totalSalido;

            if ($item['infoCantidad'] > $disponible) {
                return response()->json(['success' => 2, 'fila' => $index + 1]);
            }
        }

        foreach ($contenedor as $item) {
            SalidasDetalle::create([
                'id_salida'          => $salida->id,
                'id_entrada_detalle' => $item['infoIdEntradaDeta'],
                'cantidad_salida'    => $item['infoCantidad'],
            ]);
        }

        return response()->json(['success' => 10]);
    }





    /*public function indexHistorialRepuestosSalida(){

        return view('backend.admin.historial.salidarepuesto.vistasalidarepuesto');
    }


    public function tablaHistorialRepuestosSalida()
    {
        $lista = Salidas::with('tipoproyecto')
            ->orderBy('fecha', 'DESC')
            ->get()
            ->map(function ($dato) {
                $dato->fechaFormato = Carbon::parse($dato->fecha)->format('d-m-Y');
                $dato->nomproy      = optional($dato->tipoproyecto)->nombre ?? '-';
                return $dato;
            });

        return view('backend.admin.historial.salidarepuesto.tablasalidarepuesto', compact('lista'));
    }

    public function detalleHistorialSalida($id)
    {
        $salida = Salidas::with('tipoproyecto')->findOrFail($id);

        return view('backend.admin.historial.salidarepuesto.detalle', compact('salida'));
    }

    public function tablaDetalleHistorialSalida($id)
    {
        $lista = DB::table('salidas_detalle as sd')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->where('sd.id_salida', $id)
            ->select(
                'm.nombre as nommaterial',
                'um.nombre as medida',
                'sd.cantidad_salida',
                'ed.precio'
            )
            ->get()
            ->map(function ($fila) {
                $fila->precioFormat = '$' . number_format($fila->precio, 2, '.', ',');
                return $fila;
            });

        return view('backend.admin.historial.salidarepuesto.tabladetalle', compact('lista'));
    }
*/




}
