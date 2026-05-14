<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 5%">ID</th>
                                <th style="width: 18%">Tipo de Proyecto</th>
                                <th style="width: 10%">Fecha</th>
                                <th style="width: 22%">Descripción</th>
                                <th style="width: 8%">
                                    Transferencia
                                    <span data-toggle="tooltip"
                                          data-placement="top"
                                          title="Indica si esta salida fue generada por una transferencia entre proyectos">
                                        <i class="fas fa-question-circle text-info" style="cursor:pointer"></i>
                                    </span>
                                </th>
                                <th style="width: 7%">Estado</th>
                                <th style="width: 25%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($arraySalidas as $dato)
                                @php $cerrado = $dato->tipoproyecto && $dato->tipoproyecto->transferido == 1; @endphp
                                <tr>
                                    <td class="text-center">
                                        <span class="badge badge-dark">#{{ $dato->id }}</span>
                                    </td>
                                    <td>{{ $dato->tipoproyecto->nombre ?? '' }}</td>
                                    <td>{{ $dato->fecha_fmt }}</td>
                                    <td>{{ $dato->descripcion ?? '' }}</td>
                                    <td class="text-center">
                                        @if($dato->es_transferencia)
                                            <span class="badge badge-info">Sí</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($cerrado)
                                            <span class="badge badge-danger">Cerrado</span>
                                        @else
                                            <span class="badge badge-success">Activo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">

                                        @if(!$cerrado)
                                            <button type="button"
                                                    class="btn btn-success btn-xs"
                                                    onclick="window.location.href='{{ url('/admin/historial/salidas/extras') }}/' + {{ $dato->id }}">
                                                <i class="fas fa-plus"></i> Extras
                                            </button>
                                        @endif

                                        <button type="button"
                                                style="margin: 3px"
                                                class="btn btn-info btn-xs"
                                                onclick="verDetalle({{ $dato->id }}, '{{ addslashes($dato->tipoproyecto->nombre ?? '') }}', '{{ $dato->fecha_fmt }}', {{ $cerrado ? 1 : 0 }})">
                                            <i class="fas fa-list"></i> Detalle
                                        </button>

                                        @if(!$cerrado)
                                            <button type="button"
                                                    style="margin: 3px"
                                                    class="btn btn-warning btn-xs"
                                                    onclick="modalEditar({{ $dato->id }})">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <button type="button"
                                                    style="margin: 3px"
                                                    class="btn btn-danger btn-xs"
                                                    onclick="eliminar({{ $dato->id }})">
                                                <i class="fas fa-trash"></i> Borrar
                                            </button>
                                        @endif

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

<script>
    $('[data-toggle="tooltip"]').tooltip();
</script>
