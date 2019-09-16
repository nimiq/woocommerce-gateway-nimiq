<?php

declare(strict_types = 1);

namespace Nimiq\Utils;

class JSONUtils
{
    public static function stringify($value) {
        // TODO: Handle binary data in value

        return json_encode($value);
    }
}
