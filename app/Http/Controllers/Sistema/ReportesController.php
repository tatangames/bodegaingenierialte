<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\HistorialEntradas;
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

    public function reportePdfEntradaSalida($tipo, $desde, $hasta){

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $resultsBloque = array();
        $index = 0;

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));


        // entrada
        if($tipo == 1) {

            // lista de entradas
            $listaEntrada = HistorialEntradas::whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($listaEntrada as $ll){

                $ll->fecha = date("d-m-Y", strtotime($ll->fecha));

                $infoProyecto = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();

                $ll->nombreproy = $infoProyecto->nombre;

                array_push($resultsBloque, $ll);

                // obtener detalle
                $listaDetalle = DB::table('historial_entradas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->select('m.nombre', 'm.codigo', 'ed.cantidad', 'm.id_medida')
                    ->where('ed.id_historial', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd){
                    if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                        $dd->medida = $info->nombre;
                    }else{
                        $dd->medida = "";
                    }
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }


            //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Entradas');

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

                            <h2 style='margin:0;'>Reporte de Entradas</h2>
                            <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>

                        </td>
                    </tr>
                </table>
                ";


            foreach ($listaEntrada as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>";

                $tabla .= "<tr>
                    <td style='font-weight: bold; width: 20%; font-size: 13px'>Fecha</td>
                     <td style='font-weight: bold; width: 45%; font-size: 13px'>Proyecto</td>
                     <td style='font-weight: bold; width: 15%; font-size: 13px'>Descripción</td>
                </tr>
                ";

                $tabla .= "<tr>
                    <td style='font-size: 12px'>$dd->fecha</td>
                     <td style='font-size: 12px'>$dd->nombreproy</td>
                     <td style='font-size: 12px'>$dd->descripcion</td>
                </tr>
                ";


                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top: 20px'>
            <tbody>";

                $tabla .= "<tr>
                    <td style='font-weight: bold; width: 25%; font-size: 13px'>Repuesto</td>
                    <td style='font-weight: bold; width: 8%; font-size: 13px'>Medida</td>
                    <td style='font-weight: bold; width: 8%; font-size: 13px'>Cantidad</td>
                </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "<tr>
                    <td style='font-size: 12px'>$gg->nombre</td>
                    <td style='font-size: 12px'>$gg->medida</td>
                    <td style='font-size: 12px'>$gg->cantidad</td>
                </tr>";
                }

                $tabla .= "</tbody></table>";
            }


            $tabla .= "<table width='100%' id='tablaFor'>
            <tbody>";

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet,1);

            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            //$mpdf->WriteHTML($tabla,2);
            $mpdf->WriteHTML($tabla, 2);

            $mpdf->Output();

        }else {
            // salida

            // lista de salidas
            $listaSalida = HistorialSalidas::whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($listaSalida as $ll){

                $infoProyecto = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();

                $ll->nombreproy = $infoProyecto->nombre;

                $ll->fecha = date("d-m-Y", strtotime($ll->fecha));

                array_push($resultsBloque, $ll);

                // obtener detalle
                $listaDetalle = DB::table('historial_salidas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->select( 'm.id', 'm.nombre', 'm.codigo', 'ed.cantidad', 'm.id_medida', 'ed.id_historial_salidas')
                    ->where('ed.id_historial_salidas', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd){
                    if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                        $dd->medida = $info->nombre;
                    }else{
                        $dd->medida = "";
                    }
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }


            //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Salidas');

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

                            <h2 style='margin:0;'>Reporte de Salidas</h2>
                            <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>

                        </td>
                    </tr>
                </table>
                ";

            foreach ($listaSalida as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>";

                $tabla .= "<tr>
                     <td  style='width: 20%; font-size: 13px; font-weight: bold'>Fecha</td>
                     <td  style='width: 45%; font-size: 13px; font-weight: bold'>Proyecto</td>
                     <td  style='width: 15%; font-size: 13px; font-weight: bold'>Descripción</td>
                </tr>
                ";

                $tabla .= "<tr>
                    <td style='width: 20%; font-size: 12px'>$dd->fecha</td>
                     <td style='width: 45%; font-size: 12px'>$dd->nombreproy</td>
                     <td style='width: 15%; font-size: 12px'>$dd->descripcion</td>
                </tr>
                ";


                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top: 20px'>
            <tbody>";

                $tabla .= "<tr>
                    <td style='width: 25%; font-size: 13px; font-weight: bold'>Repuesto</td>
                    <td style='width: 8%; font-size: 13px; font-weight: bold'>Medida</td>
                    <td style='width: 20%; font-size: 13px; font-weight: bold'>Cantidad</td>
                </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "<tr>
                        <td style='width: 25%; font-size: 12px'>$gg->nombre</td>
                        <td style='width: 8%; font-size: 12px'>$gg->medida</td>
                        <td style='width: 20%; font-size: 12px'>$gg->cantidad</td>
                    </tr>";
                }

                $tabla .= "</tbody></table>";
            }


            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet,1);

            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla,2);

            $mpdf->Output();
        }
    }







    public function vistaParaReporteInventario(){
        return view('backend.admin.repuestos.reporte.vistareporteinventario');
    }

    public function reporteInventarioActual($tipo){
        // JUNTOS
        if($tipo == 1){

            $lista = Materiales::orderBy('nombre', 'ASC')->get();

            foreach ($lista as $item) {
                $medida = '';
                if($dataUnidad = UnidadMedida::where('id', $item->id_medida)->first()){
                    $medida = $dataUnidad->nombre;
                }

                $item->medida = $medida;

                $arrayEntradas = Entradas::where('id_material', $item->id)->get();

                $sumatoria = 0;
                foreach ($arrayEntradas as $data){
                    $sumatoria += $data->cantidad;
                }

                $item->total = $sumatoria;
            }

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
            </table>
        ";

            $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>";

            $tabla .= "<tr>
                <td style='font-weight: bold; width: 15%; font-size: 13px'>Código</td>
                <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad</td>
            <tr>";

                foreach ($lista as $info) {
                    if($info->total > 0){
                        $tabla .= "<tr>
                        <td style='font-size: 12px'>$info->codigo</td>
                        <td style='font-size: 12px'>$info->nombre</td>
                        <td style='font-size: 12px'>$info->total</td>
                    <tr>";
                    }
                }

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

            foreach ($listaProy as $dato){
                array_push($resultsBloque, $dato);

                $arrayEntradas = Entradas::where('id_tipoproyecto', $dato->id)->get();

                foreach ($arrayEntradas as $info){
                    $infoMate   = Materiales::where('id', $info->id_material)->first();
                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $info->nombremate = $infoMate->nombre;
                    $info->codigomate = $infoMate->codigo;
                    $info->unimedida  = $infoMedida ? $infoMedida->nombre : '—';
                }

                $resultsBloque[$index]->detalle = $arrayEntradas;
                $index++;
            }

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
            </table>
        ";

            foreach ($listaProy as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'><tbody>";
                $tabla .= "<tr><td style='font-weight: bold'>Proyecto</td></tr>";
                $tabla .= "<tr><td>$dd->nombre</td></tr>";
                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top: 20px'><tbody>";
                $tabla .= "<tr>
                <td style='font-weight: bold; width: 12%; font-size: 13px'>Código</td>
                <td style='font-weight: bold; width: 12%; font-size: 13px'>Medida</td>
                <td style='font-weight: bold; width: 20%; font-size: 13px'>Repuesto</td>
                <td style='font-weight: bold; width: 14%; font-size: 13px'>Cantidad</td>
            </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "<tr>
                    <td style='font-size: 12px'>$gg->codigomate</td>
                    <td style='font-size: 12px'>$gg->unimedida</td>
                    <td style='font-size: 12px'>$gg->nombremate</td>
                    <td style='font-size: 12px'>$gg->cantidad</td>
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





}
