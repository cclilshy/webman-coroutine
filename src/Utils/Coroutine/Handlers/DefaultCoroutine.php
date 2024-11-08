<?php
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
declare(strict_types=1);

namespace Workbunny\WebmanCoroutine\Utils\Coroutine\Handlers;

use Throwable;

class DefaultCoroutine implements CoroutineInterface
{
    /**
     * @var string|null
     */
    protected ?string $id = null;

    /** @inheritdoc  */
    public function __construct(\Closure $func)
    {
        call_user_func($func, $this->id = spl_object_hash($func));
        $this->id = null;
    }

    /** @inheritdoc  */
    public function __destruct()
    {
    }

    /** @inheritdoc  */
    public function origin(): mixed
    {
        return null;
    }

    /** @inheritdoc  */
    public function id(): ?string
    {
        return $this->id;
    }

    /** @inheritdoc  */
    public function kill(Throwable $throwable): void
    {
    }
}
