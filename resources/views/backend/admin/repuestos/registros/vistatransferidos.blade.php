@extends('adminlte::page')

@section('title', 'Cierre de Proyectos')

@section('content_header')
    <h1>Cierre de Proyectos</h1>
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
    <style>
        table { table-layout: fixed; }
    </style>

    <div id="divcontenedor">

        {{-- ══ CERRAR PROYECTO ══ --}}
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-10">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-lock mr-1"></i> Cerrar Proyecto
                                </h3>
                            </div>
                            <div class="card-body">

                                <div class="row">
                                    <div class="col-md-12">
                                        <label>Fecha de Cierre: <span class="text-danger">*</span></label>
                                        <input style="width: 25%; margin-left: 25px;"
                                               type="date" class="form-control" id="fecha">
                                    </div>
                                </div>

                                <div class="form-group mt-3">
                                    <label>Seleccionar Proyecto: <span class="text-danger">*</span></label>
                                    <select id="select-tipoproyecto" class="form-control">
                                        @foreach($tipoproyecto as $item)
                                            <option value="{{ $item->id }}">{{ $item->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group mt-3">
                                    <label>Descripción:</label>
                                    <input type="text" class="form-control"
                                           autocomplete="off" maxlength="800" id="descripcion">
                                </div>

                                {{-- SI LO PIDEN LO VOY HABILITAR 23/05/2026 --}}
                                <div class="form-group mt-3" style="display:none;">
                                    <label>Documento Acta de Cierre (opcional)</label>
                                    <input type="file" id="documento" class="form-control"
                                           accept="image/jpeg, image/jpg, image/png, .pdf"/>
                                </div>

                                <div class="form-group text-right mt-3">
                                    <button type="button" onclick="guardarTransferencia()"
                                            class="btn btn-primary btn-sm">
                                        <i class="fas fa-lock mr-1"></i> Guardar Cierre
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══ REABRIR PROYECTO ══ --}}
        <section class="content" style="margin-top:0;">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-10">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-lock-open mr-1"></i> Reabrir Proyecto Cerrado - SIN TRANSFERENCIAS
                                </h3>
                            </div>
                            <div class="card-body">

                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        Seleccionar Proyecto Cerrado:
                                    </label>
                                    <select id="select-reabrir" class="form-control">
                                        <option value="">— Seleccione —</option>
                                        @foreach($proyectosCerrados as $p)
                                            <option value="{{ $p->id }}"
                                                    data-puede="{{ $p->puede_reabrir ? 1 : 0 }}">
                                                {{ $p->nombre }}{{ $p->puede_reabrir ? '' : ' 🔒' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small id="reabrir-aviso" class="text-danger mt-1" style="display:none;">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Este proyecto ya tiene materiales retirados y no puede reabrirse.
                                    </small>
                                </div>

                                <button type="button"
                                        id="btn-reabrir"
                                        class="btn btn-warning btn-sm"
                                        onclick="reabrirProyecto()"
                                        disabled>
                                    <i class="fas fa-lock-open mr-1"></i> Reabrir Proyecto
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
@stop

@section('js')
    <script src="{{ asset('js/jquery.dataTables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/dataTables.bootstrap4.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/bootstrap-input-spinner.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/custom-editors.js') }}" type="text/javascript"></script>

    <script>
        $(document).ready(function () {
            document.getElementById('divcontenedor').style.display = 'block';

            // Fecha de hoy por defecto
            var fecha = new Date();
            document.getElementById('fecha').value = fecha.toJSON().slice(0, 10);

            // Select2 — cerrar proyecto
            $('#select-tipoproyecto').select2({
                theme: 'bootstrap-5',
                language: { noResults: function () { return 'Búsqueda no encontrada'; } }
            });

            // Select2 — reabrir proyecto
            $('#select-reabrir').select2({
                theme: 'bootstrap-5',
                placeholder: '— Seleccione —',
                allowClear: true,
                language: { noResults: function () { return 'No encontrado'; } }
            });

            // Cambio en select reabrir
            $('#select-reabrir').on('change', function () {
                const puede = $(this).find(':selected').data('puede');
                const id    = $(this).val();

                if (!id) {
                    $('#btn-reabrir').prop('disabled', true);
                    $('#reabrir-aviso').hide();
                    return;
                }

                if (puede == 1) {
                    $('#btn-reabrir').prop('disabled', false);
                    $('#reabrir-aviso').hide();
                } else {
                    $('#btn-reabrir').prop('disabled', true);
                    $('#reabrir-aviso').show();
                }
            });
        });
    </script>

    <script>
        // ── Cerrar proyecto ───────────────────────────────────────
        function guardarTransferencia() {
            var fecha      = document.getElementById('fecha').value;
            var descripc   = document.getElementById('descripcion').value;
            var idproyecto = document.getElementById('select-tipoproyecto').value;
            var documento  = document.getElementById('documento');

            if (fecha === '') {
                toastr.error('Fecha es requerida'); return;
            }
            if (descripc.length > 800) {
                toastr.error('Descripción máximo 800 caracteres'); return;
            }
            if (idproyecto === '') {
                toastr.error('Proyecto es requerido'); return;
            }
            if (documento.files && documento.files[0]) {
                if (!documento.files[0].type.match('image/jpeg|image/png|application/pdf')) {
                    toastr.error('Formatos permitidos: .png .jpg .jpeg .pdf'); return;
                }
            }

            Swal.fire({
                title: '¿Cerrar Proyecto?',
                text: 'El proyecto quedará marcado como cerrado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, cerrar'
            }).then((result) => {
                if (result.isConfirmed) {
                    openLoading();

                    let formData = new FormData();
                    formData.append('fecha',       fecha);
                    formData.append('descripcion', descripc);
                    formData.append('idproyecto',  idproyecto);
                    if (documento.files && documento.files[0]) {
                        formData.append('documento', documento.files[0]);
                    }

                    axios.post(urlAdmin + '/admin/generar/salida/transferencia', formData)
                        .then((response) => {
                            closeLoading();

                            if (response.data.success === 1) {
                                Swal.fire({
                                    title: 'No Guardado',
                                    text: 'Este proyecto ya fue cerrado anteriormente.',
                                    icon: 'info',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'Aceptar'
                                });
                            } else if (response.data.success === 2) {
                                Swal.fire({
                                    title: 'Fecha de cierre inválida',
                                    html: 'La última salida registrada es del <b>' + response.data.ultima_salida + '</b>.<br><br>' +
                                        'La fecha de cierre (<b>' + response.data.fecha_cierre + '</b>) ' +
                                        'no puede ser anterior a la última salida.',
                                    icon: 'warning',
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'Entendido'
                                });
                            } else if (response.data.success === 3) {
                                Swal.fire({
                                    title: 'Cierre Exitoso',
                                    text: 'El proyecto ha sido cerrado correctamente.',
                                    icon: 'success',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'Aceptar',
                                    allowOutsideClick: false,
                                }).then((result) => {
                                    if (result.isConfirmed) { window.location.reload(); }
                                });
                            } else {
                                toastr.error('Error al guardar');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al guardar'); });
                }
            });
        }

        // ── Reabrir proyecto ──────────────────────────────────────
        function reabrirProyecto() {
            const id     = $('#select-reabrir').val();
            const nombre = $('#select-reabrir option:selected').text().replace(' 🔒', '').trim();

            if (!id) { toastr.error('Seleccione un proyecto'); return; }

            Swal.fire({
                title: '¿Reabrir proyecto?',
                html: 'El proyecto <b>' + nombre + '</b> volverá a estar activo.<br>' +
                    '<small class="text-muted">Se eliminará el snapshot de cierre.</small>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, reabrir'
            }).then((result) => {
                if (result.isConfirmed) {
                    openLoading();

                    axios.post(urlAdmin + '/admin/proyectos/reabrir', { id: id })
                        .then((response) => {
                            closeLoading();

                            switch (response.data.success) {
                                case 1:
                                    Swal.fire({
                                        title: 'Proyecto Reabierto',
                                        text: 'El proyecto ha sido reabierto correctamente.',
                                        icon: 'success',
                                        confirmButtonColor: '#28a745',
                                        confirmButtonText: 'Aceptar',
                                        allowOutsideClick: false,
                                    }).then(() => { window.location.reload(); });
                                    break;

                                case 2:
                                    Swal.fire({
                                        title: 'No se puede reabrir',
                                        text: response.data.msg,
                                        icon: 'warning',
                                        confirmButtonColor: '#d33',
                                        confirmButtonText: 'Entendido'
                                    });
                                    break;

                                case 0:
                                    toastr.error('El proyecto no existe o ya está abierto');
                                    break;

                                default:
                                    toastr.error('Error al reabrir el proyecto');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al reabrir'); });
                }
            });
        }
    </script>
@endsection
