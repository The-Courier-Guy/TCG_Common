<?php

namespace Tcg\Common\Shopify\Services\Product;

use App\Models\ProductDimension;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class BulkProductUpdate
{
    /**
     * Handle bulk update request for product dimensions
     *
     * @param Request $request
     *
     * @return Application|ResponseFactory|Response
     * */
    public function handle(Request $request): Application|ResponseFactory|Response
    {
        try {
            $shop     = $request->input('shop');
            $products = $request->input('products');

            Log::debug('Bulk Import Data Received:', [
                'shop'          => $shop,
                'product_count' => count($products),
                'products'      => $products
            ]);

            $result = $this->processBulkImport($products, $shop);

            return response($result, $result['status'] ? 200 : 422);
        } catch (Exception $e) {
            Log::error('Bulk Product Update Error: ' . $e->getMessage(), [
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response([
                                'message' => 'An error occurred during bulk update',
                                'status'  => false
                            ], 500);
        }
    }

    /**
     * Process bulk update of existing product dimensions
     *
     * @param array $products
     * @param string $shop
     *
     * @return array
     */
    private function processBulkImport(array $products, string $shop): array
    {
        $results = ['updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($products, $shop, &$results) {
                foreach ($products as $productData) {
                    $this->processIndividualProduct($productData, $shop, $results);
                }
            });

            return $this->buildResponse($products, $results);
        } catch (Throwable $e) {
            Log::error('Bulk update transaction failed: ' . $e->getMessage());

            return $this->buildFailureResponse($products);
        }
    }

    /**
     * Process a single product update
     *
     * @param array $productData
     * @param string $shop
     * @param array &$results
     *
     * @return void
     */
    private function processIndividualProduct(array $productData, string $shop, array &$results): void
    {
        try {
            $existing = $this->findExistingProduct($productData, $shop);

            if ($existing) {
                $this->updateExistingProduct($existing, $productData);
                $results['updated']++;

                return;
            }

            $this->skipNonExistentProduct($productData, $shop);
            $results['skipped']++;
        } catch (Exception $e) {
            $this->handleProductError($productData, $shop, $e, $results);
        }
    }

    /**
     * Find existing product dimension record
     *
     * @param array $productData
     * @param string $shop
     *
     * @return ProductDimension|null
     */
    private function findExistingProduct(array $productData, string $shop): ?ProductDimension
    {
        return ProductDimension::where('shop', $shop)
                               ->where('product_id', $productData['product_id'])
                               ->where('variant_id', $productData['variant_id'])
                               ->first();
    }

    /**
     * Update existing product with new dimensions
     *
     * @param ProductDimension $existing
     * @param array $productData
     *
     * @return void
     */
    private function updateExistingProduct(ProductDimension $existing, array $productData): void
    {
        $data = [
            'length'             => (int)$productData['length'],
            'width'              => (int)$productData['width'],
            'height'             => (int)$productData['height'],
            'single_parcel_item' => (bool)$productData['single_parcel_item'],
            'updated_at'         => now(),
        ];

        $existing->update($data);
    }

    /**
     * Log skipped product information
     *
     * @param array $productData
     * @param string $shop
     *
     * @return void
     */
    private function skipNonExistentProduct(array $productData, string $shop): void
    {
        Log::info('Skipping non-existent product', [
            'product_id' => $productData['product_id'],
            'variant_id' => $productData['variant_id'],
            'shop'       => $shop
        ]);
    }

    /**
     * Handle individual product processing error
     *
     * @param array $productData
     * @param string $shop
     * @param Exception $e
     * @param array &$results
     *
     * @return void
     */
    private function handleProductError(array $productData, string $shop, Exception $e, array &$results): void
    {
        $error               = "Product {$productData['product_id']} - Variant {$productData['variant_id']}: " . $e->getMessage(
            );
        $results['errors'][] = $error;

        Log::warning('Individual product update failed', [
            'shop'       => $shop,
            'product_id' => $productData['product_id'] ?? 'unknown',
            'variant_id' => $productData['variant_id'] ?? 'unknown',
            'error'      => $e->getMessage()
        ]);
    }

    /**
     * Build success response with results
     *
     * @param array $products
     * @param array $results
     *
     * @return array
     */
    private function buildResponse(array $products, array $results): array
    {
        $response = [
            'message' => 'Bulk update completed',
            'status'  => true,
            'summary' => [
                'total_products' => count($products),
                'updated'        => $results['updated'],
                'skipped'        => $results['skipped'],
                'processed'      => $results['updated'],
                'failed'         => count($results['errors'])
            ]
        ];

        if (!empty($results['errors'])) {
            $response['errors']  = $results['errors'];
            $response['message'] .= ' with some errors';
        } else {
            $response['message'] .= ' successfully';
        }

        if ($results['skipped'] > 0) {
            $response['message'] .= " ({$results['skipped']} products skipped - not found in database)";
        }

        Log::info('Bulk update completed', $response['summary']);

        return $response;
    }

    /**
     * Build failure response for transaction errors
     *
     * @param array $products
     *
     * @return array
     */
    private function buildFailureResponse(array $products): array
    {
        return [
            'message' => 'Bulk update failed',
            'status'  => false,
            'summary' => [
                'total_products' => count($products),
                'updated'        => 0,
                'skipped'        => 0,
                'processed'      => 0,
                'failed'         => count($products)
            ]
        ];
    }
}
