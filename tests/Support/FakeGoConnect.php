<?php

namespace Tests\Support;

use App\Banking\GoConnect\GoConnectFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

/**
 * Podstawia bank atrapą HTTP, ale NIE omija biblioteki - komunikaty
 * przechodzą przez prawdziwe parsowanie camt i prawdziwą walidację
 * konfiguracji, łącznie z wymogiem istnienia certyfikatu na dysku.
 * Test, który omijałby te warstwy, nie mówiłby nic o integracji.
 */
final class FakeGoConnect
{
    private static ?string $certPath = null;
    private static ?string $keyPath = null;

    /**
     * @param  list<string>  $soapBodies  kolejne odpowiedzi banku (wnętrze soap:Body)
     */
    public static function bind(array $soapBodies): void
    {
        self::configure();

        $responses = array_map(
            static fn (string $body) => new Response(200, ['Content-Type' => 'text/xml'], self::envelope($body)),
            $soapBodies,
        );

        $stack = HandlerStack::create(new MockHandler($responses));

        app()->forgetInstance(\App\Banking\GoConnect\GoConnectGateway::class);
        app()->forgetInstance(GoConnectFactory::class);

        app()->singleton(GoConnectFactory::class, fn () => new GoConnectFactory(
            new NullLogger(),
            new Client(['handler' => $stack]),
        ));
    }

    public static function configure(): void
    {
        self::generateCertificate();

        config([
            'bnp.goconnect.client_id'                              => '12345678',
            'bnp.goconnect.initiating_party'                       => 'dpayrecontest',
            'bnp.goconnect.communication_certificate.cert_path'    => self::$certPath,
            'bnp.goconnect.communication_certificate.key_path'     => self::$keyPath,
            'bnp.goconnect.communication_certificate.passphrase'   => null,
            'bnp.goconnect.authorization_certificate.pkcs12_path'  => null,
        ]);
    }

    public static function envelope(string $bodyXml): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'.$bodyXml.'</soap:Body>'
            .'</soap:Envelope>';
    }

    /** Odpowiedź GetAccountReport (camt.052) z listą pozycji. */
    public static function accountReport(string $accountIban, array $entries): string
    {
        $ntry = '';
        foreach ($entries as $entry) {
            $ntry .= self::entry($entry);
        }

        return '<ns40:GetAccountReportResponse xmlns:ns40="urn:ca:std:cdc:tech:xsd:cdc.001.01">'
            .'<ns6:Document xmlns:ns6="urn:iso:std:iso:20022:tech:xsd:camt.052.001.02">'
            .'<ns6:BkToCstmrAcctRpt>'
            .'<ns6:GrpHdr><ns6:MsgId>TESTMSG</ns6:MsgId><ns6:CreDtTm>2026-08-30T10:00:00</ns6:CreDtTm></ns6:GrpHdr>'
            .'<ns6:Rpt><ns6:Id>TESTRPT</ns6:Id>'
            .'<ns6:Acct><ns6:Id><ns6:IBAN>'.$accountIban.'</ns6:IBAN></ns6:Id></ns6:Acct>'
            .$ntry
            .'</ns6:Rpt></ns6:BkToCstmrAcctRpt></ns6:Document></ns40:GetAccountReportResponse>';
    }

    /**
     * @param  array{amount: string, direction?: string, credited?: string|null,
     *               remittance?: string, number?: string, date?: string, debtor?: string}  $entry
     */
    private static function entry(array $entry): string
    {
        $date = $entry['date'] ?? '2026-08-30';
        $direction = $entry['direction'] ?? 'CRDT';

        $xml = '<ns6:Ntry><ns6:Amt Ccy="PLN">'.$entry['amount'].'</ns6:Amt>'
            .'<ns6:CdtDbtInd>'.$direction.'</ns6:CdtDbtInd><ns6:Sts>BOOK</ns6:Sts>'
            .'<ns6:BookgDt><ns6:Dt>'.$date.'</ns6:Dt></ns6:BookgDt>'
            .'<ns6:ValDt><ns6:Dt>'.$date.'</ns6:Dt></ns6:ValDt>'
            .'<ns6:NtryDtls><ns6:TxDtls>'
            .'<ns6:Refs><ns6:MsgId>'.($entry['number'] ?? '1').'</ns6:MsgId>'
            .'<ns6:TxId>'.($entry['tx_id'] ?? ('TX'.($entry['number'] ?? '1'))).'</ns6:TxId></ns6:Refs>'
            .'<ns6:RltdPties>'
            .'<ns6:Dbtr><ns6:Nm>'.($entry['debtor'] ?? 'Jan Kowalski').'</ns6:Nm></ns6:Dbtr>'
            .'<ns6:DbtrAcct><ns6:Id><ns6:Othr><ns6:Id>PL66160011270000000000000002</ns6:Id></ns6:Othr></ns6:Id></ns6:DbtrAcct>';

        if (! empty($entry['credited'])) {
            $xml .= '<ns6:CdtrAcct><ns6:Id><ns6:Othr><ns6:Id>'.$entry['credited'].'</ns6:Id></ns6:Othr></ns6:Id></ns6:CdtrAcct>';
        }

        $xml .= '</ns6:RltdPties>'
            .'<ns6:RmtInf><ns6:Ustrd>'.($entry['remittance'] ?? 'Wplata').'</ns6:Ustrd></ns6:RmtInf>'
            .'</ns6:TxDtls></ns6:NtryDtls></ns6:Ntry>';

        return $xml;
    }

    private static function generateCertificate(): void
    {
        if (self::$certPath !== null && is_file(self::$certPath)) {
            return;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['countryName' => 'PL', 'organizationName' => 'Dpay Test', 'commonName' => 'goconnect-test'], $key);
        $certificate = openssl_csr_sign($csr, null, $key, 365);

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        $dir = sys_get_temp_dir().'/dpay-recon-tests';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        self::$certPath = $dir.'/client.crt.pem';
        self::$keyPath = $dir.'/client.key.pem';

        file_put_contents(self::$certPath, $certificatePem);
        file_put_contents(self::$keyPath, $privateKeyPem);
    }
}
