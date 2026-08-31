<?php

namespace App\Banking\Masscollect;

use RuntimeException;

/** Domena wyczerpała przydzieloną przestrzeń numerów. */
class SequenceExhaustedException extends RuntimeException
{
}
