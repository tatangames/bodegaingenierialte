<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        @if($arraySalidas->isEmpty())
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p class="mb-0">No se encontraron salidas con los filtros seleccionados.</p>
                            </div>
                        @else
                            <table id="tabla" class="table table-bordered table-striped">
                                <thead>
                                <tr>
                                    <th style="width: 5%">ID</th>
                                    <th style="width: 18%">Tipo de Proyecto</th>
                                    <th style="width: 10%">Fecha</th>
                                    <th style="width: 22%">Descripción</th>
                                    <th style="width: 7%">Estado Proyecto</th>
                                    <th style="width: 25%">Opciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($arraySalidas as $dato)
                                    @php $cerrado = $dato->tipoproyecto && $dato->tipoproyecto->transferido == 1; @endphp
                                    <tr>
                                        <td>{{ $dato->id }}</td>
                                        <td>{{ $dato->tipoproyecto->nombre ?? '' }}</td>
                                        <td>{{ $dato->fecha_fmt }}</td>
                                        <td>{{ $dato->descripcion ?? '' }}</td>
                                        <td class="text-center">
                                            @if($cerrado)
                                                <span class="badge badge-danger">Cerrado</span>
                                            @else
                                                <span class="badge badge-success">En ejecución</span>
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
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    $('[data-toggle="tooltip"]').tooltip();
</script>
