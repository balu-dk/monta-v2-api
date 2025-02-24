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
        ])->get("https://app.monta.app/cpis/start/" . $chargepointID);

        if ($response->status() == 200) {
            if (isset($response->headers()['location'])) {
                return [
                    'status' => '200',
                    'message' => 'Successfully got integration link',
                    'response' => $response->headers()['location']
                ];
            }
        }

        return [
            'status' => '401',
            'message' => 'Failed to get integration link',
            'response' => $response->headers()
        ];
    }
}
