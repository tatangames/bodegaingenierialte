<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\TipoProyecto;
use App\Models\TransferenciaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HistorialController extends Controller
{

    public function indexHistorialEntradas()
    {
        return view('backend.admin.historial.entradas.vistahistorialentradas');
    }

    public function tablaHistorialEntradas()
    {
        $arrayEntradas = Entradas::with([
            'tipoproyecto',
            'tipoproyectoTransferencia'
        ])
            ->orderBy('fecha', 'desc')
            ->get();

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

        // 1. IDs de los detalles de esta entrada
        $idsDetalle = $entrada->detalle()->pluck('id');

        if ($idsDetalle->isNotEmpty()) {

            // 2. IDs de transferencias afectadas (antes de borrar sus detalles)
            $idsTransferencia = \App\Models\TransferenciaDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                ->pluck('id_transferencia')
                ->unique();

            // 3. Borrar transferencia_detalle que apuntan a estos entradas_detalle
            \App\Models\TransferenciaDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

            // 4. Borrar las transferencias que quedaron sin detalles
            if ($idsTransferencia->isNotEmpty()) {
                \App\Models\Transferencia::whereIn('id', $idsTransferencia)->delete();
            }

            // 5. Ahora sí borrar entradas_detalle
            $entrada->detalle()->delete();
        }

        // 6. Finalmente borrar la entrada
        $entrada->delete();

        return response()->json(['success' => 1]);
    }






    public function indexHistorialRepuestosSalida(){

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





}
