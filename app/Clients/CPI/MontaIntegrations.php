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
            \Illuminate\Support\Facades\Log::debug($integrationURL);
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

        return [
            'status' => '200',
            'message' => 'Successfully got integration data from URL',
            'data' => [
                'user_identifier' => $queryParams['user_identifier'] ?? null,
                'charge_point_identifier' => $queryParams['charge_point_identifier'] ?? null,
                'charge_point_model_identifier' => $chargePointModelIdentifier,
                'charge_point_brand' => $chargePointBrand,
                'integrations' => MontaIntegrations::listIntegrations()
            ]
        ];
    }
}
