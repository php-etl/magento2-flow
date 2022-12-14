<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\Magento2;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Contract\Bucket\ResultBucketInterface;

final class ProductExtractor implements \Kiboko\Contract\Pipeline\ExtractorInterface
{
    private array $queryParameters = [
        'searchCriteria[currentPage]' => 1,
        'searchCriteria[pageSize]' => 100,
    ];

    public function __construct(
        private \Psr\Log\LoggerInterface $logger,
        private \Kiboko\Magento\V2_1\Client|\Kiboko\Magento\V2_2\Client|\Kiboko\Magento\V2_3\Client|\Kiboko\Magento\V2_4\Client $client,
        private int $pageSize = 100,
        /** @var FilterGroup[] $filters */
        private array $filters = [],
    ) {
    }

    private function compileQueryParameters(int $currentPage = 1): array
    {
        $parameters = $this->queryParameters;
        $parameters['searchCriteria[currentPage]'] = $currentPage;
        $parameters['searchCriteria[pageSize]'] = $this->pageSize;

        $filters = array_map(fn (FilterGroup $item, int $key) => $item->compileFilters($key), $this->filters, array_keys($this->filters));
        return array_merge($parameters, ...$filters);
    }

    public function extract(): iterable
    {
        try {
            $response = $this->client->catalogProductRepositoryV1GetListGet(
                queryParameters: $this->compileQueryParameters(),
            );

            if (!$response instanceof \Kiboko\Magento\V2_1\Model\CatalogDataProductSearchResultsInterface
                && !$response instanceof \Kiboko\Magento\V2_2\Model\CatalogDataProductSearchResultsInterface
                && !$response instanceof \Kiboko\Magento\V2_3\Model\CatalogDataProductSearchResultsInterface
                && !$response instanceof \Kiboko\Magento\V2_4\Model\CatalogDataProductSearchResultsInterface
            ) {
                return;
            }

            yield $this->processResponse($response);

            $currentPage = 1;
            $pageCount = ceil($response->getTotalCount() / $this->pageSize);
            while ($currentPage++ < $pageCount) {
                $response = $this->client->catalogProductRepositoryV1GetListGet(
                    queryParameters: $this->compileQueryParameters($currentPage),
                );

                yield $this->processResponse($response);
            }
        } catch (\Exception $exception) {
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
        }
    }

    private function processResponse($response): ResultBucketInterface
    {
        if ($response instanceof \Kiboko\Magento\V2_1\Model\ErrorResponse
            || $response instanceof \Kiboko\Magento\V2_2\Model\ErrorResponse
            || $response instanceof \Kiboko\Magento\V2_3\Model\ErrorResponse
            || $response instanceof \Kiboko\Magento\V2_4\Model\ErrorResponse
        ) {
            return new RejectionResultBucket($response);
        }

        return new AcceptanceResultBucket(...$response->getItems());
    }
}
