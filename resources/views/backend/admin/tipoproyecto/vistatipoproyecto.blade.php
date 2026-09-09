@extends('adminlte::page')

@section('title', 'Lista de Proyectos')

@section('content_header')
    <h1>Lista de Proyectos</h1>
@stop

@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')

    <style>
        .active-filtro {
            opacity: 1 !important;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.25);
            font-weight: bold;
        }
        .btn-filtro:not(.active-filtro) {
            opacity: 0.65;
        }
    </style>

    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">
                {{ Auth::guard('admin')->user()->nombre }}
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i>
                Editar Perfil
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
        table { table-layout: fixed; }
        .btn-filtro { min-width: 110px; }
    </style>

    <div id="divcontenedor">

        <section class="content-header">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <button type="button" onclick="modalAgregar()" class="btn btn-dark btn-sm">
                        <i class="fas fa-plus-square"></i>
                        Nuevo Proyecto
                    </button>
                </div>

                {{-- Filtros vigente / cerrado / todos --}}
                <div class="col-sm-6 text-right">
                    <div class="btn-group" role="group">
                        <button type="button"
                                class="btn btn-sm btn-filtro btn-success active-filtro"
                                id="btn-vigente"
                                onclick="cambiarFiltro('vigente')">
                            <i class="fas fa-folder-open"></i> Vigentes
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-filtro btn-secondary"
                                id="btn-cerrado"
                                onclick="cambiarFiltro('cerrado')">
                            <i class="fas fa-folder"></i> Cerrados
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-filtro btn-info"
                                id="btn-todos"
                                onclick="cambiarFiltro('todos')">
                            <i class="fas fa-list"></i> Todos
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title" id="titulo-tabla">Proyectos Vigentes</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable">
                                    {{-- Loading inicial --}}
                                    <div id="loading-proyectos" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                            <span class="sr-only">Cargando...</span>
                                        </div>
                                        <p class="mt-3 text-muted">Cargando listado de proyectos...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- modal agregar -->
        <div class="modal fade" id="modalAgregar">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Nuevo Proyecto</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-nuevo" onsubmit="event.preventDefault(); nuevo();">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Nombre</label>
                                            <input type="text" maxlength="800" class="form-control" id="nombre-nuevo" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" onclick="nuevo()">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- modal editar -->
        <div class="modal fade" id="modalEditar">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Editar Proyecto</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        {{-- Alerta: tiene entradas registradas --}}
                        <div id="alerta-entradas" class="alert alert-warning d-none" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>No se puede editar.</strong>
                            Este proyecto ya tiene ingresos de material registrados.
                            No es posible modificar su nombre mientras tenga entradas asociadas.
                        </div>

                        <form id="formulario-editar" onsubmit="event.preventDefault(); editar();">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <input type="hidden" id="id-editar">
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre de Proyecto</label>
                                            <input type="text" maxlength="800" class="form-control"
                                                   id="nombre-editar" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" id="btn-guardar-editar" class="btn btn-primary" onclick="editar()">Guardar</button>
                    </div>
                </div>
            </div>
        </div>


    </div>
@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>
        var filtroActual = 'vigente';
        var rutaBase     = "{{ url('/admin/proyecto/tabla/index') }}";

        var titulos = {
            vigente: 'Proyectos Vigentes',
            cerrado: 'Proyectos Cerrados',
            todos:   'Todos los Proyectos'
        };

        $(function () {
            cargarTabla();
        });

        function cambiarFiltro(filtro) {
            filtroActual = filtro;

            // Resaltar botón activo
            $('#btn-vigente, #btn-cerrado, #btn-todos').removeClass('active-filtro');
            $('#btn-' + filtro).addClass('active-filtro');

            // Actualizar título
            $('#titulo-tabla').text(titulos[filtro]);

            cargarTabla();
        }

        function cargarTabla() {
            var url = rutaBase + '?filtro=' + filtroActual;

            if ($.fn.DataTable.isDataTable('#tabla')) {
                $('#tabla').DataTable().destroy();
            }

            // Mostrar loading antes de la petición
            $('#tablaDatatable').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="sr-only">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando listado de proyectos...</p>
                </div>
            `);

            $('#tablaDatatable').load(url, function () {
                initDataTable();
            });
        }

        function initDataTable() {
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
                    sProcessing:     "Procesando...",
                    sLengthMenu:     "Mostrar _MENU_ registros",
                    sZeroRecords:    "No se encontraron resultados",
                    sEmptyTable:     "Ningún dato disponible en esta tabla",
                    sInfo:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    sInfoEmpty:      "Mostrando 0 a 0 de 0 registros",
                    sInfoFiltered:   "(filtrado de _MAX_ registros)",
                    sSearch:         "Buscar:",
                    oPaginate: {
                        sFirst:    "Primero",
                        sLast:     "Último",
                        sNext:     "Siguiente",
                        sPrevious: "Anterior"
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

        // Exponer para uso externo (después de crear/editar)
        window.recargar = function () {
            cargarTabla();
        };

        function modalAgregar(){
            document.getElementById("formulario-nuevo").reset();
            $('#modalAgregar').modal('show');
        }

        function nuevo(){
            var nombre = document.getElementById('nombre-nuevo').value;

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }
            if(nombre.length > 800){
                toastr.error('Nombre máximo 800 caracteres');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('nombre', nombre);

            axios.post(urlAdmin + '/admin/proyecto/nuevo', formData)
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Registrado correctamente');
                        $('#modalAgregar').modal('hide');
                        recargar();
                    } else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch(() => {
                    toastr.error('Error al registrar');
                    closeLoading();
                });
        }

        function informacion(id) {
            openLoading();
            document.getElementById('formulario-editar').reset();

            // Limpiar estado anterior
            $('#alerta-entradas').addClass('d-none');
            $('#formulario-editar input:not([type=hidden])').prop('disabled', false);
            $('#btn-guardar-editar').prop('disabled', false).show();

            axios.post(urlAdmin + '/admin/proyecto/informacion', { id: id })
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        var info          = response.data.info;
                        var tieneEntradas = response.data.tiene_entradas;

                        $('#id-editar').val(info.id);
                        $('#nombre-editar').val(info.nombre);

                        if (tieneEntradas) {
                            $('#alerta-entradas').removeClass('d-none');
                            $('#formulario-editar input:not([type=hidden])').prop('disabled', true);
                            $('#btn-guardar-editar').prop('disabled', true).hide();
                        }

                        $('#modalEditar').modal('show');
                    } else {
                        toastr.error('Información no encontrada');
                    }
                })
                .catch(() => {
                    closeLoading();
                    toastr.error('Información no encontrada');
                });
        }

        function editar(){
            var id     = document.getElementById('id-editar').value;
            var nombre = document.getElementById('nombre-editar').value;

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }
            if(nombre.length > 800){
                toastr.error('Nombre máximo 800 caracteres');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('id', id);
            formData.append('nombre', nombre);

            axios.post(urlAdmin + '/admin/proyecto/editar', formData)
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Actualizado correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else if(response.data.success === 3){
                        toastr.error('No se puede editar: este proyecto ya tiene entradas registradas');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else if(response.data.success === 2){
                        toastr.error('Proyecto no encontrado');
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => {
                    toastr.error('Error al actualizar');
                    closeLoading();
                });
        }



    </script>
@endsection
