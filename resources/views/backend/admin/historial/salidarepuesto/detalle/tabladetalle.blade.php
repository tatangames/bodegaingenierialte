<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width:5%">#</th>
                                <th style="width:15%">Fecha</th>
                                <th style="width:30%">Proyecto</th>
                                <th style="width:35%">Descripción</th>
                                <th style="width:10%">Tipo</th>
                                <th style="width:5%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($lista as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ $dato->fechaFormato }}</td>
                                    <td>{{ $dato->nomproy }}</td>
                                    <td>{{ $dato->descripcion ?? '-' }}</td>
                                    <td>
                                        @if($dato->es_transferencia)
                                            <span class="badge badge-warning">Transferencia</span>
                                        @else
                                            <span class="badge badge-primary">Salida</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button onclick="detalleHistorial({{ $dato->id }})"
                                                class="btn btn-info btn-sm"
                                                title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="informacion({{ $dato->id }})"
                                                class="btn btn-warning btn-sm"
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
