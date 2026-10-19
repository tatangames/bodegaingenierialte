<table id="tabla" class="table table-bordered table-striped table-sm" style="width:100%">
    <thead>
    <tr>
        <th>Fecha salida</th>
        <th>Salida #</th>
        <th>Material</th>
        <th>Unidad</th>
        <th class="text-center">Salió</th>
        <th class="text-center">Reingresado</th>
        <th class="text-center">Pendiente</th>
        <th>Descripción</th>
        <th style="width: 110px;">Acción</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lista as $fila)
        <tr>
            <td>{{ $fila->fecha }}</td>
            <td>{{ $fila->id_salida }}</td>
            <td>{{ $fila->material }}</td>
            <td>{{ $fila->medida }}</td>
            <td class="text-center">{{ $fila->cantidad_salida }}</td>
            <td class="text-center">{{ $fila->reingresado }}</td>
            <td class="text-center">
                @if($fila->pendiente > 0)
                    <span class="badge badge-warning">{{ $fila->pendiente }}</span>
                @else
                    <span class="badge badge-success">0</span>
                @endif
            </td>
            <td>{{ $fila->descripcion }}</td>
            <td>
                @if($fila->pendiente > 0)
                    <button type="button"
                            class="btn btn-warning btn-xs"
                            onclick="modalReingreso({{ $fila->id }}, {{ $fila->pendiente }}, @js($fila->material))">
                        <i class="fas fa-undo"></i> Reingresar
                    </button>
                @else
                    <span class="text-muted">Completo</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
