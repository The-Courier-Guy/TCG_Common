<?php

require_once __DIR__ . '/Support/LogStub.php';

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Tcg\Common\Shopify\Services\Product\ShopifyProductService;

class BulkOperationPollingTest extends TestCase
{
    private const SHOP = 'example.myshopify.com';

    protected function setUp(): void
    {
        Log::$records = [];
    }

    public function testReturnsTheStartedOperationId()
    {
        $result = ['data' => ['bulkOperationRunQuery' => [
            'bulkOperation' => ['id' => 'gid://shopify/BulkOperation/1', 'status' => 'CREATED'],
            'userErrors'    => [],
        ]]];

        $this->assertSame(
            'gid://shopify/BulkOperation/1',
            ShopifyProductService::bulkOperationIdFromStartResult($result, self::SHOP)
        );
        $this->assertSame([], Log::$records);
    }

    public function testLogsAndReturnsNullWhenShopifyRejectsTheQuery()
    {
        $result = ['data' => ['bulkOperationRunQuery' => [
            'bulkOperation' => null,
            'userErrors'    => [['field' => ['query'], 'message' => 'Invalid bulk query']],
        ]]];

        $this->assertNull(ShopifyProductService::bulkOperationIdFromStartResult($result, self::SHOP));
        $this->assertSame('error', Log::$records[0][0]);
        $this->assertSame('Invalid bulk query', Log::$records[0][2]['user_errors'][0]['message']);
    }

    public function testLogsAndReturnsNullOnTopLevelGraphqlErrors()
    {
        $result = ['errors' => [['message' => 'Access denied for bulkOperationRunQuery field.']]];

        $this->assertNull(ShopifyProductService::bulkOperationIdFromStartResult($result, self::SHOP));
        $this->assertSame('Access denied for bulkOperationRunQuery field.', Log::$records[0][2]['errors'][0]['message']);
    }

    public function testReturnsTheOperationOnceCompleted()
    {
        $responses = [
            ['status' => 'CREATED'],
            ['status' => 'RUNNING'],
            ['status' => 'COMPLETED', 'url' => 'https://example.com/results.jsonl'],
        ];
        $sleeps    = [];

        $operation = ShopifyProductService::waitForBulkOperation(
            function () use (&$responses) {
                return array_shift($responses);
            },
            self::SHOP,
            120,
            5,
            function (int $seconds) use (&$sleeps) {
                $sleeps[] = $seconds;
            }
        );

        $this->assertSame('https://example.com/results.jsonl', $operation['url']);
        $this->assertSame([5, 5], $sleeps);
    }

    public function testStopsWhenTheOperationIsNotFound()
    {
        $calls = 0;

        $operation = ShopifyProductService::waitForBulkOperation(
            function () use (&$calls) {
                $calls++;

                return [];
            },
            self::SHOP,
            120,
            5,
            function () {
                $this->fail('Should not wait when no operation is found.');
            }
        );

        $this->assertNull($operation);
        $this->assertSame(1, $calls);
        $this->assertSame('error', Log::$records[0][0]);
    }

    public function testStopsWhenTheOperationFails()
    {
        $operation = ShopifyProductService::waitForBulkOperation(
            fn () => ['status' => 'FAILED', 'errorCode' => 'INTERNAL_SERVER_ERROR'],
            self::SHOP,
            120,
            5,
            fn () => null
        );

        $this->assertNull($operation);
        $this->assertSame('INTERNAL_SERVER_ERROR', Log::$records[0][2]['error_code']);
    }

    public function testGivesUpAfterTheTimeout()
    {
        $calls = 0;

        $operation = ShopifyProductService::waitForBulkOperation(
            function () use (&$calls) {
                $calls++;

                return ['status' => 'RUNNING'];
            },
            self::SHOP,
            20,
            5,
            fn () => null
        );

        $this->assertNull($operation);
        $this->assertSame(5, $calls); // at 0, 5, 10, 15 and 20 seconds
        $this->assertSame('Product bulk operation timed out.', Log::$records[0][1]);
    }
}
