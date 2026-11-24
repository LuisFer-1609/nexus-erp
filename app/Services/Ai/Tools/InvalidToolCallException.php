<?php

namespace App\Services\Ai\Tools;

use InvalidArgumentException;

/**
 * La llamada que armó el modelo no es válida. El mensaje se le devuelve al
 * modelo (en español) para que corrija su llamada.
 */
class InvalidToolCallException extends InvalidArgumentException {}
