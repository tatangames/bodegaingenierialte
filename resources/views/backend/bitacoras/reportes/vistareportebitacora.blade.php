@extends('adminlte::page')

@section('title', 'Reportes de Bitácoras')

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
            border: none; border-radius: 12px;
            box-shadow: 0 2px 18px rgba(0,0,0,.10);
            margin-bottom: 24px; overflow: hidden;
        }
        .reporte-header {
            padding: 14px 20px; display: flex;
            align-items: center; gap: 12px;
        }
        .reporte-header.bitacoras { background: linear-gradient(135deg, #1a3a6b, #2874a6); }
        .reporte-header i  { font-size: 22px; color: #fff; }
        .reporte-header h5 {
            color: #fff; font-size: 14px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .05em; margin: 0;
        }
        .reporte-body { padding: 22px 24px; background: #fff; }
        .field-label {
            font-size: 11px; font-weight: 700; color: #6b7a99;
            text-transform: uppercase; letter-spacing: .06em;
            margin-bottom: 6px; display: block;
        }
        .divider { border: none; border-top: 2px dashed #e8eef8; margin: 12px 0 18px 0; }
        .btn-pdf {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 20px; border-radius: 8px; font-weight: 600;
            font-size: 13px; border: none; cursor: pointer;
            transition: all .2s; margin-top: 14px;
        }
        .btn-pdf.azul { background: linear-gradient(135deg, #1a3a6b, #2874a6); color: #fff; box-shadow: 0 4px 14px rgba(40,116,166,.35); }
        .btn-pdf:hover { transform: translateY(-1px); filter: brightness(1.08); color: #fff; }

        .fecha-row { display: flex; gap: 14px; margin-bottom: 14px; }
        .fecha-box { flex: 1; }
        .fecha-box label {
            font-size: 11px; font-weight: 700; color: #6b7a99;
            text-transform: uppercase; margin-bottom: 4px; display: block;
        }

        .checkbox-fotos {
            display: flex; align-items: center; gap: 8px;
            margin-top: 10px; font-size: 13px; color: #555;
        }
        .checkbox-fotos input[type="checkbox"] {
            width: 18px; height: 18px; cursor: pointer;
        }
    </style>

    <div id="divcontenedor" style="display:none">
        <section class="content">
            <div class="container-fluid">
                <div class="row justify-content-center">

                    <div class="col-md-7">
                        <div class="reporte-card">
                            <div class="reporte-header bitacoras">
                                <i class="fas fa-clipboard-list"></i>
                                <h5>Reporte de Bitácoras</h5>
                            </div>
                            <div class="reporte-body">
                                <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                    Genera un reporte PDF con el detalle de las bitácoras registradas,
                                    incluyendo empleados, ubicación y fotos.
                                </p>
                                <hr class="divider">

                                {{-- Fechas --}}
                                <div class="fecha-row">
                                    <div class="fecha-box">
                                        <label>Desde</label>
                                        <input type="date" class="form-control" id="reporte-desde">
                                    </div>
                                    <div class="fecha-box">
                                        <label>Hasta</label>
                                        <input type="date" class="form-control" id="reporte-hasta">
                                    </div>
                                </div>

                                {{-- Empleado --}}
                                <label class="field-label">
                                    <i class="fas fa-user mr-1"></i>Empleado (opcional)
                                </label>
                                <select class="form-control" id="select-empleado-reporte">
                                    <option value="">— Todos los empleados —</option>
                                    @foreach($arrayEmpleados as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                                    @endforeach
                                </select>

                                {{-- Incluir fotos --}}
                                <div class="checkbox-fotos">
                                    <input type="checkbox" id="incluir-fotos" checked>
                                    <label for="incluir-fotos" style="margin:0; cursor:pointer; font-weight:600;">
                                        Incluir fotografías en el reporte
                                    </label>
                                </div>

                                <br>
                                <button type="button" onclick="generarPdfBitacora()" class="btn-pdf azul">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
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
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            document.getElementById("divcontenedor").style.display = "block";

            $('#select-empleado-reporte').select2({
                theme: "bootstrap-5",
                allowClear: true,
                placeholder: '— Todos los empleados —',
                language: { noResults: function () { return "No encontrado"; } }
            });
        });

        function generarPdfBitacora() {
            var desde     = document.getElementById('reporte-desde').value || 'null';
            var hasta     = document.getElementById('reporte-hasta').value || 'null';
            var empleado  = $('#select-empleado-reporte').val() || 'null';
            var conFotos  = document.getElementById('incluir-fotos').checked ? '1' : '0';

            window.open("{{ URL::to('admin/bitacoras/reportes/pdf') }}/"
                + desde + "/" + hasta + "/" + empleado + "/" + conFotos);
        }
    </script>
@endsection
