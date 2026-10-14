<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Str;
use Tests\TestCase;

final class ContentTypeOptionsHeaderTest extends TestCase
{
    public function test_successful_and_error_responses_carry_nosniff(): void
    {
        $responses = [
            'created' => $this->postJson('/api/invoices', [
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'product_lines' => [],
            ]),
            'validation error' => $this->postJson('/api/invoices', []),
            'unknown invoice' => $this->getJson('/api/invoices/'.Str::uuid()->toString()),
            'webhook' => $this->get('/api/notification/hook/delivered/'.Str::uuid()->toString()),
            'unknown route' => $this->getJson('/api/does-not-exist'),
        ];

        foreach ($responses as $case => $response) {
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $case);
        }
    }
}
