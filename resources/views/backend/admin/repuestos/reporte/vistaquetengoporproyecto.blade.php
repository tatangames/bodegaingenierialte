@extends('adminlte::page')

@section('title', 'Reporte Salida Proyecto')

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
                        <h5 style="font-weight: bold"><i class="fas fa-info"></i> Inventario Actual de un Proyecto</h5>
                        <div class="card">
                            <form class="form-horizontal">
                                <div class="card-body">

                                    <div class="form-group">
                                        <label style="color:#191818">Proyectos</label>
                                        <br>
                                        <div>
                                            <select class="form-control" id="select-proyecto">
                                                @foreach($proyectos as $dd)
                                                    <option value="{{ $dd->id }}">{{ $dd->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>


                                    <div class="form-group row">
                                        <div class="col-sm-7">
                                            <div class="info-box shadow">

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

        $('#select-proyecto').select2({
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
            var idproy = document.getElementById('select-proyecto').value;

            if(idproy === ''){
                toastr.error('Proyecto es requerido');
                return;
            }

            window.open("{{ URL::to('admin/reporte/quetengopor/proyectos/pdf') }}/" + idproy);
        }

    </script>


@endsection
