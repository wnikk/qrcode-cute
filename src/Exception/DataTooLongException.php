<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Exception;

/** The payload is too long for a version 40 code at the selected error correction level. */
final class DataTooLongException extends \LengthException implements QrCodeException {}
