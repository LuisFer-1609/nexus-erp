<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * El proveedor de IA no está disponible: sin llaves, llaves agotadas o error de Google.
 */
class AiUnavailableException extends RuntimeException {}
