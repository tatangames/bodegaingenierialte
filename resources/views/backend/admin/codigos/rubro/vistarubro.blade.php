@extends('adminlte::page')

@section('title', 'Rubro')

@section('content_header')
    <h1>Rubro</h1>
@stop


{{-- Activa plugins que necesitas --}}
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
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
    <div id="divcontenedor">

        <section class="content-header">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <button type="button" onclick="modalAgregar()" class="btn btn-dark btn-sm">
                        <i class="fas fa-plus-square"></i>
                        Nuevo Rubro
                    </button>
                </div>

            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Rubros</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="modal fade" id="modalAgregar">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Nuevo Rubro</h4>
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
                                            <label>Código <span style="color: red">*</span> </label>
                                            <input type="text" maxlength="100" class="form-control" id="codigo-nuevo" autocomplete="off">
                                        </div>

                                        <div class="form-group">
                                            <label>Nombre <span style="color: red">*</span> </label>
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
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Editar</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>


                    <div class="modal-body">

                        {{-- Alerta: tiene materiales asignados --}}
                        <div id="alerta-materiales" class="alert alert-warning d-none" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>No se puede editar.</strong>
                            Este rubro tiene cuentas con objetos específicos que ya tienen materiales asignados.
                            Desvincule primero los materiales asociados para poder modificarlo.
                        </div>

                        <form id="formulario-editar" onsubmit="event.preventDefault(); editar();">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">

                                        <div class="form-group">
                                            <input type="hidden" id="id-editar">
                                        </div>

                                        <div class="form-group">
                                            <label>Código <span style="color: red">*</span></label>
                                            <input type="text" maxlength="100" class="form-control"
                                                   id="codigo-editar" autocomplete="off">
                                        </div>

                                        <div class="form-group">
                                            <label>Nombre <span style="color: red">*</span></label>
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
        $(function () {
            const ruta = "{{ url('/admin/rubro/tabla/index') }}";

            function initDataTable() {
                // Si ya hay instancia, destrúyela antes de re-crear
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }

                // Inicializa
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
                        sProcessing: "Procesando...",
                        sLengthMenu: "Mostrar _MENU_ registros",
                        sZeroRecords: "No se encontraron resultados",
                        sEmptyTable: "Ningún dato disponible en esta tabla",
                        sInfo: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        sInfoEmpty: "Mostrando 0 a 0 de 0 registros",
                        sInfoFiltered: "(filtrado de _MAX_ registros)",
                        sSearch: "Buscar:",
                        oPaginate: {sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior"},
                        oAria: {sSortAscending: ": Orden ascendente", sSortDescending: ": Orden descendente"}
                    },
                    dom:
                        "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
                        "tr" +
                        "<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });

                // Estilitos
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                $('#tablaDatatable').load(ruta, function () {
                    // AQUI debe existir exactamente un <table id="tabla"> en la parcial
                    initDataTable();
                });
            }

            // Primera carga
            cargarTabla();

            // Exponer recarga para tus flujos (crear/editar)
            window.recargar = function () {
                cargarTabla();
            };
        });
    </script>

    <script>

        function recargar(){
            var ruta = "{{ url('/admin/rubro/tabla/index') }}";
            $('#tablaDatatable').load(ruta);
        }

        function modalAgregar(){
            document.getElementById("formulario-nuevo").reset();
            $('#modalAgregar').modal('show');
        }

        function nuevo(){

            var codigo = document.getElementById('codigo-nuevo').value;
            var nombre = document.getElementById('nombre-nuevo').value;
            if(codigo === ''){
                toastr.error('Código es requerido');
                return;
            }

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('nombre', nombre);
            formData.append('codigo', codigo);

            axios.post(urlAdmin+'/admin/rubro/nuevo', formData, {
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Registrado correctamente');
                        $('#modalAgregar').modal('hide');
                        recargar();
                    }
                    else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al registrar');
                    closeLoading();
                });
        }

        function informacion(id) {
            openLoading();
            document.getElementById('formulario-editar').reset();

            // Limpiar estado anterior
            $('#alerta-materiales').addClass('d-none');
            $('#formulario-editar input').prop('disabled', false);
            $('#btn-guardar-editar').prop('disabled', false).show();

            axios.post(urlAdmin + '/admin/rubro/informacion', { id: id })
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        var info            = response.data.info;
                        var tieneMateriales = response.data.tiene_materiales;

                        $('#id-editar').val(info.id);
                        $('#nombre-editar').val(info.nombre);
                        $('#codigo-editar').val(info.codigo);

                        if (tieneMateriales) {
                            $('#alerta-materiales').removeClass('d-none');
                            $('#formulario-editar input').prop('disabled', true);
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
            var id = document.getElementById('id-editar').value;
            var nombre = document.getElementById('nombre-editar').value;
            var codigo = document.getElementById('codigo-editar').value;

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }

            if(codigo === ''){
                toastr.error('Código es requerido');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('id', id);
            formData.append('nombre', nombre);
            formData.append('codigo', codigo);

            axios.post(urlAdmin+'/admin/rubro/editar', formData, {
            })
                .then((response) => {
                    closeLoading();

                    if(response.data.success === 1){
                        toastr.success('Actualizado correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    }
                    else {
                        toastr.error('Error al actualizar');
                    }

                })
                .catch((error) => {
                    toastr.error('Error al actualizar');
                    closeLoading();
                });
        }


    </script>


@endsection
