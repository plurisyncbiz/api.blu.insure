<?php

namespace App\Security;

use Cake\Chronos\Chronos;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtAuth
{
    private array $settings;
    private string $algorithm;
    private int $expire;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->algorithm = $this->settings['algorithm'] ?? 'RS256';
        $this->expire = $this->settings['expire'] ?? 3600;
    }

    public function encode(array $data): string
    {
        $now = Chronos::now();
        $payload = [
            'iss' => $this->settings['issuer_claim'],
            'aud' => $this->settings['audience_claim'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->addSeconds(1)->getTimestamp(),
            'exp' => $now->addSeconds($this->expire)->getTimestamp(),
            'data' => $data,
        ];
        return JWT::encode($payload, $this->settings['private_key'], $this->algorithm);
    }

    public function encodeBearer(array $data): array
    {
// Transform the result into a OAuh 2.0 Access Token Response
// https://www.oauth.com/oauth2-servers/access-tokens/access-token-response/
        return [
            'access_token' => $this->encode($data),
            'token_type' => 'Bearer',
            'expires_in' => $this->expire,
        ];
    }

    public function decode(string $jwt): object
    {
        return JWT::decode($jwt, new Key($this->settings['public_key'], $this->algorithm));
    }
}
