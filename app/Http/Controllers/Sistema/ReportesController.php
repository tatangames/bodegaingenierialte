<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{



    public function pdfQueHaSalidoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = Tipoproyecto::find($idproy);

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start      = date('Y-m-d 00:00:00', strtotime($desde));
            $end        = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel = "Fecha: " . date("d-m-Y", strtotime($desde)) . "  -  " . date("d-m-Y", strtotime($hasta));
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
            <h2 style='margin:0;'>Reporte de Materiales Entregados</h2>
            <p style='margin:0; font-size:12px;'>$fechaLabel</p>
        </td>
    </tr>
</table>";

        // ─── TIPO 1: JUNTOS ───────────────────────────────────────────
        if ($tipo == 1) {

            $query = Salidas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $idsSalidas = $query->orderBy('fecha', 'ASC')->pluck('id');

            $totalSalidas = $idsSalidas->count();

            $detalles = SalidasDetalle::with('entradaDetalle.material.unidadMedida')
                ->whereIn('id_salida', $idsSalidas)
                ->get();

            $dataArray         = [];
            $sumaTotalCantidad = 0;

            foreach ($detalles as $det) {
                $entDet = $det->entradaDetalle;
                if (!$entDet || !$entDet->material) continue;

                $idMat = $entDet->id_material;

                if (!isset($dataArray[$idMat])) {
                    $dataArray[$idMat] = [
                        'nombre'   => $entDet->material->nombre ?? '',
                        'medida'   => $entDet->material->unidadMedida->nombre ?? '',
                        'codigo'   => $entDet->codigo ?? '',
                        'cantidad' => 0,
                        'total'    => 0,
                        'precio'   => 0,
                    ];
                }

                $dataArray[$idMat]['cantidad']  += $det->cantidad_salida;
                $dataArray[$idMat]['total']     += ($det->cantidad_salida * $entDet->precio);
                $dataArray[$idMat]['precio']     = $entDet->precio;
                $sumaTotalCantidad              += $det->cantidad_salida;
            }

            usort($dataArray, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

            $granTotal            = array_sum(array_column($dataArray, 'total'));
            $granTotalFmt         = number_format($granTotal, 4);
            $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2, '.', ',');

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "
        <p style='font-size:15px;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </p>
        <p style='font-size:13px;'>
            <span style='font-weight:bold;'>Total de salidas registradas:</span> $totalSalidas
        </p>";

            $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Marca</td>
                <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Cantidad</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
            </tr>";

            foreach ($dataArray as $info) {
                $precioFmt = number_format($info['precio'], 4);
                $totalFmt  = number_format($info['total'], 4);

                $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$info['codigo']}</td>
                <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
                <td style='font-size:12px;'>{$info['medida']}</td>
                <td style='font-size:12px;'>{$info['cantidad']}</td>
                <td style='font-size:12px;'>$ $precioFmt</td>
                <td style='font-size:12px;'>$ $totalFmt</td>
            </tr>";
            }

            $tabla .= "
            <tr>
                <td colspan='3' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL CANTIDAD:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                    $sumaTotalCantidadFmt
                </td>
                <td style='font-weight:bold; font-size:13px; text-align:right;
                            border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                    $ $granTotalFmt
                </td>
            </tr>
        </tbody>
    </table>";

            // ─── TIPO 2: SEPARADOS ────────────────────────────────────────
        } else {

            $query = Salidas::with([
                'detalle.entradaDetalle.material.unidadMedida',
                'proyectoTransferencia', // ← relación al proyecto destino
                'detalle.entradaDetalle.material.objetoEspecifico',
            ])->where('id_tipoproyecto', $idproy);

            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }

            $arraySalidas = $query->orderBy('fecha', 'ASC')->get();

            $totalSalidas      = $arraySalidas->count();
            $granTotal         = 0;
            $sumaTotalCantidad = 0;

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "
        <p style='font-size:15px;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </p>
        <p style='font-size:13px;'>
            <span style='font-weight:bold;'>Total de salidas registradas:</span> $totalSalidas
        </p>";

            foreach ($arraySalidas as $salida) {

                $fechaFmt        = date("d-m-Y", strtotime($salida->fecha));
                $descripcion     = $salida->descripcion ?? '';
                $esTransferencia = (int) $salida->es_transferencia === 1;

                // ── Badge de transferencia con destino ──
                if ($esTransferencia) {

                    if ($salida->id_tipoproyecto_transferencia) {
                        // Fue a un proyecto específico
                        $nombreDestino = $salida->proyectoTransferencia
                            ? $salida->proyectoTransferencia->nombre
                            : 'Proyecto #' . $salida->id_tipoproyecto_transferencia;
                        $textoLabel = "TRANSFERENCIA &#8594; $nombreDestino";
                    } else {
                        // Salida general sin proyecto destino
                        $textoLabel = "SALIDA GENERAL (Sin proyecto destino)";
                    }

                    $tabla .= "
                <table width='100%' style='margin-bottom:3px;'>
                    <tbody>
                        <tr>
                            <td style='
                                background-color:#e9e9e9;
                                border:1px solid #aaaaaa;
                                color:#444444;
                                font-weight:bold;
                                font-size:12px;
                                padding:4px 8px;
                                text-align:center;
                            '>
                                $textoLabel
                            </td>
                        </tr>
                    </tbody>
                </table>";
                }

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                    <td style='font-weight:bold; width:85%; font-size:13px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='font-size:12px;'>$fechaFmt</td>
                    <td style='font-size:12px;'>$descripcion</td>
                </tr>
            </tbody>
        </table>";

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                </tr>";

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($salida->detalle as $det) {


                    //return $det;

                    $entDet = $det->entradaDetalle;
                    if (!$entDet || !$entDet->material) continue;

                    $codigo = $entDet->material->objetoEspecifico->codigo ?? '';
                    $medida    = $entDet->material->unidadMedida->nombre ?? '';
                    $nombreMat = $entDet->material->nombre ?? '';
                    $cantidad  = $det->cantidad_salida;
                    $precio    = $entDet->precio ?? 0;
                    $total     = $cantidad * $precio;

                    $granTotal         += $total;
                    $subtotal          += $total;
                    $sumaTotalCantidad += $cantidad;
                    $subtotalCantidad  += $cantidad;

                    $precioFmt = number_format($precio, 4);
                    $totalFmt  = number_format($total, 4);

                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$codigo</td>
                    <td style='font-size:12px;'>$medida</td>
                    <td style='font-size:12px;'>$nombreMat</td>
                    <td style='font-size:12px;'>$cantidad</td>
                    <td style='font-size:12px;'>$ $precioFmt</td>
                    <td style='font-size:12px;'>$ $totalFmt</td>
                </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2, '.', ',');

                $tabla .= "
                <tr>
                    <td colspan='2' style='border-top:1px solid #000;'></td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                               border-top:1px solid #000; padding-top:3px;'>
                        Subtotal cantidad:
                    </td>
                    <td style='font-weight:bold; font-size:12px;
                               border-top:1px solid #000; padding-top:3px;'>
                        $subtotalCantidadFmt
                    </td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                               border-top:1px solid #000; padding-top:3px;'>
                        Subtotal:
                    </td>
                    <td style='font-weight:bold; font-size:12px;
                               border-top:1px solid #000; padding-top:3px;'>
                        $ $subtotalFmt
                    </td>
                </tr>
            </tbody>
        </table><br>";
            }

            $granTotalFmt         = number_format($granTotal, 4);
            $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2, '.', ',');

            $tabla .= "
    <table width='100%' style='margin-top:10px;'>
        <tbody>
            <tr>
                <td style='font-weight:bold; font-size:14px; text-align:right;
                            border-top:2px solid #000; padding-top:6px;'>
                    TOTAL CANTIDAD:&nbsp;&nbsp;
                </td>
                <td style='font-weight:bold; font-size:14px; width:15%;
                            border-top:2px solid #000; padding-top:6px;'>
                    $sumaTotalCantidadFmt
                </td>
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
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }



    public function vistaQueTengoPorProyecto()
    {
        $proyectos   = Tipoproyecto::where('transferido', 0)->orderBy('nombre', 'ASC')->get();
        $transferido = Tipoproyecto::where('transferido', 1)->orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquetengoporproyecto', compact('proyectos', 'transferido'));
    }

    public function reporteQueTengoPorProyecto($idproy)
    {
        $infoProyecto = Tipoproyecto::find($idproy);
        $fechaFormat  = date("d-m-Y");
        $logoalcaldia = 'images/logo.png';

        // Obtener entradas_detalle del proyecto con material y medida
        $detalles = EntradasDetalle::with('material.unidadMedida')
            ->whereHas('entrada', fn($q) => $q->where('id_tipoproyecto', $idproy))
            ->get();

        // Agrupar por material y calcular stock
        $porMaterial = [];

        foreach ($detalles as $det) {
            if (!$det->material) continue;

            $idMat = $det->id_material;

            if (!isset($porMaterial[$idMat])) {
                $porMaterial[$idMat] = [
                    'nombre'   => $det->material->nombre ?? '',
                    'medida'   => $det->material->unidadMedida->nombre ?? '',
                    'codigo'   => $det->codigo ?? '',
                    'entradas' => 0,
                    'salidas'  => 0,
                    'precio'   => 0,
                ];
            }

            $porMaterial[$idMat]['entradas'] += $det->cantidad_inicial;
            $porMaterial[$idMat]['precio']    = $det->precio;

            $salidas = SalidasDetalle::where('id_entrada_detalle', $det->id)
                ->sum('cantidad_salida');
            $porMaterial[$idMat]['salidas'] += $salidas;
        }

        // Solo materiales con stock > 0
        $porMaterial = array_filter($porMaterial, fn($m) => ($m['entradas'] - $m['salidas']) > 0);

        // Ordenar por nombre
        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Inventario Actual');
        $mpdf->showImageErrors = false;

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
    </table>";

        $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

        $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Marca</td>
                <td style='font-weight:bold; width:38%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:10%; font-size:13px;'>Stock</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Total ($)</td>
            </tr>";

        foreach ($porMaterial as $mat) {
            $stock      = $mat['entradas'] - $mat['salidas'];
            $totalLinea = $stock * $mat['precio'];
            $granTotal += $totalLinea;

            $precioFmt = number_format($mat['precio'], 4);
            $totalFmt  = number_format($totalLinea, 4);

            $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$mat['codigo']}</td>
                <td style='font-size:12px;'>{$mat['nombre']}</td>
                <td style='font-size:12px;'>{$mat['medida']}</td>
                <td style='font-size:12px;'>$stock</td>
                <td style='font-size:12px;'>$ $precioFmt</td>
                <td style='font-size:12px;'>$ $totalFmt</td>
            </tr>";
        }

        $granTotalFmt = number_format($granTotal, 4);

        $tabla .= "
            <tr>
                <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
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


    public function vistaProyectoCompletado()
    {
        // Proyectos cerrados = transferido 1
        $transferido = Tipoproyecto::where('transferido', 1)
            ->orderBy('nombre', 'ASC')
            ->get();

        return view('backend.admin.repuestos.reporte.vistaproyectocompletado', compact('transferido'));
    }

    public function reporteProyectoTerminado($idtrans)
    {
        $infoProyecto  = Tipoproyecto::find($idtrans);
        $fechaGenerado = date("d-m-Y");
        $logoalcaldia  = 'images/logo.png';

        $transferencia = Transferencia::where('id_tipoproyecto', $idtrans)
            ->orderBy('id', 'desc')
            ->first();

        if (!$transferencia) {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER-L']);
            $mpdf->WriteHTML("<p style='font-family:Arial; font-size:14px; color:red;'>
        Este proyecto no tiene registro de cierre generado.</p>", 2);
            $mpdf->Output();
            return;
        }

        $fechaCierre = date("d-m-Y", strtotime($transferencia->fecha));

        $detallesSnapshot = TransferenciaDetalle::where('id_transferencia', $transferencia->id)
            ->get();

        $porMaterial = [];

        foreach ($detallesSnapshot as $det) {

            // ── Clave por id_entrada_detalle, no por nombre ────────────────
            $key = $det->id_entrada_detalle;

            $entradaDet = EntradasDetalle::with('material.unidadMedida', 'material.objetoEspecifico')
                ->find($det->id_entrada_detalle);

            $cantAdquirida = $entradaDet?->cantidad_inicial ?? 0;

            $cantUtilizada = \App\Models\SalidasDetalle::where('id_entrada_detalle', $det->id_entrada_detalle)
                ->whereHas('salida', function ($q) use ($idtrans) {
                    $q->where('id_tipoproyecto', $idtrans)
                        ->where('es_transferencia', false);
                })
                ->sum('cantidad_salida');

            if (!isset($porMaterial[$key])) {
                $porMaterial[$key] = [
                    'nombre'          => $entradaDet?->material?->nombre ?? $det->nombre_material ?? '—',
                    'medida'          => $entradaDet?->material?->unidadMedida?->nombre ?? '—',
                    'codigo'          => $entradaDet?->material?->objetoEspecifico?->codigo ?? '—',
                    'cant_adquirida'  => 0,
                    'cant_utilizada'  => 0,
                    'cantidad_cierre' => 0,
                    'precio'          => $det->precio,
                ];
            }

            $porMaterial[$key]['cant_adquirida']  += $cantAdquirida;
            $porMaterial[$key]['cant_utilizada']  += $cantUtilizada;
            $porMaterial[$key]['cantidad_cierre'] += $det->cantidad_sobrante;
        }

        $porMaterial = array_filter($porMaterial, fn($m) => $m['cantidad_cierre'] > 0);
        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER-L']);
        $mpdf->SetTitle('Reporte GEAD-001-INFO');
        $mpdf->showImageErrors = false;

        // ── Encabezado ────────────────────────────────────────────────────
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
        <td style='width:40%; border:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <h2 style='margin:0; font-size:14px;'>Informe de Inventario Físico de<br>Materiales Sobrantes</h2>
            <p style='margin:2px 0 0; font-size:11px; color:#555;'>GEAD-001-INFO</p>
        </td>
        <td style='width:30%; border:0.8px solid #000; padding:6px 8px; font-size:11px;'>
            <b>Fecha de generación:</b> $fechaGenerado<br>
            <b>Fecha de cierre:</b> $fechaCierre
        </td>
    </tr>
</table>";

        // ── Info proyecto ─────────────────────────────────────────────────
        $tabla .= "
<table width='100%' style='margin-bottom:4px; border-collapse:collapse;'>
    <tr>
        <td style='font-size:13px; padding:4px 0;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </td>
    </tr>
</table>";

        // ── Tabla de materiales ───────────────────────────────────────────
        $thStyle = "font-weight:bold; font-size:11px; border:0.8px solid #000;
                padding:5px 4px; background:#d9e1f2; text-align:center;";
        $tdStyle = "font-size:11px; border:0.8px solid #000; padding:4px;";
        $tdC     = $tdStyle . " text-align:center;";
        $tdR     = $tdStyle . " text-align:right;";

        $tabla .= "
<table width='100%' style='border-collapse:collapse;'>
    <thead>
        <tr>
            <th style='{$thStyle} width:8%;'>Obj.<br>Espec.</th>
            <th style='{$thStyle} width:28%;'>Material</th>
            <th style='{$thStyle} width:8%;'>Medida</th>
            <th style='{$thStyle} width:9%;'>Cant.<br>Adquirida</th>
            <th style='{$thStyle} width:9%;'>Cant.<br>Utilizada</th>
            <th style='{$thStyle} width:9%;'>Cant.<br>Sobrante</th>
            <th style='{$thStyle} width:10%;'>Precio<br>Unit.</th>
            <th style='{$thStyle} width:10%;'>Total ($)</th>

        </tr>
    </thead>
    <tbody>";

        foreach ($porMaterial as $mat) {
            $totalLinea = $mat['cantidad_cierre'] * $mat['precio'];
            $granTotal += $totalLinea;

            $precioFmt = '$ ' . number_format($mat['precio'], 4);
            $totalFmt  = '$ ' . number_format($totalLinea, 4);

            $tabla .= "
        <tr>
            <td style='{$tdC}'>{$mat['codigo']}</td>
            <td style='{$tdStyle}'>{$mat['nombre']}</td>
            <td style='{$tdC}'>{$mat['medida']}</td>
            <td style='{$tdC}'>{$mat['cant_adquirida']}</td>
            <td style='{$tdC}'>{$mat['cant_utilizada']}</td>
            <td style='{$tdC} font-weight:bold;'>{$mat['cantidad_cierre']}</td>
            <td style='{$tdR}'>{$precioFmt}</td>
            <td style='{$tdR}'>{$totalFmt}</td>

        </tr>";
        }

        $granTotalFmt = '$ ' . number_format($granTotal, 4);

        $tabla .= "
        <tr>
            <td colspan='6' style='font-weight:bold; font-size:12px; text-align:right;
                                    border-top:1.5px solid #000; border:0.8px solid #000;
                                    padding:5px 4px;'>
                TOTAL GENERAL:
            </td>
            <td colspan='2' style='font-weight:bold; font-size:12px;
                                    border-top:1.5px solid #000; border:0.8px solid #000;
                                    padding:5px 4px;'>
                {$granTotalFmt}
            </td>
        </tr>
    </tbody>
</table>";

        $informacionGeneral = InformacionGeneral::where('id', 1)->first();

        // ── Sección de firmas ─────────────────────────────────────────────
       $firmaStyle = "width:33%; text-align:center; font-size:14px; padding-top:{$informacionGeneral->px_sobrantes}px;";
        $tabla .= "
<table width='100%' style='margin-top:30px; border-collapse:collapse;'>
    <tr>
        <td style='{$firmaStyle}'>
            <div style='border-top:0.8px solid #000; padding-top:4px;'>
                Responsable de Ejecución
            </div>
        </td>
        <td style='{$firmaStyle}'>
            <div style='border-top:0.8px solid #000; padding-top:4px;'>
                Supervisor / Jefe Inmediato
            </div>
        </td>
        <td style='{$firmaStyle}'>
            <div style='border-top:0.8px solid #000; padding-top:4px;'>
                Bodeguero / Responsable Asignado
            </div>
        </td>
    </tr>
</table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaQueHaEntradoProyecto()
    {
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquehaentradoproyecto', compact('proyectos'));
    }


    public function pdfQueHaEntradoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = Tipoproyecto::find($idproy);

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start       = date('Y-m-d 00:00:00', strtotime($desde));
            $end         = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel  = "Fecha: " . date("d-m-Y", strtotime($desde)) . "  -  " . date("d-m-Y", strtotime($hasta));
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
</table>";

        $totalCantidad = 0;

        // ─── TIPO 1: JUNTOS ───────────────────────────────────────────
        if ($tipo == 1) {

            $query = Entradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $idsEntradas = $query->orderBy('fecha', 'ASC')->pluck('id');

            $detalles = EntradasDetalle::with('material.unidadMedida')
                ->whereIn('id_entradas', $idsEntradas)
                ->get();

            $dataArray = [];
            $granTotal = 0;

            foreach ($detalles as $det) {
                $idMat = $det->id_material;
                $totalCantidad += $det->cantidad_inicial;

                if (!isset($dataArray[$idMat])) {
                    $dataArray[$idMat] = [
                        'nombre'         => $det->material->nombre ?? '',
                        'medida'         => $det->material->unidadMedida->nombre ?? '',
                        'codigo'         => $det->material->codigo ?? '',
                        'cantidad'       => 0,
                        'totalMaterial'  => 0,
                        'precioUnitario' => 0,
                    ];
                }

                $dataArray[$idMat]['cantidad']       += $det->cantidad_inicial;
                $dataArray[$idMat]['totalMaterial']  += ($det->precio * $det->cantidad_inicial);
                $dataArray[$idMat]['precioUnitario']  = $det->precio;
            }

            usort($dataArray, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

            foreach ($dataArray as $item) {
                $granTotal += $item['totalMaterial'];
            }

            $granTotalFmt     = number_format($granTotal, 2);
            $totalCantidadFmt = number_format($totalCantidad, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            $tabla .= "
<table width='100%' id='tablaFor'>
    <tbody>
        <tr>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Marca</td>
            <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
            <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Cantidad</td>
            <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
            <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
        </tr>";

            foreach ($dataArray as $info) {
                $precioFmt = number_format($info['precioUnitario'], 4);
                $totalFmt  = number_format($info['totalMaterial'], 4);

                $tabla .= "
        <tr>
            <td style='font-size:12px;'>{$info['codigo']}</td>
            <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
            <td style='font-size:12px;'>{$info['medida']}</td>
            <td style='font-size:12px;'>{$info['cantidad']}</td>
            <td style='font-size:12px;'>$ $precioFmt</td>
            <td style='font-size:12px;'>$ $totalFmt</td>
        </tr>";
            }

            $tabla .= "
        <tr>
            <td colspan='3' style='font-weight:bold; font-size:13px; text-align:right;
                                    border-top:1.5px solid #000; padding-top:4px;'>
                TOTAL CANTIDAD:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                $totalCantidadFmt
            </td>
            <td style='font-weight:bold; font-size:13px; text-align:right;
                        border-top:1.5px solid #000; padding-top:4px;'>
                TOTAL GENERAL:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                $ $granTotalFmt
            </td>
        </tr>
    </tbody>
</table>";

            // ─── TIPO 2: SEPARADOS ────────────────────────────────────────
        } else {

            $query = Entradas::with([
                'detalle.material.unidadMedida',
                'detalle.material.objetoEspecifico',
            ])
                ->where('id_tipoproyecto', $idproy);

            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }

            $arrayEntradas = $query->orderBy('fecha', 'ASC')->get();

            $granTotal = 0;

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            foreach ($arrayEntradas as $entrada) {

                $fechaFmt        = date("d-m-Y", strtotime($entrada->fecha));
                $descripcion     = $entrada->descripcion ?? '';
                $factura         = $entrada->factura ?? '';
                $esTransferencia = (int) $entrada->es_transferencia === 1;

                // ── Fila de cierre/transferencia solo si aplica ──
                if ($esTransferencia) {

                    // Buscar el nombre del proyecto origen
                    $proyectoOrigen = null;
                    if ($entrada->id_tipoproyecto_transferencia) {
                        $proyectoOrigen = Tipoproyecto::find($entrada->id_tipoproyecto_transferencia);
                    }
                    $nombreOrigen = $proyectoOrigen ? $proyectoOrigen->nombre : 'Proyecto #' . $entrada->id_tipoproyecto_transferencia;

                    $tabla .= "
                <table width='100%' style='margin-bottom:3px;'>
                    <tbody>
                        <tr>
                            <td style='
                                background-color:#e9e9e9;
                                border:1px solid #aaaaaa;
                                color:#444444;
                                font-weight:bold;
                                font-size:12px;
                                padding:4px 8px;
                                text-align:center;
                            '>
                                 ENTRADA POR CIERRE DE PROYECTO: $nombreOrigen
                            </td>
                        </tr>
                    </tbody>
                </table>";
                }

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                    <td style='font-weight:bold; width:20%; font-size:13px;'>Factura</td>
                    <td style='font-weight:bold; width:65%; font-size:13px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='font-size:12px;'>$fechaFmt</td>
                    <td style='font-size:12px;'>$factura</td>
                    <td style='font-size:12px;'>$descripcion</td>
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

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($entrada->detalle as $det) {
                    $totalCantidad    += $det->cantidad_inicial;
                    $subtotalCantidad += $det->cantidad_inicial;

                    $totalLinea  = $det->precio * $det->cantidad_inicial;
                    $granTotal  += $totalLinea;
                    $subtotal   += $totalLinea;

                    $codigo    = $det->material->objetoEspecifico->codigo ?? '';
                    $nombreMat = $det->material->nombre ?? '';
                    $medida    = $det->material->unidadMedida->nombre ?? '';
                    $precioFmt = number_format($det->precio, 4);
                    $totalFmt  = number_format($totalLinea, 4);

                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$codigo</td>
                    <td style='font-size:12px;'>$medida</td>
                    <td style='font-size:12px;'>$nombreMat</td>
                    <td style='font-size:12px;'>{$det->cantidad_inicial}</td>
                    <td style='font-size:12px;'>$ $precioFmt</td>
                    <td style='font-size:12px;'>$ $totalFmt</td>
                </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2);

                $tabla .= "
                <tr>
                    <td colspan='3' style='font-weight:bold; font-size:12px; text-align:right;
                                           border-top:1px solid #000; padding-top:3px;'>
                        Subtotal Cantidad:
                    </td>
                    <td style='font-weight:bold; font-size:12px; border-top:1px solid #000; padding-top:3px;'>
                        $subtotalCantidadFmt
                    </td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                                border-top:1px solid #000; padding-top:3px;'>
                        Subtotal:
                    </td>
                    <td style='font-weight:bold; font-size:12px; border-top:1px solid #000; padding-top:3px;'>
                        $ $subtotalFmt
                    </td>
                </tr>
            </tbody>
        </table><br>";
            }

            $granTotalFmt     = number_format($granTotal, 4);
            $totalCantidadFmt = number_format($totalCantidad, 2);

            $tabla .= "
<table width='100%' style='margin-top:10px;'>
    <tbody>
        <tr>
            <td style='font-weight:bold; font-size:14px; text-align:right;
                        border-top:2px solid #000; padding-top:6px;'>
                TOTAL CANTIDAD:&nbsp;&nbsp;
            </td>
            <td style='font-weight:bold; font-size:14px; width:12%;
                        border-top:2px solid #000; padding-top:6px;'>
                $totalCantidadFmt
            </td>
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
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }



    public function vistaReporteProyectoCodigos()
    {
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistareporteporcodigos', compact('proyectos'));
    }



    public function reportePDFProyectoCodigos($idproy, $desde, $hasta, $descripcion = '')
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end   = Carbon::parse($hasta)->endOfDay();

        $desdeFormat  = Carbon::parse($desde)->format('d/m/Y');
        $hastaFormat  = Carbon::parse($hasta)->format('d/m/Y');
        $descripcion  = urldecode($descripcion);

        $proyecto = DB::table('tipoproyecto')->where('id', $idproy)->first();

        $rows = DB::select("
    WITH entradas AS (
        SELECT
            ed.id               AS id_entradadetalle,
            ed.id_material,
            ed.precio,
            ed.codigo           AS codigo_detalle,
            ed.nombre           AS nombre_copia,
            ed.cantidad_inicial AS cantidad_entrada,
            e.fecha             AS fecha_entrada
        FROM entradas_detalle ed
        JOIN entradas e ON e.id = ed.id_entradas
        WHERE e.id_tipoproyecto = ?
    ),
    salidas AS (
        SELECT
            sd.id_entrada_detalle,
            sd.cantidad_salida,
            s.fecha AS fecha_salida
        FROM salidas_detalle sd
        JOIN salidas s ON s.id = sd.id_salida
        WHERE s.id_tipoproyecto = ?
    ),
    in_before AS (
        SELECT id_entradadetalle, SUM(cantidad_entrada) AS qty_in_before
        FROM entradas WHERE fecha_entrada < ?
        GROUP BY id_entradadetalle
    ),
    out_before AS (
        SELECT id_entrada_detalle, SUM(cantidad_salida) AS qty_out_before
        FROM salidas WHERE fecha_salida < ?
        GROUP BY id_entrada_detalle
    ),
    in_period AS (
        SELECT id_entradadetalle, SUM(cantidad_entrada) AS qty_in_period
        FROM entradas WHERE fecha_entrada >= ? AND fecha_entrada <= ?
        GROUP BY id_entradadetalle
    ),
    out_period AS (
        SELECT id_entrada_detalle, SUM(cantidad_salida) AS qty_out_period
        FROM salidas WHERE fecha_salida >= ? AND fecha_salida <= ?
        GROUP BY id_entrada_detalle
    )
    SELECT
        en.id_entradadetalle,
        en.id_material,
        obj.codigo                          AS codigo_obj,
        COALESCE(m.nombre, en.nombre_copia) AS descripcion,
        en.precio,
        COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)  AS saldo_inicial_cant,
        COALESCE(ip.qty_in_period,  0)                                   AS entradas_cant,
        COALESCE(op.qty_out_period, 0)                                   AS salidas_cant,
        (COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
         + COALESCE(ip.qty_in_period, 0)
         - COALESCE(op.qty_out_period, 0))                               AS saldo_final_cant,
        ((COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)) * en.precio) AS saldo_inicial_money,
        (COALESCE(ip.qty_in_period,  0) * en.precio)                                   AS entradas_money,
        (COALESCE(op.qty_out_period, 0) * en.precio)                                   AS salidas_money,
        ((COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
          + COALESCE(ip.qty_in_period, 0)
          - COALESCE(op.qty_out_period, 0)) * en.precio)                               AS saldo_final_money
    FROM entradas en
    LEFT JOIN materiales m          ON m.id  = en.id_material
    LEFT JOIN objeto_especifico obj ON obj.id = m.id_objespecifico
    LEFT JOIN in_before  ib ON ib.id_entradadetalle  = en.id_entradadetalle
    LEFT JOIN out_before ob ON ob.id_entrada_detalle = en.id_entradadetalle
    LEFT JOIN in_period  ip ON ip.id_entradadetalle  = en.id_entradadetalle
    LEFT JOIN out_period op ON op.id_entrada_detalle = en.id_entradadetalle
    ORDER BY obj.codigo, descripcion, en.precio
    ", [
            $idproy, $idproy,
            $start->toDateString(), $start->toDateString(),
            $start->toDateString(), $end->toDateString(),
            $start->toDateString(), $end->toDateString(),
        ]);

        $totales = [
            'inicial_cant'   => 0, 'entradas_cant'  => 0,
            'salidas_cant'   => 0, 'final_cant'      => 0,
            'inicial_money'  => 0.0, 'entradas_money' => 0.0,
            'salidas_money'  => 0.0, 'final_money'    => 0.0,
        ];

        $sumPorCodigo = [];

        foreach ($rows as $r) {
            $totales['inicial_cant']   += (int)   ($r->saldo_inicial_cant  ?? 0);
            $totales['entradas_cant']  += (int)   ($r->entradas_cant       ?? 0);
            $totales['salidas_cant']   += (int)   ($r->salidas_cant        ?? 0);
            $totales['final_cant']     += (int)   ($r->saldo_final_cant    ?? 0);
            $totales['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $totales['entradas_money'] += (float) ($r->entradas_money      ?? 0);
            $totales['salidas_money']  += (float) ($r->salidas_money       ?? 0);
            $totales['final_money']    += (float) ($r->saldo_final_money   ?? 0);

            $codigo = $r->codigo_obj ?? 'SIN-CODIGO';

            if (!isset($sumPorCodigo[$codigo])) {
                $sumPorCodigo[$codigo] = [
                    'codigo'         => $codigo,
                    'inicial_cant'   => 0, 'entradas_cant'  => 0,
                    'salidas_cant'   => 0, 'final_cant'      => 0,
                    'inicial_money'  => 0.0, 'entradas_money' => 0.0,
                    'salidas_money'  => 0.0, 'final_money'    => 0.0,
                ];
            }

            $sumPorCodigo[$codigo]['inicial_cant']   += (int)   ($r->saldo_inicial_cant  ?? 0);
            $sumPorCodigo[$codigo]['entradas_cant']  += (int)   ($r->entradas_cant       ?? 0);
            $sumPorCodigo[$codigo]['salidas_cant']   += (int)   ($r->salidas_cant        ?? 0);
            $sumPorCodigo[$codigo]['final_cant']     += (int)   ($r->saldo_final_cant    ?? 0);
            $sumPorCodigo[$codigo]['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $sumPorCodigo[$codigo]['entradas_money'] += (float) ($r->entradas_money      ?? 0);
            $sumPorCodigo[$codigo]['salidas_money']  += (float) ($r->salidas_money       ?? 0);
            $sumPorCodigo[$codigo]['final_money']    += (float) ($r->saldo_final_money   ?? 0);
        }

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'     => sys_get_temp_dir(),
            'format'      => 'LETTER',
            'orientation' => 'L',
        ]);

        $mpdf->SetTitle('Reporte por Proyecto');
        $mpdf->showImageErrors = false;

        $logoalcaldia   = 'images/gobiernologo.jpg';
        $nombreProyecto = $proyecto->nombre ?? 'Proyecto';

        // ── Encabezado ────────────────────────────────────────────────────
        $encabezado = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:30%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:38px'>
                    </td>
                    <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
                   padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            CONTROL DE ENTRADAS/SALIDAS
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'></td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>
<strong>Proyecto:</strong> {$nombreProyecto}<br>
<strong>Del {$desdeFormat} al {$hastaFormat}</strong><br><br>
";


        if (file_exists(public_path('css/cssbodega.css'))) {
            $stylesheet = file_get_contents(public_path('css/cssbodega.css'));
            $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        }

        // ── Tabla detalle ─────────────────────────────────────────────────
        $html  = $encabezado;
        $html .= "
<table width='100%' border='1' cellspacing='0' cellpadding='4'
       style='border-collapse:collapse; font-size:10px; margin-top:8px;'>
    <thead style='background:#f2f4f8;'>
        <tr>
            <th style='width:3%;'>#</th>
            <th style='width:8%;'>Código</th>
            <th>Descripción / Nombre</th>
            <th style='text-align:right; width:7%;'>PRECIO</th>
            <th style='text-align:right; width:7%;'>INICIAL</th>
            <th style='text-align:right; width:8%;'>\$ INICIAL</th>
            <th style='text-align:right; width:8%;'>ENTRADAS</th>
            <th style='text-align:right; width:9%;'>\$ ENTRADAS</th>
            <th style='text-align:right; width:6%;'>SALIDAS</th>
            <th style='text-align:right; width:8%;'>\$ SALIDAS</th>
            <th style='text-align:right; width:5%;'>SALDO</th>
            <th style='text-align:right; width:8%;'>\$ SALDO</th>
        </tr>
    </thead>
    <tbody>
";

        $i = 1;
        foreach ($rows as $r) {
            $tieneCodigo = !empty($r->codigo_obj);
            $codigoHtml  = $tieneCodigo
                ? e($r->codigo_obj)
                : "<span style='color:#dc3545; font-weight:bold;'>S/C</span>";

            $html .= "
<tr>
    <td>{$i}</td>
    <td style='text-align:center;'>{$codigoHtml}</td>
    <td>".e($r->descripcion)."</td>
    <td style='text-align:right;'>$".number_format($r->precio              ?? 0, 4)."</td>
    <td style='text-align:right;'>".number_format($r->saldo_inicial_cant   ?? 0)."</td>
    <td style='text-align:right;'>$".number_format($r->saldo_inicial_money ?? 0, 2)."</td>
    <td style='text-align:right;'>".number_format($r->entradas_cant        ?? 0)."</td>
    <td style='text-align:right;'>$".number_format($r->entradas_money      ?? 0, 2)."</td>
    <td style='text-align:right;'>".number_format($r->salidas_cant         ?? 0)."</td>
    <td style='text-align:right;'>$".number_format($r->salidas_money       ?? 0, 2)."</td>
    <td style='text-align:right;'>".number_format($r->saldo_final_cant     ?? 0)."</td>
    <td style='text-align:right;'>$".number_format($r->saldo_final_money   ?? 0, 2)."</td>
</tr>
";
            $i++;
        }

        if (!$rows) {
            $html .= "<tr><td colspan='12' style='text-align:center; color:#888;'>Sin registros en el rango seleccionado.</td></tr>";
        }

        $html .= "
    </tbody>
    <tfoot>
        <tr style='font-weight:bold; background:#f9fafb;'>
            <td colspan='4' style='text-align:right;'>Totales:</td>
            <td style='text-align:right;'>".number_format($totales['inicial_cant'])."</td>
            <td style='text-align:right;'>$".number_format($totales['inicial_money'],  2)."</td>
            <td style='text-align:right;'>".number_format($totales['entradas_cant'])."</td>
            <td style='text-align:right;'>$".number_format($totales['entradas_money'], 2)."</td>
            <td style='text-align:right;'>".number_format($totales['salidas_cant'])."</td>
            <td style='text-align:right;'>$".number_format($totales['salidas_money'],  2)."</td>
            <td style='text-align:right;'>".number_format($totales['final_cant'])."</td>
            <td style='text-align:right;'>$".number_format($totales['final_money'],    2)."</td>
        </tr>
    </tfoot>
</table>
";

        // ── Resumen del período ───────────────────────────────────────────
        $html .= "
<br>
<table width='55%' border='1' cellspacing='0' cellpadding='6'
       style='border-collapse:collapse; font-size:12px;'>
    <tr style='background:#eef3ff; font-weight:bold; text-align:center;'>
        <td colspan='3'>Resumen del período {$desdeFormat} - {$hastaFormat}</td>
    </tr>
    <tr style='font-weight:bold; background:#f9fafb;'>
        <td></td>
        <td style='text-align:right;'>Cantidad</td>
        <td style='text-align:right;'>Dinero ($)</td>
    </tr>
    <tr>
        <td>Saldo inicial</td>
        <td style='text-align:right;'>".number_format($totales['inicial_cant'])."</td>
        <td style='text-align:right;'>$".number_format($totales['inicial_money'], 2)."</td>
    </tr>
    <tr>
        <td>Ingresó (Entradas del período)</td>
        <td style='text-align:right;'>".number_format($totales['entradas_cant'])."</td>
        <td style='text-align:right;'>$".number_format($totales['entradas_money'], 2)."</td>
    </tr>
    <tr>
        <td>Salió (Salidas del período)</td>
        <td style='text-align:right;'>".number_format($totales['salidas_cant'])."</td>
        <td style='text-align:right;'>$".number_format($totales['salidas_money'], 2)."</td>
    </tr>
    <tr style='font-weight:bold;'>
        <td>Disponible al cierre (Saldo final)</td>
        <td style='text-align:right;'>".number_format($totales['final_cant'])."</td>
        <td style='text-align:right;'>$".number_format($totales['final_money'], 2)."</td>
    </tr>
</table>
";

        // ── Resumen por código objeto específico ──────────────────────────
        if (!empty($sumPorCodigo)) {

            $totalSaldoFinalCodigos = 0;

            $html .= "
<br><br>
<table width='100%' border='1' cellspacing='0' cellpadding='4'
       style='border-collapse:collapse; font-size:11px;'>
    <thead style='background:#f2f4f8;'>
        <tr>
            <th style='width:4%;'>#</th>
            <th style='width:10%;'>Código</th>
            <th style='text-align:right; width:6%;'>INICIAL</th>
            <th style='text-align:right; width:10%;'>\$ INICIAL</th>
            <th style='text-align:right; width:6%;'>ENTRADAS</th>
            <th style='text-align:right; width:10%;'>\$ ENTRADAS</th>
            <th style='text-align:right; width:6%;'>SALIDAS</th>
            <th style='text-align:right; width:10%;'>\$ SALIDAS</th>
            <th style='text-align:right; width:6%;'>SALDO</th>
            <th style='text-align:right; width:10%;'>\$ SALDO</th>
        </tr>
    </thead>
    <tbody>
";

            $j = 1;
            foreach ($sumPorCodigo as $s) {

                $totalSaldoFinalCodigos += (float) $s['final_money'];

                $html .= "
<tr>
    <td>{$j}</td>
    <td>".e($s['codigo'])."</td>
    <td style='text-align:right;'>".number_format($s['inicial_cant'])."</td>
    <td style='text-align:right;'>$".number_format($s['inicial_money'],  2)."</td>
    <td style='text-align:right;'>".number_format($s['entradas_cant'])."</td>
    <td style='text-align:right;'>$".number_format($s['entradas_money'], 2)."</td>
    <td style='text-align:right;'>".number_format($s['salidas_cant'])."</td>
    <td style='text-align:right;'>$".number_format($s['salidas_money'],  2)."</td>
    <td style='text-align:right;'>".number_format($s['final_cant'])."</td>
    <td style='text-align:right;'>$".number_format($s['final_money'],    2)."</td>
</tr>
";
                $j++;
            }

            $html .= "
    <tr style='font-weight:bold; background:#f9fafb;'>
        <td colspan='9' style='text-align:right;'>TOTAL</td>
        <td style='text-align:right;'>$".number_format($totalSaldoFinalCodigos, 2)."</td>
    </tr>
    </tbody>
</table>
";
        }




        // ── Observaciones ─────────────────────────────────────────────────
        $html .= "
<br>
<table width='100%' border='1' cellspacing='0' cellpadding='6'
       style='border-collapse:collapse; font-size:11px;'>
    <tr style='background:#f2f4f8;'>
        <td style='font-weight:bold;'>Observaciones: </td>
    </tr>
    <tr>
        <td style='height:50px; font-size: 14px; vertical-align:top;'>$descripcion</td>
    </tr>
</table>
";

        $informacionGeneral = InformacionGeneral::where('id', 1)->first();


        // ── Firma central ─────────────────────────────────────────────────
        $html .= "
<table width='100%' style='border-collapse:collapse; margin-top:{$informacionGeneral->px_sobrantes}px;'>
    <tr>
        <td style='width:33%;'></td>
        <td style='width:34%; height:60px;'></td>
        <td style='width:33%;'></td>
    </tr>
    <tr>
        <td style='width:33%;'></td>
        <td style='width:34%; text-align:center; font-size:12px; border-top:0.8px solid #000; padding-top:6px;'>
            <strong>UNIDAD ESTRUCTURAS METÁLICAS</strong>
        </td>
        <td style='width:33%;'></td>
    </tr>
</table>
";

        $mpdf->setFooter("Página {PAGENO} de {nb}");
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output();
    }








    public function reporteDestinoSobrantes($idtrans, $tipo)
    {
        $infoProyecto  = Tipoproyecto::find($idtrans);
        $fechaGenerado = date("d-m-Y");
        $logoalcaldia  = 'images/logo.png';

        $transferencia = Transferencia::where('id_tipoproyecto', $idtrans)
            ->orderBy('id', 'desc')
            ->first();

        if (!$transferencia) {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER-L']);
            $mpdf->WriteHTML("<p style='font-family:Arial; font-size:14px; color:red;'>
        Este proyecto no tiene registro de cierre generado.</p>", 2);
            $mpdf->Output();
            return;
        }

        $fechaCierre = date("d-m-Y", strtotime($transferencia->fecha));

        $tituloTipo  = $tipo === 'proyecto' ? 'Transferidos a Otro Proyecto' : 'Salida General';
        $colorHeader = $tipo === 'proyecto' ? '#1a5c3a' : '#7a4f1a';
        $colorBg     = $tipo === 'proyecto' ? '#e8f5e9'  : '#fff3e0';

        $detallesSnapshot = TransferenciaDetalle::where('id_transferencia', $transferencia->id)
            ->get();

        $porMaterial = [];

        foreach ($detallesSnapshot as $det) {

            // ── Clave por id_entrada_detalle ──────────────────────────────
            $key = $det->id_entrada_detalle;

            $entradaDet = EntradasDetalle::with('material.unidadMedida', 'material.objetoEspecifico')
                ->find($det->id_entrada_detalle);

            // ── Salidas filtradas por tipo desde salidas_detalle ──────────
            $salidas = SalidasDetalle::where('id_entrada_detalle', $det->id_entrada_detalle)
                ->whereHas('salida', function ($q) use ($idtrans, $tipo) {
                    $q->where('id_tipoproyecto', $idtrans)
                        ->where('es_transferencia', true);

                    if ($tipo === 'proyecto') {
                        // Transferencia a proyecto: tiene proyecto destino
                        $q->whereNotNull('id_tipoproyecto_transferencia');
                    } elseif ($tipo === 'general') {
                        // Salida general: sin proyecto destino
                        $q->whereNull('id_tipoproyecto_transferencia');
                    }
                })
                ->with('salida')
                ->get();

            $cantDespachada = $salidas->sum('cantidad_salida');

            // Si no hay salidas de este tipo, saltar
            if ($cantDespachada <= 0) continue;

            // ── Proyectos destino (solo aplica para tipo = 'proyecto') ────
            $proyectosDestino = '—';
            if ($tipo === 'proyecto') {
                $proyectosDestino = $salidas
                    ->map(fn($sd) => Tipoproyecto::find($sd->salida->id_tipoproyecto_transferencia)?->nombre)
                    ->filter()
                    ->unique()
                    ->implode(', ') ?: '—';
            }

            // ── Fechas de despacho ────────────────────────────────────────
            $fechasDespacho = $salidas
                ->map(fn($sd) => date('d/m/Y', strtotime($sd->salida->fecha)))
                ->unique()
                ->implode(', ') ?: '—';

            if (!isset($porMaterial[$key])) {
                $porMaterial[$key] = [
                    'nombre'            => $entradaDet?->material?->nombre ?? $det->nombre_material ?? '—',
                    'medida'            => $entradaDet?->material?->unidadMedida?->nombre ?? '—',
                    'codigo'            => $entradaDet?->material?->objetoEspecifico?->codigo ?? '—',
                    'cant_sobrante'     => $det->cantidad_sobrante,
                    'cant_despachada'   => 0,
                    'proyectos_destino' => $proyectosDestino,
                    'fechas_despacho'   => $fechasDespacho,
                    'precio'            => $det->precio,
                ];
            }

            $porMaterial[$key]['cant_despachada'] += $cantDespachada;

            // ── Acumular destinos y fechas si hay múltiples entradas ──────
            if ($porMaterial[$key]['proyectos_destino'] === '—' && $proyectosDestino !== '—') {
                $porMaterial[$key]['proyectos_destino'] = $proyectosDestino;
            }
            if ($porMaterial[$key]['fechas_despacho'] === '—' && $fechasDespacho !== '—') {
                $porMaterial[$key]['fechas_despacho'] = $fechasDespacho;
            }
        }

        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        if (empty($porMaterial)) {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER-L']);
            $mpdf->WriteHTML("<p style='font-family:Arial; font-size:14px; color:#888; padding:20px;'>
        No hay registros de <b>{$tituloTipo}</b> para este proyecto.</p>", 2);
            $mpdf->Output();
            return;
        }

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER-L']);
        $mpdf->SetTitle('Destino de Sobrantes - ' . $tituloTipo);
        $mpdf->showImageErrors = false;

        // ── Encabezado ────────────────────────────────────────────────────
        $tabla = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:30%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:38px'>
                    </td>
                    <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
                   padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            CONTROL DE ENTRADAS/SALIDAS
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>GEAD-001-INFO</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>

";






        $tabla .= "
<table width='100%' style='margin-bottom:4px; border-collapse:collapse;'>
    <tr>
        <td style='font-size:13px; padding:4px 0;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </td>
         <td style='font-size:13px; padding:4px 0;'>
            <span style='font-weight:bold;'>Generado:</span> {$infoProyecto->nombre}
        </td>
    </tr>
</table>";

        // ── Tabla de materiales ───────────────────────────────────────────
        $thStyle = "font-weight:bold; font-size:11px; border:0.8px solid #000;
                padding:5px 4px; background:#d9e1f2; text-align:center;";
        $tdStyle = "font-size:11px; border:0.8px solid #000; padding:4px;";
        $tdC     = $tdStyle . " text-align:center;";
        $tdR     = $tdStyle . " text-align:right;";

        $thDestino = $tipo === 'proyecto'
            ? "<th style='{$thStyle} width:20%;'>Proyecto Destino</th>"
            : '';

        $tabla .= "
<table width='100%' style='border-collapse:collapse;'>
    <thead>
        <tr>
            <th style='{$thStyle} width:8%;'>Obj.<br>Espec.</th>
            <th style='{$thStyle} width:25%;'>Material</th>
            <th style='{$thStyle} width:8%;'>Medida</th>
            <th style='{$thStyle} width:9%;'>Cant.<br>Sobrante</th>
            <th style='{$thStyle} width:9%;'>Cant.<br>Despachada</th>
            <th style='{$thStyle} width:10%;'>Fecha(s)<br>Despacho</th>
            {$thDestino}
            <th style='{$thStyle} width:9%;'>Precio<br>Unit.</th>
            <th style='{$thStyle} width:9%;'>Total ($)</th>
        </tr>
    </thead>
    <tbody>";

        foreach ($porMaterial as $mat) {
            $totalLinea = $mat['cant_despachada'] * $mat['precio'];
            $granTotal += $totalLinea;

            $precioFmt = '$ ' . number_format($mat['precio'], 4);
            $totalFmt  = '$ ' . number_format($totalLinea, 4);

            $tdDestino = $tipo === 'proyecto'
                ? "<td style='{$tdStyle} font-size:9px;'>{$mat['proyectos_destino']}</td>"
                : '';

            $tabla .= "
        <tr>
            <td style='{$tdC}'>{$mat['codigo']}</td>
            <td style='{$tdStyle}'>{$mat['nombre']}</td>
            <td style='{$tdC}'>{$mat['medida']}</td>
            <td style='{$tdC}'>{$mat['cant_sobrante']}</td>
            <td style='{$tdC} font-weight:bold; background:{$colorBg};'>{$mat['cant_despachada']}</td>
            <td style='{$tdC} font-size:9px;'>{$mat['fechas_despacho']}</td>
            {$tdDestino}
            <td style='{$tdR}'>{$precioFmt}</td>
            <td style='{$tdR}'>{$totalFmt}</td>
        </tr>";
        }

        $granTotalFmt = '$ ' . number_format($granTotal, 4);
        $colspanTotal = $tipo === 'proyecto' ? 7 : 6;

        $tabla .= "
        <tr>
            <td colspan='{$colspanTotal}' style='font-weight:bold; font-size:12px; text-align:right;
                                    border:0.8px solid #000; padding:5px 4px;'>
                TOTAL GENERAL:
            </td>
            <td colspan='2' style='font-weight:bold; font-size:12px;
                                    border:0.8px solid #000; padding:5px 4px;'>
                {$granTotalFmt}
            </td>
        </tr>
    </tbody>
</table>";

        $informacionGeneral = InformacionGeneral::where('id', 1)->first();

        $tabla .= "
<table width='100%' style='margin-top:30px; border-collapse:collapse;'>
    <tr>
        <td style='width:33%; height:{$informacionGeneral->px_sobrantes}px;'></td>
        <td style='width:33%; height:{$informacionGeneral->px_sobrantes}px;'></td>
        <td style='width:33%; height:{$informacionGeneral->px_sobrantes}px;'></td>
    </tr>
    <tr>
        <td style='width:33%; text-align:center; font-size:13px;
                   border-top:0.8px solid #000; padding-top:4px;'>
            Responsable de Ejecución
        </td>
        <td style='width:33%; text-align:center; font-size:13px;
                   border-top:0.8px solid #000; padding-top:4px;'>
            Supervisor / Jefe Inmediato
        </td>
        <td style='width:33%; text-align:center; font-size:13px;
                   border-top:0.8px solid #000; padding-top:4px;'>
            Bodeguero / Responsable Asignado
        </td>
    </tr>
</table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }











}
