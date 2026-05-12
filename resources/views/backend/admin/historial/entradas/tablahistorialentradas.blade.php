<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 18%">Tipo de Proyecto</th>
                                <th style="width: 10%">Fecha</th>
                                <th style="width: 10%">Factura</th>
                                <th style="width: 25%">Descripción</th>
                                <th style="width: 10%">Transferencia</th>
                                <th style="width: 12%">Proyecto Destino</th>
                                <th style="width: 10%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($arrayEntradas as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ $dato->tipoproyecto->nombre ?? '—' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($dato->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $dato->factura ?? '—' }}</td>
                                    <td>{{ $dato->descripcion ?? '—' }}</td>
                                    <td class="text-center">
                                        @if($dato->es_transferencia)
                                            <span class="badge badge-info">Sí</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>{{ $dato->tipoproyectoTransferencia->nombre ?? '—' }}</td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn btn-warning btn-xs"
                                                onclick="modalEditar({{ $dato->id }})">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-xs ml-1"
                                                onclick="eliminar({{ $dato->id }})">
                                            <i class="fas fa-trash"></i> Borrar
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
