@extends('adminlte::page')

@section('title', 'Reportes de Proyectos')

@section('plugins.Sweetalert2', true)
@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i>Editar Perfil
            </a>
        </div>
    </li>
    <li class="nav-item">
        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="nav-link btn btn-link border-0 bg-transparent">
                <i class="fas fa-sign-out-alt"></i>
                <span class="d-none d-md-inline">Cerrar Sesión</span>
            </button>
        </form>
    </li>
@endsection

@section('content')
    <style>
        *:focus { outline: none; }

        .reporte-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 18px rgba(0,0,0,.10);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .reporte-header {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .reporte-header.activo {
            background: linear-gradient(135deg, #1a3a6b, #2156af);
        }
        .reporte-header.completado {
            background: linear-gradient(135deg, #3d1f6b, #6f42c1);
        }
        .reporte-header.destino {
            background: linear-gradient(135deg, #1a5c3a, #28a745);
        }
        .reporte-header i {
            font-size: 22px;
            color: #fff;
        }
        .reporte-header h5 {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: 0;
        }
        .reporte-body {
            padding: 22px 24px;
            background: #fff;
        }
        .field-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7a99;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
            display: block;
        }
        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all .2s;
            margin-top: 14px;
        }
        .btn-pdf.azul {
            background: linear-gradient(135deg, #1a3a6b, #2156af);
            color: #fff;
            box-shadow: 0 4px 14px rgba(33,86,175,.35);
        }
        .btn-pdf.morado {
            background: linear-gradient(135deg, #3d1f6b, #6f42c1);
            color: #fff;
            box-shadow: 0 4px 14px rgba(111,66,193,.35);
        }
        .btn-pdf.verde {
            background: linear-gradient(135deg, #1a5c3a, #28a745);
            color: #fff;
            box-shadow: 0 4px 14px rgba(40,167,69,.35);
        }
        .btn-pdf.cian {
            background: linear-gradient(135deg, #0a4d68, #088395);
            color: #fff;
            box-shadow: 0 4px 14px rgba(8,131,149,.35);
        }
        .btn-pdf:hover { transform: translateY(-1px); filter: brightness(1.08); color: #fff; }
        .divider {
            border: none;
            border-top: 2px dashed #e8eef8;
            margin: 10px 0 20px 0;
        }
    </style>

    <div id="divcontenedor" style="display:none">
        <section class="content">
            <div class="container-fluid">
                <div class="row">

                    {{-- ══ REPORTE 1: Inventario Actual de Proyecto Activo ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header activo">
                                <i class="fas fa-boxes"></i>
                                <h5>Inventario Actual de Proyecto - EN EJECUCIÓN</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    Muestra el stock disponible actual de un proyecto activo,
                                    incluyendo entradas, salidas y saldo.
                                </p>
                                <hr class="divider">
                                <label class="field-label">
                                    <i class="fas fa-project-diagram mr-1"></i>Proyecto
                                </label>
                                <select class="form-control"
                                        id="select-proyecto-activo"
                                        style="width:100%;">
                                    @foreach($proyectos as $dd)
                                        <option value="{{ $dd->id }}">{{ $dd->nombre }}</option>
                                    @endforeach
                                </select>
                                <br>



                                <button type="button" onclick="generarPdfActivo()" class="btn-pdf azul">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
                                </button>

                                {{-- ✅ NUEVO: botón totalizado --}}
                                <button type="button" onclick="generarPdfTotalizado()" class="btn-pdf"
                                        style="background:linear-gradient(135deg,#1a4d5c,#117a8b);
                             color:#fff; box-shadow:0 4px 14px rgba(17,122,139,.35); margin-left:8px;">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Totalizado General
                                </button>


                            </div>
                        </div>
                    </div>


                    {{-- ══ REPORTE 4: Consolidado por Materiales Seleccionados ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header" style="background:linear-gradient(135deg,#4a1a1a,#c0392b);">
                                <i class="fas fa-layer-group"></i>
                                <h5>Consolidado por Materiales - EN EJECUCIÓN</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    Muestra el stock actual disponible de los materiales seleccionados,
                                    consolidando cantidades de todos los proyectos activos.
                                </p>
                                <hr class="divider">
                                <label class="field-label">
                                    <i class="fas fa-boxes mr-1"></i>Materiales
                                </label>
                                <select class="form-control"
                                        id="select-materiales-consolidado"
                                        multiple
                                        style="width:100%;">
                                    @foreach($arrayCatalogoMateriales as $mat)
                                        <option value="{{ $mat->id }}">
                                            {{ $mat->nombre }}
                                            @if($mat->unidadMedida)
                                                ({{ $mat->unidadMedida->nombre }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="mt-2" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">

                                    <button type="button"
                                            onclick="limpiarSeleccionMateriales()"
                                            class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-times mr-1"></i>Limpiar
                                    </button>
                                </div>
                                <br>
                                <button type="button"
                                        onclick="generarPdfConsolidadoMateriales()"
                                        class="btn-pdf"
                                        style="background:linear-gradient(135deg,#4a1a1a,#c0392b);
                           color:#fff; box-shadow:0 4px 14px rgba(192,57,43,.35);">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
                                </button>
                            </div>
                        </div>
                    </div>



                    {{-- ══ REPORTE 7: Existencia Actual de Proyecto Cerrado ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">

                            <div class="reporte-header"
                                 style="background:linear-gradient(135deg,#2d1b4e,#7b2d8b);">
                                <i class="fas fa-search"></i>
                                <h5>Existencia Actual — Proyecto Cerrado</h5>
                            </div>

                            <div class="reporte-body">

                                <hr class="divider">

                                <label class="field-label">
                                    <i class="fas fa-lock mr-1"></i>
                                    Proyecto Cerrado
                                </label>

                                <select class="form-control"
                                        id="select-proyecto-cerrado-existencia"
                                        style="width:100%;">
                                    @foreach($transferido as $dd)
                                        <option value="{{ $dd->id }}">
                                            {{ $dd->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <br>


                                {{-- ═══════════════════════════════════════ --}}
                                {{-- BOTÓN 2: CONTEO FÍSICO --}}
                                {{-- ═══════════════════════════════════════ --}}

                                <div style="margin-bottom:18px;">

                                    <p style="font-size:13px; color:#555; margin-bottom:8px;">
                                        <strong>Conteo Físico:</strong>
                                        Genera un listado de los materiales existentes para realizar
                                        el conteo físico y verificar las cantidades disponibles en bodega.
                                        Si afecta por Transferencia o descargo general.
                                    </p>

                                    <button type="button"
                                            onclick="generarPdfConteoFisico()"
                                            class="btn-pdf"
                                            style="background:linear-gradient(135deg,#1a3a1a,#2e7d32);
                               color:#fff;
                               box-shadow:0 4px 14px rgba(46,125,50,.35);
                               width:100%;">
                                        <img src="{{ asset('images/logopdf.png') }}"
                                             width="22px"
                                             height="22px">
                                        Para Conteo Físico
                                    </button>

                                </div>


                                {{-- ═══════════════════════════════════════ --}}
                                {{-- BOTÓN 3: PRECIO / LOTE --}}
                                {{-- ═══════════════════════════════════════ --}}

                                <div style="margin-bottom:10px;">

                                    <p style="font-size:13px; color:#555; margin-bottom:8px;">
                                        <strong>Precio / Por Lote:</strong>
                                        Muestra los materiales individualmente según cada ingreso o lote,
                                        incluyendo el precio unitario para facilitar la verificación
                                        del monto total de las existencias. Si afecta por Transferencia o descargo general.
                                    </p>

                                    <button type="button"
                                            onclick="generarPdfExistenciaLote()"
                                            class="btn-pdf"
                                            style="background:linear-gradient(135deg,#1a3a1a,#2e7d32);
                               color:#fff;
                               box-shadow:0 4px 14px rgba(46,125,50,.35);
                               width:100%;">
                                        <img src="{{ asset('images/logopdf.png') }}"
                                             width="22px"
                                             height="22px">
                                        Con Precio / Por Lote
                                    </button>

                                </div>




                                {{-- ═══════════════════════════════════════ --}}
                                {{-- BOTÓN 4: TOTALIZADO PROYECTOS CERRADOS --}}
                                {{-- ═══════════════════════════════════════ --}}

                                <div style="margin-bottom:10px;">

                                    <p style="font-size:13px; color:#555; margin-bottom:8px;">
                                        <strong>Totalizado</strong>
                                        Muestra el stock sobrante consolidado de todos los proyectos cerrados, agrupado por objeto específico, tal como fue registrado al momento del cierre de cada proyecto.
                                        Si afecta por Transferencia o descargo general.
                                    </p>

                                    <button type="button"
                                            onclick="generarPdfTotalizadoCerrados()"
                                            class="btn-pdf"
                                            style="background:linear-gradient(135deg,#4a3000,#c87800);
                           color:#fff; box-shadow:0 4px 14px rgba(200,120,0,.35);">
                                        <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                        Totalizado Proyectos Cerrados
                                    </button>

                                </div>



                                {{-- ═══════════════════════════════════════ --}}
                                {{-- BOTÓN 5: TOTALIZADO PROYECTOS CERRADOS - PRECIO UNITARIO --}}
                                {{-- ═══════════════════════════════════════ --}}

                                <div style="margin-bottom:10px;">

                                    <p style="font-size:13px; color:#555; margin-bottom:8px;">
                                        <strong>Totalizado - Precio Unitario</strong>
                                        Muestra el stock sobrante consolidado de todos los proyectos cerrados, agrupado por objeto específico, tal como fue registrado al momento del cierre de cada proyecto.
                                        Mostrara precio unitario desglosando cada material.
                                        Si afecta por Transferencia o descargo general.
                                    </p>

                                    <button type="button"
                                            onclick="generarPdfTotalizadoCerradosPrecioDesglose()"
                                            class="btn-pdf"
                                            style="background:linear-gradient(135deg,#4a3000,#c87800);
                           color:#fff; box-shadow:0 4px 14px rgba(200,120,0,.35);">
                                        <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                        Totalizado Proyectos Cerrados - Precio Unitario
                                    </button>

                                </div>





                            </div>
                        </div>
                    </div>







                    {{-- ══ REPORTE 6: Consolidado por Materiales — Proyectos Cerrados ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header"
                                 style="background:linear-gradient(135deg,#1a3a1a,#4a7c4a);">
                                <i class="fas fa-layer-group"></i>
                                <h5>Consolidado por Materiales — Proyectos Cerrados</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    Muestra el stock sobrante de los materiales seleccionados,
                                    consolidando cantidades de <strong>todos los proyectos cerrados</strong>.
                                </p>
                                <p><strong>Si la existencia es 0, no saldra en el reporte</strong></p>
                                <hr class="divider">

                                <label class="field-label">
                                    <i class="fas fa-boxes mr-1"></i>Materiales
                                </label>
                                <select class="form-control"
                                        id="select-materiales-cerrados"
                                        multiple
                                        style="width:100%;">
                                    @foreach($arrayCatalogoMateriales as $mat)
                                        <option value="{{ $mat->id }}">
                                            {{ $mat->nombre }}
                                            @if($mat->unidadMedida)
                                                ({{ $mat->unidadMedida->nombre }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <div class="mt-2" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <button type="button"
                                            onclick="limpiarSeleccionMaterialesCerrados()"
                                            class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-times mr-1"></i>Limpiar
                                    </button>
                                </div>
                                <br>

                                <button type="button"
                                        onclick="generarPdfConsolidadoMaterialesCerrados()"
                                        class="btn-pdf"
                                        style="background:linear-gradient(135deg,#1a3a1a,#4a7c4a);
                           color:#fff; box-shadow:0 4px 14px rgba(74,124,74,.35);">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
                                </button>
                            </div>
                        </div>
                    </div>







                    {{-- ══ REPORTE 2: Sobrantes de Proyecto Completado ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header completado">
                                <i class="fas fa-flag-checkered"></i>
                                <h5>Sobrantes de Proyecto Completado</h5>
                                <h5>REPORTE DE SALDOS DE MATERIALES SOBRANTES</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:15px; color:#000000; margin-bottom:14px;">
                                    Muestra el inventario sobrante registrado al momento del cierre
                                    del proyecto. Los movimientos posteriores no afectan este reporte.
                                </p>
                                <hr class="divider">
                                <label class="field-label">
                                    <i class="fas fa-lock mr-1"></i>Proyecto Cerrado
                                </label>
                                <select class="form-control" id="select-proyecto-completado">
                                    @foreach($transferido as $dd)
                                        <option value="{{ $dd->id }}">{{ $dd->nombre }}</option>
                                    @endforeach
                                </select>
                                <br>
                                <button type="button" onclick="generarPdfCompletado()" class="btn-pdf morado">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
                                </button>

                                <br>

                                <div class="row mt-3">

                                    <div class="col-md-12 mb-2">
                                        <label class="field-label">
                                            <i class="fas fa-user mr-1"></i>Nombre 1
                                        </label>

                                        <input type="text"
                                               id="s_nombre1"
                                               class="form-control"
                                               maxlength="200"
                                               value="{{ $infoGeneral->s_nombre1 ?? '' }}"
                                               placeholder="Ingrese nombre">
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <label class="field-label">
                                            <i class="fas fa-user-check mr-1"></i>Nombre 2
                                        </label>

                                        <input type="text"
                                               id="s_nombre2"
                                               class="form-control"
                                               maxlength="200"
                                               value="{{ $infoGeneral->s_nombre2 ?? '' }}"
                                               placeholder="Ingrese nombre">
                                    </div>

                                    <div class="col-md-12 text-right mt-2">
                                        <button type="button"
                                                onclick="actualizarFirmasSobrantes()"
                                                class="btn btn-primary">

                                            Guardar
                                        </button>
                                    </div>

                                </div>


                            </div>
                        </div>
                    </div>

                    {{-- ══ REPORTE 3: Destino de Sobrantes ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header destino">
                                <i class="fas fa-share-alt"></i>
                                <h5>Destino de Sobrantes por Proyecto</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    REPORTE DE MATERIALES SOBRANTES
                                    TRANSFERIDOS A PROYECTO DE INVERSIÓN PÚBLICA
                                </p>
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    REPORTE DE SALIDAS DE MATERIALES SOBRANTES PARA MANTENIMIENTO DE INSTALACIONES MUNICIPALES
                                </p>
                                <hr class="divider">
                                <label class="field-label">
                                    <i class="fas fa-lock mr-1"></i>Proyecto Cerrado
                                </label>
                                <select class="form-control" id="select-proyecto-destino">
                                    @foreach($transferido as $dd)
                                        <option value="{{ $dd->id }}">{{ $dd->nombre }}</option>
                                    @endforeach
                                </select>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="field-label">
                                            <i class="fas fa-calendar-alt mr-1"></i>Fecha Desde
                                        </label>
                                        <input type="date" class="form-control" id="destino-fecha-desde">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">
                                            <i class="fas fa-calendar-alt mr-1"></i>Fecha Hasta
                                        </label>
                                        <input type="date" class="form-control" id="destino-fecha-hasta">
                                    </div>
                                </div>

                                <div class="mt-3" style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" onclick="generarPdfDestino('proyecto')" class="btn-pdf verde">
                                        <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                        A Otro Proyecto
                                    </button>
                                    <button type="button" onclick="generarPdfDestino('general')" class="btn-pdf"
                                            style="background:linear-gradient(135deg,#7a4f1a,#fd7e14);
                               color:#fff; box-shadow:0 4px 14px rgba(253,126,20,.35);">
                                        <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                        Salida General
                                    </button>
                                    <button type="button" onclick="generarPdfDescriptivo()" class="btn-pdf cian">
                                        <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                        Descriptivo
                                    </button>
                                </div>


                                <br>

                                <div class="row mt-3">

                                    <div class="col-md-12 mb-2">
                                        <label class="field-label">
                                            <i class="fas fa-user mr-1"></i>Nombre 1
                                        </label>

                                        <input type="text"
                                               id="d_nombre1"
                                               class="form-control"
                                               maxlength="200"
                                               value="{{ $infoGeneral->d_nombre1 ?? '' }}"
                                               placeholder="Ingrese nombre">
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <label class="field-label">
                                            <i class="fas fa-user-check mr-1"></i>Nombre 2
                                        </label>

                                        <input type="text"
                                               id="d_nombre2"
                                               class="form-control"
                                               maxlength="200"
                                               value="{{ $infoGeneral->d_nombre2 ?? '' }}"
                                               placeholder="Ingrese nombre">
                                    </div>

                                    <div class="col-md-12 text-right mt-2">
                                        <button type="button"
                                                onclick="actualizarFirmasDestinoTraspaso()"
                                                class="btn btn-primary">

                                            Guardar
                                        </button>
                                    </div>

                                </div>


                            </div>
                        </div>
                    </div>



                    {{-- ══ CONFIGURACIÓN: Distancias del Reporte (píxeles) ══ --}}
                    <div class="col-md-6">
                        <div class="reporte-card">
                            <div class="reporte-header completado">
                                <i class="fas fa-sliders-h"></i>
                                <h5>Configuración de Distancias del Reporte</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    Ajusta el espacio en píxeles que se reserva para las firmas y
                                    para el bloque de observaciones en los reportes PDF.
                                </p>
                                <hr class="divider">

                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="field-label">
                                            <i class="fas fa-signature mr-1"></i>Píxeles Firmas
                                        </label>
                                        <input type="number" min="0" class="form-control" id="config-px-firmas"
                                               value="{{ $infoGeneral->px_firmas ?? 0 }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">
                                            <i class="fas fa-align-left mr-1"></i>Píxeles Observaciones
                                        </label>
                                        <input type="number" min="0" class="form-control" id="config-px-observaciones"
                                               value="{{ $infoGeneral->px_observaciones ?? 0 }}">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="button" onclick="actualizarPxConfig()" class="btn-pdf morado">
                                        <i class="fas fa-save"></i>
                                        Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </section>
    </div>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>

    <script>
        $(document).ready(function () {
            document.getElementById("divcontenedor").style.display = "block";

            $('#select-proyecto-activo').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });

            $('#select-proyecto-completado').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });

            $('#select-proyecto-destino').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });

            $('#select-materiales-consolidado').select2({
                theme: "bootstrap-5",
                placeholder: "Buscar y seleccionar materiales...",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });

            // Inicializar select2 para materiales de proyectos cerrados
            $('#select-materiales-cerrados').select2({
                theme: "bootstrap-5",
                placeholder: "Buscar y seleccionar materiales...",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });

            $('#select-proyecto-cerrado-existencia').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "Búsqueda no encontrada"; } }
            });
        });

        function generarPdfActivo() {
            var idproy = $('#select-proyecto-activo').val();
            if (!idproy) { toastr.error('Proyecto es requerido'); return; }
            window.open("{{ URL::to('admin/reporte/quetengopor/proyectos/pdf') }}/" + idproy);
        }

        function generarPdfCompletado() {
            var idtrans = $('#select-proyecto-completado').val();
            if (!idtrans) { toastr.error('Proyecto es requerido'); return; }
            window.open("{{ URL::to('admin/reporte/inventario/sobranteterminado/proy') }}/" + idtrans);
        }

        function generarPdfDestino(tipo) {
            var idtrans = $('#select-proyecto-destino').val();
            if (!idtrans) { toastr.error('Proyecto es requerido'); return; }

            var desde = $('#destino-fecha-desde').val();
            var hasta = $('#destino-fecha-hasta').val();

            if (!desde || !hasta) {
                toastr.error('Debe indicar la fecha desde y hasta');
                return;
            }
            if (desde > hasta) {
                toastr.error('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }

            var url = "{{ URL::to('admin/reporte/inventario/destino/sobrantes') }}/"
                + idtrans + "/" + tipo
                + "?desde=" + desde + "&hasta=" + hasta;

            window.open(url);
        }

        function generarPdfDescriptivo() {
            var idtrans = $('#select-proyecto-destino').val();
            if (!idtrans) { toastr.error('Proyecto es requerido'); return; }

            var desde = $('#destino-fecha-desde').val();
            var hasta = $('#destino-fecha-hasta').val();

            if (!desde || !hasta) {
                toastr.error('Debe indicar la fecha desde y hasta');
                return;
            }
            if (desde > hasta) {
                toastr.error('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }

            var url = "{{ URL::to('admin/reporte/inventario/destino/sobrantesdescriptivo') }}/"
                + idtrans
                + "?desde=" + desde + "&hasta=" + hasta;

            window.open(url);
        }

        function actualizarPxConfig() {
            var pxFirmas        = $('#config-px-firmas').val();
            var pxObservaciones = $('#config-px-observaciones').val();

            if (pxFirmas === '' || pxObservaciones === '') {
                toastr.error('Ambos campos de píxeles son requeridos');
                return;
            }
            if (parseInt(pxFirmas) < 0 || parseInt(pxObservaciones) < 0) {
                toastr.error('Los valores no pueden ser negativos');
                return;
            }

            axios.post("{{ route('admin.informacion.actualizar.px') }}", {
                _token:           '{{ csrf_token() }}',
                px_firmas:        pxFirmas,
                px_observaciones: pxObservaciones
            })
                .then(function (response) {
                    if (response.data.success === 1) {
                        toastr.success('Configuración actualizada correctamente');
                    } else {
                        toastr.error('No se pudo actualizar la configuración');
                    }
                })
                .catch(function () {
                    toastr.error('Ocurrió un error al guardar');
                });
        }



        function actualizarFirmasSobrantes() {

            openLoading();

            axios.post(urlAdmin + '/admin/firmas/proyectos/completado/actualizar', {
                s_nombre1: document.getElementById('s_nombre1').value.trim(),
                s_nombre2: document.getElementById('s_nombre2').value.trim()
            })
                .then((response) => {

                    closeLoading();

                    switch (response.data.success) {

                        case 1:
                            toastr.success('Información actualizada correctamente');
                            break;

                        case 0:
                            toastr.error('No se encontró la información');
                            break;

                        case 99:
                            toastr.error('Ocurrió un error al actualizar');
                            break;

                        default:
                            toastr.error('Error al actualizar');
                    }
                })
                .catch(() => {
                    closeLoading();
                    toastr.error('Error al actualizar');
                });
        }



        function actualizarFirmasDestinoTraspaso() {

            openLoading();

            axios.post(urlAdmin + '/admin/firmas/proyectos/traspaso/actualizar', {
                d_nombre1: document.getElementById('d_nombre1').value.trim(),
                d_nombre2: document.getElementById('d_nombre2').value.trim()
            })
                .then((response) => {

                    closeLoading();

                    switch (response.data.success) {

                        case 1:
                            toastr.success('Información actualizada correctamente');
                            break;

                        case 0:
                            toastr.error('No se encontró la información');
                            break;

                        case 99:
                            toastr.error('Ocurrió un error al actualizar');
                            break;

                        default:
                            toastr.error('Error al actualizar');
                    }
                })
                .catch(() => {
                    closeLoading();
                    toastr.error('Error al actualizar');
                });
        }


        function generarPdfTotalizado() {
            window.open("{{ URL::to('admin/reporte/quetengopor/proyectos/totalizado/pdf') }}");
        }

        function seleccionarTodosMateriales() {
            $('#select-materiales-consolidado option').prop('selected', true);
            $('#select-materiales-consolidado').trigger('change');
        }

        function limpiarSeleccionMateriales() {
            $('#select-materiales-consolidado').val(null).trigger('change');
        }

        function generarPdfConsolidadoMateriales() {
            var ids = $('#select-materiales-consolidado').val();
            if (!ids || ids.length === 0) {
                toastr.error('Debe seleccionar al menos un material');
                return;
            }
            var params = ids.map(id => 'ids[]=' + id).join('&');
            window.open("{{ URL::to('admin/reporte/consolidado/materiales/pdf') }}?" + params);
        }


        function generarPdfTotalizadoCerrados() {
            window.open("{{ URL::to('admin/reporte/cerrados/totalizado/pdf') }}");
        }

        function generarPdfTotalizadoCerradosPrecioDesglose() {
            window.open("{{ URL::to('admin/reporte/cerrados/totalizado-desglosado/pdf') }}");
        }

        function limpiarSeleccionMaterialesCerrados() {
            $('#select-materiales-cerrados').val(null).trigger('change');
        }

        function generarPdfConsolidadoMaterialesCerrados() {
            var ids = $('#select-materiales-cerrados').val();
            if (!ids || ids.length === 0) {
                toastr.error('Debe seleccionar al menos un material');
                return;
            }
            var params = ids.map(id => 'ids[]=' + id).join('&');
            window.open("{{ URL::to('admin/reporte/cerrados/consolidado/materiales/pdf') }}?" + params);
        }





        function generarPdfConteoFisico() {
            var id = $('#select-proyecto-cerrado-existencia').val();
            if (!id) { toastr.error('Proyecto es requerido'); return; }
            window.open("{{ URL::to('admin/reporte/cerrado/conteo/pdf') }}/" + id);
        }


        function generarPdfExistenciaLote() {
            var id = $('#select-proyecto-cerrado-existencia').val();
            if (!id) { toastr.error('Proyecto es requerido'); return; }
            window.open("{{ URL::to('admin/reporte/cerrado/lote/pdf') }}/" + id);
        }


    </script>
@endsection
