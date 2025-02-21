<?php

use App\Clients\Portal\MontaChargepoints;
use App\Clients\Portal\MontaLocations;
use App\Clients\Portal\MontaTeams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Clients\MontaClient;

// Only Partner Portal (MISSING MIDDLEWARE)
Route::prefix('portal')->group(function () {

    // Create new operator in database
    Route::post('operator', function (Request $request) {
        $request->validate([
            'id' => 'required',
            'email' => 'required',
            'password' => 'required',
        ]);

        return response()->json(MontaClient::createOperator($request->id, $request->email, $request->password));
    });

    Route::post('customer/{type}', function (string $type, Request $request) {
        $validated = $request->validate([
            'id' => 'required',
            'name' => 'required',
            'address' => 'required',
            'zipcode' => 'required',
            'city' => 'required',
            'country' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'model' => 'required',
            'kw' => 'required',
        ]);

        return response()->json(
            MontaClient::createCustomer(
                $request->id,
                $type,
                $request->name,
                $request->address,
                $request->zipcode,
                $request->city,
                MontaClient::countries($request->id)[$request->country],
                $request->email,
                $request->phone,
                $request->model,
                $request->kw,
                MontaClient::cookies($request->id),
                $request->plan ?? null
        ));
    });
});

Route::get('teams/{team}/pricegroups', function (string $team, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(\App\Clients\Portal\MontaTeams::listPriceGroupsForTeam($request->id, $team, MontaClient::cookies($request->id))[0]['id']);
});

Route::get('users', function (Request $request) {
    $request->validate([
        'id' => 'required',
        'search' => 'required',
    ]);

    return response()->json(\App\Clients\Portal\MontaUsers::listUsers($request->id, $request->search, MontaClient::cookies($request->id)));
});

Route::post('teams/{team}/groups/', function (string $team, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaTeams::createMemberGroup($request->id, $team, 'Owner', true, true, false, false, true, $cookies));
});

Route::post('teams/{team}/members/', function (string $team, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaTeams::inviteMemberToTeam($request->id, $team, 'admin', '+4583729105', '4934813', '4530', $cookies));
});

Route::post('teams/{team}/members/group', function (string $team, Request $request) {
    $request->validate([
        'id' => 'required',
        'member' => 'required',
        'group' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaTeams::addTeamMemberToGroup($request->id, $request->member, $request->group, $cookies));
});

Route::get('chargepoints/{chargepoint}', function (string $chargepoint, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaClient::getChargepointBySerialNumber($request->id, $chargepoint, $cookies));
});

Route::get('countries', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::countries($request->id));
});

Route::get('models', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::models($request->id));
});

Route::get('auth', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::cookies($request->id));
});

Route::get('user', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);
    return response()->json(MontaClient::getCurrentUser($request->id, MontaClient::cookies($request->id)));
});

Route::post('ocpp/{serialNumber}', function (string $serialNumber, Request $request) {
    $request->validate([
        'id' => 'required',
        'url' => 'required',
    ]);

    return response()->json(MontaClient::setWebsocket($request->id, $serialNumber, $request->url, MontaClient::cookies($request->id)));
});

Route::get('subscriptions', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaClient::listSubscriptions($request->id, $cookies, isset($request->chargepoint) ? $request->chargepoint : false));
});

Route::get('subscriptions/{chargepointId}', function (string $chargepointId, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaClient::listSubscriptionsByChargepoint($request->id, $chargepointId, $cookies));
});

Route::post('chargepoint', function (Request $request) {
    $request->validate([
        'id' => 'required',
        'name' => 'required',
        'team' => 'required',
        'model' => 'required',
        'kw' => 'required',
        'address' => 'required',
        'zipcode' => 'required',
        'city' => 'required',
        'country' => 'required',
    ]);

    $cookies = MontaClient::cookies($request->id);
    return response()->json(MontaChargepoints::create(
        $request->id,
        $request->name,
        $request->team,
        $request->model,
        $request->kw,
        MontaLocations::GetLocationID($request->id, "{$request->address}, {$request->zipcode} {$request->city}", $cookies),
        $cookies
    ));
});
