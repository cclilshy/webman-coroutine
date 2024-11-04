<?php

declare(strict_types=1);

namespace Workbunny\Tests\HandlersCase;

use Mockery;
use Workbunny\Tests\TestCase;
use Workbunny\WebmanCoroutine\Exceptions\TimeoutException;
use Workbunny\WebmanCoroutine\Handlers\RippleHandler;

class RippleHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testIsAvailable()
    {
        $rippleHandlerMock = Mockery::mock(RippleHandler::class . '[_getWorkerVersion]');
        $rippleHandlerMock->shouldAllowMockingProtectedMethods();
        $rippleHandlerMock->shouldReceive('_getWorkerVersion')->andReturn('5.0.0');
        $this->assertFalse($rippleHandlerMock::isAvailable());
        // todo 可用的情况
    }

    public function testInitEnv()
    {
        RippleHandler::initEnv();
        $this->assertTrue(true);
    }

    public function testWaitFor()
    {
        $rippleHandlerMock = Mockery::mock(RippleHandler::class . '[_sleep]');
        $rippleHandlerMock->shouldAllowMockingProtectedMethods();
        $rippleHandlerMock->shouldReceive('_sleep')->andReturnNull();

        $return = false;
        $rippleHandlerMock::waitFor(function () use (&$return) {
            return $return = true;
        });
        $this->assertTrue($return);

        $return = false;
        $rippleHandlerMock::waitFor(function () use (&$return) {
            sleep(1);

            return $return = true;
        });
        $this->assertTrue($return);
        // 模拟超时
        $this->expectException(TimeoutException::class);
        $rippleHandlerMock::waitFor(function () use (&$return) {
            return false;
        }, 1);
        $this->assertFalse($return);
    }

    /**
     * @return void
     */
    public function testSleep()
    {
        $suspensionMock = Mockery::mock('alias:\Revolt\EventLoop\Suspension');
        $suspensionMock->shouldReceive('resume')->andReturnNull();
        $suspensionMock->shouldReceive('suspend')->andReturnNull();

        $eventLoopMock = Mockery::mock('alias:\Revolt\EventLoop');
        $eventLoopMock->shouldReceive('getSuspension')->andReturn($suspensionMock);
        $eventLoopMock->shouldReceive('defer')->andReturnUsing(function ($closure) {
            $closure();
        });
        $eventLoopMock->shouldReceive('delay')->andReturnUsing(function ($timeout, $closure) {
            $closure();
        });

        RevoltHandler::sleep();
        $this->assertTrue(true);

        RevoltHandler::sleep(0.001);
        $this->assertTrue(true);

        RevoltHandler::sleep(0.000001);
        $this->assertTrue(true);

        RevoltHandler::sleep(event: __METHOD__);
        $this->assertTrue(true);
    }

    public function testWakeup()
    {
        RippleHandler::wakeup(__METHOD__);
        $this->assertTrue(true);
    }
}
