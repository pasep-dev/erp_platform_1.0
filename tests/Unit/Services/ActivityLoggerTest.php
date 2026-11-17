<?php

namespace Tests\Unit\Services;

use App\Services\ActivityLogger;
use PHPUnit\Framework\TestCase;

class ActivityLoggerTest extends TestCase
{
    public function test_sensitive_values_are_redacted_recursively_without_changing_other_values(): void
    {
        $logger = new ActivityLogger;

        $safeValues = $logger->sanitizeValues([
            'password' => 'raw-password',
            'settings' => [
                'access_key' => 'cloud-secret',
                'api_token' => 'nested-secret',
                'display_name' => 'Warehouse',
            ],
        ]);

        $this->assertSame([
            'password' => '[REDACTED]',
            'settings' => [
                'access_key' => '[REDACTED]',
                'api_token' => '[REDACTED]',
                'display_name' => 'Warehouse',
            ],
        ], $safeValues);
    }
}
