@extends('adminlte::page')

@section('title', 'Historial / Bitácoras')

@section('content_header')
    <h1>Historial / Bitácoras</h1>
@stop

@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet"/>
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i> Editar Perfil
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
    <div id="divcontenedor">

        {{-- FILTROS --}}
        <section class="content" style="margin-bottom:0">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filtros</h3>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="font-weight-bold">Empleado</label>
                                <select class="form-control" id="filtro-empleado">
                                    <option value="">— Todos —</option>
                                    @foreach($arrayEmpleados as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="font-weight-bold">Fecha desde</label>
                                <input type="date" class="form-control" id="filtro-fecha-desde">
                            </div>
                            <div class="col-md-3">
                                <label class="font-weight-bold">Fecha hasta</label>
                                <input type="date" class="form-control" id="filtro-fecha-hasta">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary btn-block mb-1" onclick="recargar()">
                                    <i class="fas fa-search mr-1"></i> Filtrar
                                </button>
                                <button class="btn btn-secondary btn-block" onclick="limpiarFiltros()">
                                    <i class="fas fa-times mr-1"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- TABLA --}}
        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Bitácoras</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════
         Modal Editar
    ══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-edit mr-2"></i>Editar Bitácora
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formulario-editar">
                        <input type="hidden" id="id-editar">

                        {{-- Empleados --}}
                        <div class="form-group">
                            <label><i class="fas fa-users mr-1"></i> Empleados <span class="text-danger">*</span></label>
                            <select id="empleados-editar" class="form-control" multiple>
                                @foreach($arrayEmpleados as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Puede seleccionar uno o varios empleados.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fecha <span class="text-danger">*</span></label>
                                    <input type="date" id="fecha-editar" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tiempo Utilizado <span class="text-danger">*</span></label>
                                    <input type="text" id="tiempo-editar" class="form-control"
                                           placeholder="Ej: 2 horas 30 minutos" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre-editar" class="form-control"
                                   placeholder="Nombre o título de la bitácora" maxlength="300">
                            <small class="text-muted" id="contador-nombre">0/300</small>
                        </div>

                        <div class="form-group">
                            <label>Descripción <span class="text-danger">*</span></label>
                            <textarea id="descripcion-editar" class="form-control"
                                      rows="3" maxlength="2000" placeholder="Describe la actividad realizada"></textarea>
                            <small class="text-muted" id="contador-descripcion">0/2000</small>
                        </div>

                        <div class="form-group">
                            <label>Ubicación <span class="text-danger">*</span></label>
                            <textarea id="ubicacion-editar" class="form-control"
                                      rows="2" maxlength="800" placeholder="Dirección o descripción del lugar"></textarea>
                            <small class="text-muted" id="contador-ubicacion">0/800</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Latitud (Opcional)</label>
                                    <input type="text" id="latitud-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Longitud (Opcional)</label>
                                    <input type="text" id="longitud-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="editar()">
                        <i class="fas fa-save mr-1"></i>Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         Modal Ver Detalle + Gestión de Fotos
    ══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-eye mr-2"></i>Detalle de Bitácora
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">

                    <input type="hidden" id="detalle-id-bitacora">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><i class="fas fa-users mr-1"></i> Empleados asignados:</strong>
                            <div id="detalle-empleados" class="mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-clock mr-1"></i> Tiempo Utilizado:</strong>
                            <div id="detalle-tiempo" class="mt-2"></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-calendar mr-1"></i> Fecha:</strong> <span id="detalle-fecha"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-map-marker-alt mr-1"></i> Ubicación:</strong> <span id="detalle-ubicacion"></span></p>
                            <p><strong><i class="fas fa-globe mr-1"></i> Coordenadas:</strong> <span id="detalle-coords"></span></p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <p><strong>Nombre:</strong> <span id="detalle-nombre"></span></p>
                        <p><strong>Descripción:</strong></p>
                        <p id="detalle-descripcion" style="white-space: pre-wrap; padding: 10px; background: #f5f5f5; border-radius: 4px;"></p>
                    </div>

                    {{-- SECCIÓN DE FOTOS --}}
                    <hr>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><i class="fas fa-images mr-1"></i> Fotos (<span id="detalle-fotos-count">0</span>)</strong>
                            <button type="button" class="btn btn-success btn-sm" onclick="mostrarAgregarFotos()">
                                <i class="fas fa-plus mr-1"></i> Agregar Fotos
                            </button>
                        </div>

                        <div id="detalle-fotos" class="mt-2"></div>

                        {{-- Panel para agregar fotos --}}
                        <div id="panel-agregar-fotos" class="mt-3" style="display: none;">
                            <div class="card card-outline card-success">
                                <div class="card-header py-2">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-upload mr-1"></i> Subir nuevas fotos
                                    </h6>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" onclick="ocultarAgregarFotos()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div class="form-group mb-2">
                                        <input type="file" id="input-nuevas-fotos"
                                               class="form-control-file"
                                               accept="image/jpeg,image/png,image/jpg"
                                               multiple>
                                        <small class="text-muted">JPG o PNG, máximo 5MB por foto. Puede seleccionar varias.</small>
                                    </div>

                                    <div id="preview-nuevas-fotos" class="d-flex flex-wrap mb-2"></div>

                                    <button type="button" class="btn btn-success btn-sm" onclick="subirFotos()">
                                        <i class="fas fa-cloud-upload-alt mr-1"></i> Subir fotos
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="ocultarAgregarFotos()">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         Modal Lightbox
    ══════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalFotoGrande" tabindex="-1" role="dialog" style="z-index: 1060;">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content" style="background: rgba(0,0,0,0.9); border: none;">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="titulo-foto-grande">Foto</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body text-center p-2" style="position: relative;">
                    <button class="btn btn-outline-light btn-sm position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); z-index: 10;"
                            onclick="navegarFoto(-1)" id="btn-foto-anterior">
                        <i class="fas fa-chevron-left fa-2x"></i>
                    </button>
                    <img id="imagen-foto-grande" src="" alt="Foto"
                         style="max-width: 100%; max-height: 75vh; object-fit: contain; border-radius: 4px;">
                    <button class="btn btn-outline-light btn-sm position-absolute"
                            style="right: 15px; top: 50%; transform: translateY(-50%); z-index: 10;"
                            onclick="navegarFoto(1)" id="btn-foto-siguiente">
                        <i class="fas fa-chevron-right fa-2x"></i>
                    </button>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <span class="text-white" id="contador-fotos-grande"></span>
                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
    <style>
        .foto-wrapper {
            display: inline-block;
            position: relative;
            margin: 5px;
        }
        .foto-thumbnail {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid #dee2e6;
            transition: all 0.2s ease;
        }
        .foto-thumbnail:hover {
            border-color: #007bff;
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .foto-wrapper .foto-index {
            position: absolute;
            top: 4px;
            left: 4px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .foto-wrapper .btn-eliminar-foto {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(220,53,69,0.85);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            font-size: 12px;
            line-height: 26px;
            padding: 0;
            cursor: pointer;
            z-index: 5;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .foto-wrapper:hover .btn-eliminar-foto {
            opacity: 1;
            pointer-events: auto;
        }
        .foto-wrapper .btn-eliminar-foto:hover {
            background: #c82333;
            transform: scale(1.15);
        }
        #detalle-fotos .sin-fotos {
            padding: 25px;
            text-align: center;
            color: #999;
            background: #f9f9f9;
            border-radius: 6px;
            border: 2px dashed #ddd;
        }
        .preview-foto-wrapper {
            display: inline-block;
            position: relative;
            margin: 4px;
        }
        .preview-foto-wrapper img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid #28a745;
        }
        .preview-foto-wrapper .preview-nombre {
            display: block;
            font-size: 10px;
            color: #666;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: center;
        }
        /* Select2 dentro del modal */
        #modalEditar .select2-container {
            width: 100% !important;
        }
    </style>
@endsection

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>
        $(function () {
            var ruta = "{{ url('/admin/bitacoras/historial/tabla') }}";

            // ── Select2: filtro de empleado ──
            $('#filtro-empleado').select2({
                theme: 'bootstrap-5',
                placeholder: '— Todos —',
                allowClear: true,
                language: { noResults: function () { return 'No encontrado'; } }
            });

            // ── Select2: empleados en modal editar ──
            $('#empleados-editar').select2({
                theme: 'bootstrap-5',
                placeholder: '— Seleccione empleados —',
                allowClear: true,
                language: { noResults: function () { return 'No encontrado'; } },
                dropdownParent: $('#modalEditar')
            });

            // ── DataTable ──
            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }
                $('#tabla').DataTable({
                    paging: true, lengthChange: true, searching: true,
                    ordering: true, info: true, autoWidth: false,
                    responsive: true, pagingType: "full_numbers",
                    lengthMenu: [[50, 100, -1], [50, 100, "Todo"]],
                    language: {
                        sProcessing:   "Procesando...",
                        sLengthMenu:   "Mostrar _MENU_ registros",
                        sZeroRecords:  "No se encontraron resultados",
                        sEmptyTable:   "Ningún dato disponible en esta tabla",
                        sInfo:         "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        sInfoEmpty:    "Mostrando 0 a 0 de 0 registros",
                        sInfoFiltered: "(filtrado de _MAX_ registros)",
                        sSearch:       "Buscar:",
                        oPaginate: {
                            sFirst:    "Primero",
                            sLast:     "Último",
                            sNext:     "Siguiente",
                            sPrevious: "Anterior"
                        }
                    },
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>tr<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                var empleado   = $('#filtro-empleado').val() || '';
                var fechaDesde = $('#filtro-fecha-desde').val() || '';
                var fechaHasta = $('#filtro-fecha-hasta').val() || '';

                var params = {};
                if (empleado)   params.empleado    = empleado;
                if (fechaDesde) params.fecha_desde = fechaDesde;
                if (fechaHasta) params.fecha_hasta = fechaHasta;

                var queryString = $.param(params);
                var url = queryString ? ruta + '?' + queryString : ruta;

                $('#tablaDatatable').html(
                    '<div class="text-center py-4">' +
                    '<i class="fas fa-spinner fa-spin fa-2x text-primary"></i>' +
                    '<p class="mt-2 text-muted">Cargando...</p></div>'
                );

                $.ajax({
                    url: url, type: 'GET',
                    success: function (html) {
                        $('#tablaDatatable').html(html);
                        initDataTable();
                    },
                    error: function () {
                        $('#tablaDatatable').html('<div class="alert alert-danger">Error al cargar la tabla.</div>');
                        toastr.error('Error al cargar los datos');
                    }
                });
            }

            window.recargar = function () { cargarTabla(); };

            window.limpiarFiltros = function () {
                $('#filtro-empleado').val(null).trigger('change');
                $('#filtro-fecha-desde').val('');
                $('#filtro-fecha-hasta').val('');
                cargarTabla();
            };

            cargarTabla();

            $('#filtro-fecha-desde, #filtro-fecha-hasta').on('keypress', function (e) {
                if (e.which === 13) cargarTabla();
            });
            $('#filtro-empleado').on('change', function () { cargarTabla(); });
        });
    </script>

    <script>
        // ── Contadores ──
        $(document).on('keyup', '#nombre-editar', function () {
            $('#contador-nombre').text($(this).val().length + '/300');
        });
        $(document).on('keyup', '#descripcion-editar', function () {
            $('#contador-descripcion').text($(this).val().length + '/2000');
        });
        $(document).on('keyup', '#ubicacion-editar', function () {
            $('#contador-ubicacion').text($(this).val().length + '/800');
        });

        // ══════════════════════════════════════════════════════
        //  EDITAR BITÁCORA
        // ══════════════════════════════════════════════════════
        function modalEditar(id) {
            openLoading();

            // Reset del formulario
            document.getElementById('formulario-editar').reset();
            $('#empleados-editar').val(null).trigger('change');
            $('#contador-nombre').text('0/300');
            $('#contador-descripcion').text('0/2000');
            $('#contador-ubicacion').text('0/800');

            axios.get(urlAdmin + '/admin/bitacoras/historial/informacion', { params: { id: id } })
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 1) {
                        var b = response.data.bitacora;

                        $('#id-editar').val(b.id);
                        $('#fecha-editar').val(b.fecha);
                        $('#nombre-editar').val(b.nombre).trigger('keyup');
                        $('#descripcion-editar').val(b.descripcion).trigger('keyup');
                        $('#ubicacion-editar').val(b.ubicacion).trigger('keyup');
                        $('#latitud-editar').val(b.latitud || '');
                        $('#longitud-editar').val(b.longitud || '');
                        $('#tiempo-editar').val(b.tiempo_utilizado);

                        // Preseleccionar empleados asignados
                        var empleadosIds = b.empleados_ids || [];
                        $('#empleados-editar').val(empleadosIds).trigger('change');

                        $('#modalEditar').modal('show');
                    } else {
                        toastr.error('No se pudo cargar la información');
                    }
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al obtener información');
                });
        }

        function editar() {
            var id               = $('#id-editar').val();
            var empleados        = $('#empleados-editar').val() || [];
            var fecha            = $('#fecha-editar').val().trim();
            var nombre           = $('#nombre-editar').val().trim();
            var descripcion      = $('#descripcion-editar').val().trim();
            var ubicacion        = $('#ubicacion-editar').val().trim();
            var latitud          = $('#latitud-editar').val().trim();
            var longitud         = $('#longitud-editar').val().trim();
            var tiempo_utilizado = $('#tiempo-editar').val().trim();

            // Validaciones
            if (empleados.length === 0)    { toastr.error('Seleccione al menos un empleado'); return; }
            if (!fecha)                    { toastr.error('La fecha es requerida'); return; }
            if (!nombre)                   { toastr.error('El nombre es requerido'); return; }
            if (!descripcion)              { toastr.error('La descripción es requerida'); return; }
            if (!ubicacion)                { toastr.error('La ubicación es requerida'); return; }
            if (!tiempo_utilizado)         { toastr.error('El tiempo utilizado es requerido'); return; }
            if (nombre.length > 300)       { toastr.error('Nombre máximo 300 caracteres'); return; }
            if (descripcion.length > 2000) { toastr.error('Descripción máximo 2000 caracteres'); return; }
            if (ubicacion.length > 800)    { toastr.error('Ubicación máximo 800 caracteres'); return; }

            openLoading();

            // Usamos POST con FormData para enviar el array de empleados correctamente
            var formData = new FormData();
            formData.append('id',               id);
            formData.append('fecha',            fecha);
            formData.append('nombre',           nombre);
            formData.append('descripcion',      descripcion);
            formData.append('ubicacion',        ubicacion);
            formData.append('latitud',          latitud);
            formData.append('longitud',         longitud);
            formData.append('tiempo_utilizado', tiempo_utilizado);
            empleados.forEach(function (empId) {
                formData.append('empleados[]', empId);
            });

            axios.post(urlAdmin + '/admin/bitacoras/historial/editar', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Bitácora actualizada correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else {
                        toastr.error(response.data.message || 'Error al actualizar');
                    }
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al actualizar');
                });
        }

        // ══════════════════════════════════════════════════════
        //  VER DETALLE CON FOTOS
        // ══════════════════════════════════════════════════════
        var galeriaFotos    = [];
        var fotoActualIndex = 0;
        var detalleIdActual = null;

        function verDetalle(id) {
            openLoading();
            detalleIdActual = id;

            axios.get(urlAdmin + '/admin/bitacoras/historial/detalle', { params: { id: id } })
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 1) {
                        var b = response.data.bitacora;
                        $('#detalle-id-bitacora').val(b.id);

                        // Empleados
                        var empleadosHtml = '';
                        if (b.empleados && b.empleados.length > 0) {
                            empleadosHtml = b.empleados.map(function (e) {
                                return '<span class="badge badge-primary mr-1 mb-1" style="font-size:13px;padding:5px 10px;">' +
                                    '<i class="fas fa-user mr-1"></i>' + e.nombre + '</span>';
                            }).join(' ');
                        } else {
                            empleadosHtml = '<span class="text-muted">Sin empleados asignados</span>';
                        }
                        $('#detalle-empleados').html(empleadosHtml);

                        // Fotos
                        renderizarFotos(b.fotos);

                        // Datos generales
                        $('#detalle-fecha').text(b.fecha);
                        $('#detalle-tiempo').text(b.tiempo_utilizado);
                        $('#detalle-nombre').text(b.nombre);
                        $('#detalle-descripcion').text(b.descripcion);
                        $('#detalle-ubicacion').text(b.ubicacion);

                        var coords = 'No especificadas';
                        if (b.latitud && b.longitud) {
                            coords = b.latitud + ', ' + b.longitud +
                                ' <a href="https://www.google.com/maps?q=' + b.latitud + ',' + b.longitud +
                                '" target="_blank" class="ml-2"><i class="fas fa-external-link-alt"></i> Ver en mapa</a>';
                        }
                        $('#detalle-coords').html(coords);

                        ocultarAgregarFotos();
                        $('#modalDetalle').modal('show');
                    } else {
                        toastr.error('No se pudo cargar el detalle');
                    }
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al cargar detalle');
                });
        }

        function renderizarFotos(fotos) {
            galeriaFotos = [];
            var fotosHtml = '';

            if (fotos && fotos.length > 0) {
                $('#detalle-fotos-count').text(fotos.length);
                fotosHtml = '<div class="d-flex flex-wrap">';
                fotos.forEach(function (foto, index) {
                    var fotoUrl = foto.url || '';
                    galeriaFotos.push(fotoUrl);

                    fotosHtml += '<div class="foto-wrapper" id="foto-wrapper-' + foto.id + '">' +
                        '<span class="foto-index">' + (index + 1) + '</span>' +
                        '<button type="button" class="btn-eliminar-foto" title="Eliminar esta foto" ' +
                        'onclick="eliminarFoto(' + foto.id + ')">' +
                        '<i class="fas fa-times"></i></button>' +
                        '<img src="' + fotoUrl + '" class="foto-thumbnail" alt="Foto ' + (index + 1) + '" ' +
                        'onclick="abrirFotoGrande(' + index + ')" ' +
                        'onerror="this.onerror=null;this.src=\'data:image/svg+xml,' +
                        encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110"><rect width="110" height="110" fill="#f0f0f0"/><text x="55" y="60" text-anchor="middle" fill="#999" font-size="12">Sin imagen</text></svg>') + '\'">' +
                        '</div>';
                });
                fotosHtml += '</div>';
                fotosHtml += '<small class="text-muted d-block mt-2">' +
                    '<i class="fas fa-info-circle mr-1"></i>Click en una foto para ampliar. Pase el cursor para ver el botón eliminar.</small>';
            } else {
                $('#detalle-fotos-count').text('0');
                fotosHtml = '<div class="sin-fotos">' +
                    '<i class="fas fa-image fa-3x mb-2 d-block"></i>' +
                    'Sin fotos adjuntas<br><small>Use el botón "Agregar Fotos" para subir imágenes</small></div>';
            }
            $('#detalle-fotos').html(fotosHtml);
        }

        // ══════════════════════════════════════════════════════
        //  ELIMINAR FOTO INDIVIDUAL
        // ══════════════════════════════════════════════════════
        function eliminarFoto(fotoId) {
            Swal.fire({
                title: '¿Eliminar esta foto?',
                text: 'La foto será eliminada permanentemente',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.value) {
                    openLoading();
                    axios.get(urlAdmin + '/admin/bitacoras/historial/eliminar-foto', { params: { id: fotoId } })
                        .then(function (response) {
                            closeLoading();
                            if (response.data.success === 1) {
                                toastr.success('Foto eliminada');
                                recargarDetalle();
                                recargar();
                            } else {
                                toastr.error(response.data.message || 'Error al eliminar foto');
                            }
                        })
                        .catch(function () { closeLoading(); toastr.error('Error al eliminar foto'); });
                }
            });
        }

        // ══════════════════════════════════════════════════════
        //  AGREGAR FOTOS
        // ══════════════════════════════════════════════════════
        function mostrarAgregarFotos() {
            $('#input-nuevas-fotos').val('');
            $('#preview-nuevas-fotos').html('');
            $('#panel-agregar-fotos').slideDown(200);
        }

        function ocultarAgregarFotos() {
            $('#panel-agregar-fotos').slideUp(200);
            $('#input-nuevas-fotos').val('');
            $('#preview-nuevas-fotos').html('');
        }

        // Vista previa
        $(document).on('change', '#input-nuevas-fotos', function () {
            var files = this.files;
            var previewContainer = $('#preview-nuevas-fotos');
            previewContainer.html('');
            if (files.length === 0) return;

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
                    toastr.error('"' + file.name + '" no es JPG/PNG');
                    continue;
                }
                if (file.size > 5 * 1024 * 1024) {
                    toastr.error('"' + file.name + '" supera 5MB');
                    continue;
                }
                (function (f) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        previewContainer.append(
                            '<div class="preview-foto-wrapper">' +
                            '<img src="' + e.target.result + '" alt="Preview">' +
                            '<span class="preview-nombre">' + f.name + '</span></div>'
                        );
                    };
                    reader.readAsDataURL(f);
                })(file);
            }
        });

        function subirFotos() {
            var inputFotos = document.getElementById('input-nuevas-fotos');
            var files = inputFotos.files;

            if (!files || files.length === 0) {
                toastr.error('Seleccione al menos una foto');
                return;
            }

            var idBitacora = $('#detalle-id-bitacora').val();
            if (!idBitacora) {
                toastr.error('Error: no se identificó la bitácora');
                return;
            }

            var formData = new FormData();
            formData.append('id', idBitacora);
            for (var i = 0; i < files.length; i++) {
                formData.append('fotos[]', files[i]);
            }

            openLoading();
            axios.post(urlAdmin + '/admin/bitacoras/historial/agregar-fotos', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success(response.data.message);
                        ocultarAgregarFotos();
                        recargarDetalle();
                        recargar();
                    } else {
                        toastr.error(response.data.message || 'Error al subir fotos');
                    }
                })
                .catch(function (error) {
                    closeLoading();
                    if (error.response && error.response.data && error.response.data.message) {
                        toastr.error(error.response.data.message);
                    } else {
                        toastr.error('Error al subir fotos');
                    }
                });
        }

        function recargarDetalle() {
            var id = $('#detalle-id-bitacora').val() || detalleIdActual;
            if (!id) return;

            axios.get(urlAdmin + '/admin/bitacoras/historial/detalle', { params: { id: id } })
                .then(function (response) {
                    if (response.data.success === 1) {
                        renderizarFotos(response.data.bitacora.fotos);
                    }
                });
        }

        // ══════════════════════════════════════════════════════
        //  LIGHTBOX
        // ══════════════════════════════════════════════════════
        function abrirFotoGrande(index) {
            fotoActualIndex = index;
            mostrarFotoGrande();
            $('#modalFotoGrande').modal('show');
        }

        function mostrarFotoGrande() {
            if (galeriaFotos.length === 0) return;
            var url = galeriaFotos[fotoActualIndex];
            $('#imagen-foto-grande').attr('src', url);
            $('#titulo-foto-grande').text('Foto ' + (fotoActualIndex + 1) + ' de ' + galeriaFotos.length);
            $('#contador-fotos-grande').text((fotoActualIndex + 1) + ' / ' + galeriaFotos.length);
            $('#btn-foto-anterior').toggle(fotoActualIndex > 0);
            $('#btn-foto-siguiente').toggle(fotoActualIndex < galeriaFotos.length - 1);
        }

        function navegarFoto(direccion) {
            var nuevoIndex = fotoActualIndex + direccion;
            if (nuevoIndex >= 0 && nuevoIndex < galeriaFotos.length) {
                fotoActualIndex = nuevoIndex;
                mostrarFotoGrande();
            }
        }

        $(document).on('keydown', function (e) {
            if ($('#modalFotoGrande').hasClass('show')) {
                if (e.key === 'ArrowLeft')  navegarFoto(-1);
                if (e.key === 'ArrowRight') navegarFoto(1);
                if (e.key === 'Escape')     $('#modalFotoGrande').modal('hide');
            }
        });

        // ══════════════════════════════════════════════════════
        //  ELIMINAR BITÁCORA COMPLETA
        // ══════════════════════════════════════════════════════
        function eliminar(id, nombre) {
            Swal.fire({
                title: '¿Eliminar bitácora?',
                text: 'Se eliminará la bitácora: "' + nombre + '"',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.value) {
                    openLoading();
                    axios.get(urlAdmin + '/admin/bitacoras/historial/eliminar', { params: { id: id } })
                        .then(function (response) {
                            closeLoading();
                            if (response.data.success === 1) {
                                toastr.success('Bitácora eliminada correctamente');
                                recargar();
                            } else {
                                toastr.error(response.data.message || 'Error al eliminar');
                            }
                        })
                        .catch(function () { closeLoading(); toastr.error('Error al eliminar'); });
                }
            });
        }
    </script>
@endsection
