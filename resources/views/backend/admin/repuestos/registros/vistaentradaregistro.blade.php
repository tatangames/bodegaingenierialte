@extends('adminlte::page')

@section('title', 'Registro de Entradas')

@section('content_header')
    <h1>Registro de Entradas</h1>
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

    <style>
        #matriz {
            table-layout: auto;
            word-break: break-word;
            width: 100%;
        }

        #matriz-busqueda {
            table-layout: fixed;
        }

        .cursor-pointer:hover {
            cursor: pointer;
            color: #401fd2;
            font-weight: bold;
        }

        *:focus {
            outline: none;
        }
    </style>

    <div id="divcontenedor">

        {{-- CARD INFORMACIÓN DE INGRESO --}}
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-10">
                        <div class="card card-gray-dark">
                            <div class="card-header">
                                <h3 class="card-title">Información de Ingreso</h3>
                            </div>
                            <div class="card-body">

                                {{-- Fila 1: Fecha y Factura --}}
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Fecha: <span style="color: red">*</span></label>
                                            <input type="date" class="form-control" id="fecha">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Factura (Opcional):</label>
                                            <input type="text" class="form-control" autocomplete="off" maxlength="100" id="lote">
                                        </div>
                                    </div>
                                </div>

                                {{-- Fila 2: Proyecto --}}
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Proyecto: <span style="color: red">*</span></label>
                                            <select class="form-control" id="select-proyecto">
                                                @foreach($arrayProyecto as $sel)
                                                    <option value="{{ $sel->id }}">{{ $sel->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- Fila 3: Descripción y Botón --}}
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Descripción (Opcional):</label>
                                            <input type="text" class="form-control" autocomplete="off" maxlength="800" id="descripcion">
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end justify-content-end">
                                        <div class="form-group">
                                            <button type="button" onclick="abrirModal()" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Agregar Material
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- MODAL AGREGAR MATERIAL --}}
        <div class="modal fade" id="modalRepuesto">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Agregar Material</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-repuesto">
                            <div class="card-body">

                                <div class="form-group">
                                    <label class="control-label">Repuesto <span style="color: red">*</span></label>
                                    <p>La busqueda regresa: Material - Medida - Marca - Normativa - Color - Talla</p>
                                    <table class="table" id="matriz-busqueda" data-toggle="table">
                                        <tbody>
                                        <tr>
                                            <td>
                                                <input id="repuesto" data-info='0' autocomplete="off" class='form-control' style='width:100%' onkeyup='buscarMaterial(this)' maxlength='400' type='text'>
                                                <div class='droplista' style='position: absolute; z-index: 9; width: 75% !important;'></div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="form-group">
                                    <label class="control-label">Cantidad <span style="color: red">*</span></label>
                                    <div class="col-md-6">
                                        <input type="number" id="cantidad" min="0" max="1000000" class='form-control' autocomplete="off" placeholder="0">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label">Código</label>
                                    <div class="col-md-6">
                                        <input type="text" id="codigo" maxlength="100" class='form-control' autocomplete="off" placeholder="">
                                    </div>
                                </div>

                                <div class="form-group col-md-4" style="margin-top: 5px">
                                    <label class="control-label" style="color: #686868">Precio (4 decimales máximo): <span style="color: red">*</span></label>
                                    <div>
                                        <input type="number" min="0" max="1000000" autocomplete="off" value="0" class="form-control" id="precio-producto" placeholder="0.00">
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="agregarFila()">Agregar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- TÍTULO DETALLE --}}
        <section class="content-header">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h2>Detalle de Ingreso</h2>
                </div>
            </div>
        </section>

        {{-- TABLA DETALLE DE INGRESO --}}
        <section class="content">
            <div class="container-fluid">
                <div class="card card-gray-dark">
                    <div class="card-header">
                        <h3 class="card-title">Información de Ingreso</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0" id="matriz" data-toggle="table">
                                <thead>
                                <tr>
                                    <th style="width: 6%">#</th>
                                    <th style="width: 40%">Descripción</th>
                                    <th style="width: 12%">Cantidad</th>
                                    <th style="width: 14%">Código</th>
                                    <th style="width: 14%">Precio</th>
                                    <th style="width: 16%">Opciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- BOTÓN GUARDAR --}}
        <div class="modal-footer justify-content-between" style="margin-top: 25px;">
            <button type="button" class="btn btn-success" onclick="preguntaGuardar()">Guardar</button>
        </div>

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

    <script type="text/javascript">
        $(document).ready(function(){

            var fecha = new Date();
            document.getElementById('fecha').value = fecha.toJSON().slice(0,10);

            window.seguroBuscador = true;
            window.txtContenedorGlobal = this;

            $(document).click(function(){
                $(".droplista").hide();
            });

            $(document).ready(function() {
                $('[data-toggle="popover"]').popover({
                    placement: 'top',
                    trigger: 'hover'
                });
            });

            $('#select-proyecto').select2({
                theme: "bootstrap-5",
                "language": {
                    "noResults": function(){
                        return "Busqueda no encontrada";
                    }
                },
            });

        });
    </script>

    <script>

        document.getElementById('cantidad').addEventListener('keypress', function (event) {
            if (event.key < '0' || event.key > '9') {
                event.preventDefault();
            }
        });

        function abrirModal(){
            document.getElementById("formulario-repuesto").reset();
            $('#modalRepuesto').css('overflow-y', 'auto');
            $('#modalRepuesto').modal({backdrop: 'static', keyboard: false});
        }

        function agregarFila(){
            var repuesto = document.querySelector('#repuesto');
            var nomRepuesto = document.getElementById('repuesto').value;
            var cantidad = document.getElementById('cantidad').value;
            var codigo = document.getElementById('codigo').value;
            var precioProducto = document.getElementById('precio-producto').value;

            if(repuesto.dataset.info == 0){
                toastr.error("Material es requerido");
                return;
            }

            var reglaNumeroEntero = /^[0-9]\d*$/;
            var reglaNumeroDiesDecimal = /^([0-9]+\.?[0-9]{0,10})$/;

            if(cantidad === ''){
                toastr.error('Cantidad es requerida');
                return;
            }

            if(!cantidad.match(reglaNumeroEntero)) {
                toastr.error('Cantidad debe ser número entero y no Negativo');
                return;
            }

            if(cantidad <= 0){
                toastr.error('Cantidad no debe ser negativo o cero');
                return;
            }

            if(cantidad > 1000000){
                toastr.error('Cantidad máximo 1 millón');
                return;
            }

            if(precioProducto === ''){
                toastr.error('Precio Producto es requerido');
                return;
            }

            if(!precioProducto.match(reglaNumeroDiesDecimal)) {
                toastr.error('Precio Producto debe ser número Decimal (10 decimales)');
                return;
            }

            if(precioProducto < 0){
                toastr.error('Precio Producto no debe ser negativo');
                return;
            }

            if(precioProducto > 9000000){
                toastr.error('Precio Producto debe ser máximo 9 millones');
                return;
            }

            var nFilas = $('#matriz >tbody >tr').length;
            nFilas += 1;

            var markup = "<tr>" +

                "<td>" +
                "<p id='fila" + (nFilas) + "' class='form-control mb-0' style='max-width: 65px'>" + (nFilas) + "</p>" +
                "</td>" +

                "<td>" +
                "<input name='descripcionArray[]' disabled data-info='" + repuesto.dataset.info + "' value='" + nomRepuesto + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<input name='cantidadArray[]' disabled value='" + cantidad + "' class='form-control' type='number'>" +
                "</td>" +

                "<td>" +
                "<input name='codigoArray[]' disabled value='" + codigo + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<input name='arrayPrecio[]' data-precio='" + precioProducto + "' disabled value='$" + precioProducto + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<button type='button' class='btn btn-block btn-danger btn-sm' onclick='borrarFila(this)'>Borrar</button>" +
                "</td>" +

                "</tr>";

            $("#matriz tbody").append(markup);

            toastr.success("Agregado");

            $(txtContenedorGlobal).attr('data-info', '0');
            document.getElementById("formulario-repuesto").reset();
            document.getElementById('precio-producto').value = '0';
        }

        function borrarFila(elemento){
            var tabla = elemento.parentNode.parentNode;
            tabla.parentNode.removeChild(tabla);
            setearFila();
        }

        function setearFila(){
            var table = document.getElementById('matriz');
            var conteo = 0;
            for (var r = 1, n = table.rows.length; r < n; r++) {
                conteo += 1;
                var element = table.rows[r].cells[0].children[0];
                document.getElementById(element.id).innerHTML = "" + conteo;
            }
        }

        function buscarMaterial(e){
            if(seguroBuscador){
                seguroBuscador = false;

                var row = $(e).closest('tr');
                txtContenedorGlobal = e;

                let texto = e.value;

                if(texto === ''){
                    $(e).attr('data-info', 0);
                }

                axios.post(urlAdmin+'/admin/buscar/material', {
                    'query' : texto
                })
                    .then((response) => {
                        seguroBuscador = true;
                        $(row).each(function (index, element) {
                            $(this).find(".droplista").fadeIn();
                            $(this).find(".droplista").html(response.data);
                        });
                    })
                    .catch((error) => {
                        seguroBuscador = true;
                    });
            }
        }

        function modificarValor(edrop){
            let texto = $(edrop).text();
            $(txtContenedorGlobal).val(texto);
            $(txtContenedorGlobal).attr('data-info', edrop.id);
        }

        function preguntaGuardar(){
            colorBlancoTabla();

            Swal.fire({
                title: 'Guardar Entrada?',
                text: "",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Si'
            }).then((result) => {
                if (result.isConfirmed) {
                    guardarEntrada();
                }
            });
        }

        function guardarEntrada(){

            var fecha = document.getElementById('fecha').value;
            var descripc = document.getElementById('descripcion').value;
            var lote = document.getElementById('lote').value;
            var proyecto = document.getElementById('select-proyecto').value;

            if(fecha === ''){
                toastr.error('Fecha es requerida');
                return;
            }

            if(proyecto === ''){
                toastr.error('Proyecto es requerido');
                return;
            }

            var reglaNumeroEntero = /^[0-9]\d*$/;
            var nRegistro = $('#matriz > tbody >tr').length;

            if (nRegistro <= 0){
                toastr.error('Registro Entrada son requeridos');
                return;
            }

            var descripcionAtributo = $("input[name='descripcionArray[]']").map(function(){return $(this).attr("data-info");}).get();
            var cantidad = $("input[name='cantidadArray[]']").map(function(){return $(this).val();}).get();
            var codigo = $("input[name='codigoArray[]']").map(function(){return $(this).val();}).get();
            var arrayPrecio = $("input[name='arrayPrecio[]']").map(function(){return $(this).attr("data-precio");}).get();

            var reglaNumeroDiesDecimal = /^([0-9]+\.?[0-9]{0,10})$/;

            for(var a = 0; a < cantidad.length; a++){

                let detalle = descripcionAtributo[a];
                let datoCantidad = cantidad[a];
                let precioProducto = arrayPrecio[a];

                if(detalle == 0){
                    colorRojoTabla(a);
                    alertaMensaje('info', 'No encontrado', 'En la Fila #' + (a+1) + " El material no se encuentra. Por favor buscar de nuevo el Material");
                    return;
                }

                if (datoCantidad === '') {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad es requerida');
                    return;
                }

                if (!datoCantidad.match(reglaNumeroEntero)) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad debe ser Entero y no negativo');
                    return;
                }

                if (datoCantidad <= 0) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad no debe ser negativo');
                    return;
                }

                if (datoCantidad > 1000000) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad máximo 1 millón');
                    return;
                }

                if (precioProducto === '') {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Precio de producto es requerida.');
                    return;
                }

                if (!precioProducto.match(reglaNumeroDiesDecimal)) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Precio debe ser decimal (10 decimales) y no negativo.');
                    return;
                }

                if (precioProducto < 0) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Precio no debe ser negativo.');
                    return;
                }

                if (precioProducto > 9000000) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Precio máximo 9 millones.');
                    return;
                }
            }

            let formData = new FormData();
            const contenedorArray = [];

            for(var p = 0; p < cantidad.length; p++){
                let idMaterial = descripcionAtributo[p];
                let infoCantidad = cantidad[p];
                let infoCodigo = codigo[p];
                let infoPrecio = arrayPrecio[p];
                contenedorArray.push({ idMaterial, infoCantidad, infoCodigo, infoPrecio });
            }

            openLoading();

            formData.append('fecha', fecha);
            formData.append('descripcion', descripc);
            formData.append('lote', lote);
            formData.append('tipoproyecto', proyecto);
            formData.append('contenedorArray', JSON.stringify(contenedorArray));

            axios.post(urlAdmin+'/admin/entradas/guardar', formData, {})
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Registrado correctamente');
                        limpiar();
                    } else {
                        toastr.error('error al guardar');
                    }
                })
                .catch((error) => {
                    toastr.error('error al guardar');
                    closeLoading();
                });
        }

        function colorRojoTabla(index){
            $("#matriz tr:eq("+(index+1)+")").css('background', '#F1948A');
        }

        function colorBlancoTabla(){
            $("#matriz tbody tr").css('background', 'white');
        }

        function limpiar(){
            document.getElementById('descripcion').value = '';
            document.getElementById('precio-producto').value = '0';
            $("#matriz tbody tr").remove();
        }

    </script>

@endsection
