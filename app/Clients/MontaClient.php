<?php

namespace App\Clients;
use App\Clients\Auth\MontaAuth;
use App\Clients\CPI\MontaIntegrations;
use App\Clients\Portal\MontaChargepoints;
use App\Clients\Portal\MontaLocations;
use App\Clients\Portal\MontaPlans;
use App\Clients\Portal\MontaTeams;
use App\Clients\Portal\MontaUsers;
use App\Models\Operator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class MontaClient
{

    public static function createOperator($id, $name, $email, $password)
    {
        if (Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 400,
                'message' => 'Operator already exists',
            ];
        }

        $operator = Operator::create([
            'operator_id' => (int)$id,
            'name' => $name,
            'email' => Crypt::encrypt($email),
            'password' => Crypt::encrypt($password),
        ]);
        if ($operator) {
            return [
                'status' => 200,
                'message' => 'Operator created successfully',
            ];
        }
        return [
            'status' => 500,
            'message' => 'Internal Server Error',
        ];
    }

    public static function authenticate($id): bool
    {
        $operator = Operator::where('operator_id', (int)$id)->first();

        $cookies = self::cookies($id);
        [$status, $authentication] = MontaAuth::authenticate($operator, $cookies);
        Log::debug('Authentication: ' . $status);

        return $authentication;
    }

    public static function cookies($id): array {
        $operator = Operator::where('operator_id', $id)->first();
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        return MontaAuth::getDatabaseCookies($operator);
    }

    public static function models($id): array {
        $operator = Operator::where('operator_id', $id)->first();
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        return MontaChargepoints::listModels(MontaAuth::getDatabaseCookies($operator));
    }

    public static function createTeam($id): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        return [];
    }

    public static function countries($id): array {
        $operator = Operator::where('operator_id', $id)->first();
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        return MontaLocations::listCountries($id, MontaAuth::getDatabaseCookies($operator));
    }

    public static function getChargepointBySerialNumber($id, $serialNumber): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $chargers = MontaChargepoints::listChargepoints($id, $serialNumber, $cookies, 1);

        if (!$chargers) {
            return [
                'status' => 404,
                'message' => 'Chargepoint not found',
            ];
        }

        return MontaChargepoints::get($id, $chargers[0]['id'], $cookies);
    }

    public static function createCustomer(
        string|int $id,
        string $type,
        string $name,
        string $address,
        string|int $zipcode,
        string $city,
        string $country,
        string $email,
        string $phone,
        string|int $model,
        string|int $kw,
        string|null $plan = null
    ): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 400,
                'message' => 'Operator not found',
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $countries = MontaClient::countries($id);
        if (!in_array($country, $countries)) {
            return [
                'status' => 400,
                'message' => 'Country not found',
            ];
        }

        // Create Team
        $teamName = "{$name} – $address";
        $team = MontaTeams::create($id, $type, $teamName, $address, $zipcode, $city, $country, $email, $cookies);
        if (!isset($team['id']) || !$team['id']) {
            return [
                'status' => $team['status'],
                'message' => $team['message'],
                'failed_object' => 'team',
            ];
        }

        $location = "{$address}, {$zipcode} {$city}, {$country}";
        $chargepoint = MontaChargepoints::create(
            $id,
            $teamName,
            $team['id'],
            $model,
            $kw,
            MontaLocations::GetLocationID($id, $location, $cookies),
            $cookies
        );

        if (!isset($chargepoint['status']) || !$chargepoint['status'] == 201) {
            return [
                'status' => $chargepoint['status'],
                'message' => $chargepoint['message'],
                'failed_object' => 'chargepoint',
                'data' => [
                    'team' => $team,
                ]
            ];
        }

        $subscription = [];
        // Add chargepoint to subscription plan if needed
        if ($plan != null) {
            $subscription = self::addChargepointToSubscription($id, $plan, $chargepoint['message']['id']);

            if (!isset($subscription['id']) || !$subscription['id']) {
                return [
                    'status' => $subscription['status'],
                    'message' => $subscription['message'],
                    'failed_object' => 'subscription',
                    'data' => [
                        'team' => $team,
                        'chargepoint' => $chargepoint,
                    ]
                ];
            }
        }

        $priceGroups = MontaTeams::listPriceGroupsForTeam($id, $team['id'], $cookies);
        $priceGroupId = '';
        foreach ($priceGroups as $priceGroup) {
            if ($priceGroup['name'] == 'Default') {
                $priceGroupId = $priceGroup['id'];
                break;
            }
        }

        if (!$priceGroupId) {
            return [
                'status' => 400,
                'message' => 'Failed to find price group',
                'failed_object' => 'priceGroup',
                'data' => [
                    'team' => $team,
                    'chargepoint' => $chargepoint,
                    'subscription' => $subscription,
                ]
            ];
        }

        // Create Member Group Owner
        $memberGroup = MontaTeams::createMemberGroup($id, $team['id'], 'Owner', true, true, false, false, true, $cookies);

        if (!isset($memberGroup['id']) || !$memberGroup['id']) {
            return [
                'status' => $chargepoint['status'],
                'message' => $chargepoint['message'],
                'failed_object' => 'memberGroup',
                'data' => [
                    'team' => $team,
                    'chargepoint' => $chargepoint,
                    'subscription' => $subscription,
                    'priceGroupId' => $priceGroupId,
                ]
            ];
        }


        $user = $phone;

        $users = MontaUsers::listUsers($id, $phone, $cookies);
        $userId = null;
        if (isset($users['id']) && $users['id']) {
            $userType = false;
            $userId = $users['id'];
        } else {
            $users = MontaUsers::listUsers($id, $email, $cookies);
            if (isset($users['id']) && $users['id']) {
                $userType = true;
                $userId = $users['id'];
            }
        }
        $invite = MontaTeams::inviteMemberToTeam($id, $team['id'], 'admin', $phone, $userId, $priceGroupId, $memberGroup['id'], $cookies);

        Log::debug($invite);

        if (!isset($invite['id']) || !$invite['id']) {
            return [
                'status' => $invite['status'],
                'message' => $invite['message'],
                'failed_object' => 'invite',
                'data' => [
                    'team' => $team,
                    'chargepoint' => $chargepoint,
                    'subscription' => $subscription,
                    'priceGroupId' => $priceGroupId,
                    'memberGroup' => $memberGroup,
                ]
            ];
        }

        return [
            'status' => 201,
            'message' => 'Customer created successfully',
            'data' => [
                'team' => $team,
                'chargepoint' => $chargepoint,
                'subscription' => $subscription,
                'priceGroupId' => $priceGroupId,
                'memberGroup' => $memberGroup,
                'invite' => $invite,
            ],
        ];

    }

    public static function setWebsocket($id, $serialNumber, $websocket): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $charger = self::getChargepointBySerialNumber($id, $serialNumber, $cookies);

        if(!isset($charger['id']) || !$charger['id']) {
            return [
                'status' => $charger['status'],
                'message' => $charger['message'],
            ];
        }

        $configuration = [
            'key' => 'Cst_BackendUrl',
            'value' => $websocket,
            'store' => false
        ];

        return MontaClient::setWebsocket($id, $serialNumber, $configuration, $cookies);
    }

    public static function listSubscriptions(string|int $id, bool $chargepointType = false): array {
        if (!$id || !Operator::where('operator_id', (int)$id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $plans = MontaPlans::listPlans($id, $cookies, $chargepointType);

        if (!$plans) {
            return [
                'status' => 404,
                'message' => 'Plans not found',
            ];
        }

        return $plans;
    }

    public static function listSubscriptionsByChargepoint(string|int $id, string $chargepointId): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $plans = MontaPlans::listPlans($id, $cookies, true, $chargepointId);

        if (!$plans) {
            return [
                'status' => 404,
                'message' => 'Plans not found',
            ];
        }

        return $plans;
    }

    public static function addChargepointToSubscription($id, $plan, $chargePointId): array
    {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $response = MontaPlans::addToPlan($id, $cookies, $plan, $chargePointId);

        if (!isset($response['id']) || !$response['id']) {
            return [
                'status' => $response['status'],
                'message' => $response['message'],
            ];
        }

        return $response;
    }

    public static function getCurrentUser($id): array {
        Log::debug('Getting current user with ID ' . $id . '...');
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }
        Log::debug('Operator exists on ID ' . $id . '!');
        $authenticated = self::authenticate($id);
        Log::debug('Authenticated: ' . $authenticated);
        if(!$authenticated) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        return MontaAuth::getUser($cookies);
    }

    public static function getIntegrationFromChargepointId($id, $chargepointID): array {
        if (!$id || !Operator::where('operator_id', $id)->exists()) {
            return [
                'status' => 404,
                'message' => 'Operator not found'
            ];
        }

        if(!self::authenticate($id)) {
            return [
                'status' => 401,
                'message' => 'Unauthorized: User not authenticated',
            ];
        }

        $cookies = self::cookies($id);

        $integration = MontaIntegrations::getIntegrationLink($cookies, $chargepointID);

        return $integration;
    }

    public static function getIntegrationDataFromURL($integrationURL): array {
        return MontaIntegrations::getIntegrationFromURL($integrationURL);
    }

    public static function integrateChargePoint(
        $serialNumber,
        $userIdentifier,
        $chargePointIdentifier,
        $chargePointModelIdentifier,
        $integrationType,
        $chargePointBrand = null): array {
        $integration = MontaIntegrations::integrateChargePoint($serialNumber, $userIdentifier, $chargePointIdentifier, $chargePointModelIdentifier, $integrationType);

        if ($integration['status'] !== 200) {
            Log::error('Failed to integrate charge point: ' . $integration['error']);
            return [
                'status' => $integration['status'],
                'message' => $integration['message'],
                'error' => $integration['error']
            ];
        }

        // Check if pairing is needed
        Log::debug('Checking if pairing is needed for integration type: ' . $integrationType);
        if ($integrationType == 'zaptec_cloud' && $chargePointBrand == 'zaptec') {
            Log::debug('Pairing is needed for Zaptec Cloud integration');
            $basicAuth = env('ZAPTEC');
            $response = MontaIntegrations::pairChargePoint($serialNumber, $basicAuth, $chargePointBrand, $chargePointModelIdentifier);
            if ($response['status'] !== 200) {
                Log::debug('Response data: ' . json_encode($response));
                Log::error('Failed to pair charge point: ' . $response['error']);
                return [
                    'status' => $response['status'],
                    'message' => $response['message'],
                    'error' => $response['error']
                ];
            }

            Log::debug('Successfully paired charge point');
        }

        Log::debug('Validating integration for serial number: ' . $serialNumber);
        for ($iterations = 0; $iterations < 3; $iterations++) {
            Log::debug('Iteration ' . ($iterations + 1) . ' of 3 for validating integration');
            $validation = MontaIntegrations::validateIntegration($serialNumber);
            if ($validation['status'] === 200) {
                Log::debug('Integration validated successfully');
                return [
                    'status' => 200,
                    'message' => 'Successfully integrated & validated integration',
                    'data' => [
                        'integration' => $integration['data'],
                        'validation' => $validation['data']
                    ]
                ];
            }
            Log::debug('Failed to validate integration: ' . $validation['message']);
            sleep(5);
        }

        Log::error('Failed to validate integration after 3 attempts');

        return [
            'status' => 401,
            'message' => 'Failed to validate integration',
            'error' => $validation['error']
        ];
    }
}
