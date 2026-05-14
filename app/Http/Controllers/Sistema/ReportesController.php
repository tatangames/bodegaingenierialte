<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\HistorialEntradas;
use App\Models\HistorialEntradasDeta;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\HistorialTransferido;
use App\Models\HistorialTransferidoDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
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

        $desdeFormat = $sinFiltro ? '' : date("d-m-Y", strtotime($desde));
        $hastaFormat = $sinFiltro ? '' : date("d-m-Y", strtotime($hasta));

        $logoalcaldia = 'images/logo.png';
        $periodoTexto = $sinFiltro ? 'Todo el historial' : "Fecha: $desdeFormat  -  $hastaFormat";

        // ── ENTRADAS ──────────────────────────────────────────────────
        if ($tipo == 1) {

            $query = Entradas::with([
                'tipoproyecto',
                'detalle.material.unidadMedida',
            ])->orderBy('fecha', 'ASC');

            if (!$sinFiltro) {
                $start = date('Y-m-d 00:00:00', strtotime($desde));
                $end   = date('Y-m-d 23:59:59', strtotime($hasta));
                $query->whereBetween('fecha', [$start, $end]);
            }

            $listaEntrada = $query->get();

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Entradas');
            $mpdf->showImageErrors = false;

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c; font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:1px solid #000; padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Entradas</h2>
                    <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
                </td>
            </tr>
        </table>
        ";

            foreach ($listaEntrada as $entrada) {

                $fechaFmt   = date("d-m-Y", strtotime($entrada->fecha));
                $proyecto   = $entrada->tipoproyecto->nombre ?? '';
                $descripcion = $entrada->descripcion ?? '';
                $factura    = $entrada->factura ?? '';

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
                <tbody>
                    <tr>
                        <td style='width:15%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                        <td style='width:15%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Factura</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='width:15%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$fechaFmt</td>
                        <td style='width:15%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$factura</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$proyecto</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$descripcion</td>
                    </tr>
                </tbody>
            </table>

            <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $totalGeneral = 0;

                foreach ($entrada->detalle as $det) {
                    $codigo       = $det->codigo ?? '';
                    $nombreMat    = $det->material->nombre ?? '';
                    $medida       = $det->material->unidadMedida->nombre ?? '';
                    $cantidad     = $det->cantidad_inicial;
                    $precio       = $det->precio;
                    $total        = $cantidad * $precio;
                    $totalGeneral += $total;

                    $precioFormat = number_format($precio, 4);
                    $totalFormat  = number_format($total,  4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$nombreMat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$cantidad</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 4);

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

            // ── SALIDAS ───────────────────────────────────────────────────
        } else {

            $query = Salidas::with([
                'tipoproyecto',
                'detalle.entradaDetalle.material.unidadMedida',
            ])->orderBy('fecha', 'ASC');

            if (!$sinFiltro) {
                $start = date('Y-m-d 00:00:00', strtotime($desde));
                $end   = date('Y-m-d 23:59:59', strtotime($hasta));
                $query->whereBetween('fecha', [$start, $end]);
            }

            $listaSalida = $query->get();

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Salidas');
            $mpdf->showImageErrors = false;

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c; font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:1px solid #000; padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Salidas</h2>
                    <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
                </td>
            </tr>
        </table>
        ";

            foreach ($listaSalida as $salida) {

                $fechaFmt    = date("d-m-Y", strtotime($salida->fecha));
                $proyecto    = $salida->tipoproyecto->nombre ?? '';
                $descripcion = $salida->descripcion ?? '';

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
                <tbody>
                    <tr>
                        <td style='width:20%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                        <td style='width:45%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='width:20%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$fechaFmt</td>
                        <td style='width:45%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$proyecto</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$descripcion</td>
                    </tr>
                </tbody>
            </table>

            <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $totalGeneral = 0;

                foreach ($salida->detalle as $det) {
                    $entDet       = $det->entradaDetalle;
                    $codigo       = $entDet->codigo ?? '';
                    $nombreMat    = $entDet->material->nombre ?? '';
                    $medida       = $entDet->material->unidadMedida->nombre ?? '';
                    $cantidad     = $det->cantidad_salida;
                    $precio       = $entDet->precio ?? 0;
                    $total        = $cantidad * $precio;
                    $totalGeneral += $total;

                    $precioFormat = number_format($precio, 4);
                    $totalFormat  = number_format($total,  4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$nombreMat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$cantidad</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 4);

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
        $logoalcaldia = 'images/logo.png';
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->showImageErrors = false;

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
                <h2 style='margin:0;'>Inventario de Materiales</h2>
            </td>
        </tr>
    </table>";

        // ── TIPO 1: INVENTARIO GENERAL ────────────────────────────────
        if ($tipo == 1) {

            $mpdf->SetTitle('Inventario General');

            $materiales = Materiales::with('unidadMedida')
                ->orderBy('nombre')
                ->get();

            $granTotal = 0;
            $tabla = $encabezado;

            $tabla .= "
        <table width='100%' style='border-collapse:collapse; margin-top:10px;'>
            <thead>
                <tr>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Entradas</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Salidas</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Stock</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total ($)</td>
                </tr>
            </thead>
            <tbody>
        ";

            foreach ($materiales as $mat) {

                $idsDetalle = EntradasDetalle::where('id_material', $mat->id)->pluck('id');

                $totalEntradas = EntradasDetalle::where('id_material', $mat->id)
                    ->sum('cantidad_inicial');

                $totalSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                    ->sum('cantidad_salida');

                $stock = $totalEntradas - $totalSalidas;

                if ($stock <= 0) continue;

                $ultimoDetalle = EntradasDetalle::where('id_material', $mat->id)
                    ->orderBy('id', 'DESC')
                    ->first();

                $precioUnit   = $ultimoDetalle->precio ?? 0;
                $totalLinea   = $stock * $precioUnit;
                $granTotal   += $totalLinea;

                $codigo       = '';
                $medida       = $mat->unidadMedida->nombre ?? '';  // ← corregido
                $precioFormat = number_format($precioUnit, 4);
                $totalFormat  = number_format($totalLinea, 4);

                $tabla .= "
                <tr>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat->nombre</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$totalEntradas</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$totalSalidas</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$stock</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                </tr>
            ";
            }

            $granTotalFmt = number_format($granTotal, 4);

            $tabla .= "
                <tr>
                    <td colspan='7' style='font-weight:bold; font-size:12px; padding:4px 6px;
                                           text-align:right; border:1px solid #000;'>
                        TOTAL GENERAL:
                    </td>
                    <td style='font-weight:bold; font-size:12px; padding:4px 6px;
                                text-align:right; border:1px solid #000;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";

            // ── TIPO 2: INVENTARIO POR PROYECTO ──────────────────────────
        } else {

            $mpdf->SetTitle('Inventario por Proyecto');

            $proyectos = Tipoproyecto::orderBy('nombre')->get();

            $granTotal = 0;
            $tabla = $encabezado;

            foreach ($proyectos as $proyecto) {

                $detalles = EntradasDetalle::with('material.unidadMedida')  // ← corregido
                ->whereHas('entrada', fn($q) => $q->where('id_tipoproyecto', $proyecto->id))
                    ->get();

                if ($detalles->isEmpty()) continue;

                $porMaterial = [];

                foreach ($detalles as $det) {
                    $idMat = $det->id_material;

                    if (!isset($porMaterial[$idMat])) {
                        $porMaterial[$idMat] = [
                            'nombre'   => $det->material->nombre ?? '',
                            'medida'   => $det->material->unidadMedida->nombre ?? '',  // ← corregido
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

                $porMaterial = array_filter($porMaterial, fn($m) => ($m['entradas'] - $m['salidas']) > 0);

                if (empty($porMaterial)) continue;

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px; margin-top:12px;'>
                <tr>
                    <td style='font-weight:bold; font-size:13px; padding:4px 6px;
                                border:1px solid #000; background:#e8eef8;'>
                        Proyecto: $proyecto->nombre
                    </td>
                </tr>
            </table>

            <table width='100%' style='border-collapse:collapse; margin-bottom:8px;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:13%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Entradas</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Salidas</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Stock</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total ($)</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $subtotal = 0;

                foreach ($porMaterial as $mat) {
                    $stock      = $mat['entradas'] - $mat['salidas'];
                    $totalLinea = $stock * $mat['precio'];
                    $subtotal  += $totalLinea;
                    $granTotal += $totalLinea;

                    $precioFormat = number_format($mat['precio'], 4);
                    $totalFormat  = number_format($totalLinea, 4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[codigo]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[nombre]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[medida]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$mat[entradas]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$mat[salidas]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$stock</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $subtotalFmt = number_format($subtotal, 4);

                $tabla .= "
                    <tr>
                        <td colspan='7' style='font-weight:bold; font-size:12px; padding:4px 6px;
                                               text-align:right; border:1px solid #000;'>
                            Subtotal $proyecto->nombre:
                        </td>
                        <td style='font-weight:bold; font-size:12px; padding:4px 6px;
                                    text-align:right; border:1px solid #000;'>
                            $ $subtotalFmt
                        </td>
                    </tr>
                </tbody>
            </table>
            ";
            }

            $granTotalFmt = number_format($granTotal, 4);

            $tabla .= "
        <table width='100%' style='margin-top:10px;'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; font-size:14px; text-align:right;
                                border-top:2px solid #000; padding-top:6px;'>
                        TOTAL GENERAL:&nbsp;&nbsp;
                    </td>
                    <td style='font-weight:bold; font-size:14px; width:15%;
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

    public function vistaQueHaSalidoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquehasalidoproyecto', compact('proyectos'));
    }


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

            // ── Conteo total de salidas ──────────────────────────────
            $totalSalidas = $idsSalidas->count();

            $detalles = SalidasDetalle::with('entradaDetalle.material.unidadMedida')
                ->whereIn('id_salida', $idsSalidas)
                ->get();

            // Agrupar por material
            $dataArray      = [];
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
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:13px;'>Cantidad</td>
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
            ])->where('id_tipoproyecto', $idproy);

            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }

            $arraySalidas = $query->orderBy('fecha', 'ASC')->get();

            // ── Conteo total de salidas ──────────────────────────────
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

                $fechaFmt    = date("d-m-Y", strtotime($salida->fecha));
                $descripcion = $salida->descripcion ?? '';

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
                    $entDet = $det->entradaDetalle;
                    if (!$entDet || !$entDet->material) continue;

                    $codigo    = $entDet->codigo ?? '';
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
        // Proyectos activos (no transferidos/cerrados)
        $proyectos = Tipoproyecto::orderBy('nombre', 'ASC')
            ->where('transferido', 0)
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquetengoporproyecto', compact('proyectos'));
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
                <td style='font-weight:bold; width:12%; font-size:13px;'>Código</td>
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
        $infoProyecto = Tipoproyecto::find($idtrans);
        $fechaGenerado = date("d-m-Y");
        $logoalcaldia  = 'images/logo.png';

        // Entradas_detalle del proyecto con material y medida
        $detalles = EntradasDetalle::with('material.unidadMedida')
            ->whereHas('entrada', fn($q) => $q->where('id_tipoproyecto', $idtrans))
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

        // Solo materiales con stock > 0 (sobrantes)
        $porMaterial = array_filter($porMaterial, fn($m) => ($m['entradas'] - $m['salidas']) > 0);

        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Reporte de Proyecto Completado');
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
                <h2 style='margin:0; font-size:15px;'>Reporte de Proyecto Completado</h2>
                <p style='margin:0; font-size:13px;'>Fecha: $fechaGenerado</p>
            </td>
        </tr>
    </table>";

        $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

        $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:14%; font-size:13px;'>Código</td>
                <td style='font-weight:bold; width:38%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:14%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:10%; font-size:13px;'>Stock</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Total ($)</td>
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

    public function vistaSalidaPorMaterial(){

        $arrayMateriales = Materiales::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistasalidapormaterial', compact('arrayMateriales'));
    }



    public function pdfReporteMaterialesSalidas($desde, $hasta, $materiales)
    {
        $porciones = explode("-", $materiales);

        $start = date('Y-m-d 00:00:00', strtotime($desde));
        $end   = date('Y-m-d 23:59:59', strtotime($hasta));

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $logoalcaldia = 'images/logo.png';

        $idsEntradaDetalle = EntradasDetalle::whereIn('id_material', $porciones)->pluck('id');

        $idsSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsEntradaDetalle)
            ->pluck('id_salida')
            ->unique();

        $arraySalidas = Salidas::with('tipoproyecto')
            ->whereIn('id', $idsSalidas)
            ->whereBetween('fecha', [$start, $end])
            ->orderBy('fecha', 'ASC')
            ->get();

        $idsSalidasFiltradas = $arraySalidas->pluck('id');

        // Resumen total por material
        $arrayMaterial = Materiales::with('unidadMedida')->whereIn('id', $porciones)->get();

        $dataArray = [];
        foreach ($arrayMaterial as $mat) {
            $idsDetalleMat = EntradasDetalle::where('id_material', $mat->id)->pluck('id');

            $cantidadTotal = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalleMat)
                ->whereIn('id_salida', $idsSalidasFiltradas)
                ->sum('cantidad_salida');

            if ($cantidadTotal <= 0) continue;

            // Precio: último precio registrado en entradas_detalle
            $ultimoDetalle = EntradasDetalle::where('id_material', $mat->id)
                ->orderBy('id', 'DESC')
                ->first();

            $precio     = $ultimoDetalle->precio ?? 0;
            $total      = $cantidadTotal * $precio;

            $dataArray[] = [
                'nombre'        => $mat->nombre,
                'medida'        => $mat->unidadMedida->nombre ?? '',
                'cantidadtotal' => $cantidadTotal,
                'precio'        => $precio,
                'total'         => $total,
            ];
        }

        $granTotal = array_sum(array_column($dataArray, 'total'));

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Salida Por Materiales');
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
                <h2 style='margin:0;'>Reporte Salida de Materiales</h2>
                <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>
            </td>
        </tr>
    </table>";

        foreach ($arraySalidas as $salida) {

            $fechaFmt    = date("d-m-Y", strtotime($salida->fecha));
            $nombreProy  = $salida->tipoproyecto->nombre ?? '';
            $descripcion = $salida->descripcion ?? '';

            $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:15%; font-size:12px;'>Fecha</td>
                    <td style='font-weight:bold; width:50%; font-size:12px;'>Proyecto</td>
                    <td style='font-weight:bold; width:35%; font-size:12px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='font-size:12px;'>$fechaFmt</td>
                    <td style='font-size:12px;'>$nombreProy</td>
                    <td style='font-size:12px;'>$descripcion</td>
                </tr>
            </tbody>
        </table>";

            $idsDetalleMateriales = EntradasDetalle::whereIn('id_material', $porciones)->pluck('id');

            $detalle = SalidasDetalle::with('entradaDetalle.material.unidadMedida')
                ->where('id_salida', $salida->id)
                ->whereIn('id_entrada_detalle', $idsDetalleMateriales)
                ->get()
                ->groupBy(fn($d) => $d->entradaDetalle->id_material ?? 0)
                ->map(fn($grupo) => [
                    'nombre'   => $grupo->first()->entradaDetalle->material->nombre ?? '',
                    'medida'   => $grupo->first()->entradaDetalle->material->unidadMedida->nombre ?? '',
                    'cantidad' => $grupo->sum('cantidad_salida'),
                    'precio'   => $grupo->first()->entradaDetalle->precio ?? 0,
                    'total'    => $grupo->sum(fn($d) => $d->cantidad_salida * ($d->entradaDetalle->precio ?? 0)),
                ]);

            $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:35%; font-size:12px;'>Material</td>
                    <td style='font-weight:bold; width:13%; font-size:12px;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:12px;'>Cantidad</td>
                    <td style='font-weight:bold; width:14%; font-size:12px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:14%; font-size:12px;'>Total ($)</td>
                </tr>";

            foreach ($detalle as $det) {
                $precioFmt = number_format($det['precio'], 4);
                $totalFmt  = number_format($det['total'], 4);

                $tabla .= "
                <tr>
                    <td style='font-size:12px;'>{$det['nombre']}</td>
                    <td style='font-size:12px;'>{$det['medida']}</td>
                    <td style='font-size:12px;'>{$det['cantidad']}</td>
                    <td style='font-size:12px;'>$ $precioFmt</td>
                    <td style='font-size:12px;'>$ $totalFmt</td>
                </tr>";
            }

            $tabla .= "
            </tbody>
        </table>";
        }

        // ── Resumen total ─────────────────────────────────────────────
        $granTotalFmt = number_format($granTotal, 4);

        $tabla .= "<p style='font-weight:bold; margin-top:30px;'>MATERIALES ENTREGADOS</p>";

        $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:10%; font-size:13px;'>Cantidad</td>
                <td style='font-weight:bold; width:14%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:14%; font-size:13px;'>Total ($)</td>
            </tr>";

        foreach ($dataArray as $info) {
            $precioFmt = number_format($info['precio'], 4);
            $totalFmt  = number_format($info['total'], 2);
            $cantFmt   = number_format($info['cantidadtotal'], 2, '.', ',');

            $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$info['nombre']}</td>
                <td style='font-size:12px;'>{$info['medida']}</td>
                <td style='font-size:12px;'>$cantFmt</td>
                <td style='font-size:12px;'>$ $precioFmt</td>
                <td style='font-size:12px;'>$ $totalFmt</td>
            </tr>";
        }

        $tabla .= "
            <tr>
                <td colspan='4' style='font-weight:bold; font-size:13px; text-align:right;
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


    public function vistaQueHaEntradoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
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
                        'codigo'         => $det->codigo ?? '',
                        'cantidad'       => 0,
                        'totalMaterial'  => 0,
                        'precioUnitario' => 0,
                    ];
                }

                $dataArray[$idMat]['cantidad']      += $det->cantidad_inicial;
                $dataArray[$idMat]['totalMaterial']  += ($det->precio * $det->cantidad_inicial);
                $dataArray[$idMat]['precioUnitario']  = $det->precio;
            }

            usort($dataArray, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

            foreach ($dataArray as $item) {
                $granTotal += $item['totalMaterial'];
            }

            $granTotalFmt    = number_format($granTotal, 2);
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
                <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
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

                $fechaFmt    = date("d-m-Y", strtotime($entrada->fecha));
                $descripcion = $entrada->descripcion ?? '';
                $factura     = $entrada->factura ?? '';

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

                    $codigo      = $det->codigo ?? '';
                    $nombreMat   = $det->material->nombre ?? '';
                    $medida      = $det->material->unidadMedida->nombre ?? '';
                    $precioFmt   = number_format($det->precio, 4);
                    $totalFmt    = number_format($totalLinea, 4);

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



    public function vistaEntradaPorMaterialProyecto()
    {
        // necesito todos los proyectos, ya que solo es reporte
        $arrayMateriales = Materiales::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaentradamaterialproyecto', compact('arrayMateriales'));
    }

    public function pdfReporteMaterialesEntradaProyecto($desde, $hasta, $materiales)
    {
        $porciones = explode("-", $materiales);

        $start = date('Y-m-d 00:00:00', strtotime($desde));
        $end   = date('Y-m-d 23:59:59', strtotime($hasta));

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $logoalcaldia = public_path('images/logo.png');

        $arrayMaterial = Materiales::with('unidadMedida')
            ->whereIn('id', $porciones)
            ->orderBy('nombre', 'ASC')
            ->get();

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format' => 'LETTER'
        ]);

        $mpdf->SetTitle('Entrada Material Por Proyecto');
        $mpdf->showImageErrors = false;

        $tabla = "

    <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>

        <tr>

            <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>

                <table width='100%'>

                    <tr>

                        <td style='width:35%; text-align:left;'>
                            <img src='{$logoalcaldia}' style='height:45px'>
                        </td>

                        <td style='width:65%; text-align:left; color:#104e8c;
                                    font-size:12px; font-weight:bold; line-height:1.4;'>

                            SANTA ANA NORTE<br>
                            EL SALVADOR

                        </td>

                    </tr>

                </table>

            </td>

            <td style='width:70%; border:0.8px solid #000;
                        padding:8px; text-align:center; vertical-align:middle;'>

                <h2 style='margin:0;'>
                    REPORTE ENTRADA MATERIAL POR PROYECTO
                </h2>

                <p style='margin:0; font-size:12px;'>
                    Fecha: $desdeFormat - $hastaFormat
                </p>

            </td>

        </tr>

    </table>";


        foreach ($arrayMaterial as $material) {

            $detallesEntrada = EntradasDetalle::with([
                'entrada.tipoproyecto',
                'material.unidadMedida'
            ])
                ->where('id_material', $material->id)
                ->whereHas('entrada', function ($q) use ($start, $end) {
                    $q->whereBetween('fecha', [$start, $end]);
                })
                ->orderBy('id', 'ASC')
                ->get();

            if ($detallesEntrada->isEmpty()) {
                continue;
            }

            $tabla .= "

        <div style='margin-top:25px;'></div>

        <table width='100%' style='border-collapse:collapse; margin-bottom:10px;'>

            <tr>

                <td style='font-size:15px;
                           font-weight:bold;
                           background:#eaeaea;
                           padding:6px;'>

                    MATERIAL:
                    {$material->nombre}

                </td>

            </tr>

        </table>";

            $tabla .= "

        <table width='100%' id='tablaFor'>

            <thead>

                <tr>

                    <td style='font-weight:bold; font-size:12px; width:15%;'>
                        Fecha
                    </td>

                    <td style='font-weight:bold; font-size:12px; width:35%;'>
                        Proyecto
                    </td>

                    <td style='font-weight:bold; font-size:12px; width:20%;'>
                        Cantidad
                    </td>

                    <td style='font-weight:bold; font-size:12px; width:15%;'>
                        Precio Unit.
                    </td>

                    <td style='font-weight:bold; font-size:12px; width:15%;'>
                        Total
                    </td>

                </tr>

            </thead>

            <tbody>";

            $cantidadTotal = 0;
            $totalMaterial = 0;

            foreach ($detallesEntrada as $detalle) {

                $entrada = $detalle->entrada;

                $fecha = date('d-m-Y', strtotime($entrada->fecha));

                $proyecto = $entrada->tipoproyecto->nombre ?? 'Sin Proyecto';

                $cantidad = $detalle->cantidad_inicial;

                $precio = $detalle->precio;

                $total = $cantidad * $precio;

                $cantidadTotal += $cantidad;
                $totalMaterial += $total;

                $cantidadFmt = number_format($cantidad, 2, '.', ',');
                $precioFmt = number_format($precio, 4);
                $totalFmt = number_format($total, 2);

                $tabla .= "

            <tr>

                <td style='font-size:12px;'>
                    {$fecha}
                </td>

                <td style='font-size:12px;'>
                    {$proyecto}
                </td>

                <td style='font-size:12px;'>
                    {$cantidadFmt}
                </td>

                <td style='font-size:12px;'>
                    $ {$precioFmt}
                </td>

                <td style='font-size:12px;'>
                    $ {$totalFmt}
                </td>

            </tr>";
            }

            $cantidadTotalFmt = number_format($cantidadTotal, 2, '.', ',');
            $totalMaterialFmt = number_format($totalMaterial, 2);

            $tabla .= "

            <tr>

                <td colspan='2'
                    style='font-weight:bold;
                           text-align:right;
                           font-size:12px;
                           border-top:1px solid #000;
                           padding-top:4px;'>

                    TOTAL MATERIAL:

                </td>

                <td style='font-weight:bold;
                           font-size:12px;
                           border-top:1px solid #000;
                           padding-top:4px;'>

                    {$cantidadTotalFmt}

                </td>

                <td></td>

                <td style='font-weight:bold;
                           font-size:12px;
                           border-top:1px solid #000;
                           padding-top:4px;'>

                    $ {$totalMaterialFmt}

                </td>

            </tr>

            </tbody>

        </table>";
        }




        $stylesheet = file_get_contents(public_path('css/cssregistro.css'));

        $mpdf->WriteHTML($stylesheet, 1);

        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');

        $mpdf->WriteHTML($tabla, 2);

        $mpdf->Output();
    }



}
