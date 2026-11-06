<?php
declare(strict_types=1);

function format_utc_date(string $value, string $format = 'M j, Y'): string
{
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format($format);
}
