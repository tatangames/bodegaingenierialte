@extends('adminlte::page')

@section('title', 'Reporte Inventario')

{{-- Activa plugins que necesitas --}}
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')

    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />

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
    <section class="content" style="max-width: 50%">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="callout callout-info">
                        <h5 style="font-weight: bold"><i class="fas fa-info"></i> Generar Inventario</h5>
                        <div class="card">
                            <form class="form-horizontal">
                                <div class="card-body">

                                    <div class="form-group">

                                        <label style="color:#191818">
                                            Tipo de Reporte
                                        </label>

                                        <select class="form-control mt-2"
                                                id="select-tipo"
                                                style="width: 250px;">

                                            <option value="1">
                                                Juntos
                                            </option>

                                            <option value="2">
                                                Separado
                                            </option>

                                        </select>

                                    </div>

                                    <button type="button"
                                            onclick="generarPdf()"
                                            class="btn btn-outline-dark mt-3">

                                        <img src="{{ asset('images/logopdf.png') }}"
                                             width="55"
                                             height="55">

                                        Generar PDF

                                    </button>

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

        function generarPdf() {

            let tipo = document.getElementById('select-tipo').value;

            if(tipo === ''){

                toastr.error('Seleccionar Tipo');
                return;
            }

            window.open(
                "{{ URL::to('admin/reporte/inventario/pdf') }}/" + tipo
            );
        }

    </script>

@endsection
