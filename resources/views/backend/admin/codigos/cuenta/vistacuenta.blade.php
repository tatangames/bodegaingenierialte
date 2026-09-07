@extends('adminlte::page')

@section('title', 'Cuenta')

@section('content_header')
    <h1>Cuenta</h1>
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

        <section class="content-header">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <button type="button" onclick="modalAgregar()" class="btn btn-dark btn-sm">
                        <i class="fas fa-plus-square"></i> Nueva Cuenta
                    </button>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Cuentas</h3>
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

        {{-- ══ Modal Agregar ══ --}}
        <div class="modal fade" id="modalAgregar" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-dark">
                        <h4 class="modal-title text-white">
                            <i class="fas fa-plus-square mr-2"></i>Nueva Cuenta
                        </h4>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-nuevo" onsubmit="event.preventDefault(); nuevo();">
                            <div class="row">

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="font-weight-bold">
                                            Rubro <span class="text-danger">*</span>
                                        </label>
                                        <select id="id_rubro-nuevo" class="form-control" style="width:100%">
                                            <option value="">— Seleccione un rubro — </option>
                                            @foreach($rubros as $rubro)
                                                <option value="{{ $rubro->id }}">
                                                    {{ $rubro->codigo }} — {{ $rubro->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Código <span class="text-danger">*</span></label>
                                        <input type="text" maxlength="100" class="form-control"
                                               id="codigo-nuevo" autocomplete="off"
                                               placeholder="Código">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="font-weight-bold">
                                            Nombre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" maxlength="300" class="form-control"
                                               id="nombre-nuevo" autocomplete="off"
                                               placeholder="Nombre de la cuenta">
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cerrar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nuevo()">
                            <i class="fas fa-save mr-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Modal Editar ══ --}}
        <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h4 class="modal-title text-white">
                            <i class="fas fa-edit mr-2"></i>Editar Cuenta
                        </h4>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        {{-- Alerta: tiene materiales asignados --}}
                        <div id="alerta-materiales" class="alert alert-warning d-none" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>No se puede editar.</strong>
                            Esta cuenta tiene objetos específicos con materiales asignados.
                            Desvincule primero los materiales asociados para poder modificarla.
                        </div>

                        <form id="formulario-editar" onsubmit="event.preventDefault(); editar();">
                            <input type="hidden" id="id-editar">
                            <div class="row">

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="font-weight-bold">
                                            Rubro <span class="text-danger">*</span>
                                        </label>
                                        <select id="id_rubro-editar" class="form-control" style="width:100%">
                                            <option value="">— Seleccione un rubro —</option>
                                            @foreach($rubros as $rubro)
                                                <option value="{{ $rubro->id }}">
                                                    {{ $rubro->codigo }} — {{ $rubro->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Código <span class="text-danger">*</span></label>
                                        <input type="text" maxlength="100" class="form-control"
                                               id="codigo-editar" autocomplete="off"
                                               placeholder="Código">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="font-weight-bold">
                                            Nombre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" maxlength="300" class="form-control"
                                               id="nombre-editar" autocomplete="off"
                                               placeholder="Nombre de la cuenta">
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>


                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cerrar
                        </button>
                        <button type="button" id="btn-guardar-editar" class="btn btn-warning" onclick="editar()">
                            <i class="fas fa-save mr-1"></i>Guardar cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>

    <script>
        $(function () {

            // ── Select2 con buscador, ligado al modal correspondiente ──
            $('#id_rubro-nuevo').select2({
                theme: 'bootstrap-5',
                placeholder: '— Seleccione un rubro —',
                allowClear: true,
                dropdownParent: $('#modalAgregar'),
                language: { noResults: function () { return 'No encontrado'; } }
            });

            $('#id_rubro-editar').select2({
                theme: 'bootstrap-5',
                placeholder: '— Seleccione un rubro —',
                allowClear: true,
                dropdownParent: $('#modalEditar'),
                language: { noResults: function () { return 'No encontrado'; } }
            });

            // ── DataTable ─────────────────────────────────────────────
            const ruta = "{{ url('/admin/cuenta/tabla/index') }}";

            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }
                $('#tabla').DataTable({
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: false,
                    responsive: true,
                    pagingType: "full_numbers",
                    lengthMenu: [[100, 150, -1], [100, 150, "Todo"]],
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
                            sFirst: "Primero", sLast: "Último",
                            sNext: "Siguiente", sPrevious: "Anterior"
                        }
                    },
                    dom:
                        "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
                        "tr" +
                        "<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                $('#tablaDatatable').load(ruta, function () {
                    initDataTable();
                });
            }

            cargarTabla();
            window.recargar = function () { cargarTabla(); };
        });

        // ── Abrir modal agregar ────────────────────────────────────────
        function modalAgregar() {
            document.getElementById('formulario-nuevo').reset();
            $('#id_rubro-nuevo').val(null).trigger('change');
            $('#modalAgregar').modal('show');
        }

        // ── Guardar nuevo ──────────────────────────────────────────────
        function nuevo() {
            var id_rubro = $('#id_rubro-nuevo').val();
            var codigo   = $('#codigo-nuevo').val().trim();
            var nombre   = $('#nombre-nuevo').val().trim();

            if (!id_rubro)   { toastr.error('Rubro es requerido'); return; }
            if (!nombre)     { toastr.error('Nombre es requerido'); return; }
            if (!codigo)     { toastr.error('Código es requerido'); return; }

            openLoading();
            var formData = new FormData();
            formData.append('id_rubro', id_rubro);
            formData.append('codigo',   codigo);
            formData.append('nombre',   nombre);

            axios.post(urlAdmin + '/admin/cuenta/nuevo', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Registrado correctamente');
                        $('#modalAgregar').modal('hide');
                        recargar();
                    } else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al registrar'); });
        }

        // ── Cargar info para editar ────────────────────────────────────
        function informacion(id) {
            openLoading();
            document.getElementById('formulario-editar').reset();

            // Limpiar estado anterior
            $('#alerta-materiales').addClass('d-none');
            $('#formulario-editar input, #formulario-editar select').prop('disabled', false);
            $('#btn-guardar-editar').prop('disabled', false).show();

            axios.post(urlAdmin + '/admin/cuenta/informacion', { id: id })
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        var info            = response.data.info;
                        var tieneMateriales = response.data.tiene_materiales;

                        $('#id-editar').val(info.id);
                        $('#id_rubro-editar').val(info.id_rubro).trigger('change');
                        $('#codigo-editar').val(info.codigo ?? '');
                        $('#nombre-editar').val(info.nombre);

                        if (tieneMateriales) {
                            $('#alerta-materiales').removeClass('d-none');
                            $('#formulario-editar input, #formulario-editar select').prop('disabled', true);
                            $('#btn-guardar-editar').prop('disabled', true).hide();
                        }

                        $('#modalEditar').modal('show');
                    } else {
                        toastr.error('Información no encontrada');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Información no encontrada'); });
        }

        // ── Guardar edición ────────────────────────────────────────────
        function editar() {
            var id       = $('#id-editar').val();
            var id_rubro = $('#id_rubro-editar').val();
            var codigo   = $('#codigo-editar').val().trim();
            var nombre   = $('#nombre-editar').val().trim();

            if (!id_rubro) { toastr.error('Rubro es requerido'); return; }
            if (!nombre)   { toastr.error('Nombre es requerido'); return; }
            if (!codigo)   { toastr.error('Código es requerido'); return; }

            openLoading();
            var formData = new FormData();
            formData.append('id',       id);
            formData.append('id_rubro', id_rubro);
            formData.append('codigo',   codigo);
            formData.append('nombre',   nombre);

            axios.post(urlAdmin + '/admin/cuenta/editar', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Actualizado correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al actualizar'); });
        }
    </script>
@endsection
