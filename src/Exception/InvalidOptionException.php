<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Exception;

/** An option or its value is not known. */
final class InvalidOptionException extends \InvalidArgumentException implements QrCodeException {}
