<?php

namespace App\Clients\Portal;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

class MontaPlans
{
    public static function listPlans(string|int $id, array $cookies, bool $chargepointType = false, string $chargePointId = ''): array
    {
        $cookieJar = new CookieJar();
        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        $withoutChargepoint = [
            'page' => 1,
            'perPage' => 16,
            'include' => 'ACTIVE_SUBSCRIPTION_COUNT,PENDING_SUBSCRIPTION_COUNT,PARTNER_SUGGESTION,PROMOTION_CODE,COUNTRY',
        ];

        $withChargePoint = [
            'page' => 1,
            'search' => '',
            'perPage' => 16,
            'include' => 'PARENT',
            'teamId' => '',
            $chargePointId != '' ?? 'chargePointId' => $chargePointId,
            'sortBy' => 'name',
            'type' => 'chargePoints'
        ];
        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->withHeaders([
            'X-Monta-Operator-Id' => $id,
        ])->get("https://portal-v2.monta.app/api/v1/plans", $chargepointType ? $withChargePoint : $withoutChargepoint);

        return $response->json()['data'];
    }

    public static function addToPlan (string|int $id, array$cookies, string $plan, $chargePointId): array
    {
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
        ])->post("https://portal-v2.monta.app/api/v1/subscriptions", [
            'planId' => $plan,
            'chargePointId' => $chargePointId
        ]);

        return $response->json();
    }
}
