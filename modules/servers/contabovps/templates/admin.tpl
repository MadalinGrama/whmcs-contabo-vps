{*
 * admin.tpl — Panel Admin para ContaboVPS
 * WHMCS Smarty Template (Bootstrap 3)
 *}

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-server"></i> Contabo VPS — Detalle de Instancia
        </h3>
    </div>

    {if $error}
        <div class="panel-body">
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i>
                {$error|escape}
            </div>
        </div>
    {else}
        <div class="panel-body">

            <div class="row">

                {* ── Información general ──────────────────────────────────── *}
                <div class="col-md-6">
                    <h4><i class="fa fa-info-circle"></i> Información General</h4>
                    <table class="table table-bordered table-condensed">
                        <tbody>
                            <tr>
                                <th style="width:40%">Instance ID</th>
                                <td><code>{$instanceId|escape}</code></td>
                            </tr>
                            <tr>
                                <th>Hostname</th>
                                <td>{$hostname|escape}</td>
                            </tr>
                            <tr>
                                <th>Estado</th>
                                <td>
                                    {if $status == 'running'}
                                        <span class="label label-success">
                                            <i class="fa fa-circle"></i> Running
                                        </span>
                                    {elseif $status == 'stopped'}
                                        <span class="label label-danger">
                                            <i class="fa fa-circle-o"></i> Stopped
                                        </span>
                                    {elseif $status == 'installing'}
                                        <span class="label label-warning">
                                            <i class="fa fa-spinner fa-spin"></i> Installing
                                        </span>
                                    {else}
                                        <span class="label label-default">{$status|escape}</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>Tipo de Producto</th>
                                <td>{$productType|escape}</td>
                            </tr>
                            <tr>
                                <th>Región</th>
                                <td>{$region|escape}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {* ── Red ──────────────────────────────────────────────────── *}
                <div class="col-md-6">
                    <h4><i class="fa fa-globe"></i> Red</h4>
                    <table class="table table-bordered table-condensed">
                        <tbody>
                            <tr>
                                <th style="width:40%">IPv4</th>
                                <td>
                                    {if $ipv4}
                                        <code>{$ipv4|escape}</code>
                                    {else}
                                        <span class="text-muted">—</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>IPv6</th>
                                <td>
                                    {if $ipv6}
                                        <code>{$ipv6|escape}</code>
                                    {else}
                                        <span class="text-muted">—</span>
                                    {/if}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h4><i class="fa fa-microchip"></i> Recursos</h4>
                    <table class="table table-bordered table-condensed">
                        <tbody>
                            <tr>
                                <th style="width:40%">CPU</th>
                                <td>{$cpuCores} cores</td>
                            </tr>
                            <tr>
                                <th>RAM</th>
                                <td>{$ramGb} GB ({$ramMb} MB)</td>
                            </tr>
                            <tr>
                                <th>Disco</th>
                                <td>{$diskGb} GB ({$diskMb} MB)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>{* /row *}

            {* ── Snapshots ────────────────────────────────────────────────── *}
            <div class="row">
                <div class="col-md-12">
                    <h4>
                        <i class="fa fa-camera"></i> Snapshots
                        <span class="badge">{$snapshots|count}</span>
                    </h4>

                    {if $snapshots}
                        <table class="table table-bordered table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Snapshot ID</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                    <th>Tamaño</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $snapshots as $snap}
                                <tr>
                                    <td><code>{$snap.snapshotId|escape}</code></td>
                                    <td>{$snap.name|escape}</td>
                                    <td>
                                        <span class="label label-info">
                                            {$snap.status|default:'unknown'|escape}
                                        </span>
                                    </td>
                                    <td>{$snap.createdDate|default:'—'|escape}</td>
                                    <td>
                                        {if $snap.sizeMb}
                                            {$snap.sizeMb} MB
                                        {else}
                                            —
                                        {/if}
                                    </td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    {else}
                        <p class="text-muted"><i class="fa fa-info-circle"></i> Sin snapshots.</p>
                    {/if}
                </div>
            </div>

        </div>{* /panel-body *}
    {/if}

    <div class="panel-footer text-muted small">
        <i class="fa fa-clock-o"></i>
        Datos obtenidos en tiempo real desde la API de Contabo.
        <strong>Instance ID:</strong> {$instanceId|escape}
    </div>

</div>
