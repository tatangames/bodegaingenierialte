<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\HistorialEntradas;
use App\Models\HistorialEntradasDeta;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\HistorialTransferido;
use App\Models\HistorialTransferidoDetalle;
use App\Models\Materiales;
use App\Models\TipoProyecto;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function indexEntradaReporte(){
        return view('backend.admin.repuestos.reporte.vistaentradasalidareporte');
    }

    public function reportePdfEntradaSalida($tipo, $desde = null, $hasta = null)
    {
        $sinFiltro = ($desde === 'todos' || $desde === null);

        $start = Carbon::parse($desde)->startOfDay();
        $end   = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $resultsBloque = [];
        $index = 0;

        // ────────────────────────────────────────────
        // ENTRADAS
        // ────────────────────────────────────────────
        if ($tipo == 1) {

            $query = HistorialEntradas::orderBy('fecha', 'ASC');
            if (!$sinFiltro) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $listaEntrada = $query->get();

            foreach ($listaEntrada as $ll) {

                $ll->fecha = date("d-m-Y", strtotime($ll->fecha));

                $infoProyecto   = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();
                $ll->nombreproy = $infoProyecto ? $infoProyecto->nombre : '—';

                array_push($resultsBloque, $ll);

                $listaDetalle = DB::table('historial_entradas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->select('m.nombre', 'ed.cantidad', 'm.id_medida', 'ed.precio', 'ed.codigo')
                    ->where('ed.id_historial', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd) {
                    $info       = UnidadMedida::where('id', $dd->id_medida)->first();
                    $dd->medida = $info ? $info->nombre : '';
                    $dd->total  = $dd->cantidad * $dd->precio;
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Entradas');
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';
            $periodoTexto = $sinFiltro ? 'Todo el historial' : "Fecha: $desdeFormat  -  $hastaFormat";

            $tabla = "
    <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
        <tr>
            <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                <table width='100%'>
                    <tr>
                        <td style='width:35%; text-align:left;'>
                            <img src='{$logoalcaldia}' style='height:40px'>
                        </td>
                        <td style='width:65%; text-align:left; color:#104e8c;
                                    font-size:12px; font-weight:bold; line-height:1.4;'>
                            SANTA ANA NORTE<br>EL SALVADOR
                        </td>
                    </tr>
                </table>
            </td>
            <td style='width:70%; border:1px solid #000;
                        padding:8px; text-align:center; vertical-align:middle;'>
                <h2 style='margin:0;'>Reporte de Entradas</h2>
                <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
            </td>
        </tr>
    </table>
    ";

            foreach ($listaEntrada as $dd) {

                $tabla .= "
        <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
            <tbody>
                <tr>
                    <td style='width:20%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                    <td style='width:45%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                    <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='width:20%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->fecha</td>
                    <td style='width:45%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->nombreproy</td>
                    <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->descripcion</td>
                </tr>
            </tbody>
        </table>

        <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
            <thead>
                <tr>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Repuesto</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                </tr>
            </thead>
            <tbody>
        ";

                $totalGeneral = 0;

                foreach ($dd->detalle as $gg) {
                    $totalGeneral += $gg->total;
                    $precioFormat  = number_format($gg->precio, 2);
                    $totalFormat   = number_format($gg->total,  2);

                    $tabla .= "
                <tr>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->codigo</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->nombre</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->medida</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$gg->cantidad</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                </tr>
            ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 2);

                $tabla .= "
                <tr>
                    <td colspan='5' style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                        Total General:
                    </td>
                    <td style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                        $ $totalGeneralFormat
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        ";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
        else {

            $query = HistorialSalidas::orderBy('fecha', 'ASC');
            if (!$sinFiltro) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $listaSalida = $query->get();

            foreach ($listaSalida as $ll) {

                $infoProyecto   = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();
                $ll->nombreproy = $infoProyecto ? $infoProyecto->nombre : '—';
                $ll->fecha      = date("d-m-Y", strtotime($ll->fecha));

                array_push($resultsBloque, $ll);

                $listaDetalle = DB::table('historial_salidas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->leftJoin('historial_entradas_deta AS hed', 'ed.id_historial_entradas_deta', '=', 'hed.id')
                    ->select(
                        'm.id',
                        'm.nombre',
                        'ed.cantidad',
                        'm.id_medida',
                        'ed.id_historial_salidas',
                        DB::raw('COALESCE(hed.precio, 0) AS precio'),
                        DB::raw('COALESCE(hed.codigo, "") AS codigo')
                    )
                    ->where('ed.id_historial_salidas', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd) {
                    $info       = UnidadMedida::where('id', $dd->id_medida)->first();
                    $dd->medida = $info ? $info->nombre : '';
                    $dd->total  = $dd->cantidad * $dd->precio;
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Salidas');
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';
            $periodoTexto = $sinFiltro ? 'Todo el historial' : "Fecha: $desdeFormat  -  $hastaFormat";

            $tabla = "
    <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
        <tr>
            <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                <table width='100%'>
                    <tr>
                        <td style='width:35%; text-align:left;'>
                            <img src='{$logoalcaldia}' style='height:40px'>
                        </td>
                        <td style='width:65%; text-align:left; color:#104e8c;
                                    font-size:12px; font-weight:bold; line-height:1.4;'>
                            SANTA ANA NORTE<br>EL SALVADOR
                        </td>
                    </tr>
                </table>
            </td>
            <td style='width:70%; border:1px solid #000;
                        padding:8px; text-align:center; vertical-align:middle;'>
                <h2 style='margin:0;'>Reporte de Salidas</h2>
                <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
            </td>
        </tr>
    </table>
    ";

            foreach ($listaSalida as $dd) {

                $tabla .= "
        <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
            <tbody>
                <tr>
                    <td style='width:20%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                    <td style='width:45%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                    <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='width:20%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->fecha</td>
                    <td style='width:45%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->nombreproy</td>
                    <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$dd->descripcion</td>
                </tr>
            </tbody>
        </table>

        <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
            <thead>
                <tr>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Repuesto</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                </tr>
            </thead>
            <tbody>
        ";

                $totalGeneral = 0;

                foreach ($dd->detalle as $gg) {
                    $totalGeneral += $gg->total;
                    $precioFormat  = number_format($gg->precio, 2);
                    $totalFormat   = number_format($gg->total,  2);

                    $tabla .= "
                <tr>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->codigo</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->nombre</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$gg->medida</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$gg->cantidad</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                </tr>
            ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 2);

                $tabla .= "
                <tr>
                    <td colspan='5' style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                        Total General:
                    </td>
                    <td style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                        $ $totalGeneralFormat
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        ";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }

    }




    public function vistaParaReporteInventario(){
        return view('backend.admin.repuestos.reporte.vistareporteinventario');
    }



    public function reporteInventarioActual($tipo)
    {

        DB::transaction(function () {

            // Limpiar tablas nuevas antes de migrar
            DB::table('transferencia_detalle')->delete();
            DB::table('transferencia')->delete();

            $historialTransf = DB::table('historial_transf')->get();

            echo "📦 Transferencias encontradas: " . $historialTransf->count() . "\n";

            $totalDetalles = 0;
            $totalSaltadas = 0;

            foreach ($historialTransf as $ht) {

                $nuevaTransfId = DB::table('transferencia')->insertGetId([
                    'id_tipoproyecto' => $ht->id_tipoproyecto,
                    'fecha'           => $ht->fecha,
                    'descripcion'     => $ht->descripcion,
                    'documento'       => $ht->documento,
                ]);

                $detalles = DB::table('historial_transf_deta')
                    ->where('id_historial_transf', $ht->id)
                    ->get();

                foreach ($detalles as $det) {

                    // Buscar entrada_detalle del mismo material y proyecto (FIFO)
                    // ↓ corregido: tipoproyecto_id en tabla entrada
                    $entradaDetalle = DB::table('entradas_detalle as ed')
                        ->join('entrada as e', 'e.id', '=', 'ed.entradas_id')
                        ->where('e.tipoproyecto_id', $ht->id_tipoproyecto) // 👈 corregido
                        ->where('ed.material_id', $det->id_material)
                        ->orderBy('e.fecha', 'asc')
                        ->select('ed.id')
                        ->first();

                    if (!$entradaDetalle) {
                        \Log::warning("Migración transferencia: no se encontró entrada_detalle para tipoproyecto_id={$ht->id_tipoproyecto}, material_id={$det->id_material}");
                        $totalSaltadas++;
                        continue;
                    }

                    DB::table('transferencia_detalle')->insert([
                        'id_transferencia'        => $nuevaTransfId,
                        'id_entrada_detalle'      => $entradaDetalle->id,
                        'cantidad_transferencia'  => round($det->cantidad),
                        'id_tipoproyecto_destino' => null,
                    ]);

                    $totalDetalles++;
                }

                echo "   ✅ Transferencia id={$ht->id} → {$detalles->count()} detalles migrados\n";
            }

            echo "\n📊 Total detalles migrados: {$totalDetalles}\n";

            if ($totalSaltadas > 0) {
                echo "⚠️  Detalles saltados sin entrada: {$totalSaltadas}\n";
            }

            echo "\n✅ Migración de transferencias completa.\n";
        });
    }



    public function reporteInventarioActual2($tipo){
        // JUNTOS


        // Limpiar primero
       /* DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('salidas_detalle')->delete();
        DB::table('salidas')->delete();
        DB::table('entradas_detalle')->delete();
        DB::table('entrada')->delete();
        DB::statement('ALTER TABLE salidas_detalle AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE salidas AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE entradas_detalle AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE entrada AUTO_INCREMENT = 1');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $tipoproyectos = DB::table('tipoproyecto')->pluck('id');

        foreach ($tipoproyectos as $tipoproyectoId) {

            $materiales = DB::select("
        SELECT
            m.id as material_id,
            COALESCE(e.total_entradas, 0) - COALESCE(s.total_salidas, 0) as stock_neto
        FROM materiales m

        LEFT JOIN (
            SELECT hed.id_material, SUM(hed.cantidad) as total_entradas
            FROM historial_entradas_deta hed
            INNER JOIN historial_entradas he ON he.id = hed.id_historial
            WHERE he.id_tipoproyecto = ?
            GROUP BY hed.id_material
        ) e ON e.id_material = m.id

        LEFT JOIN (
            SELECT hsd.id_material, SUM(hsd.cantidad) as total_salidas
            FROM historial_salidas_deta hsd
            INNER JOIN historial_salidas hs ON hs.id = hsd.id_historial_salidas
            WHERE hs.id_tipoproyecto = ?
            GROUP BY hsd.id_material
        ) s ON s.id_material = m.id

        HAVING stock_neto > 0
    ", [$tipoproyectoId, $tipoproyectoId]);

            if (empty($materiales)) {
                continue;
            }

            $entradaId = DB::table('entrada')->insertGetId([
                'tipoproyecto_id' => $tipoproyectoId,
                'fecha'           => Carbon::today()->toDateString(),
                'descripcion'     => 'Migración inicial',
            ]);

            $detalles = array_map(fn($m) => [
                'entradas_id'      => $entradaId,
                'material_id'      => $m->material_id,
                'cantidad_inicial' => (int) $m->stock_neto,
                'precio'           => 0,
            ], $materiales);

            DB::table('entradas_detalle')->insert($detalles);

            echo "Proyecto {$tipoproyectoId}: entrada #{$entradaId} con " . count($detalles) . " materiales\n";
        }

        echo "Migración completada.\n";*/



        // Obtener todas las salidas viejas
        $historialSalidas = DB::table('historial_salidas')->get();

        foreach ($historialSalidas as $salida) {

            // Crear cabecera en salidas nueva
            $salidaId = DB::table('salidas')->insertGetId([
                'tipoproyecto_id' => $salida->id_tipoproyecto,
                'fecha'           => $salida->fecha,
                'descripcion'     => $salida->descripcion,
            ]);

            // Obtener detalles de esta salida
            $detalles = DB::table('historial_salidas_deta as hsd')
                ->select(
                    'hsd.cantidad',
                    'hsd.id_historial_entradas_deta',
                )
                ->where('hsd.id_historial_salidas', $salida->id)
                ->whereNotNull('hsd.id_historial_entradas_deta')
                ->get();

            if ($detalles->isEmpty()) {
                continue;
            }

            $nuevosDetalles = $detalles->map(fn($d) => [
                'salida_id'          => $salidaId,
                'entrada_detalle_id' => $d->id_historial_entradas_deta,
                'cantidad_salida'    => (int) $d->cantidad,
            ])->toArray();

            DB::table('salidas_detalle')->insert($nuevosDetalles);

            echo "Salida #{$salidaId} (proyecto {$salida->id_tipoproyecto}): " . count($nuevosDetalles) . " detalles\n";
        }

        echo "Migración de salidas completada.\n";



        return



        $tipoproyectoId = 2;

        $materiales = DB::table('materiales as m')
            ->select(
                'm.id',
                'm.nombre',
                DB::raw('COALESCE(SUM(ed.cantidad_inicial), 0) as total_entradas'),
                DB::raw('COALESCE(SUM(sd.cantidad_salida), 0) as total_salidas'),
                DB::raw('COALESCE(SUM(ed.cantidad_inicial), 0) - COALESCE(SUM(sd.cantidad_salida), 0) as stock_actual')
            )
            ->join('entradas_detalle as ed', 'ed.material_id', '=', 'm.id')
            ->join('entradas as e', function ($join) use ($tipoproyectoId) {
                $join->on('e.id', '=', 'ed.entradas_id')
                    ->where('e.tipoproyecto_id', '=', $tipoproyectoId);
            })
            ->leftJoin('salidas_detalle as sd', 'sd.entrada_detalle_id', '=', 'ed.id')
            ->groupBy('m.id', 'm.nombre')
            ->orderBy('m.nombre')
            ->get();










        if($tipo == 1){

            $lista = Materiales::orderBy('nombre', 'ASC')->get();

            $granTotal = 0;

            foreach ($lista as $item) {
                $medida = '';
                if($dataUnidad = UnidadMedida::where('id', $item->id_medida)->first()){
                    $medida = $dataUnidad->nombre;
                }
                $item->medida = $medida;

                $arrayEntradas = Entradas::where('id_material', $item->id)->get();

                $sumatoria    = 0;
                $totalDinero  = 0;
                $precioUnit   = 0;

                foreach ($arrayEntradas as $data){
                    $sumatoria   += $data->cantidad;
                    $totalDinero += ($data->precio * $data->cantidad);
                    $precioUnit   = $data->precio; // último precio registrado
                }

                $granTotal += $totalDinero;

                $item->total         = $sumatoria;
                $item->precioUnit    = number_format($precioUnit, 2);
                $item->totalDinero   = number_format($totalDinero, 2);
            }

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Inventario Actual');
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Inventario de Materiales</h2>
                    <p style='margin:0; font-size:12px;'>TALLER DE ESTRUCTURAS</p>
                </td>
            </tr>
        </table>";

            $tabla .= "<table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:16%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:16%; font-size:13px;'>Total ($)</td>
                </tr>";

            foreach ($lista as $info) {
                if($info->total > 0){
                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$info->codigo</td>
                    <td style='font-size:12px;'>$info->nombre</td>
                    <td style='font-size:12px;'>$info->medida</td>
                    <td style='font-size:12px;'>$info->total</td>
                    <td style='font-size:12px;'>$ $info->precioUnit</td>
                    <td style='font-size:12px;'>$ $info->totalDinero</td>
                </tr>";
                }
            }

            // ── Gran total ──
            $tabla .= "
        <tr>
            <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                    border-top:1.5px solid #000; padding-top:5px;'>
                TOTAL GENERAL:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000;
                        padding-top:5px;'>
                $ $granTotalFmt
            </td>
        </tr>";

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

        } else {
            // SEPARADOS

            $listaProyPrimero = TipoProyecto::orderBy('nombre')
                ->where('transferido', 0)
                ->get();

            $resultsBloque = array();
            $index = 0;
            $pilaArrayId = array();

            foreach ($listaProyPrimero as $infodata){
                $arrayEntradas = Entradas::where('id_tipoproyecto', $infodata->id)->get();
                foreach ($arrayEntradas as $info){
                    if($info->cantidad > 0){
                        array_push($pilaArrayId, $infodata->id);
                        break;
                    }
                }
            }

            $listaProy = TipoProyecto::orderBy('nombre')
                ->whereIn('id', $pilaArrayId)
                ->get();

            $granTotal = 0;

            foreach ($listaProy as $dato){
                array_push($resultsBloque, $dato);

                $arrayEntradas = Entradas::where('id_tipoproyecto', $dato->id)->get();

                foreach ($arrayEntradas as $info){
                    $infoMate   = Materiales::where('id', $info->id_material)->first();
                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $totalLinea = $info->precio * $info->cantidad;
                    $granTotal += $totalLinea;

                    $info->nombremate  = $infoMate->nombre;
                    $info->codigomate  = $infoMate->codigo;
                    $info->unimedida   = $infoMedida ? $infoMedida->nombre : '—';
                    $info->precioFmt   = number_format($info->precio, 2);
                    $info->totalFmt    = number_format($totalLinea, 2);
                }

                $resultsBloque[$index]->detalle = $arrayEntradas;
                $index++;
            }

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Inventario Materiales');
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Inventario de Materiales</h2>
                    <p style='margin:0; font-size:12px;'>TALLER DE ESTRUCTURAS</p>
                </td>
            </tr>
        </table>";

            foreach ($listaProy as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'><tbody>";
                $tabla .= "<tr><td style='font-weight:bold;'>Proyecto</td></tr>";
                $tabla .= "<tr><td>$dd->nombre</td></tr>";
                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top:20px;'><tbody>";
                $tabla .= "
            <tr>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:28%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Cantidad</td>
                <td style='font-weight:bold; width:16%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:16%; font-size:13px;'>Total ($)</td>
            </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$gg->codigomate</td>
                    <td style='font-size:12px;'>$gg->unimedida</td>
                    <td style='font-size:12px;'>$gg->nombremate</td>
                    <td style='font-size:12px;'>$gg->cantidad</td>
                    <td style='font-size:12px;'>$ $gg->precioFmt</td>
                    <td style='font-size:12px;'>$ $gg->totalFmt</td>
                </tr>";
                }

                $tabla .= "</tbody></table><br>";
            }

            // ── Gran total al final ──
            $tabla .= "
        <table width='100%' style='margin-top:10px;'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; font-size:14px; text-align:right;
                                border-top:2px solid #000; padding-top:6px;'>
                        TOTAL GENERAL:&nbsp;&nbsp;
                    </td>
                    <td style='font-weight:bold; font-size:14px; width:18%;
                                border-top:2px solid #000; padding-top:6px;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }


    public function vistaQueHaSalidoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquehasalidoproyecto', compact('proyectos'));
    }


    public function pdfQueHaSalidoProyectos($idproy, $desde, $hasta, $tipo){

        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $logoalcaldia = 'images/logo.png';

        $encabezado = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Materiales Entregados</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>
                </td>
            </tr>
        </table>
    ";

        // JUNTOS
        if($tipo == 1){

            $pilaArray = array();

            $arrayHistoSalida = HistorialSalidas::where('id_tipoproyecto', $idproy)
                ->whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($arrayHistoSalida as $data){
                array_push($pilaArray, $data->id);
            }

            $dataArray = array();

            $arraySalidaDetalle = HistorialSalidasDeta::whereIn('id_historial_salidas', $pilaArray)->get();

            $arrayMateriales = Materiales::all();

            foreach ($arrayMateriales as $data){

                $infoMedida = UnidadMedida::where('id', $data->id_medida)->first();

                $cantidad = 0;

                foreach ($arraySalidaDetalle as $item) {
                    if($item->id_material == $data->id){
                        $cantidad = $cantidad + $item->cantidad;
                    }
                }

                if($cantidad > 0){
                    $dataArray[] = [
                        'nombre'   => $data->nombre,
                        'codigo'   => $data->codigo,
                        'cantidad' => $cantidad,
                        'medida'   => $infoMedida ? $infoMedida->nombre : '—'
                    ];
                }
            }

            usort($dataArray, function ($a, $b) {
                return strcmp($a['nombre'], $b['nombre']);
            });

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;

            $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

            $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad</td>
                    </tr>";

            foreach ($dataArray as $info) {
                $codigo   = $info['codigo'];
                $nombre   = $info['nombre'];
                $cantidad = $info['cantidad'];

                $tabla .= "<tr>
                <td style='font-size: 12px'>$codigo</td>
                <td style='text-align: left !important; font-size: 12px'>$nombre</td>
                <td style='font-size: 12px'>$cantidad</td>
            </tr>";
            }

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

        } else {
            // SEPARADOS

            $arrayHistoSalida = HistorialSalidas::where('id_tipoproyecto', $idproy)
                ->whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            $resultsBloque = array();
            $index = 0;

            foreach ($arrayHistoSalida as $data){

                array_push($resultsBloque, $data);

                $data->fecha = date("d-m-Y", strtotime($data->fecha));

                $arrayDetalle = HistorialSalidasDeta::where('id_historial_salidas', $data->id)->get();

                foreach ($arrayDetalle as $deta){
                    $infoMate = Materiales::where('id', $deta->id_material)->first();

                    if (!$infoMate) continue;

                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $deta->nombremate = $infoMate->nombre;
                    $deta->codigo     = $infoMate->codigo;
                    $deta->unimedida  = $infoMedida ? $infoMedida->nombre : '—';
                }

                $resultsBloque[$index]->detalle = $arrayDetalle;
                $index++;
            }

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

            foreach ($arrayHistoSalida as $info) {

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Fecha</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='font-size: 12px'>$info->fecha</td>
                        <td style='font-size: 12px'>$info->descripcion</td>
                    </tr>
                </tbody>
            </table>";

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Medida</td>
                        <td style='font-weight: bold; width: 30%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Cantidad</td>
                    </tr>";

                foreach ($info->detalle as $data) {
                    $tabla .= "<tr>
                    <td style='font-size: 12px'>$data->codigo</td>
                    <td style='font-size: 12px'>$data->unimedida</td>
                    <td style='font-size: 12px'>$data->nombremate</td>
                    <td style='font-size: 12px'>$data->cantidad</td>
                </tr>";
                }

                $tabla .= "</tbody></table>";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }

    public function vistaQueTengoPorProyecto(){

        $terminados = HistorialTransferido::all();
        $pilaIdTransfe = array();

        foreach ($terminados as $data){
            array_push($pilaIdTransfe, $data->id_tipoproyecto);
        }

        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')
            ->whereNotIn('id', $pilaIdTransfe)
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquetengoporproyecto', compact('proyectos'));
    }

    public function reporteQueTengoPorProyecto($idproy){

        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $arrayInventario = Entradas::where('id_tipoproyecto', $idproy)->get();

        foreach ($arrayInventario as $dato){
            $infoMate = Materiales::where('id', $dato->id_material)->first();

            if (!$infoMate) continue;

            $dato->nombreMate = $infoMate->nombre;
            $dato->codigoMate = $infoMate->codigo;
        }

        $fechahoy = Carbon::parse(Carbon::now());
        $fechaFormat = date("d-m-Y", strtotime($fechahoy));

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Inventario Actual');
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/logo.png';

        $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Inventario de Proyecto</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $fechaFormat</p>
                </td>
            </tr>
        </table>
    ";

        $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

        $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad</td>
                    </tr>";

        foreach ($arrayInventario as $info) {
            if($info->cantidad > 0){
                $tabla .= "<tr>
                <td style='font-size: 12px'>$info->codigoMate</td>
                <td style='font-size: 12px'>$info->nombreMate</td>
                <td style='font-size: 12px'>$info->cantidad</td>
            </tr>";
            }
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaProyectoCompletado(){

        $transferido = HistorialTransferido::orderBy('fecha', 'ASC')->get();

        foreach ($transferido as $dato){

            $dato->fecha = date("d-m-Y", strtotime($dato->fecha));

            $infoProy = TipoProyecto::where('id', $dato->id_tipoproyecto)->first();

            $dato->nomproy = $infoProy->nombre;
        }

        return view('backend.admin.repuestos.reporte.vistaproyectocompletado', compact('transferido'));
    }

    public function reporteProyectoTerminado($idtrans){

        $infoTrans = HistorialTransferido::where('id', $idtrans)->first();

        $fechaGenerado = date("d-m-Y", strtotime($infoTrans->fecha));

        $infoProyecto = TipoProyecto::where('id', $infoTrans->id_tipoproyecto)->first();

        $listado = HistorialTransferidoDetalle::where('id_historial_transf', $idtrans)->get();

        foreach ($listado as $dato){

            $infoMaterial = Materiales::where('id', $dato->id_material)->first();

            $dato->nommaterial = $infoMaterial->nombre;
            $dato->codmaterial = $infoMaterial->codigo;

            $infoUnidad = UnidadMedida::where('id', $infoMaterial->id_medida)->first();
            $dato->nomunidad = $infoUnidad->nombre;
        }

        //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Transferido');

        // mostrar errores
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/logo.png';

        $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0; font-size: 15px'>Reporte de Proyecto Completado</h2>
                    <p style='margin:0; font-size:13px;'>Fecha: $fechaGenerado</p>
                </td>
            </tr>
        </table>
    ";

        $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";


        $tabla .= "<table width='100%' id='tablaFor'>
            <tbody>";

        $tabla .= "<tr>
                    <td style='font-weight: bold; width: 14%; font-size: 13px'>Código</td>
                    <td style='font-weight: bold; width: 14%; font-size: 13px'>Medida</td>
                    <td style='font-weight: bold; width: 22%; font-size: 13px'>Material</td>
                    <td style='font-weight: bold; width: 12%; font-size: 13px'>Cantidad</td>
                </tr>
                ";

        foreach ($listado as $dd) {

            $tabla .= "<tr>
                     <td style='font-size: 12px'>$dd->codmaterial</td>
                     <td style='font-size: 12px'>$dd->nomunidad</td>
                     <td style='font-size: 12px'>$dd->nommaterial</td>
                     <td style='font-size: 12px'>$dd->cantidad</td>
                </tr>
                ";
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet,1);

        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');

        $mpdf->WriteHTML($tabla, 2);

        $mpdf->Output();
    }


    public function vistaSalidaPorMaterial(){

        $arrayMateriales = Materiales::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistasalidapormaterial', compact('arrayMateriales'));
    }


    public function pdfReporteMaterialesSalidas($desde, $hasta, $materiales){

        $porciones = explode("-", $materiales);

        $arrayIdSalidas = HistorialSalidasDeta::whereIn('id_material', $porciones)->get();

        $pilaIdSalidas = array();

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $resultsBloque = array();
        $index = 0;

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        foreach ($arrayIdSalidas as $dato){
            array_push($pilaIdSalidas, $dato->id_historial_salidas);
        }

        $arraySalidas = HistorialSalidas::whereIn('id', $pilaIdSalidas)
            ->whereBetween('fecha', [$start, $end])
            ->orderBy('fecha', 'ASC')
            ->get();

        $pilaIdSalidasFormat = array();
        foreach ($arraySalidas as $dato){
            array_push($pilaIdSalidasFormat, $dato->id);
        }

        foreach ($arraySalidas as $infoFila){
            array_push($resultsBloque, $infoFila);

            $infoFila->fechaFormat = date("d-m-Y", strtotime($infoFila->fecha));

            $infoTipoProy = TipoProyecto::where('id', $infoFila->id_tipoproyecto)->first();
            $infoFila->nombreProy = $infoTipoProy->nombre;

            $arrayDetalle = DB::table('historial_salidas_deta AS deta')
                ->join('materiales AS ma', 'ma.id', '=', 'deta.id_material')
                ->select('ma.nombre', 'deta.id_material', 'deta.id_historial_salidas', 'deta.cantidad')
                ->where('deta.id_historial_salidas', $infoFila->id)
                ->whereIn('deta.id_material', $porciones)
                ->orderBy('ma.nombre', 'ASC')
                ->get();

            $resultsBloque[$index]->detalle = $arrayDetalle;
            $index++;
        }

        $arrayMaterial = Materiales::whereIn('id', $porciones)->get();

        $dataArray = array();

        foreach ($arrayMaterial as $dato){

            $conteoDetalle = HistorialSalidasDeta::whereIn('id_historial_salidas', $pilaIdSalidasFormat)
                ->where('id_material', $dato->id)
                ->sum('cantidad');

            $conteoDetalle = number_format((float)$conteoDetalle, 2, '.', ',');

            $dataArray[] = [
                'nombre'        => $dato->nombre,
                'cantidadtotal' => $conteoDetalle,
            ];
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Salida Por Materiales');
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/logo.png';

        $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte Salida de Materiales</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>
                </td>
            </tr>
        </table>
    ";

        foreach ($arraySalidas as $info) {

            $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>
                        <tr>
                            <td style='font-weight: bold; width: 15%; font-size: 12px'>Fecha</td>
                            <td style='font-weight: bold; width: 50%; font-size: 12px'>Proyecto</td>
                            <td style='font-weight: bold; width: 15%; font-size: 12px'>Descripción</td>
                        </tr>
                        <tr>
                            <td style='font-size: 12px'>$info->fechaFormat</td>
                            <td style='font-size: 12px'>$info->nombreProy</td>
                            <td style='font-size: 12px'>$info->descripcion</td>
                        </tr>
                    </tbody>
                </table>";

            $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>
                        <tr>
                            <td style='font-weight: bold; width: 15%; font-size: 13px'>Material</td>
                            <td style='font-weight: bold; width: 10%; font-size: 13px'>Cantidad</td>
                        </tr>";

            foreach ($info->detalle as $dato) {
                $tabla .= "<tr>
                        <td style='font-size: 12px'>$dato->nombre</td>
                        <td style='font-size: 12px'>$dato->cantidad</td>
                    </tr>";
            }

            $tabla .= "</tbody></table>";
        }

        $tabla .= "<p style='font-weight: bold; margin-top: 30px'>MATERIALES ENTREGADOS</p>";

        $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad Total</td>
                    </tr>";

        foreach ($dataArray as $info){
            $infoNombre = $info['nombre'];
            $infoConteo = $info['cantidadtotal'];

            $tabla .= "<tr>
                    <td style='font-size: 12px'>$infoNombre</td>
                    <td style='font-size: 12px'>$infoConteo</td>
                </tr>";
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaQueHaEntradoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquehaentradoproyecto', compact('proyectos'));
    }



    public function pdfQueHaEntradoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start = Carbon::parse($desde)->startOfDay();
            $end   = Carbon::parse($hasta)->endOfDay();

            $desdeFormat = date("d-m-Y", strtotime($desde));
            $hastaFormat = date("d-m-Y", strtotime($hasta));
            $fechaLabel  = "Fecha: $desdeFormat  -  $hastaFormat";
        } else {
            $fechaLabel = "Todas las fechas";
        }

        $encabezado = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c;
                                        font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:0.8px solid #000;
                            padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Materiales Recibidos</h2>
                    <p style='margin:0; font-size:12px;'>$fechaLabel</p>
                </td>
            </tr>
        </table>
    ";

        // ─── TIPO 1: JUNTOS ────────────────────────────────────────────────────
        if ($tipo == 1) {

            $pilaArray = array();

            $query = HistorialEntradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $arrayHistoEntrada = $query->orderBy('fecha', 'ASC')->get();

            foreach ($arrayHistoEntrada as $data) {
                array_push($pilaArray, $data->id);
            }

            $dataArray = array();
            $arrayEntradaDetalle = HistorialEntradasDeta::whereIn('id_historial', $pilaArray)->get();
            $arrayMateriales = Materiales::all();

            $granTotal = 0; // ← acumulador global

            foreach ($arrayMateriales as $data) {

                $infoMedida = UnidadMedida::where('id', $data->id_medida)->first();

                $cantidad     = 0;
                $totalMaterial = 0;
                $precioUnitario = 0;

                foreach ($arrayEntradaDetalle as $item) {
                    if ($item->id_material == $data->id) {
                        $cantidad       += $item->cantidad;
                        $totalMaterial  += ($item->precio * $item->cantidad);
                        $precioUnitario  = $item->precio; // último precio registrado
                    }
                }

                if ($cantidad > 0) {
                    $granTotal += $totalMaterial;
                    $dataArray[] = [
                        'nombre'         => $data->nombre,
                        'codigo'         => $data->codigo,
                        'cantidad'       => $cantidad,
                        'precioUnitario' => number_format($precioUnitario, 2),
                        'total'          => number_format($totalMaterial, 2),
                        'medida'         => $infoMedida ? $infoMedida->nombre : '—',
                    ];
                }
            }

            usort($dataArray, function ($a, $b) {
                return strcmp($a['nombre'], $b['nombre']);
            });

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                </tr>";

            foreach ($dataArray as $info) {
                $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$info['codigo']}</td>
                <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
                <td style='font-size:12px;'>{$info['medida']}</td>
                <td style='font-size:12px;'>{$info['cantidad']}</td>
                <td style='font-size:12px;'>$ {$info['precioUnitario']}</td>
                <td style='font-size:12px;'>$ {$info['total']}</td>
            </tr>";
            }

            // ── Fila de gran total ──
            $tabla .= "
            <tr>
                <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000;
                            padding-top:4px;'>
                    $ $granTotalFmt
                </td>
            </tr>";

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

        } else {
            // ─── TIPO 2: SEPARADOS ─────────────────────────────────────────────

            $query = HistorialEntradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $arrayHistoEntrada = $query->orderBy('fecha', 'ASC')->get();

            $resultsBloque = array();
            $index = 0;
            $granTotal = 0; // ← acumulador global

            foreach ($arrayHistoEntrada as $data) {

                array_push($resultsBloque, $data);

                $data->fechaFormat = $data->fecha
                    ? date("d-m-Y", strtotime($data->fecha))
                    : 'Sin fecha';

                $arrayDetalle = HistorialEntradasDeta::where('id_historial', $data->id)->get();

                foreach ($arrayDetalle as $deta) {
                    $infoMate = Materiales::where('id', $deta->id_material)->first();

                    if (!$infoMate) continue;

                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $totalLinea = $deta->precio * $deta->cantidad;
                    $granTotal += $totalLinea;

                    $deta->nombremate    = $infoMate->nombre;
                    $deta->codigomate    = $infoMate->codigo;
                    $deta->unimedida     = $infoMedida ? $infoMedida->nombre : '—';
                    $deta->precioUnitFmt = number_format($deta->precio, 2);
                    $deta->totalFmt      = number_format($totalLinea, 2);
                }

                $resultsBloque[$index]->detalle = $arrayDetalle;
                $index++;
            }

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            foreach ($resultsBloque as $info) {

                $tabla .= "
            <table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                        <td style='font-weight:bold; width:85%; font-size:13px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='font-size:12px;'>{$info->fechaFormat}</td>
                        <td style='font-size:12px;'>{$info->descripcion}</td>
                    </tr>
                </tbody>
            </table>";

                $tabla .= "
            <table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                        <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                        <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
                        <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                    </tr>";

                foreach ($info->detalle as $deta) {
                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>{$deta->codigomate}</td>
                    <td style='font-size:12px;'>{$deta->unimedida}</td>
                    <td style='font-size:12px;'>{$deta->nombremate}</td>
                    <td style='font-size:12px;'>{$deta->cantidad}</td>
                    <td style='font-size:12px;'>$ {$deta->precioUnitFmt}</td>
                    <td style='font-size:12px;'>$ {$deta->totalFmt}</td>
                </tr>";
                }

                $tabla .= "</tbody></table><br>";
            }

            // ── Gran total al final del documento ──
            $tabla .= "
        <table width='100%' style='margin-top:10px;'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; font-size:14px; text-align:right;
                                border-top:2px solid #000; padding-top:6px;'>
                        TOTAL GENERAL:&nbsp;&nbsp;
                    </td>
                    <td style='font-weight:bold; font-size:14px; width:18%;
                                border-top:2px solid #000; padding-top:6px;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }



}
