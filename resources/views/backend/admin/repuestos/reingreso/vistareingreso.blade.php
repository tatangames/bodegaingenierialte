@extends('adminlte::page')

@section('title', 'Reingreso de Materiales')

@section('content_header')
    <h1>Reingreso de Materiales</h1>
@stop

{{-- Activa plugins que necesitas --}}
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

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

        <section class="content">
            <div class="container-fluid">

                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Seleccionar Proyecto</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Proyecto <span style="color: red">*</span></label>
                                    <select id="select-proyecto" class="form-control">
                                        <option value="">Seleccione un proyecto...</option>
                                        @foreach($proyectos as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Salidas del Proyecto</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable">
                                    <p class="text-muted mb-0">Seleccione un proyecto para ver sus salidas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- modal reingreso -->
        <div class="modal fade" id="modalReingreso">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Reingreso de Material</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-reingreso" onsubmit="event.preventDefault(); guardarReingreso();">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">

                                        <input type="hidden" id="id-detalle-reingreso">

                                        <div class="form-group">
                                            <label>Material</label>
                                            <input type="text" class="form-control" id="material-reingreso" disabled>
                                        </div>

                                        <div class="form-group">
                                            <label>Cantidad a reingresar (máx. <span id="max-reingreso"></span>) <span style="color: red">*</span></label>
                                            <input type="number" min="1" class="form-control" id="cantidad-reingreso" autocomplete="off">
                                        </div>

                                        <div class="form-group">
                                            <label>Fecha de reingreso <span style="color: red">*</span></label>
                                            <input type="date" class="form-control" id="fecha-reingreso">
                                        </div>

                                        <div class="form-group">
                                            <label>Justificación <span style="color: red">*</span></label>
                                            <textarea class="form-control" id="justificacion-reingreso" rows="3" maxlength="1000"></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label>Documento de respaldo (PDF o imagen, máx. 5 MB)</label>
                                            <input type="file" class="form-control-file" id="archivo-reingreso" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="guardarReingreso()">Guardar</button>
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

            // Select2 — proyecto (mismo estilo que Cierre de Proyectos)
            $('#select-proyecto').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione un proyecto...',
                language: {
                    noResults: function () { return 'Búsqueda no encontrada'; }
                }
            });

            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }

                $('#tabla').DataTable({
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: false,
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

                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                var idproyecto = $('#select-proyecto').val();

                if (!idproyecto) {
                    $('#tablaDatatable').html('<p class="text-muted mb-0">Seleccione un proyecto para ver sus salidas.</p>');
                    return;
                }

                var ruta = "{{ url('/admin/reingreso/tabla') }}/" + idproyecto;

                openLoading();
                $('#tablaDatatable').load(ruta, function (response, status) {
                    closeLoading();

                    if (status === 'error') {
                        toastr.error('Error al cargar los datos');
                        return;
                    }

                    // La parcial debe tener exactamente un <table id="tabla">
                    initDataTable();
                });
            }

            $('#select-proyecto').on('change', cargarTabla);

            // Exponer recarga para después de guardar
            window.recargar = function () {
                cargarTabla();
            };
        });
    </script>

    <script>

        function modalReingreso(id, pendiente, material){
            document.getElementById("formulario-reingreso").reset();

            $('#id-detalle-reingreso').val(id);
            $('#material-reingreso').val(material);
            $('#max-reingreso').text(pendiente);
            $('#cantidad-reingreso').attr('max', pendiente);
            $('#fecha-reingreso').val("{{ date('Y-m-d') }}");

            $('#modalReingreso').modal('show');
        }

        function guardarReingreso(){
            var id            = document.getElementById('id-detalle-reingreso').value;
            var cantidad      = document.getElementById('cantidad-reingreso').value;
            var fecha         = document.getElementById('fecha-reingreso').value;
            var justificacion = document.getElementById('justificacion-reingreso').value.trim();
            var archivo       = document.getElementById('archivo-reingreso');
            var maximo        = parseInt(document.getElementById('max-reingreso').textContent);

            if(cantidad === ''){
                toastr.error('Cantidad es requerida');
                return;
            }

            if(!/^[0-9]+$/.test(cantidad) || parseInt(cantidad) < 1){
                toastr.error('Cantidad debe ser un número entero mayor a 0');
                return;
            }

            if(parseInt(cantidad) > maximo){
                toastr.error('La cantidad no puede ser mayor a ' + maximo);
                return;
            }

            if(fecha === ''){
                toastr.error('Fecha es requerida');
                return;
            }

            if(justificacion === ''){
                toastr.error('Justificación es requerida');
                return;
            }

            if(justificacion.length < 5){
                toastr.error('Justificación debe tener al menos 5 caracteres');
                return;
            }

            if(justificacion.length > 1000){
                toastr.error('Justificación máximo 1000 caracteres');
                return;
            }

            if(archivo.files.length > 0){
                var file = archivo.files[0];
                var permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

                if(!permitidos.includes(file.type)){
                    toastr.error('Formato de archivo no permitido (PDF, JPG o PNG)');
                    return;
                }

                if(file.size > 5 * 1024 * 1024){
                    toastr.error('El archivo no puede pesar más de 5 MB');
                    return;
                }
            }

            openLoading();
            var formData = new FormData();
            formData.append('id_salida_detalle', id);
            formData.append('cantidad', cantidad);
            formData.append('fecha', fecha);
            formData.append('justificacion', justificacion);

            if(archivo.files.length > 0){
                formData.append('archivo', archivo.files[0]);
            }

            axios.post(urlAdmin+'/admin/reingreso/nuevo', formData, {
            })
                .then((response) => {
                    closeLoading();

                    if(response.data.success === 1){
                        toastr.success('Reingreso registrado correctamente');
                        $('#modalReingreso').modal('hide');
                        recargar();
                    }
                    else if(response.data.success === 2){
                        toastr.error('Solo se pueden reingresar ' + response.data.maximo + ' unidades como máximo');
                    }
                    else if(response.data.success === 3){
                        toastr.error('El proyecto ya fue transferido, no se permiten reingresos');
                    }
                    else if(response.data.success === 4){
                        toastr.error('Esta salida fue una transferencia a otro proyecto y no se puede reingresar');
                    }
                    else if(response.data.success === 0){
                        toastr.error(response.data.mensaje);
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

    </script>

@endsection
