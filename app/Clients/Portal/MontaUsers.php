<?php

namespace App\Clients\Portal;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

class MontaUsers {
    public static function listUsers(string|int $id, string $search, $cookies): array {
        $cookieJar = new CookieJar();
        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->withHeaders([
            'X-Monta-Operator-Id' => $id,
        ])->get('https://portal-v2.monta.app/api/v1/users/find-one', [
            'search' => $search
        ]);

        return $response->json();
    }
}
