<?php

namespace EasyBib\Tests\QPush;

use EasyBib\QPush\ProviderRegistry;
use Uecode\Bundle\QPushBundle\Provider\ProviderInterface;

class ProviderRegistryTest extends \PHPUnit_Framework_TestCase
{
    public function testDefault()
    {
        $providerName = 'provider';
        $provider = $this->getMockBuilder(ProviderInterface::class)->getMock();
        $registry = new ProviderRegistry();
        $registry->addProvider($providerName, $provider);

        $this->assertTrue($registry->has($providerName));
        $this->assertInstanceOf(ProviderInterface::class, $registry->get($providerName));
    }

    public function testWithSuffix()
    {
        $suffix = '-suffix';
        $providerName = 'provider';
        $provider = $this->getMockBuilder(ProviderInterface::class)->getMock();
        $registry = new ProviderRegistry($suffix);
        $registry->addProvider($providerName . $suffix, $provider);

        // Can fetch provider without passing the suffix.
        $this->assertTrue($registry->has($providerName));
        $this->assertInstanceOf(ProviderInterface::class, $registry->get($providerName));

        // Can't fetch provider while passing the suffix
        $this->assertFalse($registry->has($providerName . $suffix));
    }
}
