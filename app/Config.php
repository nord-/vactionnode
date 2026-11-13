<?php
namespace App;

final class Config
{
    /** Läser config.php i repo-roten (gitignorerad, se config.php.example). */
    public static function load(): array
    {
        return require dirname(__DIR__) . '/config.php';
    }
}
