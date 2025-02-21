<?php

namespace App\Clients\CPI;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

class MontaConfiguration {
    public static function changeConfiguration($id, $serialNumber, $configuration, $cookies): array {
        $cookieJar = new CookieJar();

        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->post("https://cpi-api.monta.app/api/v1/cpi-tool/charge-points/{$serialNumber}/set-configuration", $configuration);

        return $response->json();
    }
}
