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
            'name' => 'required',
            'email' => 'required',
            'password' => 'required',
        ]);

        return response()->json(MontaClient::createOperator($request->id, $request->name, $request->email, $request->password));
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

        $plan = null;
        if (isset($request->plan)) {
            $plan = $request->plan;
        }

        \Illuminate\Support\Facades\Log::debug(
            'Creating customer with id: ' . $request->id . ' type: ' . $type . ' name: ' . $request->name . ' address: ' . $request->address . ' zipcode: ' . $request->zipcode . ' city: ' . $request->city . ' country: ' . $request->country . ' email: ' . $request->email . ' phone: ' . $request->phone . ' model: ' . $request->model . ' kw: ' . $request->kw . ' plan: ' . $plan
        );

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
                $plan
        ));
    });
});

Route::get('chargepoints/{chargepoint}', function (string $chargepoint, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::getChargepointBySerialNumber($request->id, $chargepoint));
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

Route::get('user', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);
    \Illuminate\Support\Facades\Log::debug('Retrieving user with id: ' . $request->id);
    return response()->json(MontaClient::getCurrentUser($request->id));
});

Route::post('ocpp/{serialNumber}', function (string $serialNumber, Request $request) {
    $request->validate([
        'id' => 'required',
        'url' => 'required',
    ]);

    return response()->json(MontaClient::setWebsocket($request->id, $serialNumber, $request->url));
});

Route::get('subscriptions', function (Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::listSubscriptions($request->id, isset($request->chargepoint) ? $request->chargepoint : false));
});

Route::get('subscriptions/{chargepointId}', function (string $chargepointId, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);
    return response()->json(MontaClient::listSubscriptionsByChargepoint($request->id, $chargepointId));
});

Route::get('integrations/{chargepointId}', function (string $chargepointID, Request $request) {
    $request->validate([
        'id' => 'required',
    ]);

    return response()->json(MontaClient::getIntegrationFromChargepointId($request->id, $chargepointID));
});

Route::get('operators', function () {
    return response()->json([
        'status' => '200',
        'message' => 'Successfully retrieved operators',
        'data' => \App\Models\Operator::all()->pluck('name', 'operator_id')->mapWithKeys(function ($item, $key) {
            return [
                'id' => $key,
                'name'=> $item];
        })
    ]);
});
