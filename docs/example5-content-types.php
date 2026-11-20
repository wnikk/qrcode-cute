<?php

/*
 * Example 5: content types with one design, saved as WebP.
 *
 *     php docs/example5-content-types.php
 *
 * The script writes docs/example5-content-types.webp. From left to right:
 * a Wi-Fi network, a contact card, a location and a Bitcoin payment.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

// One design for all codes. Each setter and each content method gives a new object.
$design = (new QrCode)
    ->shape('hexagon')
    ->frame('thin')
    ->pattern('rounded')
    ->color('#0f172a')
    ->frameColor('#0ea5e9');

saveScene('example5-content-types.webp', [
    $design->wifi('Office', 'secret'),
    $design->vcard(firstName: 'Ivan', lastName: 'Petrov', phone: '+15551234567', email: 'ivan@example.com'),
    $design->geo(55.751244, 37.618423, 'Red Square'),
    $design->bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.0012', 'Shop'),
], '#bae6fd');
