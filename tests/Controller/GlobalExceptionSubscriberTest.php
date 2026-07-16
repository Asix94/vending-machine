<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GlobalExceptionSubscriberTest extends WebTestCase
{
    public function testUnexpectedExceptionReturnsGeneric500Json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_test/runtime-exception');

        self::assertResponseStatusCodeSame(500);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['error' => 'internal_server_error', 'message' => 'Internal server error.'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
