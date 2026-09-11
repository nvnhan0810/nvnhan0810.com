<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SlugHelpers {
    public static function createFromString(string $value, int $index = 0): string {
        $result = Str::slug($value, '-');

        if ($index) {
            $result .= "-{$index}";
        }

        return $result;
    }
}
