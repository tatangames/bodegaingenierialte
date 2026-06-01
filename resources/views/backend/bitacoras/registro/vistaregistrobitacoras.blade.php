@extends('adminlte::page')

@section('title', 'Registrar Bitácora')

@section('content_header')
    <h1>Registrar Bitácora</h1>
@stop

@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet"/>
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/estiloToggle.css') }}" type="text/css" rel="stylesheet"/>

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

    <style>
        #preview-fotos {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .preview-foto {
            position: relative;
            width: 150px;
            height: 150px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        .preview-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-foto .btn-eliminar-foto {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .preview-foto .btn-eliminar-foto:hover {
            background: rgba(220, 53, 69, 1);
        }
        .preview-foto .foto-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 10px;
            padding: 3px 5px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .zona-drop {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        .zona-drop:hover, .zona-drop.dragover {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
        .zona-drop i {
            font-size: 48px;
            color: #6c757d;
        }

        /* ── Loading overlay ── */
        #loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.55);
            align-items: center;
            justify-content: center;
        }
        #loading-overlay.activo {
            display: flex;
        }
        #loading-box {
            background: #fff;
            border-radius: 12px;
            padding: 2rem 3rem;
            text-align: center;
            min-width: 260px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        }
        #loading-box .spinner {
            width: 52px;
            height: 52px;
            border: 4px solid #e0e0e0;
            border-top-color: #007bff;
            border-radius: 50%;
            margin: 0 auto 1rem;
            animation: girar 0.75s linear infinite;
        }
        #loading-box p.titulo {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 4px;
            color: #212529;
        }
        #loading-box p.subtitulo {
            font-size: 13px;
            color: #6c757d;
            margin: 0;
        }
        @keyframes girar {
            to { transform: rotate(360deg); }
        }
    </style>

    {{-- ══ Loading overlay bloqueante ══════════════════════════════ --}}
    <div id="loading-overlay">
        <div id="loading-box">
            <div class="spinner"></div>
            <p class="titulo">Guardando bitácora...</p>
            <p class="subtitulo">Por favor espera,<br>no cierres esta página</p>
        </div>
    </div>

    <div id="divcontenedor">
        <section class="content">
            <div class="container-fluid">
                <form id="formulario-bitacora" enctype="multipart/form-data">

                    {{-- ══ Datos generales ══════════════════════════════════ --}}
                    <div class="card card-blue">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clipboard-list mr-2"></i>Datos Generales
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Fecha: <span style="color: red">*</span></label>
                                        <input type="date" class="form-control" id="fecha"
                                               value="{{ date('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Nombre: <span style="color: red">*</span></label>
                                        <input type="text" class="form-control" autocomplete="off"
                                               onpaste="contarcaracteresNombre();" onkeyup="contarcaracteresNombre();"
                                               maxlength="300" id="nombre"
                                               placeholder="Nombre o título de la bitácora">
                                        <div id="res-caracter-nombre" style="float: right">0/300</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Descripción: <span style="color: red">*</span></label>
                                <textarea class="form-control" id="descripcion" rows="4"
                                          placeholder="Describe la actividad realizada"
                                          onpaste="contarcaracteresDescripcion();"
                                          onkeyup="contarcaracteresDescripcion();"
                                          maxlength="2000"></textarea>
                                <div id="res-caracter-descripcion" style="float: right">0/2000</div>
                            </div>

                            <div class="form-group">
                                <label>Tiempo Utilizado: <span style="color: red">*</span></label>
                                <input type="text" class="form-control" autocomplete="off"
                                       maxlength="100" id="tiempo_utilizado"
                                       placeholder="Ej: 2 horas 30 minutos">
                            </div>
                        </div>
                    </div>

                    {{-- ══ Ubicación ════════════════════════════════════════ --}}
                    <div class="card card-blue">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-map-marker-alt mr-2"></i>Ubicación
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Ubicación: <span style="color: red">*</span></label>
                                <textarea class="form-control" id="ubicacion" rows="2"
                                          placeholder="Dirección o descripción del lugar"
                                          onpaste="contarcaracteresUbicacion();"
                                          onkeyup="contarcaracteresUbicacion();"
                                          maxlength="800"></textarea>
                                <div id="res-caracter-ubicacion" style="float: right">0/800</div>
                            </div>

                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Latitud: (Opcional)</label>
                                        <input type="text" class="form-control" autocomplete="off"
                                               maxlength="100" id="latitud"
                                               placeholder="Ej: 13.6929">
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label>Longitud: (Opcional)</label>
                                        <input type="text" class="form-control" autocomplete="off"
                                               maxlength="100" id="longitud"
                                               placeholder="Ej: -89.2182">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══ Personal asignado ════════════════════════════════ --}}
                    <div class="card card-blue">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users mr-2"></i>Personal Asignado
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Empleados: <span style="color: red">*</span></label>
                                <select class="form-control" id="select-empleados" multiple="multiple"
                                        style="width:100%">
                                    @foreach($arrayEmpleados as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Puede seleccionar uno o varios empleados</small>
                            </div>
                        </div>
                    </div>

                    {{-- ══ Fotografías ══════════════════════════════════════ --}}
                    <div class="card card-blue">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-camera mr-2"></i>Fotografías
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Subir Fotos: <small class="text-muted">(JPG, PNG - Máx. 5MB por foto)</small></label>

                                <div class="zona-drop" id="zona-drop"
                                     onclick="document.getElementById('input-fotos').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p class="mt-2 mb-0">
                                        <strong>Haz clic aquí</strong> o arrastra las fotos
                                    </p>
                                    <small class="text-muted">Puedes seleccionar varias fotos a la vez</small>
                                </div>

                                <input type="file" id="input-fotos" multiple accept="image/jpeg,image/png,image/jpg"
                                       style="display: none;" onchange="manejarFotos(this.files)">

                                <div id="preview-fotos"></div>

                                <small class="text-muted d-block mt-2">
                                    Fotos seleccionadas: <span id="contador-fotos">0</span>
                                </small>
                            </div>
                        </div>
                    </div>

                    {{-- ══ Botones ══════════════════════════════════════════ --}}
                    <div class="card">
                        <div class="card-body text-right">
                            <button type="button" class="btn btn-primary" id="btn-guardar" onclick="registrar()">
                                <i class="fas fa-save"></i> Guardar Bitácora
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </section>
    </div>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>

        var fotosSeleccionadas = [];
        var enviando = false; // ← bandera anti-doble envío

        // ── Loading overlay ───────────────────────────────────────────
        function mostrarLoading() {
            document.getElementById('loading-overlay').classList.add('activo');
            document.getElementById('btn-guardar').disabled = true;
        }

        function ocultarLoading() {
            document.getElementById('loading-overlay').classList.remove('activo');
            document.getElementById('btn-guardar').disabled = false;
        }

        // ── Inicializar ───────────────────────────────────────────────
        $(function () {
            $('#select-empleados').select2({
                theme: "bootstrap-5",
                placeholder: "Seleccione uno o varios empleados...",
                language: { noResults: function () { return "No encontrado"; } }
            });
            initDragDrop();
        });

        // ── Drag & Drop ───────────────────────────────────────────────
        function initDragDrop() {
            var zona = document.getElementById('zona-drop');
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (e) {
                zona.addEventListener(e, function (ev) { ev.preventDefault(); ev.stopPropagation(); }, false);
            });
            ['dragenter', 'dragover'].forEach(function (e) {
                zona.addEventListener(e, function () { zona.classList.add('dragover'); }, false);
            });
            ['dragleave', 'drop'].forEach(function (e) {
                zona.addEventListener(e, function () { zona.classList.remove('dragover'); }, false);
            });
            zona.addEventListener('drop', function (e) {
                manejarFotos(e.dataTransfer.files);
            }, false);
        }

        // ── Fotos ─────────────────────────────────────────────────────
        function manejarFotos(files) {
            var maxSize = 5 * 1024 * 1024;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                if (!file.type.match(/image\/(jpeg|jpg|png)/)) {
                    toastr.warning('El archivo "' + file.name + '" no es una imagen válida');
                    continue;
                }
                if (file.size > maxSize) {
                    toastr.warning('La imagen "' + file.name + '" excede los 5MB');
                    continue;
                }
                var idFoto = Date.now() + '_' + i;
                fotosSeleccionadas.push({ id: idFoto, file: file });
                generarPreview(idFoto, file);
            }
            document.getElementById('input-fotos').value = '';
            actualizarContador();
        }

        function generarPreview(idFoto, file) {
            var reader = new FileReader();
            reader.onload = function (e) {
                var div = document.createElement('div');
                div.className = 'preview-foto';
                div.id = 'foto-' + idFoto;
                div.innerHTML =
                    '<img src="' + e.target.result + '" alt="preview">' +
                    '<button type="button" class="btn-eliminar-foto" ' +
                    'onclick="eliminarFoto(\'' + idFoto + '\')" title="Eliminar">' +
                    '<i class="fas fa-times"></i></button>' +
                    '<div class="foto-info">' + file.name + '</div>';
                document.getElementById('preview-fotos').appendChild(div);
            };
            reader.readAsDataURL(file);
        }

        function eliminarFoto(idFoto) {
            fotosSeleccionadas = fotosSeleccionadas.filter(function (f) { return f.id !== idFoto; });
            var el = document.getElementById('foto-' + idFoto);
            if (el) el.remove();
            actualizarContador();
        }

        function actualizarContador() {
            document.getElementById('contador-fotos').innerText = fotosSeleccionadas.length;
        }

        // ── Registrar ─────────────────────────────────────────────────
        function registrar() {
            if (enviando) return; // bloquear doble clic

            var fecha            = $('#fecha').val();
            var nombre           = $('#nombre').val().trim();
            var descripcion      = $('#descripcion').val().trim();
            var ubicacion        = $('#ubicacion').val().trim();
            var latitud          = $('#latitud').val().trim();
            var longitud         = $('#longitud').val().trim();
            var tiempo_utilizado = $('#tiempo_utilizado').val().trim();
            var empleados        = $('#select-empleados').val();

            if (!fecha)                                  { toastr.error('La fecha es requerida'); return; }
            if (!nombre)                                 { toastr.error('El nombre es requerido'); return; }
            if (!descripcion)                            { toastr.error('La descripción es requerida'); return; }
            if (!ubicacion)                              { toastr.error('La ubicación es requerida'); return; }
            if (!tiempo_utilizado)                       { toastr.error('El tiempo utilizado es requerido'); return; }
            if (!empleados || empleados.length === 0)    { toastr.error('Debe asignar al menos un empleado'); return; }

            Swal.fire({
                title: '¿Registrar bitácora?',
                text: 'Se guardará con ' + empleados.length + ' empleado(s) y ' + fotosSeleccionadas.length + ' foto(s)',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.value === true) {
                    enviarFormulario();
                }
            });
        }

        function enviarFormulario() {
            if (enviando) return; // doble protección
            enviando = true;
            mostrarLoading();

            var formData = new FormData();
            formData.append('fecha',            $('#fecha').val());
            formData.append('nombre',           $('#nombre').val().trim());
            formData.append('descripcion',      $('#descripcion').val().trim());
            formData.append('ubicacion',        $('#ubicacion').val().trim());
            formData.append('latitud',          $('#latitud').val().trim());
            formData.append('longitud',         $('#longitud').val().trim());
            formData.append('tiempo_utilizado', $('#tiempo_utilizado').val().trim());

            $('#select-empleados').val().forEach(function (idEmp) {
                formData.append('empleados[]', idEmp);
            });

            fotosSeleccionadas.forEach(function (item) {
                formData.append('fotos[]', item.file);
            });

            axios.post(urlAdmin + '/admin/bitacoras/registro', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })
                .then(function (response) {
                    ocultarLoading();
                    enviando = false;

                    if (response.data.success === 1) {
                        toastr.success('Bitácora registrada correctamente');
                        setTimeout(function () {
                            window.location.href = urlAdmin + '/admin/bitacoras/registro/index';
                        }, 1000);
                    } else {
                        toastr.error(response.data.message || 'Error al registrar la bitácora');
                    }
                })
                .catch(function (error) {
                    ocultarLoading();
                    enviando = false;

                    if (error.response && error.response.data && error.response.data.message) {
                        toastr.error(error.response.data.message);
                    } else {
                        toastr.error('Error al registrar la bitácora');
                    }
                });
        }

        // ── Contadores ────────────────────────────────────────────────
        function contarcaracteresNombre() {
            setTimeout(function () {
                document.getElementById('res-caracter-nombre').innerHTML =
                    document.getElementById('nombre').value.length + '/300';
            }, 10);
        }
        function contarcaracteresDescripcion() {
            setTimeout(function () {
                document.getElementById('res-caracter-descripcion').innerHTML =
                    document.getElementById('descripcion').value.length + '/2000';
            }, 10);
        }
        function contarcaracteresUbicacion() {
            setTimeout(function () {
                document.getElementById('res-caracter-ubicacion').innerHTML =
                    document.getElementById('ubicacion').value.length + '/800';
            }, 10);
        }

    </script>
@endsection
