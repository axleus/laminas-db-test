<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Db\Sql\ExpressionInterface;
use Laminas\Db\Sql\SqlInterface;
use InvalidArgumentException;

use function current;
use function is_array;
use function is_int;
use function is_scalar;
use function key;

enum NormalizedArgument: string
{
    case Identifier = 'identifier';
    case Value      = 'value';
    case Literal    = 'literal';
    case Select     = 'select';

    public static function buildArgument(mixed $argument, NormalizedArgument|string $type = 'value'): array
    {
        if ($type instanceof NormalizedArgument) {
            return [$argument, $type->value];
        }
        $self = self::tryFrom($type);
        if ($self === null) {
            throw new InvalidArgumentException('Invalid argument type');
        }

        return match (true) {
            $argument instanceof ExpressionInterface,
            $argument instanceof SqlInterface,       => [$argument, self::Value->value],
            is_scalar($argument), $argument === null => [$argument, $self->value],
            is_array($argument)                      => (function () use ($argument, $self) {
                $value = current($argument);
                if ($value instanceof ExpressionInterface || $value instanceof SqlInterface) {
                    return [$argument, self::Value->value];
                }

                $key = key($argument);
                if (is_int($key) && self::tryFrom($value) === null) {
                    return [$value, $self->value];
                }
                return self::buildArgument($key, $value);
            })($argument, $self),
            default => throw new InvalidArgumentException('Invalid argument type'),
        };
    }
}
