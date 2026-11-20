<?php

/*
 * Example 5: content types with one Generator design, saved as WebP.
 *
 *     php docs/example5-content-types.php
 *
 * The script writes docs/example5-content-types.webp. From left to right:
 * a Wi-Fi network, a contact card, a location and a Bitcoin payment.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\Generator;
use Wnikk\QrCodeCute\Payload;

// One design for all codes. Each setter gives a new generator.
$generator = (new Generator)
    ->shape('hexagon')
    ->frame('thin')
    ->pattern('rounded')
    ->color('#0f172a')
    ->frameColor('#0ea5e9');

saveScene('example5-content-types.webp', [
    $generator->make(Payload::wifi('Office', 'secret')),
    $generator->make(Payload::vcard(firstName: 'Ivan', lastName: 'Petrov', phone: '+15551234567', email: 'ivan@example.com')),
    $generator->make(Payload::geo(55.751244, 37.618423, 'Red Square')),
    $generator->make(Payload::bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.0012', 'Shop')),
], '#bae6fd');
