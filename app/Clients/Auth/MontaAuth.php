<?php

namespace App\Clients\Auth;

use App\Clients\MontaClient;
use App\Models\Operator;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MontaAuth
{
    public static function getIdentity(): array {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get('https://app.monta.app/identity/auth/login');

            $jsonResponse = $response->json();
            $id = $jsonResponse['id'];
            $csrf_token = '';

            $nodes = json_encode($jsonResponse['ui']['nodes']);
            foreach ($jsonResponse['ui']['nodes'] as $node) {
                if ($node['attributes']['name'] == 'csrf_token') {
                    $csrf_token = $node['attributes']['value'];
                }
            }
            $cookies = self::parseCookies($response->headers()['set-cookie'][0]);
            return [
                'status' => '200',
                'id' => $id,
                'csrf_token' => $csrf_token,
                'cookie' => [
                    key($cookies) => reset($cookies)
                ],
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Log::error($e->getMessage());
            return [
                "status" => 500,
                "body" => $e->getMessage()
            ];
        } catch (ConnectionException $e) {
            Log::error($e->getMessage());
            return [
                "status" => 500,
                "body" => $e->getMessage()
            ];
        }
    }

    public static function getAuthenticationCookies($id, $csrf_token, $email, $password, $cookie) {
        try {
            $response = Http::withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $csrf_token,
                ])
                ->withBody(json_encode([
                    "csrf_token" => $csrf_token,
                    "transient_payload" => "{\"language\":\"en\"}",
                    "identifier" => $email,
                    "password" => $password,
                    "method" => "password"
                ]))
                ->withCookies($cookie, 'monta.app')
                ->post('https://app.monta.app/identity/kratos/self-service/login?flow=' . $id);

            $oxy_kratos_session = '';
            foreach ($response->headers()['set-cookie'] as $cookie) {
                if (strpos($cookie, 'ory_kratos_session') !== false) {
                    $oxy_kratos_session = self::parseCookies($cookie)['ory_kratos_session'];
                }
            }

            Log::debug('Oxy kratos session: ' . $oxy_kratos_session);

            if ($response->status() == 200) {
                return [
                    "status" => $response->status(),
                    "cookie" => [
                        'ory_kratos_session' => $oxy_kratos_session
                    ],
                    "body" => $response->json(),
                ];
            }
            return [
                "status" => $response->status(),
                "body" => $response->json(),
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return [
                "status" => 500,
                "body" => $e->getMessage()
            ];
        } catch (ConnectionException $e) {
            return [
                "status" => 500,
                "body" => $e->getMessage()
            ];
        }
    }

    public static function getUser(string $csrf_token_key, string $csrf_token_value, string $oxy_kratos_session, string $xsrf_token, string $monta_session) {
        try {
            $cookieJar = new CookieJar();
            // Add cookies for monta.app
            $cookieJar->setCookie(new SetCookie([
                'Name' => $csrf_token_key,
                'Value' => $csrf_token_value,
                'Domain' => 'monta.app'
            ]));
            $cookieJar->setCookie(new SetCookie([
                'Name' => 'ory_kratos_session',
                'Value' => $oxy_kratos_session,
                'Domain' => 'monta.app'
            ]));

            // Add cookies for app.monta.app
            $cookieJar->setCookie(new SetCookie([
                'Name' => 'XSRF-TOKEN',
                'Value' => $xsrf_token,
                'Domain' => 'app.monta.app'
            ]));
            $cookieJar->setCookie(new SetCookie([
                'Name' => 'monta_session',
                'Value' => $monta_session,
                'Domain' => 'app.monta.app'
            ]));

            $response = Http::withHeaders([
                'Accept' => 'application/json',])
                ->withOptions([
                    'cookies' => $cookieJar
                ])
                ->get('https://portal-v2.monta.app/api/v1/users/me')
                ->json();
            return $response;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return $e->getMessage();
        } catch (ConnectionException $e) {
            return $e->getMessage();
        }
    }

    public static function isLoggedIn($cookies) {
        try {
            if (!isset($cookies['csrfTokenKey']) || !isset($cookies['csrfTokenValue']) || !isset($cookies['oxyKratosSession']) || !isset($cookies['xsrfToken']) || !isset($cookies['montaSession'])) {
                return false;
            }
            $csrf_token_key = $cookies['csrfTokenKey'];
            $csrf_token_value = $cookies['csrfTokenValue'];
            $oxy_kratos_session = $cookies['oxyKratosSession'];
            $xsrf_token = $cookies['xsrfToken'];
            $monta_session = $cookies['montaSession'];

            $response = self::GetUser($csrf_token_key, $csrf_token_value, $oxy_kratos_session, $xsrf_token, $monta_session);
            return isset($response['id']);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return false;
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public static function getSession(string $csrfKey, string $csrfValue, string $oxyKratosSession) {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Prioriy' => 'u=0, i'
            ])
                ->withCookies([
                    $csrfKey => $csrfValue,
                    'ory_kratos_session' => $oxyKratosSession,
                ], 'monta.app')
                ->get('https://app.monta.app/portal/redirect-to-portal');
            if (!$response->headers()['set-cookie'] && $response->json()['status']) {
                return ['status' => 401];
            }

            return [
                'status' => 200,
                'XSRF-Token' => self::parseCookies($response->headers()['set-cookie'][0])['XSRF-TOKEN'],
                'monta_session' => self::parseCookies($response->headers()['set-cookie'][1])['monta_session'],
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage()
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    private static function parseCookies($setCookieHeader) {
        $cookies = [];
        $parts = explode('; ', $setCookieHeader);
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $cookies[trim($key)] = trim($value);
            } else {
                $cookies[trim($part)] = true;
            }
        }
        return $cookies;
    }

    public static function getDatabaseCookies(Operator $operator): array {
        $csrfTokenKey = $operator->csrf_token_key ? Crypt::decrypt($operator->csrf_token_key) : '';
        $csrfTokenValue = $operator->csrf_token_value ? Crypt::decrypt($operator->csrf_token_value) : '';
        $oxyKratosSession = $operator->oxy_kratos_session ? Crypt::decrypt($operator->oxy_kratos_session) : '';
        $xsrfToken = $operator->xsrf_token ? Crypt::decrypt($operator->xsrf_token) : '';
        $montaSession = $operator->monta_session ? Crypt::decrypt($operator->monta_session) : '';
        return [
            'csrfTokenKey' => $csrfTokenKey,
            'csrfTokenValue' => $csrfTokenValue,
            'oxyKratosSession' => $oxyKratosSession,
            'xsrfToken' => $xsrfToken,
            'montaSession' => $montaSession,
        ];
    }

    private static function getDatabaseLogin(Operator $operator): array {
        return [
            'email' => Crypt::decrypt($operator->email),
            'password' => Crypt::decrypt($operator->password),
        ];
    }
    public static function authenticate(Operator | null $operator, $cookies): array {

        try {
            if (!$operator->exists || !$operator) {
                return [400, false];
            }
        } catch (\Exception $e) {
            return [500, false];
        }

        try {
            if (!self::isLoggedIn($cookies)) {
                Log::debug('Operator is not logged in');
                $identity = self::getIdentity();
                if ($identity['status'] != 200) {
                    return [$identity['status'], false];
                }
                Log::debug('Identity was successfully retrieved');

                $id = $identity['id'];
                $csrf_token = $identity['csrf_token'];
                $cookieKey = key($identity['cookie']);
                $cookieValue = reset($identity['cookie']);
                $email = self::getDatabaseLogin($operator)['email'];
                $password = self::getDatabaseLogin($operator)['password'];

                $response = self::getAuthenticationCookies($id, $csrf_token, $email, $password, [$cookieKey => $cookieValue]);
                if ($response['status'] == 200) {
                    Log::debug('Authentication cookies was successfully retrieved');
                    $oxy_kratos_session = reset($response['cookie']);
                    $session = self::getSession($cookieKey, $cookieValue, $oxy_kratos_session);
                    Log::debug('Monta Session cookie: ' . $session['monta_session']);
                    if ($session['status'] == 200) {
                        Log::debug('Session cookies was successfully retrieved');
                        $xsrf_token = $session['XSRF-Token'];
                        $monta_session = $session['monta_session'];

                        $operator->csrf_token_key = Crypt::encrypt($cookieKey);
                        $operator->csrf_token_value = Crypt::encrypt($cookieValue);
                        $operator->oxy_kratos_session = Crypt::encrypt($oxy_kratos_session);
                        $operator->xsrf_token = Crypt::encrypt($xsrf_token);
                        $operator->monta_session = Crypt::encrypt($monta_session);
                        $operator->save();

                        return [200, true];
                    }
                }

                return [401, false];
            }
        } catch (\Exception $e) {
            return [500, false];
        }

        Log::debug('Operator is logged in');

        return [200, true];
    }
}
