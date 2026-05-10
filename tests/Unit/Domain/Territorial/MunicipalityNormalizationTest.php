<?php

declare(strict_types=1);

use App\Domain\Territorial\Models\Municipality;

it('normalizes municipality names', function () {
    expect(Municipality::normalize('Vaprio d\'Adda'))->toBe("vaprio d'adda");
    expect(Municipality::normalize('Reggio nell\'Emilia'))->toBe("reggio nell'emilia");
    expect(Municipality::normalize('Forlì'))->toBe('forli');
    expect(Municipality::normalize('  ROMA  '))->toBe('roma');
    expect(Municipality::normalize("Sant\u{2019}Antonio"))->toBe("sant'antonio");
});
