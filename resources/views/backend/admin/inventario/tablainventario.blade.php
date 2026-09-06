<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 6%">ID</th>
                                <th style="width: 8%">Marca</th>
                                <th style="width: 20%">Nombre</th>
                                <th style="width: 10%">Medida</th>
                                <th style="width: 10%">Cantidad Acumulada</th>
                                <th style="width: 15%">Objeto Específico</th>
                                <th style="width: 8%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($lista as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ $dato->codigo }}</td>
                                    <td>{{ $dato->nombre }}</td>
                                    <td>{{ $dato->medida }}</td>
                                    <td>{{ $dato->total }}</td>
                                    <td>
                                        @if($dato->objeto_especifico)
                                            <span class="badge badge-success">
                                                {{ $dato->objeto_especifico->codigo }} — {{ $dato->objeto_especifico->nombre }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td>

                                        @if($dato->entradas == 0)
                                            <button type="button" style="margin: 2px" class="btn btn-primary btn-xs"
                                                    onclick="informacion({{ $dato->id }})">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                        @else
                                            <span data-toggle="tooltip"
                                                  title="No se puede editar: este material ya tiene entradas registradas."
                                                  style="display:inline-block; margin: 2px">
                                                <button type="button"
                                                        class="btn btn-secondary btn-xs"
                                                        style="pointer-events:none; opacity:.65"
                                                        disabled>
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                            </span>
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
