<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Ui\Component\Form\Element;

use Panth\FilterSeo\Ui\Component\Form\Element\StoreOptions;
use PHPUnit\Framework\TestCase;

class StoreOptionsTest extends TestCase
{
    public function testAllStoreViewsIsPrependedOnceAsValueZero(): void
    {
        $tree = ['label' => 'Main Website', 'value' => [['label' => 'Store', 'value' => [['label' => 'Default', 'value' => '1']]]]];
        $options = $this->getMockBuilder(StoreOptions::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['generateCurrentOptions'])
            ->getMock();
        $options->expects($this->once())->method('generateCurrentOptions')->willReturnCallback(
            function () use ($options, $tree) {
                $property = new \ReflectionProperty($options, 'currentOptions');
                $property->setValue($options, ['Main Website' => $tree]);
            }
        );

        $expected = [['label' => 'All Store Views', 'value' => '0'], $tree];
        $this->assertSame($expected, $options->toOptionArray());
        $this->assertSame($expected, $options->toOptionArray());
    }
}
