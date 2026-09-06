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

class HistorialTransferenciaController extends Controller
{


    // ── Historial Transferencias ──────────────────────────────────────────────────

    public function indexHistorialTransferencias()
    {
        // Solo proyectos que tienen al menos una transferencia registrada
        $arrayProyectos = TipoProyecto::whereHas('transferencia')
            ->orderBy('nombre')
            ->get();

        return view('backend.admin.historial.transferencias.vistahistorialtransferencia',
            compact('arrayProyectos'));
    }


    public function tablaHistorialTransferencias(Request $request)
    {
        $arrayTransferencias = Transferencia::with([
            'tipoproyecto',         // destino
            'tipoproyectoOrigen',   // origen
        ])

            // Proyecto + tipo de búsqueda (origen | destino)
            ->when($request->proyecto, function ($q) use ($request) {

                $tipoBusqueda = $request->tipo_busqueda ?: 'origen';

                if ($tipoBusqueda === 'destino') {

                    // buscar por DESTINO
                    if ($request->proyecto == 'general') {
                        $q->where('tipo_salida', 'general');
                    } else {
                        $q->where('tipo_salida', '!=', 'general')
                            ->where('id_tipoproyecto', $request->proyecto);
                    }

                } else {

                    // buscar por ORIGEN
                    if ($request->proyecto == 'general') {
                        $q->where('tipo_salida', 'general');
                    } else {
                        $q->where('id_tipoproyecto_origen', $request->proyecto);
                    }
                }
            })

            // Tipo de salida (proyecto | general)
            ->when($request->tipo_salida, function ($q) use ($request) {
                $q->where('tipo_salida', $request->tipo_salida);
            })

            // Fecha desde
            ->when($request->fecha_desde, function ($q) use ($request) {
                $q->whereDate('fecha', '>=', $request->fecha_desde);
            })

            // Fecha hasta
            ->when($request->fecha_hasta, function ($q) use ($request) {
                $q->whereDate('fecha', '<=', $request->fecha_hasta);
            })

            // Material — busca tanto en el snapshot (nombre_material) como
            // en el nombre "vivo" del material relacionado, porque el
            // snapshot no siempre queda poblado
            ->when($request->material, function ($q) use ($request) {

                $busqueda = '%' . trim($request->material) . '%';

                $q->whereHas('detalle', function ($q2) use ($busqueda) {
                    $q2->where(function ($q3) use ($busqueda) {
                        $q3->where('nombre_material', 'LIKE', $busqueda)
                            ->orWhereHas('entradaDetalle.material', function ($q4) use ($busqueda) {
                                $q4->where('nombre', 'LIKE', $busqueda)
                                    ->orWhere('codigo', 'LIKE', $busqueda);
                            });
                    });
                });
            })

            // Documento
            ->when($request->documento, function ($q) use ($request) {

                $q->where('documento', 'LIKE', '%' . trim($request->documento) . '%');
            })

            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()

            ->map(function ($item) {

                $item->fecha_fmt = date('d/m/Y', strtotime($item->fecha));

                // Proyecto de ORIGEN (de donde vino el material)
                $item->nombre_origen = $item->tipoproyectoOrigen?->nombre ?? '—';

                // Proyecto de DESTINO (a donde se mandó)
                $item->nombre_destino =
                    $item->tipo_salida === 'general'
                        ? 'Mantenimiento de instalaciones'
                        : ($item->tipoproyecto?->nombre ?? '—');

                // ¿Viene de un despacho de reserva? (sin datos para PDF)
                $item->es_reserva = $item->origen_registro === 'reserva';

                // ¿El material que entró al destino ya fue usado o reservado?
                $item->se_puede_borrar = true;

                if ($item->id_entrada) {
                    $idsDetalle = EntradasDetalle::where('id_entradas', $item->id_entrada)
                        ->pluck('id');

                    $usado = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                        ->sum('cantidad_salida');

                    $reservado = Reserva::whereIn('id_entrada_detalle', $idsDetalle)
                        ->sum('cantidad');

                    if ($usado > 0 || $reservado > 0) {
                        $item->se_puede_borrar = false;
                    }
                }

                return $item;
            });

        return view(
            'backend.admin.historial.transferencias.tablahistorialtransferencia',
            compact('arrayTransferencias')
        );
    }


    public function informacionTransferencia(Request $request)
    {
        $transferencia = Transferencia::find($request->id);

        if (!$transferencia) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success'       => 1,
            'transferencia' => [
                'id'          => $transferencia->id,
                'fecha'       => $transferencia->fecha,
                'descripcion' => $transferencia->descripcion,
                'documento'   => $transferencia->documento,
            ]
        ]);
    }

    public function eliminarTransferencia(Request $request)
    {
        $transferencia = Transferencia::find($request->id);

        if (!$transferencia) {
            return response()->json(['success' => 0]);
        }

        DB::beginTransaction();

        try {
            $idSalida  = $transferencia->id_salida;
            $idEntrada = $transferencia->id_entrada;

            // ==========================================================
            // 1) VALIDACION: el material que entro al proyecto destino
            //    NO debe haber sido usado todavia.
            // ==========================================================
            if ($idEntrada) {
                $detallesEntrada = EntradasDetalle::where('id_entradas', $idEntrada)->get();

                foreach ($detallesEntrada as $entDet) {
                    $usado = SalidasDetalle::where('id_entrada_detalle', $entDet->id)
                        ->sum('cantidad_salida');

                    $reservado = Reserva::where('id_entrada_detalle', $entDet->id)
                        ->sum('cantidad');

                    if ($usado > 0 || $reservado > 0) {
                        DB::rollback();
                        return response()->json([
                            'success'         => 2,
                            'nombre_material' => $entDet->nombre,
                        ]);
                    }
                }
            }

            // ==========================================================
            // 2) BORRAR TRANSFERENCIA PRIMERO — libera los FK
            //    id_salida e id_entrada antes de borrar las cabeceras.
            // ==========================================================
            $transferencia->detalle()->delete();
            $transferencia->delete();

            // ==========================================================
            // 3) BORRAR SALIDA (ya sin FK que la referencie)
            // ==========================================================
            if ($idSalida) {
                SalidasDetalle::where('id_salida', $idSalida)->delete();
                Salidas::where('id', $idSalida)->delete();
            }

            // ==========================================================
            // 4) BORRAR ENTRADA (ya sin FK que la referencie)
            // ==========================================================
            if ($idEntrada) {
                EntradasDetalle::where('id_entradas', $idEntrada)->delete();
                Entradas::where('id', $idEntrada)->delete();
            }

            DB::commit();
            return response()->json(['success' => 1]);

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('eliminarTransferencia: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }

    public function detalleTransferencia(Request $request)
    {
        $transferencia = Transferencia::find($request->id);

        if (!$transferencia) {
            return response()->json(['success' => 0]);
        }

        $detalle = $transferencia->detalle()
            ->with([
                'entradaDetalle.material.objetoEspecifico.cuenta',
                'entradaDetalle.material.unidadMedida',
            ])
            ->get()
            ->map(function ($item) {
                $ed       = $item->entradaDetalle;
                $material = $ed?->material;
                $objEsp   = $material?->objetoEspecifico;

                return [
                    'nombre_material'   => $item->nombre_material
                        ?: ($material?->nombre ?? '—'),
                    'objeto_especifico' => $objEsp
                        ? $objEsp->codigo . ' — ' . $objEsp->nombre
                        : '—',
                    'unidad_medida'     => $material?->unidadMedida?->nombre ?? '—',
                    'cantidad_sobrante' => $item->cantidad_sobrante,
                    'precio'            => number_format($item->precio, 4),
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }


    public function actaDesdeHistorial($id)
    {
        $transferencia = Transferencia::find($id);

        if (!$transferencia) {
            abort(404, 'Transferencia no encontrada');
        }

        if ($transferencia->origen_registro === 'reserva') {
            abort(404); // o redirigir con un mensaje
        }

        $informacionGeneral = InformacionGeneral::where('id', 1)->first();
        $logoalcaldia       = 'images/logo.png';

        // ── Datos del proyecto origen ─────────────────────────────────
        $proyectoOrigen = Tipoproyecto::find($transferencia->id_tipoproyecto_origen);
        $nombreProyecto = $proyectoOrigen?->nombre ?? '—';
        $fechaFormat    = date('d/m/Y', strtotime($transferencia->fecha));

        // ── Datos del acta desde la tabla salidas ─────────────────────
        $numero        = '';
        $referencia    = '';
        $depto         = '';
        $nombreSolic   = '';
        $cargoSolic    = '';

        $firma_1 = '';
        $firma_2 = '';



        $tipodestino   = $transferencia->tipo_salida === 'general'
            ? 'Salida General — Mantenimiento de Instalaciones Municipales'
            : ('Transferencia a Proyecto: ' . (Tipoproyecto::find($transferencia->id_tipoproyecto)?->nombre ?? '—'));

        if ($transferencia->id_salida) {
            $salida = Salidas::find($transferencia->id_salida);

            if ($salida) {
                $numero        = $salida->acta_numero      ?? '';
                $referencia    = $salida->acta_referencia  ?? '';
                $nombreSolic   = $salida->acta_nombre_solic ?? '';
                $cargoSolic    = $salida->acta_cargo_solic  ?? '';
                $observaciones = $salida->acta_observaciones ?? '';
                $tipodestino   = $salida->acta_tipo_destino ?? $tipodestino;
                $firma_1       = $salida->firma_1 ?? '';
                $firma_2       = $salida->firma_2 ?? '';

                if ($salida->acta_id_departamento) {
                    $deptoDB = DB::table('departamentos')
                        ->where('id', $salida->acta_id_departamento)
                        ->first();
                    $depto = $deptoDB?->nombre ?? '';
                }
            }
        }

        // ── Materiales desde transferencia_detalle ────────────────────
        $detalles = TransferenciaDetalle::where('id_transferencia', $id)->get();

        $rows = [];

        foreach ($detalles as $det) {
            $codigo = '—';
            $medida = '—';
            $precio = $det->precio ?? 0;

            $entDet = EntradasDetalle::with([
                'material.unidadMedida',
                'material.objetoEspecifico',
            ])->find($det->id_entrada_detalle);

            if ($entDet) {
                if ($entDet->material?->objetoEspecifico) {
                    $codigo = $entDet->material->objetoEspecifico->codigo ?? '—';
                } elseif (!empty($entDet->material?->id_objespecifico)) {
                    $objEsp = DB::table('objeto_especifico')
                        ->where('id', $entDet->material->id_objespecifico)
                        ->first();
                    $codigo = $objEsp?->codigo ?? '—';
                }

                $medida = $entDet->material?->unidadMedida?->nombre ?? '—';

                if ($precio == 0) {
                    $precio = $entDet->precio ?? 0;
                }
            }

            $cantidad = (int) ($det->cantidad_sobrante ?? 0);
            $subtotal = $cantidad * $precio;

            $rows[] = [
                'codigo'   => $codigo,
                'nombre'   => $det->nombre_material ?? $entDet?->nombre ?? '—',
                'medida'   => $medida,
                'cantidad' => $cantidad,
                'precio'   => $precio,
                'subtotal' => $subtotal,
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // CONSTRUCCIÓN DEL HTML
        // ═══════════════════════════════════════════════════════════════
        $thStyle = "font-weight:bold; font-size:10px; border:0.8px solid #000;
                    padding:4px; background:#d9e1f2; text-align:center;";
        $tdStyle = "font-size:10px; border:0.8px solid #000; padding:4px;";
        $tdC     = $tdStyle . " text-align:center;";
        $tdR     = $tdStyle . " text-align:right;";

        // ── Encabezado ────────────────────────────────────────────────
        $html = "
<table width='100%' style='border-collapse:collapse; font-family:Arial,sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:38px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#104e8c;
                                font-size:12px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
                   padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            ACTA DE RECEPCIÓN DE<br>MATERIALES SOBRANTES
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000;
                                           border-bottom:0.8px solid #000; padding:4px 6px;'>
                        <strong>Código:</strong>
                    </td>
                    <td width='60%' style='border-bottom:0.8px solid #000;
                                           padding:4px 6px; text-align:center;'>GEAD-002-ACTA</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000;
                               border-bottom:0.8px solid #000; padding:4px 6px;'>
                        <strong>Versión:</strong>
                    </td>
                    <td style='border-bottom:0.8px solid #000;
                               padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'>
                        <strong>Fecha de vigencia:</strong>
                    </td>
                    <td style='padding:4px 6px; text-align:center;'></td>
                </tr>
            </table>
        </td>
    </tr>
</table><br>";

        // ── No. Acta y Fecha ──────────────────────────────────────────
        $html .= "
<table width='100%' style='border-collapse:collapse; margin-bottom:4px; margin-top:6px;'>
    <tr>
        <td style='width:20%; border:0.8px solid #ccc; padding:5px 8px;
                   font-size:11px; font-weight:bold; background:#f5f5f5;'>
            NO. DE ACTA DE RECEPCIÓN:
        </td>
        <td style='width:44%; border:0.8px solid #ccc; padding:5px 8px; font-size:11px;'>
            " . htmlspecialchars($numero) . "
        </td>
        <td style='width:5%; border:none;'></td>
        <td style='width:13%; border:0.8px solid #000; padding:5px 8px;
                   font-size:11px; font-weight:bold; text-align:center; background:#f5f5f5;'>
            FECHA:
        </td>
        <td style='width:18%; border:0.8px solid #000; padding:5px 8px;
                   font-size:11px; text-align:center;'>
            {$fechaFormat}
        </td>
    </tr>
</table>";

        // ── Campos del acta ───────────────────────────────────────────
        $campos = [
            'PROYECTO DE ORIGEN DE LOS MATERIALES' => $nombreProyecto,
            'REFERENCIA DE LA SOLICITUD'            => $referencia,
            'TIPO DE DESTINO / USO'                 => $tipodestino,
            'UNIDAD SOLICITANTE'                    => $depto,
            'NOMBRE DE SOLICITANTE'                 => $nombreSolic,
            'CARGO DE SOLICITANTE'                  => $cargoSolic,
        ];

        $html .= "<table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>";
        foreach ($campos as $label => $valor) {
            $html .= "
    <tr>
        <td style='width:25%; border:0.8px solid #ccc; padding:5px 8px;
                   font-size:11px; font-weight:bold; background:#f5f5f5;'>
            {$label}:
        </td>
        <td style='border:0.8px solid #ccc; padding:5px 8px; font-size:11px;'>
            " . htmlspecialchars($valor) . "
        </td>
    </tr>";
        }
        $html .= "</table>";

        // ── Texto declaración ─────────────────────────────────────────
        $html .= "
<table width='100%' style='border-collapse:collapse; margin-bottom:8px; margin-top:4px;'>
    <tr>
        <td style='border:0.8px solid #000; padding:8px 10px; font-size:10px;
                   text-align:justify; line-height:1.6;'>
            POR MEDIO DEL PRESENTE, EL RESPONSABLE DE LA BODEGA DE PROYECTOS O RESPONSABLE ASIGNADO
            HACE ENTREGA FORMAL DE LOS MATERIALES DETALLADOS EN EL FORMULARIO DE SOLICITUD. POR SU PARTE,
            EL RESPONSABLE QUE RECIBE DECLARA LA RECEPCIÓN CONFORME DE LOS MISMOS, ASUMIENDO LA CUSTODIA
            Y RESPONSABILIDAD PARA SU USO EXCLUSIVO EN EL DESTINO ESPECIFICADO Y SE COMPROMETE A REALIZAR
            LOS REGISTROS DE CONSUMO CORRESPONDIENTES.
        </td>
    </tr>
</table>";

        // ── Tabla de materiales ───────────────────────────────────────
        // Ordenar las filas por código presupuestario para poder agrupar
        usort($rows, function ($a, $b) {
            return strcmp($a['codigo'], $b['codigo']);
        });

        $granTotal = 0;
        foreach ($rows as $r) {
            $granTotal += $r['subtotal'];
        }

        $html .= "
<table width='100%' style='border-collapse:collapse;'>
    <thead>
        <tr>
            <th style='{$thStyle} width:5%;'>No.</th>
            <th style='{$thStyle} width:10%;'>COD PRESUP.</th>
            <th style='{$thStyle} width:35%;'>DESCRIPCIÓN</th>
            <th style='{$thStyle} width:12%;'>U. DE MEDIDA</th>
            <th style='{$thStyle} width:10%;'>CANTIDAD</th>
            <th style='{$thStyle} width:13%;'>PRECIO UNITARIO</th>
            <th style='{$thStyle} width:15%;'>SUBTOTAL</th>
        </tr>
    </thead>
    <tbody>";

        $i             = 1;
        $codigoActual  = null;
        $subtotalGrupo = 0;

        // Función auxiliar para imprimir la fila de subtotal de un código
        $filaSubtotal = function ($codigo, $monto) {
            return "
        <tr>
            <td colspan='6' style='font-weight:bold; font-size:10px; text-align:center;
                                    border:0.8px solid #000; padding:4px; background:#f2f4f8;'>
                SUBTOTAL [" . htmlspecialchars($codigo) . "]
            </td>
            <td style='font-weight:bold; font-size:10px; text-align:right;
                        border:0.8px solid #000; padding:4px; background:#f2f4f8;'>
                $ " . number_format($monto, 4) . "
            </td>
        </tr>";
        };

        foreach ($rows as $r) {

            // Si cambió el código (y no es la primera fila), cerrar el grupo anterior
            if ($codigoActual !== null && $r['codigo'] !== $codigoActual) {
                $html .= $filaSubtotal($codigoActual, $subtotalGrupo);
                $subtotalGrupo = 0;
            }

            $codigoActual   = $r['codigo'];
            $subtotalGrupo += $r['subtotal'];

            $html .= "
        <tr>
            <td style='{$tdC}'>{$i}</td>
            <td style='{$tdC}'>" . htmlspecialchars($r['codigo']) . "</td>
            <td style='{$tdStyle}'>" . htmlspecialchars($r['nombre']) . "</td>
            <td style='{$tdC}'>" . htmlspecialchars($r['medida']) . "</td>
            <td style='{$tdC} font-weight:bold;'>" . number_format($r['cantidad']) . "</td>
            <td style='{$tdR}'>$ " . number_format($r['precio'], 4) . "</td>
            <td style='{$tdR}'>$ " . number_format($r['subtotal'], 4) . "</td>
        </tr>";
            $i++;
        }

        // Cerrar el subtotal del último grupo (si hubo filas)
        if ($codigoActual !== null) {
            $html .= $filaSubtotal($codigoActual, $subtotalGrupo);
        }

        $html .= "
        <tr>
            <td colspan='6' style='font-weight:bold; font-size:11px; text-align:center;
                                    border:0.8px solid #000; padding:5px; background:#d9e1f2;'>
                TOTAL GENERAL
            </td>
            <td style='font-weight:bold; font-size:11px; text-align:right;
                        border:0.8px solid #000; padding:5px; background:#d9e1f2;'>
                $ " . number_format($granTotal, 4) . "
            </td>
        </tr>
    </tbody>
</table>";

        // ── Observaciones ─────────────────────────────────────────────
        $html .= "
            <br>
            <table width='100%' border='1' cellspacing='0' cellpadding='6'
                   style='border-collapse:collapse; font-size:11px;'>
                <tr style='background:#f2f4f8;'>
                    <td style='font-weight:bold;'>OBSERVACIONES:</td>
                </tr>
                <tr>
                    <td style='height:40px; vertical-align:top;'>" . htmlspecialchars($observaciones) . "</td>
                </tr>
            </table>";

        // ── Firmas ────────────────────────────────────────────────────
        $px = $informacionGeneral->px_firmas ?? 40;

        $html .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial,sans-serif;
                            margin-top:{$px}px; font-size:22px; line-height:1.8;'>
    <tr>
        <td style='width:50%; padding-right:50px; vertical-align:top;'>
            <strong style='font-size:28px;'>ENTREGADO POR:</strong><br><br>

            <table width='100%' style='border-collapse:collapse; font-size:28px;'>
                <tr>
                    <td style='width:22%; padding-bottom:14px; font-weight:bold;'>FIRMA:</td>
                    <td style='border-bottom:1.5px solid #000; width:78%;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td style='padding-bottom:14px; font-weight:bold;'>NOMBRE:</td>
                    <td style='border-bottom:1.5px solid #000;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td style='padding-bottom:14px; font-weight:bold;'>CARGO:</td>
                    <td style='border-bottom:1.5px solid #000;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td colspan='2'
                        style='text-align:center; font-size:26px; font-weight:normal; line-height:1.5;'>
                        $firma_1
                    </td>
                </tr>
            </table>
        </td>

        <td style='width:50%; padding-left:50px; vertical-align:top;'>
            <strong style='font-size:28px;'>RECIBIDO POR:</strong><br><br>

            <table width='100%' style='border-collapse:collapse; font-size:28px;'>
                <tr>
                    <td style='width:22%; padding-bottom:14px; font-weight:bold;'>FIRMA:</td>
                    <td style='border-bottom:1.5px solid #000; width:78%;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td style='padding-bottom:14px; font-weight:bold;'>NOMBRE:</td>
                    <td style='border-bottom:1.5px solid #000;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td style='padding-bottom:14px; font-weight:bold;'>CARGO:</td>
                    <td style='border-bottom:1.5px solid #000;'>&nbsp;</td>
                </tr>

                <tr><td colspan='2' style='height:45px;'></td></tr>

                <tr>
                    <td colspan='2'
                        style='text-align:center; font-size:26px; font-weight:normal; line-height:1.5;'>
                        $firma_2
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>";

        // ── Generar PDF ───────────────────────────────────────────────
        $mpdf = new \Mpdf\Mpdf([
            'tempDir'     => sys_get_temp_dir(),
            'format'      => 'LETTER',
            'orientation' => 'P',
        ]);
        $mpdf->SetTitle('GEAD-002-ACTA');
        $mpdf->showImageErrors = false;
        $mpdf->setFooter("Página {PAGENO} de {nb}");
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output();
    }


}
