<div class="row mb-3">
    <div class="col-md-3">
        <div class="info-box mb-0">
            <span class="info-box-icon bg-warning"><i class="fas fa-undo"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Reingresos</span>
                <span class="info-box-number">{{ $lista->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box mb-0">
            <span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Unidades reingresadas</span>
                <span class="info-box-number">{{ number_format($totalCantidad) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box mb-0">
            <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Valor reingresado</span>
                <span class="info-box-number">$ {{ number_format($totalDinero, 2) }}</span>
            </div>
        </div>
    </div>
</div>

<table id="tabla" class="table table-bordered table-striped table-sm" style="width:100%">
    <thead>
    <tr>
        <th>Fecha reingreso</th>
        <th>Proyecto</th>
        <th>Salida #</th>
        <th>Fecha salida</th>
        <th>Material</th>
        <th>Unidad</th>
        <th class="text-center">Cantidad</th>
        <th class="text-right">Precio unit.</th>
        <th class="text-right">Total</th>
        <th style="min-width:220px;">Justificación</th>
        <th class="text-center">Documento</th>
        <th class="text-center" style="width:150px;">Acción</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lista as $fila)
        <tr>
            <td>{{ $fila->fecha_fmt }}</td>
            <td>{{ $fila->proyecto }}</td>
            <td>{{ $fila->id_salida }}</td>
            <td>{{ $fila->fecha_salida_fmt }}</td>
            <td>{{ $fila->material }}</td>
            <td>{{ $fila->medida }}</td>
            <td class="text-center"><strong>{{ $fila->cantidad }}</strong></td>
            <td class="text-right">$ {{ number_format($fila->precio, 4) }}</td>
            <td class="text-right">$ {{ number_format($fila->total, 2) }}</td>
            <td>{{ $fila->justificacion }}</td>
            <td class="text-center">
                @if($fila->url_archivo)
                    <a href="{{ $fila->url_archivo }}" target="_blank" rel="noopener"
                       class="btn btn-info btn-xs">
                        <i class="fas {{ $fila->es_pdf ? 'fa-file-pdf' : 'fa-image' }}"></i> Ver
                    </a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td class="text-center" style="white-space:nowrap;">
                @if($fila->puede_editar)
                    <button type="button" class="btn btn-primary btn-xs"
                            onclick="abrirEditarReingreso({{ $fila->id }})">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                @else
                    <button type="button" class="btn btn-secondary btn-xs"
                            onclick="mostrarMotivo('No se puede editar: el proyecto ya fue cerrado y este reingreso forma parte del cierre.')">
                        <i class="fas fa-lock"></i> Editar
                    </button>
                @endif

                @if($fila->motivo)
                    <button type="button" class="btn btn-secondary btn-xs"
                            title="Ver por qué no se puede eliminar"
                            onclick="mostrarMotivo(@js($fila->motivo))">
                        <i class="fas fa-lock"></i> Eliminar
                    </button>
                @else
                    <button type="button" class="btn btn-danger btn-xs"
                            onclick="eliminarReingreso({{ $fila->id }}, @js($fila->material), {{ $fila->cantidad }})">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
