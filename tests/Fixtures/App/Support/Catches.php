<?php

namespace App\Support;

use Exception;
use RuntimeException;
use Throwable;

final class Catches
{
    public function run(callable $callback): mixed
    {
        try {
            $callback();
        } catch (Throwable) {
        }

        try {
            $callback();
        } catch (Exception $exception) {
            report($exception);
        }

        try {
            $callback();
        } catch (RuntimeException|Throwable $exception) {
            $retry = function () use ($exception): void {
                throw $exception;
            };
        }

        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }

        try {
            $callback();
        } catch (Exception $exception) {
            if ($exception->getCode() === 0) {
                throw new RuntimeException('Wrapped', previous: $exception);
            }
        }

        try {
            return $callback();
        } catch (RuntimeException) {
            return null;
        }
    }
}
