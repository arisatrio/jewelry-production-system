<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class StoreStockSpkSyncException extends Exception
{
    public static function failed(Throwable $previous): self
    {
        return new self('Gagal mengirim nomor SPK ke Store. Silakan coba lagi.', previous: $previous);
    }
}
