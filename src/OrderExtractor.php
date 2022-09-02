<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\Magento2;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Bucket\ResultBucketInterface;

final class OrderExtractor implements \Kiboko\Contract\Pipeline\ExtractorInterface
{
    private array $queryParameters = [
        'searchCriteria[currentPage]' => 1,
        'searchCriteria[pageSize]' => 100,
    ];

    public function __construct(
        private \Psr\Log\LoggerInterface $logger,
        private \Kiboko\Magento\V2_1\Client|\Kiboko\Magento\V2_2\Client|\Kiboko\Magento\V2_3\Client|\Kiboko\Magento\V2_4\Client $client,
        private int $pageSize = 100,
    ) {
    }

    public function withFilterGroup(FilterGroup $group): self
    {
        $this->queryParameters['searchCriteria[filter_groups]'][] = $group->asArray();

        return $this;
    }

    private function compileQueryParameters(int $currentPage = 1): array
    {
        $parameters = $this->queryParameters;
        $parameters['searchCriteria[currentPage]'] = $currentPage;
        $parameters['searchCriteria[pageSize]'] = $this->pageSize;

        return $parameters;
    }

    public function extract(): iterable
    {
        try {
            $response = $this->client->salesOrderRepositoryV1GetListGet(
                queryParameters: $this->compileQueryParameters(),
            );

            yield $this->processResponse($response);

            $currentPage = 1;
            $pageCount = ceil($response->getTotalCount() / $this->pageSize);
            while ($currentPage++ < $pageCount) {
                $response = $this->client->salesOrderRepositoryV1GetListGet(
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
        return new AcceptanceResultBucket(...$response->getItems());
    }
}