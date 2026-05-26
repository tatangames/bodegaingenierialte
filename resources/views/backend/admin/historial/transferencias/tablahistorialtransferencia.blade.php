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
                                <th style="width: 16%">Origen</th>
                                <th style="width: 16%">Destino</th>
                                <th style="width: 10%">Tipo</th>
                                <th style="width: 9%">Fecha</th>
                                <th style="width: 15%">Descripción</th>
                                <th style="width: 29%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($arrayTransferencias as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ $dato->nombre_origen }}</td>
                                    <td>{{ $dato->nombre_destino }}</td>
                                    <td class="text-center">
                                        @if($dato->tipo_salida === 'general')
                                            <span class="badge badge-warning">
                                            <i class="fas fa-warehouse"></i> Salida General
                                        </span>

                                        @elseif($dato->tipo_salida === 'proyecto')
                                            <span class="badge badge-success">
                                            <i class="fas fa-exchange-alt"></i> Proyecto
                                        </span>

                                        @elseif($dato->tipo_salida === 'snapshot')
                                            <span class="badge badge-warning">
                                            <i class="fas fa-exchange-alt"></i> Proyecto Finalizado
                                        </span>

                                            <small class="text-muted d-block mt-1">
                                                Materiales sobrantes capturados al momento de cerrar el proyecto.
                                            </small>
                                        @endif

                                        @if($dato->es_reserva)
                                            <br>
                                            <span class="badge badge-info mt-1">
                                                <i class="fas fa-bookmark"></i> Por Reserva
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $dato->fecha_fmt }}</td>
                                    <td>{{ $dato->descripcion ?? '' }}</td>
                                    <td class="text-center">

                                        <button type="button"
                                                class="btn btn-info btn-xs"
                                                style="margin: 3px"
                                                onclick="verDetalle(
                {{ $dato->id }},
                '{{ addslashes($dato->nombre_origen) }}',
                '{{ $dato->fecha_fmt }}',
                '',
                '{{ addslashes($dato->descripcion ?? '') }}'
            )">
                                            <i class="fas fa-list"></i> Detalle
                                        </button>

                                        {{-- Botón PDF — solo para transferencias normales (las de reserva no tienen datos del acta) --}}
                                        @if(! $dato->es_reserva)
                                            @if($dato->tipo_salida !== 'snapshot')
                                            <a href="{{ url('admin/historial/transferencias/acta/pdf/' . $dato->id) }}"
                                               target="_blank"
                                               class="btn btn-secondary btn-xs"
                                               style="margin: 3px">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                            @endif
                                        @endif

                                        @if($dato->tipo_salida !== 'snapshot')
                                            @if($dato->se_puede_borrar)
                                                <button type="button"
                                                        class="btn btn-danger btn-xs"
                                                        style="margin: 3px"
                                                        onclick="eliminar({{ $dato->id }})">
                                                    <i class="fas fa-trash"></i> Borrar
                                                </button>
                                            @else
                                                <span data-toggle="tooltip"
                                                      title="No se puede eliminar: el material ya fue usado o reservado en el proyecto destino."
                                                      style="display:inline-block; margin: 3px">
                                                    <button type="button"
                                                            class="btn btn-secondary btn-xs"
                                                            style="pointer-events:none; opacity:.65"
                                                            disabled>
                                                        <i class="fas fa-trash"></i> Borrar
                                                    </button>
                                                </span>
                                            @endif
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
