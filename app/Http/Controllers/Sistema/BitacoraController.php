<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\BitacoraEmpleado;
use App\Models\BitacoraFoto;
use App\Models\Bitacoras;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class BitacoraController extends Controller
{
    public function vistaRegistroBitacora()
    {
        $arrayEmpleados = Empleado::orderBy('nombre', 'ASC')->get();

        return view('backend.bitacoras.registro.vistaregistrobitacoras', compact('arrayEmpleados'));
    }

    /**
     * Registrar una nueva bitácora con empleados y fotos
     */
    public function registrarBitacora(Request $request)
    {
        // ── Validación ─────────────────────────────────────────
        $validator = validator($request->all(), [
            'fecha'            => 'required|date',
            'nombre'           => 'required|string|max:300',
            'descripcion'      => 'required|string|max:2000',
            'ubicacion'        => 'required|string|max:800',
            'latitud'          => 'nullable|string|max:100',
            'longitud'         => 'nullable|string|max:100',
            'tiempo_utilizado' => 'required|string|max:255',
            'empleados'        => 'required|array|min:1',
            'empleados.*'      => 'integer|exists:empleados,id',
            'fotos'            => 'nullable|array',
            'fotos.*'          => 'image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'fecha.required'            => 'La fecha es requerida',
            'nombre.required'           => 'El nombre es requerido',
            'descripcion.required'      => 'La descripción es requerida',
            'ubicacion.required'        => 'La ubicación es requerida',
            'tiempo_utilizado.required' => 'El tiempo utilizado es requerido',
            'empleados.required'        => 'Debe asignar al menos un empleado',
            'empleados.min'             => 'Debe asignar al menos un empleado',
            'empleados.*.exists'        => 'Uno o más empleados no existen',
            'fotos.*.image'             => 'Los archivos deben ser imágenes válidas',
            'fotos.*.mimes'             => 'Las fotos deben ser JPG o PNG',
            'fotos.*.max'               => 'Cada foto debe pesar menos de 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── Guardado transaccional ─────────────────────────────
        DB::beginTransaction();
        try {

            // 1) Crear la bitácora
            $bitacora = new Bitacoras();
            $bitacora->fecha            = $request->input('fecha');
            $bitacora->nombre           = $request->input('nombre');
            $bitacora->descripcion      = $request->input('descripcion');
            $bitacora->ubicacion        = $request->input('ubicacion');
            $bitacora->latitud          = $request->input('latitud');
            $bitacora->longitud         = $request->input('longitud');
            $bitacora->tiempo_utilizado = $request->input('tiempo_utilizado');
            $bitacora->save();

            // 2) Asignar empleados (tabla bitacoras_empleados)
            $empleados = $request->input('empleados', []);
            foreach ($empleados as $idEmpleado) {
                $relacion = new BitacoraEmpleado();
                $relacion->id_bitacora = $bitacora->id;
                $relacion->id_empleado = $idEmpleado;
                $relacion->save();
            }

            // 3) Guardar las fotos en el disco 'archivos' (tabla bitacoras_fotos)
            if ($request->hasFile('fotos')) {

                $manager = ImageManager::usingDriver(Driver::class);

                foreach ($request->file('fotos') as $foto) {

                    $nombreArchivo = time() . '_' . Str::random(15) . '.jpg';
                    $rutaDisco     = Storage::disk('archivos')->path('');
                    $rutaFinal     = $rutaDisco . $nombreArchivo;

                    $manager->decodePath($foto->getRealPath())
                        ->scale(width: 1280)
                        ->encodeUsingFormat(\Intervention\Image\Format::JPEG, quality: 75)
                        ->save($rutaFinal);

                    $registroFoto = new BitacoraFoto();
                    $registroFoto->id_bitacora = $bitacora->id;
                    $registroFoto->imagen      = $nombreArchivo;
                    $registroFoto->descripcion = null;
                    $registroFoto->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => 1,
                'message' => 'Bitácora registrada correctamente',
                'id'      => $bitacora->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => 0,
                'message' => 'Error al registrar la bitácora: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Vista principal del historial de bitácoras
     */
    public function vistaHistorialBitacoras()
    {
        $arrayEmpleados = Empleado::orderBy('nombre')->get();

        return view('backend.bitacoras.historial.vistaHistorialBitacoras', compact('arrayEmpleados'));
    }

    /**
     * Cargar tabla dinámica con filtros
     */
    public function tablaHistorialBitacoras(Request $request)
    {
        $query = Bitacoras::with(['empleados', 'fotos']);

        // Filtro por empleado
        if ($request->has('empleado') && !empty($request->empleado)) {
            $query->whereHas('empleados', function ($q) use ($request) {
                $q->where('empleados.id', $request->empleado);
            });
        }

        // Filtro por fecha desde
        if ($request->has('fecha_desde') && !empty($request->fecha_desde)) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        // Filtro por fecha hasta
        if ($request->has('fecha_hasta') && !empty($request->fecha_hasta)) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $arrayBitacoras = $query->orderBy('fecha', 'DESC')->get();

        return view('backend.bitacoras.historial.tablaHistorialBitacoras', compact('arrayBitacoras'));
    }

    /**
     * Obtener información de una bitácora
     */
    public function historialBitacoraInformacion(Request $request)
    {
        $bitacora = Bitacoras::with('empleados')->find($request->id);

        if (!$bitacora) {
            return response()->json(['success' => 0, 'message' => 'Bitácora no encontrada']);
        }

        return response()->json([
            'success' => 1,
            'bitacora' => [
                'id'                => $bitacora->id,
                'fecha'             => $bitacora->fecha,
                'nombre'            => $bitacora->nombre,
                'descripcion'       => $bitacora->descripcion,
                'ubicacion'         => $bitacora->ubicacion,
                'latitud'           => $bitacora->latitud,
                'longitud'          => $bitacora->longitud,
                'tiempo_utilizado'  => $bitacora->tiempo_utilizado,
                'empleados_ids'     => $bitacora->empleados->pluck('id')->toArray(), // ← nuevo
            ]
        ]);
    }

    /**
     * Actualizar bitácora
     */
    public function historialBitacoraEditar(Request $request)
    {
        try {
            $bitacora = Bitacoras::find($request->id);

            if (!$bitacora) {
                return response()->json(['success' => 0, 'message' => 'Bitácora no encontrada']);
            }

            // Validaciones
            if (empty($request->fecha)) {
                return response()->json(['success' => 0, 'message' => 'La fecha es requerida']);
            }
            if (empty($request->nombre) || strlen($request->nombre) > 300) {
                return response()->json(['success' => 0, 'message' => 'Nombre inválido']);
            }
            if (empty($request->descripcion) || strlen($request->descripcion) > 2000) {
                return response()->json(['success' => 0, 'message' => 'Descripción inválida']);
            }
            if (empty($request->ubicacion) || strlen($request->ubicacion) > 800) {
                return response()->json(['success' => 0, 'message' => 'Ubicación inválida']);
            }
            if (empty($request->tiempo_utilizado)) {
                return response()->json(['success' => 0, 'message' => 'Tiempo utilizado requerido']);
            }

            // Actualizar
            $bitacora->fecha             = $request->fecha;
            $bitacora->nombre            = $request->nombre;
            $bitacora->descripcion       = $request->descripcion;
            $bitacora->ubicacion         = $request->ubicacion;
            $bitacora->latitud           = $request->latitud ?? null;
            $bitacora->longitud          = $request->longitud ?? null;
            $bitacora->tiempo_utilizado  = $request->tiempo_utilizado;
            $bitacora->save();

            // Sincronizar empleados (reemplaza los actuales por los nuevos)
            $empleados = $request->input('empleados', []);
            $bitacora->empleados()->sync($empleados);

            return response()->json(['success' => 1, 'message' => 'Bitácora actualizada correctamente']);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Obtener detalle completo de una bitácora (con URL de fotos)
     */
    public function historialBitacoraDetalle(Request $request)
    {
        try {
            $bitacora = Bitacoras::with(['empleados', 'fotos'])->find($request->id);

            if (!$bitacora) {
                return response()->json(['success' => 0, 'message' => 'Bitácora no encontrada']);
            }

            return response()->json([
                'success' => 1,
                'bitacora' => [
                    'id'                => $bitacora->id,
                    'fecha'             => $bitacora->fecha,
                    'nombre'            => $bitacora->nombre,
                    'descripcion'       => $bitacora->descripcion,
                    'ubicacion'         => $bitacora->ubicacion,
                    'latitud'           => $bitacora->latitud,
                    'longitud'          => $bitacora->longitud,
                    'tiempo_utilizado'  => $bitacora->tiempo_utilizado,
                    'empleados'         => $bitacora->empleados->map(function($emp) {
                        return [
                            'id'     => $emp->id,
                            'nombre' => $emp->nombre
                        ];
                    })->toArray(),
                    'fotos'             => $bitacora->fotos->map(function($foto) {
                        return [
                            'id'     => $foto->id,
                            'imagen' => $foto->imagen,
                            'url'    => asset('storage/archivos/' . $foto->imagen)
                        ];
                    })->toArray(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Eliminar bitácora
     */
    public function historialBitacoraEliminar(Request $request)
    {
        try {
            $bitacora = Bitacoras::find($request->id);

            if (!$bitacora) {
                return response()->json(['success' => 0, 'message' => 'Bitácora no encontrada']);
            }

            DB::beginTransaction();

            // Eliminar fotos del almacenamiento
            foreach ($bitacora->fotos as $foto) {
                if (!empty($foto->imagen)) {
                    try {
                        if (Storage::disk('archivos')->exists($foto->imagen)) {
                            Storage::disk('archivos')->delete($foto->imagen);
                        }
                    } catch (\Exception $e) {
                        // Continuar aunque falle la eliminación de archivo
                    }
                }
                $foto->delete();
            }

            // Eliminar relación con empleados (tabla pivote)
            $bitacora->empleados()->detach();

            // Eliminar bitácora
            $bitacora->delete();

            DB::commit();

            return response()->json(['success' => 1, 'message' => 'Bitácora eliminada correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => 0, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }







    // ══════════════════════════════════════════════════════════
    //  AGREGAR ESTOS MÉTODOS AL CONTROLADOR EXISTENTE
    // ══════════════════════════════════════════════════════════

    /**
     * Eliminar una foto individual de la bitácora
     */
    public function historialBitacoraEliminarFoto(Request $request)
    {
        try {
            $foto = BitacoraFoto::find($request->id);

            if (!$foto) {
                return response()->json(['success' => 0, 'message' => 'Foto no encontrada']);
            }

            // Eliminar archivo físico del disco 'archivos'
            if (!empty($foto->imagen)) {
                try {
                    if (Storage::disk('archivos')->exists($foto->imagen)) {
                        Storage::disk('archivos')->delete($foto->imagen);
                    }
                } catch (\Exception $e) {
                    // Continuar aunque falle la eliminación del archivo
                }
            }

            $foto->delete();

            return response()->json(['success' => 1, 'message' => 'Foto eliminada correctamente']);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Agregar fotos a una bitácora existente
     */
    public function historialBitacoraAgregarFotos(Request $request)
    {
        try {
            $bitacora = Bitacoras::find($request->id);

            if (!$bitacora) {
                return response()->json(['success' => 0, 'message' => 'Bitácora no encontrada']);
            }

            // Validar que se enviaron fotos
            if (!$request->hasFile('fotos')) {
                return response()->json(['success' => 0, 'message' => 'No se seleccionaron fotos']);
            }

            // Validar cada archivo
            $validator = validator($request->all(), [
                'fotos'   => 'required|array|min:1',
                'fotos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            ], [
                'fotos.required'  => 'Debe seleccionar al menos una foto',
                'fotos.*.image'   => 'Los archivos deben ser imágenes válidas',
                'fotos.*.mimes'   => 'Las fotos deben ser JPG o PNG',
                'fotos.*.max'     => 'Cada foto debe pesar menos de 5MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => 0,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $fotosSubidas = 0;
            $manager = ImageManager::usingDriver(Driver::class);

            foreach ($request->file('fotos') as $foto) {

                $nombreArchivo = time() . '_' . Str::random(10) . '.jpg';
                $rutaDisco     = Storage::disk('archivos')->path('');
                $rutaFinal     = $rutaDisco . $nombreArchivo;

                $manager->decodePath($foto->getRealPath())
                    ->scale(width: 1280)
                    ->encodeUsingFormat(\Intervention\Image\Format::JPEG, quality: 75)
                    ->save($rutaFinal);

                $registroFoto = new BitacoraFoto();
                $registroFoto->id_bitacora = $bitacora->id;
                $registroFoto->imagen      = $nombreArchivo;
                $registroFoto->descripcion = null;
                $registroFoto->save();

                $fotosSubidas++;
            }

            return response()->json([
                'success' => 1,
                'message' => $fotosSubidas . ' foto(s) agregada(s) correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }



    // ══════════════════════════════════════════════════════════
    //  REPORTES DE BITÁCORAS
    // ══════════════════════════════════════════════════════════

    /**
     * Vista del formulario de reportes
     */
    public function vistaReporteIndex()
    {
        $arrayEmpleados = Empleado::orderBy('nombre')->get();

        return view('backend.bitacoras.reportes.vistareportebitacora', compact('arrayEmpleados'));
    }

    /**
     * Generar PDF de bitácoras
     */
    public function pdfReporteBitacoras($desde, $hasta, $empleado, $conFotos)
    {
        $sinFecha    = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');
        $sinEmpleado = ($empleado === 'null' || $empleado === '');
        $incluirFotos = ($conFotos === '1');

        if (!$sinFecha) {
            $start      = date('Y-m-d', strtotime($desde));
            $end        = date('Y-m-d', strtotime($hasta));
            $fechaLabel = date("d-m-Y", strtotime($desde)) . "  -  " . date("d-m-Y", strtotime($hasta));
        } else {
            $fechaLabel = "Todas las fechas";
        }

        $fechaHoy = Carbon::now('America/El_Salvador')->format('d-m-Y');

        $nombreEmpleado = "Todos";
        if (!$sinEmpleado) {
            $emp = Empleado::find($empleado);
            $nombreEmpleado = $emp ? $emp->nombre : "Todos";
        }

        $query = Bitacoras::with(['empleados', 'fotos']);

        if (!$sinEmpleado) {
            $query->whereHas('empleados', function ($q) use ($empleado) {
                $q->where('empleados.id', $empleado);
            });
        }

        if (!$sinFecha) {
            $query->whereBetween('fecha', [$start, $end]);
        }

        $arrayBitacoras = $query->orderBy('fecha', 'ASC')->get();

        $logoalcaldia = 'images/logo.png';

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
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            REPORTE DE BITÁCORAS
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>INEL-001-REPO</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'>17/04/2026</td>
                </tr>
            </table>
        </td>
    </tr>
</table><br>

<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:4px;'>
    <tr>
        <td style='width:22%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
                   font-weight:bold; background:#f5f5f5; vertical-align:top;'>
            EMPLEADO
        </td>
        <td style='border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>
            " . e($nombreEmpleado) . "
        </td>
    </tr>
</table>

<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px;'>
    <tr>
        <td style='width:22%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
                   font-weight:bold; background:#f5f5f5;'>
            PERIODO
        </td>
        <td style='width:43%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>
            $fechaLabel
        </td>
        <td style='width:20%;'></td>
        <td style='width:7%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;
                   font-weight:bold; background:#f5f5f5; text-align:center;'>
            FECHA
        </td>
        <td style='width:8%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; text-align:center;'>
           $fechaHoy
        </td>
    </tr>
</table>
";

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format'  => 'LETTER',
        ]);
        $mpdf->SetTitle('Reporte de Bitácoras');
        $mpdf->showImageErrors = false;

        $tabla = $encabezado;

        $contador = 0;
        foreach ($arrayBitacoras as $bitacora) {
            $contador++;
            $fechaFmt    = date("d-m-Y", strtotime($bitacora->fecha));
            $nombre      = e($bitacora->nombre);
            $descripcion = e($bitacora->descripcion);
            $ubicacion   = e($bitacora->ubicacion);
            $tiempo      = e($bitacora->tiempo_utilizado);

            $listaEmpleados = 'Sin empleados';
            if ($bitacora->empleados && $bitacora->empleados->count() > 0) {
                $listaEmpleados = $bitacora->empleados->pluck('nombre')->implode(', ');
                $listaEmpleados = e($listaEmpleados);
            }

            $coordenadas = '';
            if ($bitacora->latitud && $bitacora->longitud) {
                $coordenadas = $bitacora->latitud . ', ' . $bitacora->longitud;
            }

            $tabla .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:2px;'>
    <tr>
        <td style='
            background-color:#2874a6;
            color:#ffffff;
            font-weight:bold;
            font-size:12px;
            padding:5px 10px;
            text-align:left;
        '>
            BITÁCORA #{$contador} — {$fechaFmt}
        </td>
    </tr>
</table>";

            $tabla .= "
<table width='100%' id='tablaFor' style='margin-bottom:2px;'>
    <tbody>
        <tr>
            <td style='font-weight:bold; width:15%; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Fecha</td>
            <td style='width:20%; font-size:11px; padding:4px 6px;'>{$fechaFmt}</td>
            <td style='font-weight:bold; width:18%; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Tiempo Utilizado</td>
            <td style='width:47%; font-size:11px; padding:4px 6px;'>{$tiempo}</td>
        </tr>
        <tr>
            <td style='font-weight:bold; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Nombre</td>
            <td colspan='3' style='font-size:11px; padding:4px 6px;'>{$nombre}</td>
        </tr>
        <tr>
            <td style='font-weight:bold; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Empleados</td>
            <td colspan='3' style='font-size:11px; padding:4px 6px;'>{$listaEmpleados}</td>
        </tr>
        <tr>
            <td style='font-weight:bold; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Ubicación</td>
            <td colspan='3' style='font-size:11px; padding:4px 6px;'>{$ubicacion}</td>
        </tr>";

            if (!empty($coordenadas)) {
                $tabla .= "
        <tr>
            <td style='font-weight:bold; font-size:11px; background:#f5f5f5; padding:4px 6px;'>Coordenadas</td>
            <td colspan='3' style='font-size:11px; padding:4px 6px;'>{$coordenadas}</td>
        </tr>";
            }

            $tabla .= "
    </tbody>
</table>";

            $tabla .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:2px;'>
    <tr>
        <td style='font-weight:bold; font-size:11px; background:#f5f5f5; border:0.5px solid #ccc; padding:4px 6px; width:15%;'>Descripción</td>
        <td style='font-size:11px; border:0.5px solid #ccc; padding:6px 8px; line-height:1.5;'>{$descripcion}</td>
    </tr>
</table>";

            // ── Fotos en 2 columnas ─────────────────────────
            if ($incluirFotos && $bitacora->fotos && $bitacora->fotos->count() > 0) {
                $cantFotos = $bitacora->fotos->count();
                $tabla .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:2px;'>
    <tr>
        <td style='font-weight:bold; font-size:11px; background:#f5f5f5; border:0.5px solid #ccc; padding:4px 6px;'>
            Fotografías ({$cantFotos})
        </td>
    </tr>
</table>";

                $tabla .= "<table width='100%' style='border-collapse:collapse; margin-bottom:2px;'>";

                $fotosArray = $bitacora->fotos->values();
                $totalFotos = $fotosArray->count();

                for ($i = 0; $i < $totalFotos; $i++) {
                    if ($i % 2 === 0) {
                        $tabla .= "<tr>";
                    }

                    $foto = $fotosArray[$i];
                    $rutaFoto = storage_path('app/public/archivos/' . $foto->imagen);

                    if (file_exists($rutaFoto)) {
                        $tabla .= "
    <td style='width:50%; padding:6px; text-align:center; vertical-align:top;'>
        <img src='{$rutaFoto}' style='max-width:260px; max-height:220px; border:1px solid #ccc; border-radius:4px;'>
        <br>
        <span style='font-size:9px; color:#888;'>Foto " . ($i + 1) . "</span>
    </td>";
                    } else {
                        $tabla .= "
    <td style='width:50%; padding:6px; text-align:center; vertical-align:top;'>
        <span style='font-size:10px; color:#999; font-style:italic;'>[Imagen no disponible]</span>
        <br>
        <span style='font-size:9px; color:#888;'>Foto " . ($i + 1) . "</span>
    </td>";
                    }

                    if ($i % 2 === 1 || $i === $totalFotos - 1) {
                        if ($i === $totalFotos - 1 && $i % 2 === 0) {
                            $tabla .= "<td style='width:50%;'></td>";
                        }
                        $tabla .= "</tr>";
                    }
                }

                $tabla .= "</table>";
            }

            $tabla .= "<br><hr style='border:none; border-top:1px dashed #ccc; margin:4px 0 10px 0;'>";
        }

        if ($arrayBitacoras->count() === 0) {
            $tabla .= "
<table width='100%' style='margin-top:20px;'>
    <tr>
        <td style='text-align:center; font-size:14px; color:#999; padding:40px;'>
            No se encontraron bitácoras con los filtros seleccionados.
        </td>
    </tr>
</table>";
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }








}
