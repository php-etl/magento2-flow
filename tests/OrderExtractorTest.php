<?php

declare(strict_types=1);

namespace Tests\Kiboko\Magento\V2\Extractor;

use Kiboko\Component\Flow\Magento2\OrderExtractor;
use Kiboko\Component\PHPUnitExtension\Assert\ExtractorAssertTrait;
use Kiboko\Component\PHPUnitExtension\PipelineRunner;
use Kiboko\Contract\Pipeline\PipelineRunnerInterface;
use Kiboko\Magento\V2_3\Model\SalesDataOrderInterface;
use Kiboko\Magento\V2_3\Model\SalesDataOrderSearchResultInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OrderExtractorTest extends TestCase
{
    use ExtractorAssertTrait;

    public function testIsSuccessful(): void
    {
        $order = (new SalesDataOrderInterface())
            ->setEntityId(1)
            ->setCustomerId(10)
            ->setTotalQtyOrdered(3);

        $client = $this->createMock(\Kiboko\Magento\V2_3\Client::class);
        $client
            ->expects($this->once())
            ->method('salesOrderRepositoryV1GetListGet')
            ->willReturn(
                (new SalesDataOrderSearchResultInterface)
                    ->setItems([
                        $order
                    ])
                    ->setTotalCount(1)
            );

        $extractor = new OrderExtractor(
            new NullLogger(),
            $client,
        );

        $this->assertExtractorExtractsExactly(
            [
                $order
            ],
            $extractor
        );
    }

    public function pipelineRunner(): PipelineRunnerInterface
    {
        return new PipelineRunner();
    }
}
