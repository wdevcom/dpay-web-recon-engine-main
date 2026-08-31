<?php

namespace App\Banking\GoConnect;

use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\AdapterConfiguration;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\AuthorizationCertificate;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\CommunicationCertificate;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\GoConnectClient;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Http\ServiceEndpoint;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Buduje klienta GOconnect z konfiguracji Laravela.
 *
 * Klient powstaje leniwie - bez certyfikatów aplikacja ma się uruchomić
 * i obsłużyć wszystko, co nie wymaga banku.
 */
class GoConnectFactory
{
    private ?GoConnectClient $client = null;

    /**
     * `$httpClient` podstawia transport HTTP. W produkcji zostaje `null`
     * (biblioteka buduje własnego Guzzle z certyfikatem i przypiętym TLS);
     * seam istnieje dla testów i dla proxy korporacyjnego, gdzie trzeba
     * wpiąć własnego klienta.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?ClientInterface $httpClient = null,
    ) {
    }

    public function client(): GoConnectClient
    {
        return $this->client ??= new GoConnectClient($this->configuration(), $this->logger, $this->httpClient);
    }

    public function isConfigured(): bool
    {
        $config = config('bnp.goconnect');

        return is_string($config['client_id'] ?? null) && $config['client_id'] !== ''
            && $this->isReadableFile($config['communication_certificate']['cert_path'] ?? null)
            && $this->isReadableFile($config['communication_certificate']['key_path'] ?? null);
    }

    public function configuration(): AdapterConfiguration
    {
        $config = config('bnp.goconnect');

        $certPath = $config['communication_certificate']['cert_path'] ?? null;
        $keyPath  = $config['communication_certificate']['key_path'] ?? null;

        if (! $this->isReadableFile($certPath) || ! $this->isReadableFile($keyPath)) {
            throw new GoConnectNotConfiguredException(
                'Brak certyfikatu komunikacyjnego GOconnect - ustaw BNP_CERT_PATH i BNP_KEY_PATH '
                .'na czytelne pliki PEM. Kanał nie istnieje bez dwustronnego SSL.'
            );
        }

        if (empty($config['client_id'])) {
            throw new GoConnectNotConfiguredException('Brak BNP_CLIENT_ID (identyfikator Klienta GOconnect Biznes).');
        }

        $passphrase = $config['communication_certificate']['passphrase'] ?? null;

        return new AdapterConfiguration(
            endpoint: $this->endpoint(),
            communicationCertificate: CommunicationCertificate::fromPemFiles(
                (string) $certPath,
                (string) $keyPath,
                $passphrase !== null && $passphrase !== '' ? (string) $passphrase : null,
            ),
            clientId: (string) $config['client_id'],
            initiatingPartyName: (string) ($config['initiating_party'] ?? 'dpayrecon'),
            authorizationCertificate: $this->authorizationCertificate(),
            caBundlePath: $config['ca_bundle_path'] ?: null,
            timeoutSeconds: (int) ($config['timeout_seconds'] ?? 120),
            messageIdPrefix: (string) ($config['message_id_prefix'] ?? 'DPAY'),
        );
    }

    private function endpoint(): ServiceEndpoint
    {
        $endpoint = config('bnp.goconnect.endpoint');

        return $endpoint ? ServiceEndpoint::custom((string) $endpoint) : ServiceEndpoint::production();
    }

    private function authorizationCertificate(): ?AuthorizationCertificate
    {
        $path = config('bnp.goconnect.authorization_certificate.pkcs12_path');

        if (! $this->isReadableFile($path)) {
            return null;
        }

        return AuthorizationCertificate::fromPkcs12File(
            (string) $path,
            (string) config('bnp.goconnect.authorization_certificate.password', ''),
        );
    }

    private function isReadableFile(mixed $path): bool
    {
        return is_string($path) && $path !== '' && is_file($path) && is_readable($path);
    }
}
