<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Hash
{
    public static function simple(string $data): string
    {
        return md5($data);
    }
}
