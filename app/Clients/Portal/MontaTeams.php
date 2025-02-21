<?php

namespace App\Clients\Portal;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MontaTeams
{
    public static function create($id, $type, $name, $address, $zipcode, $city, $country, $email, $cookies): array
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
        ])->post('https://portal-v2.monta.app/api/v1/teams', [
            'type' => $type,
            "modules" => [
                'legacy'
            ],
            'name' => $name,
            'email' => $email,
            "financeEmail" => null,
            "tagId" => null,
            "companyName" => null,
            "salesTax" => null,
            "vatNumber" => null,
            'address' => [
                'address1' => $address,
                'address2' => null,
                'address3' => null,
                'zip' => $zipcode,
                'city' => $city,
                "countryId" => $country,
                "countryName" => null,
                "areaId" => 2,
                "locationId" => null
            ],
            "operatorId" => null
        ]);

        return $response->json();
    }

    public static function listPriceGroupsForTeam($id, $team, $cookies): array
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
        ])->get("https://portal-v2.monta.app/api/v1/teams/{$team}/price-groups");

        return $response->json()['data'];
    }

    public static function createMemberGroup($id, $team, $name, $canUseForPayments, $canUseForWallet, $canRequestSponsoring, $canConfigureChargepoints, $canManageMembers, $cookies): array {
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
        ])->post("https://portal-v2.monta.app/api/v1/member-groups", [
            "name" => $name,
            "teamId" => $team,
            "description" => null,
            "settings" => [
                "role" => "admin"
            ],
            "permissions" => [
                "canUseForPaymentsCharges" => "all",
                "canUseForPaymentsChargesCountryIds" => [],
                "canUseForPayments" => $canUseForPayments,
                "canUseForManageWallet" => $canUseForWallet,
                "canRequestSponsoring" => $canRequestSponsoring,
                "canConfigureChargePoints" => $canConfigureChargepoints,
                "canManageMembers" => $canManageMembers
            ]
        ]);

        return $response->json();
    }

    public static function inviteMemberToTeam(string|int $id, string $team, string $role, string $phone, ?string $userId, string|int $priceGroup, ?string $memberGroup, array $cookies) {
        $cookieJar = new CookieJar();
        $cookieJar->setCookie(new SetCookie([
            'Name' => 'ory_kratos_session',
            'Value' => $cookies['oxyKratosSession'],
            'Domain' => 'monta.app'
        ]));

        Log::debug(implode([
            "role" => $role,
            "userInfo" => $phone,
            "userId" => $userId ?? null,
            "memberGroupId" => $memberGroup ?? null,
            "priceGroupId" => $priceGroup,
            "canRequestSponsoring" => false
        ]));

        $response = Http::withOptions([
            'cookies' => $cookieJar
        ])->withHeaders([
            'X-Monta-Operator-Id' => $id,
        ])->post("https://portal-v2.monta.app/api/v1/teams/{$team}/members", [
            "role" => $role,
            "userInfo" => $phone,
            "userId" => $userId ?? null,
            "memberGroupId" => $memberGroup ?? null,
            "priceGroupId" => $priceGroup,
            "canRequestSponsoring" => false
        ]);

        return $response->json();
    }

    public static function addTeamMemberToGroup(string|int $id, string|int $teamMember, string|int $memberGroup, array $cookies): array
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
        ])->post("https://portal-v2.monta.app/api/v1/member-groups/{$memberGroup}/members", [
            "action" => "add",
            "teamMemberIds" => [
                $teamMember
            ]
        ]);
    }
}
