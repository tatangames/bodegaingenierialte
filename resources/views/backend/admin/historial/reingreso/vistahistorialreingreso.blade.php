@extends('adminlte::page')

@section('title', 'Historial de Reingresos')

@section('content_header')
    <h1>Historial de Reingresos</h1>
@stop

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
                        <h3 class="card-title">Filtros</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Proyecto</label>
                                    <select id="filtro-proyecto" class="form-control">
                                        <option value="">Todos los proyectos</option>
                                        @foreach($arrayProyectos as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Reingreso desde</label>
                                    <input type="date" id="filtro-desde" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Reingreso hasta</label>
                                    <input type="date" id="filtro-hasta" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Material (nombre o código)</label>
                                    <input type="text" id="filtro-material" class="form-control"
                                           autocomplete="off" maxlength="100" placeholder="Buscar material...">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="button" class="btn btn-default btn-sm" onclick="limpiarFiltros()">
                                    <i class="fas fa-eraser"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="buscarConFiltros()">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Reingresos Registrados</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable">
                                    <p class="text-muted mb-0">Presione "Filtrar" para ver los reingresos.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- modal editar reingreso -->
        <div class="modal fade" id="modalEditarReingreso">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Editar Reingreso</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-editar-reingreso" onsubmit="event.preventDefault(); guardarEdicionReingreso();">
                            <div class="card-body">
                                <input type="hidden" id="edit-id">

                                <div class="form-group">
                                    <label>Material</label>
                                    <input type="text" class="form-control" id="edit-material" disabled>
                                </div>

                                <div class="form-group">
                                    <label>Cantidad reingresada</label>
                                    <input type="text" class="form-control" id="edit-cantidad" disabled>
                                    <small class="text-muted">La cantidad no se puede editar. Si es incorrecta, elimine el reingreso y regístrelo de nuevo.</small>
                                </div>

                                <div class="form-group">
                                    <label>Fecha de reingreso <span style="color: red">*</span></label>
                                    <input type="date" class="form-control" id="edit-fecha">
                                    <small class="text-muted">Fecha de la salida de origen: <span id="edit-fecha-salida"></span></small>
                                </div>

                                <div class="form-group">
                                    <label>Justificación <span style="color: red">*</span></label>
                                    <textarea class="form-control" id="edit-justificacion" rows="3" maxlength="1000"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Documento de respaldo (PDF o imagen, máx. 5 MB)</label>
                                    <div id="edit-archivo-actual" class="mb-2" style="display:none;">
                                        Documento actual:
                                        <a id="edit-archivo-link" href="#" target="_blank" rel="noopener">Ver</a>
                                        &nbsp;|&nbsp;
                                        <label class="mb-0" style="font-weight:normal;">
                                            <input type="checkbox" id="edit-quitar-archivo"> Quitar documento actual
                                        </label>
                                    </div>
                                    <input type="file" class="form-control-file" id="edit-archivo" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Si sube uno nuevo, reemplaza al actual.</small>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="guardarEdicionReingreso()">Guardar</button>
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

            $('#filtro-proyecto').select2({
                theme: 'bootstrap-5',
                language: {
                    noResults: function () { return 'Búsqueda no encontrada'; }
                }
            });

            // Enter en el buscador de material dispara el filtro
            $('#filtro-material').on('keypress', function (e) {
                if (e.which === 13) { buscarConFiltros(); }
            });
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

        function buscarConFiltros() {
            var desde = $('#filtro-desde').val();
            var hasta = $('#filtro-hasta').val();

            if (desde !== '' && hasta !== '' && desde > hasta) {
                toastr.error('La fecha "desde" no puede ser mayor que la fecha "hasta"');
                return;
            }

            var params = $.param({
                proyecto:    $('#filtro-proyecto').val() || '',
                fecha_desde: desde,
                fecha_hasta: hasta,
                material:    $('#filtro-material').val().trim()
            });

            var ruta = "{{ url('/admin/historial/reingreso/tabla') }}?" + params;

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

        function limpiarFiltros() {
            $('#filtro-proyecto').val('').trigger('change');
            $('#filtro-desde').val('');
            $('#filtro-hasta').val('');
            $('#filtro-material').val('');
            $('#tablaDatatable').html('<p class="text-muted mb-0">Presione "Filtrar" para ver los reingresos.</p>');
        }

        function mostrarMotivo(motivo) {
            Swal.fire({
                title: 'No se puede completar',
                text: motivo,
                type: 'info',
                confirmButtonColor: '#6c757d',
                confirmButtonText: 'Entendido'
            });
        }

        function eliminarReingreso(id, material, cantidad) {
            Swal.fire({
                title: '¿Eliminar reingreso?',
                html: 'Se eliminarán <b>' + cantidad + '</b> unidad(es) reingresadas de<br><b>' + material + '</b>.<br><br>' +
                    'El stock volverá a bajar y se borrará el documento adjunto. Esta acción no se puede deshacer.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    openLoading();
                    axios.post(urlAdmin + '/admin/historial/reingreso/eliminar', { id: id })
                        .then((response) => {
                            closeLoading();

                            if (response.data.success === 1) {
                                toastr.success('Reingreso eliminado correctamente');
                                buscarConFiltros();
                            }
                            else if (response.data.success === 2) {
                                // Pudo cambiar entre que se cargó la tabla y se presionó el botón
                                mostrarMotivo(response.data.mensaje);
                                buscarConFiltros();
                            }
                            else if (response.data.success === 0) {
                                toastr.error('El reingreso no existe o ya fue eliminado');
                                buscarConFiltros();
                            }
                            else {
                                toastr.error('Error al eliminar');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al eliminar'); });
                }
            });
        }

        function abrirEditarReingreso(id) {
            openLoading();
            document.getElementById('formulario-editar-reingreso').reset();

            axios.post(urlAdmin + '/admin/historial/reingreso/informacion', { id: id })
                .then((response) => {
                    closeLoading();

                    if (response.data.success === 1) {
                        var r = response.data.reingreso;

                        $('#edit-id').val(r.id);
                        $('#edit-material').val(r.material);
                        $('#edit-cantidad').val(r.cantidad);
                        $('#edit-fecha').val(r.fecha);
                        $('#edit-fecha-salida').text(r.fecha_salida);
                        $('#edit-justificacion').val(r.justificacion);
                        $('#edit-quitar-archivo').prop('checked', false);

                        if (r.archivo_url) {
                            $('#edit-archivo-link').attr('href', r.archivo_url);
                            $('#edit-archivo-actual').show();
                        } else {
                            $('#edit-archivo-actual').hide();
                        }

                        $('#modalEditarReingreso').modal('show');
                    }
                    else if (response.data.success === 3) {
                        mostrarMotivo(response.data.mensaje);
                    }
                    else {
                        toastr.error('Reingreso no encontrado');
                        buscarConFiltros();
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al cargar la información'); });
        }

        function guardarEdicionReingreso() {
            var id            = document.getElementById('edit-id').value;
            var fecha         = document.getElementById('edit-fecha').value;
            var justificacion = document.getElementById('edit-justificacion').value.trim();
            var archivo       = document.getElementById('edit-archivo');
            var quitar        = document.getElementById('edit-quitar-archivo').checked;

            if (fecha === '') {
                toastr.error('Fecha es requerida');
                return;
            }

            if (justificacion === '') {
                toastr.error('Justificación es requerida');
                return;
            }

            if (justificacion.length < 5) {
                toastr.error('Justificación debe tener al menos 5 caracteres');
                return;
            }

            if (justificacion.length > 1000) {
                toastr.error('Justificación máximo 1000 caracteres');
                return;
            }

            if (archivo.files.length > 0) {
                var file = archivo.files[0];
                var permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

                if (!permitidos.includes(file.type)) {
                    toastr.error('Formato de archivo no permitido (PDF, JPG o PNG)');
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    toastr.error('El archivo no puede pesar más de 5 MB');
                    return;
                }
            }

            openLoading();
            var formData = new FormData();
            formData.append('id', id);
            formData.append('fecha', fecha);
            formData.append('justificacion', justificacion);
            formData.append('quitar_archivo', quitar ? 1 : 0);

            if (archivo.files.length > 0) {
                formData.append('archivo', archivo.files[0]);
            }

            axios.post(urlAdmin + '/admin/historial/reingreso/editar', formData)
                .then((response) => {
                    closeLoading();

                    if (response.data.success === 1) {
                        toastr.success('Reingreso actualizado correctamente');
                        $('#modalEditarReingreso').modal('hide');
                        buscarConFiltros();
                    }
                    else if (response.data.success === 3) {
                        $('#modalEditarReingreso').modal('hide');
                        mostrarMotivo(response.data.mensaje);
                        buscarConFiltros();
                    }
                    else if (response.data.success === 4 || response.data.success === 5 || response.data.success === 6) {
                        Swal.fire({
                            title: 'No se puede guardar',
                            text: response.data.mensaje,
                            type: 'warning',
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'Entendido'
                        });
                    }
                    else if (response.data.success === 0) {
                        toastr.error(response.data.mensaje || 'Error al actualizar');
                    }
                    else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al actualizar'); });
        }
    </script>
@endsection
