<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Exception;

/** The PNG renderer cannot draw the input, or the library cannot write a file. */
final class RenderException extends \RuntimeException implements QrCodeException {}
