<?php

namespace App\Banking\GoConnect;

use RuntimeException;

/**
 * Brak certyfikatów albo identyfikatora klienta. Osobny wyjątek, bo to nie
 * jest awaria banku tylko brak konfiguracji - aplikacja ma wstać i działać
 * (panel, alokacje z puli), a dopiero wywołanie kanału ma się wyłożyć
 * czytelnym komunikatem.
 */
class GoConnectNotConfiguredException extends RuntimeException
{
}
