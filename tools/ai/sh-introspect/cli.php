<?php
declare(strict_types=1);

function shIntrospectMain(array $argv): int
{
    // TODO: Move CLI parsing, usage, --help, --all, error handling here.
    return 0;
}

function shIntrospectUsage(): string
{
    // TODO: Move usage renderer entrypoint here.
    return '';
}

function shIntrospectFail(string $message, int $code = 1): int
{
    fwrite(STDERR, $message . PHP_EOL);
    return $code;
}
