<?php

namespace App\Clients\CPI;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

class MontaIntegrations {
    public static function getIntegrationLink ($cookies, $chargepointID): array {
        $cookieJar = new CookieJar();
        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->withoutRedirecting()->get("https://app.monta.app/cpis/start/" . $chargepointID);

        if (isset($response->headers()['location'])) {
            return [
                'status' => '200',
                'message' => 'Successfully got integration link',
                'link' => $response->headers()['location'][0]
            ];
        }

        return [
            'status' => '401',
            'message' => 'Failed to get integration link',
            'headers' => $response->headers()
        ];
    }

    public static function listIntegrations(): array {
        $response = Http::get("https://integrations-api.monta.app/api/integrations/");

        if ($response->successful()) {
            return [
                'status' => '200',
                'message' => 'Successfully got integrations',
                'data' => $response->json()
            ];
        }

        return [
            'status' => '401',
            'message' => 'Failed to get integrations',
            'error' => $response->json()
        ];
    }

    public static function getIntegrationFromURL(string $integrationURL): array {
        if (empty($integrationURL)) {
            return [
                'status' => '400',
                'message' => 'Integration URL is empty'
            ];
        }

        try {
            // Parse the URL and get the path
            $path = parse_url($integrationURL, PHP_URL_PATH);
            $pathSegments = explode('/', trim($path, '/'));

            // Extract the charge_point_model_identifier from the path
            $chargePointModelIdentifier = $pathSegments[3] ?? null;
            $chargePointBrand = $pathSegments[1] ?? null;

            // Parse the URL and get the query string
            $queryString = parse_url($integrationURL, PHP_URL_QUERY);

            // Parse the query string into an associative array
            parse_str($queryString, $queryParams);

        } catch (\Exception $e) {
            return [
                'status' => '500',
                'message' => 'Failed to get integration from URL',
                'error' => $e->getMessage()
            ];
        }

        if (empty($queryParams['user_identifier'])) {
            return [
                'status' => '400',
                'message' => 'user_identifier is missing'
            ];
        }

        if (empty($queryParams['charge_point_identifier'])) {
            return [
                'status' => '400',
                'message' => 'charge_point_identifier is missing'
            ];
        }

        if (empty($chargePointModelIdentifier)) {
            return [
                'status' => '400',
                'message' => 'charge_point_model_identifier is missing'
            ];
        }

        $data = MontaIntegrations::listIntegrations();
        if ($data['status'] !== '200') {
            return [
                'status' => '401',
                'message' => 'Failed to get integrations',
                'error' => $data['error']
            ];
        }

        $integrations = $data['data'];


        $chargePointIntegrations = collect($integrations)
            ->filter(function ($brand) use ($chargePointBrand) {
                return $brand['slug'] === $chargePointBrand; // Filter by brand
            })
            ->flatMap(function ($brand) {
                return $brand['models']; // Extract models
            })
            ->filter(function ($model) {
                return $model['slug'] === $chargePointModelIdentifier; // Filter by model
            })
            ->flatMap(function ($model) {
                return $model['integrations']; // Extract integrations
            })
            ->filter(function ($integration) {
                return is_array($integration); // Ensure each integration is an array
            })
            ->pluck('type') // Extract only the 'type' field
            ->map(function ($type) {
                return strtolower($type); // Convert type to lowercase
            })
            ->all();


        return [
            'status' => '200',
            'message' => 'Successfully got integration data from URL',
            'data' => [
                'user_identifier' => $queryParams['user_identifier'] ?? null,
                'charge_point_identifier' => $queryParams['charge_point_identifier'] ?? null,
                'charge_point_model_identifier' => $chargePointModelIdentifier,
                'charge_point_brand' => $chargePointBrand,
                'integrations' => $chargePointIntegrations
            ]
        ];
    }

    protected static function generateUUID(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, // 4xxx (UUID version 4)
            mt_rand(0, 0x3fff) | 0x8000, // yxxx (UUID variant 1)
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function integrateChargePoint($serialNumber, $userIdentifier, $chargePointIdentifier, $chargePointModelIdentifier, $integrationType): array {
        $headers = [
            'Operator' => 'monta',
            'Accept' => 'application/json',
            'Timezone' => 'Europe/Copenhagen',
            'UUID' => self::generateUUID(),
            'Meta' => 'web;production;1.2.0;browser;chrome%20MacIntel',
            'Accept-Language' => 'da',
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'user_identifier' => $userIdentifier,
            'charge_point_identifier' => $chargePointIdentifier,
            'charge_point_model_identifier' => $chargePointModelIdentifier,
            'connector_id' => 1,
            'integration_type' => $integrationType
        ];

        $endpoint = 'https://integrations-api.monta.app/api/v1/charge-points/' . $serialNumber . '/integrations';

        $response = Http::withHeaders($headers)->post($endpoint, $payload);

        if (!$response->successful()) {
            return [
                'status' => 401,
                'message' => 'Failed to create integration',
                'error' => $response->json()
            ];
        }

        return [
            'status' => 200,
            'message' => 'Successfully created integration',
            'data' => $response->json()
        ];
    }

    public static function validateIntegration(string $serialNumber): array {
        $endpoint = 'https://integrations-api.monta.app/api/integrations/ZAPTEC_CLOUD/charge_point/' . $serialNumber . '/validate';

        $response = Http::get($endpoint);

        $data = $response->json();

        if (!$response->successful()) {
            return [
                'status' => 401,
                'message' => 'Failed to validate integration',
                'error' => $data['readable_message']
            ];
        }

        return [
            'status' => 200,
            'message' => 'Successfully validated integration',
            'data' => $data
        ];
    }

    public static function pairChargePoint(string $serialNumber, $auth, $brand, $model): array {
        $endpoint = 'https://integrations-api.monta.app/api/integrations/zaptec_cloud/charge_point?=' . $serialNumber;

        $headers = [
            'Authorization' => 'Basic ' . $auth,
            'Operator' => 'monta',
            'Accept' => 'application/json',
            'Timezone' => 'Europe/Copenhagen',
            'UUID' => self::generateUUID(),
            'Meta' => 'web;production;1.2.0;browser;chrome%20MacIntel',
            'Accept-Language' => 'da',
            'Content-Type' => 'application/json'
        ];

        $response = Http::withHeaders($headers)->get($endpoint);
        $data = $response->json();

        if (!$response->successful()) {
            return [
                'status' => 401,
                'message' => 'Failed to authorize charge point',
                'error' => $data
            ];
        }

        if (empty($data) || !isset($data[0]['id'])) {
            return [
                'status' => 401,
                'message' => 'Failed to find charge point',
                'error' => $data
            ];
        }

        $chargePointId = $data[0]['id'];

        $endpoint = 'https://integrations-api.monta.app/api/integrations/ZAPTEC_CLOUD/charge_point/' . $chargePointId . '/pair';
        $response = Http::withHeaders($headers)->post($endpoint, [
            'brand' => $brand,
            'model' => $model,
        ]);

        if (!$response->successful()) {
            return [
                'status' => 401,
                'message' => 'Failed to retrieve charge point',
                'error' => $response->json()
            ];
        }

        return [
            'status' => 200,
            'message' => 'Successfully paired charge point',
            'data' => $response->json()
        ];
    }
}
