<?php

/**
 * ContaboApi — Helper class for Contabo Cloud API v1
 *
 * Auth: OAuth2 Resource Owner Password Credentials (grant_type=password)
 * Token lifetime: ~300s. Auto-refreshes on expiry.
 *
 * @author  Luis (WHMCS Module Dev)
 * @version 1.0.0
 */

declare(strict_types=1);

class ContaboApi
{
    private const AUTH_URL = 'https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token';
    private const API_BASE = 'https://api.contabo.com/v1';

    private string $clientId;
    private string $clientSecret;
    private string $apiUser;
    private string $apiPassword;

    /** Cached token data */
    private ?string $accessToken  = null;
    private int     $tokenExpires = 0;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $apiUser,
        string $apiPassword
    ) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiUser      = $apiUser;
        $this->apiPassword  = $apiPassword;
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    /**
     * Returns a valid Bearer token, refreshing if expired.
     */
    public function getToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpires) {
            return $this->accessToken;
        }

        $payload = http_build_query([
            'grant_type'    => 'password',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username'      => $this->apiUser,
            'password'      => $this->apiPassword,
        ]);

        $ch = curl_init(self::AUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("ContaboApi: cURL error during auth — {$curlErr}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("ContaboApi: auth failed (HTTP {$httpCode}) — {$response}");
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('ContaboApi: no access_token in auth response');
        }

        $this->accessToken  = $data['access_token'];
        // Subtract 30s buffer so we refresh before actual expiry
        $this->tokenExpires = time() + (int)($data['expires_in'] ?? 300) - 30;

        return $this->accessToken;
    }

    // -------------------------------------------------------------------------
    // Instances
    // -------------------------------------------------------------------------

    /**
     * List all compute instances in the account.
     */
    public function getInstances(): array
    {
        return $this->request('GET', '/compute/instances');
    }

    /**
     * Get details for a single instance.
     */
    public function getInstance(string $instanceId): array
    {
        $data = $this->request('GET', "/compute/instances/{$instanceId}");
        if (array_is_list($data)) {
            if ($data === []) {
                throw new \RuntimeException('ContaboApi: empty response received for instance request');
            }
            return $data[0];
        }
        return $data;
    }

    /**
     * Power on an instance.
     */
    public function startInstance(string $instanceId): bool
    {
        $this->request('POST', "/compute/instances/{$instanceId}/actions/start");
        return true;
    }

    /**
     * Force power off (hard stop).
     */
    public function stopInstance(string $instanceId): bool
    {
        $this->request('POST', "/compute/instances/{$instanceId}/actions/stop");
        return true;
    }

    /**
     * Hard reboot.
     */
    public function restartInstance(string $instanceId): bool
    {
        $this->request('POST', "/compute/instances/{$instanceId}/actions/restart");
        return true;
    }

    /**
     * Graceful ACPI shutdown.
     */
    public function shutdownInstance(string $instanceId): bool
    {
        $this->request('POST', "/compute/instances/{$instanceId}/actions/shutdown");
        return true;
    }

    // -------------------------------------------------------------------------
    // Snapshots
    // -------------------------------------------------------------------------

    /**
     * List snapshots for a given instance.
     */
    public function getSnapshots(string $instanceId): array
    {
        return $this->request('GET', "/snapshots?instanceId={$instanceId}");
    }

    /**
     * Create a named snapshot for an instance.
     */
    public function createSnapshot(string $instanceId, string $name): array
    {
        return $this->request('POST', '/snapshots', [
            'instanceId' => $instanceId,
            'name'       => $name,
        ]);
    }

    /**
     * Delete a snapshot by ID.
     */
    public function deleteSnapshot(string $instanceId, string $snapshotId): bool
    {
        $this->request('DELETE', "/snapshots/{$snapshotId}");
        return true;
    }

    // -------------------------------------------------------------------------
    // Images
    // -------------------------------------------------------------------------

    /**
     * Get list of available OS images.
     */
    public function getAvailableImages(): array
    {
        return $this->request('GET', '/compute/images');
    }

    /**
     * Reinstall instance with a new image (PUT replaces the instance image config).
     */
    public function reinstallInstance(string $instanceId, string $imageId): bool
    {
        $this->request('PUT', "/compute/instances/{$instanceId}", [
            'imageId' => $imageId,
        ]);
        return true;
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helper
    // -------------------------------------------------------------------------

    /**
     * Perform an authenticated API request.
     *
     * @param  string     $method  GET | POST | PUT | DELETE
     * @param  string     $path    Path relative to /v1 (e.g. /compute/instances)
     * @param  array|null $body    JSON body for POST/PUT
     * @return array               Decoded response data (may be empty for 204)
     * @throws \RuntimeException   On HTTP errors or cURL failures
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $token = $this->getToken();
        $url   = self::API_BASE . $path;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'x-request-id: ' . $this->generateRequestId(),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if (in_array($method, ['POST', 'PUT'], true) && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'POST' && $body === null) {
            // Actions with no body still need Content-Length: 0
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("ContaboApi: cURL error — {$curlErr}");
        }

        // 204 No Content is success with no body
        if ($httpCode === 204) {
            return [];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $detail = $response ?: '(no body)';
            throw new \RuntimeException("ContaboApi: HTTP {$httpCode} on {$method} {$path} — {$detail}");
        }

        if (empty($response)) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('ContaboApi: invalid JSON response — ' . json_last_error_msg());
        }

        // Contabo wraps list responses in { "data": [...] }
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Generate a UUID v4 for x-request-id header (required by Contabo API).
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
