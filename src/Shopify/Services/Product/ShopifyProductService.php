<?php

namespace Tcg\Common\Shopify\Services\Product;

use App\Models\ProductDimension;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Shopify\Clients\Graphql;
use Shopify\Exception\MissingArgumentException;

class ShopifyProductService
{
    /**
     * @param $session
     * @param $shop
     *
     * @return array
     */
    public static function getAllProducts($session, $shop): array
    {
        $products = [];

        $client = new Graphql($shop, $session->getAccessToken());

        $bulkQueryMutation = <<<'GRAPHQL'
mutation {
  bulkOperationRunQuery(
   query: """
    {
      products {
        edges {
          node {
            id
            title
            variants {
                                    edges {
                                        node {
                                            id
                                            title
                                            price
                                            sku
                                            inventoryItem {
                                                measurement {
                                                  weight {
                                                    value
                                                    unit
                                                  }
                                                }
                                            }
                                        }
                                    }
                                }
          }
        }
      }
    }
    """
  ) {
    bulkOperation {
      id
      status
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;
        $response = $client->query([
            'query' => $bulkQueryMutation,
        ]);
        $result   = $response->getDecodedBody();

        $operationId = self::bulkOperationIdFromStartResult($result, $shop);
        if ($operationId === null) {
            return [];
        }

        $operationQuery = <<<'GRAPHQL'
query BulkOperationStatus($id: ID!) {
  node(id: $id) {
    ... on BulkOperation {
      id
      status
      errorCode
      objectCount
      url
    }
  }
}
GRAPHQL;

        $operation = self::waitForBulkOperation(
            function () use ($client, $operationQuery, $operationId): array {
                $body = $client->query([
                    'query'     => $operationQuery,
                    'variables' => ['id' => $operationId],
                ])->getDecodedBody();

                return $body['data']['node'] ?? [];
            },
            $shop
        );
        if ($operation === null) {
            return [];
        }

        $url = $operation['url'] ?? null;
        if (!$url) {
            // A completed export with no URL means the shop has no products.
            Log::info('Bulk operation completed with no results URL.', [
                'shop'         => $shop,
                'object_count' => $operation['objectCount'] ?? null,
            ]);

            return [];
        }

        $response = Http::timeout(300)->get($url);
        if (!$response->successful()) {
            Log::error("Failed to download bulk operation results from URL: $url", [
                'shop'   => $shop,
                'status' => $response->status(),
                'body'   => $response->body()
            ]);

            return [];
        }
        $lines = explode("\n", $response->body());
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $product = json_decode($line, true);
            if (str_contains($product['id'] ?? '', 'gid://shopify/Product/')) {
                $product['product_id'] = str_replace('gid://shopify/Product/', '', $product['id']);
                $product['variants']   = []; // Initialize variants structure
                $products[]            = $product;
            } elseif (str_contains($product['id'] ?? '', 'gid://shopify/ProductVariant/')) {
                $prod                           = $products[count($products) - 1];
                $product['product_id']          = $prod['product_id'];
                $product['variant_id']          = str_replace('gid://shopify/ProductVariant/', '', $product['id']);
                $prod['variants'][]             = $product;
                $products[count($products) - 1] = $prod; // Update the last product with its variants
            } else {
                Log::warning("Unexpected line in bulk operation results: " . $line, ['shop' => $shop]);
            }
        }

        return $products;
    }

    /**
     * @param $products
     * @param $shop
     *
     * @return void
     */
    public static function saveProductDimensions($products, $shop): void
    {
        $newKeys         = [];
        $recordsToInsert = [];
        $recordsToUpdate = [];
        $existingKeys    = [];
        $existingRecords    = [];
        DB::table('product_dimensions')
          ->select('id')
          ->selectRaw(
              "MD5(
                                                   CONCAT(product_title, '-', variant_title , '-', sku, '-',
            grams)) as `hash`"
          )
          ->selectRaw("CONCAT(product_id, '-', variant_id) as `key`")
          ->orderBy('id')->chunkById(100, function ($records) use (&$existingKeys, &$existingRecords) {
                $mapped = $records->mapWithKeys(function ($item) {
                    return [
                        $item->key => [
                            'id'   => $item->id,
                            'hash' => $item->hash,
                        ]
                    ];
                })->toArray();
                $existingRecords = array_merge($existingRecords ?? [], $mapped);
                $existingKeys = array_merge($existingKeys, array_keys($mapped));
          });

        foreach ($products as $product) {
            $productId = $product['product_id'];
            foreach ($product['variants'] ?? [] as $variant) {
                $variantId = $variant['variant_id'];
                if (!$productId || !$variantId) {
                    Log::error("Product ID or Variant ID is missing.", ['product_data' => $product]);
                    continue;
                }

                $key       = $productId . '-' . $variantId;
                $newKeys[] = $key;

                // Convert weight to grams if incoming unit is kilograms
                $weight = $variant['inventoryItem']['measurement']['weight'] ?? null;
                $grams  = null;
                if (is_array($weight) && isset($weight['value'])) {
                    $grams = $weight['value'];
                    if (($weight['unit'] ?? '') === 'KILOGRAMS') {
                        $grams = $grams * 1000;
                    }
                }

                $data = [
                    'shop'          => $shop,
                    'product_id'    => $productId,
                    'variant_id'    => $variantId,
                    'product_title' => $product['title'] ?? '',
                    'variant_title' => $variant['title'] ?? '',
                    'sku'           => $variant['sku'] ?? '',
                ];

                // Only set grams when we actually have a value
                if ($grams !== null) {
                    $data['grams'] = $grams;
                }

                // Check if record exists
                if (in_array($key, $existingKeys)) {
                    $id         = $existingRecords[$key]['id'];
                    $hash       = $existingRecords[$key]['hash'];
                    $newHash    = md5(
                        implode('-', [
                            $data['product_title'] ?? '',
                            $data['variant_title'] ?? '',
                            $data['sku'] ?? '',
                            $data['grams'] ?? '',
                        ])
                    );
                    $updateData = [];

                    if ($hash !== $newHash) {
                        $updateData['product_title'] = $data['product_title'];
                        $updateData['variant_title'] = $data['variant_title'];
                        $updateData['sku']           = $data['sku'];
                        $updateData['grams']         = $data['grams'];
                    }

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        $recordsToUpdate[$id]     = $updateData;
                    }
                } else {
                    // Prepare for batch insert
                    $data['created_at'] = now();
                    $data['updated_at'] = now();
                    $recordsToInsert[]  = $data;
                }
            }
        }

        // Batch insert new records
        if (!empty($recordsToInsert)) {
            collect($recordsToInsert)->chunk(100)->each(function ($chunk) {
                ProductDimension::insert($chunk->toArray());
            });
        }

        // Batch update existing records
        if (!empty($recordsToUpdate)) {
            foreach ($recordsToUpdate as $id => $updateData) {
                DB::table('product_dimensions')
                  ->where('id', $id)
                  ->update($updateData);
            }
        }

        // Delete dimensions that are no longer needed - use more efficient approach
        $keysToDelete = array_diff($existingKeys, $newKeys);
        if (!empty($keysToDelete)) {
            $idsToDelete = [];
            foreach ($keysToDelete as $key) {
                $idsToDelete[] = $existingRecords[$key]['id'];
            }

            if (!empty($idsToDelete)) {
                ProductDimension::whereIn('id', $idsToDelete)->delete();
            }
        }
    }

    /**
     * Returns the id of the bulk operation that bulkOperationRunQuery started, or null (and logs why) when Shopify
     * did not start one.
     */
    public static function bulkOperationIdFromStartResult(?array $result, string $shop): ?string
    {
        $errors     = $result['errors'] ?? [];
        $userErrors = $result['data']['bulkOperationRunQuery']['userErrors'] ?? [];
        $operation  = $result['data']['bulkOperationRunQuery']['bulkOperation'] ?? null;

        if (!empty($errors) || !empty($userErrors) || empty($operation['id'])) {
            Log::error('Shopify did not start the product bulk operation.', [
                'shop'        => $shop,
                'errors'      => $errors,
                'user_errors' => $userErrors,
            ]);

            return null;
        }

        return $operation['id'];
    }

    /**
     * Polls a bulk operation until it completes. Returns the operation on COMPLETED, or null (and logs why) when it
     * fails, cannot be read, or does not finish within $timeoutSeconds.
     *
     * @param callable(): array $fetchOperation Returns the BulkOperation node as an array (empty when not found).
     * @param callable(int): void|null $sleep  Injected for tests; defaults to sleep().
     */
    public static function waitForBulkOperation(
        callable $fetchOperation,
        string $shop,
        int $timeoutSeconds = 120,
        int $intervalSeconds = 5,
        ?callable $sleep = null
    ): ?array {
        $sleep   = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
        $elapsed = 0;

        while (true) {
            $operation = $fetchOperation();
            $status    = $operation['status'] ?? null;

            if ($status === 'COMPLETED') {
                return $operation;
            }

            if (!in_array($status, ['CREATED', 'RUNNING', 'CANCELING'], true)) {
                // FAILED, CANCELED, EXPIRED, or no operation found at all.
                Log::error('Product bulk operation did not complete.', [
                    'shop'       => $shop,
                    'status'     => $status,
                    'error_code' => $operation['errorCode'] ?? null,
                ]);

                return null;
            }

            if ($elapsed >= $timeoutSeconds) {
                Log::error('Product bulk operation timed out.', [
                    'shop'            => $shop,
                    'status'          => $status,
                    'timeout_seconds' => $timeoutSeconds,
                ]);

                return null;
            }

            $sleep($intervalSeconds);
            $elapsed += $intervalSeconds;
        }
    }

    /**
     * @param $gid
     *
     * @return string
     */
    public static function extractId($gid): string
    {
        preg_match('/\d+/', $gid, $matches);

        return $matches[0];
    }

    /**
     * @throws MissingArgumentException
     * @throws \JsonException
     */
    public static function getVariantsByIds($session, array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        $client = new Graphql($session->getShop(), $session->getAccessToken());

        // Shopify GraphQL requires IDs in global ID (gid) format
        $globalVariantIds = array_map(
            fn($id) => "gid://shopify/ProductVariant/{$id}",
            $variantIds
        );

        $query = <<<'QUERY'
        query getVariantsByIds($ids: [ID!]!) {
            nodes(ids: $ids) {
                ... on ProductVariant {
                    id
                    title
                    sku
                    price
                    inventoryQuantity
                    product {
                        id
                        title
                    }
                }
            }
        }
        QUERY;

        try {
            $response = $client->query([
                'query'     => $query,
                'variables' => ['ids' => $globalVariantIds],
            ]);

            $decoded = $response->getDecodedBody();

            // Return clean array of variants
            return $decoded['data']['nodes'] ?? [];
        } catch (\Exception $e) {
            Log::error('Shopify getVariantsByIds error', [
                'error' => $e->getMessage(),
                'ids'   => $variantIds,
            ]);

            return [];
        }
    }
}
