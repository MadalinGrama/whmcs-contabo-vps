<?php

/**
 * contabovps — WHMCS Provisioning Module for Contabo VPS
 *
 * Supports: CreateAccount, SuspendAccount, UnsuspendAccount, TerminateAccount
 * Client Area: power controls, snapshot management
 *
 * @author  Ed Álvarez — Plus Soluciones <edgardoalvarez100@gmail.com>
 * @version 1.0.0
 * @license MIT
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/ContaboApi.php';

// =============================================================================
// Module Metadata
// =============================================================================

function contabovps_MetaData(): array
{
    return [
        'DisplayName'               => 'Contabo VPS',
        'APIVersion'                => '1.1',
        'RequiresServer'            => true,
        'DefaultNonSSLPort'         => '',
        'DefaultSSLPort'            => '',
        'ServiceSingleSignOnLabel'  => '',
        'AdminSingleSignOnLabel'    => '',
    ];
}

// =============================================================================
// Server Configuration (Admin → Servers)
// =============================================================================

function contabovps_config(): array
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Contabo VPS Module',
        ],
        'client_id' => [
            'FriendlyName' => 'Client ID (OAuth2)',
            'Type'         => 'text',
            'Size'         => 50,
            'Default'      => '',
            'Description'  => 'Ej: INT-12345678',
        ],
        'client_secret' => [
            'FriendlyName' => 'Client Secret (OAuth2)',
            'Type'         => 'password',
            'Size'         => 80,
            'Default'      => '',
            'Description'  => 'OAuth2 client secret de Contabo',
        ],
        'api_user' => [
            'FriendlyName' => 'API User (email)',
            'Type'         => 'text',
            'Size'         => 80,
            'Default'      => '',
            'Description'  => 'Email de la cuenta Contabo',
        ],
        'api_password' => [
            'FriendlyName' => 'API Password',
            'Type'         => 'password',
            'Size'         => 80,
            'Default'      => '',
            'Description'  => 'Contraseña de la cuenta Contabo',
        ],
    ];
}

// =============================================================================
// Product Config Options (per-service)
// =============================================================================

function contabovps_ConfigOptions(): array
{
    return [
        'instance_id' => [
            'FriendlyName' => 'Instance ID de Contabo',
            'Type'         => 'text',
            'Size'         => 20,
            'Default'      => '',
            'Description'  => 'ID numérico de la instancia en Contabo (asignado manualmente por el admin)',
        ],
    ];
}

// =============================================================================
// Internal Helpers
// =============================================================================

/**
 * Build a ContaboApi instance from WHMCS $params (server credentials).
 */
function contabovps_getApi(array $params): ContaboApi
{
    $clientId     = trim((string)($params['serverusername'] ?? ''));
    $clientSecret = trim((string)($params['serverpassword'] ?? ''));

    $apiUser = '';
    $apiPassword = '';
    $accessHash = trim((string)($params['serveraccesshash'] ?? ''));
    if ($accessHash !== '') {
        $lines = preg_split("/\r?\n|\r/", $accessHash);
        $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
        if (count($lines) >= 2) {
            $apiUser = $lines[0];
            $apiPassword = $lines[1];
        } elseif (count($lines) === 1 && str_contains($lines[0], ':')) {
            [$userPart, $passPart] = array_map('trim', explode(':', $lines[0], 2));
            if ($userPart !== '' && $passPart !== '') {
                $apiUser = $userPart;
                $apiPassword = $passPart;
            }
        }
    }

    if ($clientId === '') {
        $clientId = trim((string)($params['configoption1'] ?? ''));
    }
    if ($clientSecret === '') {
        $clientSecret = trim((string)($params['configoption2'] ?? ''));
    }
    if ($apiUser === '') {
        $apiUser = trim((string)($params['configoption3'] ?? ''));
    }
    if ($apiPassword === '') {
        $apiPassword = trim((string)($params['configoption4'] ?? ''));
    }

    if (!$clientId || !$clientSecret || !$apiUser || !$apiPassword) {
        throw new \RuntimeException('ContaboVPS: faltan credenciales API en la configuración del servidor');
    }

    return new ContaboApi($clientId, $clientSecret, $apiUser, $apiPassword);
}

/**
 * Get the instance_id from the service's custom fields.
 * Custom fields are keyed by their field name.
 */
function contabovps_getInstanceId(array $params): string
{
    // WHMCS passes custom fields as $params['customfields'] (associative by name)
    $instanceId = $params['customfields']['instance_id']
        ?? $params['configoptions']['instance_id']
        ?? '';

    $instanceId = trim((string)$instanceId);

    if ($instanceId === '') {
        throw new \RuntimeException('ContaboVPS: instance_id no configurado para este servicio');
    }

    return $instanceId;
}

/**
 * Sanitize instance data from Contabo API response for template use.
 */
function contabovps_parseInstanceData(array $instance): array
{
    $ipv4 = '';
    $ipv6 = '';

    if (!empty($instance['ipConfig']['v4']['ip'])) {
        $ipv4 = $instance['ipConfig']['v4']['ip'];
    }
    if (!empty($instance['ipConfig']['v6']['ip'])) {
        $ipv6 = $instance['ipConfig']['v6']['ip'];
    }

    $ramMb  = (int)($instance['ramMb']  ?? 0);
    $diskMb = (int)($instance['diskMb'] ?? 0);
    $ramGb  = $ramMb  > 0 ? round($ramMb  / 1024, 1) : 0;
    $diskGb = $diskMb > 0 ? round($diskMb / 1024, 1) : 0;

    return [
        'instanceId'  => (string)($instance['instanceId']  ?? ''),
        'status'      => (string)($instance['status']       ?? 'unknown'),
        'hostname'    => htmlspecialchars((string)($instance['name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8'),
        'ipv4'        => htmlspecialchars($ipv4, ENT_QUOTES, 'UTF-8'),
        'ipv6'        => htmlspecialchars($ipv6, ENT_QUOTES, 'UTF-8'),
        'region'      => htmlspecialchars((string)($instance['region'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'productType' => htmlspecialchars((string)($instance['productType'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'cpuCores'    => (int)($instance['cpuCores'] ?? 0),
        'ramMb'       => $ramMb,
        'diskMb'      => $diskMb,
        'ramGb'       => $ramGb,
        'diskGb'      => $diskGb,
    ];
}

// =============================================================================
// Core Provisioning Functions
// =============================================================================

/**
 * Called when the product is activated for a client.
 * We only verify the instance exists; we don't auto-create VPS in Contabo.
 */
function contabovps_CreateAccount(array $params): string
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);

        // Verify the instance actually exists
        $api->getInstance($instanceId);

        return 'success';
    } catch (\Exception $e) {
        // WHMCS logs the return value; avoid leaking credentials
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend: stop (power-off) the VPS instance.
 */
function contabovps_SuspendAccount(array $params): string
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);

        $api->stopInstance($instanceId);

        return 'success';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend: start (power-on) the VPS instance.
 */
function contabovps_UnsuspendAccount(array $params): string
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);

        $api->startInstance($instanceId);

        return 'success';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate: we mark success but DO NOT delete the instance in Contabo.
 * The admin should handle physical decommission manually (safety measure).
 */
function contabovps_TerminateAccount(array $params): string
{
    // Safety: do not auto-delete VPS. Log the action for the admin.
    logModuleCall(
        'contabovps',
        __FUNCTION__,
        ['serviceId' => $params['serviceid'] ?? '', 'instanceId' => $params['customfields']['instance_id'] ?? ''],
        'TerminateAccount called — instance NOT deleted in Contabo. Manual action required.',
        '',
        []
    );

    return 'success';
}

// =============================================================================
// Admin Services Tab
// =============================================================================

function contabovps_AdminServicesTabFields(array $params): array
{
    try {
        $api = contabovps_getApi($params);
        $instanceId = $params['customfields']['instance_id'] ?? '';

        if (empty($instanceId)) {
            return ['Info' => '<p class="alert alert-warning">No hay Instance ID configurado para este servicio.</p>'];
        }

        $instance = $api->getInstance($instanceId);
        $snapshots = $api->getSnapshots($instanceId);

        // Render admin.tpl via Smarty
        $smarty = new \Smarty();
        $smarty->assign('instanceId',  $instanceId);
        $smarty->assign('status',      $instance['status'] ?? 'unknown');
        $smarty->assign('hostname',    $instance['name'] ?? '');
        $smarty->assign('productType', $instance['productType'] ?? '');
        $smarty->assign('region',      $instance['region'] ?? '');
        $smarty->assign('ipv4',        $instance['ipConfig']['v4']['ip'] ?? '');
        $smarty->assign('ipv6',        $instance['ipConfig']['v6']['ip'] ?? '');
        $smarty->assign('cpuCores',    $instance['cpuCores'] ?? 0);
        $smarty->assign('ramGb',       round(($instance['ramMb'] ?? 0) / 1024, 1));
        $smarty->assign('diskGb',      round(($instance['diskMb'] ?? 0) / 1024, 1));
        $smarty->assign('snapshots',   is_array($snapshots) ? $snapshots : []);

        $tplPath = __DIR__ . '/templates/admin.tpl';
        $html = $smarty->fetch($tplPath);

        return ['VPS Info' => $html];

    } catch (\Exception $e) {
        logModuleCall('contabovps', 'AdminServicesTabFields', [], $e->getMessage(), '', []);
        return ['Error' => '<p class="alert alert-danger">No se pudo conectar con la API de Contabo.</p>'];
    }
}

// =============================================================================
// Client Area
// =============================================================================

function contabovps_ClientArea(array $params): array
{
    $templateVars = [
        'instanceId'  => '',
        'status'      => 'unknown',
        'hostname'    => '',
        'ipv4'        => '',
        'ipv6'        => '',
        'region'      => '',
        'productType' => '',
        'cpuCores'    => 0,
        'ramMb'       => 0,
        'diskMb'      => 0,
        'ramGb'       => 0,
        'diskGb'      => 0,
        'snapshots'   => [],
        'action'      => '',
        'actionResult'=> null,
        'csrfToken'   => isset($_SESSION['token']) ? $_SESSION['token'] : '',
        'error'       => null,
    ];

    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);

        // Handle POST actions (power/snapshot)
        $action = $_GET['action'] ?? '';
        $templateVars['action'] = $action;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation
            $postedToken = $_POST['token'] ?? '';
            $sessionToken = $_SESSION['token'] ?? '';
            if (!$postedToken || !hash_equals($sessionToken, $postedToken)) {
                $templateVars['actionResult'] = ['success' => false, 'message' => 'Token de seguridad inválido.'];
            } else {
                $templateVars['actionResult'] = contabovps_handleClientPost($api, $instanceId, $_POST);
            }
        }

        // Fetch instance data
        $instance = $api->getInstance($instanceId);
        $parsed   = contabovps_parseInstanceData($instance);
        $templateVars = array_merge($templateVars, $parsed);

        // Fetch snapshots
        $snapshots = $api->getSnapshots($instanceId);
        $templateVars['snapshots'] = is_array($snapshots) ? $snapshots : [];

    } catch (\Exception $e) {
        logModuleCall(
            'contabovps',
            'ClientArea',
            ['instanceId' => $instanceId],
            $e->getMessage(),
            '',
            []
        );
        $templateVars['error'] = 'No se pudo cargar la información del VPS. Por favor contacta a soporte.';
    }

    return [
        'templatefile' => 'clientarea',
        'vars'         => $templateVars,
    ];
}

/**
 * Handle POST actions from the client area form.
 */
function contabovps_handleClientPost(ContaboApi $api, string $instanceId, array $post): array
{
    $action = $post['vps_action'] ?? '';

    try {
        switch ($action) {
            case 'start':
                $api->startInstance($instanceId);
                return ['success' => true, 'message' => 'Instancia iniciada correctamente.'];

            case 'stop':
                $api->stopInstance($instanceId);
                return ['success' => true, 'message' => 'Instancia apagada correctamente.'];

            case 'restart':
                $api->restartInstance($instanceId);
                return ['success' => true, 'message' => 'Instancia reiniciada correctamente.'];

            case 'shutdown':
                $api->shutdownInstance($instanceId);
                return ['success' => true, 'message' => 'Shutdown enviado correctamente.'];

            case 'create_snapshot':
                $name = trim($post['snapshot_name'] ?? '');
                if ($name === '') {
                    return ['success' => false, 'message' => 'El nombre del snapshot es requerido.'];
                }
                $api->createSnapshot($instanceId, $name);
                return ['success' => true, 'message' => "Snapshot '{$name}' creado correctamente."];

            case 'delete_snapshot':
                $snapshotId = trim($post['snapshot_id'] ?? '');
                if ($snapshotId === '') {
                    return ['success' => false, 'message' => 'ID de snapshot inválido.'];
                }
                $api->deleteSnapshot($instanceId, $snapshotId);
                return ['success' => true, 'message' => 'Snapshot eliminado correctamente.'];

            default:
                return ['success' => false, 'message' => 'Acción no reconocida.'];
        }
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// =============================================================================
// Custom Functions (WHMCS Module Commands)
// =============================================================================

function contabovps_start(array $params): array
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $api->startInstance($instanceId);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => 'Instancia iniciada.']],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}

function contabovps_stop(array $params): array
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $api->stopInstance($instanceId);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => 'Instancia apagada.']],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}

function contabovps_restart(array $params): array
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $api->restartInstance($instanceId);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => 'Instancia reiniciada.']],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}

function contabovps_shutdown(array $params): array
{
    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $api->shutdownInstance($instanceId);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => 'Shutdown enviado.']],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}

function contabovps_create_snapshot(array $params): array
{
    $name = trim($_POST['snapshot_name'] ?? 'snapshot-' . date('YmdHis'));

    try {
        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $result     = $api->createSnapshot($instanceId, $name);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => "Snapshot '{$name}' creado."]],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}

function contabovps_delete_snapshot(array $params): array
{
    $snapshotId = trim($_POST['snapshot_id'] ?? '');

    try {
        if ($snapshotId === '') {
            throw new \RuntimeException('snapshot_id no proporcionado');
        }

        $api        = contabovps_getApi($params);
        $instanceId = contabovps_getInstanceId($params);
        $api->deleteSnapshot($instanceId, $snapshotId);

        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => true, 'message' => 'Snapshot eliminado.']],
        ];
    } catch (\Exception $e) {
        return [
            'templatefile' => 'clientarea',
            'vars'         => ['actionResult' => ['success' => false, 'message' => $e->getMessage()]],
        ];
    }
}
