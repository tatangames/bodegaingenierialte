<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
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

class ReportesReingresoController extends Controller
{

    public function pdfReingresos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = TipoProyecto::find($idproy);
        $fechaHoy     = Carbon::now('America/El_Salvador')->format('d-m-Y');
        $logoalcaldia = 'images/logo.png';

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        if (!$sinFecha) {
            $fechaLabel = date('d-m-Y', strtotime($desde)) . '  -  ' . date('d-m-Y', strtotime($hasta));
        } else {
            $fechaLabel = 'Todas las fechas';
        }

        $filas = DB::table('salidas_detalle_reingreso as r')
            ->join('salidas_detalle as sd', 'sd.id', '=', 'r.id_salida_detalle')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->leftJoin('objeto_especifico as obj', 'obj.id', '=', 'm.id_objespecifico')
            ->where('s.id_tipoproyecto', $idproy)
            ->when(!$sinFecha, function ($q) use ($desde, $hasta) {
                $q->whereDate('r.fecha', '>=', $desde)
                    ->whereDate('r.fecha', '<=', $hasta);
            })
            ->select(
                'r.id',
                'r.fecha',
                'r.cantidad',
                'r.justificacion',
                'r.archivo',
                's.id as id_salida',
                's.fecha as fecha_salida',
                'm.id as id_material',
                'um.nombre as medida',
                'ed.precio',
                DB::raw('COALESCE(ed.nombre, m.nombre) as material'),
                DB::raw('COALESCE(obj.codigo, "SIN-CODIGO") as objespec')
            )
            ->orderBy('r.fecha')
            ->orderBy('r.id')
            ->get();

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Reporte de Materiales Reingresados');
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
    <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
        REPORTE DE MATERIALES REINGRESADOS
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
</table><br>

<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:4px;'>
<tr>
    <td style='width:22%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
               font-weight:bold; background:#f5f5f5; vertical-align:top;'>
        PROYECTO
    </td>
    <td style='border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>
        " . e($infoProyecto->nombre ?? '') . "
    </td>
</tr>
</table>

<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px;'>
<tr>
    <td style='width:22%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
               font-weight:bold; background:#f5f5f5;'>
        PERIODO (FECHA DE REINGRESO)
    </td>
    <td style='width:43%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>
        {$fechaLabel}
    </td>
    <td style='width:20%;'></td>
    <td style='width:7%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
               font-weight:bold; background:#f5f5f5; text-align:center;'>
        FECHA
    </td>
    <td style='width:8%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; text-align:center;'>
        {$fechaHoy}
    </td>
</tr>
</table>";

        if ($filas->isEmpty()) {
            $tabla .= "<p style='font-family:Arial; font-size:13px; color:#888; padding:20px; text-align:center;'>
            No hay reingresos para este proyecto en el rango seleccionado.</p>";
        }
        // ─── TIPO 1: JUNTOS ───────────────────────────────────────────
        elseif ($tipo == 1) {

            $dataArray     = [];
            $totalCantidad = 0;

            foreach ($filas as $f) {
                $precio = (float) $f->precio;
                $clave  = $f->id_material . '|' . number_format($precio, 4, '.', '');

                if (!isset($dataArray[$clave])) {
                    $dataArray[$clave] = [
                        'nombre'   => $f->material,
                        'medida'   => $f->medida ?? '',
                        'objespec' => $f->objespec,
                        'precio'   => $precio,
                        'cantidad' => 0,
                        'total'    => 0,
                    ];
                }

                $dataArray[$clave]['cantidad'] += $f->cantidad;
                $dataArray[$clave]['total']    += $f->cantidad * $precio;
                $totalCantidad                 += $f->cantidad;
            }

            usort($dataArray, function ($a, $b) {
                $cmp = strcmp($a['objespec'], $b['objespec']);
                return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
            });

            $granTotal = array_sum(array_column($dataArray, 'total'));

            $tabla .= "
        <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:11%; font-size:13px;'>Obj. Espec.</td>
                <td style='font-weight:bold; width:31%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:11%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Total ($)</td>
            </tr>";

            $codigoActual    = null;
            $subtotalCodigo  = 0;
            $subtotalCantCod = 0;

            $imprimirSubtotal = function ($codigo, $cantidad, $monto) {
                return "
                <tr>
                    <td colspan='3' style='font-weight:bold; font-size:12px; text-align:right;
                                            background:#f2f4f8; padding:4px;'>
                        SUBTOTAL [" . e($codigo) . "]
                    </td>
                    <td style='font-weight:bold; font-size:12px; background:#f2f4f8; padding:4px;'>
                        " . number_format($cantidad, 2, '.', ',') . "
                    </td>
                    <td style='background:#f2f4f8;'></td>
                    <td style='font-weight:bold; font-size:12px; background:#f2f4f8; padding:4px;'>
                        $ " . number_format($monto, 4) . "
                    </td>
                </tr>";
            };

            foreach ($dataArray as $info) {

                if ($codigoActual !== null && $info['objespec'] !== $codigoActual) {
                    $tabla .= $imprimirSubtotal($codigoActual, $subtotalCantCod, $subtotalCodigo);
                    $subtotalCodigo  = 0;
                    $subtotalCantCod = 0;
                }
                $codigoActual = $info['objespec'];

                $subtotalCodigo  += $info['total'];
                $subtotalCantCod += $info['cantidad'];

                $tabla .= "
    <tr>
        <td style='font-size:12px;'>" . e($info['objespec']) . "</td>
        <td style='text-align:left; font-size:12px;'>" . e($info['nombre']) . "</td>
        <td style='font-size:12px;'>" . e($info['medida']) . "</td>
        <td style='font-size:12px;'>{$info['cantidad']}</td>
        <td style='font-size:12px;'>$ " . number_format($info['precio'], 4) . "</td>
        <td style='font-size:12px;'>$ " . number_format($info['total'], 4) . "</td>
    </tr>";
            }

            if ($codigoActual !== null) {
                $tabla .= $imprimirSubtotal($codigoActual, $subtotalCantCod, $subtotalCodigo);
            }

            $tabla .= "
    <tr>
        <td colspan='3' style='font-weight:bold; font-size:13px; text-align:right;
                                border-top:1.5px solid #000; padding-top:4px;'>
            TOTAL CANTIDAD:
        </td>
        <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
            " . number_format($totalCantidad, 2, '.', ',') . "
        </td>
        <td style='font-weight:bold; font-size:13px; text-align:right;
                    border-top:1.5px solid #000; padding-top:4px;'>
            TOTAL GENERAL:
        </td>
        <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
            $ " . number_format($granTotal, 4) . "
        </td>
    </tr>
</tbody>
</table>";

            // ─── TIPO 2: SEPARADOS ────────────────────────────────────────
        } else {

            $granTotal     = 0;
            $totalCantidad = 0;

            foreach ($filas as $f) {

                $total          = $f->cantidad * $f->precio;
                $granTotal     += $total;
                $totalCantidad += $f->cantidad;

                $tabla .= "
<table width='100%' id='tablaFor'>
<tbody>
    <tr>
        <td style='font-weight:bold; width:15%; font-size:13px;'>Reingreso</td>
        <td style='font-weight:bold; width:25%; font-size:13px;'>Salida de origen</td>
        <td style='font-weight:bold; width:60%; font-size:13px;'>Justificación</td>
    </tr>
    <tr>
        <td style='font-size:12px;'>" . date('d-m-Y', strtotime($f->fecha)) . "</td>
        <td style='font-size:12px;'>Fecha: " . date('d-m-Y', strtotime($f->fecha_salida)) . ")</td>
        <td style='font-size:12px;'>" . e($f->justificacion) . "</td>
    </tr>
</tbody>
</table>

<table width='100%' id='tablaFor'>
<tbody>
    <tr>
        <td style='font-weight:bold; width:12%; font-size:13px;'>Código</td>
        <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
        <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
        <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
        <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
        <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
    </tr>
    <tr>
        <td style='font-size:12px;'>" . e($f->objespec) . "</td>
        <td style='font-size:12px;'>" . e($f->medida ?? '') . "</td>
        <td style='font-size:12px;'>" . e($f->material) . "</td>
        <td style='font-size:12px;'>{$f->cantidad}</td>
        <td style='font-size:12px;'>$ " . number_format($f->precio, 4) . "</td>
        <td style='font-size:12px;'>$ " . number_format($total, 4) . "</td>
    </tr>
</tbody>
</table><br>";
            }

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
            " . number_format($totalCantidad, 2, '.', ',') . "
        </td>
        <td style='font-weight:bold; font-size:14px; text-align:right;
                    border-top:2px solid #000; padding-top:6px;'>
            TOTAL GENERAL:&nbsp;&nbsp;
        </td>
        <td style='font-weight:bold; font-size:14px; width:18%;
                    border-top:2px solid #000; padding-top:6px;'>
            $ " . number_format($granTotal, 4) . "
        </td>
    </tr>
</tbody>
</table>";
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }



}
