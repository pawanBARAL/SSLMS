<?php

function valid_month($value): bool
{
    return is_string($value)
        && preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/', $value) === 1
        && checkdate((int) substr($value, 5, 2), 1, (int) substr($value, 0, 4));
}
