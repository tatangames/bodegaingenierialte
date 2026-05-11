@extends('adminlte::page')

@section('title', 'Salida por Material')

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
        <a href="#"
           class="nav-link"
           data-toggle="dropdown"
           role="button"
           aria-haspopup="true"
           aria-expanded="false">

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

        <form action="{{ route('admin.logout') }}"
              method="POST"
              class="d-inline">

            @csrf

            <button type="submit"
                    class="nav-link btn btn-link border-0 bg-transparent">

                <i class="fas fa-sign-out-alt"></i>

                <span class="d-none d-md-inline">
                    Cerrar Sesión
                </span>

            </button>

        </form>

    </li>

@endsection
@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">

        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="callout callout-info">
                        <h5 style="font-weight: bold"><i class="fas fa-info"></i> Generar Reporte de Salidas Por Material</h5>
                        <div class="card">
                            <form class="form-horizontal">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <div class="col-sm-3">
                                            <div class="info-box shadow">
                                                <div class="info-box-content">
                                                    <div class="form-group">
                                                        <label>Desde:</label>
                                                        <input type="date" class="form-control" id="fecha-desde">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="info-box shadow">
                                                <div class="info-box-content">
                                                    <div class="form-group">
                                                        <label>Hasta:</label>
                                                        <input type="date" class="form-control" id="fecha-hasta">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <p style="font-weight: bold; font-size: 22px">Lista de Materiales</p>

                                    <div class="form-group row">
                                        <div class="col-sm-10">
                                            <div class="info-box shadow">
                                                <div class="info-box-content">
                                                    <label>Seleccionar</label>
                                                    <select class="form-control" id="select-materiales"  multiple="multiple">
                                                        @foreach($arrayMateriales as $sel)
                                                            <option value="{{ $sel->id }}">{{ $sel->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <button type="button" onclick="generarPdf()" class="btn" style="margin-left: 10px; border-color: black; border-radius: 0.1px;">
                                                    <img src="{{ asset('images/logopdf.png') }}" width="55px" height="55px">
                                                    Generar PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>





@stop
@section('js')

    <script src="{{ asset('js/jquery.dataTables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/dataTables.bootstrap4.js') }}" type="text/javascript"></script>

    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/jquery.simpleaccordion.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>

        $('#select-materiales').select2({
            theme: "bootstrap-5",
            "language": {
                "noResults": function(){
                    return "Búsqueda no encontrada";
                }
            },
        });

    </script>

    <script>

        function generarPdf() {
            var desde = document.getElementById('fecha-desde').value;
            var hasta = document.getElementById('fecha-hasta').value;
            var valores = $('#select-materiales').val();

            if(valores.length ==  null || valores.length === 0){
                toastr.error('Seleccionar mínimo 1 Material');
                return;
            }

            var selected = [];
            for (var option of document.getElementById('select-materiales').options){
                if (option.selected) {
                    selected.push(option.value);
                }
            }

            if(desde === ''){
                toastr.error('Fecha desde es requerido');
                return;
            }

            if(hasta === ''){
                toastr.error('Fecha hasta es requerido');
                return;
            }

            let listado = selected.toString();
            let reemplazo = listado.replace(/,/g, "-");

            window.open("{{ URL::to('admin/pdf/salida/pormaterial/proyecto') }}/" + desde + "/" + hasta + "/" + reemplazo);
        }

    </script>


@endsection
