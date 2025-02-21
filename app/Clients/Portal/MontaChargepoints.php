<?php

namespace App\Clients\Portal;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psy\Util\Json;

class MontaChargepoints {
    public static function listModels($cookies): array {
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
        ])->get('https://portal-v2.monta.app/api/v1/charge-points/brands');

        if ($response->status() !== 200) {
            return [];
        }

        return $response->json()['data'];
    }

    public static function create(
        string|int $id, string $name, string|int $team, string|int $model, string|int $kw, string $location, array $cookies
    ): array {
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
        ])->post("https://portal-v2.monta.app/api/v1/teams/{$team}/charge-points", [
            "name" => $name,
            "chargePointModelId" => $model,
            "maxKw" => $kw,
            "maxReservationMin" => null,
            "siteId" => null,
            "locationId" => $location,
            "note" => null,
            "operatorNote" => null,
            "scheduleId" => null,
            "priceGroupId" => null,
            "costPriceGroupId" => null,
            "visibility" => "private",
            "active" => true,
            "showOnMap" => null,
            "hasCable" => null,
            "chatActive" => null,
            "siteName" => null,
            "roaming" => null,
            "roamingPriceGroupId" => null,
            "resetCost" => false
            ]);

        return [
            'status' => $response->status(),
            'message' => $response->json()
        ];
    }

    /**
     * @throws ConnectionException
     */
    public static function get($id, $chargepoint, $cookies): array {
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
        ])->get("https://portal-v2.monta.app/api/v1/charge-points/{$chargepoint}", [
            'include' => 'publish_settings,subscription,team_subscription,active_subscription,team_active_subscription,team,operator,permission_map'
        ]);

        if ($response->status() !== 200) {
            return [
                'status' => $response->status(),
                'message' => $response->json()
            ];
        }

        return $response->json();
    }

    public static function listChargepoints(string|int $id, string $search, array $cookies, int $page = 16): array {
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
        ])->get("https://portal-v2.monta.app/api/v1/charge-points", [
            "page" => 1,
            "perPage" => $page,
            "search" => $search
        ]);

        if ($response->status() !== 200) {
            return [
                'status' => $response->status(),
                'message' => $response->json()
            ];
        }
        return $response->json()['data'];
    }
}
