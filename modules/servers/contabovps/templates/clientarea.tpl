{*
 * clientarea.tpl — Panel del cliente para ContaboVPS
 * WHMCS Smarty Template (Bootstrap 3)
 *}

{* ── Error global ─────────────────────────────────────────────────────────── *}
{if $error}
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-triangle"></i>
        {$error|escape}
    </div>
{else}

{* ── Alerta de resultado de acción ───────────────────────────────────────── *}
{if $actionResult}
    {if $actionResult.success}
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            <i class="fa fa-check-circle"></i> {$actionResult.message|escape}
        </div>
    {else}
        <div class="alert alert-danger alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            <i class="fa fa-times-circle"></i> {$actionResult.message|escape}
        </div>
    {/if}
{/if}

{* ── Panel principal ─────────────────────────────────────────────────────── *}
<div class="panel panel-default contabo-vps-panel">

    {* Header *}
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-server"></i>
            &nbsp;Mi VPS
            {if $hostname} — <strong>{$hostname|escape}</strong>{/if}
        </h3>
    </div>

    {* Info del servidor *}
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-6">
                <table class="table table-condensed table-borderless contabo-info-table">
                    <tbody>
                        <tr>
                            <th>Estado</th>
                            <td>
                                {if $status == 'running'}
                                    <span class="label label-success"><i class="fa fa-circle"></i> Running</span>
                                {elseif $status == 'stopped'}
                                    <span class="label label-danger"><i class="fa fa-circle-o"></i> Stopped</span>
                                {elseif $status == 'installing'}
                                    <span class="label label-warning"><i class="fa fa-spinner fa-spin"></i> Installing</span>
                                {else}
                                    <span class="label label-default">{$status|escape}</span>
                                {/if}
                            </td>
                        </tr>
                        <tr>
                            <th>IPv4</th>
                            <td><code>{$ipv4|escape}</code></td>
                        </tr>
                        {if $ipv6}
                        <tr>
                            <th>IPv6</th>
                            <td><code>{$ipv6|escape}</code></td>
                        </tr>
                        {/if}
                        <tr>
                            <th>Región</th>
                            <td>{$region|escape}</td>
                        </tr>
                        <tr>
                            <th>Tipo</th>
                            <td>{$productType|escape}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-sm-6">
                <table class="table table-condensed table-borderless contabo-info-table">
                    <tbody>
                        <tr>
                            <th><i class="fa fa-microchip"></i> CPU</th>
                            <td>{$cpuCores} cores</td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-memory"></i> RAM</th>
                            <td>{$ramGb} GB</td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-hdd-o"></i> Disco</th>
                            <td>{$diskGb} GB</td>
                        </tr>
                        <tr>
                            <th>Instance ID</th>
                            <td><small class="text-muted">{$instanceId|escape}</small></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {* ── Controles de energía ─────────────────────────────────────────────── *}
    <div class="panel-footer">
        <form method="POST" action="" id="contabo-power-form">
            <input type="hidden" name="token" value="{$csrfToken|escape}">

            <div class="btn-group" role="group">

                {* Encender — solo si está stopped *}
                {if $status != 'running'}
                    <button type="submit" name="vps_action" value="start"
                            class="btn btn-success btn-sm">
                        <i class="fa fa-play"></i> Encender
                    </button>
                {else}
                    <button type="button" class="btn btn-success btn-sm" disabled>
                        <i class="fa fa-play"></i> Encender
                    </button>
                {/if}

                {* Apagar — solo si está running *}
                {if $status == 'running'}
                    <button type="submit" name="vps_action" value="stop"
                            class="btn btn-danger btn-sm contabo-confirm"
                            data-confirm="¿Apagar el VPS (forzado)? Perderás datos no guardados.">
                        <i class="fa fa-stop"></i> Apagar
                    </button>
                {else}
                    <button type="button" class="btn btn-danger btn-sm" disabled>
                        <i class="fa fa-stop"></i> Apagar
                    </button>
                {/if}

                {* Reiniciar — solo si está running *}
                {if $status == 'running'}
                    <button type="submit" name="vps_action" value="restart"
                            class="btn btn-warning btn-sm contabo-confirm"
                            data-confirm="¿Reiniciar el VPS? La conexión se interrumpirá brevemente.">
                        <i class="fa fa-refresh"></i> Reiniciar
                    </button>
                {else}
                    <button type="button" class="btn btn-warning btn-sm" disabled>
                        <i class="fa fa-refresh"></i> Reiniciar
                    </button>
                {/if}

                {* Shutdown suave — solo si está running *}
                {if $status == 'running'}
                    <button type="submit" name="vps_action" value="shutdown"
                            class="btn btn-default btn-sm contabo-confirm"
                            data-confirm="¿Enviar señal de apagado suave (ACPI)?">
                        <i class="fa fa-power-off"></i> Shutdown
                    </button>
                {else}
                    <button type="button" class="btn btn-default btn-sm" disabled>
                        <i class="fa fa-power-off"></i> Shutdown
                    </button>
                {/if}

            </div>{* /btn-group *}
        </form>
    </div>{* /panel-footer *}

</div>{* /panel principal *}

{* ── Snapshots ───────────────────────────────────────────────────────────── *}
<div class="panel panel-default">

    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-camera"></i>
            &nbsp;Snapshots
            <span class="badge">{$snapshots|count}</span>
        </h3>
    </div>

    <div class="panel-body">

        {* Lista de snapshots *}
        {if $snapshots}
            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Creado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $snapshots as $snap}
                    <tr>
                        <td>{$snap.name|escape}</td>
                        <td>
                            {if $snap.createdDate}
                                {$snap.createdDate|escape}
                            {else}
                                —
                            {/if}
                        </td>
                        <td>
                            <span class="label label-info">{$snap.status|default:'unknown'|escape}</span>
                        </td>
                        <td>
                            <form method="POST" action="" style="display:inline"
                                  class="contabo-snapshot-delete-form">
                                <input type="hidden" name="token" value="{$csrfToken|escape}">
                                <input type="hidden" name="vps_action" value="delete_snapshot">
                                <input type="hidden" name="snapshot_id" value="{$snap.snapshotId|escape}">
                                <button type="submit"
                                        class="btn btn-xs btn-danger contabo-confirm"
                                        data-confirm="¿Eliminar el snapshot '{$snap.name|escape}'? Esta acción no se puede deshacer.">
                                    <i class="fa fa-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <p class="text-muted text-center">
                <i class="fa fa-info-circle"></i> No hay snapshots disponibles.
            </p>
        {/if}

        <hr>

        {* Crear snapshot *}
        <form method="POST" action="" id="contabo-snapshot-form">
            <input type="hidden" name="token" value="{$csrfToken|escape}">
            <input type="hidden" name="vps_action" value="create_snapshot">

            <div class="form-group">
                <label for="snapshot_name">
                    <i class="fa fa-plus-circle"></i> Crear nuevo snapshot
                </label>
                <div class="input-group" style="max-width: 400px;">
                    <input type="text"
                           class="form-control"
                           id="snapshot_name"
                           name="snapshot_name"
                           placeholder="Nombre del snapshot"
                           maxlength="80"
                           required>
                    <span class="input-group-btn">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-camera"></i> Crear
                        </button>
                    </span>
                </div>
                <p class="help-block">El snapshot captura el estado actual del disco de tu VPS.</p>
            </div>
        </form>

    </div>{* /panel-body *}
</div>{* /panel snapshots *}

{/if}{* /if error *}

{* ── JavaScript ─────────────────────────────────────────────────────────── *}
<script>
(function () {
    // Confirmación antes de acciones destructivas
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.contabo-confirm');
        if (!btn) return;
        var msg = btn.getAttribute('data-confirm') || '¿Estás seguro?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });
})();
</script>

<style>
.contabo-vps-panel .panel-heading { background: #2c3e50; color: #fff; }
.contabo-vps-panel .panel-heading .panel-title { color: #fff; }
.contabo-info-table th { color: #777; font-weight: 600; white-space: nowrap; }
.contabo-info-table td { padding-left: 8px; }
</style>
