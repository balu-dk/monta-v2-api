<?php

namespace App\Clients\Portal;

use GuzzleHttp\Cookie\SetCookie;
use \Illuminate\Support\Facades\Http;
use \GuzzleHttp\Cookie\CookieJar;
class MontaLocations
{
    public static function GetLocationID(string $id, string $search, array $cookies): string {
        $cookieJar = new CookieJar();

        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $response = Http::withOptions([
                'cookies' => $cookieJar
            ])->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Monta-Operator-Id' => $id
            ])->get('https://portal-v2.monta.app/api/v1/locations', [
                'search' => $search
            ]);

        $firstID = $response->json()['data'][0]['id'];
        if ($firstID) {
            return $firstID;
        }

        return '';
    }

    public static function listCountries(string $id, array $cookies): array {
        $cookieJar = new CookieJar();

        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Monta-Operator-Id' => $id
        ])->get('https://portal-v2.monta.app/api/v1/countries');

        if (!$response->successful()) {
            return [];
        }

        $formattedCountries = [];
        foreach ($response->json()['data'] as $country) {
            $formattedCountries[$country['name']] = $country['id'];
        }
        return $formattedCountries;
    }
}
