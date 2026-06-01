<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 3%">ID</th>
                                <th style="width: 10%">Fecha</th>
                                <th style="width: 22%">Nombre</th>
                                <th style="width: 15%">Empleados</th>
                                <th style="width: 10%">Ubicación</th>
                                <th style="width: 8%">Tiempo</th>
                                <th style="width: 7%">Fotos</th>
                                <th style="width: 25%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($arrayBitacoras as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($dato->fecha)->format('d/m/Y') }}</td>
                                    <td>
                                        <strong>{{ $dato->nombre }}</strong>
                                        <br>
                                        <small class="text-muted">{{ Str::limit($dato->descripcion, 50) }}</small>
                                    </td>
                                    <td>
                                        @if($dato->empleados && $dato->empleados->count() > 0)
                                            @foreach($dato->empleados as $emp)
                                                <div style="margin-top: 5px">
                                                    <span class="badge badge-info">{{ $emp->nombre }}</span>
                                                </div>
                                            @endforeach
                                        @else
                                            <span class="text-muted text-sm">Sin empleados</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($dato->ubicacion, 20) }}</td>
                                    <td>{{ $dato->tiempo_utilizado }}</td>
                                    <td class="text-center">
                                        @if($dato->fotos && $dato->fotos->count() > 0)
                                            <span class="badge badge-success" style="font-size: 13px;">
                                                <i class="fas fa-camera mr-1"></i>{{ $dato->fotos->count() }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button"
                                                style="margin: 2px"
                                                class="btn btn-info btn-xs"
                                                title="Ver detalle completo"
                                                onclick="verDetalle({{ $dato->id }})">
                                            <i class="fas fa-eye"></i> Ver
                                        </button>

                                        <button type="button"
                                                style="margin: 2px"
                                                class="btn btn-warning btn-xs"
                                                title="Editar bitácora"
                                                onclick="modalEditar({{ $dato->id }})">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>

                                        <button type="button"
                                                style="margin: 2px"
                                                class="btn btn-danger btn-xs"
                                                title="Eliminar bitácora"
                                                onclick="eliminar({{ $dato->id }}, '{{ addslashes($dato->nombre) }}')">
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
